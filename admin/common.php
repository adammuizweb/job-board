<?php
declare(strict_types=1);

if (!defined('DASHBOARD_CONTEXT')) { http_response_code(403); exit; }
$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo instanceof PDO) throw new RuntimeException('Job Board database is unavailable.');
if (!jyb_schema_ready($pdo)) throw new RuntimeException(jyb_t('Job Board storage is unavailable.'));
if (!function_exists('adiwira_redirect_with_flash') && defined('DASH_PATH')) require_once rtrim((string)DASH_PATH, DIRECTORY_SEPARATOR) . '/admin/_notify.php';

$jybAdminBase = rtrim((string)ADMIN_BASE_PATH, '/') . '/?page=admin/tools/job-board';
$jybActorId = function_exists('current_user_id') ? (int)current_user_id() : 0;
if ($jybActorId < 1) { adiwira_render_404(); return; }

function jyb_admin_guard(PDO $pdo, string $permission): int
{
    [$actorId] = adiwira_require_permission($pdo, $permission, false);
    return (int)$actorId;
}

function jyb_admin_url(string $route = '', array $query = []): string
{
    $page = 'admin/tools/job-board' . ($route !== '' ? '/' . trim($route, '/') : '');
    return rtrim((string)ADMIN_BASE_PATH, '/') . '/?' . http_build_query(['page'=>$page] + $query);
}

function jyb_admin_redirect(string $type, string $message, string $route = '', array $query = []): never
{
    $target = jyb_admin_url($route, $query);
    if (function_exists('adiwira_redirect_with_flash')) adiwira_redirect_with_flash($target, $type, $message, 303);
    header('Location: ' . $target, true, 303); exit;
}

function jyb_admin_require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { adiwira_render_404(); exit; }
    $token = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
    if (!function_exists('csrf_check') || !csrf_check($token)) jyb_admin_redirect('error', jyb_t('The security token is invalid.'));
}

function jyb_admin_reauthorize(PDO $pdo, int $actorId, string $permission): void
{
    if (!$pdo->inTransaction() || !function_exists('authorization_lock_actor_permissions')
        || !authorization_lock_actor_permissions($pdo, $actorId) || !function_exists('user_can')
        || !user_can($pdo, $actorId, $permission)) throw new RuntimeException('Job Board permission changed.');
}

function jyb_admin_can(PDO $pdo, int $actorId, string $permission): bool
{
    return function_exists('user_can') && user_can($pdo, $actorId, $permission);
}

function jyb_admin_nav(PDO $pdo, int $actorId, string $active): void
{
    $items = [
        ['jobs','',jyb_t('Jobs'),JYB_PERMISSION_READ],
        ['companies','companies',jyb_t('Companies'),JYB_PERMISSION_COMPANIES],
        ['categories','categories',jyb_t('Categories'),JYB_PERMISSION_CATEGORIES],
        ['settings','settings',jyb_t('Settings'),JYB_PERMISSION_SETTINGS],
        ['audit','audit',jyb_t('Audit'),JYB_PERMISSION_AUDIT],
    ];
    echo '<nav class="jyb-admin-nav" aria-label="' . jyb_h(jyb_t('Job Board workspace')) . '">';
    foreach ($items as [$key,$route,$label,$permission]) if (jyb_admin_can($pdo,$actorId,$permission)) echo '<a href="' . jyb_h(jyb_admin_url($route)) . '"' . ($active === $key ? ' aria-current="page"' : '') . '>' . jyb_h($label) . '</a>';
    echo '</nav>';
}

function jyb_admin_datetime_input(?string $value): string
{
    if (!$value) return '';
    if (function_exists('app_utc_mysql_to_site')) {
        $date = app_utc_mysql_to_site($value);
        if ($date instanceof DateTimeInterface) return $date->format('Y-m-d\TH:i');
    }
    return substr(str_replace(' ', 'T', $value), 0, 16);
}

function jyb_admin_datetime_to_utc(mixed $value): ?string
{
    $value = jyb_scalar($value);
    if ($value === '') return null;
    $date = function_exists('app_parse_site_datetime_local') ? app_parse_site_datetime_local($value) : null;
    if ($date instanceof DateTimeInterface && function_exists('app_site_datetime_to_utc_mysql')) {
        return app_site_datetime_to_utc_mysql($date);
    }
    return jyb_datetime_utc($value);
}

function jyb_admin_datetime(?string $value): string
{
    if ($value === null || trim($value) === '') return '—';
    if (function_exists('app_utc_mysql_to_site') && function_exists('app_display_datetime')) {
        $date = app_utc_mysql_to_site($value);
        if ($date instanceof DateTimeInterface) return app_display_datetime($date);
    }
    try {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('Y-m-d H:i');
    } catch (Throwable) {
        return $value;
    }
}

function jyb_admin_status_class(string $status): string
{
    return match ($status) {
        'published' => 'jyb-status--published',
        'closed' => 'jyb-status--closed',
        'archived' => 'jyb-status--archived',
        default => 'jyb-status--draft',
    };
}

function jyb_admin_translation_class(string $status): string
{
    return match ($status) {
        'reviewed' => 'jyb-translation--reviewed',
        'machine' => 'jyb-translation--machine',
        'stale' => 'jyb-translation--stale',
        default => 'jyb-translation--draft',
    };
}

function jyb_admin_actions_trigger(string $menuId): void
{
    $label = jyb_t('Open actions');
    echo '<button class="adam-actions__trigger" type="button" aria-haspopup="menu" aria-expanded="false" aria-controls="' . jyb_h($menuId) . '" aria-label="' . jyb_h($label) . '" title="' . jyb_h($label) . '">';
    echo '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="2"></circle><circle cx="12" cy="12" r="2"></circle><circle cx="19" cy="12" r="2"></circle></svg>';
    echo '</button>';
}

function jyb_admin_companies(PDO $pdo): array
{
    return $pdo->query('SELECT c.id,c.source_locale,c.status,c.version,c.updated_at_utc,t.name,t.translation_status FROM jyb_companies c JOIN jyb_company_texts t ON t.company_id=c.id AND t.locale=c.source_locale ORDER BY t.name,c.id')->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function jyb_admin_categories(PDO $pdo): array
{
    return $pdo->query('SELECT c.id,c.source_locale,c.status,c.version,c.updated_at_utc,t.name,t.slug,t.translation_status FROM jyb_categories c JOIN jyb_category_texts t ON t.category_id=c.id AND t.locale=c.source_locale ORDER BY t.name,c.id')->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
