-- ============================================================
-- schema_v15_plans.sql — Supplier Plans Extended Schema (PR #9)
-- ============================================================
-- Adds plan_features, plan_invoices tables and extends
-- plan_subscriptions with billing_period + amount columns.
-- Uses INT UNSIGNED for all IDs to match schema conventions.
-- ============================================================

-- -----------------------------------------------------------
-- plan_features — Per-plan feature rows for comparison table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS plan_features (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_id       INT UNSIGNED NOT NULL,
    feature_key   VARCHAR(100) NOT NULL,
    feature_value VARCHAR(255) NOT NULL DEFAULT '',
    feature_label VARCHAR(255) NOT NULL DEFAULT '',
    sort_order    INT UNSIGNED DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_plan_id (plan_id),
    INDEX idx_feature_key (feature_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- plan_invoices — Per-invoice records for billing history
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS plan_invoices (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscription_id     INT UNSIGNED NOT NULL DEFAULT 0,
    supplier_id         INT UNSIGNED NOT NULL,
    amount              DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    currency            VARCHAR(3) NOT NULL DEFAULT 'USD',
    status              ENUM('paid','pending','failed','refunded') NOT NULL DEFAULT 'pending',
    billing_period      ENUM('monthly','quarterly','semi_annual','annual') NOT NULL DEFAULT 'monthly',
    stripe_invoice_id   VARCHAR(255) DEFAULT NULL,
    pdf_url             VARCHAR(500) DEFAULT NULL,
    description         VARCHAR(500) DEFAULT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_supplier_id (supplier_id),
    INDEX idx_subscription_id (subscription_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Extend plan_subscriptions with billing_period + amount
-- Uses a stored procedure for compatibility with MySQL < 8.0.29
-- -----------------------------------------------------------
DROP PROCEDURE IF EXISTS _add_plans_columns;
DELIMITER //
CREATE PROCEDURE _add_plans_columns()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'plan_subscriptions'
          AND COLUMN_NAME  = 'billing_period'
    ) THEN
        ALTER TABLE plan_subscriptions
            ADD COLUMN billing_period ENUM('monthly','quarterly','semi_annual','annual')
            NOT NULL DEFAULT 'monthly' AFTER plan_id;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'plan_subscriptions'
          AND COLUMN_NAME  = 'amount'
    ) THEN
        ALTER TABLE plan_subscriptions
            ADD COLUMN amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER billing_period;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'plan_subscriptions'
          AND COLUMN_NAME  = 'next_billing_date'
    ) THEN
        ALTER TABLE plan_subscriptions
            ADD COLUMN next_billing_date DATE DEFAULT NULL AFTER cancel_at_period_end;
    END IF;
END //
DELIMITER ;
CALL _add_plans_columns();
DROP PROCEDURE IF EXISTS _add_plans_columns;

-- -----------------------------------------------------------
-- Seed: plan_features rows for Free / Pro / Enterprise
-- -----------------------------------------------------------
INSERT IGNORE INTO plan_features (plan_id, feature_key, feature_value, feature_label, sort_order)
SELECT sp.id, f.feature_key, f.feature_value, f.feature_label, f.sort_order
FROM supplier_plans sp
JOIN (
    -- Free plan features
    SELECT 'free' AS slug, 'max_products'           AS feature_key, '10'             AS feature_value, 'Products'                AS feature_label, 1  AS sort_order UNION ALL
    SELECT 'free', 'max_images_per_product',  '3',              'Images / product',        2  UNION ALL
    SELECT 'free', 'max_dropship_products',   '0',              'Dropship products',       3  UNION ALL
    SELECT 'free', 'can_livestream',          '0',              'Livestream',              4  UNION ALL
    SELECT 'free', 'can_api',                 '0',              'API access',              5  UNION ALL
    SELECT 'free', 'commission_discount',     '0',              'Commission discount',     6  UNION ALL
    SELECT 'free', 'support_level',           'Community',      'Support',                 7  UNION ALL
    -- Pro plan features
    SELECT 'pro',  'max_products',            '500',            'Products',                1  UNION ALL
    SELECT 'pro',  'max_images_per_product',  '10',             'Images / product',        2  UNION ALL
    SELECT 'pro',  'max_dropship_products',   '100',            'Dropship products',       3  UNION ALL
    SELECT 'pro',  'can_livestream',          '1',              'Livestream',              4  UNION ALL
    SELECT 'pro',  'can_api',                 'basic',          'API access',              5  UNION ALL
    SELECT 'pro',  'commission_discount',     '15',             'Commission discount',     6  UNION ALL
    SELECT 'pro',  'support_level',           'Priority email', 'Support',                 7  UNION ALL
    -- Enterprise plan features
    SELECT 'enterprise', 'max_products',           '-1',            'Products',            1  UNION ALL
    SELECT 'enterprise', 'max_images_per_product', '20',            'Images / product',    2  UNION ALL
    SELECT 'enterprise', 'max_dropship_products',  '-1',            'Dropship products',   3  UNION ALL
    SELECT 'enterprise', 'can_livestream',         '1',             'Livestream',          4  UNION ALL
    SELECT 'enterprise', 'can_api',                'full',          'API access',          5  UNION ALL
    SELECT 'enterprise', 'commission_discount',    '30',            'Commission discount', 6  UNION ALL
    SELECT 'enterprise', 'support_level',          'Dedicated manager', 'Support',         7
) f ON sp.slug = f.slug;
