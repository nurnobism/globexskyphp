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

-- -----------------------------------------------------------
-- ADVERTISING CAMPAIGNS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS advertising_campaigns (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED NOT NULL,
    title            VARCHAR(255) NOT NULL,
    description      TEXT,
    budget           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    start_date       DATE NOT NULL,
    end_date         DATE NOT NULL,
    status           ENUM('draft','active','paused','completed') NOT NULL DEFAULT 'draft',
    target_audience  VARCHAR(255),
    impressions      INT UNSIGNED NOT NULL DEFAULT 0,
    clicks           INT UNSIGNED NOT NULL DEFAULT 0,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- AD ANALYTICS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS ad_analytics (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id   INT UNSIGNED NOT NULL,
    date          DATE NOT NULL,
    impressions   INT UNSIGNED NOT NULL DEFAULT 0,
    clicks        INT UNSIGNED NOT NULL DEFAULT 0,
    conversions   INT UNSIGNED NOT NULL DEFAULT 0,
    spend         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_campaign (campaign_id),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- BLOG COMMENTS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS blog_comments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id     INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    content     TEXT NOT NULL,
    parent_id   INT UNSIGNED DEFAULT NULL,
    status      ENUM('pending','approved','spam') NOT NULL DEFAULT 'pending',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_post (post_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- CHAT ROOMS & MESSAGES
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS chat_rooms (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255),
    type        ENUM('direct','group') NOT NULL DEFAULT 'direct',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_messages (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id     INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    message     TEXT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_room (room_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_room_members (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id   INT UNSIGNED NOT NULL,
    user_id   INT UNSIGNED NOT NULL,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_room_user (room_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- PRODUCT COMPARISONS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS product_comparisons (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED DEFAULT NULL,
    product_ids JSON,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- COUPON USAGE
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS coupon_usage (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    coupon_id       INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NOT NULL,
    order_id        INT UNSIGNED DEFAULT NULL,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    used_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_coupon (coupon_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- PRODUCT CUSTOMIZATIONS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS product_customizations (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED NOT NULL,
    product_id     INT UNSIGNED NOT NULL,
    options        JSON,
    preview_image  VARCHAR(500),
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- DISPUTES
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS disputes (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id     INT UNSIGNED NOT NULL,
    buyer_id     INT UNSIGNED NOT NULL,
    seller_id    INT UNSIGNED NOT NULL,
    reason       VARCHAR(255) NOT NULL,
    description  TEXT,
    evidence     JSON,
    status       ENUM('open','under_review','resolved','closed') NOT NULL DEFAULT 'open',
    resolution   TEXT,
    resolved_at  DATETIME DEFAULT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order (order_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- ESCROW TRANSACTIONS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS escrow_transactions (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id     INT UNSIGNED DEFAULT NULL,
    buyer_id     INT UNSIGNED NOT NULL,
    seller_id    INT UNSIGNED NOT NULL,
    amount       DECIMAL(12,2) NOT NULL,
    currency     VARCHAR(3) NOT NULL DEFAULT 'USD',
    status       ENUM('pending','held','released','disputed') NOT NULL DEFAULT 'pending',
    released_at  DATETIME DEFAULT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_buyer (buyer_id),
    INDEX idx_seller (seller_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- FLASH SALES
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS flash_sales (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title               VARCHAR(255) NOT NULL,
    description         TEXT,
    start_time          DATETIME NOT NULL,
    end_time            DATETIME NOT NULL,
    discount_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    status              ENUM('draft','active','ended') NOT NULL DEFAULT 'draft',
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_times (start_time, end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS flash_sale_products (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    flash_sale_id   INT UNSIGNED NOT NULL,
    product_id      INT UNSIGNED NOT NULL,
    original_price  DECIMAL(12,2) NOT NULL,
    sale_price      DECIMAL(12,2) NOT NULL,
    quantity        INT UNSIGNED NOT NULL DEFAULT 0,
    sold_count      INT UNSIGNED NOT NULL DEFAULT 0,
    INDEX idx_sale (flash_sale_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- GDPR REQUESTS & CONSENT
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS gdpr_requests (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    type         ENUM('export','delete') NOT NULL,
    status       ENUM('pending','processing','completed','rejected') NOT NULL DEFAULT 'pending',
    completed_at DATETIME DEFAULT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS consent_logs (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    consent_type  VARCHAR(100) NOT NULL,
    granted       TINYINT(1) NOT NULL DEFAULT 1,
    ip_address    VARCHAR(45),
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- MARKET INSIGHTS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS market_insights (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category    VARCHAR(100) NOT NULL,
    title       VARCHAR(255) NOT NULL,
    data        JSON,
    period      VARCHAR(50),
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- LOGISTICS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS logistics_routes (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    origin         VARCHAR(255) NOT NULL,
    destination    VARCHAR(255) NOT NULL,
    carrier        VARCHAR(255),
    estimated_days INT UNSIGNED NOT NULL DEFAULT 0,
    cost           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_origin (origin),
    INDEX idx_destination (destination)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS warehouses (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(255) NOT NULL,
    address       TEXT,
    city          VARCHAR(100),
    country       VARCHAR(100),
    capacity      INT UNSIGNED NOT NULL DEFAULT 0,
    current_stock INT UNSIGNED NOT NULL DEFAULT 0,
    manager_id    INT UNSIGNED DEFAULT NULL,
    status        ENUM('active','inactive','maintenance') NOT NULL DEFAULT 'active',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_country (country),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- LOYALTY
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS loyalty_points (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    points       INT NOT NULL,
    type         ENUM('earned','redeemed') NOT NULL,
    description  VARCHAR(255),
    reference_id INT UNSIGNED DEFAULT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loyalty_rewards (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    description     TEXT,
    points_required INT UNSIGNED NOT NULL,
    category        VARCHAR(100),
    image           VARCHAR(500),
    stock           INT UNSIGNED NOT NULL DEFAULT 0,
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- MEETINGS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS meetings (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organizer_id INT UNSIGNED NOT NULL,
    title        VARCHAR(255) NOT NULL,
    description  TEXT,
    start_time   DATETIME NOT NULL,
    end_time     DATETIME NOT NULL,
    meeting_url  VARCHAR(500),
    status       ENUM('scheduled','in_progress','completed','cancelled') NOT NULL DEFAULT 'scheduled',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_organizer (organizer_id),
    INDEX idx_status (status),
    INDEX idx_start (start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meeting_participants (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    status     ENUM('invited','accepted','declined') NOT NULL DEFAULT 'invited',
    joined_at  DATETIME DEFAULT NULL,
    INDEX idx_meeting (meeting_id),
    UNIQUE KEY uniq_meeting_user (meeting_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- NEWSLETTERS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS newsletters (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(255) NOT NULL,
    subject    VARCHAR(255) NOT NULL,
    content    TEXT NOT NULL,
    status     ENUM('draft','sent') NOT NULL DEFAULT 'draft',
    sent_at    DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- NOTIFICATION PREFERENCES
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS notification_preferences (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    type          VARCHAR(100) NOT NULL,
    email_enabled TINYINT(1) NOT NULL DEFAULT 1,
    push_enabled  TINYINT(1) NOT NULL DEFAULT 1,
    sms_enabled   TINYINT(1) NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_type (user_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- PAYMENT METHODS & TRANSACTIONS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS payment_methods (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id              INT UNSIGNED NOT NULL,
    type                 ENUM('card','bank','wallet') NOT NULL,
    provider             VARCHAR(100),
    account_number_last4 VARCHAR(4),
    is_default           TINYINT(1) NOT NULL DEFAULT 0,
    status               ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_transactions (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id          INT UNSIGNED DEFAULT NULL,
    user_id           INT UNSIGNED NOT NULL,
    payment_method_id INT UNSIGNED DEFAULT NULL,
    amount            DECIMAL(12,2) NOT NULL,
    currency          VARCHAR(3) NOT NULL DEFAULT 'USD',
    status            ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
    transaction_ref   VARCHAR(100),
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_order (order_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- SAMPLE REQUESTS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS sample_requests (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED NOT NULL,
    product_id       INT UNSIGNED NOT NULL,
    quantity         INT UNSIGNED NOT NULL DEFAULT 1,
    shipping_address TEXT,
    status           ENUM('pending','approved','shipped','delivered','rejected') NOT NULL DEFAULT 'pending',
    tracking_number  VARCHAR(100),
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_product (product_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- SEARCH HISTORY
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS search_history (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED DEFAULT NULL,
    query         VARCHAR(255) NOT NULL,
    filters       JSON,
    results_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- SOURCING
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS sourcing_requests (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    title       VARCHAR(255) NOT NULL,
    description TEXT,
    category    VARCHAR(100),
    quantity    INT UNSIGNED NOT NULL DEFAULT 0,
    budget      DECIMAL(12,2) DEFAULT NULL,
    deadline    DATE DEFAULT NULL,
    status      ENUM('open','in_progress','closed','cancelled') NOT NULL DEFAULT 'open',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sourcing_quotes (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id    INT UNSIGNED NOT NULL,
    supplier_id   INT UNSIGNED NOT NULL,
    price         DECIMAL(12,2) NOT NULL,
    quantity      INT UNSIGNED NOT NULL DEFAULT 0,
    delivery_time INT UNSIGNED DEFAULT NULL,
    notes         TEXT,
    status        ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_request (request_id),
    INDEX idx_supplier (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- SUPPORT
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS support_tickets (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    subject     VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category    VARCHAR(100),
    priority    ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    status      ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
    assigned_to INT UNSIGNED DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_replies (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id  INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    message    TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS faqs (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question   VARCHAR(500) NOT NULL,
    answer     TEXT NOT NULL,
    category   VARCHAR(100),
    order_num  INT UNSIGNED NOT NULL DEFAULT 0,
    status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- TEAMS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS teams (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    owner_id    INT UNSIGNED NOT NULL,
    description TEXT,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_owner (owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS team_members (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_id    INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    role       ENUM('owner','admin','editor','member','viewer') NOT NULL DEFAULT 'member',
    invited_by INT UNSIGNED DEFAULT NULL,
    status     ENUM('pending','active','removed') NOT NULL DEFAULT 'pending',
    joined_at  DATETIME DEFAULT NULL,
    INDEX idx_team (team_id),
    UNIQUE KEY uniq_team_user (team_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- SUPPLIER SCORECARDS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS supplier_scorecards (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id         INT UNSIGNED NOT NULL,
    quality_score       DECIMAL(3,1) NOT NULL DEFAULT 0.0,
    delivery_score      DECIMAL(3,1) NOT NULL DEFAULT 0.0,
    communication_score DECIMAL(3,1) NOT NULL DEFAULT 0.0,
    overall_score       DECIMAL(3,1) NOT NULL DEFAULT 0.0,
    review_period       VARCHAR(50),
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_supplier (supplier_id)
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
    INDEX idx_user (user_id),
    INDEX idx_action (action)
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
