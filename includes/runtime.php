<?php
declare(strict_types=1);

function jyb_capture_resolved_path(string $path): string
{
    $GLOBALS['jyb_resolved_path'] = trim($path, '/');
    return $path;
}

function jyb_request_path(): string
{
    if (is_string($GLOBALS['jyb_resolved_path'] ?? null)) return trim($GLOBALS['jyb_resolved_path'], '/');
    $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    return trim(rawurldecode(is_string($path) ? $path : '/'), '/');
}

function jyb_site_origin(PDO $pdo): string
{
    if (function_exists('app_site_origin')) return app_site_origin($pdo);
    $configured = function_exists('settings_get') ? trim((string)settings_get($pdo, 'site_url', '')) : '';
    $parts = $configured !== '' ? parse_url($configured) : false;
    if (is_array($parts) && in_array(strtolower((string)($parts['scheme'] ?? '')), ['http','https'], true) && !empty($parts['host']) && !isset($parts['user'],$parts['pass'],$parts['query'],$parts['fragment'])) return rtrim($configured, '/');
    $scheme = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (preg_match('/\A[a-z0-9.-]+(?::[0-9]{1,5})?\z/i', $host) !== 1) $host = 'localhost';
    return $scheme . '://' . $host;
}

function jyb_template(string $name): string
{
    $fallback = dirname(__DIR__) . '/templates/' . $name . '.php';
    if (!in_array($name, ['list','single','widget-list'], true) || !defined('PUBLIC_PATH') || !function_exists('get_active_theme_folder')) return $fallback;
    $folder = get_active_theme_folder($GLOBALS['pdo'] ?? null);
    if (!is_string($folder) || preg_match('/\A[A-Za-z0-9._-]{1,100}\z/', $folder) !== 1) return $fallback;
    $public = rtrim((string)PUBLIC_PATH, '/\\');
    $themes = $public . '/views/themes';
    $themeRoot = $themes . '/' . $folder;
    $pluginsRoot = $themeRoot . '/plugins';
    $directory = $pluginsRoot . '/job-board';
    $candidate = $directory . '/' . $name . '.php';
    $publicReal = !is_link($public) ? realpath($public) : false;
    $themesReal = !is_link($public . '/views') && !is_link($themes) ? realpath($themes) : false;
    $rootReal = !is_link($themeRoot) ? realpath($themeRoot) : false;
    $pluginsReal = !is_link($pluginsRoot) ? realpath($pluginsRoot) : false;
    $directoryReal = !is_link($directory) ? realpath($directory) : false;
    $candidateReal = !is_link($candidate) ? realpath($candidate) : false;
    if (is_string($publicReal) && is_string($themesReal) && is_string($rootReal) && is_string($pluginsReal)
        && is_string($directoryReal) && is_string($candidateReal)
        && str_starts_with($themesReal, $publicReal . DIRECTORY_SEPARATOR)
        && str_starts_with($rootReal, $themesReal . DIRECTORY_SEPARATOR)
        && str_starts_with($pluginsReal, $rootReal . DIRECTORY_SEPARATOR)
        && str_starts_with($directoryReal, $pluginsReal . DIRECTORY_SEPARATOR)
        && str_starts_with($candidateReal, $directoryReal . DIRECTORY_SEPARATOR) && is_file($candidateReal)) return $candidateReal;
    return $fallback;
}

function jyb_render_template(string $name, array $variables): string
{
    extract($variables, EXTR_SKIP);
    ob_start(); require jyb_template($name); return (string)ob_get_clean();
}

function jyb_frontend_assets(): void
{
    if (empty($GLOBALS['jyb_rendering'])) return;
    echo jyb_frontend_asset_tag();
}

function jyb_frontend_asset_tag(): string
{
    if (!empty($GLOBALS['jyb_frontend_asset_emitted'])) return '';
    $GLOBALS['jyb_frontend_asset_emitted'] = true;
    return '<link rel="stylesheet" href="/static/plugins/job-board/frontend.css?v=' . rawurlencode(JYB_VERSION) . '">' . PHP_EOL;
}

function jyb_render_document(string $content, string $title, string $description, string $canonical, string $context): void
{
    $GLOBALS['jyb_rendering'] = true;
    add_filter('document_meta_description', static fn(string $current): string => $description, PHP_INT_MAX);
    $content_html=$content; $page_title=$title; $metaDesc=$description; $canonical_url=$canonical;
    $context_for_layout=$context; $layout_full_width=true; $enable_sidebar=false; $post=null;
    require dirname(__DIR__, 3) . '/app/layout.php';
}

function jyb_cosmetic_404(): void
{
    require dirname(__DIR__, 3) . '/app/frontend_404.php';
}

function jyb_frontend_route(PDO $pdo): void
{
    if (!jyb_schema_ready($pdo)) { http_response_code(503); header('Retry-After: 300'); echo jyb_h(jyb_t('Job Board storage is unavailable.')); return; }
    $base = jyb_base_path($pdo); $path = jyb_request_path(); $locale = jyb_current_locale();
    if ($path === $base) {
        $filters = jyb_public_filters($_GET);
        $result = jyb_public_job_page($pdo, $locale, $filters, jyb_bounded_int($_GET['page_number'] ?? 1, 1, 1000000, 1), jyb_per_page($pdo));
        $options = jyb_public_filter_options($pdo, $locale);
        $content = jyb_render_template('list', compact('result','filters','options','base','locale'));
        $query = array_filter(['q'=>$filters['q'],'company'=>$filters['company'] ?: '','category'=>$filters['category'] ?: '','employment'=>$filters['employment'],'workplace'=>$filters['workplace'],'featured'=>$filters['featured'],'page_number'=>$result['page'] > 1 ? $result['page'] : ''], static fn(mixed $v): bool => $v !== '');
        $pathUrl = jyb_localized_path($base, $locale) . ($query === [] ? '' : '?' . http_build_query($query));
        jyb_set_current_resource_context([
            'adapter_key'=>'job-board',
            'resource_id'=>'list',
            'query'=>$query === [] ? null : http_build_query($query),
        ]);
        jyb_render_document($content, jyb_t('Jobs'), jyb_t('Browse current job opportunities.'), jyb_site_origin($pdo) . $pathUrl, 'job-board.list');
        return;
    }
    if (!str_starts_with($path, $base . '/')) { jyb_cosmetic_404(); return; }
    $suffix = substr($path, strlen($base) + 1);
    if ($suffix === '' || str_contains($suffix, '/') || jyb_slug($suffix) !== $suffix) { jyb_cosmetic_404(); return; }
    $job = jyb_public_job_by_slug($pdo, $locale, $suffix);
    if ($job === null) { jyb_cosmetic_404(); return; }
    $canonical = jyb_site_origin($pdo) . jyb_localized_path($base . '/' . rawurlencode((string)$job['slug']), $locale);
    $application = jyb_application_target($job['application_type'], $job['application_target']);
    if ($application === null) { jyb_cosmetic_404(); return; }
    jyb_set_current_resource_context(['adapter_key'=>'job-board','resource_id'=>'job:' . (int)$job['id']]);
    $content = jyb_render_template('single', compact('job','application','base','locale'));
    add_action('jy_head', static function () use ($job, $application, $canonical): void {
        $schema = ['@context'=>'https://schema.org','@type'=>'JobPosting','title'=>(string)$job['title'],'description'=>trim(strip_tags((string)$job['description'])),'datePosted'=>substr((string)($job['published_at_utc'] ?: $job['created_at_utc']),0,10),'employmentType'=>strtoupper(str_replace('-','_',(string)$job['employment_type'])),'hiringOrganization'=>['@type'=>'Organization','name'=>(string)$job['company_name']],'url'=>$canonical,'directApply'=>$application['type']==='url'];
        if (!empty($job['closes_at_utc'])) $schema['validThrough'] = str_replace(' ', 'T', (string)$job['closes_at_utc']) . 'Z';
        if (!empty($job['location_text'])) $schema[(string)$job['workplace_type'] === 'remote' ? 'jobLocationType' : 'jobLocation'] = (string)$job['workplace_type'] === 'remote' ? 'TELECOMMUTE' : ['@type'=>'Place','address'=>(string)$job['location_text']];
        echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>' . PHP_EOL;
    });
    jyb_render_document($content, (string)$job['title'], (string)($job['summary'] ?: jyb_t('Job opportunity at %s', $job['company_name'])), $canonical, 'job-board.single');
}
