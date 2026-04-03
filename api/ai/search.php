<?php
/**
 * api/ai/search.php — AI Search Enhancement Endpoint (Phase 8)
 */

require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/deepseek.php';

header('Content-Type: application/json');

$db     = getDB();
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$action = $_GET['action'] ?? 'enhance';

switch ($action) {

    case 'enhance':
        $query = trim($_GET['q'] ?? '');
        if (!$query) { jsonResponse(['success' => false, 'error' => 'q (query) required'], 400); }
        try {
            $ai     = getDeepSeek();
            $result = $ai->enhanceSearch($query, $userId);
            jsonResponse(['success' => true, 'data' => $result]);
        } catch (Throwable $e) {
            jsonResponse(['success' => false, 'error' => 'AI service unavailable'], 503);
        }
        break;

    case 'suggest':
        $query = trim($_GET['q'] ?? '');
        if (!$query) { jsonResponse(['success' => false, 'error' => 'q (query) required'], 400); }
        try {
            $ai       = getDeepSeek();
            $response = $ai->chat(
                "Generate 5 search query suggestions related to: \"$query\"\nReturn JSON array of strings.",
                'You are a marketplace search assistant. Return only a JSON array of suggestion strings.'
            );
            $suggestions = json_decode($response, true);
            jsonResponse(['success' => true, 'data' => is_array($suggestions) ? $suggestions : []]);
        } catch (Throwable $e) {
            jsonResponse(['success' => false, 'error' => 'AI service unavailable'], 503);
        }
        break;

    case 'history':
        if (!$userId) { jsonResponse(['success' => false, 'error' => 'Login required'], 401); }
        try {
            $stmt = $db->prepare(
                "SELECT id, original_query, enhanced_query, intent, results_count, created_at
                 FROM ai_search_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 20"
            );
            $stmt->execute([$userId]);
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            jsonResponse(['success' => false, 'error' => 'Database error'], 500);
        }
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
}
