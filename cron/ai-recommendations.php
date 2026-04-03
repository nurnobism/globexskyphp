<?php
/**
 * cron/ai-recommendations.php — Daily AI Recommendations Generator
 *
 * Run via cron: 0 2 * * * php /path/to/cron/ai-recommendations.php
 *
 * - Generates personalised recommendations for active users
 * - Cleans up expired recommendation entries
 * - Logs execution statistics
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
require_once APP_ROOT . '/includes/ai-recommendations.php';

$start    = microtime(true);
$total    = 0;
$success  = 0;
$skipped  = 0;
$errors   = 0;

echo '[' . date('Y-m-d H:i:s') . '] AI Recommendations Cron starting...' . PHP_EOL;

// Get active users with recent activity (last 30 days)
try {
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT DISTINCT user_id
         FROM orders
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
           AND status NOT IN ('cancelled')
         UNION
         SELECT DISTINCT user_id
         FROM cart_items
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         LIMIT 1000"
    );
    $stmt->execute();
    $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    echo 'ERROR: Could not fetch users: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

echo 'Processing ' . count($userIds) . ' users...' . PHP_EOL;

foreach ($userIds as $userId) {
    $total++;
    try {
        // Check if recommendations are still fresh (within configured hours)
        $refreshHours = (int)getAiConfig('recommendation_refresh_hours', '24');
        $stmt         = $db->prepare(
            'SELECT COUNT(*) FROM ai_recommendations
             WHERE user_id = ?
               AND recommendation_type = ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)'
        );
        $stmt->execute([$userId, 'personalized', $refreshHours]);
        if ((int)$stmt->fetchColumn() > 0) {
            $skipped++;
            continue;
        }

        $ok = generateRecommendations((int)$userId);
        if ($ok) {
            $success++;
        } else {
            $skipped++;
        }

        // Small sleep to avoid rate limiting
        usleep(200000); // 200ms
    } catch (Throwable $e) {
        $errors++;
        error_log('Recommendations cron error for user ' . $userId . ': ' . $e->getMessage());
    }
}

// Clean up expired recommendations
try {
    $deleted = $db->exec("DELETE FROM ai_recommendations WHERE expires_at < NOW()");
    echo 'Cleaned up ' . (int)$deleted . ' expired recommendations.' . PHP_EOL;
} catch (Throwable $e) {
    echo 'Warning: Could not clean expired recs: ' . $e->getMessage() . PHP_EOL;
}

// Clean up expired content cache
try {
    $deleted = $db->exec("DELETE FROM ai_content_cache WHERE expires_at < NOW()");
    echo 'Cleaned up ' . (int)$deleted . ' expired cache entries.' . PHP_EOL;
} catch (Throwable $e) { /* ignore */ }

$elapsed = round(microtime(true) - $start, 2);
echo '[' . date('Y-m-d H:i:s') . '] Done. '
   . "Total=$total, Success=$success, Skipped=$skipped, Errors=$errors, Time={$elapsed}s" . PHP_EOL;

exit($errors > 0 ? 1 : 0);
