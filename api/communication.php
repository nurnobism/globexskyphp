<?php
require_once __DIR__ . '/../includes/middleware.php';
header('Content-Type: application/json');
$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOut($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

switch ($action) {
    case 'list_messages':
        requireAuth();
        $user   = getCurrentUser();
        $page   = (int)($_GET['page'] ?? 1);
        $sql    = 'SELECT id, sender_id, subject, read_at, created_at FROM messages WHERE recipient_id = ? ORDER BY created_at DESC';
        $result = paginate($db, $sql, [$user['id']], $page);
        jsonOut(['success' => true, 'data' => $result]);
    break;

    case 'send_message':
        requireAuth();
        validateCsrf();
        $user         = getCurrentUser();
        $recipient_id = (int)($_POST['recipient_id'] ?? 0);
        $subject      = sanitize($_POST['subject'] ?? '');
        $content      = sanitize($_POST['content'] ?? '');
        $thread_id    = (int)($_POST['thread_id'] ?? 0);
        if ($recipient_id <= 0 || $content === '') {
            jsonOut(['success' => false, 'message' => 'recipient_id and content are required'], 400);
        }
        $recipientCheck = $db->prepare('SELECT id FROM users WHERE id = ?');
        $recipientCheck->execute([$recipient_id]);
        if (!$recipientCheck->fetch()) jsonOut(['success' => false, 'message' => 'Recipient not found'], 404);
        $stmt = $db->prepare(
            'INSERT INTO messages (sender_id, recipient_id, subject, content, thread_id, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$user['id'], $recipient_id, $subject, $content, $thread_id ?: null]);
        $newId = $db->lastInsertId();
        jsonOut(['success' => true, 'message' => 'Message sent', 'id' => $newId], 201);
    break;

    case 'get_thread':
        requireAuth();
        $user      = getCurrentUser();
        $thread_id = (int)($_GET['thread_id'] ?? 0);
        if ($thread_id <= 0) jsonOut(['success' => false, 'message' => 'thread_id required'], 400);
        $stmt = $db->prepare(
            'SELECT * FROM messages
             WHERE thread_id = ? AND (sender_id = ? OR recipient_id = ?)
             ORDER BY created_at ASC'
        );
        $stmt->execute([$thread_id, $user['id'], $user['id']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonOut(['success' => true, 'data' => $rows]);
    break;

    case 'list_rooms':
        requireAuth();
        $user = getCurrentUser();
        $stmt = $db->prepare(
            'SELECT cr.* FROM chat_rooms cr
             JOIN chat_room_members crm ON crm.room_id = cr.id
             WHERE crm.user_id = ?
             ORDER BY cr.created_at DESC'
        );
        $stmt->execute([$user['id']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonOut(['success' => true, 'data' => $rows]);
    break;

    case 'create_room':
        requireAuth();
        validateCsrf();
        $user    = getCurrentUser();
        $name    = sanitize($_POST['name'] ?? '');
        $members = $_POST['members'] ?? [];
        if ($name === '') jsonOut(['success' => false, 'message' => 'Room name is required'], 400);
        $db->prepare('INSERT INTO chat_rooms (name, created_by, created_at) VALUES (?, ?, NOW())')
           ->execute([$name, $user['id']]);
        $roomId = $db->lastInsertId();
        $db->prepare('INSERT INTO chat_room_members (room_id, user_id, joined_at) VALUES (?, ?, NOW())')
           ->execute([$roomId, $user['id']]);
        if (is_array($members)) {
            $insertMember = $db->prepare(
                'INSERT IGNORE INTO chat_room_members (room_id, user_id, joined_at) VALUES (?, ?, NOW())'
            );
            foreach ($members as $memberId) {
                $memberId = (int)$memberId;
                if ($memberId > 0 && $memberId !== (int)$user['id']) {
                    $insertMember->execute([$roomId, $memberId]);
                }
            }
        }
        jsonOut(['success' => true, 'message' => 'Room created', 'id' => $roomId], 201);
    break;

    case 'send_chat':
        requireAuth();
        validateCsrf();
        $user    = getCurrentUser();
        $room_id = (int)($_POST['room_id'] ?? 0);
        $content = sanitize($_POST['content'] ?? '');
        if ($room_id <= 0 || $content === '') {
            jsonOut(['success' => false, 'message' => 'room_id and content are required'], 400);
        }
        $membership = $db->prepare(
            'SELECT 1 FROM chat_room_members WHERE room_id = ? AND user_id = ?'
        );
        $membership->execute([$room_id, $user['id']]);
        if (!$membership->fetch()) jsonOut(['success' => false, 'message' => 'Not a room member'], 403);
        $stmt = $db->prepare(
            'INSERT INTO chat_messages (room_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$room_id, $user['id'], $content]);
        $newId = $db->lastInsertId();
        jsonOut(['success' => true, 'message' => 'Message sent', 'id' => $newId], 201);
    break;

    case 'get_history':
        requireAuth();
        $user    = getCurrentUser();
        $room_id = (int)($_GET['room_id'] ?? 0);
        if ($room_id <= 0) jsonOut(['success' => false, 'message' => 'room_id required'], 400);
        $membership = $db->prepare(
            'SELECT 1 FROM chat_room_members WHERE room_id = ? AND user_id = ?'
        );
        $membership->execute([$room_id, $user['id']]);
        if (!$membership->fetch()) jsonOut(['success' => false, 'message' => 'Not a room member'], 403);
        $page   = (int)($_GET['page'] ?? 1);
        $sql    = 'SELECT * FROM chat_messages WHERE room_id = ? ORDER BY created_at ASC';
        $result = paginate($db, $sql, [$room_id], $page);
        jsonOut(['success' => true, 'data' => $result]);
    break;

    case 'mark_read':
        requireAuth();
        validateCsrf();
        $user = getCurrentUser();
        $id   = (int)($_POST['id'] ?? 0);
        if ($id <= 0) jsonOut(['success' => false, 'message' => 'id required'], 400);
        $stmt = $db->prepare(
            'UPDATE messages SET read_at = NOW() WHERE id = ? AND recipient_id = ? AND read_at IS NULL'
        );
        $stmt->execute([$id, $user['id']]);
        if ($stmt->rowCount() === 0) {
            jsonOut(['success' => false, 'message' => 'Message not found or already read'], 404);
        }
        jsonOut(['success' => true, 'message' => 'Message marked as read']);
    break;

    default:
        jsonOut(['success' => false, 'message' => 'Invalid action'], 400);
}
