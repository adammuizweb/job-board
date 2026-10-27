<?php
declare(strict_types=1);
$root=dirname(__DIR__);$sql=(string)file_get_contents($root.'/migrations/0001-schema.sql');$verify=(string)file_get_contents($root.'/migrations/0002-verify-schema.php');$failures=[];$check=static function(bool $ok,string $message)use(&$failures):void{echo($ok?'PASS':'FAIL').' '.$message.PHP_EOL;if(!$ok)$failures[]=$message;};
$tables=['jyb_companies','jyb_company_texts','jyb_categories','jyb_category_texts','jyb_jobs','jyb_job_texts','jyb_job_categories','jyb_audit'];foreach($tables as $table)$check(str_contains($sql,'CREATE TABLE IF NOT EXISTS `'.$table.'`'),'schema creates '.$table);
$check(substr_count($sql,'ENGINE=InnoDB')===8&&substr_count($sql,'CHARSET=utf8mb4')===8,'all plugin tables use InnoDB and utf8mb4');
$check(str_contains($sql,'UNIQUE KEY `jyb_job_locale_slug_uq` (`locale`, `slug`)'),'localized job slugs are unique per locale');
$check(str_contains($sql,'UNIQUE KEY `jyb_job_company_reference_uq` (`company_id`, `reference_code`)'),'non-empty job references are unique per company');
$check(substr_count($sql,'CHARACTER SET ascii COLLATE ascii_bin')>=14,'machine identifiers use exact ASCII comparison');
$check(substr_count($sql,' CONSTRAINT `')>=16&&str_contains($sql,'`jyb_job_status_ck` CHECK')&&str_contains($verify,'information_schema.TABLE_CONSTRAINTS'),'bounded states are enforced and verified with database checks');
$check(str_contains($sql,'`description` MEDIUMTEXT NOT NULL')&&str_contains($verify,'must use MEDIUMTEXT'),'bounded 30,000-character job descriptions fit the database column');
$check(substr_count($verify,"'PRIMARY'")>=8&&str_contains($verify,'IS_NULLABLE')&&str_contains($verify,'COLLATION_NAME')&&str_contains($verify,'auto_increment'),'strict verifier checks identities, operational primary keys, nullability, defaults, and identifier collations');
$check(!str_contains($sql,'CURRENT_TIMESTAMP')&&!preg_match('/\bNOW\s*\(/i',$sql),'schema has no implicit local-time writes');
$check(substr_count($sql,'_utc` DATETIME')>=12,'instant columns use explicit UTC DATETIME names');
$check(!preg_match('/FOREIGN KEY \(`(?:created_by|updated_by|actor_id)`\)/',$sql),'historical actor IDs do not reference Core users');
$check(str_contains($verify,'information_schema.COLUMNS')&&str_contains($verify,'information_schema.REFERENTIAL_CONSTRAINTS')&&str_contains($verify,'actor IDs must remain historical'),'strict verifier checks columns, foreign keys, and actor isolation');
$migrationFiles=glob($root.'/migrations/*')?:[];sort($migrationFiles);$check(array_map('basename',$migrationFiles)===['0001-schema.sql','0002-verify-schema.php'],'migrations are append-only canonical sequences');
if($failures!==[]){fwrite(STDERR,count($failures)." schema check(s) failed.\n");exit(1);}echo "Job Board schema contract passed.\n";
