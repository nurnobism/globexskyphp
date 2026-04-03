SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS currencies (
    code VARCHAR(3) NOT NULL PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    symbol VARCHAR(10) NOT NULL,
    exchange_rate DECIMAL(15,6) DEFAULT 1.000000,
    is_active TINYINT(1) DEFAULT 1,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exchange_rate_cache (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    base_currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    rates_json TEXT,
    fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_base (base_currency),
    INDEX idx_fetched (fetched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO currencies (code, name, symbol, exchange_rate) VALUES
('USD', 'US Dollar', '$', 1.000000),
('EUR', 'Euro', '€', 0.920000),
('GBP', 'British Pound', '£', 0.790000),
('CNY', 'Chinese Yuan', '¥', 7.240000),
('JPY', 'Japanese Yen', '¥', 149.500000),
('KRW', 'South Korean Won', '₩', 1320.000000),
('INR', 'Indian Rupee', '₹', 83.200000),
('BDT', 'Bangladeshi Taka', '৳', 110.000000),
('AED', 'UAE Dirham', 'د.إ', 3.670000),
('SAR', 'Saudi Riyal', '﷼', 3.750000),
('TRY', 'Turkish Lira', '₺', 32.000000),
('BRL', 'Brazilian Real', 'R$', 4.970000),
('RUB', 'Russian Ruble', '₽', 90.000000),
('THB', 'Thai Baht', '฿', 35.200000),
('VND', 'Vietnamese Dong', '₫', 24500.000000),
('IDR', 'Indonesian Rupiah', 'Rp', 15700.000000),
('MYR', 'Malaysian Ringgit', 'RM', 4.720000),
('PHP', 'Philippine Peso', '₱', 56.500000),
('SGD', 'Singapore Dollar', 'S$', 1.340000),
('AUD', 'Australian Dollar', 'A$', 1.530000),
('CAD', 'Canadian Dollar', 'C$', 1.360000),
('CHF', 'Swiss Franc', 'CHF', 0.900000),
('SEK', 'Swedish Krona', 'kr', 10.500000),
('NOK', 'Norwegian Krone', 'kr', 10.700000),
('DKK', 'Danish Krone', 'kr', 6.880000),
('PLN', 'Polish Zloty', 'zł', 4.030000),
('CZK', 'Czech Koruna', 'Kč', 22.800000),
('HUF', 'Hungarian Forint', 'Ft', 357.000000),
('ZAR', 'South African Rand', 'R', 18.700000),
('NGN', 'Nigerian Naira', '₦', 1550.000000);

SET FOREIGN_KEY_CHECKS = 1;
