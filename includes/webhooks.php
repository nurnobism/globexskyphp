<?php
/**
 * includes/webhooks.php — GlobexSky Webhook Delivery System
 *
 * Handles queuing, signing, delivering, and retrying webhook payloads.
 *
 * Payload format:
 * {
 *   "event":     "order.created",
 *   "timestamp": "2024-01-15T10:30:00Z",
 *   "data":      { ... event-specific data ... }
 * }
 *
 * Signature: HMAC-SHA256 of the JSON payload using the webhook secret.
 * Header:    X-GlobexSky-Signature: sha256=<hex>
 */

/**
 * Trigger a webhook event.
 * Finds all active webhooks subscribed to $eventName and queues delivery.
 *
 * @param string $eventName  e.g. 'order.created'
 * @param array  $data       Event payload data
 */
function triggerEvent(string $eventName, array $data): void
{
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            "SELECT id, url, secret FROM webhooks
             WHERE is_active = 1
               AND JSON_CONTAINS(events, ?)"
        );
        $stmt->execute([json_encode($eventName)]);
        $webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($webhooks as $webhook) {
            $deliveryId = generateDeliveryId();
            $payload    = buildPayload($eventName, $data);

            // Insert delivery record (status = pending)
            $db->prepare(
                'INSERT INTO webhook_deliveries
                 (webhook_id, event, payload, delivery_id, status, created_at)
                 VALUES (?, ?, ?, ?, "pending", NOW())'
            )->execute([$webhook['id'], $eventName, json_encode($payload), $deliveryId]);

            $deliveryDbId = (int)$db->lastInsertId();

            // Attempt delivery immediately
            deliverWebhook($deliveryDbId, $webhook, $payload);
        }
    } catch (PDOException $e) {
        // Webhook delivery failure must never break the main application
        error_log('Webhook trigger error: ' . $e->getMessage());
    }
}

/**
 * Deliver a single webhook.
 *
 * @param int   $deliveryId  DB id in webhook_deliveries
 * @param array $webhook     Row from webhooks table (id, url, secret)
 * @param array $payload     Payload array (will be JSON-encoded)
 */
function deliverWebhook(int $deliveryId, array $webhook, array $payload): void
{
    $db          = getDB();
    $jsonPayload = json_encode($payload);
    $signature   = 'sha256=' . hash_hmac('sha256', $jsonPayload, $webhook['secret']);
    $deliveryUid = $payload['delivery_id'] ?? generateDeliveryId();
    $start       = microtime(true);

    $ch = curl_init($webhook['url']);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $jsonPayload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-GlobexSky-Event: '     . $payload['event'],
            'X-GlobexSky-Signature: ' . $signature,
            'X-GlobexSky-Delivery: '  . $deliveryUid,
            'X-GlobexSky-Timestamp: ' . ($payload['timestamp'] ?? date('c')),
            'User-Agent: GlobexSky-Webhooks/1.0',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $responseBody = curl_exec($ch);
    $httpCode     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $elapsed      = (int)((microtime(true) - $start) * 1000);
    $curlError    = curl_error($ch);
    curl_close($ch);

    $success = ($httpCode >= 200 && $httpCode < 300);

    if ($success) {
        $db->prepare(
            'UPDATE webhook_deliveries
             SET status = "success", response_code = ?, response_body = ?,
                 response_time_ms = ?, delivered_at = NOW()
             WHERE id = ?'
        )->execute([$httpCode, substr((string)$responseBody, 0, 2000), $elapsed, $deliveryId]);

        $db->prepare(
            'UPDATE webhooks SET last_triggered_at = NOW(), success_count = success_count + 1 WHERE id = ?'
        )->execute([$webhook['id']]);
    } else {
        // Schedule retry
        $stmt = $db->prepare('SELECT retry_count, max_retries FROM webhook_deliveries WHERE id = ?');
        $stmt->execute([$deliveryId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $retryCount = (int)($row['retry_count'] ?? 0);
        $maxRetries = (int)($row['max_retries'] ?? 3);

        // Retry schedule: +1 min, +5 min, +30 min
        $retryDelays = [1, 5, 30];
        $nextRetryAt = null;
        $newStatus   = 'failed';

        if ($retryCount < $maxRetries) {
            $delayMinutes = $retryDelays[$retryCount] ?? 30;
            $nextRetryAt  = date('Y-m-d H:i:s', strtotime("+{$delayMinutes} minutes"));
            $newStatus    = 'retrying';
        }

        $db->prepare(
            'UPDATE webhook_deliveries
             SET status = ?, response_code = ?, response_body = ?,
                 response_time_ms = ?, retry_count = retry_count + 1,
                 next_retry_at = ?
             WHERE id = ?'
        )->execute([
            $newStatus,
            $httpCode ?: 0,
            substr($curlError ?: (string)$responseBody, 0, 2000),
            $elapsed,
            $nextRetryAt,
            $deliveryId,
        ]);

        $db->prepare(
            'UPDATE webhooks SET failure_count = failure_count + 1 WHERE id = ?'
        )->execute([$webhook['id']]);
    }
}

/**
 * Verify a webhook signature received by an endpoint consumer.
 *
 * @param string $payload    Raw request body
 * @param string $signature  Value of X-GlobexSky-Signature header
 * @param string $secret     Webhook secret
 * @return bool
 */
function verifyWebhookSignature(string $payload, string $signature, string $secret): bool
{
    if (!str_starts_with($signature, 'sha256=')) {
        return false;
    }
    $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
    return hash_equals($expected, $signature);
}

/**
 * Process pending webhook retries.
 * Called by cron/process-webhooks.php.
 */
function retryFailedWebhooks(): void
{
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT wd.*, w.url, w.secret
         FROM webhook_deliveries wd
         JOIN webhooks w ON w.id = wd.webhook_id
         WHERE wd.status = 'retrying'
           AND wd.next_retry_at <= NOW()
           AND w.is_active = 1
         LIMIT 100"
    );
    $stmt->execute();
    $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pending as $delivery) {
        $payload = json_decode($delivery['payload'], true);
        deliverWebhook((int)$delivery['id'], [
            'id'     => $delivery['webhook_id'],
            'url'    => $delivery['url'],
            'secret' => $delivery['secret'],
        ], $payload ?? []);
    }
}

/**
 * Purge delivery logs older than $days days.
 */
function purgeOldWebhookLogs(int $days = 30): int
{
    $db   = getDB();
    $stmt = $db->prepare(
        'DELETE FROM webhook_deliveries WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)'
    );
    $stmt->execute([$days]);
    return $stmt->rowCount();
}

/**
 * Get recent delivery logs for a webhook.
 */
function getWebhookLogs(int $webhookId, int $limit = 50): array
{
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT * FROM webhook_deliveries WHERE webhook_id = ? ORDER BY created_at DESC LIMIT ?'
    );
    $stmt->execute([$webhookId, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── Private helpers ──────────────────────────────────────────────────────────

/**
 * Build the standard webhook payload envelope.
 */
function buildPayload(string $eventName, array $data): array
{
    return [
        'event'       => $eventName,
        'timestamp'   => gmdate('Y-m-d\TH:i:s\Z'),
        'delivery_id' => generateDeliveryId(),
        'data'        => $data,
    ];
}

/**
 * Generate a UUID v4 style delivery ID.
 */
function generateDeliveryId(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}
