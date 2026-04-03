-- GlobexSky Database Schema v5
-- Real-Time Chat + Notification System + Webmail
-- Run after schema.sql, schema_v2.sql, schema_v3.sql

SET NAMES utf8mb4;

-- -------------------------------------------------------
-- Upgrade existing tables from schema.sql (simpler versions)
-- Drop dependents first, then parent tables, then recreate
-- -------------------------------------------------------

-- Drop old simple chat tables (defined in schema.sql)
DROP TABLE IF EXISTS chat_room_members;
DROP TABLE IF EXISTS chat_messages;
DROP TABLE IF EXISTS chat_rooms;

-- Drop old notifications table (defined in schema.sql)
DROP TABLE IF EXISTS notifications;

-- -------------------------------------------------------
-- Chat rooms (enhanced version)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS chat_rooms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('direct','order','inquiry','support','group') NOT NULL DEFAULT 'direct',
    name VARCHAR(200),
    order_id INT UNSIGNED,
    product_id INT UNSIGNED,
    created_by INT UNSIGNED,
    last_message_at DATETIME,
    last_message_preview TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_order (order_id),
    INDEX idx_last_message (last_message_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat room participants
CREATE TABLE IF NOT EXISTS chat_participants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    role ENUM('member','admin','muted') DEFAULT 'member',
    last_read_at DATETIME,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_room_user (room_id, user_id),
    INDEX idx_user (user_id),
    FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat messages (enhanced version)
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id INT UNSIGNED NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    message TEXT,
    type ENUM('text','image','file','system','product_link') DEFAULT 'text',
    file_url VARCHAR(500),
    file_name VARCHAR(200),
    file_size INT UNSIGNED,
    reply_to_id INT UNSIGNED,
    is_edited TINYINT(1) DEFAULT 0,
    is_deleted TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_room (room_id),
    INDEX idx_sender (sender_id),
    INDEX idx_created (created_at),
    FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Message read receipts
CREATE TABLE IF NOT EXISTS message_read_receipts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_message_user (message_id, user_id),
    FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications (enhanced version)
CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT,
    data JSON,
    priority ENUM('low','normal','high','critical') DEFAULT 'normal',
    is_read TINYINT(1) DEFAULT 0,
    read_at DATETIME,
    action_url VARCHAR(500),
    icon VARCHAR(50),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_type (type),
    INDEX idx_read (is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notification preferences
CREATE TABLE IF NOT EXISTS notification_preferences (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    in_app TINYINT(1) DEFAULT 1,
    email TINYINT(1) DEFAULT 1,
    push TINYINT(1) DEFAULT 0,
    sms TINYINT(1) DEFAULT 0,
    UNIQUE KEY unique_user_event (user_id, event_type),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Webmail messages
CREATE TABLE IF NOT EXISTS webmail_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    thread_id INT UNSIGNED,
    sender_id INT UNSIGNED NOT NULL,
    subject VARCHAR(500) NOT NULL,
    body TEXT NOT NULL,
    body_html TEXT,
    priority ENUM('normal','high','urgent') DEFAULT 'normal',
    is_draft TINYINT(1) DEFAULT 0,
    parent_message_id INT UNSIGNED,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sender (sender_id),
    INDEX idx_thread (thread_id),
    INDEX idx_draft (is_draft),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Webmail recipients
CREATE TABLE IF NOT EXISTS webmail_recipients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    type ENUM('to','cc','bcc') DEFAULT 'to',
    is_read TINYINT(1) DEFAULT 0,
    read_at DATETIME,
    is_deleted TINYINT(1) DEFAULT 0,
    deleted_at DATETIME,
    is_trashed TINYINT(1) DEFAULT 0,
    trashed_at DATETIME,
    label VARCHAR(50),
    INDEX idx_user (user_id),
    INDEX idx_message (message_id),
    INDEX idx_read (is_read),
    FOREIGN KEY (message_id) REFERENCES webmail_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Webmail attachments
CREATE TABLE IF NOT EXISTS webmail_attachments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id INT UNSIGNED NOT NULL,
    file_name VARCHAR(200) NOT NULL,
    file_url VARCHAR(500) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    mime_type VARCHAR(100),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES webmail_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Webmail labels
CREATE TABLE IF NOT EXISTS webmail_labels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(50) NOT NULL,
    color VARCHAR(7) DEFAULT '#6c757d',
    sort_order INT DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_label (user_id, name),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User online status
CREATE TABLE IF NOT EXISTS user_online_status (
    user_id INT UNSIGNED PRIMARY KEY,
    is_online TINYINT(1) DEFAULT 0,
    last_seen DATETIME,
    socket_id VARCHAR(100),
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
