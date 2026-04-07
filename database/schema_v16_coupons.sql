-- GlobexSky Database Schema v16 — Coupon & Promotion System (PR #13)
-- Creates coupons, coupon_usages, promotions tables
-- and extends orders table with coupon fields.
-- Run after schema_v15_plans.sql

SET NAMES utf8mb4;

-- -----------------------------------------------------------
-- coupons — Full coupon definitions
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS coupons (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code                        VARCHAR(50)   NOT NULL,
    type                        ENUM('percentage','fixed','free_shipping','bxgy') NOT NULL DEFAULT 'percentage',
    value                       DECIMAL(10,4) NOT NULL DEFAULT 0,
    min_order_amount            DECIMAL(10,2) NOT NULL DEFAULT 0,
    max_discount_amount         DECIMAL(10,2) DEFAULT NULL,
    usage_limit                 INT UNSIGNED  DEFAULT NULL COMMENT 'NULL = unlimited',
    per_user_limit              INT UNSIGNED  NOT NULL DEFAULT 1,
    usage_count                 INT UNSIGNED  NOT NULL DEFAULT 0,
    valid_from                  DATETIME      DEFAULT NULL,
    valid_to                    DATETIME      DEFAULT NULL,
    is_active                   TINYINT(1)    NOT NULL DEFAULT 1,
    applicable_categories_json  JSON          DEFAULT NULL,
    applicable_products_json    JSON          DEFAULT NULL,
    applicable_suppliers_json   JSON          DEFAULT NULL,
    created_by                  INT UNSIGNED  NOT NULL DEFAULT 0,
    creator_role                ENUM('admin','supplier') NOT NULL DEFAULT 'admin',
    description                 TEXT          DEFAULT NULL,
    buy_x                       TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT 'For bxgy type',
    get_y                       TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'For bxgy type',
    created_at                  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at                  DATETIME      DEFAULT NULL,
    UNIQUE KEY uq_code          (code),
    INDEX idx_type              (type),
    INDEX idx_is_active         (is_active),
    INDEX idx_valid_range       (valid_from, valid_to),
    INDEX idx_created_by        (created_by),
    INDEX idx_deleted_at        (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- coupon_usages — Track every coupon redemption
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS coupon_usages (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    coupon_id       INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NOT NULL,
    order_id        INT UNSIGNED NOT NULL DEFAULT 0,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    used_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_coupon_id (coupon_id),
    INDEX idx_user_id   (user_id),
    INDEX idx_order_id  (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- promotions — Time-limited flash sale / promotion events
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS promotions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    slug            VARCHAR(255) NOT NULL DEFAULT '',
    description     TEXT DEFAULT NULL,
    discount_type   ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
    discount_value  DECIMAL(10,4) NOT NULL DEFAULT 0,
    start_date      DATETIME NOT NULL,
    end_date        DATETIME NOT NULL,
    banner_image    VARCHAR(500) DEFAULT NULL,
    products_json   JSON DEFAULT NULL,
    categories_json JSON DEFAULT NULL,
    is_featured     TINYINT(1) NOT NULL DEFAULT 0,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    views_count     INT UNSIGNED NOT NULL DEFAULT 0,
    created_by      INT UNSIGNED NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_slug  (slug),
    INDEX idx_dates     (start_date, end_date),
    INDEX idx_featured  (is_featured),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Extend orders table with coupon fields (idempotent)
-- -----------------------------------------------------------
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'coupon_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE orders ADD COLUMN coupon_id INT UNSIGNED DEFAULT NULL AFTER tax_amount',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'coupon_code');
SET @sql = IF(@col = 0,
    'ALTER TABLE orders ADD COLUMN coupon_code VARCHAR(50) DEFAULT NULL AFTER coupon_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'discount_amount');
SET @sql = IF(@col = 0,
    'ALTER TABLE orders ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER coupon_code',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------
-- Seed: sample coupons
-- -----------------------------------------------------------
INSERT IGNORE INTO coupons
    (code, type, value, min_order_amount, max_discount_amount, usage_limit, per_user_limit,
     valid_from, valid_to, is_active, created_by, creator_role, description)
VALUES
    ('WELCOME10', 'percentage', 10.0000, 20.00,  50.00, 1000, 1, NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), 1, 1, 'admin', '10% off for new customers'),
    ('SAVE20',    'percentage', 20.0000, 50.00, 100.00, 500,  1, NOW(), DATE_ADD(NOW(), INTERVAL 6 MONTH), 1, 1, 'admin', '20% off orders over $50'),
    ('FREESHIP',  'free_shipping', 0.0000, 30.00, NULL,  NULL, 2, NOW(), DATE_ADD(NOW(), INTERVAL 3 MONTH), 1, 1, 'admin', 'Free shipping on orders $30+'),
    ('FLAT15',    'fixed', 15.0000, 40.00, NULL,  300,  2, NOW(), DATE_ADD(NOW(), INTERVAL 4 MONTH), 1, 1, 'admin', '$15 off orders over $40'),
    ('BIGSAVE30', 'percentage', 30.0000, 100.00, 75.00, 200, 1, NOW(), DATE_ADD(NOW(), INTERVAL 2 MONTH), 1, 1, 'admin', '30% off orders over $100 (max $75)');

-- -----------------------------------------------------------
-- Seed: sample promotion
-- -----------------------------------------------------------
INSERT IGNORE INTO promotions
    (name, slug, description, discount_type, discount_value, start_date, end_date, is_featured, is_active, created_by)
VALUES
    ('Summer Sale 2026', 'summer-sale-2026', 'Big summer savings across all categories', 'percentage', 15.0000,
     NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 1, 1, 1),
    ('Flash Friday', 'flash-friday', 'Every Friday — massive discounts on select products', 'percentage', 25.0000,
     NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 0, 1, 1);
