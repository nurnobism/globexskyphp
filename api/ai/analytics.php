<?php
/**
 * api/ai/analytics.php — AI Analytics Endpoint (Phase 8)
 */

require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/deepseek.php';
require_once __DIR__ . '/../../includes/ai-analytics.php';

header('Content-Type: application/json');
requireLogin();

$userId = (int)$_SESSION['user_id'];
$role   = $_SESSION['role'] ?? 'buyer';
$action = $_GET['action'] ?? '';

switch ($action) {

    case 'sales_trends':
        $period = in_array($_GET['period'] ?? '', ['7days','30days','90days']) ? $_GET['period'] : '30days';
        // For suppliers: use their supplier ID; for admins: use given supplier_id or 0 for all
        $supplierId = (int)($_GET['supplier_id'] ?? 0);
        if (!$supplierId && $role === 'supplier') {
            $db = getDB();
            try {
                $stmt = $db->prepare("SELECT id FROM suppliers WHERE user_id = ?");
                $stmt->execute([$userId]);
                $sup = $stmt->fetch(PDO::FETCH_ASSOC);
                $supplierId = (int)($sup['id'] ?? 0);
            } catch (PDOException $e) {}
        }
        $result = analyzeSalesTrends($supplierId, $period);
        jsonResponse(['success' => true, 'data' => $result]);
        break;

    case 'demand_forecast':
        $productId = (int)($_GET['product_id'] ?? 0);
        if (!$productId) { jsonResponse(['success' => false, 'error' => 'product_id required'], 400); }
        $period = in_array($_GET['period'] ?? '', ['7days','30days','90days']) ? $_GET['period'] : '30days';
        $result = predictDemand($productId, $period);
        jsonResponse(['success' => true, 'data' => $result]);
        break;

    case 'customer_segments':
        if (!in_array($role, ['admin','super_admin','supplier'])) {
            jsonResponse(['success' => false, 'error' => 'Unauthorized'], 403);
        }
        $result = analyzeCustomerBehavior($_GET['segment'] ?? 'all');
        jsonResponse(['success' => true, 'data' => $result]);
        break;

    case 'insights':
        $result = generateBusinessInsights($userId, $role);
        jsonResponse(['success' => true, 'data' => $result]);
        break;

    case 'health':
        if (!in_array($role, ['admin','super_admin'])) {
            jsonResponse(['success' => false, 'error' => 'Unauthorized'], 403);
        }
        $result = getAIHealthMetrics();
        jsonResponse(['success' => true, 'data' => $result]);
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
}
