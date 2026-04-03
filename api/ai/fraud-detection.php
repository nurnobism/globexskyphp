<?php
/**
 * api/ai/fraud-detection.php — AI Fraud Detection Endpoint (Phase 8)
 */

require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/deepseek.php';
require_once __DIR__ . '/../../includes/ai-fraud.php';

header('Content-Type: application/json');
requireLogin();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$role   = $_SESSION['role'] ?? 'buyer';
$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'analyze_order':
        if ($method !== 'POST') { jsonResponse(['success' => false, 'error' => 'POST required'], 405); }
        if (!in_array($role, ['admin', 'super_admin', 'support'])) {
            jsonResponse(['success' => false, 'error' => 'Unauthorized'], 403);
        }
        $orderId = (int)($input['order_id'] ?? 0);
        if (!$orderId) { jsonResponse(['success' => false, 'error' => 'order_id required'], 400); }
        $result = analyzeOrder($orderId);
        jsonResponse(['success' => true, 'data' => $result]);
        break;

    case 'analyze_user':
        if ($method !== 'POST') { jsonResponse(['success' => false, 'error' => 'POST required'], 405); }
        if (!in_array($role, ['admin', 'super_admin'])) {
            jsonResponse(['success' => false, 'error' => 'Unauthorized'], 403);
        }
        $targetUserId = (int)($input['user_id'] ?? 0);
        if (!$targetUserId) { jsonResponse(['success' => false, 'error' => 'user_id required'], 400); }
        $result = analyzeUser($targetUserId);
        jsonResponse(['success' => true, 'data' => $result]);
        break;

    case 'analyze_review':
        if ($method !== 'POST') { jsonResponse(['success' => false, 'error' => 'POST required'], 405); }
        if (!in_array($role, ['admin', 'super_admin', 'support'])) {
            jsonResponse(['success' => false, 'error' => 'Unauthorized'], 403);
        }
        $reviewId = (int)($input['review_id'] ?? 0);
        if (!$reviewId) { jsonResponse(['success' => false, 'error' => 'review_id required'], 400); }
        $result = analyzeReview($reviewId);
        jsonResponse(['success' => true, 'data' => $result]);
        break;

    case 'alerts':
        if (!in_array($role, ['admin', 'super_admin', 'support'])) {
            jsonResponse(['success' => false, 'error' => 'Unauthorized'], 403);
        }
        $filters = [
            'entity_type' => $_GET['entity_type'] ?? '',
            'risk_level'  => $_GET['risk_level']  ?? '',
            'action_taken'=> $_GET['action_taken'] ?? '',
            'date_from'   => $_GET['date_from']   ?? '',
            'date_to'     => $_GET['date_to']     ?? '',
        ];
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(50, (int)($_GET['per_page'] ?? 20));
        $result  = getAuditLog($filters, $page, $perPage);
        jsonResponse(['success' => true, 'data' => $result]);
        break;

    case 'stats':
        if (!in_array($role, ['admin', 'super_admin', 'support'])) {
            jsonResponse(['success' => false, 'error' => 'Unauthorized'], 403);
        }
        $stats = getRiskDashboardStats();
        jsonResponse(['success' => true, 'data' => $stats]);
        break;

    case 'review':
        if ($method !== 'POST') { jsonResponse(['success' => false, 'error' => 'POST required'], 405); }
        if (!in_array($role, ['admin', 'super_admin'])) {
            jsonResponse(['success' => false, 'error' => 'Unauthorized'], 403);
        }
        $fraudLogId  = (int)($input['fraud_log_id'] ?? 0);
        $actionTaken = $input['action_taken'] ?? 'none';
        if (!$fraudLogId) { jsonResponse(['success' => false, 'error' => 'fraud_log_id required'], 400); }
        $allowedActions = ['none','flag','hold','block','notify_admin'];
        if (!in_array($actionTaken, $allowedActions)) { jsonResponse(['success' => false, 'error' => 'Invalid action'], 400); }
        try {
            $db->prepare(
                "UPDATE ai_fraud_logs SET action_taken = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?"
            )->execute([$actionTaken, $userId, $fraudLogId]);
            jsonResponse(['success' => true]);
        } catch (PDOException $e) {
            jsonResponse(['success' => false, 'error' => 'Database error'], 500);
        }
        break;

    case 'false_positive':
        if ($method !== 'POST') { jsonResponse(['success' => false, 'error' => 'POST required'], 405); }
        if (!in_array($role, ['admin', 'super_admin'])) {
            jsonResponse(['success' => false, 'error' => 'Unauthorized'], 403);
        }
        $fraudLogId = (int)($input['fraud_log_id'] ?? 0);
        if (!$fraudLogId) { jsonResponse(['success' => false, 'error' => 'fraud_log_id required'], 400); }
        $result = markFalsePositive($fraudLogId, $userId);
        jsonResponse(['success' => $result]);
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
}
