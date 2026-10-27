<?php
declare(strict_types=1);

require dirname(__DIR__) . '/common.php';
$jybActorId = jyb_admin_guard($pdo, JYB_PERMISSION_COMPANIES);
jyb_admin_require_post();
$id = jyb_positive_id($_POST['id'] ?? 0);
$version = jyb_bounded_int($_POST['version'] ?? 0, 0, PHP_INT_MAX, 0);

try {
    $sourceLocale = jyb_locale($_POST['source_locale'] ?? '');
    if ($sourceLocale === null || !in_array($sourceLocale, jyb_content_locales($pdo), true)) throw new DomainException(jyb_t('Enter a valid source locale.'));
    $status = jyb_scalar($_POST['status'] ?? '');
    if (!in_array($status, ['active','inactive'], true)) throw new DomainException(jyb_t('Select a valid company status.'));
    $website = jyb_https_url($_POST['website_url'] ?? '');
    if ($website === null) throw new DomainException(jyb_t('Company website must be an HTTPS URL.'));
    $texts = [];
    foreach (array_slice(is_array($_POST['texts'] ?? null) ? $_POST['texts'] : [], 0, 30, true) as $key => $row) {
        if (!is_array($row)) continue;
        $locale = jyb_locale($row['locale'] ?? $key);
        $name = jyb_text($row['name'] ?? '', 191);
        if ($locale === null || $name === '') continue;
        if (!in_array($locale, jyb_content_locales($pdo), true)) throw new DomainException(jyb_t('A submitted locale is unavailable.'));
        $translationStatus = jyb_scalar($row['translation_status'] ?? 'draft');
        if (!in_array($translationStatus, ['draft','published'], true)) throw new DomainException(jyb_t('Select a valid translation status.'));
        $texts[$locale] = ['name'=>$name,'description'=>jyb_text($row['description'] ?? '', 10000, false),'status'=>$translationStatus];
    }
    if (!isset($texts[$sourceLocale])) throw new DomainException(jyb_t('Source-locale company text is required.'));

    $now = jyb_now_utc();
    $pdo->beginTransaction();
    jyb_admin_reauthorize($pdo, $jybActorId, JYB_PERMISSION_COMPANIES);
    foreach (array_keys($texts) as $locale) {
        if (!jyb_locale_edit_allowed($pdo, $jybActorId, $locale, true)) throw new DomainException(jyb_t('You cannot edit one of the submitted locales.'));
    }
    $before = null;
    if ($id > 0) {
        $lock = $pdo->prepare('SELECT * FROM jyb_companies WHERE id=? FOR UPDATE');
        $lock->execute([$id]);
        $current = $lock->fetch(PDO::FETCH_ASSOC);
        if (!is_array($current) || (int)$current['version'] !== $version) throw new DomainException(jyb_t('This company changed after it was loaded.'));
        if ($current['source_locale'] !== $sourceLocale) throw new DomainException(jyb_t('The source locale cannot change after creation.'));
        $before = $current;
    }
    $existing = $pdo->prepare('SELECT locale,translation_status FROM jyb_company_texts WHERE company_id=? ORDER BY locale FOR UPDATE');
    $existing->execute([$id]);
    $existingStatuses = [];
    foreach ($existing->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) $existingStatuses[$row['locale']] = $row['translation_status'];
    $hasPublishedText = in_array('published', $existingStatuses, true);
    $globalPublicChange = is_array($before) && $hasPublishedText
        && ((string)$before['status'] !== $status || (string)($before['website_url'] ?? '') !== $website);
    $needsPublish = $globalPublicChange;
    foreach ($texts as $locale => $text) {
        if ($text['status'] === 'published' || ($existingStatuses[$locale] ?? '') === 'published') $needsPublish = true;
    }
    if ($needsPublish && !user_can($pdo, $jybActorId, JYB_PERMISSION_PUBLISH)) {
        throw new DomainException(jyb_t('Publishing company content requires publishing permission.'));
    }
    if ($id > 0 && $status === 'inactive') {
        $used = $pdo->prepare("SELECT 1 FROM jyb_jobs WHERE company_id=? AND status='published' LIMIT 1 FOR UPDATE");
        $used->execute([$id]);
        if ($used->fetchColumn()) throw new DomainException(jyb_t('Close or archive published jobs before deactivating this company.'));
    }
    if ($id > 0) {
        $update = $pdo->prepare('UPDATE jyb_companies SET status=?,website_url=?,version=version+1,updated_by=?,updated_at_utc=? WHERE id=? AND version=?');
        $update->execute([$status,$website ?: null,$jybActorId,$now,$id,$version]);
        if ($update->rowCount() !== 1) throw new DomainException(jyb_t('This company changed after it was loaded.'));
    } else {
        $insert = $pdo->prepare('INSERT INTO jyb_companies (source_locale,status,website_url,created_by,updated_by,created_at_utc,updated_at_utc) VALUES (?,?,?,?,?,?,?)');
        $insert->execute([$sourceLocale,$status,$website ?: null,$jybActorId,$jybActorId,$now,$now]);
        $id = (int)$pdo->lastInsertId();
    }
    $textStatement = $pdo->prepare('INSERT INTO jyb_company_texts (company_id,locale,name,description,translation_status,updated_at_utc) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description),translation_status=VALUES(translation_status),updated_at_utc=VALUES(updated_at_utc)');
    foreach ($texts as $locale => $text) $textStatement->execute([$id,$locale,$text['name'],$text['description'] ?: null,$text['status'],$now]);
    jyb_audit($pdo,$jybActorId,$before === null ? 'company.created' : 'company.updated','company',$id,$before,['status'=>$status,'locales'=>array_keys($texts)]);
    $pdo->commit();
    jyb_admin_redirect('success', jyb_t('Company saved.'), 'companies');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (!$error instanceof DomainException) error_log('[job-board] company save failed: ' . $error->getMessage());
    jyb_admin_redirect('error', $error instanceof DomainException ? $error->getMessage() : jyb_t('The company could not be saved.'), 'companies/edit', $id > 0 ? ['id'=>$id] : []);
}
