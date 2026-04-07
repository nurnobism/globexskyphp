-- ============================================================
-- schema_v16_tax.sql — Tax Calculation Engine Schema (PR #12)
-- ============================================================
-- Creates:
--   tax_rates         — Per-country/state configurable tax rates
--   tax_exemptions    — Per-user tax exemption records
--   order_tax_details — Per-order tax audit trail
-- Also seeds system_settings with tax config keys and
-- populates tax_rates with 30+ standard country rates.
-- ============================================================

-- -----------------------------------------------------------
-- tax_rates — Country / state-level tax rate configuration
-- Drop and recreate to upgrade from the v3 schema which
-- lacked state_code / state_name / tax_name columns.
-- -----------------------------------------------------------
DROP TABLE IF EXISTS tax_rates;
CREATE TABLE tax_rates (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    country_code VARCHAR(2)  NOT NULL,
    country_name VARCHAR(100) NOT NULL DEFAULT '',
    state_code   VARCHAR(10)  NOT NULL DEFAULT '',
    state_name   VARCHAR(100) NOT NULL DEFAULT '',
    rate         DECIMAL(6,4) NOT NULL DEFAULT 0.0000 COMMENT 'Rate as percentage, e.g. 20.00 = 20%',
    tax_name     VARCHAR(100) NOT NULL DEFAULT 'Tax',
    is_active    TINYINT(1)   NOT NULL DEFAULT 1,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_country_state (country_code, state_code),
    INDEX idx_country_code (country_code),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- tax_exemptions — Per-user tax exemption grants
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS tax_exemptions (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id            INT UNSIGNED NOT NULL,
    exemption_type     ENUM('full','partial','b2b','non_profit','government') NOT NULL DEFAULT 'full',
    certificate_number VARCHAR(100) NOT NULL DEFAULT '',
    expiry_date        DATE         DEFAULT NULL,
    granted_by         INT UNSIGNED NOT NULL DEFAULT 0,
    is_active          TINYINT(1)   NOT NULL DEFAULT 1,
    created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- order_tax_details — Per-order tax audit trail
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_tax_details (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id         INT UNSIGNED NOT NULL,
    tax_mode         ENUM('fixed','per_country','vat') NOT NULL DEFAULT 'fixed',
    tax_rate         DECIMAL(6,4) NOT NULL DEFAULT 0.0000,
    taxable_amount   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_amount       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    country_code     VARCHAR(2)   NOT NULL DEFAULT '',
    state_code       VARCHAR(10)  NOT NULL DEFAULT '',
    vat_number       VARCHAR(50)  NOT NULL DEFAULT '',
    is_reverse_charge TINYINT(1)  NOT NULL DEFAULT 0,
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_order_id (order_id),
    INDEX idx_country_code (country_code),
    INDEX idx_tax_mode (tax_mode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Extend orders table with tax columns (idempotent)
-- -----------------------------------------------------------
DROP PROCEDURE IF EXISTS _add_tax_order_columns;
DELIMITER //
CREATE PROCEDURE _add_tax_order_columns()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'orders'
          AND COLUMN_NAME  = 'tax_amount'
    ) THEN
        ALTER TABLE orders
            ADD COLUMN tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER total,
            ADD COLUMN tax_rate   DECIMAL(6,4)  NOT NULL DEFAULT 0.0000 AFTER tax_amount,
            ADD COLUMN tax_mode   VARCHAR(20)   NOT NULL DEFAULT '' AFTER tax_rate;
    END IF;
END //
DELIMITER ;
CALL _add_tax_order_columns();
DROP PROCEDURE IF EXISTS _add_tax_order_columns;

-- -----------------------------------------------------------
-- Seed system_settings with tax configuration keys
-- -----------------------------------------------------------
INSERT INTO system_settings (setting_key, setting_value, description)
VALUES
    ('tax_mode',               'fixed',    'Tax calculation mode: fixed, per_country, vat'),
    ('tax_fixed_rate',         '10.00',    'Fixed tax rate percentage (used when tax_mode=fixed)'),
    ('tax_default_rate',       '10.00',    'Default/fallback tax rate percentage'),
    ('tax_inclusive',          '0',        'Whether prices already include tax (1=inclusive, 0=exclusive)'),
    ('show_tax_on_product',    '1',        'Show tax information on product detail page'),
    ('tax_label',              'Tax',      'Tax label shown to customers (Tax, VAT, GST, Sales Tax)'),
    ('vies_validation_enabled','0',        'Enable live VIES VAT number validation via EU API')
ON DUPLICATE KEY UPDATE
    description = VALUES(description);

-- -----------------------------------------------------------
-- Seed tax_rates with standard country rates (30+ countries)
-- -----------------------------------------------------------
INSERT INTO tax_rates (country_code, country_name, state_code, state_name, rate, tax_name, is_active)
VALUES
    ('US', 'United States',      '', '', 0.00,  'Sales Tax',   1),
    ('GB', 'United Kingdom',     '', '', 20.00, 'VAT',         1),
    ('DE', 'Germany',            '', '', 19.00, 'MwSt',        1),
    ('FR', 'France',             '', '', 20.00, 'TVA',         1),
    ('IT', 'Italy',              '', '', 22.00, 'IVA',         1),
    ('ES', 'Spain',              '', '', 21.00, 'IVA',         1),
    ('NL', 'Netherlands',        '', '', 21.00, 'BTW',         1),
    ('BE', 'Belgium',            '', '', 21.00, 'BTW/TVA',     1),
    ('AT', 'Austria',            '', '', 20.00, 'USt',         1),
    ('PL', 'Poland',             '', '', 23.00, 'VAT',         1),
    ('SE', 'Sweden',             '', '', 25.00, 'Moms',        1),
    ('DK', 'Denmark',            '', '', 25.00, 'Moms',        1),
    ('FI', 'Finland',            '', '', 25.50, 'ALV',         1),
    ('NO', 'Norway',             '', '', 25.00, 'MVA',         1),
    ('CH', 'Switzerland',        '', '', 8.10,  'MwSt/TVA',    1),
    ('PT', 'Portugal',           '', '', 23.00, 'IVA',         1),
    ('IE', 'Ireland',            '', '', 23.00, 'VAT',         1),
    ('CA', 'Canada',             '', '', 5.00,  'GST',         1),
    ('AU', 'Australia',          '', '', 10.00, 'GST',         1),
    ('NZ', 'New Zealand',        '', '', 15.00, 'GST',         1),
    ('JP', 'Japan',              '', '', 10.00, 'Consumption Tax', 1),
    ('CN', 'China',              '', '', 13.00, 'VAT',         1),
    ('KR', 'South Korea',        '', '', 10.00, 'VAT',         1),
    ('SG', 'Singapore',          '', '', 9.00,  'GST',         1),
    ('IN', 'India',              '', '', 18.00, 'GST',         1),
    ('BD', 'Bangladesh',         '', '', 15.00, 'VAT',         1),
    ('PK', 'Pakistan',           '', '', 17.00, 'GST',         1),
    ('AE', 'United Arab Emirates','','', 5.00,  'VAT',         1),
    ('SA', 'Saudi Arabia',       '', '', 15.00, 'VAT',         1),
    ('TR', 'Turkey',             '', '', 20.00, 'KDV',         1),
    ('BR', 'Brazil',             '', '', 17.00, 'ICMS',        1),
    ('MX', 'Mexico',             '', '', 16.00, 'IVA',         1),
    ('ZA', 'South Africa',       '', '', 15.00, 'VAT',         1),
    ('NG', 'Nigeria',            '', '', 7.50,  'VAT',         1),
    ('EG', 'Egypt',              '', '', 14.00, 'VAT',         1),
    ('RU', 'Russia',             '', '', 20.00, 'НДС',         1),
    ('MY', 'Malaysia',           '', '', 6.00,  'SST',         1),
    ('TH', 'Thailand',           '', '', 7.00,  'VAT',         1),
    ('PH', 'Philippines',        '', '', 12.00, 'VAT',         1),
    ('ID', 'Indonesia',          '', '', 11.00, 'PPN',         1),
    ('VN', 'Vietnam',            '', '', 10.00, 'VAT',         1)
ON DUPLICATE KEY UPDATE
    country_name = VALUES(country_name),
    rate         = VALUES(rate),
    tax_name     = VALUES(tax_name);

-- US State-level sales tax rates (common states)
INSERT INTO tax_rates (country_code, country_name, state_code, state_name, rate, tax_name, is_active)
VALUES
    ('US', 'United States', 'CA', 'California',   7.25, 'Sales Tax', 1),
    ('US', 'United States', 'TX', 'Texas',         6.25, 'Sales Tax', 1),
    ('US', 'United States', 'NY', 'New York',      4.00, 'Sales Tax', 1),
    ('US', 'United States', 'FL', 'Florida',       6.00, 'Sales Tax', 1),
    ('US', 'United States', 'WA', 'Washington',    6.50, 'Sales Tax', 1),
    ('US', 'United States', 'IL', 'Illinois',      6.25, 'Sales Tax', 1),
    ('US', 'United States', 'PA', 'Pennsylvania',  6.00, 'Sales Tax', 1),
    ('US', 'United States', 'OH', 'Ohio',          5.75, 'Sales Tax', 1),
    ('US', 'United States', 'GA', 'Georgia',       4.00, 'Sales Tax', 1),
    ('US', 'United States', 'NJ', 'New Jersey',    6.625,'Sales Tax', 1)
ON DUPLICATE KEY UPDATE
    rate = VALUES(rate),
    tax_name = VALUES(tax_name);

-- Canadian Province GST/HST/PST
INSERT INTO tax_rates (country_code, country_name, state_code, state_name, rate, tax_name, is_active)
VALUES
    ('CA', 'Canada', 'ON', 'Ontario',             13.00, 'HST', 1),
    ('CA', 'Canada', 'QC', 'Quebec',              14.975,'GST+QST', 1),
    ('CA', 'Canada', 'BC', 'British Columbia',    12.00, 'HST', 1),
    ('CA', 'Canada', 'AB', 'Alberta',              5.00, 'GST', 1),
    ('CA', 'Canada', 'NS', 'Nova Scotia',         15.00, 'HST', 1)
ON DUPLICATE KEY UPDATE
    rate = VALUES(rate),
    tax_name = VALUES(tax_name);
