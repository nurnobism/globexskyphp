<?php
/**
 * api/api-platform.php — API Platform Management API
 */

require_once __DIR__ . '/../includes/middleware.php';
requireLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list_keys';
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();
$userId = $_SESSION['user_id'];

switch ($action) {

    case 'list_keys':
        $stmt = $db->prepare('SELECT id, name, key_prefix, created_at, last_used_at, status
            FROM api_keys WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$userId]);
        $keys = $stmt->fetchAll();
        // Mask the full key — only show prefix
        foreach ($keys as &$k) {
            $k['key_display'] = $k['key_prefix'] . str_repeat('•', 20);
        }
        jsonResponse(['data' => $keys]);
        break;

    case 'create_key':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);
        $name = trim(post('name', ''));
        if (!$name) jsonResponse(['error' => 'Key name is required'], 422);
        // Enforce max 10 keys per user
        $count = $db->prepare('SELECT COUNT(*) FROM api_keys WHERE user_id = ? AND status = "active"');
        $count->execute([$userId]);
        if ($count->fetchColumn() >= 10) jsonResponse(['error' => 'Maximum of 10 active API keys reached'], 429);
        $rawKey    = 'gsk_live_' . bin2hex(random_bytes(20));
        $keyHash   = password_hash($rawKey, PASSWORD_BCRYPT);
        $keyPrefix = substr($rawKey, 0, 12);
        $db->prepare('INSERT INTO api_keys (user_id, name, key_hash, key_prefix, status, created_at)
            VALUES (?, ?, ?, ?, "active", NOW())')
            ->execute([$userId, $name, $keyHash, $keyPrefix]);
        $keyId = $db->lastInsertId();
        jsonResponse(['success' => true, 'key_id' => $keyId, 'api_key' => $rawKey,
            'message' => 'Copy this key now — it will not be shown again.']);
        break;

    case 'revoke_key':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);
        $keyId = (int)post('key_id', 0);
        if (!$keyId) jsonResponse(['error' => 'Key ID required'], 422);
        $stmt = $db->prepare('UPDATE api_keys SET status = "revoked", revoked_at = NOW()
            WHERE id = ? AND user_id = ? AND status = "active"');
        $stmt->execute([$keyId, $userId]);
        if ($stmt->rowCount() === 0) jsonResponse(['error' => 'Key not found or already revoked'], 404);
        jsonResponse(['success' => true]);
        break;

    case 'usage_stats':
        $period = get('period', '7d');
        $days   = match($period) { '30d' => 30, '90d' => 90, default => 7 };
        $stmt   = $db->prepare('SELECT DATE(created_at) day, COUNT(*) calls,
            SUM(CASE WHEN status_code < 400 THEN 1 ELSE 0 END) success,
            SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) errors
            FROM api_requests WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at) ORDER BY day ASC');
        $stmt->execute([$userId, $days]);
        $daily = $stmt->fetchAll();
        $totStmt = $db->prepare('SELECT COUNT(*) total,
            SUM(CASE WHEN status_code < 400 THEN 1 ELSE 0 END) success
            FROM api_requests WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)');
        $totStmt->execute([$userId, $days]);
        $totals = $totStmt->fetch();
        $epStmt = $db->prepare('SELECT endpoint, COUNT(*) calls FROM api_requests
            WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY endpoint ORDER BY calls DESC LIMIT 5');
        $epStmt->execute([$userId, $days]);
        jsonResponse(['data' => ['daily' => $daily, 'totals' => $totals, 'top_endpoints' => $epStmt->fetchAll()]]);
        break;

    case 'list_plans':
        $plans = [
            ['id'=>'free',       'name'=>'Free',       'price'=>0,   'calls_per_day'=>1000,    'features'=>['Basic endpoints','Community support','Rate limited']],
            ['id'=>'basic',      'name'=>'Basic',      'price'=>99,  'calls_per_day'=>50000,   'features'=>['All endpoints','Email support','Webhooks']],
            ['id'=>'pro',        'name'=>'Pro',         'price'=>299, 'calls_per_day'=>500000,  'features'=>['All endpoints','Priority support','Webhooks','Analytics']],
            ['id'=>'enterprise', 'name'=>'Enterprise', 'price'=>999, 'calls_per_day'=>-1,      'features'=>['All endpoints','Dedicated support','SLA','Custom limits']],
        ];
        $subStmt = $db->prepare('SELECT plan_id FROM api_subscriptions WHERE user_id = ? AND status = "active" LIMIT 1');
        $subStmt->execute([$userId]);
        $currentPlan = $subStmt->fetchColumn();
        jsonResponse(['data' => $plans, 'current_plan' => $currentPlan ?: 'free']);
        break;

    case 'subscribe_plan':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);
        $planId = post('plan_id', '');
        $valid  = ['free', 'basic', 'pro', 'enterprise'];
        if (!in_array($planId, $valid)) jsonResponse(['error' => 'Invalid plan'], 422);
        $db->prepare('UPDATE api_subscriptions SET status = "cancelled", cancelled_at = NOW()
            WHERE user_id = ? AND status = "active"')->execute([$userId]);
        $db->prepare('INSERT INTO api_subscriptions (user_id, plan_id, status, started_at)
            VALUES (?, ?, "active", NOW())')->execute([$userId, $planId]);
        jsonResponse(['success' => true, 'plan' => $planId,
            'message' => $planId === 'free' ? 'Switched to Free plan.' : 'Redirecting to payment…']);
        break;

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}
