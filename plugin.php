<?php
declare(strict_types=1);

if (!defined('BACKEND_PATH')) return;

const JYB_VERSION = '0.1.0';
const JYB_PERMISSION_READ = 'plugin.job-board.workspace.read';
const JYB_PERMISSION_CREATE = 'plugin.job-board.jobs.create';
const JYB_PERMISSION_EDIT = 'plugin.job-board.jobs.edit';
const JYB_PERMISSION_PUBLISH = 'plugin.job-board.jobs.publish';
const JYB_PERMISSION_COMPANIES = 'plugin.job-board.companies.manage';
const JYB_PERMISSION_CATEGORIES = 'plugin.job-board.categories.manage';
const JYB_PERMISSION_SETTINGS = 'plugin.job-board.settings.manage';
const JYB_PERMISSION_AUDIT = 'plugin.job-board.audit.view';

function jyb_h(mixed $value): string
{
    return str_replace(['[', ']'], ['&#91;', '&#93;'], htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'));
}

function jyb_t(string $source, mixed ...$arguments): string
{
    $text = function_exists('__') ? __($source) : $source;
    if ($text === $source) {
        $locale = function_exists('get_locale') ? strtolower((string)get_locale()) : 'en';
        $locale = explode('-', str_replace('_', '-', $locale), 2)[0];
        if (in_array($locale, ['id', 'de'], true)) {
            static $catalogs = [];
            if (!isset($catalogs[$locale])) {
                $file = __DIR__ . '/languages/' . $locale . '.php';
                $catalog = is_file($file) ? require $file : [];
                $catalogs[$locale] = is_array($catalog) ? $catalog : [];
            }
            $text = (string)($catalogs[$locale][$source] ?? $source);
        }
    }
    return $arguments === [] ? $text : sprintf($text, ...$arguments);
}

require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/repository.php';
require_once __DIR__ . '/includes/translation.php';
require_once __DIR__ . '/includes/runtime.php';
require_once __DIR__ . '/includes/shortcodes.php';
require_once __DIR__ . '/includes/lifecycle.php';

function jyb_is_admin_surface(): bool
{
    $page = trim(is_scalar($_GET['page'] ?? null) ? (string)$_GET['page'] : '', '/');
    return $page === 'admin/tools/job-board' || str_starts_with($page, 'admin/tools/job-board/');
}

function jyb_admin_assets(): void
{
    if (!jyb_is_admin_surface()) return;
    echo '<link rel="stylesheet" href="/static/plugins/job-board/admin.css?v=' . rawurlencode(JYB_VERSION) . '">' . PHP_EOL;
    echo '<script src="/static/plugins/job-board/admin.js?v=' . rawurlencode(JYB_VERSION) . '" defer></script>' . PHP_EOL;
}

if (!function_exists('register_frontend_route') || !function_exists('register_shortcode_source_provider')
    || !function_exists('register_widget_shortcode_handler')) {
    throw new RuntimeException('Job Board requires Jyavani CMS 2.3.187 or newer.');
}

add_action('admin_head', 'jyb_admin_assets');
add_action('jy_head', 'jyb_frontend_assets');
add_action('plugin_uninstall', 'jyb_uninstall');
add_action('shortcode_preset_editor_fields', 'jyb_preset_editor_fields');
add_filter('router_path', 'jyb_capture_resolved_path', PHP_INT_MAX);

$jybPdo = $GLOBALS['pdo'] ?? null;
$jybBase = jyb_base_path($jybPdo instanceof PDO ? $jybPdo : null);
if ($jybPdo instanceof PDO) jyb_assert_base_path_available($jybPdo, $jybBase);
if (!register_frontend_route($jybBase, 'jyb_frontend_route', ['match' => 'prefix', 'methods' => ['GET']])) {
    throw new RuntimeException('Job Board public route could not be registered.');
}
if (!jyb_register_shortcode_integrations()) throw new RuntimeException('Job Board shortcode integration collided with an existing registration.');
unset($jybPdo, $jybBase);
