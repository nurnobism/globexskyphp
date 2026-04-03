<?php
/**
 * api/ai/supplier.php — AI Supplier Analysis Endpoint (Phase 8)
 */

require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/deepseek.php';

header('Content-Type: application/json');
requireLogin();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$role   = $_SESSION['role'] ?? 'buyer';
$action = $_GET['action'] ?? '';

switch ($action) {

    case 'score':
        $supplierId = (int)($_GET['supplier_id'] ?? 0);
        if (!$supplierId) { jsonResponse(['success' => false, 'error' => 'supplier_id required'], 400); }
        try {
            $stmt = $db->prepare("SELECT * FROM ai_supplier_scores WHERE supplier_id = ? ORDER BY calculated_at DESC LIMIT 1");
            $stmt->execute([$supplierId]);
            $score = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($score) {
                jsonResponse(['success' => true, 'data' => $score]);
            } else {
                $ai     = getDeepSeek();
                $result = $ai->analyzeSupplier($supplierId);
                jsonResponse(['success' => true, 'data' => $result]);
            }
        } catch (PDOException $e) {
            jsonResponse(['success' => false, 'error' => 'Database error'], 500);
        }
        break;

    case 'analyze':
        if (!in_array($role, ['admin','super_admin','buyer'])) {
            jsonResponse(['success' => false, 'error' => 'Unauthorized'], 403);
        }
        $supplierId = (int)($_GET['supplier_id'] ?? 0);
        if (!$supplierId) { jsonResponse(['success' => false, 'error' => 'supplier_id required'], 400); }
        try {
            $ai     = getDeepSeek();
            $result = $ai->analyzeSupplier($supplierId);
            jsonResponse(['success' => true, 'data' => $result]);
        } catch (Throwable $e) {
            jsonResponse(['success' => false, 'error' => 'AI service unavailable'], 503);
        }
        break;

    case 'compare':
        $ids = array_map('intval', (array)($_GET['supplier_ids'] ?? []));
        if (count($ids) < 2) { jsonResponse(['success' => false, 'error' => 'At least 2 supplier_ids required'], 400); }
        $results = [];
        $ai = getDeepSeek();
        foreach (array_slice($ids, 0, 5) as $sid) {
            try {
                $results[$sid] = $ai->analyzeSupplier($sid);
            } catch (Throwable $e) {
                $results[$sid] = ['error' => 'Analysis unavailable'];
            }
        }
        jsonResponse(['success' => true, 'data' => $results]);
        break;

    case 'ranking':
        if (!in_array($role, ['admin','super_admin','buyer'])) {
            jsonResponse(['success' => false, 'error' => 'Unauthorized'], 403);
        }
        try {
            $stmt = $db->query(
                "SELECT ss.*, s.company_name, s.user_id
                 FROM ai_supplier_scores ss
                 JOIN suppliers s ON s.id = ss.supplier_id
                 ORDER BY ss.overall_score DESC LIMIT 20"
            );
            $ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);
            jsonResponse(['success' => true, 'data' => $ranking]);
        } catch (PDOException $e) {
            jsonResponse(['success' => false, 'error' => 'Database error'], 500);
        }
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
}
