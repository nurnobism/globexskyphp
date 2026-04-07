-- ============================================================
-- schema_v14_checkout.sql — Checkout & Stripe Payment (PR #6)
-- ============================================================

-- -----------------------------------------------------------
-- WEBHOOK LOGS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS webhook_logs (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source       VARCHAR(50) NOT NULL DEFAULT 'stripe',
    event_type   VARCHAR(100) NOT NULL,
    event_id     VARCHAR(255),
    payload      LONGTEXT,
    status       ENUM('received','processed','failed') NOT NULL DEFAULT 'received',
    error        TEXT,
    processed_at DATETIME,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_type (event_type),
    INDEX idx_status     (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- PAYMENT INTENTS (lightweight log per PaymentIntent)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS payment_intents (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id           INT UNSIGNED NOT NULL,
    payment_intent_id  VARCHAR(255) NOT NULL UNIQUE,
    amount_cents       INT UNSIGNED NOT NULL,
    currency           CHAR(3) NOT NULL DEFAULT 'USD',
    status             VARCHAR(50) NOT NULL DEFAULT 'created',
    client_secret      TEXT,
    metadata           JSON,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order          (order_id),
    INDEX idx_intent_id      (payment_intent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- ALTER orders — add commission_amount (if missing)
-- -----------------------------------------------------------
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'orders'
      AND COLUMN_NAME  = 'commission_amount'
);
SET @sql = IF(
    @col_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `commission_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------
-- ALTER orders — add payment_intent_id (if missing)
-- -----------------------------------------------------------
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'orders'
      AND COLUMN_NAME  = 'payment_intent_id'
);
SET @sql = IF(
    @col_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `payment_intent_id` VARCHAR(255) DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------
-- ALTER order_items — add product_image (if missing)
-- -----------------------------------------------------------
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'order_items'
      AND COLUMN_NAME  = 'product_image'
);
SET @sql = IF(
    @col_exists = 0,
    'ALTER TABLE `order_items` ADD COLUMN `product_image` VARCHAR(500) DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------
-- ALTER order_items — add variation_info (if missing)
-- -----------------------------------------------------------
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'order_items'
      AND COLUMN_NAME  = 'variation_info'
);
SET @sql = IF(
    @col_exists = 0,
    'ALTER TABLE `order_items` ADD COLUMN `variation_info` VARCHAR(500) DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
