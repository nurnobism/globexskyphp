<?php
/**
 * includes/ai-chatbot.php — AI Chatbot Logic
 *
 * Manages conversations and messages using the DeepSeek AI engine.
 * Requires includes/ai-engine.php.
 */

require_once __DIR__ . '/ai-engine.php';

/**
 * Start a new AI conversation for a user.
 *
 * @return int|null  New conversation ID or null on failure
 */
function startConversation(int $userId, string $contextType = 'general'): ?int
{
    $sessionId = bin2hex(random_bytes(16));
    $validTypes = ['general', 'product_search', 'sourcing', 'support', 'fraud_review', 'admin'];
    if (!in_array($contextType, $validTypes, true)) {
        $contextType = 'general';
    }

    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'INSERT INTO ai_conversations (user_id, session_id, context_type, title)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $sessionId, $contextType, 'New Conversation']);
        return (int)$db->lastInsertId();
    } catch (Throwable $e) {
        error_log('startConversation error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Send a user message to the AI and return the AI response.
 *
 * @return array ['success' => bool, 'message' => string, 'conversation_id' => int, 'error' => string|null]
 */
function sendMessage(int $conversationId, string $userMessage, int $userId = 0): array
{
    if (empty(trim($userMessage))) {
        return ['success' => false, 'message' => '', 'conversation_id' => $conversationId, 'error' => 'Empty message'];
    }

    try {
        $db = getDB();

        // Load conversation context
        $stmt = $db->prepare('SELECT context_type FROM ai_conversations WHERE id = ? LIMIT 1');
        $stmt->execute([$conversationId]);
        $conv = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$conv) {
            return ['success' => false, 'message' => '', 'conversation_id' => $conversationId, 'error' => 'Conversation not found'];
        }
        $contextType = $conv['context_type'];

        // Load recent history (last 20 messages)
        $stmt = $db->prepare(
            'SELECT role, content FROM ai_messages
             WHERE conversation_id = ?
             ORDER BY id DESC LIMIT 20'
        );
        $stmt->execute([$conversationId]);
        $history = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

        // Build messages array
        $messages = [['role' => 'system', 'content' => buildSystemPrompt($contextType)]];
        foreach ($history as $h) {
            if (in_array($h['role'], ['user', 'assistant'], true)) {
                $messages[] = ['role' => $h['role'], 'content' => $h['content']];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        // Call DeepSeek
        $start  = microtime(true);
        $result = deepseekRequest($messages, [
            'user_id' => $userId,
            'feature' => 'chatbot',
            'temperature' => (float)getAiConfig('temperature_chat', '0.7'),
            'max_tokens'  => (int)getAiConfig('max_tokens_chat', '2048'),
        ]);
        $responseMs = (int)((microtime(true) - $start) * 1000);

        if (!$result['success']) {
            return [
                'success'         => false,
                'message'         => '',
                'conversation_id' => $conversationId,
                'error'           => $result['error'] ?? 'AI unavailable',
            ];
        }

        $aiContent = $result['content'];
        $tokens    = $result['tokens'];

        // Save user message
        $stmt = $db->prepare(
            'INSERT INTO ai_messages (conversation_id, role, content, tokens) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$conversationId, 'user', $userMessage, 0]);

        // Save AI response
        $stmt = $db->prepare(
            'INSERT INTO ai_messages (conversation_id, role, content, tokens, response_time_ms)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $conversationId,
            'assistant',
            $aiContent,
            (int)($tokens['completion_tokens'] ?? 0),
            $responseMs,
        ]);

        // Update conversation metadata
        $totalTokens = (int)($tokens['total_tokens'] ?? 0);
        $stmt = $db->prepare(
            'UPDATE ai_conversations
             SET messages_count = messages_count + 2,
                 tokens_used    = tokens_used + ?,
                 updated_at     = NOW()
             WHERE id = ?'
        );
        $stmt->execute([$totalTokens, $conversationId]);

        // Auto-generate title from first user message
        $stmt = $db->prepare('SELECT messages_count FROM ai_conversations WHERE id = ?');
        $stmt->execute([$conversationId]);
        $msgCount = (int)$stmt->fetchColumn();
        if ($msgCount <= 2) {
            $title = mb_substr($userMessage, 0, 80);
            $db->prepare('UPDATE ai_conversations SET title = ? WHERE id = ?')
               ->execute([$title, $conversationId]);
        }

        return [
            'success'         => true,
            'message'         => $aiContent,
            'conversation_id' => $conversationId,
            'error'           => null,
        ];
    } catch (Throwable $e) {
        error_log('sendMessage error: ' . $e->getMessage());
        return ['success' => false, 'message' => '', 'conversation_id' => $conversationId, 'error' => 'Server error'];
    }
}

/**
 * List a user's conversations (most recent first).
 */
function getConversations(int $userId, int $limit = 20, int $offset = 0): array
{
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT id, title, context_type, messages_count, tokens_used, updated_at
             FROM ai_conversations
             WHERE user_id = ? AND is_active = 1
             ORDER BY updated_at DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Get all messages in a conversation (oldest first).
 */
function getConversationMessages(int $conversationId): array
{
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            "SELECT role, content, tokens, created_at
             FROM ai_messages
             WHERE conversation_id = ? AND role != 'system'
             ORDER BY id ASC"
        );
        $stmt->execute([$conversationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Soft-delete a conversation.
 */
function deleteConversation(int $conversationId, int $userId): bool
{
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'UPDATE ai_conversations SET is_active = 0 WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$conversationId, $userId]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Return context-specific quick action buttons.
 *
 * @return array  Array of ['label' => string, 'message' => string]
 */
function getQuickActions(string $contextType): array
{
    return match ($contextType) {
        'product_search' => [
            ['label' => 'Find suppliers for...', 'message' => 'Help me find reliable suppliers for '],
            ['label' => 'Compare prices for...', 'message' => 'Compare prices for '],
            ['label' => 'Quality check for...', 'message' => 'What quality certifications should I look for when buying '],
        ],
        'sourcing' => [
            ['label' => 'Source from China',       'message' => 'I want to source products from China. Can you guide me through the process?'],
            ['label' => 'Find manufacturer for...', 'message' => 'Help me find a manufacturer for '],
            ['label' => 'MOQ negotiation help',     'message' => 'How do I negotiate minimum order quantities with suppliers?'],
        ],
        'support' => [
            ['label' => 'Track my order',  'message' => 'How do I track my order?'],
            ['label' => 'Return policy',   'message' => 'What is the return policy on GlobexSky?'],
            ['label' => 'Payment issue',   'message' => 'I have an issue with my payment.'],
        ],
        default => [
            ['label' => 'How does GlobexSky work?', 'message' => 'Can you explain how GlobexSky works?'],
            ['label' => 'Find products',             'message' => 'Help me find products on GlobexSky'],
            ['label' => 'Contact support',           'message' => 'I need help with my account'],
        ],
    };
}
