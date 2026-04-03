-- GlobexSky Database Schema v3
-- Commission Engine + Supplier Plans + Pricing Enforcement + Payout System
-- Run after schema.sql and schema_v2.sql

SET NAMES utf8mb4;

-- -----------------------------------------------------------
-- Commission Tiers
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS commission_tiers (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    min_monthly_sales   DECIMAL(12,2) NOT NULL,
    max_monthly_sales   DECIMAL(12,2),
    rate                DECIMAL(5,2)  NOT NULL,
    tier_name           VARCHAR(50),
    is_active           TINYINT(1) DEFAULT 1,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_min (min_monthly_sales)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Category Commission Rate Overrides
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS category_commission_rates (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    rate        DECIMAL(5,2) NOT NULL,
    is_active   TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Commission Logs
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS commission_logs (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    order_id                INT NOT NULL,
    supplier_id             INT NOT NULL,
    order_amount            DECIMAL(12,2) NOT NULL,
    commission_rate         DECIMAL(5,2)  NOT NULL,
    commission_amount       DECIMAL(12,2) NOT NULL,
    tier                    VARCHAR(50),
    category_rate_applied   TINYINT(1) DEFAULT 0,
    plan_discount_applied   DECIMAL(5,2) DEFAULT 0,
    details                 JSON,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_supplier  (supplier_id),
    INDEX idx_order     (order_id),
    INDEX idx_created   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Supplier Plans
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS supplier_plans (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(100) NOT NULL,
    slug                VARCHAR(100) UNIQUE NOT NULL,
    price               DECIMAL(10,2) NOT NULL DEFAULT 0,
    currency            VARCHAR(3) DEFAULT 'USD',
    billing_period      ENUM('monthly','yearly') DEFAULT 'monthly',
    features            JSON,
    limits              JSON,
    commission_discount DECIMAL(5,2) DEFAULT 0,
    stripe_price_id     VARCHAR(255),
    is_active           TINYINT(1) DEFAULT 1,
    sort_order          INT DEFAULT 0,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Plan Subscriptions
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS plan_subscriptions (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id             INT NOT NULL,
    plan_id                 INT NOT NULL,
    stripe_subscription_id  VARCHAR(255),
    status                  ENUM('active','past_due','cancelled','trialing') DEFAULT 'active',
    current_period_start    DATETIME,
    current_period_end      DATETIME,
    cancel_at_period_end    TINYINT(1) DEFAULT 0,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_supplier (supplier_id),
    INDEX idx_status   (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Supplier Earnings / Balance Ledger
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS supplier_earnings (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id   INT NOT NULL,
    order_id      INT,
    type          ENUM('sale','commission_deduct','payout','refund','adjustment') NOT NULL,
    amount        DECIMAL(12,2) NOT NULL,
    balance_after DECIMAL(12,2) NOT NULL,
    description   TEXT,
    reference_id  VARCHAR(255),
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_supplier (supplier_id),
    INDEX idx_type     (type),
    INDEX idx_created  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Payout Requests
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS payout_requests (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id      INT NOT NULL,
    amount           DECIMAL(12,2) NOT NULL,
    payout_method    ENUM('bank_transfer','paypal','wise') NOT NULL,
    payout_details   JSON,
    status           ENUM('pending','processing','completed','rejected') DEFAULT 'pending',
    admin_note       TEXT,
    reference_number VARCHAR(255),
    processed_at     DATETIME,
    processed_by     INT,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_supplier (supplier_id),
    INDEX idx_status   (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Tax Rates
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS tax_rates (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    country_code VARCHAR(2)   NOT NULL,
    country_name VARCHAR(100),
    rate         DECIMAL(5,2) NOT NULL,
    tax_type     ENUM('vat','gst','sales_tax','none') DEFAULT 'vat',
    is_active    TINYINT(1) DEFAULT 1,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_country (country_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Invoices
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS invoices (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    supplier_id    INT NOT NULL,
    type           ENUM('commission','plan_subscription','payout') NOT NULL,
    amount         DECIMAL(12,2) NOT NULL,
    tax_amount     DECIMAL(12,2) DEFAULT 0,
    total          DECIMAL(12,2) NOT NULL,
    status         ENUM('draft','sent','paid','overdue','cancelled') DEFAULT 'draft',
    due_date       DATE,
    paid_at        DATETIME,
    details        JSON,
    pdf_url        VARCHAR(500),
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_supplier (supplier_id),
    INDEX idx_status   (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Seed: Supplier Plans (skip if already exist)
-- -----------------------------------------------------------
INSERT IGNORE INTO supplier_plans (name, slug, price, billing_period, features, limits, commission_discount, sort_order) VALUES
('Free', 'free', 0.00, 'monthly',
 '{"support":"community","analytics":"basic","badge":"none"}',
 '{"products":10,"images_per_product":3,"featured_per_month":0,"livestream_per_week":0,"dropshipping":false,"api_access":false}',
 0.00, 1),
('Pro', 'pro', 299.00, 'monthly',
 '{"support":"email","analytics":"advanced","badge":"pro","custom_store":true}',
 '{"products":500,"images_per_product":10,"featured_per_month":2,"livestream_per_week":2,"dropshipping":true,"api_access":"basic"}',
 15.00, 2),
('Enterprise', 'enterprise', 999.00, 'monthly',
 '{"support":"phone_email","analytics":"full_ai","badge":"enterprise","custom_store":true,"custom_domain":true}',
 '{"products":-1,"images_per_product":20,"featured_per_month":-1,"livestream_per_week":-1,"dropshipping":true,"api_access":"full"}',
 30.00, 3);

-- -----------------------------------------------------------
-- Seed: Commission Tiers (skip if already exist)
-- -----------------------------------------------------------
INSERT IGNORE INTO commission_tiers (min_monthly_sales, max_monthly_sales, rate, tier_name) VALUES
(0, 1000.00, 12.00, 'Starter'),
(1000.01, 10000.00, 10.00, 'Growth'),
(10000.01, 50000.00, 8.00, 'Scale'),
(50000.01, NULL, 6.00, 'Enterprise');

-- -----------------------------------------------------------
-- Seed: Tax Rates (skip if already exist)
-- -----------------------------------------------------------
INSERT IGNORE INTO tax_rates (country_code, country_name, rate, tax_type) VALUES
('US', 'United States',  0.00, 'sales_tax'),
('GB', 'United Kingdom', 20.00, 'vat'),
('DE', 'Germany',        19.00, 'vat'),
('FR', 'France',         20.00, 'vat'),
('CN', 'China',          13.00, 'vat'),
('BD', 'Bangladesh',     15.00, 'vat'),
('IN', 'India',          18.00, 'gst'),
('JP', 'Japan',          10.00, 'vat'),
('AE', 'UAE',             5.00, 'vat'),
('SA', 'Saudi Arabia',   15.00, 'vat');
