<?php
declare(strict_types=1);

function jyb_default_content_locale(): string
{
    try {
        $locale = function_exists('content_default_locale') ? jyb_locale((string)content_default_locale()) : null;
    } catch (Throwable) {
        $locale = null;
    }
    return $locale ?? 'en';
}

function jyb_current_locale(): string
{
    $candidate = function_exists('get_locale') ? get_locale() : jyb_default_content_locale();
    return jyb_locale($candidate) ?? jyb_default_content_locale();
}

function jyb_translation_host_compatible(): bool
{
    if (!function_exists('ct_resource_adapter_resolve') || !function_exists('ct_content_locales')
        || !function_exists('ct_resource_adapter_supports') || !function_exists('ct_external_resource_locale_edit_granted')) {
        return false;
    }
    try {
        return ct_resource_adapter_supports('alternate-query') === true
            && ct_resource_adapter_supports('locking-locale-grant') === true;
    } catch (Throwable) {
        return false;
    }
}

function jyb_content_locales(PDO $pdo): array
{
    if (!jyb_translation_host_compatible()) return [jyb_default_content_locale()];
    try {
        $candidates = ct_content_locales($pdo);
    } catch (Throwable) {
        return [jyb_default_content_locale()];
    }
    $locales = [];
    foreach (is_array($candidates) ? $candidates : [] as $candidate) {
        $locale = jyb_locale($candidate);
        if ($locale !== null && !in_array($locale, $locales, true)) $locales[] = $locale;
    }
    return $locales ?: [jyb_default_content_locale()];
}

function jyb_locale_edit_allowed(PDO $pdo, int $actorId, string $locale, bool $lock = false): bool
{
    $locale = jyb_locale($locale);
    if ($locale === null) return false;
    if ($locale === jyb_default_content_locale()) return true;
    if (!jyb_translation_host_compatible() || !in_array($locale, jyb_content_locales($pdo), true)) return false;
    try {
        return ct_external_resource_locale_edit_granted($pdo, $locale, $actorId, $lock);
    } catch (Throwable) {
        return false;
    }
}

function jyb_supported_locales(): array
{
    $pdo = $GLOBALS['pdo'] ?? null;
    return $pdo instanceof PDO ? jyb_content_locales($pdo) : [jyb_default_content_locale()];
}

function jyb_editable_locales(PDO $pdo, int $actorId): array
{
    return array_values(array_filter(
        jyb_content_locales($pdo),
        static fn(string $locale): bool => jyb_locale_edit_allowed($pdo, $actorId, $locale)
    ));
}

function jyb_localized_path(string $path, string $locale): string
{
    $path = '/' . trim($path, '/') . '/';
    $locale = jyb_locale($locale) ?? jyb_default_content_locale();
    if (function_exists('localized_path_url')) return localized_path_url($path, $locale);
    return $locale === jyb_default_content_locale() ? $path : '/' . rawurlencode($locale) . $path;
}

function jyb_set_current_resource_context(array $context): void
{
    $GLOBALS['jyb_current_resource_context'] = $context;
}

function jyb_translation_resolve(PDO $pdo, string $locale, string $path): ?array
{
    $base = jyb_base_path($pdo);
    $path = trim(rawurldecode($path), '/');
    if ($path !== $base && !str_starts_with($path, $base . '/')) return null;
    $unavailable = ['available'=>false];
    $locale = jyb_locale($locale);
    if ($locale === null || !jyb_schema_ready($pdo) || !in_array($locale, jyb_content_locales($pdo), true)) return $unavailable;
    if ($path === $base) {
        return ['available'=>true,'resource_id'=>'list','resource_type'=>'job_list','route_path'=>$base];
    }
    $slug = substr($path, strlen($base) + 1);
    if ($slug === '' || str_contains($slug, '/') || jyb_slug($slug) !== $slug) return $unavailable;
    $job = jyb_public_job_by_slug($pdo, $locale, $slug);
    if ($job === null) return $unavailable;
    return [
        'available'=>true,
        'resource_id'=>'job:' . (int)$job['id'],
        'resource_type'=>'job',
        'route_path'=>$base . '/' . $slug,
    ];
}

function jyb_translation_current(PDO $pdo): ?array
{
    $context = $GLOBALS['jyb_current_resource_context'] ?? null;
    return is_array($context) ? $context : null;
}

function jyb_translation_alternates(PDO $pdo, array $context): array
{
    if (($context['adapter_key'] ?? $context['key'] ?? '') !== 'job-board') return [];
    $resourceId = (string)($context['resource_id'] ?? '');
    $base = jyb_base_path($pdo);
    $locales = jyb_content_locales($pdo);
    if ($resourceId === 'list') {
        $current = jyb_translation_current($pdo);
        $query = is_array($current) && ($current['resource_id'] ?? '') === 'list' && is_string($current['query'] ?? null)
            ? $current['query'] : null;
        return array_map(static function (string $locale) use ($base, $query): array {
            $row = ['locale'=>$locale,'path'=>jyb_localized_path($base, $locale)];
            if ($query !== null && $query !== '') $row['query'] = $query;
            return $row;
        }, $locales);
    }
    $id = preg_match('/\Ajob:([1-9][0-9]*)\z/', $resourceId, $match) === 1 ? (int)$match[1] : 0;
    if ($id < 1) return [];
    $slugQuery = $pdo->prepare("SELECT slug FROM jyb_job_texts WHERE job_id=? AND locale=? AND translation_status='published' LIMIT 1");
    $rows = [];
    foreach ($locales as $locale) {
        $slugQuery->execute([$id, $locale]);
        $slug = $slugQuery->fetchColumn();
        if (!is_string($slug) || $slug === '') continue;
        $job = jyb_public_job_by_slug($pdo, $locale, $slug);
        if (!is_array($job) || (int)$job['id'] !== $id) continue;
        $rows[] = ['locale'=>$locale,'path'=>jyb_localized_path($base . '/' . rawurlencode($slug), $locale)];
    }
    return $rows;
}

if (function_exists('add_filter')) {
    add_filter('content_translation_resource_adapters', static function (array $adapters): array {
        if (!jyb_translation_host_compatible()) return $adapters;
        $adapters[] = [
            'api_version'=>1,
            'key'=>'job-board',
            'resolve'=>'jyb_translation_resolve',
            'current'=>'jyb_translation_current',
            'alternates'=>'jyb_translation_alternates',
        ];
        return $adapters;
    });
}
