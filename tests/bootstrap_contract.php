<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    echo ($ok ? 'PASS' : 'FAIL') . ' ' . $message . PHP_EOL;
    if (!$ok) $failures[] = $message;
};
$GLOBALS['jyb_test_actions'] = [];
$GLOBALS['jyb_test_filters'] = [];
$GLOBALS['jyb_test_routes'] = [];
$GLOBALS['jyb_test_providers'] = [];
$GLOBALS['_widget_shortcode_handlers'] = [];

define('BACKEND_PATH', '/tmp/opencode');
function add_action(string $hook, callable|string $callback, int $priority = 10): void { $GLOBALS['jyb_test_actions'][$hook][] = $callback; }
function add_filter(string $hook, callable|string $callback, int $priority = 10): void { $GLOBALS['jyb_test_filters'][$hook][] = $callback; }
function register_frontend_route(string $path, callable|string $callback, array $options = []): bool {
    if (isset($GLOBALS['jyb_test_routes'][$path])) return false;
    $GLOBALS['jyb_test_routes'][$path] = ['callback'=>$callback,'options'=>$options];
    return true;
}
function register_shortcode_source_provider(string $id, array $provider): bool {
    if (isset($GLOBALS['jyb_test_providers'][$id])) return false;
    $GLOBALS['jyb_test_providers'][$id] = $provider;
    return true;
}
function register_widget_shortcode_handler(string $name, callable $callback, array $defaults = []): void {
    $GLOBALS['_widget_shortcode_handlers'][$name] = ['callback'=>$callback,'defaults'=>$defaults];
}

require $root . '/plugin.php';

$route = $GLOBALS['jyb_test_routes']['jobs'] ?? [];
$check(($route['callback'] ?? null) === 'jyb_frontend_route' && ($route['options']['match'] ?? '') === 'prefix'
    && ($route['options']['methods'] ?? []) === ['GET'], 'plugin bootstrap registers one GET prefix route');
$check(isset($GLOBALS['jyb_test_providers']['job_board_jobs'],$GLOBALS['_widget_shortcode_handlers']['job_board_list']), 'plugin bootstrap registers the provider and direct widget');
$check(isset($GLOBALS['jyb_test_actions']['plugin_uninstall'],$GLOBALS['jyb_test_actions']['shortcode_preset_editor_fields']), 'plugin bootstrap registers lifecycle and editor hooks');
$adapter = $GLOBALS['jyb_test_filters']['content_translation_resource_adapters'][0] ?? null;
$check(is_callable($adapter) && $adapter([]) === [], 'optional translation adapter stays inert without its host');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " bootstrap check(s) failed.\n");
    exit(1);
}
echo "Job Board bootstrap contract passed.\n";
