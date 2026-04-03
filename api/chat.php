<?php
require_once __DIR__ . '/../includes/middleware.php';
header('Content-Type: application/json');
$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOut($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }
function validateCsrf(): void { if (!verifyCsrf()) { jsonOut(['error' => 'Invalid CSRF token'], 403); } }

define('CHAT_MESSAGE_DELETE_WINDOW', 300); // seconds within which a sender may delete a message
define('CHAT_PREVIEW_MAX_LENGTH', 120);    // characters kept in room last_message_preview

switch ($action) {

    // ── GET ROOMS ─────────────────────────────────────────────────────────────
    case 'get_rooms':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $stmt = $db->prepare(
            'SELECT cr.*, cp.role AS my_role, cp.last_read_at,
                    (SELECT COUNT(*) FROM chat_messages cm
                     WHERE cm.room_id = cr.id AND cm.is_deleted = 0
                       AND cm.created_at > COALESCE(cp.last_read_at, "1970-01-01")
                       AND cm.sender_id != ?) AS unread_count
             FROM chat_rooms cr
             JOIN chat_participants cp ON cp.room_id = cr.id AND cp.user_id = ?
             WHERE cr.is_active = 1
             ORDER BY cr.last_message_at DESC'
        );
        $stmt->execute([$userId, $userId]);
        $rooms = $stmt->fetchAll();
        jsonOut(['success' => true, 'data' => $rooms]);
        break;

    // ── CREATE ROOM ───────────────────────────────────────────────────────────
    case 'create_room':
        requireAuth();
        validateCsrf();
        $userId       = $_SESSION['user_id'];
        $type         = $_POST['type'] ?? 'direct';
        $name         = trim($_POST['name'] ?? '');
        $participants = $_POST['participants'] ?? [];
        $orderId      = (int) ($_POST['order_id'] ?? 0) ?: null;
        $productId    = (int) ($_POST['product_id'] ?? 0) ?: null;

        if (!in_array($type, ['direct', 'order', 'inquiry', 'support', 'group'])) {
            jsonOut(['success' => false, 'message' => 'Invalid room type'], 400);
        }
        if (is_string($participants)) {
            $participants = json_decode($participants, true) ?? [];
        }
        $participants = array_map('intval', $participants);
        $participants = array_filter($participants);

        // For direct messages, return existing room if one already exists
        if ($type === 'direct') {
            if (count($participants) !== 1) {
                jsonOut(['success' => false, 'message' => 'Direct rooms require exactly one other participant'], 400);
            }
            $otherId = (int) $participants[0];
            $stmt = $db->prepare(
                'SELECT cr.* FROM chat_rooms cr
                 JOIN chat_participants cp1 ON cp1.room_id = cr.id AND cp1.user_id = ?
                 JOIN chat_participants cp2 ON cp2.room_id = cr.id AND cp2.user_id = ?
                 WHERE cr.type = "direct" AND cr.is_active = 1
                 LIMIT 1'
            );
            $stmt->execute([$userId, $otherId]);
            $existing = $stmt->fetch();
            if ($existing) {
                jsonOut(['success' => true, 'data' => $existing, 'existing' => true]);
            }
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'INSERT INTO chat_rooms (type, name, order_id, product_id, created_by, is_active)
                 VALUES (?, ?, ?, ?, ?, 1)'
            );
            $stmt->execute([$type, $name ?: null, $orderId, $productId, $userId]);
            $roomId = (int) $db->lastInsertId();

            // Add creator as admin
            $addPart = $db->prepare(
                'INSERT IGNORE INTO chat_participants (room_id, user_id, role) VALUES (?, ?, ?)'
            );
            $addPart->execute([$roomId, $userId, 'admin']);

            // Add other participants as members
            foreach ($participants as $pid) {
                if ($pid !== $userId) {
                    $addPart->execute([$roomId, $pid, 'member']);
                }
            }

            $db->commit();
            $stmt = $db->prepare('SELECT * FROM chat_rooms WHERE id = ?');
            $stmt->execute([$roomId]);
            $room = $stmt->fetch();
            jsonOut(['success' => true, 'data' => $room, 'existing' => false]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonOut(['success' => false, 'message' => 'Failed to create room'], 500);
        }
        break;

    // ── GET MESSAGES ──────────────────────────────────────────────────────────
    case 'get_messages':
        requireAuth();
        $userId   = $_SESSION['user_id'];
        $roomId   = (int) ($_GET['room_id'] ?? 0);
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $beforeId = (int) ($_GET['before_id'] ?? 0);

        if (!$roomId) {
            jsonOut(['success' => false, 'message' => 'room_id required'], 400);
        }

        // Verify user is participant
        $check = $db->prepare('SELECT id FROM chat_participants WHERE room_id = ? AND user_id = ?');
        $check->execute([$roomId, $userId]);
        if (!$check->fetch()) {
            jsonOut(['success' => false, 'message' => 'Access denied'], 403);
        }

        $params = [$roomId];
        $beforeSql = '';
        if ($beforeId > 0) {
            $beforeSql = ' AND cm.id < ?';
            $params[]  = $beforeId;
        }

        $offset = ($page - 1) * 50;
        $stmt = $db->prepare(
            'SELECT cm.*,
                    CONCAT(u.first_name, " ", u.last_name) AS sender_name,
                    u.avatar AS sender_avatar
             FROM chat_messages cm
             JOIN users u ON u.id = cm.sender_id
             WHERE cm.room_id = ? AND cm.is_deleted = 0' . $beforeSql . '
             ORDER BY cm.created_at DESC
             LIMIT 50 OFFSET ' . $offset
        );
        $stmt->execute($params);
        $messages = array_reverse($stmt->fetchAll());

        $countStmt = $db->prepare(
            'SELECT COUNT(*) FROM chat_messages WHERE room_id = ? AND is_deleted = 0'
        );
        $countStmt->execute([$roomId]);
        $total = (int) $countStmt->fetchColumn();

        jsonOut(['success' => true, 'data' => $messages, 'total' => $total]);
        break;

    // ── POLL FOR NEW MESSAGES ─────────────────────────────────────────────────
    case 'get_new':
        requireAuth();
        $userId  = $_SESSION['user_id'];
        $roomId  = (int) ($_GET['room_id'] ?? 0);
        $lastId  = (int) ($_GET['last_id'] ?? 0);

        if (!$roomId) {
            jsonOut(['success' => false, 'message' => 'room_id required'], 400);
        }

        $check = $db->prepare('SELECT id FROM chat_participants WHERE room_id = ? AND user_id = ?');
        $check->execute([$roomId, $userId]);
        if (!$check->fetch()) {
            jsonOut(['success' => false, 'message' => 'Access denied'], 403);
        }

        $stmt = $db->prepare(
            'SELECT cm.*,
                    CONCAT(u.first_name, " ", u.last_name) AS sender_name,
                    u.avatar AS sender_avatar
             FROM chat_messages cm
             JOIN users u ON u.id = cm.sender_id
             WHERE cm.room_id = ? AND cm.is_deleted = 0 AND cm.id > ?
             ORDER BY cm.created_at ASC'
        );
        $stmt->execute([$roomId, $lastId]);
        $messages = $stmt->fetchAll();
        jsonOut(['success' => true, 'data' => $messages]);
        break;

    // ── SEND MESSAGE ──────────────────────────────────────────────────────────
    case 'send':
        requireAuth();
        validateCsrf();
        $userId     = $_SESSION['user_id'];
        $roomId     = (int) ($_POST['room_id'] ?? 0);
        $message    = trim($_POST['message'] ?? '');
        $type       = $_POST['type'] ?? 'text';
        $fileUrl    = trim($_POST['file_url'] ?? '');
        $fileName   = trim($_POST['file_name'] ?? '');
        $fileSize   = (int) ($_POST['file_size'] ?? 0);
        $replyToId  = (int) ($_POST['reply_to_id'] ?? 0) ?: null;

        if (!$roomId) {
            jsonOut(['success' => false, 'message' => 'room_id required'], 400);
        }
        if (empty($message) && empty($fileUrl)) {
            jsonOut(['success' => false, 'message' => 'Message or file required'], 400);
        }
        if (!in_array($type, ['text', 'image', 'file', 'system', 'product_link'])) {
            $type = 'text';
        }

        // Verify sender is participant
        $check = $db->prepare('SELECT id FROM chat_participants WHERE room_id = ? AND user_id = ?');
        $check->execute([$roomId, $userId]);
        if (!$check->fetch()) {
            jsonOut(['success' => false, 'message' => 'You are not a participant in this room'], 403);
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'INSERT INTO chat_messages (room_id, sender_id, message, type, file_url, file_name, file_size, reply_to_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $roomId, $userId,
                $message ?: null,
                $type,
                $fileUrl ?: null,
                $fileName ?: null,
                $fileSize ?: null,
                $replyToId,
            ]);
            $msgId = (int) $db->lastInsertId();

            $preview = $message ?: ($fileName ?: '[file]');
            if (strlen($preview) > CHAT_PREVIEW_MAX_LENGTH) {
                $preview = substr($preview, 0, CHAT_PREVIEW_MAX_LENGTH) . '…';
            }
            $upd = $db->prepare(
                'UPDATE chat_rooms SET last_message_at = NOW(), last_message_preview = ? WHERE id = ?'
            );
            $upd->execute([$preview, $roomId]);

            $db->commit();

            $fetch = $db->prepare(
                'SELECT cm.*,
                        CONCAT(u.first_name, " ", u.last_name) AS sender_name,
                        u.avatar AS sender_avatar
                 FROM chat_messages cm
                 JOIN users u ON u.id = cm.sender_id
                 WHERE cm.id = ?'
            );
            $fetch->execute([$msgId]);
            jsonOut(['success' => true, 'data' => $fetch->fetch()]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonOut(['success' => false, 'message' => 'Failed to send message'], 500);
        }
        break;

    // ── MARK READ ─────────────────────────────────────────────────────────────
    case 'mark_read':
        requireAuth();
        validateCsrf();
        $userId    = $_SESSION['user_id'];
        $roomId    = (int) ($_POST['room_id'] ?? 0);
        $messageId = (int) ($_POST['message_id'] ?? 0);

        if (!$roomId || !$messageId) {
            jsonOut(['success' => false, 'message' => 'room_id and message_id required'], 400);
        }

        $check = $db->prepare('SELECT id FROM chat_participants WHERE room_id = ? AND user_id = ?');
        $check->execute([$roomId, $userId]);
        if (!$check->fetch()) {
            jsonOut(['success' => false, 'message' => 'Access denied'], 403);
        }

        // Update participant last_read_at
        $db->prepare(
            'UPDATE chat_participants SET last_read_at = NOW() WHERE room_id = ? AND user_id = ?'
        )->execute([$roomId, $userId]);

        // Insert read receipts for unread messages up to message_id
        $msgs = $db->prepare(
            'SELECT id FROM chat_messages
             WHERE room_id = ? AND id <= ? AND is_deleted = 0 AND sender_id != ?'
        );
        $msgs->execute([$roomId, $messageId, $userId]);
        $insertReceipt = $db->prepare(
            'INSERT IGNORE INTO message_read_receipts (message_id, user_id, read_at) VALUES (?, ?, NOW())'
        );
        foreach ($msgs->fetchAll() as $msg) {
            $insertReceipt->execute([$msg['id'], $userId]);
        }

        jsonOut(['success' => true, 'message' => 'Messages marked as read']);
        break;

    // ── ONLINE USERS ──────────────────────────────────────────────────────────
    case 'online_users':
        requireAuth();
        $stmt = $db->prepare(
            'SELECT uos.user_id, uos.is_online, uos.last_seen,
                    CONCAT(u.first_name, " ", u.last_name) AS name,
                    u.avatar
             FROM user_online_status uos
             JOIN users u ON u.id = uos.user_id
             WHERE uos.is_online = 1
             ORDER BY u.first_name ASC'
        );
        $stmt->execute();
        jsonOut(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    // ── DELETE MESSAGE ────────────────────────────────────────────────────────
    case 'delete_message':
        requireAuth();
        validateCsrf();
        $userId    = $_SESSION['user_id'];
        $messageId = (int) ($_POST['message_id'] ?? 0);

        if (!$messageId) {
            jsonOut(['success' => false, 'message' => 'message_id required'], 400);
        }

        $stmt = $db->prepare(
            'SELECT * FROM chat_messages WHERE id = ? AND is_deleted = 0'
        );
        $stmt->execute([$messageId]);
        $msg = $stmt->fetch();

        if (!$msg) {
            jsonOut(['success' => false, 'message' => 'Message not found'], 404);
        }
        if ((int) $msg['sender_id'] !== $userId) {
            jsonOut(['success' => false, 'message' => 'You can only delete your own messages'], 403);
        }
        $age = time() - strtotime($msg['created_at']);
        if ($age > CHAT_MESSAGE_DELETE_WINDOW) {
            jsonOut(['success' => false, 'message' => 'Messages can only be deleted within 5 minutes of sending'], 403);
        }

        $db->prepare('UPDATE chat_messages SET is_deleted = 1 WHERE id = ?')->execute([$messageId]);
        jsonOut(['success' => true, 'message' => 'Message deleted']);
        break;

    // ── UPLOAD FILE ───────────────────────────────────────────────────────────
    case 'upload_file':
        requireAuth();
        validateCsrf();

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            jsonOut(['success' => false, 'message' => 'No file uploaded or upload error'], 400);
        }

        $file     = $_FILES['file'];
        $maxSize  = 10 * 1024 * 1024;
        $allowed  = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'application/zip',
        ];

        if ($file['size'] > $maxSize) {
            jsonOut(['success' => false, 'message' => 'File exceeds 10 MB limit'], 400);
        }

        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, $allowed, true)) {
            jsonOut(['success' => false, 'message' => 'File type not allowed'], 400);
        }

        $uploadDir = UPLOAD_DIR . 'chat/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $allowedExts = [
            'jpg' => true, 'jpeg' => true, 'png' => true, 'gif' => true, 'webp' => true,
            'pdf' => true, 'doc' => true, 'docx' => true, 'xls' => true, 'xlsx' => true,
            'txt' => true, 'zip' => true,
        ];
        $rawExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $ext    = isset($allowedExts[$rawExt]) ? $rawExt : 'bin';
        $filename = generateUuid() . '.' . $ext;
        $dest     = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            jsonOut(['success' => false, 'message' => 'Failed to save file'], 500);
        }

        $fileUrl = UPLOAD_URL . 'chat/' . $filename;
        jsonOut([
            'success'   => true,
            'file_url'  => $fileUrl,
            'file_name' => $file['name'],
            'file_size' => $file['size'],
            'mime_type' => $mimeType,
        ]);
        break;

    // ── SEARCH MESSAGES ───────────────────────────────────────────────────────
    case 'search':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $query  = trim($_GET['q'] ?? '');

        if (strlen($query) < 2) {
            jsonOut(['success' => false, 'message' => 'Search query too short'], 400);
        }

        $stmt = $db->prepare(
            'SELECT cm.*,
                    CONCAT(u.first_name, " ", u.last_name) AS sender_name,
                    u.avatar AS sender_avatar,
                    cr.type AS room_type,
                    cr.name AS room_name
             FROM chat_messages cm
             JOIN chat_participants cp ON cp.room_id = cm.room_id AND cp.user_id = ?
             JOIN users u ON u.id = cm.sender_id
             JOIN chat_rooms cr ON cr.id = cm.room_id
             WHERE cm.is_deleted = 0
               AND cm.message LIKE ?
             ORDER BY cm.created_at DESC
             LIMIT 50'
        );
        $stmt->execute([$userId, '%' . $query . '%']);
        jsonOut(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    default:
        jsonOut(['success' => false, 'message' => 'Invalid action'], 400);
}
