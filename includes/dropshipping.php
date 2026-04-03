<?php
/**
 * GlobexSky Dropshipping Engine
 *
 * Core dropshipping functions: import, order processing, sync, earnings, catalog.
 * Loaded via middleware.php.
 */

// Dropship fee rate (platform takes 3% of selling price)
define('DROPSHIP_FEE_RATE', 0.03);
// Minimum/maximum markup constraints
define('DROPSHIP_MIN_MARKUP_PCT', 5.0);
define('DROPSHIP_MAX_MARKUP_PCT', 300.0);

/**
 * Check plan limits for a dropshipper.
 * Returns ['allowed' => bool, 'current_count' => int, 'max_count' => int|string, 'plan' => string]
 */
function checkDropshipPlanLimits(int $dropshipperId): array
{
    $db = getDB();

    // Determine plan
    $plan = 'free';
    $maxCount = 0;
    try {
        $stmt = $db->prepare('SELECT sp.slug, sp.limits FROM plan_subscriptions ps
            JOIN supplier_plans sp ON sp.id = ps.plan_id
            WHERE ps.supplier_id = ? AND ps.status = "active"
            ORDER BY ps.created_at DESC LIMIT 1');
        $stmt->execute([$dropshipperId]);
        $row = $stmt->fetch();
        if ($row) {
            $plan = $row['slug'] ?? 'free';
            $limits = json_decode($row['limits'] ?? '{}', true) ?: [];
            $maxCount = isset($limits['dropship_products']) ? (int)$limits['dropship_products'] : -1;
        }
    } catch (PDOException $e) { /* ignore */ }

    // Plan-based defaults if not set via subscription
    if ($maxCount === 0) {
        $maxCount = match (true) {
            str_contains($plan, 'enterprise') => -1,
            str_contains($plan, 'pro')        => 100,
            default                           => 0, // free cannot dropship
        };
    }

    if ($maxCount === 0) {
        return ['allowed' => false, 'current_count' => 0, 'max_count' => 0, 'plan' => $plan];
    }

    // Count current imported products
    $currentCount = 0;
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM dropship_products WHERE dropshipper_id = ? AND is_active = 1');
        $stmt->execute([$dropshipperId]);
        $currentCount = (int)$stmt->fetchColumn();
    } catch (PDOException $e) { /* ignore */ }

    $allowed = ($maxCount < 0) || ($currentCount < $maxCount);

    return [
        'allowed'       => $allowed,
        'current_count' => $currentCount,
        'max_count'     => $maxCount < 0 ? 'Unlimited' : $maxCount,
        'plan'          => $plan,
    ];
}

/**
 * Calculate markup: returns ['selling_price' => float, 'markup_amount' => float]
 * Validates 5%–300% for percentage; fixed ≥ 0.01 for fixed.
 */
function calculateMarkup(float $originalPrice, string $markupType, float $markupValue): array
{
    if ($markupType === 'percentage') {
        $markupValue = max(DROPSHIP_MIN_MARKUP_PCT, min(DROPSHIP_MAX_MARKUP_PCT, $markupValue));
        $markupAmount = round($originalPrice * $markupValue / 100, 2);
    } else {
        $markupAmount = max(0.01, round($markupValue, 2));
    }
    $sellingPrice = round($originalPrice + $markupAmount, 2);
    return ['selling_price' => $sellingPrice, 'markup_amount' => $markupAmount];
}

/**
 * Get or create a dropship store for a user.
 * Returns store row or null on failure.
 */
function getOrCreateDropshipStore(int $userId, string $storeName = ''): ?array
{
    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT * FROM dropship_stores WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $store = $stmt->fetch();
        if ($store) return $store;

        if (empty($storeName)) {
            // auto-generate name
            $userStmt = $db->prepare('SELECT first_name, email FROM users WHERE id = ?');
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch();
            $emailPrefix = explode('@', $user['email'] ?? 'user@x')[0];
            $displayName = $user['first_name'] ?? $emailPrefix;
            $storeName = trim($displayName . "'s Store");
        }
        $slug = generateStoreSlug($storeName, $db);

        $ins = $db->prepare('INSERT INTO dropship_stores (user_id, store_name, store_slug) VALUES (?,?,?)');
        $ins->execute([$userId, $storeName, $slug]);
        $id = (int)$db->lastInsertId();

        $stmt = $db->prepare('SELECT * FROM dropship_stores WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Generate a unique store slug.
 */
function generateStoreSlug(string $name, \PDO $db): string
{
    $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
    $base = trim($base, '-');
    $slug = $base;
    $i = 2;
    while (true) {
        try {
            $s = $db->prepare('SELECT id FROM dropship_stores WHERE store_slug = ?');
            $s->execute([$slug]);
            if (!$s->fetch()) break;
        } catch (PDOException $e) { break; }
        $slug = $base . '-' . $i++;
    }
    return $slug;
}

/**
 * Import a product into the dropshipper's store.
 * Returns ['success' => bool, 'product_id' => int|null, 'error' => string|null]
 */
function importProduct(
    int $dropshipperId,
    int $productId,
    string $markupType,
    float $markupValue,
    array $customData = []
): array {
    $db = getDB();

    // 1. Plan limit check
    $limits = checkDropshipPlanLimits($dropshipperId);
    if (!$limits['allowed']) {
        return ['success' => false, 'error' => 'Upgrade to Pro or Enterprise plan to use dropshipping. Free plan cannot dropship.'];
    }

    // 2. Fetch original product
    try {
        $stmt = $db->prepare('SELECT p.*, s.id AS sid FROM products p
            LEFT JOIN suppliers s ON s.user_id = p.supplier_id
            WHERE p.id = ? AND p.status = "active"');
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Product not found'];
    }
    if (!$product) {
        return ['success' => false, 'error' => 'Product not found or not active'];
    }

    // 3. Check supplier allows dropshipping
    $supplierId = (int)($product['supplier_id'] ?? $product['sid'] ?? 0);
    try {
        $sStmt = $db->prepare('SELECT * FROM supplier_dropship_settings WHERE supplier_id = ?');
        $sStmt->execute([$supplierId]);
        $settings = $sStmt->fetch();
        if ($settings && !(bool)$settings['allow_dropshipping']) {
            return ['success' => false, 'error' => 'Supplier does not allow dropshipping'];
        }
        // Validate markup against supplier constraints
        if ($settings && $markupType === 'percentage') {
            $markupValue = max((float)$settings['min_markup_percent'], min((float)$settings['max_markup_percent'], $markupValue));
        }
    } catch (PDOException $e) { /* table may not exist, allow by default */ }

    // 4. Get or create store
    $store = getOrCreateDropshipStore($dropshipperId);
    if (!$store) {
        return ['success' => false, 'error' => 'Could not create dropship store'];
    }
    $storeId = (int)$store['id'];

    // 5. Check not already imported
    try {
        $dupStmt = $db->prepare('SELECT id FROM dropship_products WHERE dropshipper_id = ? AND original_product_id = ?');
        $dupStmt->execute([$dropshipperId, $productId]);
        if ($dupStmt->fetch()) {
            return ['success' => false, 'error' => 'Product already imported'];
        }
    } catch (PDOException $e) { /* ignore */ }

    // 6. Calculate price
    $originalPrice = (float)($product['cost_price'] ?? $product['price'] ?? 0);
    $markup = calculateMarkup($originalPrice, $markupType, $markupValue);

    // 7. Insert dropship_products record
    try {
        $ins = $db->prepare('INSERT INTO dropship_products
            (store_id, dropshipper_id, original_product_id, supplier_id, custom_title, custom_description,
             custom_images, markup_type, markup_value, selling_price, original_price, is_active, is_auto_sync, last_synced_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,1,?,NOW())');
        $ins->execute([
            $storeId,
            $dropshipperId,
            $productId,
            $supplierId,
            $customData['custom_title'] ?? $product['name'],
            $customData['custom_description'] ?? $product['description'] ?? '',
            json_encode($customData['custom_images'] ?? json_decode($product['images'] ?? '[]', true)),
            $markupType,
            $markupValue,
            $markup['selling_price'],
            $originalPrice,
            (int)($customData['auto_sync'] ?? 1),
        ]);
        $newId = (int)$db->lastInsertId();

        // Update store product count
        $db->prepare('UPDATE dropship_stores SET total_products = total_products + 1 WHERE id = ?')
           ->execute([$storeId]);

        return ['success' => true, 'product_id' => $newId, 'selling_price' => $markup['selling_price']];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Process a dropship order when customer places an order on a dropship product.
 * Returns ['success' => bool, 'dropship_order_id' => int|null, 'error' => string|null]
 */
function processDropshipOrder(int $orderId): array
{
    $db = getDB();

    // Fetch order items that are dropship products
    try {
        $stmt = $db->prepare('SELECT oi.*, dp.id AS dp_id, dp.store_id, dp.dropshipper_id,
            dp.supplier_id, dp.selling_price, dp.original_price, dp.markup_value, dp.markup_type,
            dp.is_white_label, dp.custom_title, o.buyer_id AS customer_id
            FROM order_items oi
            JOIN dropship_products dp ON dp.original_product_id = oi.product_id
            JOIN orders o ON o.id = oi.order_id
            WHERE oi.order_id = ? AND dp.is_active = 1');
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll();
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Could not load order items'];
    }

    if (empty($items)) {
        return ['success' => false, 'error' => 'No dropship items in order'];
    }

    $createdIds = [];
    foreach ($items as $item) {
        $originalPrice  = (float)$item['original_price'];
        $sellingPrice   = (float)$item['selling_price'];
        $markupAmount   = $sellingPrice - $originalPrice;
        $dropshipFee    = round($sellingPrice * DROPSHIP_FEE_RATE, 2);
        $dropshipperEarning = round($markupAmount - $dropshipFee, 2);

        // Platform commission on supplier price (default 10%)
        $commissionRate = 0.10;
        $supplierEarning = round($originalPrice * (1 - $commissionRate), 2);
        $platformEarning = round($originalPrice * $commissionRate + $dropshipFee, 2);

        try {
            $ins = $db->prepare('INSERT INTO dropship_orders
                (order_id, dropshipper_id, supplier_id, store_id, customer_id,
                 original_price, selling_price, markup_amount, platform_dropship_fee,
                 dropshipper_earning, supplier_earning, platform_earning, status, routed_at)
                VALUES (?,?,?,?,?, ?,?,?,?, ?,?,?,"routed", NOW())');
            $ins->execute([
                $orderId,
                $item['dropshipper_id'],
                $item['supplier_id'],
                $item['store_id'],
                $item['customer_id'],
                $originalPrice,
                $sellingPrice,
                $markupAmount,
                $dropshipFee,
                $dropshipperEarning,
                $supplierEarning,
                $platformEarning,
            ]);
            $dsOrderId = (int)$db->lastInsertId();
            $createdIds[] = $dsOrderId;

            // Record dropshipper earning
            recordDropshipEarning($item['dropshipper_id'], $dsOrderId, $orderId,
                $markupAmount, $dropshipFee, $dropshipperEarning);

            // Update store stats
            $db->prepare('UPDATE dropship_stores SET total_orders = total_orders + 1,
                total_revenue = total_revenue + ? WHERE id = ?')
               ->execute([$sellingPrice, $item['store_id']]);

        } catch (PDOException $e) {
            // Log and continue with other items
        }
    }

    return ['success' => !empty($createdIds), 'dropship_order_ids' => $createdIds];
}

/**
 * Record a dropshipper earning entry.
 */
function recordDropshipEarning(
    int $dropshipperId,
    int $dropshipOrderId,
    int $orderId,
    float $grossAmount,
    float $platformFee,
    float $netAmount
): void {
    $db = getDB();
    try {
        $db->prepare('INSERT INTO dropship_earnings
            (dropshipper_id, dropship_order_id, order_id, gross_amount, platform_fee, net_amount,
             status, available_at)
            VALUES (?,?,?,?,?,?,"pending", DATE_ADD(NOW(), INTERVAL 7 DAY))')
           ->execute([$dropshipperId, $dropshipOrderId, $orderId, $grossAmount, $platformFee, $netAmount]);
    } catch (PDOException $e) { /* ignore */ }
}

/**
 * Sync a single dropship product with latest supplier data.
 * Returns ['updated' => bool, 'changes' => array, 'error' => string|null]
 */
function syncProduct(int $dropshipProductId): array
{
    $db = getDB();

    try {
        $stmt = $db->prepare('SELECT dp.*, p.price, p.cost_price, p.images, p.status AS product_status,
            p.name AS original_name
            FROM dropship_products dp
            JOIN products p ON p.id = dp.original_product_id
            WHERE dp.id = ?');
        $stmt->execute([$dropshipProductId]);
        $dp = $stmt->fetch();
    } catch (PDOException $e) {
        return ['updated' => false, 'error' => 'Not found'];
    }
    if (!$dp) return ['updated' => false, 'error' => 'Dropship product not found'];

    $changes = [];
    $updates = ['last_synced_at' => date('Y-m-d H:i:s')];

    // Check if original product is deactivated/deleted
    if ($dp['product_status'] !== 'active') {
        $updates['is_active'] = 0;
        $changes[] = 'Product discontinued — marked inactive';
    } else {
        // Price change check
        $newOriginalPrice = (float)($dp['cost_price'] ?? $dp['price'] ?? 0);
        if (abs($newOriginalPrice - (float)$dp['original_price']) > 0.001) {
            $markup = calculateMarkup($newOriginalPrice, $dp['markup_type'], (float)$dp['markup_value']);
            $updates['original_price']  = $newOriginalPrice;
            $updates['selling_price']   = $markup['selling_price'];
            $changes[] = 'Price updated from $' . number_format((float)$dp['original_price'], 2) .
                         ' to $' . number_format($newOriginalPrice, 2);
        }
        // Image sync
        if (!empty($dp['images']) && empty($dp['custom_images'])) {
            $updates['custom_images'] = $dp['images'];
        }
    }

    // Build update query
    $setParts = [];
    $params   = [];
    foreach ($updates as $col => $val) {
        $setParts[] = "$col = ?";
        $params[]   = $val;
    }
    $params[] = $dropshipProductId;

    try {
        $db->prepare('UPDATE dropship_products SET ' . implode(', ', $setParts) . ' WHERE id = ?')
           ->execute($params);
    } catch (PDOException $e) {
        return ['updated' => false, 'error' => 'DB error: ' . $e->getMessage()];
    }

    return ['updated' => true, 'changes' => $changes];
}

/**
 * Sync all active auto-sync products for a dropshipper (or all dropshippers if null).
 */
function syncAllProducts(?int $dropshipperId = null): array
{
    $db = getDB();
    $where  = 'is_active = 1 AND is_auto_sync = 1';
    $params = [];
    if ($dropshipperId !== null) {
        $where   .= ' AND dropshipper_id = ?';
        $params[] = $dropshipperId;
    }

    try {
        $stmt = $db->prepare("SELECT id FROM dropship_products WHERE $where");
        $stmt->execute($params);
        $ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return ['synced' => 0, 'errors' => 0];
    }

    $synced = 0;
    $errors = 0;
    foreach ($ids as $id) {
        $result = syncProduct((int)$id);
        if ($result['updated']) $synced++; else $errors++;
    }
    return ['synced' => $synced, 'errors' => $errors, 'total' => count($ids)];
}

/**
 * Update dropship order status (called by supplier or admin).
 */
function updateDropshipOrderStatus(int $dropshipOrderId, string $status): bool
{
    $db = getDB();
    $allowed = ['pending','routed','processing','shipped','delivered','cancelled','refunded'];
    if (!in_array($status, $allowed, true)) return false;

    $updates = ['status = ?', 'updated_at = NOW()'];
    $params  = [$status];

    if ($status === 'shipped') {
        $updates[] = 'shipped_at = NOW()';
    } elseif ($status === 'delivered') {
        $updates[] = 'delivered_at = NOW()';
        // Mark earnings as available after 7-day hold
        try {
            $db->prepare('UPDATE dropship_earnings SET status = "available",
                available_at = DATE_ADD(NOW(), INTERVAL 7 DAY)
                WHERE dropship_order_id = ? AND status = "pending"')
               ->execute([$dropshipOrderId]);
        } catch (PDOException $e) { /* ignore */ }
    }

    $params[] = $dropshipOrderId;
    try {
        $db->prepare('UPDATE dropship_orders SET ' . implode(', ', $updates) . ' WHERE id = ?')
           ->execute($params);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get dropship catalog — products available for dropshipping.
 * Returns ['products' => array, 'total' => int]
 */
function getDropshipCatalog(array $filters = [], int $page = 1, int $perPage = 20): array
{
    $db = getDB();
    $where  = ['p.status = "active"'];
    $params = [];

    // Only show products from suppliers who allow dropshipping
    $where[] = 'EXISTS (SELECT 1 FROM supplier_dropship_settings sds
        WHERE sds.supplier_id = p.supplier_id AND sds.allow_dropshipping = 1)
        OR p.dropship_eligible = 1';

    if (!empty($filters['category'])) {
        $where[]  = 'p.category_id = ?';
        $params[] = $filters['category'];
    }
    if (!empty($filters['q'])) {
        $where[]  = 'p.name LIKE ?';
        $params[] = '%' . $filters['q'] . '%';
    }
    if (!empty($filters['min_price'])) {
        $where[]  = 'p.cost_price >= ?';
        $params[] = (float)$filters['min_price'];
    }
    if (!empty($filters['max_price'])) {
        $where[]  = 'p.cost_price <= ?';
        $params[] = (float)$filters['max_price'];
    }

    $whereClause = implode(' AND ', $where);
    $offset = ($page - 1) * $perPage;

    $orderClause = match ($filters['sort'] ?? 'newest') {
        'price_asc'  => 'p.cost_price ASC',
        'price_desc' => 'p.cost_price DESC',
        'popular'    => 'p.order_count DESC',
        default      => 'p.created_at DESC',
    };

    try {
        $countStmt = $db->prepare("SELECT COUNT(*) FROM products p WHERE $whereClause");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT p.id, p.name, p.slug, p.images, p.cost_price, p.price,
                   p.short_desc, p.order_count,
                   s.company_name AS supplier_name,
                   c.name AS category_name,
                   COALESCE(sds.min_markup_percent, 5) AS min_markup,
                   COALESCE(sds.max_markup_percent, 300) AS max_markup,
                   COALESCE(sds.processing_time_days, 3) AS processing_days,
                   ROUND(p.cost_price * 1.20, 2) AS suggested_retail
            FROM products p
            LEFT JOIN suppliers s ON s.user_id = p.supplier_id
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN supplier_dropship_settings sds ON sds.supplier_id = p.supplier_id
            WHERE $whereClause
            ORDER BY $orderClause
            LIMIT ? OFFSET ?
        ");
        $stmt->execute(array_merge($params, [$perPage, $offset]));
        $products = $stmt->fetchAll();
    } catch (PDOException $e) {
        return ['products' => [], 'total' => 0];
    }

    return ['products' => $products, 'total' => $total, 'pages' => (int)ceil($total / $perPage)];
}

/**
 * Get earnings summary for a dropshipper.
 */
function getDropshipperEarnings(int $dropshipperId, string $period = '30days'): array
{
    $db = getDB();
    $dateFilter = match ($period) {
        '7days'   => 'AND de.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
        '90days'  => 'AND de.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)',
        'all'     => '',
        default   => 'AND de.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
    };

    $result = [
        'total'     => 0.0,
        'pending'   => 0.0,
        'available' => 0.0,
        'paid'      => 0.0,
        'by_day'    => [],
    ];

    try {
        $stmt = $db->prepare("SELECT
            COALESCE(SUM(net_amount), 0) AS total,
            COALESCE(SUM(CASE WHEN status='pending' THEN net_amount ELSE 0 END), 0) AS pending,
            COALESCE(SUM(CASE WHEN status='available' THEN net_amount ELSE 0 END), 0) AS available,
            COALESCE(SUM(CASE WHEN status='paid' THEN net_amount ELSE 0 END), 0) AS paid
            FROM dropship_earnings de WHERE dropshipper_id = ? $dateFilter");
        $stmt->execute([$dropshipperId]);
        $row = $stmt->fetch();
        $result = array_merge($result, array_map('floatval', (array)$row));

        // Daily chart
        $dailyStmt = $db->prepare("SELECT DATE(de.created_at) AS day,
            COALESCE(SUM(de.net_amount), 0) AS earnings
            FROM dropship_earnings de
            WHERE dropshipper_id = ? AND de.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(de.created_at)
            ORDER BY day");
        $dailyStmt->execute([$dropshipperId]);
        $result['by_day'] = $dailyStmt->fetchAll();
    } catch (PDOException $e) { /* ignore */ }

    return $result;
}
