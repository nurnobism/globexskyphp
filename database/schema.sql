-- ============================================================
-- GlobexSky — MySQL Schema
-- Compatible with MySQL 5.7+ / 8.0
-- Import via phpMyAdmin or: mysql -u user -p dbname < schema.sql
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------
-- USERS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email         VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name    VARCHAR(100),
    last_name     VARCHAR(100),
    phone         VARCHAR(30),
    role          ENUM('buyer','supplier','admin') NOT NULL DEFAULT 'buyer',
    avatar        VARCHAR(500),
    company_name  VARCHAR(255),
    bio           TEXT,
    is_verified   TINYINT(1) NOT NULL DEFAULT 0,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    email_verified_at DATETIME,
    last_login_at DATETIME,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role  (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- ADDRESSES
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
-- SUPPLIERS (extends user profile for suppliers)
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
-- PRODUCTS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id     INT UNSIGNED NOT NULL,
    category_id     INT UNSIGNED,
    name            VARCHAR(500) NOT NULL,
    slug            VARCHAR(500) NOT NULL UNIQUE,
    short_desc      TEXT,
    description     LONGTEXT,
    sku             VARCHAR(100),
    price           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    compare_price   DECIMAL(12,2),
    min_order_qty   INT UNSIGNED NOT NULL DEFAULT 1,
    stock_qty       INT NOT NULL DEFAULT 0,
    unit            VARCHAR(50) DEFAULT 'piece',
    weight          DECIMAL(10,3),
    weight_unit     VARCHAR(10) DEFAULT 'kg',
    images          JSON,
    specifications  JSON,
    tags            JSON,
    status          ENUM('draft','active','inactive','archived') NOT NULL DEFAULT 'draft',
    is_featured     TINYINT(1) NOT NULL DEFAULT 0,
    view_count      INT UNSIGNED NOT NULL DEFAULT 0,
    rating          DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    review_count    INT UNSIGNED NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_supplier  (supplier_id),
    INDEX idx_category  (category_id),
    INDEX idx_slug      (slug(191)),
    INDEX idx_status    (status),
    INDEX idx_featured  (is_featured),
    FULLTEXT INDEX ft_search (name, short_desc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- PRODUCT VARIANTS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS product_variants (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id  INT UNSIGNED NOT NULL,
    sku         VARCHAR(100),
    attributes  JSON,         -- e.g. {"color":"red","size":"M"}
    price       DECIMAL(12,2),
    stock_qty   INT NOT NULL DEFAULT 0,
    image       VARCHAR(500),
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
    FOREIGN KEY (user_id)    REFERENCES users(id)            ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)         ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,
    UNIQUE KEY uq_cart_item (user_id, product_id, variant_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- WISHLISTS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS wishlist_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    product_id  INT UNSIGNED NOT NULL,
    added_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY uq_wishlist (user_id, product_id),
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
    status          ENUM('pending','confirmed','processing','shipped','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending',
    subtotal        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    shipping_fee    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency        CHAR(3) NOT NULL DEFAULT 'USD',
    payment_status  ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    payment_method  VARCHAR(100),
    shipping_address JSON,
    billing_address JSON,
    notes           TEXT,
    coupon_code     VARCHAR(100),
    placed_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    confirmed_at    DATETIME,
    shipped_at      DATETIME,
    delivered_at    DATETIME,
    cancelled_at    DATETIME,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id)    REFERENCES users(id)      ON DELETE RESTRICT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)  ON DELETE SET NULL,
    INDEX idx_buyer      (buyer_id),
    INDEX idx_supplier   (supplier_id),
    INDEX idx_status     (status),
    INDEX idx_order_num  (order_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- ORDER ITEMS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id    INT UNSIGNED NOT NULL,
    product_id  INT UNSIGNED,
    variant_id  INT UNSIGNED,
    product_name VARCHAR(500) NOT NULL,
    product_sku  VARCHAR(100),
    quantity    INT UNSIGNED NOT NULL,
    unit_price  DECIMAL(12,2) NOT NULL,
    total_price DECIMAL(12,2) NOT NULL,
    attributes  JSON,
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
    transaction_id  VARCHAR(255),
    amount          DECIMAL(12,2) NOT NULL,
    currency        CHAR(3) NOT NULL DEFAULT 'USD',
    method          VARCHAR(100),
    gateway         VARCHAR(100),
    status          ENUM('pending','success','failed','refunded') NOT NULL DEFAULT 'pending',
    gateway_response JSON,
    paid_at         DATETIME,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- SHIPMENTS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS shipments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id        INT UNSIGNED NOT NULL,
    tracking_number VARCHAR(255),
    carrier         VARCHAR(100),
    status          ENUM('pending','processing','in_transit','out_for_delivery','delivered','failed','returned') NOT NULL DEFAULT 'pending',
    estimated_delivery DATE,
    shipped_at      DATETIME,
    delivered_at    DATETIME,
    origin          VARCHAR(255),
    destination     VARCHAR(255),
    tracking_url    VARCHAR(500),
    events          JSON,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order   (order_id),
    INDEX idx_tracking (tracking_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- REVIEWS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id  INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    order_id    INT UNSIGNED,
    rating      TINYINT UNSIGNED NOT NULL CHECK (rating BETWEEN 1 AND 5),
    title       VARCHAR(255),
    body        TEXT,
    images      JSON,
    status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    helpful     INT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE SET NULL,
    UNIQUE KEY uq_review (user_id, product_id),
    INDEX idx_product (product_id),
    INDEX idx_user    (user_id),
    INDEX idx_status  (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- RFQ (Request for Quotation)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS rfqs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rfq_number      VARCHAR(50) NOT NULL UNIQUE,
    buyer_id        INT UNSIGNED NOT NULL,
    title           VARCHAR(500) NOT NULL,
    description     TEXT,
    category_id     INT UNSIGNED,
    quantity        INT UNSIGNED,
    unit            VARCHAR(50),
    target_price    DECIMAL(12,2),
    currency        CHAR(3) NOT NULL DEFAULT 'USD',
    destination_country VARCHAR(100),
    deadline        DATE,
    attachments     JSON,
    status          ENUM('open','closed','awarded','cancelled') NOT NULL DEFAULT 'open',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id)    REFERENCES users(id)       ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id)  ON DELETE SET NULL,
    INDEX idx_buyer  (buyer_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- RFQ QUOTES (responses from suppliers)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS rfq_quotes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rfq_id      INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    unit_price  DECIMAL(12,2) NOT NULL,
    total_price DECIMAL(12,2),
    currency    CHAR(3) NOT NULL DEFAULT 'USD',
    lead_time   VARCHAR(100),
    valid_until DATE,
    message     TEXT,
    attachments JSON,
    status      ENUM('submitted','accepted','rejected','withdrawn') NOT NULL DEFAULT 'submitted',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rfq_id)      REFERENCES rfqs(id)       ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)  ON DELETE CASCADE,
    INDEX idx_rfq      (rfq_id),
    INDEX idx_supplier (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- COUPONS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS coupons (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(100) NOT NULL UNIQUE,
    type            ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    value           DECIMAL(12,2) NOT NULL,
    min_order       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    max_discount    DECIMAL(12,2),
    usage_limit     INT UNSIGNED,
    used_count      INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at      DATETIME,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code   (code),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- NOTIFICATIONS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    type        VARCHAR(100) NOT NULL,
    title       VARCHAR(255),
    message     TEXT,
    data        JSON,
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user    (user_id),
    INDEX idx_is_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- MESSAGES (User to User / Support Chat)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS conversations (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED,
    subject     VARCHAR(500),
    status      ENUM('open','closed') NOT NULL DEFAULT 'open',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    INDEX idx_user     (user_id),
    INDEX idx_supplier (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    INDEX idx_conversation (conversation_id),
    INDEX idx_sender       (sender_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- CMS — Blog Posts
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS blog_posts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    author_id   INT UNSIGNED NOT NULL,
    title       VARCHAR(500) NOT NULL,
    slug        VARCHAR(500) NOT NULL UNIQUE,
    excerpt     TEXT,
    content     LONGTEXT,
    thumbnail   VARCHAR(500),
    tags        JSON,
    status      ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    view_count  INT UNSIGNED NOT NULL DEFAULT 0,
    published_at DATETIME,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_slug   (slug(191)),
    INDEX idx_status (status),
    FULLTEXT INDEX ft_blog (title, excerpt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- NEWSLETTER SUBSCRIBERS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email       VARCHAR(255) NOT NULL UNIQUE,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    subscribed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- CONTACT INQUIRIES
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS contact_inquiries (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    email       VARCHAR(255) NOT NULL,
    phone       VARCHAR(30),
    subject     VARCHAR(255),
    message     TEXT NOT NULL,
    status      ENUM('new','in_progress','resolved') NOT NULL DEFAULT 'new',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- PLATFORM SETTINGS (key-value)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    `key`       VARCHAR(255) NOT NULL PRIMARY KEY,
    `value`     TEXT,
    group_name  VARCHAR(100) NOT NULL DEFAULT 'general',
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_group (group_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- ADMIN LOGS
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
-- PASSWORD RESETS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email       VARCHAR(255) NOT NULL,
    token       VARCHAR(255) NOT NULL,
    expires_at  DATETIME NOT NULL,
    used        TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------
-- SEED DATA
-- -----------------------------------------------------------

-- Default admin user (password: Admin@123)
INSERT IGNORE INTO users (email, password_hash, first_name, last_name, role, is_verified, is_active)
VALUES ('admin@globexsky.com',
        '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'Admin', 'User', 'admin', 1, 1);

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
('Construction',      'construction',      'Building materials and tools',        10);

-- Default settings
INSERT IGNORE INTO settings (`key`, `value`, group_name) VALUES
('site_name',              'GlobexSky',               'general'),
('site_tagline',           'Global B2B Trade Platform', 'general'),
('contact_email',          'support@globexsky.com',    'general'),
('items_per_page',         '20',                       'general'),
('currency',               'USD',                      'general'),
('currency_symbol',        '$',                        'general'),
('maintenance_mode',       '0',                        'general'),
('registration_enabled',   '1',                        'general');
