<?php
declare(strict_types=1);

function jyb_uninstall(string $name): void
{
    if ($name !== 'job-board') return;
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!$pdo instanceof PDO) throw new RuntimeException('Job Board database is unavailable.');
    foreach (['jyb_audit','jyb_job_categories','jyb_job_texts','jyb_jobs','jyb_category_texts','jyb_categories','jyb_company_texts','jyb_companies'] as $table) $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
    $statement = $pdo->prepare("DELETE FROM settings WHERE `key` IN ('jyb_base_path','jyb_per_page')");
    $statement->execute();
}
