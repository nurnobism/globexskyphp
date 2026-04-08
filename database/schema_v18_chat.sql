-- ============================================================
-- schema_v18_chat.sql — Real-Time Chat Infrastructure (PR #18)
--
-- Tables:
--   conversations          — conversation header (type, title, participants)
--   conversation_participants — per-user membership & read-pointer
--   messages               — message content & metadata
--   message_reads          — per-message read receipts
-- ============================================================

-- Drop helper tables before parent to avoid FK conflicts
DROP TABLE IF EXISTS message_reads;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS conversation_participants;
DROP TABLE IF EXISTS conversations;

CREATE TABLE IF NOT EXISTS conversations (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type              ENUM('direct','group','support','admin') NOT NULL DEFAULT 'direct',
    title             VARCHAR(255)     DEFAULT NULL,
    created_by        INT UNSIGNED     NOT NULL,
    last_message_id   INT UNSIGNED     DEFAULT NULL,
    last_message_at   DATETIME         DEFAULT NULL,
    is_active         TINYINT(1)       NOT NULL DEFAULT 1,
    created_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_created_by    (created_by),
    INDEX idx_last_message  (last_message_at),
    INDEX idx_active        (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conversation_participants (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id   INT UNSIGNED     NOT NULL,
    user_id           INT UNSIGNED     NOT NULL,
    joined_at         DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_read_at      DATETIME         DEFAULT NULL,
    is_muted          TINYINT(1)       NOT NULL DEFAULT 0,
    role              ENUM('member','admin') NOT NULL DEFAULT 'member',
    UNIQUE KEY uq_conv_user (conversation_id, user_id),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    INDEX idx_user          (user_id),
    INDEX idx_conversation  (conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id   INT UNSIGNED     NOT NULL,
    sender_id         INT UNSIGNED     NOT NULL,
    content           TEXT             DEFAULT NULL,
    type              ENUM('text','image','file','system','product_link','order_link') NOT NULL DEFAULT 'text',
    attachments_json  JSON             DEFAULT NULL,
    is_edited         TINYINT(1)       NOT NULL DEFAULT 0,
    edited_at         DATETIME         DEFAULT NULL,
    is_deleted        TINYINT(1)       NOT NULL DEFAULT 0,
    created_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    INDEX idx_conversation  (conversation_id),
    INDEX idx_sender        (sender_id),
    INDEX idx_created_at    (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add FK from conversations.last_message_id after messages table exists
ALTER TABLE conversations
    ADD CONSTRAINT fk_conv_last_msg
    FOREIGN KEY (last_message_id) REFERENCES messages(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS message_reads (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id  INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    read_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_msg_user (message_id, user_id),
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    INDEX idx_user      (user_id),
    INDEX idx_message   (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
