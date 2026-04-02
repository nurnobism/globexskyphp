-- ============================================================
-- GlobexSky — install.sql
-- Single-file installer for a fresh MySQL 5.7+ / 8.0 database
--
-- Usage:
--   mysql -u USER -p DBNAME < database/install.sql
--   OR import via phpMyAdmin
--
-- This file combines schema.sql + schema_v2.sql + seed data.
-- Run only once on a fresh database.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- CORE TABLES
-- ============================================================

-- -----------------------------------------------------------
-- USERS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email         VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name          VARCHAR(200),
    first_name    VARCHAR(100),
    last_name     VARCHAR(100),
    phone         VARCHAR(30),
    role          ENUM('buyer','supplier','carrier','admin','super_admin','inspector','support','api_client') NOT NULL DEFAULT 'buyer',
    status        ENUM('active','suspended','banned','pending') NOT NULL DEFAULT 'active',
    avatar        VARCHAR(500),
    company_name  VARCHAR(255),
    bio           TEXT,
    language      VARCHAR(10) DEFAULT 'en',
    currency      VARCHAR(10) DEFAULT 'USD',
    timezone      VARCHAR(100) DEFAULT 'UTC',
    is_verified   TINYINT(1) NOT NULL DEFAULT 0,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    email_verified_at DATETIME,
    last_login_at DATETIME,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email  (email),
    INDEX idx_role   (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- USER SESSIONS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_sessions (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    ip_address    VARCHAR(45),
    user_agent    VARCHAR(500),
    expires_at    DATETIME NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id       (user_id),
    INDEX idx_session_token (session_token),
    INDEX idx_expires_at    (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- USER ADDRESSES
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_addresses (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    label         VARCHAR(100) DEFAULT 'Home',
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255),
    city          VARCHAR(100) NOT NULL,
    state         VARCHAR(100),
    country       VARCHAR(100) NOT NULL DEFAULT 'US',
    postal_code   VARCHAR(20),
    phone         VARCHAR(30),
    is_default    TINYINT(1) NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- ADDRESSES (legacy, kept for backward compatibility)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS addresses (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    label       VARCHAR(100) DEFAULT 'Home',
    full_name   VARCHAR(200),
    phone       VARCHAR(30),
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255),
    city        VARCHAR(100) NOT NULL,
    state       VARCHAR(100),
    postal_code VARCHAR(20),
    country     VARCHAR(100) NOT NULL DEFAULT 'US',
    is_default  TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- PASSWORD RESETS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED,
    email       VARCHAR(255) NOT NULL,
    token       VARCHAR(255) NOT NULL,
    expires_at  DATETIME NOT NULL,
    used        TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_email (email),
    INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- CATEGORIES
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id   INT UNSIGNED DEFAULT NULL,
    name        VARCHAR(200) NOT NULL,
    slug        VARCHAR(200) NOT NULL UNIQUE,
    description TEXT,
    image       VARCHAR(500),
    sort_order  INT NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_slug   (slug),
    INDEX idx_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- SUPPLIERS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS suppliers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL UNIQUE,
    company_name    VARCHAR(255) NOT NULL,
    slug            VARCHAR(255) UNIQUE,
    description     TEXT,
    logo            VARCHAR(500),
    banner          VARCHAR(500),
    country         VARCHAR(100),
    city            VARCHAR(100),
    website         VARCHAR(500),
    business_type   VARCHAR(100),
    verified        TINYINT(1) NOT NULL DEFAULT 0,
    total_products  INT UNSIGNED NOT NULL DEFAULT 0,
    total_orders    INT UNSIGNED NOT NULL DEFAULT 0,
    rating          DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    response_time   VARCHAR(50),
    established_year YEAR,
    employee_count  VARCHAR(50),
    annual_revenue  VARCHAR(100),
    certifications  JSON,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_slug    (slug),
    INDEX idx_country (country)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- CARRIERS
-- -----------------------------------------------------------
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
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_carriers_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- PRODUCTS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id     INT UNSIGNED NOT NULL,
    category_id     INT UNSIGNED,
    sku             VARCHAR(100),
    name            VARCHAR(255) NOT NULL,
    slug            VARCHAR(255) NOT NULL UNIQUE,
    description     TEXT,
    short_desc      VARCHAR(500),
    price           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    sale_price      DECIMAL(12,2),
    cost_price      DECIMAL(12,2),
    min_order_qty   INT UNSIGNED NOT NULL DEFAULT 1,
    stock_qty       INT NOT NULL DEFAULT 0,
    weight          DECIMAL(8,3),
    dimensions      VARCHAR(100),
    images          JSON,
    tags            JSON,
    status          ENUM('active','inactive','draft','deleted') NOT NULL DEFAULT 'active',
    is_featured     TINYINT(1) NOT NULL DEFAULT 0,
    view_count      INT UNSIGNED NOT NULL DEFAULT 0,
    rating          DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    review_count    INT UNSIGNED NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_slug       (slug),
    INDEX idx_supplier   (supplier_id),
    INDEX idx_category   (category_id),
    INDEX idx_status     (status),
    INDEX idx_is_featured (is_featured)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- PRODUCT VARIANTS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS product_variants (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id  INT UNSIGNED NOT NULL,
    name        VARCHAR(200) NOT NULL,
    sku         VARCHAR(100),
    price       DECIMAL(12,2),
    stock_qty   INT NOT NULL DEFAULT 0,
    attributes  JSON,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- CART ITEMS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS cart_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    product_id  INT UNSIGNED NOT NULL,
    variant_id  INT UNSIGNED,
    quantity    INT UNSIGNED NOT NULL DEFAULT 1,
    added_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cart (user_id, product_id, variant_id),
    FOREIGN KEY (user_id)    REFERENCES users(id)            ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)         ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- WISHLIST ITEMS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS wishlist_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    product_id  INT UNSIGNED NOT NULL,
    added_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_wishlist (user_id, product_id),
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- ORDERS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number    VARCHAR(50) NOT NULL UNIQUE,
    buyer_id        INT UNSIGNED NOT NULL,
    supplier_id     INT UNSIGNED,
    subtotal        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    shipping_cost   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_amount      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency        VARCHAR(10) NOT NULL DEFAULT 'USD',
    status          ENUM('pending','confirmed','processing','shipped','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending',
    payment_status  ENUM('pending','paid','failed','refunded','partial') NOT NULL DEFAULT 'pending',
    payment_method  VARCHAR(100),
    shipping_address JSON,
    notes           TEXT,
    placed_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id)    REFERENCES users(id)      ON DELETE RESTRICT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)  ON DELETE SET NULL,
    INDEX idx_buyer      (buyer_id),
    INDEX idx_supplier   (supplier_id),
    INDEX idx_status     (status),
    INDEX idx_placed_at  (placed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- ORDER ITEMS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_items (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id        INT UNSIGNED NOT NULL,
    product_id      INT UNSIGNED,
    variant_id      INT UNSIGNED,
    product_name    VARCHAR(255) NOT NULL,
    product_sku     VARCHAR(100),
    unit_price      DECIMAL(12,2) NOT NULL,
    quantity        INT UNSIGNED NOT NULL DEFAULT 1,
    total_price     DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (order_id)   REFERENCES orders(id)           ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)         ON DELETE SET NULL,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,
    INDEX idx_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- PAYMENTS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS payments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id        INT UNSIGNED NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    currency        VARCHAR(10) NOT NULL DEFAULT 'USD',
    method          VARCHAR(100),
    gateway         VARCHAR(100),
    transaction_id  VARCHAR(255),
    status          ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
    gateway_response JSON,
    paid_at         DATETIME,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- REVIEWS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id  INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    order_id    INT UNSIGNED,
    rating      TINYINT UNSIGNED NOT NULL DEFAULT 5,
    title       VARCHAR(255),
    body        TEXT,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE SET NULL,
    INDEX idx_product (product_id),
    INDEX idx_user    (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- RFQs
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS rfqs (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    buyer_id      INT UNSIGNED NOT NULL,
    category_id   INT UNSIGNED,
    title         VARCHAR(255) NOT NULL,
    description   TEXT NOT NULL,
    quantity      VARCHAR(100),
    budget        DECIMAL(12,2),
    currency      VARCHAR(10) DEFAULT 'USD',
    destination   VARCHAR(100),
    deadline      DATE,
    status        ENUM('open','quoted','closed','cancelled') NOT NULL DEFAULT 'open',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id)    REFERENCES users(id)       ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id)  ON DELETE SET NULL,
    INDEX idx_buyer  (buyer_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- RFQ QUOTES
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS rfq_quotes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rfq_id          INT UNSIGNED NOT NULL,
    supplier_id     INT UNSIGNED NOT NULL,
    price           DECIMAL(12,2) NOT NULL,
    lead_time       VARCHAR(100),
    notes           TEXT,
    attachments     JSON,
    status          ENUM('pending','accepted','rejected','withdrawn') NOT NULL DEFAULT 'pending',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rfq_id)      REFERENCES rfqs(id)       ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)  ON DELETE CASCADE,
    INDEX idx_rfq      (rfq_id),
    INDEX idx_supplier (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- NOTIFICATIONS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    type        VARCHAR(100) NOT NULL,
    title       VARCHAR(255),
    body        TEXT,
    data        JSON,
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user   (user_id),
    INDEX idx_is_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- CONVERSATIONS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS conversations (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED,
    subject     VARCHAR(255),
    last_msg_at DATETIME,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- MESSAGES
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT UNSIGNED NOT NULL,
    sender_id       INT UNSIGNED NOT NULL,
    body            TEXT NOT NULL,
    attachments     JSON,
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id)       REFERENCES users(id)         ON DELETE CASCADE,
    INDEX idx_conversation (conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- BLOG POSTS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS blog_posts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    author_id   INT UNSIGNED NOT NULL,
    title       VARCHAR(255) NOT NULL,
    slug        VARCHAR(255) NOT NULL UNIQUE,
    excerpt     TEXT,
    body        LONGTEXT,
    cover_image VARCHAR(500),
    tags        JSON,
    status      ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    published_at DATETIME,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_slug   (slug),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- NEWSLETTER SUBSCRIBERS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email        VARCHAR(255) NOT NULL UNIQUE,
    name         VARCHAR(200),
    is_active    TINYINT(1) NOT NULL DEFAULT 1,
    subscribed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- CONTACT INQUIRIES
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS contact_inquiries (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(200) NOT NULL,
    email       VARCHAR(255) NOT NULL,
    subject     VARCHAR(255),
    message     TEXT NOT NULL,
    status      ENUM('new','in_progress','resolved','spam') NOT NULL DEFAULT 'new',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_email  (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- PLATFORM SETTINGS (key-value, used by admin settings pages)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS platform_settings (
    setting_key   VARCHAR(255) NOT NULL PRIMARY KEY,
    setting_value TEXT,
    setting_group VARCHAR(100) NOT NULL DEFAULT 'general',
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_group (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- SETTINGS (legacy key-value store)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    `key`       VARCHAR(255) NOT NULL PRIMARY KEY,
    `value`     TEXT,
    group_name  VARCHAR(100) NOT NULL DEFAULT 'general',
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_group (group_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- SYSTEM SETTINGS (structured, with id + timestamps)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS system_settings (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key   VARCHAR(255) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_group VARCHAR(100) NOT NULL DEFAULT 'general',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_group (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- ADMIN ACTIVITY LOGS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_activity_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id    INT UNSIGNED NOT NULL,
    action      VARCHAR(255) NOT NULL,
    target_type VARCHAR(100),
    target_id   INT UNSIGNED,
    details     JSON,
    ip_address  VARCHAR(45),
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_admin      (admin_id),
    INDEX idx_target     (target_type, target_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- ADMIN LOGS (legacy)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id    INT UNSIGNED NOT NULL,
    action      VARCHAR(255) NOT NULL,
    entity_type VARCHAR(100),
    entity_id   INT UNSIGNED,
    old_data    JSON,
    new_data    JSON,
    ip_address  VARCHAR(45),
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_admin  (admin_id),
    INDEX idx_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- COUPONS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS coupons (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(50) NOT NULL UNIQUE,
    type            ENUM('percent','fixed','free_shipping') NOT NULL DEFAULT 'percent',
    value           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    min_order_value DECIMAL(12,2) DEFAULT 0.00,
    max_discount    DECIMAL(12,2),
    usage_limit     INT UNSIGNED,
    used_count      INT UNSIGNED NOT NULL DEFAULT 0,
    start_date      DATE,
    end_date        DATE,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code      (code),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- SHIPMENTS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS shipments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id        INT UNSIGNED NOT NULL,
    carrier_name    VARCHAR(100),
    tracking_number VARCHAR(255),
    tracking_url    VARCHAR(500),
    shipped_at      DATETIME,
    estimated_at    DATE,
    delivered_at    DATETIME,
    status          ENUM('pending','picked_up','in_transit','out_for_delivery','delivered','failed') NOT NULL DEFAULT 'pending',
    notes           TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order   (order_id),
    INDEX idx_status  (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- SUPPORT TICKETS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS support_tickets (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED,
    subject     VARCHAR(255) NOT NULL,
    body        TEXT NOT NULL,
    category    VARCHAR(100),
    priority    ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    status      ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
    assigned_to INT UNSIGNED,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user   (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- DISPUTES
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS disputes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id        INT UNSIGNED NOT NULL,
    raised_by       INT UNSIGNED NOT NULL,
    reason          VARCHAR(255) NOT NULL,
    description     TEXT,
    evidence        JSON,
    status          ENUM('open','under_review','resolved','closed') NOT NULL DEFAULT 'open',
    resolution      TEXT,
    resolved_by     INT UNSIGNED,
    resolved_at     DATETIME,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id)  REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (raised_by) REFERENCES users(id)  ON DELETE CASCADE,
    INDEX idx_order  (order_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- RETURN REQUESTS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS return_requests (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id    INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    reason      VARCHAR(255) NOT NULL,
    description TEXT,
    status      ENUM('requested','approved','rejected','completed') NOT NULL DEFAULT 'requested',
    refund_amount DECIMAL(12,2),
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order  (order_id),
    INDEX idx_user   (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- ACTIVITY LOGS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED DEFAULT NULL,
    action      VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address  VARCHAR(45),
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user   (user_id),
    INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Default super_admin user (password: Admin@123)
INSERT IGNORE INTO users (email, password_hash, name, first_name, last_name, role, status, is_verified, is_active)
VALUES ('admin@globexsky.com',
        '$2y$12$qEaCi0r7V1ZoHpwQySEoNOGm2W0WwleHbLKysWkDV68mvXrx9SRbe',
        'Super Admin', 'Super', 'Admin', 'super_admin', 'active', 1, 1);

-- Default categories
INSERT IGNORE INTO categories (name, slug, description, sort_order) VALUES
('Electronics',       'electronics',       'Electronic devices and components',   1),
('Machinery',         'machinery',         'Industrial machinery and equipment',  2),
('Apparel & Fashion', 'apparel-fashion',   'Clothing, shoes, and accessories',    3),
('Home & Garden',     'home-garden',       'Furniture, décor, and garden items',  4),
('Food & Beverage',   'food-beverage',     'Food products and beverages',         5),
('Chemicals',         'chemicals',         'Industrial and consumer chemicals',   6),
('Automotive',        'automotive',        'Auto parts and accessories',          7),
('Health & Beauty',   'health-beauty',     'Health products and cosmetics',       8),
('Sports & Outdoors', 'sports-outdoors',   'Sports equipment and outdoor gear',   9),
('Industrial',        'industrial',        'Industrial tools and equipment',      10),
('Documents',         'documents',         'Document services and templates',     11);

-- Default settings
INSERT IGNORE INTO settings (`key`, `value`, group_name) VALUES
('site_name',              'GlobexSky',                  'general'),
('site_tagline',           'Global B2B Trade Platform',  'general'),
('contact_email',          'support@globexsky.com',      'general'),
('items_per_page',         '20',                         'general'),
('currency',               'USD',                        'general'),
('currency_symbol',        '$',                          'general'),
('maintenance_mode',       '0',                          'general'),
('registration_enabled',   '1',                          'general');

-- System settings
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_group) VALUES
('site_name',            'GlobexSky',                  'general'),
('site_logo',            '/assets/images/logo.png',    'general'),
('default_language',     'en',                         'general'),
('default_currency',     'USD',                        'general'),
('maintenance_mode',     '0',                          'general'),
('registration_enabled', '1',                          'general'),
('contact_email',        'support@globexsky.com',      'general'),
('site_tagline',         'Global B2B Trade Platform',  'general');

-- Platform settings (used by admin settings pages)
INSERT IGNORE INTO platform_settings (setting_key, setting_value, setting_group) VALUES
('ai_enabled',           '1',           'ai'),
('ai_default_provider',  'deepseek',    'ai'),
('ai_max_tokens',        '2048',        'ai'),
('ai_daily_limit',       '100',         'ai'),
('ai_monthly_budget',    '500',         'ai'),
('payment_stripe_enabled', '0',         'payment'),
('payment_paypal_enabled', '0',         'payment'),
('mail_driver',          'smtp',        'mail'),
('maintenance_enabled',  '0',           'maintenance'),
('maintenance_message',  'Site under maintenance. Please check back soon.', 'maintenance');
