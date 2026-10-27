<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    echo ($ok ? 'PASS' : 'FAIL') . ' ' . $message . PHP_EOL;
    if (!$ok) $failures[] = $message;
};
$GLOBALS['jyb_test_filters'] = [];
$GLOBALS['jyb_test_grant'] = true;
$GLOBALS['jyb_test_lock'] = null;
$GLOBALS['jyb_test_grant_calls'] = 0;
function add_filter(string $hook, callable $callback): void { $GLOBALS['jyb_test_filters'][$hook][] = $callback; }
function content_default_locale(): string { return 'en'; }
function ct_resource_adapter_resolve(PDO $pdo, string $locale, string $path): ?array { return null; }
function ct_content_locales(PDO $pdo): array { return ['en', 'de']; }
function ct_resource_adapter_supports(string $capability): bool { return in_array($capability, ['alternate-query', 'locking-locale-grant'], true); }
function ct_external_resource_locale_edit_granted(PDO $pdo, string $locale, ?int $actorId = null, bool $lock = false): bool {
    $GLOBALS['jyb_test_grant_calls']++;
    $GLOBALS['jyb_test_lock'] = $lock;
    if ($GLOBALS['jyb_test_grant'] === 'throw') throw new RuntimeException('grant unavailable');
    return $GLOBALS['jyb_test_grant'] === true && $locale === 'de' && $actorId === 7;
}
function localized_path_url(string $path, ?string $locale = null): string {
    $path = '/' . ltrim($path, '/');
    return $locale !== null && $locale !== 'en' ? '/' . $locale . $path : $path;
}
function jyb_base_path(?PDO $pdo = null): string { return 'jobs'; }
function jyb_schema_ready(PDO $pdo): bool { return true; }
function jyb_public_job_by_slug(PDO $pdo, string $locale, string $slug): ?array {
    return $locale === 'de' && $slug === 'beispiel-rolle' ? ['id'=>3] : null;
}

require_once $root . '/includes/validation.php';
require_once $root . '/includes/translation.php';
final class JybTranslationPdo extends PDO { public function __construct() {} }
$pdo = new JybTranslationPdo();

$registration = $GLOBALS['jyb_test_filters']['content_translation_resource_adapters'][0] ?? null;
$adapters = is_callable($registration) ? $registration([]) : [];
$adapter = $adapters[0] ?? [];
$check(($adapter['api_version'] ?? null) === 1 && ($adapter['key'] ?? '') === 'job-board'
    && is_callable($adapter['resolve'] ?? null) && is_callable($adapter['current'] ?? null) && is_callable($adapter['alternates'] ?? null),
    'adapter registration has the complete API v1 shape');
$grantCalls = $GLOBALS['jyb_test_grant_calls'];
$check(jyb_locale_edit_allowed($pdo, 7, 'en') && $GLOBALS['jyb_test_grant_calls'] === $grantCalls, 'default locale editing does not require a host grant');
$check(jyb_locale_edit_allowed($pdo, 7, 'de') && $GLOBALS['jyb_test_lock'] === false, 'matching non-default locale grant is honored');
$check(jyb_locale_edit_allowed($pdo, 7, 'de', true) && $GLOBALS['jyb_test_lock'] === true, 'transaction-time locale checks request host locking');
$GLOBALS['jyb_test_grant'] = false;
$check(!jyb_locale_edit_allowed($pdo, 7, 'de'), 'host locale denial fails closed');
$GLOBALS['jyb_test_grant'] = 'throw';
$check(!jyb_locale_edit_allowed($pdo, 7, 'de'), 'host locale errors fail closed');
$GLOBALS['jyb_test_grant'] = true;

$check(jyb_translation_resolve($pdo, 'de', 'articles/example') === null, 'resolver ignores paths outside the Job Board base');
$check(jyb_translation_resolve($pdo, 'de', 'jobs/missing') === ['available'=>false], 'owned missing paths remain unavailable without leaking identity');
$resolved = jyb_translation_resolve($pdo, 'de', 'jobs/beispiel-rolle');
$check(($resolved['resource_id'] ?? '') === 'job:3' && ($resolved['route_path'] ?? '') === 'jobs/beispiel-rolle', 'published localized detail resolves to stable identity and route');
$list = jyb_translation_resolve($pdo, 'de', 'jobs');
$check(($list['resource_id'] ?? '') === 'list' && ($list['route_path'] ?? '') === 'jobs', 'localized list resolves to the internal route');
$query = 'q=Engineer&page_number=2';
jyb_set_current_resource_context(['adapter_key'=>'job-board','resource_id'=>'list','query'=>$query]);
$check(jyb_translation_alternates($pdo, ['adapter_key'=>'job-board','resource_id'=>'list','locale'=>'de']) === [
    ['locale'=>'en','path'=>'/jobs/','query'=>$query],
    ['locale'=>'de','path'=>'/de/jobs/','query'=>$query],
], 'list alternates preserve bounded canonical query state');
$check(jyb_locale('pt-BR') === 'pt-BR' && jyb_locale('pt_BR') === 'pt-BR', 'mixed-case registered locale identifiers retain their exact URL identity');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " translation check(s) failed.\n");
    exit(1);
}
echo "Job Board translation contract passed.\n";
