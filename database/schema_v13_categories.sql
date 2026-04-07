-- schema_v13_categories.sql
-- PR #4: 3-Level Hierarchical Category System
-- Adds icon, level, commission_rate, updated_at to existing categories table.
-- Uses INFORMATION_SCHEMA dynamic SQL to skip columns that already exist (MySQL 8.0 compatible).

-- Add `icon` column
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'categories'
      AND COLUMN_NAME  = 'icon'
);
SET @sql = IF(
    @col_exists = 0,
    'ALTER TABLE `categories` ADD COLUMN `icon` VARCHAR(100) DEFAULT NULL AFTER `description`',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add `level` column
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'categories'
      AND COLUMN_NAME  = 'level'
);
SET @sql = IF(
    @col_exists = 0,
    'ALTER TABLE `categories` ADD COLUMN `level` TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `sort_order`',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add `commission_rate` column
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'categories'
      AND COLUMN_NAME  = 'commission_rate'
);
SET @sql = IF(
    @col_exists = 0,
    'ALTER TABLE `categories` ADD COLUMN `commission_rate` DECIMAL(5,2) DEFAULT NULL AFTER `level`',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add `updated_at` column
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'categories'
      AND COLUMN_NAME  = 'updated_at'
);
SET @sql = IF(
    @col_exists = 0,
    'ALTER TABLE `categories` ADD COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `is_active`',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Update level for existing rows based on parent_id depth
UPDATE categories SET level = 1 WHERE parent_id IS NULL;
UPDATE categories c
  JOIN categories p ON c.parent_id = p.id
  SET c.level = p.level + 1
WHERE c.parent_id IS NOT NULL;
