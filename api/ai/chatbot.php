<?php
/**
 * api/ai/chatbot.php — AI Chatbot Endpoint
 * POST  {message, conversation_id?, context?}
 */

require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/deepseek.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

if (!verifyCsrf()) {
    jsonResponse(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
// Also support form-post
$message        = trim($input['message']        ?? post('message', ''));
$conversationId = trim($input['conversation_id'] ?? post('conversation_id', ''));
$context        = $input['context']              ?? [];

if ($message === '') {
    jsonResponse(['success' => false, 'message' => 'Message is required.'], 400);
}
if (strlen($message) > 2000) {
    jsonResponse(['success' => false, 'message' => 'Message too long (max 2000 characters).'], 400);
}

$db     = getDB();
$userId = $_SESSION['user_id'] ?? null;

// Ensure ai_chat_history table exists
$db->exec("CREATE TABLE IF NOT EXISTS ai_chat_history (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id VARCHAR(64)   NOT NULL,
    user_id         INT UNSIGNED  NULL,
    role            ENUM('user','assistant') NOT NULL,
    content         TEXT          NOT NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_conv  (conversation_id),
    INDEX idx_user  (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Generate or validate conversation ID
if ($conversationId === '') {
    $conversationId = bin2hex(random_bytes(16));
} else {
    // Basic validation — alphanumeric/hex only
    $conversationId = preg_replace('/[^a-zA-Z0-9_-]/', '', $conversationId);
    if (strlen($conversationId) > 64) {
        $conversationId = substr($conversationId, 0, 64);
    }
}

// Load recent conversation history (last 10 exchanges = 20 rows)
$historyStmt = $db->prepare(
    'SELECT role, content FROM ai_chat_history
     WHERE conversation_id = ?
     ORDER BY created_at DESC LIMIT 20'
);
$historyStmt->execute([$conversationId]);
$historyRows = array_reverse($historyStmt->fetchAll(PDO::FETCH_ASSOC));

// Build messages array for DeepSeek
$systemPrompt = 'You are GlobexBot, the helpful AI assistant for GlobexSky — a global B2B marketplace. '
              . 'You help buyers find products, understand orders, navigate the platform, and answer trade questions. '
              . 'Be concise, professional, and friendly. If asked about specific orders or account details, '
              . 'ask the user to provide their order number or log in. Never fabricate product prices or availability.';

if (!empty($context)) {
    $systemPrompt .= "\n\nContext about the current user/session: " . json_encode($context);
}

// Save user message
$db->prepare(
    'INSERT INTO ai_chat_history (conversation_id, user_id, role, content, created_at)
     VALUES (?, ?, "user", ?, NOW())'
)->execute([$conversationId, $userId, $message]);

// Call DeepSeek with conversation history
try {
    $deepseek = getDeepSeek();

    // Build full messages with history
    $messages = [['role' => 'system', 'content' => $systemPrompt]];
    foreach ($historyRows as $row) {
        $messages[] = ['role' => $row['role'], 'content' => $row['content']];
    }
    $messages[] = ['role' => 'user', 'content' => $message];

    // Use the raw API call with full message history
    $payload = [
        'model'       => 'deepseek-chat',
        'messages'    => $messages,
        'temperature' => 0.7,
        'max_tokens'  => 800,
    ];

    // Access private config via getDeepSeek helper
    $aiConfig  = require __DIR__ . '/../../config/ai.php';
    $apiKey    = $aiConfig['deepseek']['api_key'];
    $baseUrl   = rtrim($aiConfig['deepseek']['base_url'], '/');

    $ch = curl_init($baseUrl . '/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $result   = curl_exec($ch);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        throw new RuntimeException('cURL error: ' . $curlErr);
    }

    $data     = json_decode($result, true);
    $response = $data['choices'][0]['message']['content'] ?? 'I apologize, I could not generate a response right now. Please try again.';

} catch (Throwable $e) {
    error_log('AI chatbot error: ' . $e->getMessage());
    $response = 'I apologize, I encountered an issue. Please try again in a moment.';
}

// Save assistant response
$db->prepare(
    'INSERT INTO ai_chat_history (conversation_id, user_id, role, content, created_at)
     VALUES (?, ?, "assistant", ?, NOW())'
)->execute([$conversationId, $userId, $response]);

jsonResponse([
    'success'         => true,
    'response'        => $response,
    'conversation_id' => $conversationId,
    'timestamp'       => date('Y-m-d H:i:s'),
]);
