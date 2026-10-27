<?php
declare(strict_types=1);

require dirname(__DIR__) . '/common.php';
$jybActorId = jyb_admin_guard($pdo, JYB_PERMISSION_CATEGORIES);
jyb_admin_require_post();
$id = jyb_positive_id($_POST['id'] ?? 0);
$version = jyb_bounded_int($_POST['version'] ?? 0, 0, PHP_INT_MAX, 0);

try {
    $sourceLocale = jyb_locale($_POST['source_locale'] ?? '');
    if ($sourceLocale === null || !in_array($sourceLocale, jyb_content_locales($pdo), true)) throw new DomainException(jyb_t('Enter a valid source locale.'));
    $status = jyb_scalar($_POST['status'] ?? '');
    if (!in_array($status, ['active','inactive'], true)) throw new DomainException(jyb_t('Enter valid category values.'));
    $texts = [];
    foreach (array_slice(is_array($_POST['texts'] ?? null) ? $_POST['texts'] : [], 0, 30, true) as $key => $row) {
        if (!is_array($row)) continue;
        $locale = jyb_locale($row['locale'] ?? $key);
        $name = jyb_text($row['name'] ?? '', 191);
        $slug = jyb_slug($row['slug'] ?? '');
        if ($locale === null || ($name === '' && $slug === null)) continue;
        if (!in_array($locale, jyb_content_locales($pdo), true)) throw new DomainException(jyb_t('A submitted locale is unavailable.'));
        if ($name === '' || $slug === null) throw new DomainException(jyb_t('Each entered category translation requires a name and slug.'));
        $translationStatus = jyb_scalar($row['translation_status'] ?? 'draft');
        if (!in_array($translationStatus, ['draft','published'], true)) throw new DomainException(jyb_t('Select a valid translation status.'));
        $texts[$locale] = ['name'=>$name,'slug'=>$slug,'status'=>$translationStatus];
    }
    if (!isset($texts[$sourceLocale])) throw new DomainException(jyb_t('Source-locale category text is required.'));
    $now = jyb_now_utc();
    $pdo->beginTransaction();
    jyb_admin_reauthorize($pdo, $jybActorId, JYB_PERMISSION_CATEGORIES);
    foreach (array_keys($texts) as $locale) {
        if (!jyb_locale_edit_allowed($pdo, $jybActorId, $locale, true)) throw new DomainException(jyb_t('You cannot edit one of the submitted locales.'));
    }
    $before = null;
    if ($id > 0) {
        $lock = $pdo->prepare('SELECT * FROM jyb_categories WHERE id=? FOR UPDATE');
        $lock->execute([$id]);
        $current = $lock->fetch(PDO::FETCH_ASSOC);
        if (!is_array($current) || (int)$current['version'] !== $version) throw new DomainException(jyb_t('This category changed after it was loaded.'));
        if ($current['source_locale'] !== $sourceLocale) throw new DomainException(jyb_t('The source locale cannot change after creation.'));
        $before = $current;
    }
    $existing = $pdo->prepare('SELECT locale,translation_status FROM jyb_category_texts WHERE category_id=? ORDER BY locale FOR UPDATE');
    $existing->execute([$id]);
    $existingStatuses = [];
    foreach ($existing->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) $existingStatuses[$row['locale']] = $row['translation_status'];
    $hasPublishedText = in_array('published', $existingStatuses, true);
    $needsPublish = is_array($before) && $hasPublishedText && (string)$before['status'] !== $status;
    foreach ($texts as $locale => $text) {
        if ($text['status'] === 'published' || ($existingStatuses[$locale] ?? '') === 'published') $needsPublish = true;
    }
    if ($needsPublish && !user_can($pdo, $jybActorId, JYB_PERMISSION_PUBLISH)) throw new DomainException(jyb_t('Publishing category content requires publishing permission.'));
    if ($id > 0 && $status === 'inactive') {
        $used = $pdo->prepare("SELECT 1 FROM jyb_job_categories jc JOIN jyb_jobs j ON j.id=jc.job_id AND j.status='published' WHERE jc.category_id=? LIMIT 1 FOR UPDATE");
        $used->execute([$id]);
        if ($used->fetchColumn()) throw new DomainException(jyb_t('Close or archive published jobs before deactivating this category.'));
    }
    if ($id > 0) {
        $update = $pdo->prepare('UPDATE jyb_categories SET status=?,version=version+1,updated_by=?,updated_at_utc=? WHERE id=? AND version=?');
        $update->execute([$status,$jybActorId,$now,$id,$version]);
        if ($update->rowCount() !== 1) throw new DomainException(jyb_t('This category changed after it was loaded.'));
    } else {
        $insert = $pdo->prepare('INSERT INTO jyb_categories (source_locale,status,created_by,updated_by,created_at_utc,updated_at_utc) VALUES (?,?,?,?,?,?)');
        $insert->execute([$sourceLocale,$status,$jybActorId,$jybActorId,$now,$now]);
        $id = (int)$pdo->lastInsertId();
    }
    $textStatement = $pdo->prepare('INSERT INTO jyb_category_texts (category_id,locale,name,slug,translation_status,updated_at_utc) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),slug=VALUES(slug),translation_status=VALUES(translation_status),updated_at_utc=VALUES(updated_at_utc)');
    foreach ($texts as $locale => $text) $textStatement->execute([$id,$locale,$text['name'],$text['slug'],$text['status'],$now]);
    jyb_audit($pdo,$jybActorId,$before === null ? 'category.created' : 'category.updated','category',$id,$before,['status'=>$status,'locales'=>array_keys($texts)]);
    $pdo->commit();
    jyb_admin_redirect('success', jyb_t('Category saved.'), 'categories');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (!$error instanceof DomainException) error_log('[job-board] category save failed: ' . $error->getMessage());
    jyb_admin_redirect('error', $error instanceof DomainException ? $error->getMessage() : jyb_t('The category could not be saved.'), 'categories', $id > 0 ? ['id'=>$id] : []);
}
