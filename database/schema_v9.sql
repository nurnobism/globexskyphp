SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- KYC level tracking per user
CREATE TABLE IF NOT EXISTS kyc_levels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    current_level TINYINT UNSIGNED DEFAULT 0,
    l1_verified_at TIMESTAMP NULL DEFAULT NULL,
    l2_verified_at TIMESTAMP NULL DEFAULT NULL,
    l3_verified_at TIMESTAMP NULL DEFAULT NULL,
    l4_verified_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_level (current_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- KYC document submissions
CREATE TABLE IF NOT EXISTS kyc_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    level TINYINT UNSIGNED NOT NULL,
    document_type VARCHAR(50) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    reviewer_id INT UNSIGNED DEFAULT NULL,
    review_notes TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_level (level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Platform feature toggles
CREATE TABLE IF NOT EXISTS platform_features (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    feature_name VARCHAR(100) NOT NULL UNIQUE,
    is_enabled TINYINT(1) DEFAULT 1,
    description VARCHAR(255),
    updated_by INT UNSIGNED DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO platform_features (feature_name, is_enabled, description) VALUES
('chatbot', 1, 'AI Chatbot widget'),
('livestream', 1, 'Live streaming feature'),
('dropshipping', 1, 'Dropshipping catalog'),
('inspections', 1, 'Product inspection service'),
('kyc', 1, 'KYC verification system'),
('analytics', 1, 'AI analytics tools'),
('trade_finance', 1, 'Trade finance options'),
('rfq', 1, 'Request for quotation'),
('escrow', 1, 'Escrow payment service');

SET FOREIGN_KEY_CHECKS = 1;
