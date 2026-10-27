<?php
declare(strict_types=1);

function jyb_now_utc(): string
{
    return function_exists('app_now_utc_mysql') ? app_now_utc_mysql() : gmdate('Y-m-d H:i:s');
}

function jyb_base_path(?PDO $pdo = null): string
{
    $value = $pdo instanceof PDO && function_exists('settings_get') ? settings_get($pdo, 'jyb_base_path', 'jobs') : 'jobs';
    return jyb_slug($value) ?? 'jobs';
}

function jyb_assert_base_path_available(PDO $pdo, string $base, bool $lock = false): void
{
    if (function_exists('content_route_path_conflict')) {
        $conflict = content_route_path_conflict($pdo, $base);
        $ownedRoute = is_array($conflict) && ($conflict['type'] ?? '') === 'plugin_route'
            && ($conflict['value'] ?? '') === $base && $base === jyb_base_path($pdo);
        if ($conflict !== null && !$ownedRoute) throw new RuntimeException('Job Board base path conflicts with a protected route.');
    } elseif (function_exists('content_route_reserved_conflict')) {
        $conflict = content_route_reserved_conflict($pdo, $base);
        $ownedRoute = is_array($conflict) && ($conflict['type'] ?? '') === 'plugin_route'
            && ($conflict['value'] ?? '') === $base && $base === jyb_base_path($pdo);
        if ($conflict !== null && !$ownedRoute) throw new RuntimeException('Job Board base path conflicts with a protected route.');
    }
    $suffix = $lock ? ' FOR UPDATE' : '';
    $statement = $pdo->prepare("SELECT id FROM content_routes WHERE path=? OR path LIKE ? ESCAPE '=' LIMIT 1" . $suffix);
    $statement->execute([$base, str_replace(['=','%','_'], ['==','=%','=_'], $base) . '/%']);
    if ($statement->fetchColumn()) throw new RuntimeException('Job Board base path conflicts with an existing content route.');
    $statement = $pdo->prepare("SELECT id FROM posts WHERE slug=? AND is_deleted=0 AND type IN ('article','page','theme') LIMIT 1" . $suffix);
    $statement->execute([$base]);
    if ($statement->fetchColumn()) throw new RuntimeException('Job Board base path conflicts with existing content.');
    if ($lock) {
        $statement = $pdo->prepare('SELECT id FROM categories WHERE slug=? AND is_deleted=0 LIMIT 1 FOR UPDATE');
        $statement->execute([$base]);
        if ($statement->fetchColumn()) throw new RuntimeException('Job Board base path conflicts with an existing category.');
    }
}

function jyb_per_page(?PDO $pdo = null): int
{
    $value = $pdo instanceof PDO && function_exists('settings_get') ? settings_get($pdo, 'jyb_per_page', '12') : '12';
    return jyb_bounded_int($value, 1, 48, 12);
}

function jyb_schema_ready(PDO $pdo): bool
{
    static $ready = [];
    $key = spl_object_id($pdo);
    if (($ready[$key] ?? false) === true) return true;
    try {
        $required = ['jyb_companies','jyb_company_texts','jyb_categories','jyb_category_texts','jyb_jobs','jyb_job_texts','jyb_job_categories','jyb_audit'];
        $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (' . implode(',', array_fill(0, count($required), '?')) . ')');
        $statement->execute($required);
        return $ready[$key] = (int)$statement->fetchColumn() === count($required);
    } catch (Throwable $error) {
        error_log('[job-board] schema check failed: ' . $error->getMessage());
        return false;
    }
}

function jyb_like(string $value): string
{
    return '%' . str_replace(['=', '%', '_'], ['==', '=%', '=_'], $value) . '%';
}

function jyb_public_job_page(PDO $pdo, string $locale, array $filters = [], int $page = 1, int $perPage = 12): array
{
    $locale = jyb_locale($locale) ?? '';
    if ($locale === '') return ['rows'=>[],'total'=>0,'page'=>1,'pages'=>1,'per_page'=>max(1,min(48,$perPage))];
    $filters = jyb_public_filters($filters);
    $perPage = max(1, min(48, $perPage));
    $page = max(1, $page);
    $where = ["j.status='published'", "jt.translation_status='published'", "c.status='active'", "ct.translation_status='published'", '(j.opens_at_utc IS NULL OR j.opens_at_utc<=UTC_TIMESTAMP())', '(j.closes_at_utc IS NULL OR j.closes_at_utc>UTC_TIMESTAMP())'];
    $params = [':job_locale' => $locale, ':company_locale' => $locale];
    if ($filters['q'] !== '') {
        $where[] = "(jt.title LIKE :search_title ESCAPE '=' OR jt.summary LIKE :search_summary ESCAPE '=' OR jt.location_text LIKE :search_location ESCAPE '=' OR ct.name LIKE :search_company ESCAPE '=')";
        foreach ([':search_title',':search_summary',':search_location',':search_company'] as $name) $params[$name] = jyb_like($filters['q']);
    }
    if ($filters['company'] > 0) { $where[] = 'j.company_id=:company'; $params[':company'] = $filters['company']; }
    if ($filters['category'] > 0) { $where[] = 'EXISTS (SELECT 1 FROM jyb_job_categories jcf WHERE jcf.job_id=j.id AND jcf.category_id=:category)'; $params[':category'] = $filters['category']; }
    foreach ([['employment','j.employment_type'],['workplace','j.workplace_type']] as [$key,$column]) if ($filters[$key] !== '') { $where[] = $column . '=:' . $key; $params[':' . $key] = $filters[$key]; }
    if ($filters['featured'] === '1') $where[] = 'j.featured=1';
    $where[] = "((j.application_type='url' AND j.application_target LIKE 'https://%') OR (j.application_type='email' AND j.application_target LIKE '%@%'))";
    $from = ' FROM jyb_jobs j JOIN jyb_job_texts jt ON jt.job_id=j.id AND jt.locale=:job_locale JOIN jyb_companies c ON c.id=j.company_id JOIN jyb_company_texts ct ON ct.company_id=c.id AND ct.locale=:company_locale';
    $whereSql = implode(' AND ', $where);
    $count = $pdo->prepare('SELECT COUNT(DISTINCT j.id)' . $from . ' WHERE ' . $whereSql);
    $count->execute($params);
    $total = (int)$count->fetchColumn();
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    $query = $pdo->prepare('SELECT j.*,jt.slug,jt.title,jt.summary,jt.description,jt.location_text,ct.name company_name,ct.description company_description' . $from . ' WHERE ' . $whereSql . ' ORDER BY j.featured DESC,COALESCE(j.published_at_utc,j.created_at_utc) DESC,j.id DESC LIMIT :limit OFFSET :offset');
    foreach ($params as $name => $value) $query->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $query->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
    $query->execute();
    $rows = [];
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        if (jyb_application_target($row['application_type'] ?? '', $row['application_target'] ?? '') !== null) $rows[] = $row;
    }
    return ['rows'=>$rows,'total'=>$total,'page'=>$page,'pages'=>$pages,'per_page'=>$perPage];
}

function jyb_company_texts(PDO $pdo, int $id): array
{
    $statement = $pdo->prepare('SELECT * FROM jyb_company_texts WHERE company_id=? ORDER BY locale');
    $statement->execute([$id]);
    return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function jyb_category(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT * FROM jyb_categories WHERE id=? LIMIT 1');
    $statement->execute([$id]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) return null;
    $texts = $pdo->prepare('SELECT * FROM jyb_category_texts WHERE category_id=? ORDER BY locale');
    $texts->execute([$id]);
    $row['texts'] = $texts->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return $row;
}

function jyb_public_job_by_slug(PDO $pdo, string $locale, string $slug): ?array
{
    $locale = jyb_locale($locale); $slug = jyb_slug($slug);
    if ($locale === null || $slug === null) return null;
    $statement = $pdo->prepare("SELECT j.*,jt.slug,jt.title,jt.summary,jt.description,jt.location_text,ct.name company_name,ct.description company_description,c.website_url FROM jyb_jobs j JOIN jyb_job_texts jt ON jt.job_id=j.id AND jt.locale=? AND jt.slug=? AND jt.translation_status='published' JOIN jyb_companies c ON c.id=j.company_id AND c.status='active' JOIN jyb_company_texts ct ON ct.company_id=c.id AND ct.locale=? AND ct.translation_status='published' WHERE j.status='published' AND (j.opens_at_utc IS NULL OR j.opens_at_utc<=UTC_TIMESTAMP()) AND (j.closes_at_utc IS NULL OR j.closes_at_utc>UTC_TIMESTAMP()) LIMIT 1");
    $statement->execute([$locale, $slug, $locale]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row) || jyb_application_target($row['application_type'], $row['application_target']) === null) return null;
    $categories = $pdo->prepare("SELECT c.id,t.name,t.slug FROM jyb_job_categories jc JOIN jyb_categories c ON c.id=jc.category_id AND c.status='active' JOIN jyb_category_texts t ON t.category_id=c.id AND t.locale=? AND t.translation_status='published' WHERE jc.job_id=? ORDER BY t.name,c.id");
    $categories->execute([$locale, (int)$row['id']]);
    $row['categories'] = $categories->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return $row;
}

function jyb_public_filter_options(PDO $pdo, string $locale): array
{
    $locale = jyb_locale($locale);
    if ($locale === null) return ['companies'=>[],'categories'=>[]];
    $companies = $pdo->prepare("SELECT c.id,t.name FROM jyb_companies c JOIN jyb_company_texts t ON t.company_id=c.id AND t.locale=? AND t.translation_status='published' WHERE c.status='active' ORDER BY t.name,c.id");
    $companies->execute([$locale]);
    $categories = $pdo->prepare("SELECT c.id,t.name,t.slug FROM jyb_categories c JOIN jyb_category_texts t ON t.category_id=c.id AND t.locale=? AND t.translation_status='published' WHERE c.status='active' ORDER BY t.name,c.id");
    $categories->execute([$locale]);
    return ['companies'=>$companies->fetchAll(PDO::FETCH_ASSOC) ?: [],'categories'=>$categories->fetchAll(PDO::FETCH_ASSOC) ?: []];
}

function jyb_job(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT j.*,t.slug,t.title,t.summary,t.description,t.location_text,t.translation_status FROM jyb_jobs j JOIN jyb_job_texts t ON t.job_id=j.id AND t.locale=j.source_locale WHERE j.id=? LIMIT 1');
    $statement->execute([$id]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) return null;
    $texts = $pdo->prepare('SELECT * FROM jyb_job_texts WHERE job_id=? ORDER BY locale'); $texts->execute([$id]);
    $categories = $pdo->prepare('SELECT category_id FROM jyb_job_categories WHERE job_id=? ORDER BY category_id'); $categories->execute([$id]);
    $row['texts'] = $texts->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $row['category_ids'] = array_map('intval', $categories->fetchAll(PDO::FETCH_COLUMN) ?: []);
    return $row;
}

function jyb_audit(PDO $pdo, ?int $actorId, string $action, string $entityType, ?int $entityId, ?array $before, ?array $after): void
{
    if (!$pdo->inTransaction()) throw new LogicException('Job Board audit writes require a transaction.');
    $requestId = bin2hex(random_bytes(16));
    $statement = $pdo->prepare('INSERT INTO jyb_audit (actor_id,action,entity_type,entity_id,before_json,after_json,request_id,created_at_utc) VALUES (?,?,?,?,?,?,?,?)');
    $statement->execute([$actorId,$action,$entityType,$entityId,$before === null ? null : json_encode($before, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),$after === null ? null : json_encode($after, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),$requestId,jyb_now_utc()]);
}
