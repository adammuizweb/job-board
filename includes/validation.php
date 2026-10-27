<?php
declare(strict_types=1);

function jyb_scalar(mixed $value): string
{
    return is_string($value) || is_int($value) || is_float($value) ? (string)$value : '';
}

function jyb_text(mixed $value, int $max, bool $singleLine = true): string
{
    $value = trim(jyb_scalar($value));
    if (preg_match('//u', $value) !== 1) return '';
    if ($singleLine) $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
    else $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    return mb_substr(trim($value), 0, $max, 'UTF-8');
}

function jyb_slug(mixed $value): ?string
{
    $value = strtolower(trim(jyb_scalar($value)));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value !== '' && strlen($value) <= 191 ? $value : null;
}

function jyb_locale(mixed $value): ?string
{
    $value = str_replace('_', '-', trim(jyb_scalar($value)));
    return strlen($value) <= 12 && preg_match('/\A[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})?\z/', $value) === 1 ? $value : null;
}

function jyb_positive_id(mixed $value): int
{
    if (!is_string($value) && !is_int($value)) return 0;
    return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false ? (int)$value : 0;
}

function jyb_bounded_int(mixed $value, int $min, int $max, int $default): int
{
    if (!is_string($value) && !is_int($value)) return $default;
    $valid = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
    return $valid === false ? $default : (int)$valid;
}

function jyb_https_url(mixed $value, bool $allowEmpty = true): ?string
{
    $value = trim(jyb_scalar($value));
    if ($value === '') return $allowEmpty ? '' : null;
    $decoded = rawurldecode($value);
    if (strlen($value) > 2048 || preg_match('/[\x00-\x20\x7F]/', $decoded) === 1 || str_contains($decoded, '\\')) return null;
    $parts = parse_url($value);
    if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
        || trim((string)($parts['host'] ?? '')) === '' || isset($parts['user']) || isset($parts['pass'])) return null;
    return filter_var($value, FILTER_VALIDATE_URL) !== false ? $value : null;
}

function jyb_application_target(mixed $type, mixed $target): ?array
{
    $type = strtolower(trim(jyb_scalar($type)));
    $target = trim(jyb_scalar($target));
    if ($type === 'none') return $target === '' ? ['type' => 'none', 'target' => ''] : null;
    if ($type === 'url') {
        $url = jyb_https_url($target, false);
        return $url === null ? null : ['type' => 'url', 'target' => $url];
    }
    if ($type === 'email' && strlen($target) <= 254 && filter_var($target, FILTER_VALIDATE_EMAIL) !== false
        && preg_match('/[\r\n]/', $target) !== 1) return ['type' => 'email', 'target' => strtolower($target)];
    return null;
}

function jyb_datetime_utc(mixed $value): ?string
{
    $value = trim(jyb_scalar($value));
    if ($value === '') return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new DateTimeZone('UTC'));
    $errors = DateTimeImmutable::getLastErrors();
    return $date instanceof DateTimeImmutable && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        ? $date->format('Y-m-d H:i:s') : null;
}

function jyb_employment_types(): array
{
    return ['full-time','part-time','contract','temporary','internship','volunteer','other'];
}

function jyb_workplace_types(): array
{
    return ['on-site','hybrid','remote'];
}

function jyb_job_statuses(): array
{
    return ['draft','published','closed','archived'];
}

function jyb_public_filters(array $input): array
{
    $employment = strtolower(jyb_text($input['employment'] ?? '', 30));
    $workplace = strtolower(jyb_text($input['workplace'] ?? '', 20));
    $featured = jyb_scalar($input['featured'] ?? '') === '1' ? '1' : '';
    $open = jyb_scalar($input['open'] ?? '1') === '0' ? '0' : '1';
    return [
        'q' => jyb_text($input['q'] ?? '', 100),
        'company' => jyb_positive_id($input['company'] ?? 0),
        'category' => jyb_positive_id($input['category'] ?? ($input['job_category'] ?? 0)),
        'employment' => in_array($employment, jyb_employment_types(), true) ? $employment : '',
        'workplace' => in_array($workplace, jyb_workplace_types(), true) ? $workplace : '',
        'featured' => $featured,
        'open' => $open,
    ];
}
