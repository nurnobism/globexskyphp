<?php
/**
 * includes/wishlist.php — Wishlist Helper Library
 *
 * All operations require an authenticated user (wishlist is not available to guests).
 * DB table: wishlist_items (id, user_id, product_id, added_at)
 */

require_once __DIR__ . '/feature_toggles.php';

// ---------------------------------------------------------------------------
// Internal helpers
// ---------------------------------------------------------------------------

/**
 * Fetch an active product with supplier info.
 */
function _wishlistGetProduct(int $productId): array|false
{
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT p.*, s.company_name supplier_name
         FROM products p
         LEFT JOIN suppliers s ON s.id = p.supplier_id
         WHERE p.id = ? AND p.status = "active"'
    );
    $stmt->execute([$productId]);
    return $stmt->fetch();
}

// ---------------------------------------------------------------------------
// Wishlist CRUD
// ---------------------------------------------------------------------------

/**
 * Add a product to the user's wishlist.
 * Prevents duplicates.
 * Returns ['success'=>true] or ['error'=>'...']
 */
function addToWishlist(int $userId, int $productId): array
{
    if ($userId <= 0) {
        return ['error' => 'Login required to save items to wishlist.'];
    }

    $product = _wishlistGetProduct($productId);
    if (!$product) {
        return ['error' => 'Product not found or unavailable.'];
    }

    $db = getDB();

    // Prevent duplicates
    $checkStmt = $db->prepare('SELECT id FROM wishlist_items WHERE user_id = ? AND product_id = ?');
    $checkStmt->execute([$userId, $productId]);
    if ($checkStmt->fetch()) {
        return ['success' => true, 'message' => 'Already in wishlist.', 'already_exists' => true];
    }

    $db->prepare('INSERT INTO wishlist_items (user_id, product_id) VALUES (?, ?)')->execute([$userId, $productId]);

    return ['success' => true, 'count' => getWishlistCount($userId)];
}

/**
 * Remove a product from the user's wishlist.
 */
function removeFromWishlist(int $userId, int $productId): array
{
    if ($userId <= 0) {
        return ['error' => 'Login required.'];
    }

    getDB()->prepare('DELETE FROM wishlist_items WHERE user_id = ? AND product_id = ?')
           ->execute([$userId, $productId]);

    return ['success' => true, 'count' => getWishlistCount($userId)];
}

/**
 * Get wishlist items with pagination and product details.
 *
 * @param  int    $userId
 * @param  int    $page    1-based page number
 * @param  int    $perPage Items per page
 * @param  string $sort    'date_added'|'price_asc'|'price_desc'|'name'
 * @return array  ['items'=>[...], 'total'=>N, 'page'=>N, 'per_page'=>N, 'total_pages'=>N]
 */
function getWishlist(int $userId, int $page = 1, int $perPage = 20, string $sort = 'date_added'): array
{
    if ($userId <= 0) {
        return ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'total_pages' => 0];
    }

    $orderBy = match ($sort) {
        'price_asc'  => 'p.price ASC',
        'price_desc' => 'p.price DESC',
        'name'       => 'p.name ASC',
        default      => 'w.added_at DESC',
    };

    $db     = getDB();
    $offset = ($page - 1) * $perPage;

    // Total count
    $cntStmt = $db->prepare('SELECT COUNT(*) FROM wishlist_items w WHERE w.user_id = ?');
    $cntStmt->execute([$userId]);
    $total = (int)$cntStmt->fetchColumn();

    $stmt = $db->prepare(
        "SELECT w.id wishlist_id, w.added_at, w.product_id,
                p.name, p.slug, p.price, p.images, p.stock_qty, p.currency,
                s.company_name supplier_name
         FROM wishlist_items w
         JOIN products p ON p.id = w.product_id
         LEFT JOIN suppliers s ON s.id = p.supplier_id
         WHERE w.user_id = ?
         ORDER BY $orderBy
         LIMIT ? OFFSET ?"
    );
    $stmt->execute([$userId, $perPage, $offset]);
    $rows = $stmt->fetchAll();

    // Enrich items
    $items = [];
    foreach ($rows as $row) {
        $imgArr = is_string($row['images'] ?? '') ? json_decode($row['images'], true) : ($row['images'] ?? []);
        $img    = (is_array($imgArr) && !empty($imgArr[0])) ? $imgArr[0] : '';
        $items[] = array_merge($row, ['image' => $img]);
    }

    return [
        'items'       => $items,
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => (int)ceil($total / $perPage),
    ];
}

/**
 * Get wishlist item count for a user.
 */
function getWishlistCount(int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }

    $stmt = getDB()->prepare('SELECT COUNT(*) FROM wishlist_items WHERE user_id = ?');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Check whether a product is in the user's wishlist.
 * Returns ['in_wishlist'=>bool, 'wishlist_id'=>int|null]
 */
function isInWishlist(int $userId, int $productId): array
{
    if ($userId <= 0) {
        return ['in_wishlist' => false, 'wishlist_id' => null];
    }

    $stmt = getDB()->prepare('SELECT id FROM wishlist_items WHERE user_id = ? AND product_id = ?');
    $stmt->execute([$userId, $productId]);
    $row = $stmt->fetch();

    return ['in_wishlist' => (bool)$row, 'wishlist_id' => $row ? (int)$row['id'] : null];
}

/**
 * Move a wishlist item to the cart, then remove from wishlist.
 * Requires includes/cart.php to be loaded.
 */
function moveToCart(int $userId, int $productId, ?int $variantId, int $quantity = 1): array
{
    if ($userId <= 0) {
        return ['error' => 'Login required.'];
    }

    if (!function_exists('addToCart')) {
        require_once __DIR__ . '/cart.php';
    }

    $result = addToCart($userId, $productId, $variantId, $quantity);
    if (!empty($result['success'])) {
        // Remove from wishlist
        removeFromWishlist($userId, $productId);
        $result['message'] = 'Item moved to cart.';
    }

    return $result;
}

/**
 * Get wishlist items where the price has dropped since they were added.
 * Useful for "price drop" notifications.
 *
 * Returns array of items with price_when_added and current price.
 * Note: this requires a price_at_added column; if not available, returns [].
 */
function getWishlistPriceAlerts(int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    // The wishlist_items table may not have a price_at_added column.
    // We return items that are currently on sale compared to their list price.
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT w.id wishlist_id, w.product_id, w.added_at,
                p.name, p.slug, p.price, p.sale_price, p.images
         FROM wishlist_items w
         JOIN products p ON p.id = w.product_id
         WHERE w.user_id = ?
           AND p.sale_price IS NOT NULL
           AND p.sale_price > 0
           AND p.sale_price < p.price'
    );
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    $alerts = [];
    foreach ($rows as $row) {
        $imgArr   = is_string($row['images'] ?? '') ? json_decode($row['images'], true) : ($row['images'] ?? []);
        $img      = (is_array($imgArr) && !empty($imgArr[0])) ? $imgArr[0] : '';
        $drop     = round(100 * (1 - $row['sale_price'] / $row['price']), 0);
        $alerts[] = array_merge($row, ['image' => $img, 'discount_percent' => $drop]);
    }

    return $alerts;
}
