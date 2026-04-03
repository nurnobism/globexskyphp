-- GlobexSky Phase 11 — Security Audit + Performance Hardening
-- Run this after schema_v10.sql
-- ============================================================

-- Rate limiting table
CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `rate_key`        VARCHAR(255) NOT NULL,
    `attempts`        INT UNSIGNED NOT NULL DEFAULT 0,
    `last_attempt_at` DATETIME     NOT NULL,
    `expires_at`      DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_rate_key` (`rate_key`),
    INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Query performance log table
CREATE TABLE IF NOT EXISTS `query_log` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `query_hash`        CHAR(64)     NOT NULL,
    `query_text`        TEXT         NOT NULL,
    `execution_time_ms` FLOAT        NOT NULL,
    `row_count`         INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_query_hash`     (`query_hash`),
    INDEX `idx_execution_time` (`execution_time_ms`),
    INDEX `idx_created_at`     (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
