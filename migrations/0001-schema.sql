CREATE TABLE IF NOT EXISTS `jyb_companies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source_locale` VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `status` VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
  `website_url` VARCHAR(2048) DEFAULT NULL,
  `version` INT UNSIGNED NOT NULL DEFAULT 1,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at_utc` DATETIME NOT NULL,
  `updated_at_utc` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jyb_company_public` (`status`, `id`),
  CONSTRAINT `jyb_company_status_ck` CHECK (`status` IN ('active','inactive'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jyb_company_texts` (
  `company_id` BIGINT UNSIGNED NOT NULL,
  `locale` VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `name` VARCHAR(191) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `translation_status` VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'published',
  `updated_at_utc` DATETIME NOT NULL,
  PRIMARY KEY (`company_id`, `locale`),
  KEY `jyb_company_text_public` (`locale`, `translation_status`, `name`),
  CONSTRAINT `jyb_company_text_company_fk` FOREIGN KEY (`company_id`) REFERENCES `jyb_companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jyb_company_text_status_ck` CHECK (`translation_status` IN ('draft','published'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jyb_categories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source_locale` VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `status` VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
  `version` INT UNSIGNED NOT NULL DEFAULT 1,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at_utc` DATETIME NOT NULL,
  `updated_at_utc` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jyb_category_public` (`status`, `id`),
  CONSTRAINT `jyb_category_status_ck` CHECK (`status` IN ('active','inactive'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jyb_category_texts` (
  `category_id` BIGINT UNSIGNED NOT NULL,
  `locale` VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `name` VARCHAR(191) NOT NULL,
  `slug` VARCHAR(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `translation_status` VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'published',
  `updated_at_utc` DATETIME NOT NULL,
  PRIMARY KEY (`category_id`, `locale`),
  UNIQUE KEY `jyb_category_locale_slug_uq` (`locale`, `slug`),
  KEY `jyb_category_text_public` (`locale`, `translation_status`, `name`),
  CONSTRAINT `jyb_category_text_category_fk` FOREIGN KEY (`category_id`) REFERENCES `jyb_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jyb_category_text_status_ck` CHECK (`translation_status` IN ('draft','published'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jyb_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `source_locale` VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `status` VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft',
  `employment_type` VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `workplace_type` VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `reference_code` VARCHAR(100) DEFAULT NULL,
  `application_type` VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `application_target` VARCHAR(2048) NOT NULL,
  `featured` TINYINT(1) NOT NULL DEFAULT 0,
  `opens_at_utc` DATETIME DEFAULT NULL,
  `closes_at_utc` DATETIME DEFAULT NULL,
  `published_at_utc` DATETIME DEFAULT NULL,
  `closed_at_utc` DATETIME DEFAULT NULL,
  `archived_at_utc` DATETIME DEFAULT NULL,
  `version` INT UNSIGNED NOT NULL DEFAULT 1,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at_utc` DATETIME NOT NULL,
  `updated_at_utc` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jyb_job_public` (`status`, `opens_at_utc`, `closes_at_utc`, `featured`, `id`),
  KEY `jyb_job_company` (`company_id`, `status`, `id`),
  UNIQUE KEY `jyb_job_company_reference_uq` (`company_id`, `reference_code`),
  CONSTRAINT `jyb_job_company_fk` FOREIGN KEY (`company_id`) REFERENCES `jyb_companies` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `jyb_job_status_ck` CHECK (`status` IN ('draft','published','closed','archived')),
  CONSTRAINT `jyb_job_employment_ck` CHECK (`employment_type` IN ('full-time','part-time','contract','temporary','internship','volunteer','other')),
  CONSTRAINT `jyb_job_workplace_ck` CHECK (`workplace_type` IN ('on-site','hybrid','remote')),
  CONSTRAINT `jyb_job_application_ck` CHECK (`application_type` IN ('url','email')),
  CONSTRAINT `jyb_job_featured_ck` CHECK (`featured` IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jyb_job_texts` (
  `job_id` BIGINT UNSIGNED NOT NULL,
  `locale` VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `slug` VARCHAR(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `title` VARCHAR(191) NOT NULL,
  `summary` VARCHAR(1000) DEFAULT NULL,
  `description` MEDIUMTEXT NOT NULL,
  `location_text` VARCHAR(191) DEFAULT NULL,
  `translation_status` VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft',
  `updated_at_utc` DATETIME NOT NULL,
  PRIMARY KEY (`job_id`, `locale`),
  UNIQUE KEY `jyb_job_locale_slug_uq` (`locale`, `slug`),
  KEY `jyb_job_text_public` (`locale`, `translation_status`, `title`),
  CONSTRAINT `jyb_job_text_job_fk` FOREIGN KEY (`job_id`) REFERENCES `jyb_jobs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jyb_job_text_status_ck` CHECK (`translation_status` IN ('draft','published'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jyb_job_categories` (
  `job_id` BIGINT UNSIGNED NOT NULL,
  `category_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`job_id`, `category_id`),
  KEY `jyb_job_category_category` (`category_id`, `job_id`),
  CONSTRAINT `jyb_job_category_job_fk` FOREIGN KEY (`job_id`) REFERENCES `jyb_jobs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jyb_job_category_category_fk` FOREIGN KEY (`category_id`) REFERENCES `jyb_categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jyb_audit` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_id` BIGINT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(80) NOT NULL,
  `entity_type` VARCHAR(40) NOT NULL,
  `entity_id` BIGINT UNSIGNED DEFAULT NULL,
  `before_json` JSON DEFAULT NULL,
  `after_json` JSON DEFAULT NULL,
  `request_id` CHAR(32) DEFAULT NULL,
  `created_at_utc` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jyb_audit_created` (`created_at_utc`, `id`),
  KEY `jyb_audit_entity` (`entity_type`, `entity_id`, `id`),
  KEY `jyb_audit_actor` (`actor_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
