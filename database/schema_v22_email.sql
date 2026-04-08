-- ============================================================
-- PR #22 — Email Templates & PHPMailer Integration
-- Creates: email_queue, email_logs
-- Ensures: system_settings has SMTP keys
-- ============================================================

-- Email queue (cron-processed outbound emails)
CREATE TABLE IF NOT EXISTS `email_queue` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `to_email`      VARCHAR(255)    NOT NULL,
  `to_name`       VARCHAR(255)    NOT NULL DEFAULT '',
  `subject`       VARCHAR(500)    NOT NULL,
  `template`      VARCHAR(100)    NOT NULL DEFAULT '',
  `data_json`     JSON            NULL,
  `html_body`     LONGTEXT        NULL,
  `text_body`     TEXT            NULL,
  `status`        ENUM('pending','processing','sent','failed') NOT NULL DEFAULT 'pending',
  `attempts`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `sent_at`       DATETIME        NULL,
  `error_message` TEXT            NULL,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email_queue_status`  (`status`),
  KEY `idx_email_queue_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email delivery log
CREATE TABLE IF NOT EXISTS `email_logs` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `to_email`      VARCHAR(255)    NOT NULL,
  `to_name`       VARCHAR(255)    NOT NULL DEFAULT '',
  `subject`       VARCHAR(500)    NOT NULL,
  `template`      VARCHAR(100)    NOT NULL DEFAULT '',
  `status`        ENUM('sent','failed') NOT NULL DEFAULT 'sent',
  `smtp_response` VARCHAR(1000)   NOT NULL DEFAULT '',
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email_logs_to`      (`to_email`),
  KEY `idx_email_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure system_settings has SMTP configuration keys
-- (INSERT IGNORE so existing values are not overwritten)
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`)
VALUES
  ('smtp_host',       '',    'email'),
  ('smtp_port',       '587', 'email'),
  ('smtp_username',   '',    'email'),
  ('smtp_password',   '',    'email'),
  ('smtp_encryption', 'tls', 'email'),
  ('smtp_from_email', '',    'email'),
  ('smtp_from_name',  '',    'email');
