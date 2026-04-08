-- ============================================================
-- schema_v23_notification_prefs.sql — Notification Preferences & System Messages (PR #23)
-- ============================================================
-- Creates:
--   notification_preferences      — Per-user per-event-type channel settings
--   system_messages               — Admin-broadcast system messages
--   system_message_dismissals     — Track which users dismissed which messages
-- ============================================================

-- -----------------------------------------------------------
-- notification_preferences
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS notification_preferences (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    event_type      VARCHAR(80)  NOT NULL,
    channel_in_app  TINYINT(1)   NOT NULL DEFAULT 1,
    channel_email   TINYINT(1)   NOT NULL DEFAULT 1,
    channel_push    TINYINT(1)   NOT NULL DEFAULT 0,
    channel_sms     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_event (user_id, event_type),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- system_messages
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS system_messages (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title            VARCHAR(255) NOT NULL,
    body             TEXT         NOT NULL,
    type             ENUM('maintenance','feature_update','policy_change','promotion','security_alert')
                                  NOT NULL DEFAULT 'feature_update',
    priority         ENUM('critical','warning','info') NOT NULL DEFAULT 'info',
    target_roles_json TEXT        NOT NULL COMMENT 'JSON array of target roles, empty = all',
    starts_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at       DATETIME                 DEFAULT NULL,
    is_active        TINYINT(1)   NOT NULL DEFAULT 1,
    created_by       INT UNSIGNED NOT NULL DEFAULT 0,
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_is_active  (is_active),
    INDEX idx_expires_at (expires_at),
    INDEX idx_starts_at  (starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- system_message_dismissals
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS system_message_dismissals (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id   INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    dismissed_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_msg_user (message_id, user_id),
    INDEX idx_message_id (message_id),
    INDEX idx_user_id    (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
