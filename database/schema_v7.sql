-- GlobexSky Database Schema v7
-- REST API Platform + Live Streaming + Webhooks
-- Run after schema.sql, schema_v2.sql, schema_v3.sql, schema_v4.sql, schema_v5.sql

SET NAMES utf8mb4;

-- -----------------------------------------------------------
-- API Keys
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS api_keys (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT NOT NULL,
    name                VARCHAR(100) NOT NULL,
    api_key             VARCHAR(64) NOT NULL UNIQUE,
    key_prefix          VARCHAR(20) NOT NULL,
    environment         ENUM('live','test') DEFAULT 'live',
    permissions         JSON,
    ip_whitelist        TEXT,
    is_active           TINYINT(1) DEFAULT 1,
    last_used_at        DATETIME,
    requests_today      INT DEFAULT 0,
    requests_month      INT DEFAULT 0,
    rate_limit_per_day  INT DEFAULT 100,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    revoked_at          DATETIME,
    INDEX idx_user   (user_id),
    INDEX idx_key    (api_key),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- API Request Logs
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS api_request_logs (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    api_key_id       INT NOT NULL,
    user_id          INT NOT NULL,
    method           VARCHAR(10) NOT NULL,
    endpoint         VARCHAR(200) NOT NULL,
    params           TEXT,
    request_body     TEXT,
    response_code    INT NOT NULL,
    response_time_ms INT DEFAULT 0,
    ip_address       VARCHAR(45),
    user_agent       VARCHAR(500),
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_api_key  (api_key_id),
    INDEX idx_created  (created_at),
    INDEX idx_endpoint (endpoint)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Webhooks
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS webhooks (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    user_id           INT NOT NULL,
    url               VARCHAR(500) NOT NULL,
    secret            VARCHAR(64) NOT NULL,
    events            JSON NOT NULL,
    is_active         TINYINT(1) DEFAULT 1,
    last_triggered_at DATETIME,
    success_count     INT DEFAULT 0,
    failure_count     INT DEFAULT 0,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user   (user_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Webhook Delivery Logs
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS webhook_deliveries (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    webhook_id       INT NOT NULL,
    event            VARCHAR(50) NOT NULL,
    payload          JSON NOT NULL,
    response_code    INT,
    response_body    TEXT,
    response_time_ms INT,
    delivery_id      VARCHAR(36) NOT NULL,
    status           ENUM('pending','success','failed','retrying') DEFAULT 'pending',
    retry_count      INT DEFAULT 0,
    max_retries      INT DEFAULT 3,
    next_retry_at    DATETIME,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delivered_at     DATETIME,
    INDEX idx_webhook (webhook_id),
    INDEX idx_status  (status),
    INDEX idx_created (created_at),
    FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Live Streams
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS live_streams (
    id                     INT AUTO_INCREMENT PRIMARY KEY,
    streamer_id            INT NOT NULL,
    title                  VARCHAR(300) NOT NULL,
    description            TEXT,
    category               ENUM('product_showcase','unboxing','tutorial','flash_sale','qa','general') DEFAULT 'general',
    thumbnail_url          VARCHAR(500),
    stream_key             VARCHAR(100) UNIQUE,
    status                 ENUM('scheduled','live','ended','cancelled') DEFAULT 'scheduled',
    scheduled_at           DATETIME,
    started_at             DATETIME,
    ended_at               DATETIME,
    duration_seconds       INT DEFAULT 0,
    peak_viewers           INT DEFAULT 0,
    total_viewers          INT DEFAULT 0,
    total_messages         INT DEFAULT 0,
    total_reactions        INT DEFAULT 0,
    orders_during_stream   INT DEFAULT 0,
    revenue_during_stream  DECIMAL(12,2) DEFAULT 0.00,
    vod_url                VARCHAR(500),
    is_vod_available       TINYINT(1) DEFAULT 0,
    is_featured            TINYINT(1) DEFAULT 0,
    created_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_streamer  (streamer_id),
    INDEX idx_status    (status),
    INDEX idx_scheduled (scheduled_at),
    INDEX idx_featured  (is_featured)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Stream Products
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS stream_products (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    stream_id             INT NOT NULL,
    product_id            INT NOT NULL,
    sort_order            INT DEFAULT 0,
    is_pinned             TINYINT(1) DEFAULT 0,
    pinned_at             DATETIME,
    special_price         DECIMAL(12,2),
    special_price_expires DATETIME,
    clicks                INT DEFAULT 0,
    orders                INT DEFAULT 0,
    FOREIGN KEY (stream_id) REFERENCES live_streams(id) ON DELETE CASCADE,
    INDEX idx_stream (stream_id),
    INDEX idx_pinned (is_pinned)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Stream Chat Messages
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS stream_chat (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    stream_id      INT NOT NULL,
    user_id        INT NOT NULL,
    message        TEXT NOT NULL,
    type           ENUM('message','question','system','reaction') DEFAULT 'message',
    is_highlighted TINYINT(1) DEFAULT 0,
    is_deleted     TINYINT(1) DEFAULT 0,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_stream  (stream_id),
    INDEX idx_created (created_at),
    FOREIGN KEY (stream_id) REFERENCES live_streams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Stream Viewers
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS stream_viewers (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    stream_id  INT NOT NULL,
    user_id    INT,
    session_id VARCHAR(100),
    joined_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    left_at    DATETIME,
    is_active  TINYINT(1) DEFAULT 1,
    INDEX idx_stream (stream_id),
    INDEX idx_active (is_active),
    FOREIGN KEY (stream_id) REFERENCES live_streams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Streamer Followers
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS streamer_followers (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    streamer_id INT NOT NULL,
    follower_id INT NOT NULL,
    followed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_follow (streamer_id, follower_id),
    INDEX idx_streamer (streamer_id),
    INDEX idx_follower (follower_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
