<?php
/**
 * api/chat.php — Real-Time Chat API (PR #18)
 *
 * 10 actions:
 *   GET  action=conversations     — List user's conversations
 *   GET  action=conversation      — Get single conversation detail
 *   POST action=create            — Create conversation
 *   GET  action=messages          — Get messages for conversation (paginated)
 *   POST action=send              — Send message
 *   POST action=read              — Mark conversation as read
 *   GET  action=unread_count      — Get total unread count
 *   GET  action=search            — Search messages
 *   POST action=delete_message    — Delete a message
 *   POST action=edit_message      — Edit a message
 *
 * Feature toggle: real_time_chat
 */

require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/chat.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOut($data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function validateCsrf(): void
{
    if (!verifyCsrf()) {
        jsonOut(['error' => 'Invalid CSRF token'], 403);
    }
}

requireAuth();

if (!isFeatureEnabled('real_time_chat')) {
    jsonOut(['error' => 'Feature not available'], 503);
}

$userId = (int) $_SESSION['user_id'];

switch ($action) {

    // ── GET CONVERSATIONS ──────────────────────────────────────────────────
    case 'conversations':
        $page    = max(1, (int) ($_GET['page']     ?? 1));
        $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));
        $result  = getConversations($userId, $page, $perPage);
        jsonOut(['success' => true, 'data' => $result['data'], 'total' => $result['total'], 'page' => $page, 'per_page' => $perPage]);

    // ── GET SINGLE CONVERSATION ───────────────────────────────────────────
    case 'conversation':
        $convId = (int) ($_GET['conversation_id'] ?? 0);
        if (!$convId) jsonOut(['error' => 'conversation_id required'], 400);
        $conv = getConversation($convId, $userId);
        if (!$conv) jsonOut(['error' => 'Conversation not found or access denied'], 404);
        jsonOut(['success' => true, 'data' => $conv]);

    // ── CREATE CONVERSATION ───────────────────────────────────────────────
    case 'create':
        validateCsrf();
        $type         = $_POST['type']  ?? 'direct';
        $title        = trim($_POST['title'] ?? '');
        $participants = $_POST['participants'] ?? [];
        if (is_string($participants)) {
            $participants = json_decode($participants, true) ?? [];
        }
        $participants = array_filter(array_map('intval', $participants));
        if (!in_array($userId, $participants, true)) {
            $participants[] = $userId;
        }

        $convId = createConversation(array_values($participants), $type, $title, $userId);
        if ($convId === false) {
            jsonOut(['error' => 'Failed to create conversation'], 500);
        }
        $conv = getConversation($convId, $userId);
        jsonOut(['success' => true, 'data' => $conv], 201);

    // ── GET MESSAGES ──────────────────────────────────────────────────────
    case 'messages':
        $convId  = (int) ($_GET['conversation_id'] ?? 0);
        $page    = max(1, (int) ($_GET['page']     ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 50)));
        if (!$convId) jsonOut(['error' => 'conversation_id required'], 400);
        $result = getMessages($convId, $userId, $page, $perPage);
        jsonOut(['success' => true, 'data' => $result['data'], 'total' => $result['total'], 'page' => $page, 'per_page' => $perPage]);

    // ── SEND MESSAGE ──────────────────────────────────────────────────────
    case 'send':
        validateCsrf();
        $convId  = (int) ($_POST['conversation_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        $type    = $_POST['type'] ?? 'text';

        // Handle file attachment metadata
        $attachments = [];
        if (!empty($_POST['attachment'])) {
            $att = is_string($_POST['attachment'])
                ? (json_decode($_POST['attachment'], true) ?? [])
                : (array) $_POST['attachment'];
            if (!empty($att)) $attachments = [$att];
        }

        if (!$convId) jsonOut(['error' => 'conversation_id required'], 400);
        if ($content === '' && empty($attachments)) {
            jsonOut(['error' => 'content or attachment required'], 400);
        }

        $msgId = sendMessage($convId, $userId, $content, $type, $attachments);
        if ($msgId === false) {
            jsonOut(['error' => 'Failed to send message — check conversation access'], 400);
        }
        jsonOut(['success' => true, 'message_id' => $msgId]);

    // ── MARK AS READ ──────────────────────────────────────────────────────
    case 'read':
        validateCsrf();
        $convId = (int) ($_POST['conversation_id'] ?? 0);
        if (!$convId) jsonOut(['error' => 'conversation_id required'], 400);
        $ok = markAsRead($convId, $userId);
        if (!$ok) jsonOut(['error' => 'Access denied or conversation not found'], 403);
        jsonOut(['success' => true]);

    // ── UNREAD COUNT ──────────────────────────────────────────────────────
    case 'unread_count':
        $count = getUnreadCount($userId);
        jsonOut(['success' => true, 'unread_count' => $count]);

    // ── SEARCH ────────────────────────────────────────────────────────────
    case 'search':
        $query   = trim($_GET['q'] ?? '');
        $page    = max(1, (int) ($_GET['page']     ?? 1));
        $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));
        if (strlen($query) < 2) jsonOut(['error' => 'Search query too short (min 2 chars)'], 400);
        $result = searchMessages($userId, $query, $page, $perPage);
        jsonOut(['success' => true, 'data' => $result['data'], 'total' => $result['total']]);

    // ── DELETE MESSAGE ────────────────────────────────────────────────────
    case 'delete_message':
        validateCsrf();
        $msgId = (int) ($_POST['message_id'] ?? 0);
        if (!$msgId) jsonOut(['error' => 'message_id required'], 400);
        $result = deleteMessage($msgId, $userId);
        if ($result === 'not_found')  jsonOut(['error' => 'Message not found'], 404);
        if ($result === 'forbidden')  jsonOut(['error' => 'You can only delete your own messages'], 403);
        if (!$result)                 jsonOut(['error' => 'Failed to delete message'], 500);
        jsonOut(['success' => true]);

    // ── EDIT MESSAGE ──────────────────────────────────────────────────────
    case 'edit_message':
        validateCsrf();
        $msgId      = (int) ($_POST['message_id'] ?? 0);
        $newContent = trim($_POST['content'] ?? '');
        if (!$msgId)        jsonOut(['error' => 'message_id required'], 400);
        if ($newContent === '') jsonOut(['error' => 'content required'], 400);
        $result = editMessage($msgId, $userId, $newContent);
        if ($result === 'not_found')           jsonOut(['error' => 'Message not found'], 404);
        if ($result === 'forbidden')           jsonOut(['error' => 'You can only edit your own messages'], 403);
        if ($result === 'edit_window_expired') jsonOut(['error' => 'Message can only be edited within 15 minutes of sending'], 403);
        if (!$result)                          jsonOut(['error' => 'Failed to edit message'], 500);
        jsonOut(['success' => true]);

    default:
        jsonOut(['error' => 'Invalid action'], 400);
}
