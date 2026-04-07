-- GlobexSky Database Schema v17 — Address Management (PR #17)
-- Extends user_addresses table with full address management fields.
-- Run after schema_v16_coupons.sql

SET NAMES utf8mb4;

-- -----------------------------------------------------------
-- Extend user_addresses table with all required fields
-- -----------------------------------------------------------

-- full_name
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_addresses' AND COLUMN_NAME = 'full_name');
SET @sql = IF(@col = 0,
    'ALTER TABLE user_addresses ADD COLUMN full_name VARCHAR(200) DEFAULT NULL AFTER label',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- address_line_1 (alias for address_line1 — keep existing column, add if missing)
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_addresses' AND COLUMN_NAME = 'address_line_1');
SET @sql = IF(@col = 0,
    'ALTER TABLE user_addresses ADD COLUMN address_line_1 VARCHAR(255) DEFAULT NULL AFTER full_name',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- address_line_2
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_addresses' AND COLUMN_NAME = 'address_line_2');
SET @sql = IF(@col = 0,
    'ALTER TABLE user_addresses ADD COLUMN address_line_2 VARCHAR(255) DEFAULT NULL AFTER address_line_1',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- state_province
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_addresses' AND COLUMN_NAME = 'state_province');
SET @sql = IF(@col = 0,
    'ALTER TABLE user_addresses ADD COLUMN state_province VARCHAR(150) DEFAULT NULL AFTER city',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- state_code
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_addresses' AND COLUMN_NAME = 'state_code');
SET @sql = IF(@col = 0,
    'ALTER TABLE user_addresses ADD COLUMN state_code VARCHAR(10) DEFAULT NULL AFTER state_province',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- country_code
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_addresses' AND COLUMN_NAME = 'country_code');
SET @sql = IF(@col = 0,
    'ALTER TABLE user_addresses ADD COLUMN country_code CHAR(2) NOT NULL DEFAULT ''US'' AFTER postal_code',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- country_name
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_addresses' AND COLUMN_NAME = 'country_name');
SET @sql = IF(@col = 0,
    'ALTER TABLE user_addresses ADD COLUMN country_name VARCHAR(100) DEFAULT NULL AFTER country_code',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- latitude
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_addresses' AND COLUMN_NAME = 'latitude');
SET @sql = IF(@col = 0,
    'ALTER TABLE user_addresses ADD COLUMN latitude DECIMAL(10,7) DEFAULT NULL AFTER country_name',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- longitude
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_addresses' AND COLUMN_NAME = 'longitude');
SET @sql = IF(@col = 0,
    'ALTER TABLE user_addresses ADD COLUMN longitude DECIMAL(10,7) DEFAULT NULL AFTER latitude',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- is_default_shipping
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_addresses' AND COLUMN_NAME = 'is_default_shipping');
SET @sql = IF(@col = 0,
    'ALTER TABLE user_addresses ADD COLUMN is_default_shipping TINYINT(1) NOT NULL DEFAULT 0 AFTER longitude',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- is_default_billing
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_addresses' AND COLUMN_NAME = 'is_default_billing');
SET @sql = IF(@col = 0,
    'ALTER TABLE user_addresses ADD COLUMN is_default_billing TINYINT(1) NOT NULL DEFAULT 0 AFTER is_default_shipping',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- is_active
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_addresses' AND COLUMN_NAME = 'is_active');
SET @sql = IF(@col = 0,
    'ALTER TABLE user_addresses ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER is_default_billing',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- deleted_at (soft delete)
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_addresses' AND COLUMN_NAME = 'deleted_at');
SET @sql = IF(@col = 0,
    'ALTER TABLE user_addresses ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER updated_at',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add indexes for performance
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_addresses' AND INDEX_NAME = 'idx_default_shipping');
SET @sql = IF(@idx = 0,
    'ALTER TABLE user_addresses ADD INDEX idx_default_shipping (user_id, is_default_shipping)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_addresses' AND INDEX_NAME = 'idx_default_billing');
SET @sql = IF(@idx = 0,
    'ALTER TABLE user_addresses ADD INDEX idx_default_billing (user_id, is_default_billing)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_addresses' AND INDEX_NAME = 'idx_country_code');
SET @sql = IF(@idx = 0,
    'ALTER TABLE user_addresses ADD INDEX idx_country_code (country_code)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
