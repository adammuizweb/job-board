<?php
declare(strict_types=1);

require dirname(__DIR__) . '/common.php';
$jybActorId = jyb_admin_guard($pdo, JYB_PERMISSION_SETTINGS);
jyb_admin_require_post();

try {
    $base = jyb_slug($_POST['base_path'] ?? '');
    $perPage = jyb_bounded_int($_POST['per_page'] ?? 0, 1, 48, 0);
    $expected = jyb_scalar($_POST['expected_hash'] ?? '');
    if ($base === null || $perPage < 1 || preg_match('/\A[a-f0-9]{64}\z/', $expected) !== 1) {
        throw new DomainException(jyb_t('Enter valid Job Board settings.'));
    }

    $pdo->beginTransaction();
    jyb_admin_reauthorize($pdo, $jybActorId, JYB_PERMISSION_SETTINGS);
    foreach (['jyb_base_path'=>'jobs','jyb_per_page'=>'12'] as $key => $default) {
        $pdo->prepare('INSERT INTO settings (`key`,`value`,`autoload`) VALUES (?,?,1) ON DUPLICATE KEY UPDATE `key`=VALUES(`key`)')->execute([$key,$default]);
    }
    $routeSettingKeys = ['admin_path','category_path','jyb_base_path','jyb_per_page','login_path','pages_list_path','posts_list_path','register_path'];
    $placeholders = implode(',', array_fill(0, count($routeSettingKeys), '?'));
    $settings = $pdo->prepare('SELECT `key`,`value` FROM settings WHERE `key` IN (' . $placeholders . ') ORDER BY `key` FOR UPDATE');
    $settings->execute($routeSettingKeys);
    $lockedSettings = $settings->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    try {
        jyb_assert_base_path_available($pdo, $base, true);
    } catch (RuntimeException) {
        throw new DomainException(jyb_t('The public base path conflicts with an existing route or content.'));
    }

    $before = [
        'base_path'=>jyb_slug($lockedSettings['jyb_base_path'] ?? 'jobs') ?? 'jobs',
        'per_page'=>jyb_bounded_int($lockedSettings['jyb_per_page'] ?? 12, 1, 48, 12),
    ];
    if (!hash_equals(hash('sha256', json_encode($before, JSON_THROW_ON_ERROR)), $expected)) {
        throw new DomainException(jyb_t('Settings changed while this form was open. Reload and try again.'));
    }
    if (!settings_set($pdo, 'jyb_base_path', $base, 1) || !settings_set($pdo, 'jyb_per_page', (string)$perPage, 1)) {
        throw new RuntimeException('Settings persistence failed.');
    }
    jyb_audit($pdo,$jybActorId,'settings.updated','settings',null,$before,['base_path'=>$base,'per_page'=>$perPage]);
    $pdo->commit();
    jyb_admin_redirect('success', jyb_t('Job Board settings saved.'), 'settings');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (!$error instanceof DomainException) error_log('[job-board] settings save failed: ' . $error->getMessage());
    jyb_admin_redirect('error', $error instanceof DomainException ? $error->getMessage() : jyb_t('Job Board settings could not be saved.'), 'settings');
}
