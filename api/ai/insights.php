<?php
/**
 * api/ai/insights.php — AI Business Insights Endpoint (Phase 8)
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

    case 'daily_briefing':
        $ai       = getDeepSeek();
        $insights = generateBusinessInsights($userId, $role);
        $health   = ($role === 'admin' || $role === 'super_admin') ? getAIHealthMetrics() : [];
        $prompt   = "Generate a concise daily business briefing for a $role user. "
                  . "Business metrics: " . json_encode($insights)
                  . "\nKeep it under 200 words. Be actionable and specific.";
        $briefing = $ai->chat($prompt, 'You are a business intelligence assistant. Be concise and actionable.');
        jsonResponse(['success' => true, 'data' => ['briefing' => $briefing, 'insights' => $insights, 'health' => $health]]);
        break;

    case 'anomalies':
        if (!in_array($role, ['admin','super_admin','supplier'])) {
            jsonResponse(['success' => false, 'error' => 'Unauthorized'], 403);
        }
        $db = getDB();
        $anomalies = [];
        try {
            // Detect unusually high cancellation rates
            $stmt = $db->query(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled
                 FROM orders WHERE placed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
            );
            $orderStats = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($orderStats['total'] > 0) {
                $cancelRate = ($orderStats['cancelled'] / $orderStats['total']) * 100;
                if ($cancelRate > 20) {
                    $anomalies[] = ['type' => 'high_cancellation', 'severity' => 'high', 'detail' => round($cancelRate, 1) . '% cancellation rate in last 7 days'];
                }
            }
        } catch (PDOException $e) { /* ignore */ }
        $ai = getDeepSeek();
        $aiAnomalies = $ai->chat(
            "Identify potential anomalies based on: " . json_encode($anomalies)
            . "\nReturn JSON array: [{type:string, severity:\"low|medium|high\", detail:string, recommendation:string}]",
            'You are an anomaly detection system. Return valid JSON only.'
        );
        $aiResult = json_decode($aiAnomalies, true);
        $combined = array_merge($anomalies, is_array($aiResult) ? $aiResult : []);
        jsonResponse(['success' => true, 'data' => $combined]);
        break;

    case 'opportunities':
        $ai     = getDeepSeek();
        $result = generateBusinessInsights($userId, $role);
        $opportunities = $ai->chat(
            "Based on these business metrics, identify top 3 growth opportunities: " . json_encode($result)
            . "\nReturn JSON array: [{title:string, description:string, estimated_impact:string, difficulty:\"easy|medium|hard\", actions:[string]}]",
            'You are a business growth strategist. Return valid JSON only.'
        );
        $parsed = json_decode($opportunities, true);
        jsonResponse(['success' => true, 'data' => is_array($parsed) ? $parsed : []]);
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
}
