<?php
/**
 * api/ai/chatbot.php — AI Chatbot Endpoint (Phase 8)
 * Supports: POST/GET with action param
 */

require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/deepseek.php';

header('Content-Type: application/json');
requireLogin();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // POST action=send — send a message and get AI response
    case 'send':
        if ($method !== 'POST') { jsonResponse(['success' => false, 'error' => 'POST required'], 405); }
        $message = trim($input['message'] ?? '');
        $convId  = (int)($input['conversation_id'] ?? 0);
        $context = $input['context'] ?? 'general';

        if ($message === '') { jsonResponse(['success' => false, 'error' => 'Message required'], 400); }
        if (strlen($message) > 4000) { jsonResponse(['success' => false, 'error' => 'Message too long'], 400); }

        // Create or verify conversation
        if ($convId > 0) {
            $stmt = $db->prepare('SELECT id FROM ai_conversations WHERE id = ? AND user_id = ?');
            $stmt->execute([$convId, $userId]);
            if (!$stmt->fetch()) { jsonResponse(['success' => false, 'error' => 'Conversation not found'], 404); }
        } else {
            $sessionId = bin2hex(random_bytes(16));
            $title     = mb_substr($message, 0, 100);
            $db->prepare(
                'INSERT INTO ai_conversations (user_id, session_id, title, context_type) VALUES (?,?,?,?)'
            )->execute([$userId, $sessionId, $title, $context]);
            $convId = (int)$db->lastInsertId();
        }

        try {
            $ai       = getDeepSeek();
            $response = $ai->chatWithHistory($convId, $message);
            jsonResponse(['success' => true, 'data' => ['response' => $response, 'conversation_id' => $convId]]);
        } catch (Throwable $e) {
            error_log('Chatbot send error: ' . $e->getMessage());
            jsonResponse(['success' => false, 'error' => 'AI service temporarily unavailable'], 503);
        }
        break;

    // GET action=conversations — list user conversations
    case 'conversations':
        try {
            $stmt = $db->prepare(
                'SELECT id, title, context_type, message_count, created_at, updated_at
                 FROM ai_conversations WHERE user_id = ? AND is_archived = 0
                 ORDER BY updated_at DESC LIMIT 50'
            );
            $stmt->execute([$userId]);
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            jsonResponse(['success' => false, 'error' => 'Database error'], 500);
        }
        break;

    // GET action=messages&conversation_id=X — get messages
    case 'messages':
        $convId = (int)($_GET['conversation_id'] ?? 0);
        if (!$convId) { jsonResponse(['success' => false, 'error' => 'conversation_id required'], 400); }

        try {
            // Verify ownership
            $ownerStmt = $db->prepare('SELECT id, title, context_type FROM ai_conversations WHERE id = ? AND user_id = ?');
            $ownerStmt->execute([$convId, $userId]);
            $conv = $ownerStmt->fetch(PDO::FETCH_ASSOC);
            if (!$conv) { jsonResponse(['success' => false, 'error' => 'Not found'], 404); }

            $msgStmt = $db->prepare('SELECT id, role, content, created_at FROM ai_messages WHERE conversation_id = ? ORDER BY created_at ASC');
            $msgStmt->execute([$convId]);
            jsonResponse(['success' => true, 'data' => ['conversation' => $conv, 'messages' => $msgStmt->fetchAll(PDO::FETCH_ASSOC)]]);
        } catch (PDOException $e) {
            jsonResponse(['success' => false, 'error' => 'Database error'], 500);
        }
        break;

    // POST action=new_conversation — start fresh conversation
    case 'new_conversation':
        if ($method !== 'POST') { jsonResponse(['success' => false, 'error' => 'POST required'], 405); }
        $context   = $input['context']    ?? 'general';
        $title     = $input['title']      ?? 'New conversation';
        $contextId = (int)($input['context_id'] ?? 0);
        $sessionId = bin2hex(random_bytes(16));

        try {
            $db->prepare(
                'INSERT INTO ai_conversations (user_id, session_id, title, context_type, context_id) VALUES (?,?,?,?,?)'
            )->execute([$userId, $sessionId, $title, $context, $contextId ?: null]);
            jsonResponse(['success' => true, 'data' => ['conversation_id' => (int)$db->lastInsertId()]]);
        } catch (PDOException $e) {
            jsonResponse(['success' => false, 'error' => 'Database error'], 500);
        }
        break;

    // DELETE action=delete_conversation — archive conversation
    case 'delete_conversation':
        $convId = (int)($input['conversation_id'] ?? $_GET['conversation_id'] ?? 0);
        if (!$convId) { jsonResponse(['success' => false, 'error' => 'conversation_id required'], 400); }

        try {
            $db->prepare('UPDATE ai_conversations SET is_archived = 1 WHERE id = ? AND user_id = ?')
               ->execute([$convId, $userId]);
            jsonResponse(['success' => true]);
        } catch (PDOException $e) {
            jsonResponse(['success' => false, 'error' => 'Database error'], 500);
        }
        break;

    // POST action=rename_conversation — rename a conversation
    case 'rename_conversation':
        if ($method !== 'POST') { jsonResponse(['success' => false, 'error' => 'POST required'], 405); }
        $convId   = (int)($input['conversation_id'] ?? 0);
        $newTitle = trim($input['title'] ?? '');
        if (!$convId || !$newTitle) { jsonResponse(['success' => false, 'error' => 'conversation_id and title required'], 400); }

        try {
            $db->prepare('UPDATE ai_conversations SET title = ? WHERE id = ? AND user_id = ?')
               ->execute([mb_substr($newTitle, 0, 300), $convId, $userId]);
            jsonResponse(['success' => true]);
        } catch (PDOException $e) {
            jsonResponse(['success' => false, 'error' => 'Database error'], 500);
        }
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
}
