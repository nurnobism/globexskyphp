-- schema_v15_plans.sql — Supplier Plans SaaS (PR #9)
--
-- Extends supplier_plans / plan_subscriptions tables from schema_v3.sql
-- and adds plan_invoices table. All ALTER TABLE statements are idempotent.

-- ── Extend supplier_plans with multi-duration pricing columns ──────────────
ALTER TABLE supplier_plans
    ADD COLUMN IF NOT EXISTS price_monthly     DECIMAL(10,2) NOT NULL DEFAULT 0   COMMENT 'Monthly price',
    ADD COLUMN IF NOT EXISTS price_quarterly   DECIMAL(10,2) NOT NULL DEFAULT 0   COMMENT 'Quarterly price (per month)',
    ADD COLUMN IF NOT EXISTS price_semi_annual DECIMAL(10,2) NOT NULL DEFAULT 0   COMMENT 'Semi-annual price (per month)',
    ADD COLUMN IF NOT EXISTS price_annual      DECIMAL(10,2) NOT NULL DEFAULT 0   COMMENT 'Annual price (per month)',
    ADD COLUMN IF NOT EXISTS stripe_price_id_quarterly    VARCHAR(255)            COMMENT 'Stripe Price ID for quarterly billing',
    ADD COLUMN IF NOT EXISTS stripe_price_id_semi_annual  VARCHAR(255)            COMMENT 'Stripe Price ID for semi-annual billing',
    ADD COLUMN IF NOT EXISTS stripe_price_id_annual       VARCHAR(255)            COMMENT 'Stripe Price ID for annual billing',
    ADD COLUMN IF NOT EXISTS max_products              INT NOT NULL DEFAULT 10    COMMENT 'Max active products (-1 = unlimited)',
    ADD COLUMN IF NOT EXISTS max_images_per_product    INT NOT NULL DEFAULT 3     COMMENT 'Max images per product',
    ADD COLUMN IF NOT EXISTS max_shipping_templates    INT NOT NULL DEFAULT 1     COMMENT 'Max shipping templates (-1 = unlimited)',
    ADD COLUMN IF NOT EXISTS max_dropship_imports      INT NOT NULL DEFAULT 0     COMMENT 'Max dropship imports (-1 = unlimited)',
    ADD COLUMN IF NOT EXISTS max_featured_listings     INT NOT NULL DEFAULT 0     COMMENT 'Max featured listings per month (-1 = unlimited)',
    ADD COLUMN IF NOT EXISTS max_livestreams           INT NOT NULL DEFAULT 0     COMMENT 'Max livestreams per week (-1 = unlimited)',
    ADD COLUMN IF NOT EXISTS features_json             JSON                       COMMENT 'Rich features JSON (replaces features column)';

-- ── Extend plan_subscriptions with duration + billing fields ──────────────
ALTER TABLE plan_subscriptions
    ADD COLUMN IF NOT EXISTS duration       ENUM('monthly','quarterly','semi-annual','annual') NOT NULL DEFAULT 'monthly',
    ADD COLUMN IF NOT EXISTS amount_paid    DECIMAL(10,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS stripe_customer_id VARCHAR(255),
    ADD COLUMN IF NOT EXISTS starts_at      DATETIME,
    ADD COLUMN IF NOT EXISTS ends_at        DATETIME,
    ADD COLUMN IF NOT EXISTS cancelled_at   DATETIME,
    ADD COLUMN IF NOT EXISTS next_plan_id   INT           COMMENT 'Scheduled downgrade target plan',
    ADD COLUMN IF NOT EXISTS grace_period_ends_at DATETIME COMMENT 'Grace period after payment failure';

-- ── Plan Invoices ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS plan_invoices (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    subscription_id  INT           NOT NULL,
    supplier_id      INT           NOT NULL,
    invoice_number   VARCHAR(50)   NOT NULL UNIQUE,
    amount           DECIMAL(10,2) NOT NULL DEFAULT 0,
    currency         VARCHAR(3)    NOT NULL DEFAULT 'USD',
    status           ENUM('paid','pending','failed','refunded') NOT NULL DEFAULT 'pending',
    stripe_invoice_id VARCHAR(255),
    description      VARCHAR(255),
    paid_at          DATETIME,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_supplier    (supplier_id),
    INDEX idx_subscription(subscription_id),
    INDEX idx_status      (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Seed / update plan data ────────────────────────────────────────────────
-- Free plan
INSERT INTO supplier_plans (name, slug, price, billing_period, commission_discount, sort_order,
    price_monthly, price_quarterly, price_semi_annual, price_annual,
    max_products, max_images_per_product, max_shipping_templates, max_dropship_imports,
    max_featured_listings, max_livestreams,
    features, features_json, limits, is_active)
VALUES ('Free', 'free', 0.00, 'monthly', 0.00, 1,
    0.00, 0.00, 0.00, 0.00,
    10, 3, 1, 0, 0, 0,
    '{"support":"community","analytics":"basic","badge":"none"}',
    '{"support":"community","analytics":"basic","badge":"none","api_access":false,"custom_store":false,"dedicated_manager":false}',
    '{"products":10,"images_per_product":3,"shipping_templates":1,"dropship_imports":0,"featured_per_month":0,"livestream_per_week":0,"dropshipping":false,"api_access":false}',
    1)
ON DUPLICATE KEY UPDATE
    price_monthly=0.00, price_quarterly=0.00, price_semi_annual=0.00, price_annual=0.00,
    max_products=10, max_images_per_product=3, max_shipping_templates=1,
    max_dropship_imports=0, max_featured_listings=0, max_livestreams=0,
    commission_discount=0.00, is_active=1,
    features_json='{"support":"community","analytics":"basic","badge":"none","api_access":false,"custom_store":false,"dedicated_manager":false}';

-- Pro plan
INSERT INTO supplier_plans (name, slug, price, billing_period, commission_discount, sort_order,
    price_monthly, price_quarterly, price_semi_annual, price_annual,
    max_products, max_images_per_product, max_shipping_templates, max_dropship_imports,
    max_featured_listings, max_livestreams,
    features, features_json, limits, is_active)
VALUES ('Pro', 'pro', 299.00, 'monthly', 15.00, 2,
    299.00, 269.10, 254.15, 224.25,
    500, 10, 5, 100, 2, 2,
    '{"support":"email","analytics":"advanced","badge":"pro","custom_store":true}',
    '{"support":"priority_email","analytics":"advanced","badge":"pro","api_access":"basic","custom_store":true,"dedicated_manager":false}',
    '{"products":500,"images_per_product":10,"shipping_templates":5,"dropship_imports":100,"featured_per_month":2,"livestream_per_week":2,"dropshipping":true,"api_access":"basic"}',
    1)
ON DUPLICATE KEY UPDATE
    price_monthly=299.00, price_quarterly=269.10, price_semi_annual=254.15, price_annual=224.25,
    max_products=500, max_images_per_product=10, max_shipping_templates=5,
    max_dropship_imports=100, max_featured_listings=2, max_livestreams=2,
    commission_discount=15.00, is_active=1,
    features_json='{"support":"priority_email","analytics":"advanced","badge":"pro","api_access":"basic","custom_store":true,"dedicated_manager":false}';

-- Enterprise plan
INSERT INTO supplier_plans (name, slug, price, billing_period, commission_discount, sort_order,
    price_monthly, price_quarterly, price_semi_annual, price_annual,
    max_products, max_images_per_product, max_shipping_templates, max_dropship_imports,
    max_featured_listings, max_livestreams,
    features, features_json, limits, is_active)
VALUES ('Enterprise', 'enterprise', 999.00, 'monthly', 30.00, 3,
    999.00, 899.10, 849.15, 749.25,
    -1, 20, -1, -1, -1, -1,
    '{"support":"phone_email","analytics":"full_ai","badge":"enterprise","custom_store":true,"custom_domain":true}',
    '{"support":"dedicated_phone_email","analytics":"full_ai","badge":"enterprise","api_access":"full","custom_store":true,"dedicated_manager":true,"custom_domain":true,"custom_integrations":true}',
    '{"products":-1,"images_per_product":20,"shipping_templates":-1,"dropship_imports":-1,"featured_per_month":-1,"livestream_per_week":-1,"dropshipping":true,"api_access":"full"}',
    1)
ON DUPLICATE KEY UPDATE
    price_monthly=999.00, price_quarterly=899.10, price_semi_annual=849.15, price_annual=749.25,
    max_products=-1, max_images_per_product=20, max_shipping_templates=-1,
    max_dropship_imports=-1, max_featured_listings=-1, max_livestreams=-1,
    commission_discount=30.00, is_active=1,
    features_json='{"support":"dedicated_phone_email","analytics":"full_ai","badge":"enterprise","api_access":"full","custom_store":true,"dedicated_manager":true,"custom_domain":true,"custom_integrations":true}';
