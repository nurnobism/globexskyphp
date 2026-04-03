<?php
/**
 * api/v1/webhooks.php — Webhooks API Resource
 *
 * Actions: list, register, update, delete, test
 */
if (!defined('API_RESOURCE')) {
    require_once __DIR__ . '/gateway.php';
    exit;
}

require_once __DIR__ . '/../../includes/api-response.php';

$db     = getDB();
$action = API_ACTION ?: ($_GET['action'] ?? 'list');
$apiKey = API_KEY_ROW;
$userId = (int)$apiKey['user_id'];

$elapsed = fn() => (int)((microtime(true) - API_START_TIME) * 1000);

// All supported event types
const WEBHOOK_EVENTS = [
    'order.created', 'order.updated', 'order.shipped', 'order.delivered', 'order.cancelled',
    'product.created', 'product.updated', 'product.deleted', 'product.stock_low',
    'payment.completed', 'payment.failed', 'payment.refunded',
    'user.registered', 'user.updated',
    'review.created',
    'dropship.order_created', 'dropship.order_shipped',
];

switch ($action) {
    // ── GET list ──────────────────────────────────────────────
    case 'list':
        $stmt = $db->prepare(
            'SELECT id, url, events, is_active, last_triggered_at, success_count, failure_count, created_at
             FROM webhooks WHERE user_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$userId]);
        $hooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($hooks as &$hook) {
            $hook['events'] = json_decode($hook['events'], true);
        }
        unset($hook);

        logApiRequest((int)$apiKey['id'], $userId, 'GET', 'webhooks/list', 200, $elapsed());
        apiSuccess($hooks, null, 200, getRateLimit($apiKey));
        break;

    // ── POST register ─────────────────────────────────────────
    case 'register':
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $errors = [];
        if (empty($body['url'])) {
            $errors['url'] = 'Webhook URL is required.';
        } elseif (!filter_var($body['url'], FILTER_VALIDATE_URL) || !str_starts_with($body['url'], 'https://')) {
            $errors['url'] = 'Webhook URL must be a valid HTTPS URL.';
        }
        if (empty($body['events']) || !is_array($body['events'])) {
            $errors['events'] = 'At least one event type is required.';
        } else {
            $invalid = array_diff($body['events'], WEBHOOK_EVENTS);
            if ($invalid) {
                $errors['events'] = 'Invalid event types: ' . implode(', ', $invalid);
            }
        }
        if ($errors) {
            apiValidationError($errors);
        }

        $secret = bin2hex(random_bytes(24));  // 48-char hex secret
        $db->prepare(
            'INSERT INTO webhooks (user_id, url, secret, events, is_active)
             VALUES (?, ?, ?, ?, 1)'
        )->execute([$userId, $body['url'], $secret, json_encode($body['events'])]);
        $id = (int)$db->lastInsertId();

        logApiRequest((int)$apiKey['id'], $userId, 'POST', 'webhooks/register', 201, $elapsed());
        apiSuccess([
            'id'     => $id,
            'secret' => $secret,   // shown ONCE
            'message' => 'Webhook registered. Store the secret securely — it will not be shown again.',
        ], null, 201, getRateLimit($apiKey));
        break;

    // ── PUT update ────────────────────────────────────────────
    case 'update':
        $id   = (int)($_GET['id'] ?? 0);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!$id) {
            apiError('Webhook ID required.', 400);
        }
        $stmt = $db->prepare('SELECT id FROM webhooks WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        if (!$stmt->fetchColumn()) {
            apiNotFound('Webhook');
        }
        $allowed = [];
        if (array_key_exists('url', $body) && filter_var($body['url'], FILTER_VALIDATE_URL) && str_starts_with($body['url'], 'https://')) {
            $allowed[] = 'url = ?';
        }
        if (array_key_exists('events', $body) && is_array($body['events'])) {
            $allowed[] = 'events = ?';
            $body['events'] = json_encode($body['events']);
        }
        if (array_key_exists('is_active', $body)) {
            $allowed[] = 'is_active = ?';
        }
        if ($allowed) {
            $vals = [];
            foreach (['url', 'events', 'is_active'] as $f) {
                if (isset($body[$f]) && in_array("$f = ?", $allowed, true)) {
                    $vals[] = $body[$f];
                }
            }
            $vals[] = $id;
            $db->prepare('UPDATE webhooks SET ' . implode(', ', $allowed) . ', updated_at = NOW() WHERE id = ?')->execute($vals);
        }
        logApiRequest((int)$apiKey['id'], $userId, 'PUT', 'webhooks/update', 200, $elapsed());
        apiSuccess(['message' => 'Webhook updated.'], null, 200, getRateLimit($apiKey));
        break;

    // ── DELETE delete ─────────────────────────────────────────
    case 'delete':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            apiError('Webhook ID required.', 400);
        }
        $stmt = $db->prepare('DELETE FROM webhooks WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        if (!$stmt->rowCount()) {
            apiNotFound('Webhook');
        }
        logApiRequest((int)$apiKey['id'], $userId, 'DELETE', 'webhooks/delete', 200, $elapsed());
        apiSuccess(['message' => 'Webhook deleted.'], null, 200, getRateLimit($apiKey));
        break;

    // ── POST test ─────────────────────────────────────────────
    case 'test':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            apiError('Webhook ID required.', 400);
        }
        $stmt = $db->prepare('SELECT * FROM webhooks WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $webhook = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$webhook) {
            apiNotFound('Webhook');
        }

        require_once __DIR__ . '/../../includes/webhooks.php';

        $testPayload = [
            'event'       => 'webhook.test',
            'timestamp'   => gmdate('Y-m-d\TH:i:s\Z'),
            'delivery_id' => generateDeliveryId(),
            'data'        => ['message' => 'This is a test webhook from GlobexSky.', 'webhook_id' => $id],
        ];

        $jsonPayload = json_encode($testPayload);
        $signature   = 'sha256=' . hash_hmac('sha256', $jsonPayload, $webhook['secret']);
        $start       = microtime(true);

        $ch = curl_init($webhook['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-GlobexSky-Event: webhook.test',
                'X-GlobexSky-Signature: ' . $signature,
                'X-GlobexSky-Delivery: ' . $testPayload['delivery_id'],
                'X-GlobexSky-Timestamp: ' . $testPayload['timestamp'],
            ],
        ]);
        $responseBody = curl_exec($ch);
        $httpCode     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $elapsed2     = (int)((microtime(true) - $start) * 1000);
        curl_close($ch);

        logApiRequest((int)$apiKey['id'], $userId, 'POST', 'webhooks/test', 200, $elapsed());
        apiSuccess([
            'response_code'    => $httpCode,
            'response_time_ms' => $elapsed2,
            'success'          => ($httpCode >= 200 && $httpCode < 300),
        ], null, 200, getRateLimit($apiKey));
        break;

    default:
        logApiRequest((int)$apiKey['id'], $userId, $_SERVER['REQUEST_METHOD'], "webhooks/$action", 404, $elapsed());
        apiNotFound("Action '$action'");
}
