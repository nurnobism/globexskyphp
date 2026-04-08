-- PR #20 — Webmail System: Internal Messaging
-- Adds is_starred column to webmail_recipients for starred/important mail feature.

ALTER TABLE webmail_recipients
    ADD COLUMN IF NOT EXISTS is_starred TINYINT(1) NOT NULL DEFAULT 0 AFTER is_trashed,
    ADD INDEX IF NOT EXISTS idx_starred (user_id, is_starred);
