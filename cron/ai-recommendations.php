<?php
/**
 * cron/ai-recommendations.php — Daily AI Recommendations Cron (Phase 8)
 * Schedule: 0 2 * * * php /path/to/cron/ai-recommendations.php
 */

define('CRON_MODE', true);
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/deepseek.php';
require_once __DIR__ . '/../includes/ai-recommendations.php';

$db    = getDB();
$start = microtime(true);
$log   = function(string $msg) { echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL; };

$log('Starting AI recommendations cron...');

// 1. Expire old recommendations
try {
    $expired = $db->exec("DELETE FROM ai_recommendations WHERE expires_at IS NOT NULL AND expires_at < NOW()");
    $log("Expired $expired old recommendations.");
} catch (PDOException $e) {
    $log('Error expiring recommendations: ' . $e->getMessage());
}

// 2. Get active users (ordered in last 30 days)
$activeUsers = [];
try {
    $stmt = $db->query(
        "SELECT DISTINCT buyer_id AS user_id FROM orders
         WHERE placed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         ORDER BY buyer_id LIMIT 500"
    );
    $activeUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $log('Error fetching active users: ' . $e->getMessage());
}

$log("Processing " . count($activeUsers) . " active users...");

// 3. Generate recommendations for each user
$ai      = getDeepSeek();
$success = 0;
$errors  = 0;

foreach ($activeUsers as $userId) {
    try {
        // Skip if fresh recommendations exist (less than 12 hours old)
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM ai_recommendations
             WHERE user_id = ? AND recommendation_type = 'personalized'
               AND created_at >= DATE_SUB(NOW(), INTERVAL 12 HOUR)"
        );
        $stmt->execute([$userId]);
        if ($stmt->fetchColumn() > 0) {
            continue;
        }

        $ai->getRecommendations((int)$userId, 'personalized');
        $success++;
        usleep(500000); // 0.5s delay between API calls
    } catch (Throwable $e) {
        $log("Error for user $userId: " . $e->getMessage());
        $errors++;
    }
}

// 4. Calculate CTR and conversion metrics
try {
    $ctrResult = $db->query(
        "SELECT
            COUNT(*) AS total,
            SUM(is_clicked) AS clicks,
            SUM(is_purchased) AS purchases
         FROM ai_recommendations
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    )->fetch(PDO::FETCH_ASSOC);

    if ($ctrResult && $ctrResult['total'] > 0) {
        $ctr = round(($ctrResult['clicks'] / $ctrResult['total']) * 100, 2);
        $cvr = round(($ctrResult['purchases'] / $ctrResult['total']) * 100, 2);
        $log("Performance (30d): CTR={$ctr}%, CVR={$cvr}%");
    }
} catch (PDOException $e) {
    $log('Error calculating metrics: ' . $e->getMessage());
}

$elapsed = round(microtime(true) - $start, 2);
$log("Completed. Success: $success, Errors: $errors, Time: {$elapsed}s");
