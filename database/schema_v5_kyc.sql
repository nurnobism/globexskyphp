-- GlobexSky Database Schema v5 KYC
-- KYC System + Advanced Admin
-- Run after schema.sql, schema_v2.sql, schema_v3.sql, schema_v4.sql, schema_v5.sql

SET NAMES utf8mb4;

-- -----------------------------------------------------
-- Table `kyc_submissions`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `kyc_submissions` (
    `id`                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`            INT UNSIGNED NOT NULL,
    `business_name`      VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci,
    `business_type`      ENUM('individual','company') NOT NULL,
    `registration_number` VARCHAR(100) NULL COLLATE utf8mb4_unicode_ci,
    `tax_id`             VARCHAR(100) NULL COLLATE utf8mb4_unicode_ci,
    `country`            VARCHAR(100) NOT NULL COLLATE utf8mb4_unicode_ci,
    `address`            TEXT NOT NULL COLLATE utf8mb4_unicode_ci,
    `city`               VARCHAR(100) NOT NULL COLLATE utf8mb4_unicode_ci,
    `state`              VARCHAR(100) NOT NULL COLLATE utf8mb4_unicode_ci,
    `postal_code`        VARCHAR(20) NOT NULL COLLATE utf8mb4_unicode_ci,
    `status`             ENUM('pending','under_review','approved','rejected','expired') NOT NULL DEFAULT 'pending',
    `submitted_at`       DATETIME NOT NULL,
    `reviewed_at`        DATETIME NULL,
    `reviewed_by`        INT UNSIGNED NULL,
    `rejection_reason`   TEXT NULL COLLATE utf8mb4_unicode_ci,
    `expires_at`         DATETIME NULL,
    `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_status`  (`status`),
    CONSTRAINT `fk_kycsub_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table `kyc_documents`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `kyc_documents` (
    `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `kyc_submission_id`   INT UNSIGNED NOT NULL,
    `document_type`       ENUM('national_id','passport','business_license','tax_certificate','bank_statement','utility_bill','other') NOT NULL,
    `file_path`           VARCHAR(500) NOT NULL COLLATE utf8mb4_unicode_ci,
    `file_name`           VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci,
    `file_size`           INT UNSIGNED NOT NULL,
    `mime_type`           VARCHAR(100) NOT NULL COLLATE utf8mb4_unicode_ci,
    `status`              ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    `rejection_reason`    TEXT NULL COLLATE utf8mb4_unicode_ci,
    `verified_at`         DATETIME NULL,
    `verified_by`         INT UNSIGNED NULL,
    `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_kycdoc_submission` FOREIGN KEY (`kyc_submission_id`) REFERENCES `kyc_submissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table `kyc_audit_log`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `kyc_audit_log` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `kyc_submission_id` INT UNSIGNED NULL,
    `action`            ENUM('submitted','approved','rejected','resubmitted','expired','document_uploaded','document_verified','document_rejected') NOT NULL,
    `performed_by`      INT UNSIGNED NULL,
    `ip_address`        VARCHAR(45) NULL COLLATE utf8mb4_unicode_ci,
    `user_agent`        VARCHAR(500) NULL COLLATE utf8mb4_unicode_ci,
    `details`           JSON NULL,
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_submission` (`kyc_submission_id`),
    INDEX `idx_created`    (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table `admin_audit_log`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_audit_log` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `admin_id`    INT UNSIGNED NOT NULL,
    `action`      VARCHAR(100) NOT NULL COLLATE utf8mb4_unicode_ci,
    `target_type` ENUM('user','kyc','order','product','setting') NULL,
    `target_id`   INT UNSIGNED NULL,
    `details`     JSON NULL,
    `ip_address`  VARCHAR(45) NULL COLLATE utf8mb4_unicode_ci,
    `user_agent`  VARCHAR(500) NULL COLLATE utf8mb4_unicode_ci,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_admin`   (`admin_id`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table `system_settings`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key`   VARCHAR(100) NOT NULL COLLATE utf8mb4_unicode_ci,
    `setting_value` TEXT NULL COLLATE utf8mb4_unicode_ci,
    `setting_group` VARCHAR(50) NOT NULL DEFAULT 'general' COLLATE utf8mb4_unicode_ci,
    `description`   VARCHAR(255) NULL COLLATE utf8mb4_unicode_ci,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updated_by`    INT UNSIGNED NULL,
    UNIQUE KEY `unique_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table `admin_permissions`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_permissions` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `role`           VARCHAR(50) NOT NULL COLLATE utf8mb4_unicode_ci,
    `permission_key` VARCHAR(100) NOT NULL COLLATE utf8mb4_unicode_ci,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_role_perm` (`role`, `permission_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- ALTER TABLE users: add KYC and admin_role columns
-- Uses dynamic SQL to skip if columns already exist
-- -----------------------------------------------------

SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'users'
      AND COLUMN_NAME  = 'kyc_status'
);
SET @sql = IF(
    @col_exists = 0,
    "ALTER TABLE `users` ADD COLUMN `kyc_status` ENUM('none','pending','approved','rejected','expired') NOT NULL DEFAULT 'none' AFTER `role`",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'users'
      AND COLUMN_NAME  = 'kyc_verified_at'
);
SET @sql = IF(
    @col_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `kyc_verified_at` DATETIME NULL AFTER `kyc_status`',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'users'
      AND COLUMN_NAME  = 'admin_role'
);
SET @sql = IF(
    @col_exists = 0,
    "ALTER TABLE `users` ADD COLUMN `admin_role` ENUM('super_admin','admin','moderator','support') NULL AFTER `kyc_verified_at`",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- Default system_settings
-- -----------------------------------------------------
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`, `description`) VALUES
    ('site_name',                   'GlobexSky',  'general',  'The public name of the site'),
    ('maintenance_mode',            '0',          'general',  'Set to 1 to enable maintenance mode'),
    ('registration_enabled',        '1',          'general',  'Set to 0 to disable new user registrations'),
    ('kyc_required_for_sellers',    '1',          'kyc',      'Require KYC verification before a user can sell'),
    ('auto_approve_threshold',      '0',          'kyc',      'Auto-approve KYC submissions at or below this risk score (lower = safer; 0 = disabled, never auto-approve)');

-- -----------------------------------------------------
-- Default admin_permissions
-- -----------------------------------------------------
INSERT IGNORE INTO `admin_permissions` (`role`, `permission_key`) VALUES
    -- super_admin
    ('super_admin', 'manage_users'),
    ('super_admin', 'review_kyc'),
    ('super_admin', 'manage_orders'),
    ('super_admin', 'manage_products'),
    ('super_admin', 'manage_settings'),
    ('super_admin', 'view_audit_log'),
    ('super_admin', 'manage_finance'),
    -- admin
    ('admin', 'manage_users'),
    ('admin', 'review_kyc'),
    ('admin', 'manage_orders'),
    ('admin', 'manage_products'),
    ('admin', 'view_audit_log'),
    -- moderator
    ('moderator', 'review_kyc'),
    ('moderator', 'view_audit_log'),
    -- support
    ('support', 'view_audit_log');
