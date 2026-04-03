<?php
/**
 * cron/process-webhooks.php — Process queued webhook deliveries
 *
 * Run via cron every minute:
 *   * * * * * php /path/to/cron/process-webhooks.php
 *
 * - Retries failed deliveries whose next_retry_at <= NOW()
 * - Purges delivery logs older than 30 days
 */

// Bootstrap the application
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/webhooks.php';

echo "[" . date('Y-m-d H:i:s') . "] Processing webhooks...\n";

// 1. Retry failed deliveries
try {
    retryFailedWebhooks();
    echo "  ✓ Retried failed deliveries.\n";
} catch (Exception $e) {
    echo "  ✗ Retry error: " . $e->getMessage() . "\n";
}

// 2. Expire old API key daily counters (reset requests_today at midnight)
try {
    $db = getDB();
    $db->exec('UPDATE api_keys SET requests_today = 0 WHERE requests_today > 0');
    echo "  ✓ Reset daily API key counters.\n";
} catch (PDOException $e) {
    echo "  ✗ Counter reset error: " . $e->getMessage() . "\n";
}

// 3. Purge old delivery logs (> 30 days)
try {
    $purged = purgeOldWebhookLogs(30);
    echo "  ✓ Purged $purged old delivery logs.\n";
} catch (Exception $e) {
    echo "  ✗ Purge error: " . $e->getMessage() . "\n";
}

// 4. Deactivate expired rotated API keys (revoked_at in the past)
try {
    $db = getDB();
    $stmt = $db->exec(
        'UPDATE api_keys SET is_active = 0 WHERE revoked_at IS NOT NULL AND revoked_at <= NOW() AND is_active = 1'
    );
    echo "  ✓ Deactivated expired rotated keys.\n";
} catch (PDOException $e) {
    echo "  ✗ Key expiry error: " . $e->getMessage() . "\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Done.\n";
