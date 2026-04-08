-- GlobexSky Database Schema v18 — Socket.io Chat Infrastructure
-- Replaces the basic conversations/messages tables from schema.sql
-- with a full-featured chat system supporting direct, group, support, and admin conversations.
--
-- Run after schema.sql (and all prior schema_v* files).
-- Safe to re-run: drops dependent tables before recreating them.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------
-- Drop old chat tables that came from schema.sql
-- (must drop in FK-dependency order: children first)
-- -------------------------------------------------------
DROP TABLE IF EXISTS message_reads;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS conversation_participants;
DROP TABLE IF EXISTS conversations;

SET FOREIGN_KEY_CHECKS = 1;

-- -------------------------------------------------------
-- conversations — enhanced, multi-type conversation model
-- -------------------------------------------------------
CREATE TABLE conversations (
    id                INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
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

-- -------------------------------------------------------
-- conversation_participants
-- -------------------------------------------------------
CREATE TABLE conversation_participants (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NOT NULL,
    role            ENUM('member','admin','muted') NOT NULL DEFAULT 'member',
    last_read_at    DATETIME         DEFAULT NULL,
    joined_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    left_at         DATETIME         DEFAULT NULL,
    is_active       TINYINT(1)       NOT NULL DEFAULT 1,
    UNIQUE KEY unique_conv_user (conversation_id, user_id),
    INDEX idx_user          (user_id),
    INDEX idx_conversation  (conversation_id),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- messages
-- -------------------------------------------------------
CREATE TABLE messages (
    id              INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT UNSIGNED     NOT NULL,
    sender_id       INT UNSIGNED     NOT NULL,
    type            ENUM('text','image','file','audio','video','system') NOT NULL DEFAULT 'text',
    body            TEXT             DEFAULT NULL,
    file_url        VARCHAR(500)     DEFAULT NULL,
    file_name       VARCHAR(255)     DEFAULT NULL,
    file_size       INT UNSIGNED     DEFAULT NULL,
    mime_type       VARCHAR(100)     DEFAULT NULL,
    reply_to_id     INT UNSIGNED     DEFAULT NULL,
    is_edited       TINYINT(1)       NOT NULL DEFAULT 0,
    is_deleted      TINYINT(1)       NOT NULL DEFAULT 0,
    deleted_at      DATETIME         DEFAULT NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_conversation  (conversation_id),
    INDEX idx_sender        (sender_id),
    INDEX idx_created       (created_at),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- message_reads — per-user read receipts
-- -------------------------------------------------------
CREATE TABLE message_reads (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id  INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    read_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_message_user (message_id, user_id),
    INDEX idx_user    (user_id),
    INDEX idx_message (message_id),
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Back-fill the FK now that messages table exists
-- -------------------------------------------------------
ALTER TABLE conversations
    ADD CONSTRAINT fk_conv_last_msg
    FOREIGN KEY (last_message_id) REFERENCES messages(id) ON DELETE SET NULL;
