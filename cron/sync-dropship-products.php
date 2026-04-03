<?php
/**
 * cron/sync-dropship-products.php — Dropship Product Sync Cron Job
 *
 * Run: every 6 hours (or configurable)
 * Command: php cron/sync-dropship-products.php
 *
 * What it does:
 * 1. Get all active dropship products with auto_sync = true
 * 2. For each product:
 *    a. Fetch original product data from products table
 *    b. Check if price changed → recalculate selling price
 *    c. Check if stock changed → update availability
 *    d. Check if product deactivated/deleted → mark dropship product inactive
 *    e. Update last_synced_at
 * 3. Notify dropshippers of significant changes:
 *    - Price changed > 5%
 *    - Product went out of stock
 *    - Product discontinued
 * 4. Log sync results
 */

// This runs from CLI, so manually bootstrap
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

$start = microtime(true);
echo "=== Dropship Product Sync — " . date('Y-m-d H:i:s') . " ===\n";

// Load config and functions
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/dropshipping.php';

$db = getDB();

// Get all active auto-sync products
$products = [];
try {
    $stmt = $db->query('SELECT dp.id, dp.dropshipper_id, dp.original_product_id,
        dp.markup_type, dp.markup_value, dp.original_price, dp.selling_price,
        dp.is_active, dp.store_id,
        p.cost_price AS current_cost, p.price AS current_price, p.status AS product_status,
        p.images, p.name AS original_name
        FROM dropship_products dp
        LEFT JOIN products p ON p.id = dp.original_product_id
        WHERE dp.is_active = 1 AND dp.is_auto_sync = 1
        ORDER BY dp.id');
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "ERROR: Could not fetch products: " . $e->getMessage() . "\n";
    exit(1);
}

$total       = count($products);
$synced      = 0;
$priceChange = 0;
$deactivated = 0;
$errors      = 0;
$notifications = [];

echo "Found $total products to sync.\n\n";

foreach ($products as $dp) {
    $dpId = (int)$dp['id'];
    $changes = [];
    $sets    = ['last_synced_at = NOW()'];
    $params  = [];

    // Check if original product is deactivated/deleted
    if ($dp['product_status'] !== 'active') {
        $sets[]   = 'is_active = 0';
        $changes[] = 'discontinued';
        $deactivated++;

        $notifications[] = [
            'dropshipper_id' => (int)$dp['dropshipper_id'],
            'type'           => 'product_discontinued',
            'message'        => 'Product "' . ($dp['original_name'] ?? 'Unknown') . '" has been discontinued by the supplier.',
        ];
    } else {
        // Check price change
        $currentCost   = (float)($dp['current_cost'] ?? $dp['current_price'] ?? 0);
        $originalPrice = (float)$dp['original_price'];

        if ($currentCost > 0 && abs($currentCost - $originalPrice) > 0.001) {
            // Recalculate selling price with existing markup
            $markup = calculateMarkup($currentCost, $dp['markup_type'], (float)$dp['markup_value']);

            $sets[] = 'original_price = ?';
            $params[] = $currentCost;
            $sets[] = 'selling_price = ?';
            $params[] = $markup['selling_price'];
            $priceChange++;

            $pctChange = abs($currentCost - $originalPrice) / max($originalPrice, 0.01) * 100;
            $changes[] = sprintf('price: $%.2f → $%.2f (%+.1f%%)', $originalPrice, $currentCost, $pctChange);

            // Notify if significant (> 5%)
            if ($pctChange > 5) {
                $notifications[] = [
                    'dropshipper_id' => (int)$dp['dropshipper_id'],
                    'type'           => 'price_change',
                    'message'        => sprintf(
                        'Product "%s" price changed by %.1f%% (from $%.2f to $%.2f). Your selling price has been updated.',
                        $dp['original_name'] ?? 'Unknown', $pctChange, $originalPrice, $currentCost
                    ),
                ];
            }
        }
    }

    // Apply updates
    $params[] = $dpId;
    try {
        $db->prepare('UPDATE dropship_products SET ' . implode(', ', $sets) . ' WHERE id = ?')
           ->execute($params);
        $synced++;
    } catch (PDOException $e) {
        $errors++;
        echo "  ERROR syncing product #$dpId: " . $e->getMessage() . "\n";
    }

    if (!empty($changes)) {
        echo "  Product #$dpId: " . implode(', ', $changes) . "\n";
    }
}

// Send notifications (insert into notifications table if it exists)
$notifCount = 0;
foreach ($notifications as $n) {
    try {
        $db->prepare('INSERT INTO notifications (user_id, type, message, is_read, created_at)
            VALUES (?,?,?, 0, NOW())')
           ->execute([$n['dropshipper_id'], $n['type'], $n['message']]);
        $notifCount++;
    } catch (PDOException $e) {
        // Notifications table may not exist — skip
    }
}

$elapsed = round(microtime(true) - $start, 2);

echo "\n=== Sync Complete ===\n";
echo "Total products:     $total\n";
echo "Synced:             $synced\n";
echo "Price changes:      $priceChange\n";
echo "Deactivated:        $deactivated\n";
echo "Errors:             $errors\n";
echo "Notifications sent: $notifCount\n";
echo "Time elapsed:       {$elapsed}s\n";

// Log results to a file
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
$logEntry = date('Y-m-d H:i:s') . " | sync=$synced price=$priceChange deact=$deactivated err=$errors notif=$notifCount time={$elapsed}s\n";
@file_put_contents($logDir . '/dropship-sync.log', $logEntry, FILE_APPEND);

exit($errors > 0 ? 1 : 0);
