-- schema_v17_carry.sql — Carry Service tables (PR #16)
-- GlobexSky unique traveler-based delivery system

CREATE TABLE IF NOT EXISTS `carry_trips` (
    `id`                         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `carrier_id`                 INT UNSIGNED NOT NULL,
    `origin_city`                VARCHAR(120) NOT NULL,
    `origin_country`             VARCHAR(100) NOT NULL,
    `destination_city`           VARCHAR(120) NOT NULL,
    `destination_country`        VARCHAR(100) NOT NULL,
    `departure_date`             DATE NOT NULL,
    `arrival_date`               DATE NOT NULL,
    `max_weight_kg`              DECIMAL(8,2) NOT NULL DEFAULT 0,
    `max_dimensions`             VARCHAR(200) DEFAULT NULL,
    `available_space_description` TEXT DEFAULT NULL,
    `price_per_kg`               DECIMAL(10,2) NOT NULL DEFAULT 0,
    `flat_rate`                  DECIMAL(10,2) DEFAULT NULL,
    `carrier_notes`              TEXT DEFAULT NULL,
    `status`                     ENUM('active','inactive','cancelled','completed') NOT NULL DEFAULT 'active',
    `is_active`                  TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_carrier_id` (`carrier_id`),
    KEY `idx_origin_destination` (`origin_country`, `destination_country`),
    KEY `idx_departure_date` (`departure_date`),
    KEY `idx_is_active` (`is_active`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `carry_requests` (
    `id`                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `buyer_id`              INT UNSIGNED NOT NULL,
    `trip_id`               INT UNSIGNED NOT NULL,
    `order_id`              INT UNSIGNED DEFAULT NULL,
    `package_description`   TEXT NOT NULL,
    `weight_kg`             DECIMAL(8,2) NOT NULL DEFAULT 0,
    `dimensions`            VARCHAR(200) DEFAULT NULL,
    `pickup_address_json`   TEXT DEFAULT NULL,
    `delivery_address_json` TEXT DEFAULT NULL,
    `offered_price`         DECIMAL(10,2) NOT NULL DEFAULT 0,
    `carrier_fee`           DECIMAL(10,2) DEFAULT NULL,
    `status`                ENUM('pending','accepted','picked_up','in_transit','delivered','completed','declined','cancelled','disputed') NOT NULL DEFAULT 'pending',
    `special_instructions`  TEXT DEFAULT NULL,
    `decline_reason`        TEXT DEFAULT NULL,
    `cancel_reason`         TEXT DEFAULT NULL,
    `escrow_paid`           TINYINT(1) NOT NULL DEFAULT 0,
    `payment_released`      TINYINT(1) NOT NULL DEFAULT 0,
    `picked_up_at`          DATETIME DEFAULT NULL,
    `delivered_at`          DATETIME DEFAULT NULL,
    `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_buyer_id` (`buyer_id`),
    KEY `idx_trip_id` (`trip_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `carry_status_log` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `request_id` INT UNSIGNED NOT NULL,
    `status`     VARCHAR(50) NOT NULL,
    `changed_by` INT UNSIGNED NOT NULL,
    `note`       TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_request_id` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `carry_ratings` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `request_id` INT UNSIGNED NOT NULL,
    `buyer_id`   INT UNSIGNED NOT NULL,
    `carrier_id` INT UNSIGNED NOT NULL,
    `rating`     TINYINT UNSIGNED NOT NULL DEFAULT 5,
    `review`     TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_request_rating` (`request_id`),
    KEY `idx_carrier_id` (`carrier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `carrier_profiles` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`         INT UNSIGNED NOT NULL,
    `verified`        TINYINT(1) NOT NULL DEFAULT 0,
    `id_document`     VARCHAR(500) DEFAULT NULL,
    `phone_verified`  TINYINT(1) NOT NULL DEFAULT 0,
    `trips_completed` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_earnings`  DECIMAL(14,2) NOT NULL DEFAULT 0,
    `average_rating`  DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    `bio`             TEXT DEFAULT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
