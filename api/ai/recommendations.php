<?php
/**
 * api/ai/recommendations.php — AI Product Recommendations Endpoint (Phase 8)
 */

require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/deepseek.php';
require_once __DIR__ . '/../../includes/ai-recommendations.php';

header('Content-Type: application/json');
requireLogin();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'personalized':
        $limit = min(20, (int)($_GET['limit'] ?? 8));
        $recs  = getPersonalizedRecommendations($userId, $limit);
        jsonResponse(['success' => true, 'data' => $recs]);
        break;

    case 'similar':
        $productId = (int)($_GET['product_id'] ?? 0);
        if (!$productId) { jsonResponse(['success' => false, 'error' => 'product_id required'], 400); }
        $recs = getSimilarProducts($productId, (int)($_GET['limit'] ?? 6));
        jsonResponse(['success' => true, 'data' => $recs]);
        break;

    case 'frequently_bought':
        $productId = (int)($_GET['product_id'] ?? 0);
        if (!$productId) { jsonResponse(['success' => false, 'error' => 'product_id required'], 400); }
        $recs = getFrequentlyBoughtTogether($productId, (int)($_GET['limit'] ?? 4));
        jsonResponse(['success' => true, 'data' => $recs]);
        break;

    case 'trending':
        $category = $_GET['category'] ?? '';
        $limit    = min(20, (int)($_GET['limit'] ?? 8));
        $products = getTrendingProducts($category, $limit);
        jsonResponse(['success' => true, 'data' => $products]);
        break;

    case 'click':
        if ($method !== 'POST') { jsonResponse(['success' => false, 'error' => 'POST required'], 405); }
        $recId = (int)($input['recommendation_id'] ?? 0);
        if (!$recId) { jsonResponse(['success' => false, 'error' => 'recommendation_id required'], 400); }
        recordRecommendationClick($recId);
        jsonResponse(['success' => true]);
        break;

    case 'refresh':
        if ($method !== 'POST') { jsonResponse(['success' => false, 'error' => 'POST required'], 405); }
        refreshRecommendationsCache($userId);
        $recs = getPersonalizedRecommendations($userId, 8);
        jsonResponse(['success' => true, 'data' => $recs]);
        break;

    default:
        // Default: return all sections
        jsonResponse(['success' => true, 'data' => [
            'personalized' => getPersonalizedRecommendations($userId, 8),
            'trending'     => getTrendingProducts('', 8),
        ]]);
}
