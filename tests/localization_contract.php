<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    echo ($ok ? 'PASS' : 'FAIL') . ' ' . $message . PHP_EOL;
    if (!$ok) $failures[] = $message;
};

$source = '';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    $path = $file->getPathname();
    if (!$file->isFile() || $file->getExtension() !== 'php' || str_contains($path, '/languages/') || str_contains($path, '/tests/')) continue;
    $source .= (string)file_get_contents($path) . "\n";
}
preg_match_all("/jyb_t\\('((?:[^'\\\\]|\\\\.)*)'/", $source, $matches);
$keys = array_values(array_unique(array_map('stripcslashes', $matches[1] ?? [])));
sort($keys);

foreach (['id', 'de'] as $locale) {
    $catalog = require $root . '/languages/' . $locale . '.php';
    $check(is_array($catalog) && !array_is_list($catalog), strtoupper($locale) . ' catalog is an associative map');
    $check(array_diff($keys, array_keys($catalog)) === [], strtoupper($locale) . ' catalog covers every static Job Board string');
    $check(count(array_filter($catalog, static fn(mixed $value): bool => !is_string($value) || $value === '')) === 0, strtoupper($locale) . ' catalog has no empty or non-string translations');
    $generated = ['Draft','Published','Closed','Archived','Active','Inactive','Full Time','Part Time','Contract','Temporary','Internship','Volunteer','Other','On Site','Hybrid','Remote'];
    $check(array_diff($generated, array_keys($catalog)) === [], strtoupper($locale) . ' catalog covers generated workflow and type labels');
}

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " localization check(s) failed.\n");
    exit(1);
}
echo "Job Board localization contract passed.\n";
