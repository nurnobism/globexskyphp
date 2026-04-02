-- GlobexSky Database Schema v2
-- Additional tables to support new features
-- Run after schema.sql

-- ============================================================
-- Carry Service
-- (Table names match api/carry.php conventions)
-- ============================================================

CREATE TABLE IF NOT EXISTS carriers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    full_name       VARCHAR(150) NOT NULL,
    passport_number VARCHAR(50)  NOT NULL,
    id_document     VARCHAR(255),
    phone           VARCHAR(30),
    nationality     VARCHAR(80),
    travel_frequency ENUM('weekly','biweekly','monthly','occasional') DEFAULT 'occasional',
    bio             TEXT,
    status          ENUM('pending','active','suspended','rejected') DEFAULT 'pending',
    rating          DECIMAL(3,2) DEFAULT 0.00,
    total_trips     INT UNSIGNED DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_carriers_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS carry_trips (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    from_city       VARCHAR(100) NOT NULL,
    to_city         VARCHAR(100) NOT NULL,
    flight_date     DATE         NOT NULL,
    available_weight DECIMAL(6,2) NOT NULL,
    rate_per_kg     DECIMAL(10,2) DEFAULT 0.00,
    item_types      VARCHAR(255),
    ticket_path     VARCHAR(255),
    status          ENUM('active','completed','cancelled') DEFAULT 'active',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_carry_trips_user (user_id),
    INDEX idx_carry_trips_status (status),
    INDEX idx_carry_trips_date (flight_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS carry_deliveries (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trip_id         INT UNSIGNED,
    carrier_id      INT UNSIGNED,
    sender_id       INT UNSIGNED NOT NULL,
    item_description TEXT,
    weight          DECIMAL(6,2) NOT NULL,
    agreed_rate     DECIMAL(10,2),
    total_amount    DECIMAL(10,2),
    status          ENUM('pending','accepted','in_transit','delivered','cancelled') DEFAULT 'pending',
    completed_at    TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_carry_deliveries_carrier (carrier_id),
    INDEX idx_carry_deliveries_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS carrier_earnings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    trip_id         INT UNSIGNED,
    delivery_id     INT UNSIGNED,
    earning         DECIMAL(10,2) NOT NULL,
    description     VARCHAR(255),
    paid            TINYINT(1) DEFAULT 0,
    paid_at         TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_carrier_earnings_user (user_id),
    INDEX idx_carrier_earnings_paid (paid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Parcel Service
-- (Table names match api/parcels.php conventions)
-- Note: 'addresses' table already exists in schema.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS parcels (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             INT UNSIGNED NOT NULL,
    sender_address_id   INT UNSIGNED,
    receiver_address_id INT UNSIGNED,
    weight              DECIMAL(8,2) NOT NULL,
    length              DECIMAL(8,2) DEFAULT 0,
    width               DECIMAL(8,2) DEFAULT 0,
    height              DECIMAL(8,2) DEFAULT 0,
    contents            TEXT,
    speed               ENUM('standard','express','priority') DEFAULT 'standard',
    insured             TINYINT(1) DEFAULT 0,
    tracking_number     VARCHAR(30) UNIQUE NOT NULL,
    status              ENUM('pending','processing','in_transit','delivered','cancelled') DEFAULT 'pending',
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_parcels_user (user_id),
    INDEX idx_parcels_tracking (tracking_number),
    INDEX idx_parcels_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS parcel_events (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parcel_id       INT UNSIGNED NOT NULL,
    event_type      VARCHAR(80)  NOT NULL,
    description     TEXT,
    location        VARCHAR(150),
    status          VARCHAR(50),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_parcel_events_parcel (parcel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Pricing Management
-- ============================================================

CREATE TABLE IF NOT EXISTS pricing_rules (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    category        ENUM('commission','supplier_plan','inspection','dropship_markup','carry','api_platform','flash_sale','advertising') NOT NULL,
    value_type      ENUM('percentage','fixed') DEFAULT 'percentage',
    value           DECIMAL(12,4) NOT NULL DEFAULT 0,
    description     TEXT,
    metadata        JSON,
    min_order_value DECIMAL(12,2),
    max_order_value DECIMAL(12,2),
    sort_order      INT DEFAULT 0,
    is_active       TINYINT(1)   DEFAULT 1,
    created_by      INT UNSIGNED,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pricing_rules_category (category),
    INDEX idx_pricing_rules_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pricing_history (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rule_id         INT UNSIGNED,
    rule_name       VARCHAR(150),
    category        VARCHAR(50),
    old_value       VARCHAR(255),
    new_value       VARCHAR(255),
    changed_by      INT UNSIGNED,
    changed_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pricing_history_rule (rule_id),
    INDEX idx_pricing_history_date (changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- AI Management
-- ============================================================

CREATE TABLE IF NOT EXISTS ai_models (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    provider        ENUM('openai','deepseek','anthropic','google','custom') DEFAULT 'openai',
    model_id        VARCHAR(100) NOT NULL,
    api_key         VARCHAR(255),
    max_tokens      INT UNSIGNED DEFAULT 4096,
    cost_per_token  DECIMAL(12,8) DEFAULT 0.00002,
    description     TEXT,
    is_active       TINYINT(1)   DEFAULT 1,
    is_default      TINYINT(1)   DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ai_models_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_training_data (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    model_id        INT UNSIGNED,
    data_type       ENUM('qa','classification','completion','instruction') DEFAULT 'qa',
    input_text      TEXT NOT NULL,
    output_text     TEXT NOT NULL,
    status          ENUM('pending','approved','rejected','training') DEFAULT 'pending',
    added_by        INT UNSIGNED,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ai_training_status (status),
    INDEX idx_ai_training_model (model_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_usage_logs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED,
    model_id        INT UNSIGNED,
    feature         VARCHAR(80),
    prompt_tokens   INT UNSIGNED DEFAULT 0,
    completion_tokens INT UNSIGNED DEFAULT 0,
    tokens_used     INT UNSIGNED DEFAULT 0,
    cost            DECIMAL(12,8) DEFAULT 0,
    status          ENUM('success','error','timeout') DEFAULT 'success',
    error_message   TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ai_logs_user (user_id),
    INDEX idx_ai_logs_date (created_at),
    INDEX idx_ai_logs_model (model_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Trade Finance
-- ============================================================

CREATE TABLE IF NOT EXISTS trade_finance_applications (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    applicant_id        INT UNSIGNED NOT NULL,
    finance_type        ENUM('net_30','net_60','net_90','lc','trade_credit','invoice_financing') NOT NULL,
    requested_amount    DECIMAL(15,2) NOT NULL,
    approved_amount     DECIMAL(15,2),
    business_name       VARCHAR(200),
    business_type       ENUM('sole_proprietor','partnership','llc','corporation','other') DEFAULT 'llc',
    years_in_business   INT UNSIGNED DEFAULT 0,
    annual_revenue      DECIMAL(15,2),
    purpose             TEXT,
    additional_info     TEXT,
    admin_notes         TEXT,
    status              ENUM('pending','under_review','approved','rejected') DEFAULT 'pending',
    reviewed_by         INT UNSIGNED,
    reviewed_at         TIMESTAMP NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tf_apps_applicant (applicant_id),
    INDEX idx_tf_apps_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS letters_of_credit (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    applicant_id        INT UNSIGNED NOT NULL,
    lc_number           VARCHAR(50) UNIQUE,
    lc_type             ENUM('irrevocable','revocable','standby','transferable') DEFAULT 'irrevocable',
    amount              DECIMAL(15,2) NOT NULL,
    currency            VARCHAR(10) DEFAULT 'USD',
    expiry_date         DATE NOT NULL,
    beneficiary_name    VARCHAR(200) NOT NULL,
    beneficiary_bank    VARCHAR(200),
    goods_description   TEXT NOT NULL,
    special_terms       TEXT,
    issuing_bank        VARCHAR(200),
    status              ENUM('draft','submitted','approved','rejected','expired','paid') DEFAULT 'submitted',
    approved_by         INT UNSIGNED,
    approved_at         TIMESTAMP NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_lc_applicant (applicant_id),
    INDEX idx_lc_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS trade_insurance (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             INT UNSIGNED NOT NULL,
    policy_number       VARCHAR(50),
    insurance_type      ENUM('cargo','credit','political_risk','product_liability') NOT NULL,
    coverage_amount     DECIMAL(15,2) NOT NULL,
    premium_amount      DECIMAL(12,2),
    origin_country      VARCHAR(80),
    destination_country VARCHAR(80),
    description         TEXT,
    start_date          DATE,
    end_date            DATE,
    status              ENUM('quote_requested','quoted','active','expired','claimed') DEFAULT 'quote_requested',
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_trade_insurance_user (user_id),
    INDEX idx_trade_insurance_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Extended Platform Settings
-- ============================================================

CREATE TABLE IF NOT EXISTS platform_settings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key     VARCHAR(100) NOT NULL UNIQUE,
    setting_value   TEXT,
    setting_group   VARCHAR(50) DEFAULT 'general',
    description     VARCHAR(255),
    is_public       TINYINT(1) DEFAULT 0,
    updated_by      INT UNSIGNED,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_platform_settings_group (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default platform settings
INSERT IGNORE INTO platform_settings (setting_key, setting_value, setting_group, description) VALUES
('mail_host',              'smtp.mailtrap.io',    'mail',        'SMTP host'),
('mail_port',              '587',                 'mail',        'SMTP port'),
('mail_encryption',        'tls',                 'mail',        'SMTP encryption'),
('mail_username',          '',                    'mail',        'SMTP username'),
('mail_from_name',         'GlobexSky',           'mail',        'From name'),
('mail_from_address',      'no-reply@globexsky.com', 'mail',     'From email'),
('seo_site_title',         'GlobexSky — Global B2B Trade Platform', 'seo', 'Site title'),
('seo_robots_index',       '1',                   'seo',         'Allow indexing'),
('security_session_lifetime', '120',              'security',    'Session lifetime in minutes'),
('security_max_login_attempts', '5',              'security',    'Max failed login attempts'),
('security_lockout_duration', '30',               'security',    'Lockout duration in minutes'),
('security_min_password_length', '8',             'security',    'Minimum password length'),
('security_rate_limit_enabled', '1',              'security',    'Enable API rate limiting'),
('security_api_rate_limit', '60',                 'security',    'API requests per minute'),
('maintenance_enabled',    '0',                   'maintenance', 'Maintenance mode enabled'),
('maintenance_message',    'We are performing scheduled maintenance. We will be back shortly!', 'maintenance', 'Maintenance message'),
('shipping_standard_rate', '8.50',                'shipping',    'Standard rate per kg USD'),
('shipping_express_mult',  '1.6',                 'shipping',    'Express multiplier'),
('shipping_priority_mult', '2.2',                 'shipping',    'Priority multiplier'),
('shipping_currency',      'USD',                 'shipping',    'Default currency'),
('tax_enabled',            '0',                   'tax',         'Tax calculation enabled'),
('tax_default_rate',       '0',                   'tax',         'Default tax rate percentage'),
('tax_label',              'VAT',                 'tax',         'Tax label'),
('tax_inclusive',          '0',                   'tax',         'Tax inclusive pricing'),
('ai_chatbot_enabled',     '1',                   'ai',          'AI chatbot enabled'),
('ai_recommendations_enabled', '1',              'ai',          'AI recommendations enabled'),
('ai_search_enabled',      '1',                   'ai',          'AI search enabled'),
('ai_fraud_detection_enabled', '1',              'ai',          'AI fraud detection enabled'),
('ai_insights_enabled',    '1',                   'ai',          'AI insights enabled'),
('ai_translation_enabled', '0',                   'ai',          'AI translation enabled'),
('ai_default_provider',    'deepseek',            'ai',          'Default AI provider'),
('ai_max_tokens',          '2048',                'ai',          'Max tokens per request'),
('ai_daily_limit',         '100',                 'ai',          'Daily request limit per user'),
('ai_monthly_budget',      '500',                 'ai',          'Monthly AI budget in USD');

-- ============================================================
-- Translations (optional DB-backed i18n)
-- ============================================================

CREATE TABLE IF NOT EXISTS translations (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    locale          VARCHAR(10)  NOT NULL,
    translation_key VARCHAR(200) NOT NULL,
    translation     TEXT         NOT NULL,
    namespace       VARCHAR(80)  DEFAULT 'general',
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_translation (locale, translation_key),
    INDEX idx_translations_locale (locale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Returns & Refunds
-- ============================================================

CREATE TABLE IF NOT EXISTS return_requests (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    order_id        INT UNSIGNED NOT NULL,
    reason_code     VARCHAR(50)  NOT NULL DEFAULT 'other',
    reason          TEXT         NOT NULL,
    resolution_type ENUM('refund','replacement','partial_refund') NOT NULL DEFAULT 'refund',
    evidence_url    VARCHAR(500),
    status          ENUM('pending','approved','rejected','shipped','refunded','cancelled') NOT NULL DEFAULT 'pending',
    refund_amount   DECIMAL(12,2),
    admin_notes     TEXT,
    reviewed_at     DATETIME,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_user_id  (user_id),
    INDEX idx_order_id (order_id),
    INDEX idx_status   (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Barcode Scan History
-- ============================================================

CREATE TABLE IF NOT EXISTS scan_history (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    barcode     VARCHAR(100) NOT NULL,
    product_id  INT UNSIGNED,
    scanned_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_user_id  (user_id),
    INDEX idx_barcode  (barcode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Trade Shows
-- ============================================================

CREATE TABLE IF NOT EXISTS trade_shows (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                    VARCHAR(200) NOT NULL,
    description             TEXT,
    location                VARCHAR(300),
    start_date              DATE NOT NULL,
    end_date                DATE NOT NULL,
    registration_deadline   DATE,
    status                  ENUM('draft','open','closed','cancelled') NOT NULL DEFAULT 'draft',
    banner_image            VARCHAR(500),
    website_url             VARCHAR(500),
    max_booths              INT UNSIGNED,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_start_date (start_date),
    INDEX idx_status     (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trade_show_booths (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    show_id     INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    booth_number VARCHAR(20),
    notes       TEXT,
    status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (show_id)     REFERENCES trade_shows(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)   ON DELETE CASCADE,
    UNIQUE KEY uq_show_supplier (show_id, supplier_id),
    INDEX idx_show_id     (show_id),
    INDEX idx_supplier_id (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
