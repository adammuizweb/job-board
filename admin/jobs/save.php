<?php
declare(strict_types=1);

require dirname(__DIR__) . '/common.php';
$route = trim(jyb_scalar($_GET['page'] ?? ''), '/');
$creating = $route === 'admin/tools/job-board/jobs/create-save';
$permission = $creating ? JYB_PERMISSION_CREATE : JYB_PERMISSION_EDIT;
$jybActorId = jyb_admin_guard($pdo, $permission);
jyb_admin_require_post();
$id = $creating ? 0 : jyb_positive_id($_POST['id'] ?? 0);
$version = jyb_bounded_int($_POST['version'] ?? 0, 0, PHP_INT_MAX, 0);

try {
    if (!$creating && $id < 1) throw new DomainException(jyb_t('The selected job is unavailable.'));
    $companyId = jyb_positive_id($_POST['company_id'] ?? 0);
    if ($companyId < 1) throw new DomainException(jyb_t('Select a company.'));
    $sourceLocale = jyb_locale($_POST['source_locale'] ?? '');
    if ($sourceLocale === null || !in_array($sourceLocale, jyb_content_locales($pdo), true)) {
        throw new DomainException(jyb_t('Enter a valid source locale.'));
    }
    $employment = jyb_scalar($_POST['employment_type'] ?? '');
    $workplace = jyb_scalar($_POST['workplace_type'] ?? '');
    if (!in_array($employment, jyb_employment_types(), true) || !in_array($workplace, jyb_workplace_types(), true)) {
        throw new DomainException(jyb_t('Select valid employment and workplace types.'));
    }
    $application = jyb_application_target($_POST['application_type'] ?? '', $_POST['application_target'] ?? '');
    if ($application === null) throw new DomainException(jyb_t('Enter a valid HTTPS application URL, email address, or leave the target empty for an informational job.'));
    $opens = jyb_admin_datetime_to_utc($_POST['opens_at_utc'] ?? '');
    $closes = jyb_admin_datetime_to_utc($_POST['closes_at_utc'] ?? '');
    if ((jyb_scalar($_POST['opens_at_utc'] ?? '') !== '' && $opens === null)
        || (jyb_scalar($_POST['closes_at_utc'] ?? '') !== '' && $closes === null)) {
        throw new DomainException(jyb_t('Enter valid opening and closing times.'));
    }
    if ($opens !== null && $closes !== null && $opens >= $closes) {
        throw new DomainException(jyb_t('The closing time must be after the opening time.'));
    }

    $texts = [];
    $input = is_array($_POST['texts'] ?? null) ? $_POST['texts'] : [];
    foreach (array_slice($input, 0, 30, true) as $key => $row) {
        if (!is_array($row)) continue;
        $locale = jyb_locale($row['locale'] ?? $key);
        $title = jyb_text($row['title'] ?? '', 191);
        $description = jyb_text($row['description'] ?? '', 30000, false);
        $slug = jyb_slug($row['slug'] ?? '');
        if ($locale === null || ($title === '' && $description === '' && $slug === null)) continue;
        if (!in_array($locale, jyb_content_locales($pdo), true)) throw new DomainException(jyb_t('A submitted locale is unavailable.'));
        if ($title === '' || $description === '' || $slug === null) {
            throw new DomainException(jyb_t('Each entered translation requires a title, slug, and description.'));
        }
        $translationStatus = jyb_scalar($row['translation_status'] ?? 'draft');
        if (!in_array($translationStatus, ['draft','published'], true)) {
            throw new DomainException(jyb_t('Select a valid translation status.'));
        }
        $texts[$locale] = [
            'locale'=>$locale,
            'slug'=>$slug,
            'title'=>$title,
            'summary'=>jyb_text($row['summary'] ?? '', 1000, false),
            'description'=>$description,
            'location_text'=>jyb_text($row['location_text'] ?? '', 191),
            'translation_status'=>$translationStatus,
        ];
    }
    if (!isset($texts[$sourceLocale])) throw new DomainException(jyb_t('Source-locale job content is required.'));

    $categoryIds = [];
    foreach (is_array($_POST['category_ids'] ?? null) ? array_slice($_POST['category_ids'], 0, 10) : [] as $raw) {
        $value = jyb_positive_id($raw);
        if ($value > 0) $categoryIds[$value] = true;
    }
    $categoryIds = array_keys($categoryIds);
    sort($categoryIds, SORT_NUMERIC);
    $reference = jyb_text($_POST['reference_code'] ?? '', 100);
    $featured = ($_POST['featured'] ?? '') === '1' ? 1 : 0;
    $now = jyb_now_utc();

    $pdo->beginTransaction();
    jyb_admin_reauthorize($pdo, $jybActorId, $permission);
    foreach (array_keys($texts) as $locale) {
        if (!jyb_locale_edit_allowed($pdo, $jybActorId, $locale, true)) {
            throw new DomainException(jyb_t('You cannot edit one of the submitted locales.'));
        }
    }

    // Cross-entity mutations lock companies, company text, categories, then job.
    $jobHint = null;
    if ($id > 0) {
        $hint = $pdo->prepare('SELECT company_id,source_locale FROM jyb_jobs WHERE id=? LIMIT 1');
        $hint->execute([$id]);
        $jobHint = $hint->fetch(PDO::FETCH_ASSOC);
        if (!is_array($jobHint)) throw new DomainException(jyb_t('This job changed after it was loaded. Reload before saving.'));
    }
    $companyLockIds = array_values(array_unique(array_filter([$companyId, (int)($jobHint['company_id'] ?? 0)])));
    sort($companyLockIds, SORT_NUMERIC);
    $company = $pdo->prepare('SELECT id,status FROM jyb_companies WHERE id IN (' . implode(',', array_fill(0, count($companyLockIds), '?')) . ') ORDER BY id FOR UPDATE');
    $company->execute($companyLockIds);
    $companyRows = [];
    foreach ($company->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) $companyRows[(int)$row['id']] = $row;
    $companyRow = $companyRows[$companyId] ?? null;
    if (!is_array($companyRow)) throw new DomainException(jyb_t('The selected company is unavailable.'));
    $companyText = $pdo->prepare('SELECT translation_status FROM jyb_company_texts WHERE company_id=? AND locale=? LIMIT 1 FOR UPDATE');
    $companyText->execute([$companyId, $sourceLocale]);
    $companyTextStatus = $companyText->fetchColumn();
    $categoryLockIds = $categoryIds;
    if ($id > 0) {
        $oldCategories = $pdo->prepare('SELECT category_id FROM jyb_job_categories WHERE job_id=? ORDER BY category_id');
        $oldCategories->execute([$id]);
        $categoryLockIds = array_values(array_unique(array_merge($categoryLockIds, array_map('intval', $oldCategories->fetchAll(PDO::FETCH_COLUMN) ?: []))));
        sort($categoryLockIds, SORT_NUMERIC);
    }
    if ($categoryLockIds !== []) {
        $locks = $pdo->prepare('SELECT id,status FROM jyb_categories WHERE id IN (' . implode(',', array_fill(0, count($categoryLockIds), '?')) . ') ORDER BY id FOR UPDATE');
        $locks->execute($categoryLockIds);
        $availableCategories = [];
        foreach ($locks->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) $availableCategories[(int)$row['id']] = (string)$row['status'];
        foreach ($categoryIds as $categoryId) if (($availableCategories[$categoryId] ?? '') !== 'active') {
            throw new DomainException(jyb_t('A selected category is unavailable.'));
        }
    }

    $before = null;
    $current = null;
    $status = 'draft';
    if ($id > 0) {
        $locked = $pdo->prepare('SELECT * FROM jyb_jobs WHERE id=? FOR UPDATE');
        $locked->execute([$id]);
        $current = $locked->fetch(PDO::FETCH_ASSOC);
        if (!is_array($current) || (int)$current['version'] !== $version
            || (int)$current['company_id'] !== (int)($jobHint['company_id'] ?? 0)
            || (string)$current['source_locale'] !== (string)($jobHint['source_locale'] ?? '')) {
            throw new DomainException(jyb_t('This job changed after it was loaded. Reload before saving.'));
        }
        if ((string)$current['source_locale'] !== $sourceLocale) throw new DomainException(jyb_t('The source locale cannot change after creation.'));
        if ((string)$current['status'] === 'archived') throw new DomainException(jyb_t('Restore this job to draft before editing it.'));
        $status = (string)$current['status'];
        $before = $current;
    }
    if ($status === 'published') {
        if (!user_can($pdo, $jybActorId, JYB_PERMISSION_PUBLISH)) {
            throw new DomainException(jyb_t('Editing live job content requires publishing permission.'));
        }
        if ($texts[$sourceLocale]['translation_status'] !== 'published' || $companyRow['status'] !== 'active') {
            throw new DomainException(jyb_t('Published jobs require published source content and an active company.'));
        }
        if ($companyTextStatus !== 'published') throw new DomainException(jyb_t('Publish the matching company locale before saving this live job.'));
    }

    if ($id > 0) {
        $update = $pdo->prepare('UPDATE jyb_jobs SET company_id=?,employment_type=?,workplace_type=?,reference_code=?,application_type=?,application_target=?,featured=?,opens_at_utc=?,closes_at_utc=?,version=version+1,updated_by=?,updated_at_utc=? WHERE id=? AND version=?');
        $update->execute([$companyId,$employment,$workplace,$reference ?: null,$application['type'],$application['target'],$featured,$opens,$closes,$jybActorId,$now,$id,$version]);
        if ($update->rowCount() !== 1) throw new DomainException(jyb_t('This job changed after it was loaded. Reload before saving.'));
    } else {
        $insert = $pdo->prepare("INSERT INTO jyb_jobs (company_id,source_locale,status,employment_type,workplace_type,reference_code,application_type,application_target,featured,opens_at_utc,closes_at_utc,created_by,updated_by,created_at_utc,updated_at_utc) VALUES (?,?,'draft',?,?,?,?,?,?,?,?,?,?,?,?)");
        $insert->execute([$companyId,$sourceLocale,$employment,$workplace,$reference ?: null,$application['type'],$application['target'],$featured,$opens,$closes,$jybActorId,$jybActorId,$now,$now]);
        $id = (int)$pdo->lastInsertId();
    }

    $existing = $pdo->prepare('SELECT locale,translation_status FROM jyb_job_texts WHERE job_id=? ORDER BY locale FOR UPDATE');
    $existing->execute([$id]);
    $existingStatuses = [];
    foreach ($existing->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) $existingStatuses[$row['locale']] = $row['translation_status'];
    $textStatement = $pdo->prepare('INSERT INTO jyb_job_texts (job_id,locale,slug,title,summary,description,location_text,translation_status,updated_at_utc) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE slug=VALUES(slug),title=VALUES(title),summary=VALUES(summary),description=VALUES(description),location_text=VALUES(location_text),translation_status=VALUES(translation_status),updated_at_utc=VALUES(updated_at_utc)');
    foreach ($texts as $text) {
        if (($text['translation_status'] === 'published' || ($existingStatuses[$text['locale']] ?? '') === 'published')
            && !user_can($pdo, $jybActorId, JYB_PERMISSION_PUBLISH)) {
            throw new DomainException(jyb_t('Changing published localized content requires publishing permission.'));
        }
        $textStatement->execute([$id,$text['locale'],$text['slug'],$text['title'],$text['summary'] ?: null,$text['description'],$text['location_text'] ?: null,$text['translation_status'],$now]);
    }
    $pdo->prepare('DELETE FROM jyb_job_categories WHERE job_id=?')->execute([$id]);
    $pivot = $pdo->prepare('INSERT INTO jyb_job_categories (job_id,category_id) VALUES (?,?)');
    foreach ($categoryIds as $categoryId) $pivot->execute([$id, $categoryId]);
    $nextVersion = $creating ? 1 : $version + 1;
    jyb_audit($pdo,$jybActorId,$creating ? 'job.created' : 'job.updated','job',$id,$before,['status'=>$status,'version'=>$nextVersion,'locales'=>array_keys($texts)]);
    $pdo->commit();
    jyb_admin_redirect('success', jyb_t('Job saved.'), 'jobs/edit', ['id'=>$id]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (!$error instanceof DomainException) error_log('[job-board] job save failed: ' . $error->getMessage());
    jyb_admin_redirect('error', $error instanceof DomainException ? $error->getMessage() : jyb_t('The job could not be saved.'), $creating ? 'jobs/create' : 'jobs/edit', $id > 0 ? ['id'=>$id] : []);
}
