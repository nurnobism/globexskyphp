<?php
/**
 * cron/ai-fraud-scan.php — Hourly AI Fraud Scanner
 *
 * Run via cron: 0 * * * * php /path/to/cron/ai-fraud-scan.php
 *
 * - Scans recent orders not yet analysed for fraud
 * - Flags high-risk items
 * - Sends admin notifications for critical risk
 * - Logs execution stats
 */

// CLI-only guard
if (PHP_SAPI !== 'cli' && !defined('CRON_OVERRIDE')) {
    http_response_code(403);
    exit('CLI only');
}

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/ai-engine.php';
require_once APP_ROOT . '/includes/ai-fraud.php';

$start    = microtime(true);
$scanned  = 0;
$flagged  = 0;
$critical = 0;
$errors   = 0;

echo '[' . date('Y-m-d H:i:s') . '] AI Fraud Scan Cron starting...' . PHP_EOL;

if (!isAiEnabled('fraud')) {
    echo 'AI fraud detection is disabled. Exiting.' . PHP_EOL;
    exit(0);
}

try {
    $db = getDB();
} catch (Throwable $e) {
    echo 'ERROR: DB connection failed: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Find orders from the last 2 hours not yet analysed
$ordersToScan = [];
try {
    $stmt = $db->prepare(
        "SELECT o.id
         FROM orders o
         LEFT JOIN ai_fraud_logs fl
               ON fl.order_id = o.id AND fl.event_type = 'order'
         WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
           AND fl.id IS NULL
           AND o.status NOT IN ('cancelled')
         LIMIT 200"
    );
    $stmt->execute();
    $ordersToScan = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    echo 'Warning: Could not query orders: ' . $e->getMessage() . PHP_EOL;
}

echo 'Orders to scan: ' . count($ordersToScan) . PHP_EOL;

foreach ($ordersToScan as $orderId) {
    $scanned++;
    try {
        $result = analyzeOrderFraud((int)$orderId);

        if (in_array($result['risk_level'], ['high', 'critical'], true)) {
            $flagged++;
            echo "  [FLAGGED] Order #$orderId — {$result['risk_level']} (score: {$result['risk_score']})" . PHP_EOL;
        }

        if ($result['risk_level'] === 'critical') {
            $critical++;
            _notifyAdminFraud((int)$orderId, $result);
        }

        usleep(300000); // 300ms between calls
    } catch (Throwable $e) {
        $errors++;
        error_log('Fraud scan error for order ' . $orderId . ': ' . $e->getMessage());
    }
}

// Daily summary (run once per day at midnight-ish based on cron schedule)
if ((int)date('H') < 1) {
    _generateDailySummary($db);
}

$elapsed = round(microtime(true) - $start, 2);
echo '[' . date('Y-m-d H:i:s') . '] Done. '
   . "Scanned=$scanned, Flagged=$flagged, Critical=$critical, Errors=$errors, Time={$elapsed}s" . PHP_EOL;

exit($errors > 0 ? 1 : 0);

// ── Helpers ───────────────────────────────────────────────────

/**
 * Notify admin users about a critical fraud detection.
 */
function _notifyAdminFraud(int $orderId, array $result): void
{
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            "SELECT id, email FROM users WHERE role IN ('admin','super_admin') AND status = 'active' LIMIT 5"
        );
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($admins as $admin) {
            // Insert a notification record if notifications table exists
            try {
                $db->prepare(
                    "INSERT INTO notifications (user_id, type, title, message, link, created_at)
                     VALUES (?, 'fraud_alert', ?, ?, ?, NOW())"
                )->execute([
                    $admin['id'],
                    'Critical Fraud Alert: Order #' . $orderId,
                    'Order #' . $orderId . ' has a critical fraud risk score of ' . $result['risk_score'] . '/100. Immediate review required.',
                    '/pages/admin/ai-fraud.php',
                ]);
            } catch (Throwable $e) { /* notifications table may not exist */ }
        }
    } catch (Throwable $e) {
        error_log('_notifyAdminFraud error: ' . $e->getMessage());
    }
}

/**
 * Generate a daily fraud summary and log it.
 */
function _generateDailySummary(PDO $db): void
{
    try {
        $stmt = $db->prepare(
            "SELECT
               COUNT(*) AS total,
               SUM(risk_level = 'critical') AS critical,
               SUM(risk_level = 'high') AS high,
               AVG(risk_score) AS avg_score
             FROM ai_fraud_logs
             WHERE DATE(created_at) = CURDATE()"
        );
        $stmt->execute();
        $s = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($s) {
            echo '[DAILY SUMMARY] Total=' . $s['total']
               . ' Critical=' . $s['critical']
               . ' High=' . $s['high']
               . ' AvgScore=' . round((float)$s['avg_score'], 1) . PHP_EOL;
        }
    } catch (Throwable $e) { /* ignore */ }
}
