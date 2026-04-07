-- GlobexSky Database Schema v15 — Commission Engine (PR #8)
-- Adds full commission_logs (spec columns), commission_tier_config,
-- and ensures category_commission_rates has all needed columns.
-- Run after schema_v14_orders.sql

SET NAMES utf8mb4;

-- -----------------------------------------------------------
-- Commission Logs — full spec columns
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS commission_logs (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id            INT UNSIGNED NOT NULL,
    supplier_id         INT UNSIGNED NOT NULL,
    order_subtotal      DECIMAL(12,2) NOT NULL DEFAULT 0,
    gmv_tier            VARCHAR(50)   NOT NULL DEFAULT '',
    -- Rate columns store fractions (0.0000-0.9999, e.g. 0.1200 = 12.00%)
    base_rate           DECIMAL(6,4)  NOT NULL DEFAULT 0,
    category_rate       DECIMAL(6,4)  NOT NULL DEFAULT 0,
    plan_discount       DECIMAL(6,4)  NOT NULL DEFAULT 0,
    final_rate          DECIMAL(6,4)  NOT NULL DEFAULT 0,
    commission_amount   DECIMAL(12,2) NOT NULL DEFAULT 0,
    net_amount          DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_supplier  (supplier_id),
    INDEX idx_order     (order_id),
    INDEX idx_created   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add new columns to commission_logs if the table was created by schema_v3.sql
-- (uses INFORMATION_SCHEMA + PREPARE for MySQL 8.0 compatible idempotency)

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_logs' AND COLUMN_NAME = 'order_subtotal');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE commission_logs ADD COLUMN order_subtotal DECIMAL(12,2) NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_logs' AND COLUMN_NAME = 'gmv_tier');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE commission_logs ADD COLUMN gmv_tier VARCHAR(50) NOT NULL DEFAULT \'\'',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_logs' AND COLUMN_NAME = 'base_rate');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE commission_logs ADD COLUMN base_rate DECIMAL(6,4) NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_logs' AND COLUMN_NAME = 'category_rate');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE commission_logs ADD COLUMN category_rate DECIMAL(6,4) NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_logs' AND COLUMN_NAME = 'plan_discount');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE commission_logs ADD COLUMN plan_discount DECIMAL(6,4) NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_logs' AND COLUMN_NAME = 'final_rate');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE commission_logs ADD COLUMN final_rate DECIMAL(6,4) NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_logs' AND COLUMN_NAME = 'net_amount');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE commission_logs ADD COLUMN net_amount DECIMAL(12,2) NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------
-- Commission Tier Config -- 90-day GMV based tiers
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS commission_tier_config (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tier_name   VARCHAR(50)   NOT NULL,
    min_gmv     DECIMAL(14,2) NOT NULL DEFAULT 0,
    max_gmv     DECIMAL(14,2)          DEFAULT NULL COMMENT 'NULL = no upper bound',
    base_rate   DECIMAL(6,4)  NOT NULL DEFAULT 0 COMMENT 'Fraction, e.g. 0.12 = 12%',
    is_active   TINYINT(1)    NOT NULL DEFAULT 1,
    sort_order  INT           NOT NULL DEFAULT 0,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tier_name (tier_name),
    INDEX idx_min_gmv (min_gmv)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default tier config (Starter/Growth/Scale/Enterprise -- 90-day GMV)
INSERT IGNORE INTO commission_tier_config (tier_name, min_gmv, max_gmv, base_rate, sort_order) VALUES
    ('Starter',    0,         9999.99,  0.12, 1),
    ('Growth',     10000,    49999.99,  0.10, 2),
    ('Scale',      50000,   199999.99,  0.08, 3),
    ('Enterprise', 200000,       NULL,  0.06, 4);

-- -----------------------------------------------------------
-- Category Commission Rates -- ensure table exists with
-- all columns the engine expects
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS category_commission_rates (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id   INT UNSIGNED NOT NULL,
    override_rate DECIMAL(6,4) NOT NULL DEFAULT 0 COMMENT 'Fraction, e.g. 0.08 = 8%',
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add override_rate column to existing table if it only has the old "rate" column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'category_commission_rates' AND COLUMN_NAME = 'override_rate');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE category_commission_rates ADD COLUMN override_rate DECIMAL(6,4) NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
