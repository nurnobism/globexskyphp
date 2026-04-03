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

-- ===========================================================
-- Phase 4: Shipment System
-- ===========================================================


-- Parcel shipments
CREATE TABLE IF NOT EXISTS parcel_shipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_id INT,
    tracking_number VARCHAR(50) UNIQUE NOT NULL,
    status ENUM('pending','processing','picked_up','in_transit','out_for_delivery','delivered','failed','cancelled','returned') DEFAULT 'pending',
    sender_name VARCHAR(200) NOT NULL,
    sender_phone VARCHAR(20),
    sender_address TEXT NOT NULL,
    sender_city VARCHAR(100),
    sender_country VARCHAR(2),
    receiver_name VARCHAR(200) NOT NULL,
    receiver_phone VARCHAR(20),
    receiver_address TEXT NOT NULL,
    receiver_city VARCHAR(100),
    receiver_country VARCHAR(2),
    package_type ENUM('document','package','heavy') DEFAULT 'package',
    weight DECIMAL(8,2),
    length DECIMAL(8,2),
    width DECIMAL(8,2),
    height DECIMAL(8,2),
    declared_value DECIMAL(12,2),
    shipping_method ENUM('standard','express','priority','economy') DEFAULT 'standard',
    shipping_cost DECIMAL(12,2) NOT NULL,
    insurance_cost DECIMAL(12,2) DEFAULT 0,
    has_insurance TINYINT(1) DEFAULT 0,
    special_instructions TEXT,
    estimated_delivery DATE,
    actual_delivery DATETIME,
    carrier_name VARCHAR(100),
    payment_status ENUM('pending','paid','refunded') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ps_user (user_id),
    INDEX idx_ps_order (order_id),
    INDEX idx_ps_tracking (tracking_number),
    INDEX idx_ps_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Parcel tracking events
CREATE TABLE IF NOT EXISTS parcel_tracking_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT NOT NULL,
    status VARCHAR(100) NOT NULL,
    location VARCHAR(200),
    description TEXT,
    event_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pte_shipment (shipment_id),
    FOREIGN KEY (shipment_id) REFERENCES parcel_shipments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User addresses
CREATE TABLE IF NOT EXISTS user_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    label VARCHAR(50) DEFAULT 'Home',
    full_name VARCHAR(200) NOT NULL,
    phone VARCHAR(20),
    address_line1 VARCHAR(500) NOT NULL,
    address_line2 VARCHAR(500),
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100),
    postal_code VARCHAR(20),
    country_code VARCHAR(2) NOT NULL,
    country_name VARCHAR(100),
    is_default_shipping TINYINT(1) DEFAULT 0,
    is_default_billing TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ua_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Carrier trips (phase 4 extended version)
CREATE TABLE IF NOT EXISTS carrier_trips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carrier_id INT NOT NULL,
    departure_city VARCHAR(100) NOT NULL,
    departure_country VARCHAR(2) NOT NULL,
    departure_country_name VARCHAR(100),
    arrival_city VARCHAR(100) NOT NULL,
    arrival_country VARCHAR(2) NOT NULL,
    arrival_country_name VARCHAR(100),
    travel_date DATE NOT NULL,
    return_date DATE,
    available_capacity_kg DECIMAL(8,2) NOT NULL,
    remaining_capacity_kg DECIMAL(8,2),
    price_per_kg DECIMAL(10,2) NOT NULL,
    transport_mode ENUM('flight','bus','train','car','ship') DEFAULT 'flight',
    notes TEXT,
    status ENUM('active','matched','completed','cancelled','expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ct_carrier (carrier_id),
    INDEX idx_ct_route (departure_country, arrival_country),
    INDEX idx_ct_date (travel_date),
    INDEX idx_ct_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Carry requests
CREATE TABLE IF NOT EXISTS carry_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    category ENUM('document','electronics','clothing','food','medicine','other') DEFAULT 'other',
    weight_kg DECIMAL(8,2) NOT NULL,
    from_city VARCHAR(100) NOT NULL,
    from_country VARCHAR(2) NOT NULL,
    from_country_name VARCHAR(100),
    to_city VARCHAR(100) NOT NULL,
    to_country VARCHAR(2) NOT NULL,
    to_country_name VARCHAR(100),
    preferred_date_from DATE,
    preferred_date_to DATE,
    budget DECIMAL(10,2),
    currency VARCHAR(3) DEFAULT 'USD',
    special_handling TEXT,
    status ENUM('open','matched','in_progress','delivered','completed','cancelled','disputed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cr_sender (sender_id),
    INDEX idx_cr_route (from_country, to_country),
    INDEX idx_cr_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Carry matches
CREATE TABLE IF NOT EXISTS carry_matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    trip_id INT NOT NULL,
    carrier_id INT NOT NULL,
    sender_id INT NOT NULL,
    agreed_price DECIMAL(10,2) NOT NULL,
    platform_commission DECIMAL(10,2) NOT NULL,
    carrier_earning DECIMAL(10,2) NOT NULL,
    status ENUM('pending','accepted','pickup_scheduled','picked_up','in_transit','delivered','completed','cancelled','disputed') DEFAULT 'pending',
    pickup_time DATETIME,
    pickup_location TEXT,
    delivery_time DATETIME,
    delivery_location TEXT,
    proof_of_delivery_url VARCHAR(500),
    sender_rating DECIMAL(3,2),
    sender_review TEXT,
    carrier_rating DECIMAL(3,2),
    carrier_review TEXT,
    notes TEXT,
    completed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cm_request (request_id),
    INDEX idx_cm_carrier (carrier_id),
    INDEX idx_cm_sender (sender_id),
    INDEX idx_cm_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Carrier earnings log (phase 4 extended)
CREATE TABLE IF NOT EXISTS carrier_earning_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carrier_id INT NOT NULL,
    match_id INT,
    type ENUM('delivery_earning','commission_deduct','payout','bonus','refund') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    balance_after DECIMAL(12,2) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cel_carrier (carrier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Shipping rates configuration
CREATE TABLE IF NOT EXISTS shipping_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    origin_country VARCHAR(2) NOT NULL,
    destination_country VARCHAR(2) NOT NULL,
    base_rate DECIMAL(10,2) NOT NULL,
    rate_per_kg DECIMAL(10,2) NOT NULL,
    rate_per_cm3 DECIMAL(10,4) DEFAULT 0,
    method_multiplier_standard DECIMAL(4,2) DEFAULT 1.00,
    method_multiplier_express DECIMAL(4,2) DEFAULT 2.00,
    method_multiplier_priority DECIMAL(4,2) DEFAULT 3.00,
    method_multiplier_economy DECIMAL(4,2) DEFAULT 0.70,
    insurance_rate DECIMAL(5,2) DEFAULT 2.00,
    estimated_days_standard VARCHAR(20) DEFAULT '5-10',
    estimated_days_express VARCHAR(20) DEFAULT '2-5',
    estimated_days_priority VARCHAR(20) DEFAULT '1-3',
    estimated_days_economy VARCHAR(20) DEFAULT '10-20',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_sr_route (origin_country, destination_country)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Carry service settings
CREATE TABLE IF NOT EXISTS carry_service_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed shipping rates (common routes) - ignore if already exists
INSERT IGNORE INTO shipping_rates (origin_country, destination_country, base_rate, rate_per_kg) VALUES
('CN', 'US', 5.00, 3.50),
('CN', 'GB', 5.50, 3.80),
('CN', 'BD', 3.00, 2.00),
('CN', 'DE', 5.50, 3.80),
('CN', 'JP', 4.00, 2.50),
('CN', 'AE', 4.50, 3.00),
('US', 'GB', 6.00, 4.00),
('US', 'CN', 5.00, 3.50),
('US', 'BD', 7.00, 5.00),
('BD', 'US', 7.00, 5.00),
('BD', 'GB', 6.50, 4.50),
('BD', 'CN', 3.00, 2.00),
('BD', 'AE', 4.00, 3.00);

-- Seed carry service settings - ignore if already exists
INSERT IGNORE INTO carry_service_settings (setting_key, setting_value, description) VALUES
('platform_commission_percent', '15', 'Platform commission on carry service (%)'),
('min_price_per_kg', '5', 'Minimum price per kg for carry service'),
('max_price_per_kg', '100', 'Maximum price per kg for carry service'),
('min_carrier_rating', '3.0', 'Minimum rating to remain active carrier'),
('max_weight_per_request', '30', 'Maximum weight per carry request (kg)'),
('auto_expire_trips_days', '1', 'Auto expire trips after travel date + N days'),
('min_payout_amount', '20', 'Minimum carrier payout amount ($)');
