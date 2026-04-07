-- schema_v15_addons.sql — Add-On Purchases & Invoice System (PR #10)

CREATE TABLE IF NOT EXISTS addons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    type VARCHAR(50) NOT NULL COMMENT 'extra_product_slot,extra_image_slot,product_boost,featured_listing,livestream_session,api_calls_pack,translation_credit',
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    duration_days INT UNSIGNED DEFAULT NULL COMMENT 'NULL = permanent',
    is_stackable TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    icon VARCHAR(100) DEFAULT 'bi-box',
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS addon_purchases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT UNSIGNED NOT NULL,
    addon_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    target_product_id INT UNSIGNED DEFAULT NULL COMMENT 'for boost/featured',
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    status ENUM('pending','active','expired','cancelled','refunded') NOT NULL DEFAULT 'pending',
    stripe_payment_id VARCHAR(200) DEFAULT NULL,
    activated_at DATETIME DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (addon_id) REFERENCES addons(id) ON DELETE CASCADE,
    INDEX idx_supplier (supplier_id),
    INDEX idx_addon (addon_id),
    INDEX idx_status (status),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS addon_credits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT UNSIGNED NOT NULL,
    addon_type VARCHAR(50) NOT NULL,
    credits_total INT UNSIGNED NOT NULL DEFAULT 0,
    credits_used INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_supplier_type (supplier_id, addon_type),
    INDEX idx_supplier (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT UNSIGNED NOT NULL,
    invoice_number VARCHAR(30) NOT NULL UNIQUE,
    type ENUM('plan_subscription','addon_purchase','refund') NOT NULL DEFAULT 'addon_purchase',
    items_json TEXT NOT NULL DEFAULT '[]',
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    status ENUM('pending','paid','refunded','cancelled') NOT NULL DEFAULT 'pending',
    payment_method VARCHAR(50) DEFAULT NULL,
    payment_ref VARCHAR(200) DEFAULT NULL,
    stripe_invoice_id VARCHAR(200) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at DATETIME DEFAULT NULL,
    FOREIGN KEY (supplier_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_supplier (supplier_id),
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: Insert 7 standard add-on types
INSERT IGNORE INTO addons (name, slug, description, type, price, duration_days, is_stackable, is_active, icon, sort_order) VALUES
('Extra Product Slot',   'extra_product_slot',   'Add one more product listing to your store (permanent)',       'extra_product_slot',   0.50, NULL, 1, 1, 'bi-plus-square',        10),
('Extra Image Slot',     'extra_image_slot',     'Upload one more image per product (permanent)',                 'extra_image_slot',     0.10, NULL, 1, 1, 'bi-images',             20),
('Product Boost',        'product_boost',        'Boost a product in search results for 7 days',                 'product_boost',        5.00,    7, 1, 1, 'bi-rocket',             30),
('Featured Listing',     'featured_listing',     'Feature a product on the homepage for 1 week',                 'featured_listing',    25.00,    7, 1, 1, 'bi-star-fill',          40),
('Livestream Session',   'livestream_session',   'Host one live selling session',                                 'livestream_session',  10.00, NULL, 1, 1, 'bi-camera-video-fill',  50),
('API Calls Pack',       'api_calls_pack',       'Add 1,000 API calls to your monthly quota',                    'api_calls_pack',       1.00, NULL, 1, 1, 'bi-code-slash',         60),
('Translation Credit',   'translation_credit',   'AI-translate one product listing into one language',           'translation_credit',   2.00, NULL, 1, 1, 'bi-translate',          70);
