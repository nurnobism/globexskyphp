<?php
/**
 * api/ai.php — AI API Endpoint
 *
 * Actions: chat_send, chat_history, chat_conversations, chat_delete,
 *          recommendations, recommendation_click, search_enhanced,
 *          search_suggest, generate_description, generate_seo,
 *          summarize_reviews, translate, fraud_check, fraud_dashboard,
 *          fraud_resolve, usage_stats, admin_config, admin_usage, health
 */

require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/ai-engine.php';
require_once __DIR__ . '/../includes/ai-chatbot.php';
require_once __DIR__ . '/../includes/ai-recommendations.php';
require_once __DIR__ . '/../includes/ai-fraud.php';
require_once __DIR__ . '/../includes/ai-search.php';
require_once __DIR__ . '/../includes/ai-content.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';
$userId = isLoggedIn() ? (int)$_SESSION['user_id'] : 0;

// ── Auth helpers ──────────────────────────────────────────────
function requireAuthJson(): void
{
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        exit;
    }
}

function requireAdminJson(): void
{
    if (!isLoggedIn() || !isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        exit;
    }
}

function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function jsonError(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// ── Route ─────────────────────────────────────────────────────
switch ($action) {

    // ── Chat: send message ────────────────────────────────────
    case 'chat_send':
        requireAuthJson();
        if (!isAiEnabled('chatbot')) {
            jsonError('AI chatbot is currently disabled', 503);
        }
        $budget = checkTokenLimit($userId);
        if (!$budget['allowed']) {
            jsonError('Daily AI token limit reached. Upgrade your plan for more.', 429);
        }
        $message         = trim((string)($_POST['message'] ?? ''));
        $conversationId  = (int)($_POST['conversation_id'] ?? 0);
        $contextType     = (string)($_POST['context_type'] ?? 'general');

        if (empty($message)) {
            jsonError('Message is required');
        }

        if ($conversationId <= 0) {
            $conversationId = startConversation($userId, $contextType);
            if (!$conversationId) {
                jsonError('Could not start conversation', 500);
            }
        }

        $result = sendMessage($conversationId, $message, $userId);
        jsonResponse([
            'success'         => $result['success'],
            'message'         => $result['message'],
            'conversation_id' => $result['conversation_id'],
            'error'           => $result['error'],
        ]);

    // ── Chat: get conversation messages ──────────────────────
    case 'chat_history':
        requireAuthJson();
        $conversationId = (int)($_GET['conversation_id'] ?? 0);
        if ($conversationId <= 0) {
            jsonError('conversation_id required');
        }
        $messages = getConversationMessages($conversationId);
        jsonResponse(['success' => true, 'messages' => $messages]);

    // ── Chat: list conversations ──────────────────────────────
    case 'chat_conversations':
        requireAuthJson();
        $limit  = min(50, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        $convs  = getConversations($userId, $limit, $offset);
        jsonResponse(['success' => true, 'conversations' => $convs]);

    // ── Chat: delete conversation ─────────────────────────────
    case 'chat_delete':
        requireAuthJson();
        $conversationId = (int)($_POST['conversation_id'] ?? 0);
        $ok = deleteConversation($conversationId, $userId);
        jsonResponse(['success' => $ok]);

    // ── Recommendations: get for current user ─────────────────
    case 'recommendations':
        requireAuthJson();
        if (!isAiEnabled('recommendations')) {
            jsonError('AI recommendations are disabled', 503);
        }
        $type  = (string)($_GET['type'] ?? 'personalized');
        $limit = min(20, max(1, (int)($_GET['limit'] ?? 8)));
        $recs  = getRecommendationsForUser($userId, $type, $limit);
        jsonResponse(['success' => true, 'recommendations' => $recs]);

    // ── Recommendations: track click ─────────────────────────
    case 'recommendation_click':
        requireAuthJson();
        $recId = (int)($_POST['recommendation_id'] ?? 0);
        if ($recId > 0) {
            trackRecommendationClick($recId);
        }
        jsonResponse(['success' => true]);

    // ── AI search ─────────────────────────────────────────────
    case 'search_enhanced':
        $query  = trim((string)($_GET['q'] ?? $_POST['q'] ?? ''));
        if (empty($query)) {
            jsonError('Query parameter q is required');
        }
        $result = enhancedSearch($query, $userId ?: null);
        jsonResponse(['success' => true] + $result);

    // ── Search autocomplete ───────────────────────────────────
    case 'search_suggest':
        $q           = trim((string)($_GET['q'] ?? ''));
        $suggestions = suggestSearchTerms($q);
        jsonResponse(['success' => true, 'suggestions' => $suggestions]);

    // ── Content: generate product description ─────────────────
    case 'generate_description':
        requireAuthJson();
        if (!isAiEnabled('content')) {
            jsonError('AI content generation is disabled', 503);
        }
        $productData = json_decode((string)($_POST['product_data'] ?? '{}'), true) ?? [];
        $style       = (string)($_POST['style'] ?? 'professional');
        $description = generateProductDescription($productData, $style);
        jsonResponse(['success' => !empty($description), 'description' => $description]);

    // ── Content: generate SEO meta ────────────────────────────
    case 'generate_seo':
        requireAuthJson();
        $pageData = json_decode((string)($_POST['page_data'] ?? '{}'), true) ?? [];
        $seo      = generateSeoMeta($pageData);
        jsonResponse(['success' => true] + $seo);

    // ── Content: summarise reviews ────────────────────────────
    case 'summarize_reviews':
        $productId = (int)($_GET['product_id'] ?? 0);
        if ($productId <= 0) {
            jsonError('product_id required');
        }
        $summary = summarizeReviews($productId);
        jsonResponse(['success' => true, 'summary' => $summary]);

    // ── Content: translate ────────────────────────────────────
    case 'translate':
        requireAuthJson();
        $content      = (string)($_POST['content'] ?? '');
        $targetLang   = (string)($_POST['target_language'] ?? 'English');
        $translated   = translateContent($content, $targetLang);
        jsonResponse(['success' => true, 'translated' => $translated]);

    // ── Fraud: check order ────────────────────────────────────
    case 'fraud_check':
        requireAdminJson();
        $orderId = (int)($_POST['order_id'] ?? 0);
        if ($orderId <= 0) {
            jsonError('order_id required');
        }
        $result = analyzeOrderFraud($orderId);
        jsonResponse(['success' => true] + $result);

    // ── Fraud: dashboard stats ────────────────────────────────
    case 'fraud_dashboard':
        requireAdminJson();
        $filters  = [
            'risk_level'     => $_GET['risk_level']    ?? '',
            'event_type'     => $_GET['event_type']    ?? '',
            'admin_decision' => $_GET['admin_decision'] ?? '',
        ];
        $dashboard = getFraudDashboard(array_filter($filters));
        jsonResponse(['success' => true] + $dashboard);

    // ── Fraud: resolve case ───────────────────────────────────
    case 'fraud_resolve':
        requireAdminJson();
        $logId    = (int)($_POST['log_id'] ?? 0);
        $decision = (string)($_POST['decision'] ?? '');
        $notes    = (string)($_POST['notes'] ?? '');
        $ok       = resolveFraudCase($logId, $userId, $decision, $notes);
        jsonResponse(['success' => $ok]);

    // ── Usage stats for current user ──────────────────────────
    case 'usage_stats':
        requireAuthJson();
        $period = (string)($_GET['period'] ?? 'today');
        $stats  = getAiUsageStats($userId, $period);
        $budget = checkTokenLimit($userId);
        jsonResponse(['success' => true, 'stats' => $stats, 'budget' => $budget]);

    // ── Admin: get/set config ─────────────────────────────────
    case 'admin_config':
        requireAdminJson();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $key   = (string)($_POST['key'] ?? '');
            $value = (string)($_POST['value'] ?? '');
            if (empty($key)) {
                jsonError('key required');
            }
            $ok = setAiConfig($key, $value, $userId);
            jsonResponse(['success' => $ok]);
        }
        // GET — return all config (omit API key value)
        try {
            $stmt = getDB()->prepare('SELECT config_key, config_value, description FROM ai_config ORDER BY config_key');
            $stmt->execute();
            $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Mask API key
            foreach ($configs as &$c) {
                if ($c['config_key'] === 'deepseek_api_key' && !empty($c['config_value'])) {
                    $c['config_value'] = str_repeat('*', max(0, strlen($c['config_value']) - 4))
                                       . substr($c['config_value'], -4);
                }
            }
            unset($c);
            jsonResponse(['success' => true, 'config' => $configs]);
        } catch (Throwable $e) {
            jsonError('Failed to load config', 500);
        }

    // ── Admin: platform-wide usage stats ─────────────────────
    case 'admin_usage':
        requireAdminJson();
        $period = (string)($_GET['period'] ?? 'today');
        $stats  = getAiUsageStats(null, $period);
        jsonResponse(['success' => true, 'stats' => $stats, 'period' => $period]);

    // ── Health: check DeepSeek connectivity ──────────────────
    case 'health':
        $apiKey = getAiConfig('deepseek_api_key', '');
        if (empty($apiKey)) {
            jsonResponse(['success' => false, 'status' => 'unconfigured', 'message' => 'API key not set']);
        }
        $result = deepseekRequest(
            [['role' => 'user', 'content' => 'Reply with OK']],
            ['max_tokens' => 5, 'temperature' => 0.0]
        );
        jsonResponse([
            'success' => $result['success'],
            'status'  => $result['success'] ? 'ok' : 'error',
            'message' => $result['error'] ?? 'DeepSeek API is reachable',
        ]);

    default:
        jsonError('Unknown action', 400);
}
