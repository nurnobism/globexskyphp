-- GlobexSky Database Schema v4
-- Dropshipping Engine — Full Module
-- Run after schema.sql, schema_v2.sql, and schema_v3.sql

SET NAMES utf8mb4;

-- Dropshipping store for each dropshipper
CREATE TABLE IF NOT EXISTS dropship_stores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    store_name VARCHAR(200) NOT NULL,
    store_slug VARCHAR(200) UNIQUE,
    store_description TEXT,
    logo_url VARCHAR(500),
    banner_url VARCHAR(500),
    theme_color VARCHAR(7) DEFAULT '#0d6efd',
    custom_domain VARCHAR(200),
    is_active TINYINT(1) DEFAULT 1,
    total_products INT DEFAULT 0,
    total_orders INT DEFAULT 0,
    total_revenue DECIMAL(12,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_slug (store_slug),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Imported products (dropshipper's copy of supplier products)
CREATE TABLE IF NOT EXISTS dropship_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    dropshipper_id INT NOT NULL,
    original_product_id INT NOT NULL,
    supplier_id INT NOT NULL,
    custom_title VARCHAR(300),
    custom_description TEXT,
    custom_images JSON,
    markup_type ENUM('percentage','fixed') DEFAULT 'percentage',
    markup_value DECIMAL(10,2) NOT NULL DEFAULT 20.00,
    selling_price DECIMAL(12,2) NOT NULL,
    original_price DECIMAL(12,2) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    is_auto_sync TINYINT(1) DEFAULT 1,
    last_synced_at DATETIME,
    import_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_store (store_id),
    INDEX idx_dropshipper (dropshipper_id),
    INDEX idx_original (original_product_id),
    INDEX idx_supplier (supplier_id),
    INDEX idx_active (is_active),
    FOREIGN KEY (store_id) REFERENCES dropship_stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dropship product variation overrides
CREATE TABLE IF NOT EXISTS dropship_product_variations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dropship_product_id INT NOT NULL,
    original_variation_id INT NOT NULL,
    custom_price DECIMAL(12,2),
    markup_value DECIMAL(10,2),
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (dropship_product_id) REFERENCES dropship_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dropship orders
CREATE TABLE IF NOT EXISTS dropship_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    dropshipper_id INT NOT NULL,
    supplier_id INT NOT NULL,
    store_id INT NOT NULL,
    customer_id INT NOT NULL,
    original_price DECIMAL(12,2) NOT NULL,
    selling_price DECIMAL(12,2) NOT NULL,
    markup_amount DECIMAL(12,2) NOT NULL,
    platform_dropship_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    dropshipper_earning DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    supplier_earning DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    platform_earning DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending','routed','processing','shipped','delivered','cancelled','refunded') DEFAULT 'pending',
    is_white_label TINYINT(1) DEFAULT 0,
    white_label_brand VARCHAR(200),
    tracking_number VARCHAR(100),
    tracking_url VARCHAR(500),
    routed_at DATETIME,
    shipped_at DATETIME,
    delivered_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order (order_id),
    INDEX idx_dropshipper (dropshipper_id),
    INDEX idx_supplier (supplier_id),
    INDEX idx_store (store_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dropship earnings/payouts
CREATE TABLE IF NOT EXISTS dropship_earnings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dropshipper_id INT NOT NULL,
    dropship_order_id INT NOT NULL,
    order_id INT NOT NULL,
    gross_amount DECIMAL(12,2) NOT NULL,
    platform_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    net_amount DECIMAL(12,2) NOT NULL,
    status ENUM('pending','available','requested','paid','cancelled') DEFAULT 'pending',
    available_at DATETIME,
    paid_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_dropshipper (dropshipper_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dropship analytics (daily aggregation)
CREATE TABLE IF NOT EXISTS dropship_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    dropshipper_id INT NOT NULL,
    date DATE NOT NULL,
    impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    orders INT DEFAULT 0,
    revenue DECIMAL(12,2) DEFAULT 0.00,
    earnings DECIMAL(12,2) DEFAULT 0.00,
    conversion_rate DECIMAL(5,2) DEFAULT 0.00,
    UNIQUE KEY unique_store_date (store_id, date),
    INDEX idx_dropshipper (dropshipper_id),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Supplier dropshipping settings
CREATE TABLE IF NOT EXISTS supplier_dropship_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL UNIQUE,
    allow_dropshipping TINYINT(1) DEFAULT 0,
    min_markup_percent DECIMAL(5,2) DEFAULT 5.00,
    max_markup_percent DECIMAL(5,2) DEFAULT 300.00,
    white_label_available TINYINT(1) DEFAULT 0,
    auto_approve_dropshippers TINYINT(1) DEFAULT 0,
    dropship_terms TEXT,
    processing_time_days INT DEFAULT 3,
    return_policy ENUM('no_returns','7_days','14_days','30_days') DEFAULT '14_days',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_supplier (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dropship supplier applications
CREATE TABLE IF NOT EXISTS dropship_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dropshipper_id INT NOT NULL,
    supplier_id INT NOT NULL,
    store_id INT NOT NULL,
    message TEXT,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    reviewed_at DATETIME,
    reviewed_by INT,
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_dropshipper_supplier (dropshipper_id, supplier_id),
    INDEX idx_supplier (supplier_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dropship markup rules (admin-configurable per category)
CREATE TABLE IF NOT EXISTS dropship_markup_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    markup_pct DECIMAL(5,2) NOT NULL DEFAULT 20.00,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
