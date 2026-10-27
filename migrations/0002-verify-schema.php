<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $required = [
        'jyb_companies' => ['id','source_locale','status','website_url','version','created_by','updated_by','created_at_utc','updated_at_utc'],
        'jyb_company_texts' => ['company_id','locale','name','description','translation_status','updated_at_utc'],
        'jyb_categories' => ['id','source_locale','status','version','created_by','updated_by','created_at_utc','updated_at_utc'],
        'jyb_category_texts' => ['category_id','locale','name','slug','translation_status','updated_at_utc'],
        'jyb_jobs' => ['id','company_id','source_locale','status','employment_type','workplace_type','reference_code','application_type','application_target','featured','opens_at_utc','closes_at_utc','published_at_utc','closed_at_utc','archived_at_utc','version','created_by','updated_by','created_at_utc','updated_at_utc'],
        'jyb_job_texts' => ['job_id','locale','slug','title','summary','description','location_text','translation_status','updated_at_utc'],
        'jyb_job_categories' => ['job_id','category_id'],
        'jyb_audit' => ['id','actor_id','action','entity_type','entity_id','before_json','after_json','request_id','created_at_utc'],
    ];
    $notNull = [
        'jyb_companies'=>['id','source_locale','status','version','created_at_utc','updated_at_utc'],
        'jyb_company_texts'=>['company_id','locale','name','translation_status','updated_at_utc'],
        'jyb_categories'=>['id','source_locale','status','version','created_at_utc','updated_at_utc'],
        'jyb_category_texts'=>['category_id','locale','name','slug','translation_status','updated_at_utc'],
        'jyb_jobs'=>['id','company_id','source_locale','status','employment_type','workplace_type','application_type','application_target','featured','version','created_at_utc','updated_at_utc'],
        'jyb_job_texts'=>['job_id','locale','slug','title','description','translation_status','updated_at_utc'],
        'jyb_job_categories'=>['job_id','category_id'],
        'jyb_audit'=>['id','action','entity_type','created_at_utc'],
    ];
    $defaults = [
        'jyb_companies'=>['status'=>'active','version'=>'1'],
        'jyb_company_texts'=>['translation_status'=>'published'],
        'jyb_categories'=>['status'=>'active','version'=>'1'],
        'jyb_category_texts'=>['translation_status'=>'published'],
        'jyb_jobs'=>['status'=>'draft','featured'=>'0','version'=>'1'],
        'jyb_job_texts'=>['translation_status'=>'draft'],
    ];
    $ascii = [
        'jyb_companies'=>['source_locale','status'],
        'jyb_company_texts'=>['locale','translation_status'],
        'jyb_categories'=>['source_locale','status'],
        'jyb_category_texts'=>['locale','slug','translation_status'],
        'jyb_jobs'=>['source_locale','status','employment_type','workplace_type','application_type'],
        'jyb_job_texts'=>['locale','slug','translation_status'],
    ];
    $column = $pdo->prepare('SELECT COLUMN_NAME,DATA_TYPE,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,CHARACTER_SET_NAME,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    foreach ($required as $table => $columns) {
        $column->execute([$table]);
        $available = [];
        foreach ($column->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) $available[(string)$row['COLUMN_NAME']] = $row;
        foreach ($columns as $name) if (!isset($available[$name])) throw new RuntimeException('Job Board schema is incompatible: ' . $table . '.' . $name);
        foreach ($available as $name => $definition) {
            $type = strtolower((string)$definition['DATA_TYPE']);
            if (str_ends_with($name, '_utc') && $type !== 'datetime') throw new RuntimeException('Job Board UTC column must use DATETIME: ' . $table . '.' . $name);
        }
        if ($table === 'jyb_job_texts' && strtolower((string)$available['description']['DATA_TYPE']) !== 'mediumtext') {
            throw new RuntimeException('Job Board job descriptions must use MEDIUMTEXT.');
        }
        if (in_array($table, ['jyb_companies','jyb_categories','jyb_jobs','jyb_audit'], true)) {
            $id = $available['id'];
            $columnType = strtolower((string)$id['COLUMN_TYPE']);
            $extra = strtolower((string)$id['EXTRA']);
            if (!str_starts_with($columnType, 'bigint') || !str_contains($columnType, 'unsigned') || !str_contains($extra, 'auto_increment')) {
                throw new RuntimeException('Job Board identity column is incompatible: ' . $table . '.id');
            }
        }
        foreach ($notNull[$table] as $name) {
            if (($available[$name]['IS_NULLABLE'] ?? '') !== 'NO') throw new RuntimeException('Job Board required column is nullable: ' . $table . '.' . $name);
        }
        foreach ($defaults[$table] ?? [] as $name => $expected) {
            $actual = trim((string)($available[$name]['COLUMN_DEFAULT'] ?? ''), "'\"");
            if ($actual !== $expected) throw new RuntimeException('Job Board column default is incompatible: ' . $table . '.' . $name);
        }
        foreach ($ascii[$table] ?? [] as $name) {
            if (($available[$name]['CHARACTER_SET_NAME'] ?? '') !== 'ascii' || ($available[$name]['COLLATION_NAME'] ?? '') !== 'ascii_bin') {
                throw new RuntimeException('Job Board identifier column must use ascii_bin: ' . $table . '.' . $name);
            }
        }
    }
    $marks = implode(',', array_fill(0, count($required), '?'));
    $tables = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (' . $marks . ") AND ENGINE='InnoDB' AND TABLE_COLLATION LIKE 'utf8mb4%'");
    $tables->execute(array_keys($required));
    if ((int)$tables->fetchColumn() !== count($required)) throw new RuntimeException('Job Board tables must use InnoDB and utf8mb4.');
    foreach ([
        ['jyb_companies','PRIMARY','id'],
        ['jyb_company_texts','PRIMARY','company_id,locale'],
        ['jyb_categories','PRIMARY','id'],
        ['jyb_category_texts','PRIMARY','category_id,locale'],
        ['jyb_category_texts','jyb_category_locale_slug_uq','locale,slug'],
        ['jyb_jobs','PRIMARY','id'],
        ['jyb_jobs','jyb_job_company_reference_uq','company_id,reference_code'],
        ['jyb_job_texts','PRIMARY','job_id,locale'],
        ['jyb_job_texts','jyb_job_locale_slug_uq','locale,slug'],
        ['jyb_job_categories','PRIMARY','job_id,category_id'],
        ['jyb_audit','PRIMARY','id'],
    ] as [$table, $index, $expectedColumns]) {
        $statement = $pdo->prepare("SELECT NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') columns_csv FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? GROUP BY NON_UNIQUE LIMIT 1");
        $statement->execute([$table, $index]);
        $value = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($value) || (int)$value['NON_UNIQUE'] !== 0 || (string)$value['columns_csv'] !== $expectedColumns) {
            throw new RuntimeException('Job Board unique index is incompatible: ' . $table . '.' . $index);
        }
    }
    $foreignKeys = [
        'jyb_company_text_company_fk'=>'CASCADE',
        'jyb_category_text_category_fk'=>'CASCADE',
        'jyb_job_company_fk'=>'RESTRICT',
        'jyb_job_text_job_fk'=>'CASCADE',
        'jyb_job_category_job_fk'=>'CASCADE',
        'jyb_job_category_category_fk'=>'RESTRICT',
    ];
    $statement = $pdo->prepare('SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME=? LIMIT 1');
    foreach ($foreignKeys as $constraint => $deleteRule) {
        $statement->execute([$constraint]);
        if ($statement->fetchColumn() !== $deleteRule) throw new RuntimeException('Job Board foreign-key rule is incompatible: ' . $constraint);
    }
    $checks = [
        'jyb_company_status_ck','jyb_company_text_status_ck','jyb_category_status_ck',
        'jyb_category_text_status_ck','jyb_job_status_ck','jyb_job_employment_ck',
        'jyb_job_workplace_ck','jyb_job_application_ck','jyb_job_featured_ck','jyb_job_text_status_ck',
    ];
    $checkStatement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_TYPE=\'CHECK\' AND CONSTRAINT_NAME=?');
    foreach ($checks as $constraint) {
        $checkStatement->execute([$constraint]);
        if ((int)$checkStatement->fetchColumn() !== 1) throw new RuntimeException('Job Board check constraint is missing: ' . $constraint);
    }
    $actorFks = $pdo->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('jyb_companies','jyb_categories','jyb_jobs','jyb_audit') AND COLUMN_NAME IN ('created_by','updated_by','actor_id') AND REFERENCED_TABLE_NAME IS NOT NULL");
    if ((int)$actorFks->fetchColumn() !== 0) throw new RuntimeException('Job Board actor IDs must remain historical values without Core user foreign keys.');
};
