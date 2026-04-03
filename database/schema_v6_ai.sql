-- GlobexSky Database Schema v6 — AI Integration (Phase 8)
-- DeepSeek AI: conversations, recommendations, fraud detection, search, content cache
-- Run after schema.sql through schema_v5.sql (or independently with IF NOT EXISTS)

SET NAMES utf8mb4;

-- -----------------------------------------------------------
-- AI Conversation Sessions
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_conversations (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    session_id      VARCHAR(64)  NOT NULL,
    title           VARCHAR(300),
    context_type    ENUM('general','product_search','sourcing','support','fraud_review','admin') DEFAULT 'general',
    messages_count  INT UNSIGNED DEFAULT 0,
    tokens_used     INT UNSIGNED DEFAULT 0,
    is_active       TINYINT(1) DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user    (user_id),
    INDEX idx_session (session_id),
    INDEX idx_context (context_type),
    INDEX idx_active  (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- AI Conversation Messages
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_messages (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id     INT UNSIGNED NOT NULL,
    role                ENUM('system','user','assistant') NOT NULL,
    content             TEXT NOT NULL,
    tokens              INT UNSIGNED DEFAULT 0,
    model               VARCHAR(50) DEFAULT 'deepseek-chat',
    response_time_ms    INT UNSIGNED DEFAULT 0,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_conversation (conversation_id),
    INDEX idx_role         (role),
    FOREIGN KEY (conversation_id) REFERENCES ai_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- AI Product Recommendations
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_recommendations (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 INT UNSIGNED NOT NULL,
    product_id              INT UNSIGNED NOT NULL,
    recommendation_type     ENUM('similar','complementary','trending','personalized','ai_sourced') NOT NULL,
    score                   DECIMAL(5,4) DEFAULT 0.0000,
    reason                  TEXT,
    is_clicked              TINYINT(1) DEFAULT 0,
    is_purchased            TINYINT(1) DEFAULT 0,
    expires_at              DATETIME,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user    (user_id),
    INDEX idx_product (product_id),
    INDEX idx_type    (recommendation_type),
    INDEX idx_score   (score),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- AI Fraud Analysis Logs
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_fraud_logs (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             INT UNSIGNED NOT NULL,
    order_id            INT UNSIGNED,
    event_type          ENUM('order','login','registration','payment','refund','review') NOT NULL,
    risk_score          DECIMAL(5,2) DEFAULT 0.00,
    risk_level          ENUM('low','medium','high','critical') DEFAULT 'low',
    factors             JSON,
    ai_analysis         TEXT,
    ai_recommendation   ENUM('approve','review','hold','block') DEFAULT 'approve',
    admin_decision      ENUM('pending','approved','rejected','escalated') DEFAULT 'pending',
    admin_notes         TEXT,
    resolved_by         INT UNSIGNED,
    resolved_at         DATETIME,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user       (user_id),
    INDEX idx_order      (order_id),
    INDEX idx_risk_level (risk_level),
    INDEX idx_event      (event_type),
    INDEX idx_decision   (admin_decision),
    INDEX idx_created    (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- AI Search Query Logs
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_search_logs (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             INT UNSIGNED,
    query_text          VARCHAR(500) NOT NULL,
    interpreted_query   VARCHAR(500),
    search_type         ENUM('product','supplier','category','general') DEFAULT 'product',
    results_count       INT UNSIGNED DEFAULT 0,
    clicked_result_id   INT UNSIGNED,
    ai_enhanced         TINYINT(1) DEFAULT 0,
    response_time_ms    INT UNSIGNED DEFAULT 0,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user    (user_id),
    INDEX idx_query   (query_text(100)),
    INDEX idx_type    (search_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- AI Generated Content Cache
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_content_cache (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cache_key           VARCHAR(64) NOT NULL UNIQUE,
    content_type        ENUM('product_description','seo_meta','email_template','translation','summary') NOT NULL,
    input_hash          VARCHAR(64) NOT NULL,
    generated_content   TEXT NOT NULL,
    model               VARCHAR(50) DEFAULT 'deepseek-chat',
    tokens_used         INT UNSIGNED DEFAULT 0,
    hit_count           INT UNSIGNED DEFAULT 0,
    expires_at          DATETIME,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cache_key (cache_key),
    INDEX idx_type      (content_type),
    INDEX idx_hash      (input_hash),
    INDEX idx_expires   (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- AI Token Usage / Billing Tracking
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_usage (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED,
    feature         VARCHAR(50) NOT NULL,
    tokens_input    INT UNSIGNED DEFAULT 0,
    tokens_output   INT UNSIGNED DEFAULT 0,
    cost_usd        DECIMAL(10,6) DEFAULT 0.000000,
    model           VARCHAR(50) DEFAULT 'deepseek-chat',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user    (user_id),
    INDEX idx_feature (feature),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- AI Model Configuration
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_config (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    config_key      VARCHAR(100) NOT NULL UNIQUE,
    config_value    TEXT NOT NULL,
    description     VARCHAR(500),
    updated_by      INT UNSIGNED,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default AI configuration values
INSERT IGNORE INTO ai_config (config_key, config_value, description) VALUES
('deepseek_api_key',              '',                           'DeepSeek API key'),
('deepseek_base_url',             'https://api.deepseek.com/v1','DeepSeek API base URL'),
('deepseek_model',                'deepseek-chat',              'Default model for general queries'),
('deepseek_reasoner_model',       'deepseek-reasoner',          'Model for complex reasoning tasks'),
('ai_enabled',                    '1',                          'Global AI feature toggle'),
('ai_chatbot_enabled',            '1',                          'AI chatbot widget toggle'),
('ai_recommendations_enabled',    '1',                          'AI product recommendations toggle'),
('ai_fraud_enabled',              '1',                          'AI fraud detection toggle'),
('ai_search_enabled',             '1',                          'AI-enhanced search toggle'),
('ai_content_enabled',            '1',                          'AI content generation toggle'),
('max_tokens_chat',               '2048',                       'Max tokens for chat responses'),
('max_tokens_content',            '4096',                       'Max tokens for content generation'),
('temperature_chat',              '0.7',                        'Temperature for chatbot'),
('temperature_content',           '0.5',                        'Temperature for content generation'),
('temperature_fraud',             '0.1',                        'Temperature for fraud analysis (low = deterministic)'),
('daily_token_limit_free',        '5000',                       'Daily token limit for free users'),
('daily_token_limit_pro',         '50000',                      'Daily token limit for pro users'),
('daily_token_limit_enterprise',  '500000',                     'Daily token limit for enterprise users'),
('recommendation_refresh_hours',  '24',                         'How often to refresh recommendations'),
('fraud_score_threshold_review',  '50',                         'Risk score above which orders need review'),
('fraud_score_threshold_block',   '80',                         'Risk score above which orders are auto-blocked');
