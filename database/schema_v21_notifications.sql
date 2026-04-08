-- ============================================================
-- PR #21 — Notification Engine: Schema Upgrade
-- Adds group_key and channel columns to notifications table
-- ============================================================

ALTER TABLE notifications
    ADD COLUMN IF NOT EXISTS group_key VARCHAR(100) DEFAULT NULL AFTER icon,
    ADD COLUMN IF NOT EXISTS channel   VARCHAR(50)  DEFAULT 'in_app' AFTER group_key,
    MODIFY COLUMN type VARCHAR(100) NOT NULL;

ALTER TABLE notifications
    ADD INDEX IF NOT EXISTS idx_group_key (group_key),
    ADD INDEX IF NOT EXISTS idx_channel   (channel);

-- -------------------------------------------------------
-- Notification queue for email / push delivery
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS notification_queue (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    notification_id INT UNSIGNED NOT NULL,
    channel      ENUM('email','push','sms') NOT NULL DEFAULT 'email',
    status       ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    scheduled_at DATETIME,
    sent_at      DATETIME,
    error_msg    TEXT,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status    (status),
    INDEX idx_channel   (channel),
    INDEX idx_scheduled (scheduled_at),
    FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
