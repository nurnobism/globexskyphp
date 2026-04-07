-- ============================================================
-- schema_v16_payouts.sql — Payout System Schema (PR #11)
-- ============================================================
-- Extends payout_requests, adds payout_methods,
-- supplier_earnings, and ensures orders table has
-- commission_amount, net_supplier_amount, hold_released_at.
-- Run after schema_v15_commission.sql / schema_v15_plans.sql
-- ============================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------
-- payout_methods — Saved payout accounts per supplier
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS payout_methods (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id          INT UNSIGNED NOT NULL,
    method_type          ENUM('bank_transfer','paypal','wise') NOT NULL,
    account_details_json JSON         NOT NULL,
    is_default           TINYINT(1)   NOT NULL DEFAULT 0,
    is_verified          TINYINT(1)   NOT NULL DEFAULT 0,
    created_at           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_supplier_id (supplier_id),
    INDEX idx_default     (supplier_id, is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- supplier_earnings — Ledger for supplier balance tracking
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS supplier_earnings (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id   INT UNSIGNED NOT NULL,
    type          ENUM('sale','commission_deduct','payout','refund','hold_release','adjustment')
                               NOT NULL DEFAULT 'sale',
    amount        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    balance_after DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    description   VARCHAR(500)  NOT NULL DEFAULT '',
    reference_id  VARCHAR(100)  NOT NULL DEFAULT '',
    order_id      INT UNSIGNED  NULL,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_supplier_id  (supplier_id),
    INDEX idx_type         (type),
    INDEX idx_reference_id (reference_id),
    INDEX idx_created_at   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Extend payout_requests with additional spec columns
-- -----------------------------------------------------------
DROP PROCEDURE IF EXISTS _extend_payout_requests;
DELIMITER //
CREATE PROCEDURE _extend_payout_requests()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'payout_requests'
          AND COLUMN_NAME  = 'currency'
    ) THEN
        ALTER TABLE payout_requests
            ADD COLUMN currency VARCHAR(3) NOT NULL DEFAULT 'USD' AFTER amount;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'payout_requests'
          AND COLUMN_NAME  = 'payout_method_id'
    ) THEN
        ALTER TABLE payout_requests
            ADD COLUMN payout_method_id INT UNSIGNED NULL AFTER currency;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'payout_requests'
          AND COLUMN_NAME  = 'transaction_ref'
    ) THEN
        ALTER TABLE payout_requests
            ADD COLUMN transaction_ref VARCHAR(255) NULL AFTER reference_number;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'payout_requests'
          AND COLUMN_NAME  = 'rejection_reason'
    ) THEN
        ALTER TABLE payout_requests
            ADD COLUMN rejection_reason TEXT NULL AFTER admin_note;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'payout_requests'
          AND COLUMN_NAME  = 'admin_notes'
    ) THEN
        ALTER TABLE payout_requests
            ADD COLUMN admin_notes TEXT NULL AFTER rejection_reason;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'payout_requests'
          AND COLUMN_NAME  = 'requested_at'
    ) THEN
        ALTER TABLE payout_requests
            ADD COLUMN requested_at TIMESTAMP NULL AFTER updated_at;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'payout_requests'
          AND COLUMN_NAME  = 'approved_at'
    ) THEN
        ALTER TABLE payout_requests
            ADD COLUMN approved_at TIMESTAMP NULL AFTER requested_at;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'payout_requests'
          AND COLUMN_NAME  = 'completed_at'
    ) THEN
        ALTER TABLE payout_requests
            ADD COLUMN completed_at TIMESTAMP NULL AFTER approved_at;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'payout_requests'
          AND COLUMN_NAME  = 'rejected_at'
    ) THEN
        ALTER TABLE payout_requests
            ADD COLUMN rejected_at TIMESTAMP NULL AFTER completed_at;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'payout_requests'
          AND COLUMN_NAME  = 'cancelled_at'
    ) THEN
        ALTER TABLE payout_requests
            ADD COLUMN cancelled_at TIMESTAMP NULL AFTER rejected_at;
    END IF;
END //
DELIMITER ;
CALL _extend_payout_requests();
DROP PROCEDURE IF EXISTS _extend_payout_requests;

-- -----------------------------------------------------------
-- Extend orders with payout tracking columns
-- -----------------------------------------------------------
DROP PROCEDURE IF EXISTS _extend_orders_payout;
DELIMITER //
CREATE PROCEDURE _extend_orders_payout()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'orders'
          AND COLUMN_NAME  = 'commission_amount'
    ) THEN
        ALTER TABLE orders ADD COLUMN commission_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'orders'
          AND COLUMN_NAME  = 'net_supplier_amount'
    ) THEN
        ALTER TABLE orders ADD COLUMN net_supplier_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'orders'
          AND COLUMN_NAME  = 'hold_released_at'
    ) THEN
        ALTER TABLE orders ADD COLUMN hold_released_at TIMESTAMP NULL;
    END IF;
END //
DELIMITER ;
CALL _extend_orders_payout();
DROP PROCEDURE IF EXISTS _extend_orders_payout;
