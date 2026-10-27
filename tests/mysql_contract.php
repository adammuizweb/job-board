<?php
declare(strict_types=1);

$dsn = getenv('JYB_TEST_DSN');
if (!is_string($dsn) || $dsn === '') {
    echo "SKIP MySQL contract requires JYB_TEST_DSN.\n";
    exit(0);
}

$root = dirname(__DIR__);
$pdo = new PDO($dsn, getenv('JYB_TEST_USER') ?: null, getenv('JYB_TEST_PASS') ?: null, [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false,
]);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if (preg_match('/\Ajyb_contract_[a-z0-9_]+\z/', $database) !== 1) {
    throw new RuntimeException('MySQL contract requires a dedicated jyb_contract_* database.');
}
if ((int)$pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchColumn() !== 0) {
    throw new RuntimeException('MySQL contract database must be empty.');
}

require_once $root . '/includes/validation.php';
require_once $root . '/includes/repository.php';
require_once $root . '/includes/lifecycle.php';
$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    echo ($ok ? 'PASS' : 'FAIL') . ' ' . $message . PHP_EOL;
    if (!$ok) $failures[] = $message;
};

try {
    $sql = (string)file_get_contents($root . '/migrations/0001-schema.sql');
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) $pdo->exec($statement);
    $verify = require $root . '/migrations/0002-verify-schema.php';
    $verify($pdo);
    $check(jyb_schema_ready($pdo), 'fresh schema passes strict verification');

    $now = '2026-10-26 12:00:00';
    $pdo->exec('CREATE TABLE settings (`key` VARCHAR(191) PRIMARY KEY,`value` TEXT,`autoload` TINYINT NOT NULL DEFAULT 1) ENGINE=InnoDB');
    $pdo->prepare('INSERT INTO jyb_companies (source_locale,status,created_at_utc,updated_at_utc) VALUES (?,?,?,?)')->execute(['en','active',$now,$now]);
    $company = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO jyb_company_texts VALUES (?,?,?,?,?,?)')->execute([$company,'en','Example Cooperative',null,'published',$now]);
    $pdo->prepare('INSERT INTO jyb_company_texts VALUES (?,?,?,?,?,?)')->execute([$company,'de','Beispiel Cooperative',null,'draft',$now]);

    $pdo->prepare('INSERT INTO jyb_categories (source_locale,status,created_at_utc,updated_at_utc) VALUES (?,?,?,?)')->execute(['en','active',$now,$now]);
    $category = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO jyb_category_texts VALUES (?,?,?,?,?,?)')->execute([$category,'en','Engineering','engineering','published',$now]);
    $pdo->prepare('INSERT INTO jyb_category_texts VALUES (?,?,?,?,?,?)')->execute([$category,'de','Technik','technik','published',$now]);

    $pdo->prepare('INSERT INTO jyb_jobs (company_id,source_locale,status,employment_type,workplace_type,reference_code,application_type,application_target,published_at_utc,created_at_utc,updated_at_utc) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$company,'en','published','full-time','remote','REF-1','url','https://example.test/apply',$now,$now,$now]);
    $job = (int)$pdo->lastInsertId();
    $text = $pdo->prepare('INSERT INTO jyb_job_texts VALUES (?,?,?,?,?,?,?,?,?)');
    $text->execute([$job,'en','sample-role','Sample Role','Summary','Description','Remote','published',$now]);
    $text->execute([$job,'de','beispiel-rolle','Beispiel Rolle','Zusammenfassung','Beschreibung','Remote','draft',$now]);
    $pdo->prepare('INSERT INTO jyb_job_categories VALUES (?,?)')->execute([$job,$category]);

    $sourceJob = jyb_public_job_by_slug($pdo, 'en', 'sample-role');
    $check(is_array($sourceJob) && (int)($sourceJob['categories'][0]['id'] ?? 0) === $category, 'eligible detail query returns its localized category identity');
    $page = jyb_public_job_page($pdo, 'en', ['open'=>'1'], 1, 12);
    $check($page['total'] === 1 && count($page['rows']) === 1, 'public page count and rows use executable exact-locale SQL');
    $check(jyb_public_job_by_slug($pdo, 'de', 'beispiel-rolle') === null, 'draft exact locale does not fall back to source');
    $pdo->exec("UPDATE jyb_job_texts SET translation_status='published' WHERE job_id={$job} AND locale='de'");
    $check(jyb_public_job_by_slug($pdo, 'de', 'beispiel-rolle') === null, 'draft company locale also fails closed');
    $pdo->exec("UPDATE jyb_company_texts SET translation_status='published' WHERE company_id={$company} AND locale='de'");
    $check(is_array(jyb_public_job_by_slug($pdo, 'de', 'beispiel-rolle')), 'fully eligible localized job becomes public');

    $constraintRejected = false;
    try {
        $pdo->prepare('UPDATE jyb_jobs SET status=? WHERE id=?')->execute(['invalid',$job]);
    } catch (PDOException) {
        $constraintRejected = true;
    }
    $check($constraintRejected, 'database rejects invalid workflow states');

    $duplicateReferenceRejected = false;
    try {
        $pdo->prepare('INSERT INTO jyb_jobs (company_id,source_locale,status,employment_type,workplace_type,reference_code,application_type,application_target,created_at_utc,updated_at_utc) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([$company,'en','draft','full-time','remote','REF-1','email','jobs@example.test',$now,$now]);
    } catch (PDOException) {
        $duplicateReferenceRejected = true;
    }
    $check($duplicateReferenceRejected, 'database rejects duplicate non-empty company references');

    $GLOBALS['pdo'] = $pdo;
    jyb_uninstall('job-board');
    $remaining = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE 'jyb=_%' ESCAPE '='")->fetchColumn();
    $check($remaining === 0, 'complete uninstall removes every plugin table');
} finally {
    foreach (['jyb_audit','jyb_job_categories','jyb_job_texts','jyb_jobs','jyb_category_texts','jyb_categories','jyb_company_texts','jyb_companies','settings'] as $table) {
        $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
    }
}

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " MySQL check(s) failed.\n");
    exit(1);
}
echo "Job Board MySQL contract passed.\n";
