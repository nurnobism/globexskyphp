-- ============================================================
-- Phase 8: AI Integration tables (schema_v8.sql)
-- Compatible with MySQL 5.7+ / 8.0
-- Use: mysql -u user -p dbname < database/schema_v8.sql
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- AI conversation sessions
CREATE TABLE IF NOT EXISTS ai_conversations (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    session_id   VARCHAR(64)  NOT NULL,
    title        VARCHAR(300),
    context_type ENUM('general','product','order','support','sourcing','fraud','analytics') DEFAULT 'general',
    context_id   INT UNSIGNED DEFAULT NULL,
    message_count INT UNSIGNED DEFAULT 0,
    is_archived  TINYINT(1)   DEFAULT 0,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user    (user_id),
    INDEX idx_session (session_id),
    INDEX idx_context (context_type, context_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI messages within conversations
CREATE TABLE IF NOT EXISTS ai_messages (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT UNSIGNED NOT NULL,
    role            ENUM('user','assistant','system') NOT NULL,
    content         TEXT         NOT NULL,
    tokens_used     INT UNSIGNED DEFAULT 0,
    model           VARCHAR(50)  DEFAULT 'deepseek-chat',
    response_time_ms INT UNSIGNED DEFAULT 0,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_conversation (conversation_id),
    INDEX idx_created      (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI-generated product recommendations
CREATE TABLE IF NOT EXISTS ai_recommendations (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id              INT UNSIGNED NOT NULL,
    recommendation_type  ENUM('similar','complementary','trending','personalized','frequently_bought','ai_sourced') NOT NULL,
    source_product_id    INT UNSIGNED DEFAULT NULL,
    recommended_product_id INT UNSIGNED NOT NULL,
    score                DECIMAL(5,4) DEFAULT 0.0000,
    reason               TEXT,
    is_clicked           TINYINT(1)   DEFAULT 0,
    is_purchased         TINYINT(1)   DEFAULT 0,
    expires_at           DATETIME,
    created_at           TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user    (user_id),
    INDEX idx_source  (source_product_id),
    INDEX idx_type    (recommendation_type),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI fraud detection logs
CREATE TABLE IF NOT EXISTS ai_fraud_logs (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type    ENUM('user','order','transaction','review','listing','ip') NOT NULL,
    entity_id      INT UNSIGNED NOT NULL,
    risk_score     DECIMAL(5,2) DEFAULT 0.00,
    risk_level     ENUM('low','medium','high','critical') DEFAULT 'low',
    factors        JSON,
    ai_reasoning   TEXT,
    action_taken   ENUM('none','flag','hold','block','notify_admin') DEFAULT 'none',
    reviewed_by    INT UNSIGNED DEFAULT NULL,
    reviewed_at    DATETIME     DEFAULT NULL,
    is_false_positive TINYINT(1) DEFAULT NULL,
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity  (entity_type, entity_id),
    INDEX idx_risk    (risk_level),
    INDEX idx_action  (action_taken),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI usage tracking / token consumption
CREATE TABLE IF NOT EXISTS ai_usage (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED DEFAULT NULL,
    feature          VARCHAR(50)  NOT NULL,
    model            VARCHAR(50)  DEFAULT 'deepseek-chat',
    input_tokens     INT UNSIGNED DEFAULT 0,
    output_tokens    INT UNSIGNED DEFAULT 0,
    total_tokens     INT UNSIGNED DEFAULT 0,
    cost_usd         DECIMAL(10,6) DEFAULT 0.000000,
    response_time_ms INT UNSIGNED  DEFAULT 0,
    status           ENUM('success','error','timeout','rate_limited') DEFAULT 'success',
    error_message    TEXT         DEFAULT NULL,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user    (user_id),
    INDEX idx_feature (feature),
    INDEX idx_created (created_at),
    INDEX idx_status  (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI-powered search logs
CREATE TABLE IF NOT EXISTS ai_search_logs (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED DEFAULT NULL,
    original_query   VARCHAR(500) NOT NULL,
    enhanced_query   VARCHAR(1000),
    intent           VARCHAR(100),
    filters_suggested JSON,
    results_count    INT UNSIGNED DEFAULT 0,
    clicked_product_id INT UNSIGNED DEFAULT NULL,
    response_time_ms INT UNSIGNED DEFAULT 0,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user    (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI product description generations
CREATE TABLE IF NOT EXISTS ai_content_generations (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    content_type  ENUM('product_description','seo_title','seo_meta','review_summary','translation','email_template','ad_copy') NOT NULL,
    source_text   TEXT,
    generated_text TEXT NOT NULL,
    language      VARCHAR(10)  DEFAULT 'en',
    tokens_used   INT UNSIGNED DEFAULT 0,
    is_approved   TINYINT(1)   DEFAULT 0,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_type (content_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI supplier verification scores
CREATE TABLE IF NOT EXISTS ai_supplier_scores (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id         INT UNSIGNED NOT NULL,
    overall_score       DECIMAL(5,2) DEFAULT 0.00,
    reliability_score   DECIMAL(5,2) DEFAULT 0.00,
    quality_score       DECIMAL(5,2) DEFAULT 0.00,
    communication_score DECIMAL(5,2) DEFAULT 0.00,
    delivery_score      DECIMAL(5,2) DEFAULT 0.00,
    factors             JSON,
    ai_summary          TEXT,
    calculated_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_supplier (supplier_id),
    INDEX idx_overall  (overall_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
