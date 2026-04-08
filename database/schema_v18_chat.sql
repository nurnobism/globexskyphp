-- schema_v18_chat.sql — Enhanced Chat Infrastructure (PR #18 / #19)
-- Upgrades basic chat tables to full-featured messaging schema

-- -----------------------------------------------------------
-- Drop old minimal tables (safe because CREATE TABLE IF NOT EXISTS
-- was used; we add columns and new tables here instead)
-- -----------------------------------------------------------

-- Enhance chat_rooms with richer columns
ALTER TABLE chat_rooms
    ADD COLUMN IF NOT EXISTS order_id              INT UNSIGNED DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS product_id            INT UNSIGNED DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS created_by            INT UNSIGNED DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS is_active             TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS last_message_at       DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS last_message_preview  VARCHAR(255) DEFAULT NULL;

ALTER TABLE chat_rooms
    ADD INDEX IF NOT EXISTS idx_active_last (is_active, last_message_at);

-- Enhance chat_messages
ALTER TABLE chat_messages
    ADD COLUMN IF NOT EXISTS sender_id   INT UNSIGNED DEFAULT NULL AFTER room_id,
    ADD COLUMN IF NOT EXISTS type        ENUM('text','image','file','system','product_link') NOT NULL DEFAULT 'text',
    ADD COLUMN IF NOT EXISTS file_url    VARCHAR(512)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS file_name   VARCHAR(255)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS file_size   INT UNSIGNED  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS reply_to_id INT UNSIGNED  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS is_deleted  TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS edited_at   DATETIME DEFAULT NULL;

ALTER TABLE chat_messages
    ADD INDEX IF NOT EXISTS idx_room_sender  (room_id, sender_id),
    ADD INDEX IF NOT EXISTS idx_room_created (room_id, created_at);

-- -----------------------------------------------------------
-- chat_participants — replaces/extends chat_room_members
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS chat_participants (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id       INT UNSIGNED NOT NULL,
    user_id       INT UNSIGNED NOT NULL,
    role          ENUM('admin','member') NOT NULL DEFAULT 'member',
    last_read_at  DATETIME DEFAULT NULL,
    is_muted      TINYINT(1) NOT NULL DEFAULT 0,
    joined_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_room_user (room_id, user_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- message_read_receipts
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS message_read_receipts (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    read_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_msg_user (message_id, user_id),
    INDEX idx_message (message_id),
    INDEX idx_user    (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- user_online_status — presence tracking
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_online_status (
    user_id    INT UNSIGNED PRIMARY KEY,
    is_online  TINYINT(1) NOT NULL DEFAULT 0,
    last_seen  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_online (is_online)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- chat_typing — ephemeral typing signals
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS chat_typing (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id    INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_room_user (room_id, user_id),
    INDEX idx_room (room_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
