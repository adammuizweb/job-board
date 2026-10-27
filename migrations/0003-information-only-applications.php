<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $version = strtolower((string)$pdo->query('SELECT VERSION()')->fetchColumn());
    $drop = str_contains($version, 'mariadb') ? 'DROP CONSTRAINT' : 'DROP CHECK';
    $pdo->exec(
        'ALTER TABLE `jyb_jobs` ' . $drop . ' `jyb_job_application_ck`, '
        . "ADD CONSTRAINT `jyb_job_application_ck` CHECK ((`application_type`='none' AND `application_target`='') OR `application_type` IN ('url','email'))"
    );
    $statement = $pdo->prepare(
        "SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='jyb_job_application_ck' LIMIT 1"
    );
    $statement->execute();
    $clause = strtolower((string)$statement->fetchColumn());
    if (!str_contains($clause, 'application_type') || !str_contains($clause, 'none') || !str_contains($clause, 'application_target')) {
        throw new RuntimeException('Job Board information-only application constraint is unavailable.');
    }
};
