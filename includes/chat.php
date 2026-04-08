<?php
/**
 * includes/chat.php — Real-Time Chat Library (PR #18)
 *
 * Conversation & Message Management for GlobexSky.
 *
 * Sections:
 *   1. Conversation Management
 *   2. Message Management
 *   3. Read Receipts
 *   4. Search
 *   5. Socket.io Bridge
 *
 * Feature toggle: isFeatureEnabled('real_time_chat')
 */

define('CHAT_EDIT_WINDOW_SECONDS', 900);   // 15 minutes

// ─────────────────────────────────────────────────────────────────────────────
// 1. Conversation Management
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Create a new conversation.
 *
 * @param int[]  $participants  Array of user IDs to include (creator must be in list)
 * @param string $type          'direct'|'group'|'support'|'admin'
 * @param string $title         Optional display title (used for group/support)
 * @param int    $createdBy     User ID of the creator
 * @return int|false            New conversation ID, or false on failure
 */
function createConversation(array $participants, string $type, string $title, int $createdBy): int|false
{
    if (!isFeatureEnabled('real_time_chat')) return false;

    $allowed = ['direct', 'group', 'support', 'admin'];
    if (!in_array($type, $allowed, true)) return false;
    if (empty($participants)) return false;

    // Sanitize
    $participants = array_values(array_unique(array_filter(array_map('intval', $participants))));
    if (!in_array($createdBy, $participants, true)) {
        $participants[] = $createdBy;
    }

    // For direct (1-on-1), return existing room if found
    if ($type === 'direct') {
        $other = array_values(array_filter($participants, fn($p) => $p !== $createdBy));
        if (count($other) !== 1) return false;
        $existing = findDirectConversation($createdBy, $other[0]);
        if ($existing !== null) return $existing;
    }

    try {
        $db = getDB();
        $db->beginTransaction();

        $titleVal = $title !== '' ? $title : null;
        $db->prepare(
            'INSERT INTO conversations (type, title, created_by, is_active)
             VALUES (?, ?, ?, 1)'
        )->execute([$type, $titleVal, $createdBy]);
        $convId = (int) $db->lastInsertId();

        $insert = $db->prepare(
            'INSERT IGNORE INTO conversation_participants (conversation_id, user_id, role)
             VALUES (?, ?, ?)'
        );
        foreach ($participants as $uid) {
            $role = ($uid === $createdBy) ? 'admin' : 'member';
            $insert->execute([$convId, $uid, $role]);
        }

        $db->commit();
        return $convId;
    } catch (PDOException $e) {
        try { $db->rollBack(); } catch (Throwable $t) { /* ignore */ }
        return false;
    }
}

/**
 * Find existing 1-on-1 conversation between two users.
 *
 * @return int|null  Conversation ID or null if none found
 */
function findDirectConversation(int $userA, int $userB): ?int
{
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT c.id FROM conversations c
             JOIN conversation_participants p1 ON p1.conversation_id = c.id AND p1.user_id = ?
             JOIN conversation_participants p2 ON p2.conversation_id = c.id AND p2.user_id = ?
             WHERE c.type = "direct" AND c.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([$userA, $userB]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['id'] : null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Get a single conversation, verifying that $userId is a participant.
 *
 * @return array|null  Conversation row (with participants list) or null
 */
function getConversation(int $conversationId, int $userId): ?array
{
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT c.*,
                    cp.role AS my_role, cp.last_read_at, cp.is_muted,
                    (SELECT COUNT(*) FROM messages m
                     WHERE m.conversation_id = c.id AND m.is_deleted = 0
                       AND m.created_at > COALESCE(cp.last_read_at, "1970-01-01")
                       AND m.sender_id != ?) AS unread_count
             FROM conversations c
             JOIN conversation_participants cp ON cp.conversation_id = c.id AND cp.user_id = ?
             WHERE c.id = ? AND c.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([$userId, $userId, $conversationId]);
        $conv = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$conv) return null;

        $conv['participants'] = getConversationParticipants($conversationId);
        return $conv;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Get participants for a conversation.
 *
 * @return array[]
 */
function getConversationParticipants(int $conversationId): array
{
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT cp.user_id, cp.role, cp.joined_at, cp.is_muted,
                    CONCAT(u.first_name, " ", u.last_name) AS name,
                    u.avatar
             FROM conversation_participants cp
             JOIN users u ON u.id = cp.user_id
             WHERE cp.conversation_id = ?'
        );
        $stmt->execute([$conversationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * List a user's conversations (paginated), with last message & unread count.
 *
 * @return array{data: array[], total: int}
 */
function getConversations(int $userId, int $page = 1, int $perPage = 20): array
{
    $page    = max(1, $page);
    $perPage = min(50, max(1, $perPage));
    $offset  = ($page - 1) * $perPage;

    try {
        $db = getDB();

        $stmt = $db->prepare(
            'SELECT c.*,
                    cp.role AS my_role, cp.last_read_at, cp.is_muted,
                    (SELECT COUNT(*) FROM messages m
                     WHERE m.conversation_id = c.id AND m.is_deleted = 0
                       AND m.created_at > COALESCE(cp.last_read_at, "1970-01-01")
                       AND m.sender_id != ?) AS unread_count,
                    (SELECT m2.content FROM messages m2
                     WHERE m2.id = c.last_message_id) AS last_message_preview
             FROM conversations c
             JOIN conversation_participants cp ON cp.conversation_id = c.id AND cp.user_id = ?
             WHERE c.is_active = 1
             ORDER BY COALESCE(c.last_message_at, c.created_at) DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->execute([$userId, $userId, $perPage, $offset]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countStmt = $db->prepare(
            'SELECT COUNT(*) FROM conversations c
             JOIN conversation_participants cp ON cp.conversation_id = c.id AND cp.user_id = ?
             WHERE c.is_active = 1'
        );
        $countStmt->execute([$userId]);
        $total = (int) $countStmt->fetchColumn();

        return ['data' => $rows, 'total' => $total];
    } catch (PDOException $e) {
        return ['data' => [], 'total' => 0];
    }
}

/**
 * Soft-delete a conversation for a user (removes them as participant).
 */
function deleteConversation(int $conversationId, int $userId): bool
{
    try {
        $db   = getDB();
        $rows = $db->prepare(
            'DELETE FROM conversation_participants
             WHERE conversation_id = ? AND user_id = ?'
        );
        $rows->execute([$conversationId, $userId]);
        return $rows->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Add a participant to a group conversation.
 */
function addParticipant(int $conversationId, int $userId): bool
{
    try {
        $db = getDB();
        $db->prepare(
            'INSERT IGNORE INTO conversation_participants (conversation_id, user_id, role)
             VALUES (?, ?, "member")'
        )->execute([$conversationId, $userId]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Remove a participant from a group conversation.
 */
function removeParticipant(int $conversationId, int $userId): bool
{
    try {
        $db = getDB();
        $db->prepare(
            'DELETE FROM conversation_participants
             WHERE conversation_id = ? AND user_id = ?'
        )->execute([$conversationId, $userId]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. Message Management
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Send a message to a conversation.
 *
 * @param int    $conversationId
 * @param int    $senderId
 * @param string $content       Text content (may be empty for file-only messages)
 * @param string $type          text|image|file|system|product_link|order_link
 * @param array  $attachments   Array of attachment metadata arrays
 * @return int|false            New message ID or false on failure
 */
function sendMessage(int $conversationId, int $senderId, string $content, string $type = 'text', array $attachments = []): int|false
{
    if (!isFeatureEnabled('real_time_chat')) return false;

    $allowedTypes = ['text', 'image', 'file', 'system', 'product_link', 'order_link'];
    if (!in_array($type, $allowedTypes, true)) $type = 'text';

    // Validate content (do not HTML-encode at storage time — sanitize at output)
    if ($content === '' && empty($attachments)) return false;

    // Verify sender is a participant
    if (!isConversationParticipant($conversationId, $senderId)) return false;

    $attachmentsJson = empty($attachments) ? null : json_encode($attachments);

    try {
        $db = getDB();
        $db->beginTransaction();

        $db->prepare(
            'INSERT INTO messages (conversation_id, sender_id, content, type, attachments_json)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$conversationId, $senderId, $content ?: null, $type, $attachmentsJson]);
        $msgId = (int) $db->lastInsertId();

        $db->prepare(
            'UPDATE conversations
             SET last_message_id = ?, last_message_at = NOW(), updated_at = NOW()
             WHERE id = ?'
        )->execute([$msgId, $conversationId]);

        $db->commit();

        // Trigger Socket.io event asynchronously
        notifySocketServer($conversationId, $senderId, $msgId, $content, $type);

        return $msgId;
    } catch (PDOException $e) {
        try { $db->rollBack(); } catch (Throwable $t) { /* ignore */ }
        return false;
    }
}

/**
 * Get paginated message history for a conversation.
 *
 * @return array{data: array[], total: int}
 */
function getMessages(int $conversationId, int $userId, int $page = 1, int $perPage = 50): array
{
    if (!isConversationParticipant($conversationId, $userId)) {
        return ['data' => [], 'total' => 0];
    }

    $page    = max(1, $page);
    $perPage = min(100, max(1, $perPage));
    $offset  = ($page - 1) * $perPage;

    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT m.*,
                    CONCAT(u.first_name, " ", u.last_name) AS sender_name,
                    u.avatar AS sender_avatar
             FROM messages m
             JOIN users u ON u.id = m.sender_id
             WHERE m.conversation_id = ? AND m.is_deleted = 0
             ORDER BY m.created_at DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->execute([$conversationId, $perPage, $offset]);
        $rows = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

        $countStmt = $db->prepare(
            'SELECT COUNT(*) FROM messages WHERE conversation_id = ? AND is_deleted = 0'
        );
        $countStmt->execute([$conversationId]);
        $total = (int) $countStmt->fetchColumn();

        return ['data' => $rows, 'total' => $total];
    } catch (PDOException $e) {
        return ['data' => [], 'total' => 0];
    }
}

/**
 * Soft-delete a message (sender only).
 */
function deleteMessage(int $messageId, int $userId): bool|string
{
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT * FROM messages WHERE id = ? AND is_deleted = 0 LIMIT 1'
        );
        $stmt->execute([$messageId]);
        $msg = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$msg) return 'not_found';
        if ((int) $msg['sender_id'] !== $userId) return 'forbidden';

        $db->prepare(
            'UPDATE messages SET is_deleted = 1 WHERE id = ?'
        )->execute([$messageId]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Edit a message within the allowed edit window (15 minutes).
 */
function editMessage(int $messageId, int $userId, string $newContent): bool|string
{
    if (trim($newContent) === '') return false;

    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT * FROM messages WHERE id = ? AND is_deleted = 0 LIMIT 1'
        );
        $stmt->execute([$messageId]);
        $msg = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$msg) return 'not_found';
        if ((int) $msg['sender_id'] !== $userId) return 'forbidden';

        $age = time() - strtotime($msg['created_at']);
        if ($age > CHAT_EDIT_WINDOW_SECONDS) return 'edit_window_expired';

        $db->prepare(
            'UPDATE messages
             SET content = ?, is_edited = 1, edited_at = NOW()
             WHERE id = ?'
        )->execute([$newContent, $messageId]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. Read Receipts
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Mark all messages in a conversation as read for a user.
 */
function markAsRead(int $conversationId, int $userId): bool
{
    if (!isConversationParticipant($conversationId, $userId)) return false;

    try {
        $db = getDB();

        // Insert read receipts for unread messages
        $msgs = $db->prepare(
            'SELECT id FROM messages
             WHERE conversation_id = ? AND is_deleted = 0 AND sender_id != ?'
        );
        $msgs->execute([$conversationId, $userId]);
        $insert = $db->prepare(
            'INSERT IGNORE INTO message_reads (message_id, user_id) VALUES (?, ?)'
        );
        foreach ($msgs->fetchAll(PDO::FETCH_COLUMN) as $msgId) {
            $insert->execute([$msgId, $userId]);
        }

        // Bump participant last_read_at
        $db->prepare(
            'UPDATE conversation_participants
             SET last_read_at = NOW()
             WHERE conversation_id = ? AND user_id = ?'
        )->execute([$conversationId, $userId]);

        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Total unread message count for a user across all conversations.
 */
function getUnreadCount(int $userId): int
{
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT SUM(
                (SELECT COUNT(*) FROM messages m
                 WHERE m.conversation_id = c.id AND m.is_deleted = 0
                   AND m.created_at > COALESCE(cp.last_read_at, "1970-01-01")
                   AND m.sender_id != ?)
             ) AS total
             FROM conversations c
             JOIN conversation_participants cp ON cp.conversation_id = c.id AND cp.user_id = ?
             WHERE c.is_active = 1'
        );
        $stmt->execute([$userId, $userId]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Unread message count in a specific conversation for a user.
 */
function getConversationUnreadCount(int $conversationId, int $userId): int
{
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM messages m
             JOIN conversation_participants cp
               ON cp.conversation_id = m.conversation_id AND cp.user_id = ?
             WHERE m.conversation_id = ? AND m.is_deleted = 0
               AND m.created_at > COALESCE(cp.last_read_at, "1970-01-01")
               AND m.sender_id != ?'
        );
        $stmt->execute([$userId, $conversationId, $userId]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. Search
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Search message content across all of a user's conversations.
 *
 * @return array{data: array[], total: int}
 */
function searchMessages(int $userId, string $query, int $page = 1, int $perPage = 20): array
{
    $query = trim($query);
    if (strlen($query) < 2) return ['data' => [], 'total' => 0];

    $page    = max(1, $page);
    $perPage = min(50, max(1, $perPage));
    $offset  = ($page - 1) * $perPage;
    $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
    $like    = '%' . $escaped . '%';

    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT m.*,
                    CONCAT(u.first_name, " ", u.last_name) AS sender_name,
                    c.type AS conversation_type, c.title AS conversation_title
             FROM messages m
             JOIN conversation_participants cp
               ON cp.conversation_id = m.conversation_id AND cp.user_id = ?
             JOIN users u ON u.id = m.sender_id
             JOIN conversations c ON c.id = m.conversation_id
             WHERE m.is_deleted = 0 AND m.content LIKE ?
             ORDER BY m.created_at DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->execute([$userId, $like, $perPage, $offset]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countStmt = $db->prepare(
            'SELECT COUNT(*) FROM messages m
             JOIN conversation_participants cp
               ON cp.conversation_id = m.conversation_id AND cp.user_id = ?
             WHERE m.is_deleted = 0 AND m.content LIKE ?'
        );
        $countStmt->execute([$userId, $like]);
        $total = (int) $countStmt->fetchColumn();

        return ['data' => $rows, 'total' => $total];
    } catch (PDOException $e) {
        return ['data' => [], 'total' => 0];
    }
}

/**
 * Search conversations by participant name or conversation title.
 *
 * @return array[]
 */
function searchConversations(int $userId, string $query): array
{
    $query = trim($query);
    if (strlen($query) < 2) return [];
    $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
    $like = '%' . $escaped . '%';

    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT DISTINCT c.*
             FROM conversations c
             JOIN conversation_participants cp ON cp.conversation_id = c.id AND cp.user_id = ?
             LEFT JOIN conversation_participants cp2 ON cp2.conversation_id = c.id AND cp2.user_id != ?
             LEFT JOIN users u ON u.id = cp2.user_id
             WHERE c.is_active = 1
               AND (c.title LIKE ?
                    OR CONCAT(u.first_name, " ", u.last_name) LIKE ?)
             ORDER BY COALESCE(c.last_message_at, c.created_at) DESC
             LIMIT 20'
        );
        $stmt->execute([$userId, $userId, $like, $like]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. Socket.io Bridge
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Check whether a user is a participant of a conversation.
 */
function isConversationParticipant(int $conversationId, int $userId): bool
{
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT id FROM conversation_participants
             WHERE conversation_id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$conversationId, $userId]);
        return (bool) $stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Notify the Node.js Socket.io server about a new message via internal HTTP API.
 *
 * Non-blocking: errors are silently ignored so the PHP response is never delayed.
 */
function notifySocketServer(int $conversationId, int $senderId, int $messageId, string $content, string $type): void
{
    $socketUrl = rtrim(getenv('SOCKET_SERVER_URL') ?: 'http://localhost:3001', '/');
    $apiKey    = getenv('INTERNAL_API_KEY') ?: '';
    if ($apiKey === '') return;

    $payload = json_encode([
        'event'          => 'new_message',
        'conversationId' => $conversationId,
        'senderId'       => $senderId,
        'messageId'      => $messageId,
        'content'        => $content,
        'type'           => $type,
    ]);

    $ch = curl_init("$socketUrl/internal/chat-message");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            "Authorization: Bearer $apiKey",
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 1,
        CURLOPT_CONNECTTIMEOUT => 1,
    ]);
    curl_exec($ch);
    curl_close($ch);
}
