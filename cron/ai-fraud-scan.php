<?php
/**
 * cron/ai-fraud-scan.php — Hourly AI Fraud Scan Cron (Phase 8)
 * Schedule: 0 * * * * php /path/to/cron/ai-fraud-scan.php
 */

define('CRON_MODE', true);
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/deepseek.php';
require_once __DIR__ . '/../includes/ai-fraud.php';

$db    = getDB();
$start = microtime(true);
$log   = function(string $msg) { echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL; };

$log('Starting AI fraud scan cron...');

$scanned  = 0;
$critical = 0;
$high     = 0;

// 1. Scan recent orders (last 2 hours, not yet analyzed)
try {
    $stmt = $db->query(
        "SELECT o.id FROM orders o
         LEFT JOIN ai_fraud_logs fl ON fl.entity_type = 'order' AND fl.entity_id = o.id
         WHERE o.placed_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
           AND fl.id IS NULL
         LIMIT 100"
    );
    $orders = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $log("Scanning " . count($orders) . " new orders...");

    foreach ($orders as $orderId) {
        try {
            $result = analyzeOrder((int)$orderId);
            $scanned++;
            if ($result['risk_level'] === 'critical') $critical++;
            elseif ($result['risk_level'] === 'high') $high++;
            usleep(300000);
        } catch (Throwable $e) {
            $log("Error scanning order $orderId: " . $e->getMessage());
        }
    }
} catch (PDOException $e) {
    $log('Error fetching orders: ' . $e->getMessage());
}

// 2. Scan new users (registered in last 2 hours)
try {
    $stmt = $db->query(
        "SELECT u.id FROM users u
         LEFT JOIN ai_fraud_logs fl ON fl.entity_type = 'user' AND fl.entity_id = u.id
         WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
           AND fl.id IS NULL
         LIMIT 50"
    );
    $newUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $log("Scanning " . count($newUsers) . " new users...");

    foreach ($newUsers as $userId) {
        try {
            $result = analyzeUser((int)$userId);
            $scanned++;
            if ($result['risk_level'] === 'critical') $critical++;
            elseif ($result['risk_level'] === 'high') $high++;
            usleep(300000);
        } catch (Throwable $e) {
            $log("Error scanning user $userId: " . $e->getMessage());
        }
    }
} catch (PDOException $e) {
    $log('Error fetching new users: ' . $e->getMessage());
}

// 3. Scan recent reviews
try {
    $stmt = $db->query(
        "SELECT r.id FROM reviews r
         LEFT JOIN ai_fraud_logs fl ON fl.entity_type = 'review' AND fl.entity_id = r.id
         WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
           AND fl.id IS NULL
         LIMIT 50"
    );
    $reviews = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $log("Scanning " . count($reviews) . " new reviews...");

    foreach ($reviews as $reviewId) {
        try {
            $result = analyzeReview((int)$reviewId);
            $scanned++;
            if ($result['risk_level'] === 'critical') $critical++;
            usleep(300000);
        } catch (Throwable $e) {
            $log("Error scanning review $reviewId: " . $e->getMessage());
        }
    }
} catch (PDOException $e) {
    $log('Error fetching reviews: ' . $e->getMessage());
}

// 4. Alert admins for critical findings
if ($critical > 0) {
    $log("ALERT: $critical critical fraud findings detected! Notifying admins...");
    try {
        $adminStmt = $db->query("SELECT email FROM users WHERE role IN ('admin','super_admin') AND status='active' LIMIT 5");
        $admins = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
        $log("Admin notification targets: " . implode(', ', $admins));
        // Email notification handled by mailer in production
    } catch (PDOException $e) { /* ignore */ }
}

$elapsed = round(microtime(true) - $start, 2);
$log("Fraud scan complete. Scanned: $scanned, Critical: $critical, High: $high, Time: {$elapsed}s");
