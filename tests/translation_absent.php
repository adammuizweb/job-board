<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    echo ($ok ? 'PASS' : 'FAIL') . ' ' . $message . PHP_EOL;
    if (!$ok) $failures[] = $message;
};
$GLOBALS['jyb_test_filters'] = [];
function add_filter(string $hook, callable $callback): void { $GLOBALS['jyb_test_filters'][$hook][] = $callback; }
function jyb_base_path(?PDO $pdo = null): string { return 'jobs'; }
function jyb_schema_ready(PDO $pdo): bool { return true; }
function jyb_public_job_by_slug(PDO $pdo, string $locale, string $slug): ?array { return null; }

require_once $root . '/includes/validation.php';
require_once $root . '/includes/translation.php';
final class JybAbsentPdo extends PDO { public function __construct() {} }
$pdo = new JybAbsentPdo();

$check(jyb_content_locales($pdo) === ['en'], 'absent host exposes only the default content locale');
$check(jyb_locale_edit_allowed($pdo, 7, 'en'), 'default locale editing remains locally authorized');
$check(!jyb_locale_edit_allowed($pdo, 7, 'de') && !jyb_locale_edit_allowed($pdo, 7, 'de', true), 'non-default locale authority fails closed without the optional host');
$registration = $GLOBALS['jyb_test_filters']['content_translation_resource_adapters'][0] ?? null;
$check(is_callable($registration) && $registration([]) === [], 'resource adapter registration remains inert without a compatible host');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " absent-translation check(s) failed.\n");
    exit(1);
}
echo "Job Board absent-translation contract passed.\n";
