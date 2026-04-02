-- =============================================================
-- GlobexSky MySQL 8.0 Database Schema
-- Engine: InnoDB | Charset: utf8mb4_unicode_ci
-- =============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS `globexsky_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `globexsky_db`;

-- =============================================================
-- SECTION 1: LANGUAGES & CURRENCIES
-- =============================================================

CREATE TABLE `languages` (
    `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`        VARCHAR(10)  NOT NULL,
    `name`        VARCHAR(60)  NOT NULL,
    `native_name` VARCHAR(60)  NOT NULL,
    `is_rtl`      TINYINT(1)   NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_languages_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `currencies` (
    `id`            TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`          VARCHAR(10)      NOT NULL,
    `name`          VARCHAR(60)      NOT NULL,
    `symbol`        VARCHAR(10)      NOT NULL,
    `exchange_rate` DECIMAL(14,6)    NOT NULL DEFAULT 1.000000 COMMENT 'Rate against USD',
    `is_active`     TINYINT(1)       NOT NULL DEFAULT 1,
    `updated_at`    DATETIME         NOT NULL DEFAULT NOW() ON UPDATE NOW(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_currencies_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 2: CORE USER TABLES
-- =============================================================

CREATE TABLE `users` (
    `id`                  INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `name`                VARCHAR(120)     NOT NULL,
    `email`               VARCHAR(191)     NOT NULL,
    `password`            VARCHAR(255)     NOT NULL,
    `role`                ENUM('buyer','supplier','carrier','admin','superadmin')
                                           NOT NULL DEFAULT 'buyer',
    `avatar`              VARCHAR(255)     DEFAULT NULL,
    `phone`               VARCHAR(30)      DEFAULT NULL,
    `is_active`           TINYINT(1)       NOT NULL DEFAULT 1,
    `is_verified`         TINYINT(1)       NOT NULL DEFAULT 0,
    `verify_token`        VARCHAR(128)     DEFAULT NULL,
    `reset_token`         VARCHAR(128)     DEFAULT NULL,
    `reset_expires`       DATETIME         DEFAULT NULL,
    `two_factor_secret`   VARCHAR(64)      DEFAULT NULL,
    `two_factor_enabled`  TINYINT(1)       NOT NULL DEFAULT 0,
    `last_login`          DATETIME         DEFAULT NULL,
    `created_at`          DATETIME         NOT NULL DEFAULT NOW(),
    `updated_at`          DATETIME         NOT NULL DEFAULT NOW() ON UPDATE NOW(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role`     (`role`),
    KEY `idx_users_is_active`(`is_active`),
    KEY `idx_users_created`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_profiles` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED NOT NULL,
    `bio`             TEXT         DEFAULT NULL,
    `company_name`    VARCHAR(180) DEFAULT NULL,
    `company_address` TEXT         DEFAULT NULL,
    `country`         VARCHAR(80)  DEFAULT NULL,
    `city`            VARCHAR(80)  DEFAULT NULL,
    `postal_code`     VARCHAR(20)  DEFAULT NULL,
    `website`         VARCHAR(255) DEFAULT NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT NOW(),
    `updated_at`      DATETIME     NOT NULL DEFAULT NOW() ON UPDATE NOW(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_profiles_user_id` (`user_id`),
    CONSTRAINT `fk_user_profiles_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_addresses` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`        INT UNSIGNED NOT NULL,
    `label`          VARCHAR(60)  NOT NULL DEFAULT 'Home',
    `recipient_name` VARCHAR(120) NOT NULL,
    `phone`          VARCHAR(30)  DEFAULT NULL,
    `address_line1`  VARCHAR(255) NOT NULL,
    `address_line2`  VARCHAR(255) DEFAULT NULL,
    `city`           VARCHAR(80)  NOT NULL,
    `state`          VARCHAR(80)  DEFAULT NULL,
    `country`        VARCHAR(80)  NOT NULL,
    `postal_code`    VARCHAR(20)  DEFAULT NULL,
    `is_default`     TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`     DATETIME     NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_user_addresses_user` (`user_id`),
    CONSTRAINT `fk_user_addresses_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_sessions` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `token`      VARCHAR(128) NOT NULL,
    `ip_address` VARCHAR(45)  DEFAULT NULL,
    `user_agent` TEXT         DEFAULT NULL,
    `expires_at` DATETIME     NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_sessions_token` (`token`),
    KEY `idx_user_sessions_user`    (`user_id`),
    KEY `idx_user_sessions_expires` (`expires_at`),
    CONSTRAINT `fk_user_sessions_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 3: SUPPLIER TABLES
-- =============================================================

CREATE TABLE `supplier_plans` (
    `id`                   TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`                 VARCHAR(60)      NOT NULL,
    `price_monthly`        DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
    `commission_rate`      DECIMAL(5,4)     NOT NULL DEFAULT 0.0500 COMMENT 'e.g. 0.05 = 5%',
    `max_products`         INT UNSIGNED     NOT NULL DEFAULT 50,
    `ai_marketing_budget`  DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
    `features`             JSON             DEFAULT NULL,
    `is_active`            TINYINT(1)       NOT NULL DEFAULT 1,
    `created_at`           DATETIME         NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `suppliers` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`             INT UNSIGNED NOT NULL,
    `business_name`       VARCHAR(180) NOT NULL,
    `business_type`       VARCHAR(60)  DEFAULT NULL,
    `registration_number` VARCHAR(80)  DEFAULT NULL,
    `country`             VARCHAR(80)  DEFAULT NULL,
    `description`         TEXT         DEFAULT NULL,
    `logo`                VARCHAR(255) DEFAULT NULL,
    `banner`              VARCHAR(255) DEFAULT NULL,
    `rating`              DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    `total_reviews`       INT UNSIGNED NOT NULL DEFAULT 0,
    `total_products`      INT UNSIGNED NOT NULL DEFAULT 0,
    `is_verified`         TINYINT(1)   NOT NULL DEFAULT 0,
    `verification_status` ENUM('unverified','pending','approved','rejected')
                                       NOT NULL DEFAULT 'unverified',
    `plan_id`             TINYINT UNSIGNED DEFAULT NULL,
    `plan_expires_at`     DATETIME     DEFAULT NULL,
    `created_at`          DATETIME     NOT NULL DEFAULT NOW(),
    `updated_at`          DATETIME     NOT NULL DEFAULT NOW() ON UPDATE NOW(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_suppliers_user` (`user_id`),
    KEY `idx_suppliers_plan`     (`plan_id`),
    KEY `idx_suppliers_country`  (`country`),
    KEY `idx_suppliers_verified` (`is_verified`),
    CONSTRAINT `fk_suppliers_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_suppliers_plan`
        FOREIGN KEY (`plan_id`) REFERENCES `supplier_plans`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `supplier_subscriptions` (
    `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `supplier_id` INT UNSIGNED     NOT NULL,
    `plan_id`     TINYINT UNSIGNED NOT NULL,
    `status`      ENUM('active','cancelled','expired','trial')
                                   NOT NULL DEFAULT 'active',
    `started_at`  DATETIME         NOT NULL DEFAULT NOW(),
    `expires_at`  DATETIME         NOT NULL,
    `payment_id`  INT UNSIGNED     DEFAULT NULL,
    `created_at`  DATETIME         NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_supplier_subs_supplier` (`supplier_id`),
    KEY `idx_supplier_subs_plan`     (`plan_id`),
    CONSTRAINT `fk_supplier_subs_supplier`
        FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_supplier_subs_plan`
        FOREIGN KEY (`plan_id`) REFERENCES `supplier_plans`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `supplier_scorecard` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `supplier_id`      INT UNSIGNED NOT NULL,
    `on_time_delivery` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Percentage',
    `response_rate`    DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Percentage',
    `dispute_rate`     DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Percentage',
    `quality_score`    DECIMAL(3,2) NOT NULL DEFAULT 0.00 COMMENT '0.00 - 5.00',
    `overall_score`    DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    `updated_at`       DATETIME     NOT NULL DEFAULT NOW() ON UPDATE NOW(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_scorecard_supplier` (`supplier_id`),
    CONSTRAINT `fk_scorecard_supplier`
        FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 4: CATEGORIES
-- =============================================================

CREATE TABLE `categories` (
    `id`          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(120)      NOT NULL,
    `slug`        VARCHAR(140)      NOT NULL,
    `parent_id`   SMALLINT UNSIGNED DEFAULT NULL,
    `description` TEXT              DEFAULT NULL,
    `image`       VARCHAR(255)      DEFAULT NULL,
    `icon`        VARCHAR(80)       DEFAULT NULL COMMENT 'Font Awesome class',
    `is_active`   TINYINT(1)        NOT NULL DEFAULT 1,
    `sort_order`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  DATETIME          NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categories_slug`     (`slug`),
    KEY `idx_categories_parent`  (`parent_id`),
    KEY `idx_categories_active`  (`is_active`),
    CONSTRAINT `fk_categories_parent`
        FOREIGN KEY (`parent_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 5: PRODUCTS
-- =============================================================

CREATE TABLE `products` (
    `id`                INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `supplier_id`       INT UNSIGNED      NOT NULL,
    `category_id`       SMALLINT UNSIGNED DEFAULT NULL,
    `name`              VARCHAR(255)      NOT NULL,
    `slug`              VARCHAR(280)      NOT NULL,
    `sku`               VARCHAR(80)       DEFAULT NULL,
    `description`       LONGTEXT          DEFAULT NULL,
    `short_description` TEXT              DEFAULT NULL,
    `price`             DECIMAL(14,2)     NOT NULL,
    `sale_price`        DECIMAL(14,2)     DEFAULT NULL,
    `currency`          VARCHAR(10)       NOT NULL DEFAULT 'USD',
    `min_order_quantity`INT UNSIGNED      NOT NULL DEFAULT 1,
    `stock_quantity`    INT               NOT NULL DEFAULT 0,
    `unit`              VARCHAR(30)       NOT NULL DEFAULT 'piece',
    `weight`            DECIMAL(10,3)     DEFAULT NULL,
    `weight_unit`       ENUM('kg','lb','g','oz') NOT NULL DEFAULT 'kg',
    `is_active`         TINYINT(1)        NOT NULL DEFAULT 1,
    `is_featured`       TINYINT(1)        NOT NULL DEFAULT 0,
    `is_approved`       TINYINT(1)        NOT NULL DEFAULT 0,
    `meta_title`        VARCHAR(191)      DEFAULT NULL,
    `meta_description`  VARCHAR(320)      DEFAULT NULL,
    `created_at`        DATETIME          NOT NULL DEFAULT NOW(),
    `updated_at`        DATETIME          NOT NULL DEFAULT NOW() ON UPDATE NOW(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_products_slug`       (`slug`),
    KEY `idx_products_supplier`  (`supplier_id`),
    KEY `idx_products_category`  (`category_id`),
    KEY `idx_products_active`    (`is_active`),
    KEY `idx_products_featured`  (`is_featured`),
    KEY `idx_products_sku`       (`sku`),
    CONSTRAINT `fk_products_supplier`
        FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_products_category`
        FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_images` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `image_url`  VARCHAR(255) NOT NULL,
    `is_primary` TINYINT(1)   NOT NULL DEFAULT 0,
    `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_product_images_product` (`product_id`),
    CONSTRAINT `fk_product_images_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_variants` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `product_id`     INT UNSIGNED  NOT NULL,
    `name`           VARCHAR(60)   NOT NULL COMMENT 'e.g. Color, Size',
    `value`          VARCHAR(120)  NOT NULL COMMENT 'e.g. Red, XL',
    `price_modifier` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `stock`          INT           NOT NULL DEFAULT 0,
    `sku`            VARCHAR(80)   DEFAULT NULL,
    `created_at`     DATETIME      NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_product_variants_product` (`product_id`),
    CONSTRAINT `fk_product_variants_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_specifications` (
    `id`         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED     NOT NULL,
    `spec_key`   VARCHAR(120)     NOT NULL,
    `spec_value` VARCHAR(255)     NOT NULL,
    `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_product_specs_product` (`product_id`),
    CONSTRAINT `fk_product_specs_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_certifications` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id`   INT UNSIGNED NOT NULL,
    `name`         VARCHAR(120) NOT NULL,
    `document_url` VARCHAR(255) DEFAULT NULL,
    `issued_by`    VARCHAR(120) DEFAULT NULL,
    `expires_at`   DATE         DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_product_certs_product` (`product_id`),
    CONSTRAINT `fk_product_certs_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 6: CARRY SERVICE (PERSONAL COURIER)
-- =============================================================

CREATE TABLE `carriers` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`             INT UNSIGNED NOT NULL,
    `nationality`         VARCHAR(80)  DEFAULT NULL,
    `passport_number`     VARCHAR(40)  DEFAULT NULL,
    `id_type`             VARCHAR(40)  DEFAULT NULL,
    `id_number`           VARCHAR(60)  DEFAULT NULL,
    `is_verified`         TINYINT(1)   NOT NULL DEFAULT 0,
    `verification_status` ENUM('unverified','pending','approved','rejected')
                                       NOT NULL DEFAULT 'unverified',
    `rating`              DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    `total_trips`         INT UNSIGNED NOT NULL DEFAULT 0,
    `earnings_total`      DECIMAL(14,2)NOT NULL DEFAULT 0.00,
    `created_at`          DATETIME     NOT NULL DEFAULT NOW(),
    `updated_at`          DATETIME     NOT NULL DEFAULT NOW() ON UPDATE NOW(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_carriers_user` (`user_id`),
    CONSTRAINT `fk_carriers_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `carrier_verifications` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `carrier_id`     INT UNSIGNED NOT NULL,
    `passport_image` VARCHAR(255) DEFAULT NULL,
    `id_image`       VARCHAR(255) DEFAULT NULL,
    `facial_image`   VARCHAR(255) DEFAULT NULL,
    `selfie_image`   VARCHAR(255) DEFAULT NULL,
    `status`         ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `notes`          TEXT         DEFAULT NULL,
    `reviewed_by`    INT UNSIGNED DEFAULT NULL,
    `reviewed_at`    DATETIME     DEFAULT NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_carrier_verif_carrier`     (`carrier_id`),
    KEY `idx_carrier_verif_reviewed_by` (`reviewed_by`),
    CONSTRAINT `fk_carrier_verif_carrier`
        FOREIGN KEY (`carrier_id`) REFERENCES `carriers`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_carrier_verif_reviewer`
        FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `carry_product_catalog` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(120)  NOT NULL,
    `category`    VARCHAR(80)   NOT NULL,
    `rate_per_kg` DECIMAL(10,2) NOT NULL,
    `description` TEXT          DEFAULT NULL,
    `image`       VARCHAR(255)  DEFAULT NULL,
    `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `carry_requests` (
    `id`                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `carrier_id`         INT UNSIGNED  NOT NULL,
    `flight_number`      VARCHAR(20)   DEFAULT NULL,
    `origin_country`     VARCHAR(80)   NOT NULL,
    `destination_country`VARCHAR(80)   NOT NULL,
    `departure_date`     DATE          NOT NULL,
    `arrival_date`       DATE          NOT NULL,
    `available_weight`   DECIMAL(6,2)  NOT NULL COMMENT 'kg available for carry',
    `ticket_image`       VARCHAR(255)  DEFAULT NULL,
    `status`             ENUM('pending','approved','active','completed','cancelled')
                                       NOT NULL DEFAULT 'pending',
    `created_at`         DATETIME      NOT NULL DEFAULT NOW(),
    `updated_at`         DATETIME      NOT NULL DEFAULT NOW() ON UPDATE NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_carry_req_carrier`     (`carrier_id`),
    KEY `idx_carry_req_departure`   (`departure_date`),
    KEY `idx_carry_req_status`      (`status`),
    CONSTRAINT `fk_carry_req_carrier`
        FOREIGN KEY (`carrier_id`) REFERENCES `carriers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `carry_deliveries` (
    `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `request_id`          INT UNSIGNED  NOT NULL,
    `buyer_id`            INT UNSIGNED  NOT NULL,
    `product_catalog_id`  INT UNSIGNED  DEFAULT NULL,
    `quantity`            INT UNSIGNED  NOT NULL DEFAULT 1,
    `weight`              DECIMAL(8,3)  NOT NULL,
    `total_price`         DECIMAL(12,2) NOT NULL,
    `platform_fee`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `carrier_earnings`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `status`              ENUM('pending','accepted','collected','delivered','disputed')
                                        NOT NULL DEFAULT 'pending',
    `qr_code`             VARCHAR(255)  DEFAULT NULL,
    `delivery_receipt`    VARCHAR(255)  DEFAULT NULL,
    `created_at`          DATETIME      NOT NULL DEFAULT NOW(),
    `updated_at`          DATETIME      NOT NULL DEFAULT NOW() ON UPDATE NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_carry_del_request` (`request_id`),
    KEY `idx_carry_del_buyer`   (`buyer_id`),
    KEY `idx_carry_del_catalog` (`product_catalog_id`),
    KEY `idx_carry_del_status`  (`status`),
    CONSTRAINT `fk_carry_del_request`
        FOREIGN KEY (`request_id`) REFERENCES `carry_requests`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_carry_del_buyer`
        FOREIGN KEY (`buyer_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_carry_del_catalog`
        FOREIGN KEY (`product_catalog_id`) REFERENCES `carry_product_catalog`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `carrier_earnings` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `carrier_id`  INT UNSIGNED  NOT NULL,
    `delivery_id` INT UNSIGNED  NOT NULL,
    `amount`      DECIMAL(12,2) NOT NULL,
    `bonus`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `platform_fee`DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `net_amount`  DECIMAL(12,2) NOT NULL,
    `status`      ENUM('pending','paid') NOT NULL DEFAULT 'pending',
    `paid_at`     DATETIME      DEFAULT NULL,
    `created_at`  DATETIME      NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_carrier_earnings_carrier`  (`carrier_id`),
    KEY `idx_carrier_earnings_delivery` (`delivery_id`),
    CONSTRAINT `fk_carrier_earnings_carrier`
        FOREIGN KEY (`carrier_id`) REFERENCES `carriers`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_carrier_earnings_delivery`
        FOREIGN KEY (`delivery_id`) REFERENCES `carry_deliveries`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 7: ORDERS
-- =============================================================

CREATE TABLE `orders` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `buyer_id`         INT UNSIGNED  NOT NULL,
    `supplier_id`      INT UNSIGNED  NOT NULL,
    `order_number`     VARCHAR(30)   NOT NULL,
    `status`           ENUM('pending','confirmed','processing','shipped','delivered',
                            'cancelled','disputed','refunded')
                                     NOT NULL DEFAULT 'pending',
    `payment_status`   ENUM('unpaid','paid','refunded','partial')
                                     NOT NULL DEFAULT 'unpaid',
    `subtotal`         DECIMAL(14,2) NOT NULL,
    `discount`         DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `tax`              DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `shipping_cost`    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `total`            DECIMAL(14,2) NOT NULL,
    `currency`         VARCHAR(10)   NOT NULL DEFAULT 'USD',
    `shipping_address` JSON          DEFAULT NULL,
    `notes`            TEXT          DEFAULT NULL,
    `created_at`       DATETIME      NOT NULL DEFAULT NOW(),
    `updated_at`       DATETIME      NOT NULL DEFAULT NOW() ON UPDATE NOW(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_orders_number`    (`order_number`),
    KEY `idx_orders_buyer`           (`buyer_id`),
    KEY `idx_orders_supplier`        (`supplier_id`),
    KEY `idx_orders_status`          (`status`),
    KEY `idx_orders_payment_status`  (`payment_status`),
    KEY `idx_orders_created`         (`created_at`),
    CONSTRAINT `fk_orders_buyer`
        FOREIGN KEY (`buyer_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_orders_supplier`
        FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_items` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `order_id`   INT UNSIGNED  NOT NULL,
    `product_id` INT UNSIGNED  DEFAULT NULL,
    `variant_id` INT UNSIGNED  DEFAULT NULL,
    `name`       VARCHAR(255)  NOT NULL,
    `sku`        VARCHAR(80)   DEFAULT NULL,
    `price`      DECIMAL(14,2) NOT NULL,
    `quantity`   INT UNSIGNED  NOT NULL DEFAULT 1,
    `total`      DECIMAL(14,2) NOT NULL,
    `created_at` DATETIME      NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_order_items_order`   (`order_id`),
    KEY `idx_order_items_product` (`product_id`),
    CONSTRAINT `fk_order_items_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_order_items_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_tracking` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`    INT UNSIGNED NOT NULL,
    `status`      VARCHAR(80)  NOT NULL,
    `location`    VARCHAR(180) DEFAULT NULL,
    `description` TEXT         DEFAULT NULL,
    `created_by`  INT UNSIGNED DEFAULT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_order_tracking_order` (`order_id`),
    CONSTRAINT `fk_order_tracking_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_order_tracking_user`
        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_disputes` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`    INT UNSIGNED NOT NULL,
    `raised_by`   INT UNSIGNED NOT NULL,
    `reason`      VARCHAR(120) NOT NULL,
    `description` TEXT         NOT NULL,
    `status`      ENUM('open','in_review','resolved','closed') NOT NULL DEFAULT 'open',
    `resolution`  TEXT         DEFAULT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT NOW(),
    `updated_at`  DATETIME     NOT NULL DEFAULT NOW() ON UPDATE NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_order_disputes_order`  (`order_id`),
    KEY `idx_order_disputes_raiser` (`raised_by`),
    CONSTRAINT `fk_order_disputes_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_order_disputes_user`
        FOREIGN KEY (`raised_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 8: CART & WISHLIST
-- =============================================================

CREATE TABLE `cart` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED DEFAULT NULL,
    `session_id` VARCHAR(128) DEFAULT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT NOW(),
    `updated_at` DATETIME     NOT NULL DEFAULT NOW() ON UPDATE NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_cart_user`    (`user_id`),
    KEY `idx_cart_session` (`session_id`),
    CONSTRAINT `fk_cart_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cart_items` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `cart_id`    INT UNSIGNED  NOT NULL,
    `product_id` INT UNSIGNED  NOT NULL,
    `variant_id` INT UNSIGNED  DEFAULT NULL,
    `quantity`   INT UNSIGNED  NOT NULL DEFAULT 1,
    `price`      DECIMAL(14,2) NOT NULL,
    `created_at` DATETIME      NOT NULL DEFAULT NOW(),
    `updated_at` DATETIME      NOT NULL DEFAULT NOW() ON UPDATE NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_cart_items_cart`    (`cart_id`),
    KEY `idx_cart_items_product` (`product_id`),
    CONSTRAINT `fk_cart_items_cart`
        FOREIGN KEY (`cart_id`) REFERENCES `cart`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cart_items_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `wishlist` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wishlist_user_product` (`user_id`, `product_id`),
    KEY `idx_wishlist_product` (`product_id`),
    CONSTRAINT `fk_wishlist_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_wishlist_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 9: PAYMENTS
-- =============================================================

CREATE TABLE `payments` (
    `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `order_id`          INT UNSIGNED  DEFAULT NULL,
    `user_id`           INT UNSIGNED  NOT NULL,
    `amount`            DECIMAL(14,2) NOT NULL,
    `currency`          VARCHAR(10)   NOT NULL DEFAULT 'USD',
    `gateway`           ENUM('stripe','paypal','bkash','nagad','bank_transfer','escrow')
                                      NOT NULL,
    `gateway_reference` VARCHAR(255)  DEFAULT NULL,
    `status`            ENUM('pending','completed','failed','refunded')
                                      NOT NULL DEFAULT 'pending',
    `metadata`          JSON          DEFAULT NULL,
    `created_at`        DATETIME      NOT NULL DEFAULT NOW(),
    `updated_at`        DATETIME      NOT NULL DEFAULT NOW() ON UPDATE NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_payments_order`  (`order_id`),
    KEY `idx_payments_user`   (`user_id`),
    KEY `idx_payments_status` (`status`),
    CONSTRAINT `fk_payments_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_payments_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `refunds` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `payment_id` INT UNSIGNED  NOT NULL,
    `order_id`   INT UNSIGNED  DEFAULT NULL,
    `amount`     DECIMAL(14,2) NOT NULL,
    `reason`     TEXT          DEFAULT NULL,
    `status`     ENUM('pending','approved','processed','rejected') NOT NULL DEFAULT 'pending',
    `created_at` DATETIME      NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_refunds_payment` (`payment_id`),
    KEY `idx_refunds_order`   (`order_id`),
    CONSTRAINT `fk_refunds_payment`
        FOREIGN KEY (`payment_id`) REFERENCES `payments`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_refunds_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `escrow_transactions` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `order_id`     INT UNSIGNED  NOT NULL,
    `buyer_id`     INT UNSIGNED  NOT NULL,
    `supplier_id`  INT UNSIGNED  NOT NULL,
    `amount`       DECIMAL(14,2) NOT NULL,
    `status`       ENUM('held','released','disputed','refunded') NOT NULL DEFAULT 'held',
    `release_date` DATETIME      DEFAULT NULL,
    `created_at`   DATETIME      NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_escrow_order`    (`order_id`),
    KEY `idx_escrow_buyer`    (`buyer_id`),
    KEY `idx_escrow_supplier` (`supplier_id`),
    CONSTRAINT `fk_escrow_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_escrow_buyer`
        FOREIGN KEY (`buyer_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_escrow_supplier`
        FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payouts` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED  NOT NULL,
    `amount`          DECIMAL(14,2) NOT NULL,
    `method`          VARCHAR(60)   NOT NULL,
    `account_details` JSON          DEFAULT NULL,
    `status`          ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
    `created_at`      DATETIME      NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_payouts_user`   (`user_id`),
    KEY `idx_payouts_status` (`status`),
    CONSTRAINT `fk_payouts_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 10: PARCELS & SHIPPING
-- =============================================================

CREATE TABLE `parcels` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sender_id`          INT UNSIGNED NOT NULL,
    `reference_number`   VARCHAR(30)  NOT NULL,
    `origin_country`     VARCHAR(80)  NOT NULL,
    `destination_country`VARCHAR(80)  NOT NULL,
    `recipient_name`     VARCHAR(120) NOT NULL,
    `recipient_phone`    VARCHAR(30)  DEFAULT NULL,
    `recipient_address`  JSON         DEFAULT NULL,
    `weight`             DECIMAL(8,3) NOT NULL COMMENT 'kg',
    `dimensions`         JSON         DEFAULT NULL COMMENT '{l,w,h in cm}',
    `service_type`       ENUM('standard','express','economy') NOT NULL DEFAULT 'standard',
    `declared_value`     DECIMAL(12,2)DEFAULT NULL,
    `insurance`          TINYINT(1)   NOT NULL DEFAULT 0,
    `status`             ENUM('pending','received','processing','in_transit',
                               'customs','delivered','returned')
                                      NOT NULL DEFAULT 'pending',
    `tracking_events`    JSON         DEFAULT NULL,
    `created_at`         DATETIME     NOT NULL DEFAULT NOW(),
    `updated_at`         DATETIME     NOT NULL DEFAULT NOW() ON UPDATE NOW(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_parcels_ref`      (`reference_number`),
    KEY `idx_parcels_sender`  (`sender_id`),
    KEY `idx_parcels_status`  (`status`),
    CONSTRAINT `fk_parcels_sender`
        FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `shipping_zones` (
    `id`        SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`      VARCHAR(120)      NOT NULL,
    `countries` JSON              NOT NULL COMMENT 'Array of ISO country codes',
    `is_active` TINYINT(1)        NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `shipping_rates` (
    `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `zone_id`      SMALLINT UNSIGNED NOT NULL,
    `weight_from`  DECIMAL(8,3)     NOT NULL DEFAULT 0.000,
    `weight_to`    DECIMAL(8,3)     NOT NULL,
    `base_price`   DECIMAL(10,2)    NOT NULL,
    `price_per_kg` DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
    `service_type` ENUM('standard','express','economy') NOT NULL DEFAULT 'standard',
    `is_active`    TINYINT(1)       NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_shipping_rates_zone` (`zone_id`),
    CONSTRAINT `fk_shipping_rates_zone`
        FOREIGN KEY (`zone_id`) REFERENCES `shipping_zones`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 11: RFQ
-- =============================================================

CREATE TABLE `rfq` (
    `id`            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `buyer_id`      INT UNSIGNED      NOT NULL,
    `title`         VARCHAR(255)      NOT NULL,
    `description`   TEXT              NOT NULL,
    `category_id`   SMALLINT UNSIGNED DEFAULT NULL,
    `quantity`      INT UNSIGNED      NOT NULL DEFAULT 1,
    `target_price`  DECIMAL(14,2)     DEFAULT NULL,
    `delivery_date` DATE              DEFAULT NULL,
    `status`        ENUM('open','quoted','accepted','closed') NOT NULL DEFAULT 'open',
    `created_at`    DATETIME          NOT NULL DEFAULT NOW(),
    `updated_at`    DATETIME          NOT NULL DEFAULT NOW() ON UPDATE NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_rfq_buyer`    (`buyer_id`),
    KEY `idx_rfq_category` (`category_id`),
    KEY `idx_rfq_status`   (`status`),
    CONSTRAINT `fk_rfq_buyer`
        FOREIGN KEY (`buyer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rfq_category`
        FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rfq_quotes` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `rfq_id`      INT UNSIGNED  NOT NULL,
    `supplier_id` INT UNSIGNED  NOT NULL,
    `price`       DECIMAL(14,2) NOT NULL,
    `unit`        VARCHAR(30)   NOT NULL DEFAULT 'piece',
    `lead_time`   VARCHAR(60)   DEFAULT NULL,
    `notes`       TEXT          DEFAULT NULL,
    `status`      ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
    `created_at`  DATETIME      NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_rfq_quotes_rfq`      (`rfq_id`),
    KEY `idx_rfq_quotes_supplier` (`supplier_id`),
    CONSTRAINT `fk_rfq_quotes_rfq`
        FOREIGN KEY (`rfq_id`) REFERENCES `rfq`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rfq_quotes_supplier`
        FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 12: REVIEWS
-- =============================================================

CREATE TABLE `reviews` (
    `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED    NOT NULL,
    `user_id`    INT UNSIGNED    NOT NULL,
    `order_id`   INT UNSIGNED    DEFAULT NULL,
    `rating`     TINYINT UNSIGNED NOT NULL,
    `title`      VARCHAR(180)    DEFAULT NULL,
    `content`    TEXT            DEFAULT NULL,
    `images`     JSON            DEFAULT NULL,
    `status`     ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `created_at` DATETIME        NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_reviews_product` (`product_id`),
    KEY `idx_reviews_user`    (`user_id`),
    KEY `idx_reviews_order`   (`order_id`),
    KEY `idx_reviews_status`  (`status`),
    CONSTRAINT `fk_reviews_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reviews_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reviews_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `review_votes` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `review_id`  INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NOT NULL,
    `is_helpful` TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` DATETIME     NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_review_votes_review_user` (`review_id`, `user_id`),
    KEY `idx_review_votes_user` (`user_id`),
    CONSTRAINT `fk_review_votes_review`
        FOREIGN KEY (`review_id`) REFERENCES `reviews`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_review_votes_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 13: PRICING & COMMISSIONS
-- =============================================================

CREATE TABLE `pricing_rules` (
    `id`           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `name`         VARCHAR(120)      NOT NULL,
    `type`         ENUM('commission','markup','shipping','inspection') NOT NULL,
    `rule_type`    ENUM('global','category','tier') NOT NULL DEFAULT 'global',
    `category_id`  SMALLINT UNSIGNED DEFAULT NULL,
    `min_value`    DECIMAL(14,2)     DEFAULT NULL,
    `max_value`    DECIMAL(14,2)     DEFAULT NULL,
    `rate`         DECIMAL(5,4)      DEFAULT NULL COMMENT '0.0500 = 5%',
    `fixed_amount` DECIMAL(14,2)     DEFAULT NULL,
    `is_active`    TINYINT(1)        NOT NULL DEFAULT 1,
    `created_at`   DATETIME          NOT NULL DEFAULT NOW(),
    `updated_at`   DATETIME          NOT NULL DEFAULT NOW() ON UPDATE NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_pricing_rules_type`     (`type`),
    KEY `idx_pricing_rules_category` (`category_id`),
    CONSTRAINT `fk_pricing_rules_category`
        FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `commission_tiers` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `min_order_value`  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `max_order_value`  DECIMAL(14,2) DEFAULT NULL,
    `commission_rate`  DECIMAL(5,4)  NOT NULL COMMENT '0.05 = 5%',
    `min_commission`   DECIMAL(14,2) DEFAULT NULL,
    `max_commission`   DECIMAL(14,2) DEFAULT NULL,
    `created_at`       DATETIME      NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_commission_tiers_min` (`min_order_value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 14: DROPSHIPPING
-- =============================================================

CREATE TABLE `dropship_products` (
    `id`                INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `source`            ENUM('alibaba','1688','aliexpress') NOT NULL,
    `source_product_id` VARCHAR(120)      NOT NULL,
    `name`              VARCHAR(255)      NOT NULL,
    `description`       LONGTEXT          DEFAULT NULL,
    `original_price`    DECIMAL(14,2)     NOT NULL,
    `selling_price`     DECIMAL(14,2)     NOT NULL,
    `markup_rate`       DECIMAL(5,4)      NOT NULL DEFAULT 0.3000,
    `images`            JSON              DEFAULT NULL,
    `category_id`       SMALLINT UNSIGNED DEFAULT NULL,
    `supplier_info`     JSON              DEFAULT NULL,
    `is_active`         TINYINT(1)        NOT NULL DEFAULT 1,
    `last_synced`       DATETIME          DEFAULT NULL,
    `created_at`        DATETIME          NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_dropship_source`    (`source`, `source_product_id`),
    KEY `idx_dropship_category`  (`category_id`),
    KEY `idx_dropship_active`    (`is_active`),
    CONSTRAINT `fk_dropship_products_category`
        FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `dropship_markup_rules` (
    `id`          INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(120)      NOT NULL,
    `type`        ENUM('global','category','price_range') NOT NULL DEFAULT 'global',
    `category_id` SMALLINT UNSIGNED DEFAULT NULL,
    `min_price`   DECIMAL(14,2)     DEFAULT NULL,
    `max_price`   DECIMAL(14,2)     DEFAULT NULL,
    `markup_rate` DECIMAL(5,4)      NOT NULL COMMENT '0.30 = 30%',
    `min_profit`  DECIMAL(14,2)     DEFAULT NULL,
    `is_active`   TINYINT(1)        NOT NULL DEFAULT 1,
    `created_at`  DATETIME          NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_dropship_markup_category`
        FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `dropship_orders` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`            INT UNSIGNED NOT NULL,
    `dropship_product_id` INT UNSIGNED NOT NULL,
    `source_order_id`     VARCHAR(120) DEFAULT NULL,
    `status`              VARCHAR(60)  NOT NULL DEFAULT 'pending',
    `synced_at`           DATETIME     DEFAULT NULL,
    `created_at`          DATETIME     NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_dropship_orders_order`   (`order_id`),
    KEY `idx_dropship_orders_product` (`dropship_product_id`),
    CONSTRAINT `fk_dropship_orders_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_dropship_orders_product`
        FOREIGN KEY (`dropship_product_id`) REFERENCES `dropship_products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 15: CAMPAIGNS & MARKETING
-- =============================================================

CREATE TABLE `campaigns` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(180)  NOT NULL,
    `type`          ENUM('flash_sale','coupon','bundle','referral') NOT NULL,
    `description`   TEXT          DEFAULT NULL,
    `start_date`    DATETIME      NOT NULL,
    `end_date`      DATETIME      NOT NULL,
    `discount_type` ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    `discount_value`DECIMAL(10,2) NOT NULL,
    `min_order`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `max_discount`  DECIMAL(10,2) DEFAULT NULL,
    `usage_limit`   INT UNSIGNED  DEFAULT NULL,
    `used_count`    INT UNSIGNED  NOT NULL DEFAULT 0,
    `status`        ENUM('draft','active','expired') NOT NULL DEFAULT 'draft',
    `created_at`    DATETIME      NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_campaigns_status`  (`status`),
    KEY `idx_campaigns_dates`   (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `coupons` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `campaign_id` INT UNSIGNED  DEFAULT NULL,
    `code`        VARCHAR(40)   NOT NULL,
    `type`        ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    `value`       DECIMAL(10,2) NOT NULL,
    `min_order`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `max_uses`    INT UNSIGNED  DEFAULT NULL,
    `used_count`  INT UNSIGNED  NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
    `expires_at`  DATETIME      DEFAULT NULL,
    `created_at`  DATETIME      NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_coupons_code` (`code`),
    KEY `idx_coupons_campaign` (`campaign_id`),
    CONSTRAINT `fk_coupons_campaign`
        FOREIGN KEY (`campaign_id`) REFERENCES `campaigns`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `banners` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`      VARCHAR(180) NOT NULL,
    `image_url`  VARCHAR(255) NOT NULL,
    `link_url`   VARCHAR(255) DEFAULT NULL,
    `position`   ENUM('hero','sidebar','popup','strip') NOT NULL DEFAULT 'hero',
    `page`       VARCHAR(80)  DEFAULT NULL,
    `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
    `start_date` DATETIME     DEFAULT NULL,
    `end_date`   DATETIME     DEFAULT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_banners_position` (`position`),
    KEY `idx_banners_active`   (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `advertisements` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(180)  NOT NULL,
    `type`        ENUM('featured_product','banner','cpc','email_blast') NOT NULL,
    `product_id`  INT UNSIGNED  DEFAULT NULL,
    `supplier_id` INT UNSIGNED  DEFAULT NULL,
    `cost`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `clicks`      INT UNSIGNED  NOT NULL DEFAULT 0,
    `impressions` INT UNSIGNED  NOT NULL DEFAULT 0,
    `status`      VARCHAR(30)   NOT NULL DEFAULT 'active',
    `start_date`  DATETIME      NOT NULL,
    `end_date`    DATETIME      NOT NULL,
    `created_at`  DATETIME      NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_ads_product`  (`product_id`),
    KEY `idx_ads_supplier` (`supplier_id`),
    CONSTRAINT `fk_ads_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_ads_supplier`
        FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 16: API PLATFORM
-- =============================================================

CREATE TABLE `api_plans` (
    `id`                   TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`                 VARCHAR(60)      NOT NULL,
    `requests_per_month`   INT UNSIGNED     NOT NULL DEFAULT 10000,
    `price_monthly`        DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
    `features`             JSON             DEFAULT NULL,
    `is_active`            TINYINT(1)       NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `api_clients` (
    `id`              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED     NOT NULL,
    `plan_id`         TINYINT UNSIGNED DEFAULT NULL,
    `app_name`        VARCHAR(120)     NOT NULL,
    `app_description` TEXT             DEFAULT NULL,
    `website`         VARCHAR(255)     DEFAULT NULL,
    `status`          ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
    `created_at`      DATETIME         NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_api_clients_user` (`user_id`),
    KEY `idx_api_clients_plan` (`plan_id`),
    CONSTRAINT `fk_api_clients_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_api_clients_plan`
        FOREIGN KEY (`plan_id`) REFERENCES `api_plans`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `api_keys` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_id`   INT UNSIGNED NOT NULL,
    `key_hash`    VARCHAR(128) NOT NULL COMMENT 'SHA-256 of actual key',
    `prefix`      VARCHAR(10)  NOT NULL COMMENT 'First chars of key, for display',
    `permissions` JSON         DEFAULT NULL,
    `last_used`   DATETIME     DEFAULT NULL,
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  DATETIME     NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_api_keys_hash`   (`key_hash`),
    KEY `idx_api_keys_client` (`client_id`),
    CONSTRAINT `fk_api_keys_client`
        FOREIGN KEY (`client_id`) REFERENCES `api_clients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `api_usage_logs` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_id`     INT UNSIGNED    NOT NULL,
    `key_id`        INT UNSIGNED    DEFAULT NULL,
    `endpoint`      VARCHAR(255)    NOT NULL,
    `method`        VARCHAR(10)     NOT NULL DEFAULT 'GET',
    `status_code`   SMALLINT UNSIGNED NOT NULL,
    `response_time` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'ms',
    `ip_address`    VARCHAR(45)     DEFAULT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_api_logs_client`   (`client_id`),
    KEY `idx_api_logs_key`      (`key_id`),
    KEY `idx_api_logs_created`  (`created_at`),
    CONSTRAINT `fk_api_logs_client`
        FOREIGN KEY (`client_id`) REFERENCES `api_clients`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_api_logs_key`
        FOREIGN KEY (`key_id`) REFERENCES `api_keys`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 17: QUALITY INSPECTIONS
-- =============================================================

CREATE TABLE `inspectors` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`          INT UNSIGNED NOT NULL,
    `name`             VARCHAR(120) NOT NULL,
    `location`         VARCHAR(120) DEFAULT NULL,
    `specializations`  JSON         DEFAULT NULL,
    `rating`           DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    `is_available`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`       DATETIME     NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_inspectors_user` (`user_id`),
    CONSTRAINT `fk_inspectors_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inspections` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `order_id`       INT UNSIGNED  DEFAULT NULL,
    `requested_by`   INT UNSIGNED  NOT NULL,
    `type`           ENUM('pre_production','during_production','pre_shipment','full_audit')
                                   NOT NULL DEFAULT 'pre_shipment',
    `status`         ENUM('requested','assigned','in_progress','completed','report_ready')
                                   NOT NULL DEFAULT 'requested',
    `scheduled_date` DATE          DEFAULT NULL,
    `completed_date` DATE          DEFAULT NULL,
    `price`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `rush_fee`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `created_at`     DATETIME      NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_inspections_order`     (`order_id`),
    KEY `idx_inspections_requester` (`requested_by`),
    KEY `idx_inspections_status`    (`status`),
    CONSTRAINT `fk_inspections_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_inspections_requester`
        FOREIGN KEY (`requested_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inspection_reports` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `inspection_id` INT UNSIGNED NOT NULL,
    `inspector_id`  INT UNSIGNED NOT NULL,
    `findings`      JSON         DEFAULT NULL,
    `pass_fail`     ENUM('pass','fail','conditional') NOT NULL DEFAULT 'pass',
    `report_url`    VARCHAR(255) DEFAULT NULL,
    `photos`        JSON         DEFAULT NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_insp_reports_inspection` (`inspection_id`),
    KEY `idx_insp_reports_inspector`  (`inspector_id`),
    CONSTRAINT `fk_insp_reports_inspection`
        FOREIGN KEY (`inspection_id`) REFERENCES `inspections`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_insp_reports_inspector`
        FOREIGN KEY (`inspector_id`) REFERENCES `inspectors`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 18: LIVE STREAMING
-- =============================================================

CREATE TABLE `livestreams` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `supplier_id`  INT UNSIGNED NOT NULL,
    `title`        VARCHAR(255) NOT NULL,
    `description`  TEXT         DEFAULT NULL,
    `thumbnail`    VARCHAR(255) DEFAULT NULL,
    `stream_key`   VARCHAR(128) DEFAULT NULL,
    `stream_url`   VARCHAR(255) DEFAULT NULL,
    `status`       ENUM('scheduled','live','ended') NOT NULL DEFAULT 'scheduled',
    `scheduled_at` DATETIME     DEFAULT NULL,
    `started_at`   DATETIME     DEFAULT NULL,
    `ended_at`     DATETIME     DEFAULT NULL,
    `viewer_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`   DATETIME     NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_livestreams_supplier` (`supplier_id`),
    KEY `idx_livestreams_status`   (`status`),
    CONSTRAINT `fk_livestreams_supplier`
        FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `livestream_products` (
    `id`               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `livestream_id`    INT UNSIGNED     NOT NULL,
    `product_id`       INT UNSIGNED     NOT NULL,
    `discount_percent` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `sort_order`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_ls_products_stream`  (`livestream_id`),
    KEY `idx_ls_products_product` (`product_id`),
    CONSTRAINT `fk_ls_products_stream`
        FOREIGN KEY (`livestream_id`) REFERENCES `livestreams`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ls_products_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `livestream_chat` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `livestream_id` INT UNSIGNED    NOT NULL,
    `user_id`       INT UNSIGNED    NOT NULL,
    `message`       TEXT            NOT NULL,
    `type`          ENUM('message','purchase','join') NOT NULL DEFAULT 'message',
    `created_at`    DATETIME        NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_ls_chat_stream`  (`livestream_id`),
    KEY `idx_ls_chat_user`    (`user_id`),
    KEY `idx_ls_chat_created` (`created_at`),
    CONSTRAINT `fk_ls_chat_stream`
        FOREIGN KEY (`livestream_id`) REFERENCES `livestreams`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ls_chat_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 19: COMMUNICATION
-- =============================================================

CREATE TABLE `conversations` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `buyer_id`        INT UNSIGNED NOT NULL,
    `supplier_id`     INT UNSIGNED NOT NULL,
    `product_id`      INT UNSIGNED DEFAULT NULL,
    `order_id`        INT UNSIGNED DEFAULT NULL,
    `last_message_at` DATETIME     DEFAULT NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_conversations_buyer`    (`buyer_id`),
    KEY `idx_conversations_supplier` (`supplier_id`),
    KEY `idx_conversations_product`  (`product_id`),
    KEY `idx_conversations_order`    (`order_id`),
    CONSTRAINT `fk_conversations_buyer`
        FOREIGN KEY (`buyer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_conversations_supplier`
        FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_conversations_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_conversations_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `messages` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `conversation_id`    INT UNSIGNED    NOT NULL,
    `sender_id`          INT UNSIGNED    NOT NULL,
    `content`            TEXT            NOT NULL,
    `attachment_url`     VARCHAR(255)    DEFAULT NULL,
    `is_read`            TINYINT(1)      NOT NULL DEFAULT 0,
    `translated_content` TEXT            DEFAULT NULL,
    `created_at`         DATETIME        NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_messages_conversation` (`conversation_id`),
    KEY `idx_messages_sender`       (`sender_id`),
    KEY `idx_messages_created`      (`created_at`),
    CONSTRAINT `fk_messages_conversation`
        FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_messages_sender`
        FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED    NOT NULL,
    `type`       VARCHAR(60)     NOT NULL,
    `title`      VARCHAR(180)    NOT NULL,
    `message`    TEXT            NOT NULL,
    `data`       JSON            DEFAULT NULL,
    `is_read`    TINYINT(1)      NOT NULL DEFAULT 0,
    `link`       VARCHAR(255)    DEFAULT NULL,
    `created_at` DATETIME        NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_notifications_user`    (`user_id`),
    KEY `idx_notifications_is_read` (`is_read`),
    KEY `idx_notifications_created` (`created_at`),
    CONSTRAINT `fk_notifications_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 20: CMS & BLOG
-- =============================================================

CREATE TABLE `blog_posts` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `author_id`      INT UNSIGNED NOT NULL,
    `title`          VARCHAR(255) NOT NULL,
    `slug`           VARCHAR(280) NOT NULL,
    `content`        LONGTEXT     NOT NULL,
    `excerpt`        TEXT         DEFAULT NULL,
    `featured_image` VARCHAR(255) DEFAULT NULL,
    `category`       VARCHAR(80)  DEFAULT NULL,
    `tags`           JSON         DEFAULT NULL,
    `status`         ENUM('draft','published') NOT NULL DEFAULT 'draft',
    `published_at`   DATETIME     DEFAULT NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_blog_posts_slug`   (`slug`),
    KEY `idx_blog_posts_author`  (`author_id`),
    KEY `idx_blog_posts_status`  (`status`),
    CONSTRAINT `fk_blog_posts_author`
        FOREIGN KEY (`author_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pages` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`            VARCHAR(255) NOT NULL,
    `slug`             VARCHAR(280) NOT NULL,
    `content`          LONGTEXT     NOT NULL,
    `meta_title`       VARCHAR(191) DEFAULT NULL,
    `meta_description` VARCHAR(320) DEFAULT NULL,
    `is_published`     TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`       DATETIME     NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pages_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 21: SUPPORT
-- =============================================================

CREATE TABLE `support_tickets` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `subject`    VARCHAR(255) NOT NULL,
    `category`   VARCHAR(80)  NOT NULL DEFAULT 'general',
    `priority`   ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    `status`     ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
    `created_at` DATETIME     NOT NULL DEFAULT NOW(),
    `updated_at` DATETIME     NOT NULL DEFAULT NOW() ON UPDATE NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_tickets_user`     (`user_id`),
    KEY `idx_tickets_status`   (`status`),
    KEY `idx_tickets_priority` (`priority`),
    CONSTRAINT `fk_tickets_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ticket_replies` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ticket_id`   INT UNSIGNED NOT NULL,
    `user_id`     INT UNSIGNED NOT NULL,
    `message`     TEXT         NOT NULL,
    `attachments` JSON         DEFAULT NULL,
    `is_staff`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`  DATETIME     NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_ticket_replies_ticket` (`ticket_id`),
    KEY `idx_ticket_replies_user`   (`user_id`),
    CONSTRAINT `fk_ticket_replies_ticket`
        FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ticket_replies_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `faq` (
    `id`         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `question`   VARCHAR(255)     NOT NULL,
    `answer`     TEXT             NOT NULL,
    `category`   VARCHAR(80)      NOT NULL DEFAULT 'general',
    `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `is_active`  TINYINT(1)       NOT NULL DEFAULT 1,
    `created_at` DATETIME         NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_faq_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 22: ADMIN & SYSTEM
-- =============================================================

CREATE TABLE `platform_settings` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key`   VARCHAR(100) NOT NULL,
    `setting_value` TEXT         DEFAULT NULL,
    `description`   VARCHAR(255) DEFAULT NULL,
    `updated_at`    DATETIME     NOT NULL DEFAULT NOW() ON UPDATE NOW(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_platform_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `feature_toggles` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `feature_key`  VARCHAR(100) NOT NULL,
    `feature_name` VARCHAR(120) NOT NULL,
    `description`  VARCHAR(255) DEFAULT NULL,
    `is_enabled`   TINYINT(1)   NOT NULL DEFAULT 1,
    `updated_at`   DATETIME     NOT NULL DEFAULT NOW() ON UPDATE NOW(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_feature_toggles_key` (`feature_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `activity_logs` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED    DEFAULT NULL,
    `action`      VARCHAR(80)     NOT NULL,
    `description` TEXT            DEFAULT NULL,
    `ip_address`  VARCHAR(45)     DEFAULT NULL,
    `user_agent`  TEXT            DEFAULT NULL,
    `created_at`  DATETIME        NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_activity_logs_user`    (`user_id`),
    KEY `idx_activity_logs_action`  (`action`),
    KEY `idx_activity_logs_created` (`created_at`),
    CONSTRAINT `fk_activity_logs_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_roles` (
    `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(60)      NOT NULL,
    `permissions` JSON             NOT NULL,
    `created_at`  DATETIME         NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_admin_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `email_templates` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(80)  NOT NULL,
    `subject`    VARCHAR(255) NOT NULL,
    `body`       LONGTEXT     NOT NULL,
    `variables`  JSON         DEFAULT NULL COMMENT 'Available template variables',
    `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` DATETIME     NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email_templates_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 23: LOYALTY
-- =============================================================

CREATE TABLE `loyalty_tiers` (
    `id`            TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(60)      NOT NULL,
    `min_points`    INT UNSIGNED     NOT NULL DEFAULT 0,
    `discount_rate` DECIMAL(5,4)     NOT NULL DEFAULT 0.0000,
    `benefits`      JSON             DEFAULT NULL,
    `badge_image`   VARCHAR(255)     DEFAULT NULL,
    `created_at`    DATETIME         NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `loyalty_points` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED    NOT NULL,
    `points`      INT             NOT NULL COMMENT 'Negative for redemption',
    `type`        ENUM('earned','redeemed','expired') NOT NULL DEFAULT 'earned',
    `reference`   VARCHAR(100)    DEFAULT NULL,
    `description` VARCHAR(255)    DEFAULT NULL,
    `created_at`  DATETIME        NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_loyalty_points_user`    (`user_id`),
    KEY `idx_loyalty_points_created` (`created_at`),
    CONSTRAINT `fk_loyalty_points_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 24: TRADE SHOWS
-- =============================================================

CREATE TABLE `trade_shows` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(180) NOT NULL,
    `description` TEXT         DEFAULT NULL,
    `start_date`  DATE         NOT NULL,
    `end_date`    DATE         NOT NULL,
    `location`    VARCHAR(180) DEFAULT NULL COMMENT 'Virtual URL or physical address',
    `banner`      VARCHAR(255) DEFAULT NULL,
    `status`      VARCHAR(30)  NOT NULL DEFAULT 'upcoming',
    `created_at`  DATETIME     NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_trade_shows_dates` (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `virtual_booths` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `trade_show_id` INT UNSIGNED NOT NULL,
    `supplier_id`   INT UNSIGNED NOT NULL,
    `booth_number`  VARCHAR(20)  DEFAULT NULL,
    `description`   TEXT         DEFAULT NULL,
    `products`      JSON         DEFAULT NULL COMMENT 'Array of product IDs',
    `created_at`    DATETIME     NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_virtual_booths_show`     (`trade_show_id`),
    KEY `idx_virtual_booths_supplier` (`supplier_id`),
    CONSTRAINT `fk_virtual_booths_show`
        FOREIGN KEY (`trade_show_id`) REFERENCES `trade_shows`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_virtual_booths_supplier`
        FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 25: AI LOGS
-- =============================================================

CREATE TABLE `ai_chat_history` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED    DEFAULT NULL,
    `session_id` VARCHAR(80)     NOT NULL,
    `role`       ENUM('user','assistant') NOT NULL,
    `content`    TEXT            NOT NULL,
    `created_at` DATETIME        NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_ai_chat_user`    (`user_id`),
    KEY `idx_ai_chat_session` (`session_id`),
    KEY `idx_ai_chat_created` (`created_at`),
    CONSTRAINT `fk_ai_chat_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ai_recommendations_log` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED DEFAULT NULL,
    `product_ids` JSON         NOT NULL,
    `algorithm`   VARCHAR(80)  NOT NULL DEFAULT 'collaborative_filter',
    `created_at`  DATETIME     NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_ai_recs_user`    (`user_id`),
    KEY `idx_ai_recs_created` (`created_at`),
    CONSTRAINT `fk_ai_recs_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ai_fraud_alerts` (
    `id`             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`        INT UNSIGNED    DEFAULT NULL,
    `order_id`       INT UNSIGNED    DEFAULT NULL,
    `risk_score`     TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0-100',
    `risk_level`     ENUM('low','medium','high') NOT NULL DEFAULT 'low',
    `flags`          JSON            DEFAULT NULL,
    `recommendation` TEXT            DEFAULT NULL,
    `reviewed`       TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at`     DATETIME        NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_ai_fraud_user`    (`user_id`),
    KEY `idx_ai_fraud_order`   (`order_id`),
    KEY `idx_ai_fraud_level`   (`risk_level`),
    CONSTRAINT `fk_ai_fraud_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_ai_fraud_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 26: SOURCING
-- =============================================================

CREATE TABLE `sourcing_requests` (
    `id`          INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `buyer_id`    INT UNSIGNED      NOT NULL,
    `title`       VARCHAR(255)      NOT NULL,
    `description` TEXT              NOT NULL,
    `category_id` SMALLINT UNSIGNED DEFAULT NULL,
    `quantity`    INT UNSIGNED      NOT NULL DEFAULT 1,
    `target_price`DECIMAL(14,2)     DEFAULT NULL,
    `country`     VARCHAR(80)       DEFAULT NULL,
    `deadline`    DATE              DEFAULT NULL,
    `status`      ENUM('open','matching','closed') NOT NULL DEFAULT 'open',
    `created_at`  DATETIME          NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_sourcing_req_buyer`    (`buyer_id`),
    KEY `idx_sourcing_req_category` (`category_id`),
    KEY `idx_sourcing_req_status`   (`status`),
    CONSTRAINT `fk_sourcing_req_buyer`
        FOREIGN KEY (`buyer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sourcing_req_category`
        FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sourcing_bids` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `request_id`  INT UNSIGNED  NOT NULL,
    `supplier_id` INT UNSIGNED  NOT NULL,
    `price`       DECIMAL(14,2) NOT NULL,
    `lead_time`   VARCHAR(60)   DEFAULT NULL,
    `notes`       TEXT          DEFAULT NULL,
    `status`      ENUM('pending','selected','rejected') NOT NULL DEFAULT 'pending',
    `created_at`  DATETIME      NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    KEY `idx_sourcing_bids_request`  (`request_id`),
    KEY `idx_sourcing_bids_supplier` (`supplier_id`),
    CONSTRAINT `fk_sourcing_bids_request`
        FOREIGN KEY (`request_id`) REFERENCES `sourcing_requests`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sourcing_bids_supplier`
        FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- End of schema.sql
