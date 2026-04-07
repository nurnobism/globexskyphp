-- ============================================================
-- schema_v17_shipping.sql — Shipping Calculator Schema (PR #14)
-- ============================================================
-- Creates:
--   shipping_zones           — Named zones (Domestic, EU, etc.)
--   shipping_methods         — Rates/methods per zone
--   shipping_templates       — Supplier shipping profiles
--   shipping_template_rates  — Per-zone rates within a template
--   product_shipping         — Product-level shipping overrides
-- Also seeds system_settings with shipping config keys and
-- populates default zones with standard/express methods.
-- ============================================================

-- -----------------------------------------------------------
-- shipping_zones
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS shipping_zones (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    countries_json TEXT         NOT NULL DEFAULT '[]' COMMENT 'JSON array of ISO country codes',
    states_json   TEXT         NOT NULL DEFAULT '[]' COMMENT 'JSON array of "CC-ST" codes',
    is_default    TINYINT(1)   NOT NULL DEFAULT 0,
    sort_order    SMALLINT     NOT NULL DEFAULT 0,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_is_default (is_default),
    INDEX idx_is_active  (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- shipping_methods — Rate methods attached to a zone
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS shipping_methods (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    zone_id             INT UNSIGNED NOT NULL,
    name                VARCHAR(100) NOT NULL,
    type                ENUM('flat_rate','weight_based','price_based','free') NOT NULL DEFAULT 'flat_rate',
    base_cost           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    per_kg_cost         DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
    per_item_cost       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    free_above_amount   DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '0 = not applicable',
    estimated_days_min  TINYINT UNSIGNED NOT NULL DEFAULT 1,
    estimated_days_max  TINYINT UNSIGNED NOT NULL DEFAULT 7,
    is_active           TINYINT(1)    NOT NULL DEFAULT 1,
    sort_order          SMALLINT      NOT NULL DEFAULT 0,
    created_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_zone_id   (zone_id),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- shipping_templates — Supplier shipping profiles
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS shipping_templates (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id       INT UNSIGNED NOT NULL,
    name              VARCHAR(100) NOT NULL,
    handling_time_days TINYINT UNSIGNED NOT NULL DEFAULT 1,
    is_default        TINYINT(1)   NOT NULL DEFAULT 0,
    created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_supplier_id (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- shipping_template_rates — Per-zone rates within a template
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS shipping_template_rates (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id INT UNSIGNED NOT NULL,
    zone_id     INT UNSIGNED NOT NULL,
    method_name VARCHAR(100) NOT NULL DEFAULT 'Standard',
    cost        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    free_above  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    INDEX idx_template_id (template_id),
    INDEX idx_zone_id     (zone_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- product_shipping — Product-level shipping overrides
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS product_shipping (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id  INT UNSIGNED NOT NULL UNIQUE,
    template_id INT UNSIGNED DEFAULT NULL,
    weight_kg   DECIMAL(8,3) NOT NULL DEFAULT 0.000,
    length_cm   DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    width_cm    DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    height_cm   DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_template_id (template_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- system_settings — Shipping config keys
-- -----------------------------------------------------------
INSERT INTO system_settings (setting_key, setting_value, setting_group, description) VALUES
    ('shipping_free_threshold',    '0',   'shipping', 'Global free-shipping cart total threshold (0=disabled)'),
    ('shipping_default_handling',  '1',   'shipping', 'Default supplier handling time in days'),
    ('shipping_weight_unit',       'kg',  'shipping', 'Weight unit: kg or lb'),
    ('shipping_dimension_unit',    'cm',  'shipping', 'Dimension unit: cm or in'),
    ('shipping_show_estimate',     '1',   'shipping', 'Show shipping estimate on product page (1=yes)')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- -----------------------------------------------------------
-- Default zones + seed methods
-- -----------------------------------------------------------

-- Zone 1: Domestic (US)
INSERT INTO shipping_zones (id, name, countries_json, states_json, is_default, sort_order, is_active) VALUES
    (1, 'Domestic',      '["US"]',                           '[]', 1, 1, 1),
    (2, 'North America', '["US","CA","MX"]',                 '[]', 0, 2, 1),
    (3, 'Europe',        '["DE","FR","GB","IT","ES","NL","BE","AT","SE","NO","DK","FI","PL","PT","IE","CH","CZ","HU","RO","SK","BG","HR","LT","LV","EE","SI","LU","MT","CY"]','[]',0,3,1),
    (4, 'Asia',          '["CN","JP","KR","IN","SG","MY","TH","PH","ID","VN","HK","TW","BD","PK","LK","NP","MM","KH","LA","BN"]','[]',0,4,1),
    (5, 'Rest of World', '[]',                               '[]', 0, 5, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Methods for Zone 1 (Domestic)
INSERT INTO shipping_methods (zone_id, name, type, base_cost, per_kg_cost, free_above_amount, estimated_days_min, estimated_days_max, is_active, sort_order) VALUES
    (1, 'Economy',   'flat_rate',     4.99, 0.00, 0.00, 7, 14, 1, 1),
    (1, 'Standard',  'flat_rate',     7.99, 0.00, 0.00, 5,  7, 1, 2),
    (1, 'Express',   'weight_based', 12.99, 1.50, 0.00, 2,  3, 1, 3),
    (1, 'Free',      'free',          0.00, 0.00, 75.00, 7, 10, 1, 4);

-- Methods for Zone 2 (North America)
INSERT INTO shipping_methods (zone_id, name, type, base_cost, per_kg_cost, free_above_amount, estimated_days_min, estimated_days_max, is_active, sort_order) VALUES
    (2, 'Standard',  'flat_rate',    12.99, 0.00, 0.00,  7, 14, 1, 1),
    (2, 'Express',   'weight_based', 24.99, 2.00, 0.00,  3,  5, 1, 2),
    (2, 'Free',      'free',          0.00, 0.00, 150.00, 10, 14, 1, 3);

-- Methods for Zone 3 (Europe)
INSERT INTO shipping_methods (zone_id, name, type, base_cost, per_kg_cost, free_above_amount, estimated_days_min, estimated_days_max, is_active, sort_order) VALUES
    (3, 'Standard',  'flat_rate',    15.99, 0.00, 0.00, 10, 21, 1, 1),
    (3, 'Express',   'weight_based', 34.99, 3.00, 0.00,  5,  7, 1, 2);

-- Methods for Zone 4 (Asia)
INSERT INTO shipping_methods (zone_id, name, type, base_cost, per_kg_cost, free_above_amount, estimated_days_min, estimated_days_max, is_active, sort_order) VALUES
    (4, 'Standard',  'flat_rate',    18.99, 0.00, 0.00, 14, 30, 1, 1),
    (4, 'Express',   'weight_based', 39.99, 3.50, 0.00,  7, 14, 1, 2);

-- Methods for Zone 5 (Rest of World)
INSERT INTO shipping_methods (zone_id, name, type, base_cost, per_kg_cost, free_above_amount, estimated_days_min, estimated_days_max, is_active, sort_order) VALUES
    (5, 'Standard',  'flat_rate',    24.99, 0.00, 0.00, 21, 45, 1, 1),
    (5, 'Express',   'weight_based', 49.99, 4.00, 0.00, 10, 21, 1, 2);
