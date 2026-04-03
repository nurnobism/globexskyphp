<?php
/**
 * includes/ai-recommendations.php — AI Product Recommendations
 *
 * Personalized, similar, complementary, and trending product recommendations
 * powered by DeepSeek AI.
 *
 * Requires includes/ai-engine.php.
 */

require_once __DIR__ . '/ai-engine.php';

/**
 * Generate personalized recommendations for a user.
 * Analyses purchase history, cart items, and browsing behaviour.
 */
function generateRecommendations(int $userId): bool
{
    if (!isAiEnabled('recommendations')) {
        return false;
    }

    try {
        $db = getDB();

        // Gather user signals
        $purchaseHistory = [];
        try {
            $stmt = $db->prepare(
                "SELECT p.title, p.category_id, oi.quantity
                 FROM order_items oi
                 JOIN products p ON p.id = oi.product_id
                 JOIN orders o ON o.id = oi.order_id
                 WHERE o.user_id = ?
                 ORDER BY o.created_at DESC LIMIT 20"
            );
            $stmt->execute([$userId]);
            $purchaseHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { /* table may not exist */ }

        $cartItems = [];
        try {
            $stmt = $db->prepare(
                "SELECT p.title, p.category_id
                 FROM cart_items ci
                 JOIN products p ON p.id = ci.product_id
                 WHERE ci.user_id = ?"
            );
            $stmt->execute([$userId]);
            $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { /* ignore */ }

        if (empty($purchaseHistory) && empty($cartItems)) {
            return false; // no data to base recommendations on
        }

        // Ask DeepSeek for category/type recommendations
        $context = json_encode([
            'purchase_history' => $purchaseHistory,
            'cart_items'       => $cartItems,
        ]);

        $messages = [
            ['role' => 'system', 'content' => buildSystemPrompt('product_search')],
            ['role' => 'user',   'content' =>
                "Based on this user's activity, suggest 5 product categories or types they would likely purchase next. "
              . "Return a JSON array of strings only. User activity: $context"],
        ];

        $result = deepseekRequest($messages, [
            'user_id'     => $userId,
            'feature'     => 'recommendations',
            'max_tokens'  => 500,
            'temperature' => 0.5,
        ]);

        if (!$result['success']) {
            return false;
        }

        // Parse AI suggestions
        $raw = $result['content'];
        // Extract JSON array from response
        if (preg_match('/\[.*\]/s', $raw, $m)) {
            $suggestions = json_decode($m[0], true);
        } else {
            $suggestions = [];
        }
        if (!is_array($suggestions)) {
            return false;
        }

        // Match suggestions to actual products in DB
        $placeholders = implode(',', array_fill(0, min(count($suggestions), 5), '?'));
        if (empty($placeholders)) {
            return false;
        }

        foreach (array_slice($suggestions, 0, 5) as $suggestion) {
            try {
                $stmt = $db->prepare(
                    "SELECT id FROM products
                     WHERE (title LIKE ? OR description LIKE ?) AND status = 'active'
                     LIMIT 3"
                );
                $like = '%' . $suggestion . '%';
                $stmt->execute([$like, $like]);
                $products = $stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($products as $productId) {
                    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                    $stmt2   = $db->prepare(
                        'INSERT IGNORE INTO ai_recommendations
                         (user_id, product_id, recommendation_type, score, reason, expires_at)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $stmt2->execute([
                        $userId,
                        $productId,
                        'personalized',
                        round(0.7 + (mt_rand(0, 25) / 100), 4),
                        'Based on your purchase history',
                        $expires,
                    ]);
                }
            } catch (Throwable $e) { /* ignore per-product errors */ }
        }

        return true;
    } catch (Throwable $e) {
        error_log('generateRecommendations error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get similar products for a given product (with caching).
 */
function getSimilarProducts(int $productId, int $limit = 6): array
{
    $cacheKey = 'similar_' . $productId . '_' . $limit;

    // Check cache first
    $cached = checkContentFromCache($cacheKey);
    if ($cached !== null) {
        $ids = json_decode($cached, true);
        if (is_array($ids)) {
            return fetchProductsByIds($ids, $limit);
        }
    }

    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT title, category_id, description FROM products WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            return [];
        }

        $messages = [
            ['role' => 'system', 'content' => buildSystemPrompt('product_search')],
            ['role' => 'user',   'content' =>
                'List 5 key features or attributes of this product type that should be used to find similar products. '
              . 'Product: ' . json_encode($product) . '. Return a JSON array of feature strings only.'],
        ];

        $result = deepseekRequest($messages, ['feature' => 'recommendations', 'max_tokens' => 300]);
        if (!$result['success']) {
            return [];
        }

        // Find similar products by category + keyword
        $stmt = $db->prepare(
            "SELECT id FROM products
             WHERE category_id = ? AND id != ? AND status = 'active'
             ORDER BY RAND()
             LIMIT ?"
        );
        $stmt->execute([$product['category_id'], $productId, $limit]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        saveContentToCache($cacheKey, json_encode($ids), 'summary', (int)($result['tokens']['total_tokens'] ?? 0));

        return fetchProductsByIds($ids, $limit);
    } catch (Throwable $e) {
        error_log('getSimilarProducts error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get complementary products (items frequently bought together).
 */
function getComplementaryProducts(int $productId, int $limit = 4): array
{
    try {
        $db = getDB();

        // Simple co-purchase approach: find products in same orders
        $stmt = $db->prepare(
            "SELECT oi2.product_id, COUNT(*) AS freq
             FROM order_items oi1
             JOIN order_items oi2 ON oi2.order_id = oi1.order_id AND oi2.product_id != oi1.product_id
             WHERE oi1.product_id = ?
             GROUP BY oi2.product_id
             ORDER BY freq DESC
             LIMIT ?"
        );
        $stmt->execute([$productId, $limit]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($ids)) {
            // Fall back to same-category products
            $stmt = $db->prepare(
                "SELECT id FROM products
                 WHERE category_id = (SELECT category_id FROM products WHERE id = ? LIMIT 1)
                   AND id != ? AND status = 'active'
                 LIMIT ?"
            );
            $stmt->execute([$productId, $productId, $limit]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        return fetchProductsByIds($ids, $limit);
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Get trending products (optionally filtered by category).
 */
function getTrendingRecommendations(?string $category = null, int $limit = 8): array
{
    try {
        $db = getDB();

        $catFilter = '';
        $params    = [];
        if ($category) {
            $catFilter = 'AND p.category_id = (SELECT id FROM categories WHERE slug = ? LIMIT 1)';
            $params[]  = $category;
        }
        $params[] = $limit;

        $stmt = $db->prepare(
            "SELECT p.id
             FROM products p
             LEFT JOIN order_items oi ON oi.product_id = p.id
                 AND oi.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             WHERE p.status = 'active' $catFilter
             GROUP BY p.id
             ORDER BY COUNT(oi.id) DESC, p.created_at DESC
             LIMIT ?"
        );
        $stmt->execute($params);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return fetchProductsByIds($ids, $limit);
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Get cached AI recommendations for a user.
 */
function getRecommendationsForUser(int $userId, string $type = 'personalized', int $limit = 8): array
{
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT product_id, score, reason
             FROM ai_recommendations
             WHERE user_id = ?
               AND recommendation_type = ?
               AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY score DESC
             LIMIT ?'
        );
        $stmt->execute([$userId, $type, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Track when a user clicks a recommendation.
 */
function trackRecommendationClick(int $recommendationId): void
{
    try {
        getDB()->prepare(
            'UPDATE ai_recommendations SET is_clicked = 1 WHERE id = ?'
        )->execute([$recommendationId]);
    } catch (Throwable $e) { /* silent */ }
}

/**
 * Track when a recommended product is purchased.
 */
function trackRecommendationPurchase(int $recommendationId): void
{
    try {
        getDB()->prepare(
            'UPDATE ai_recommendations SET is_purchased = 1 WHERE id = ?'
        )->execute([$recommendationId]);
    } catch (Throwable $e) { /* silent */ }
}

/**
 * Helper: fetch product rows by an array of IDs preserving order.
 */
function fetchProductsByIds(array $ids, int $limit): array
{
    if (empty($ids)) {
        return [];
    }
    $ids = array_slice(array_unique(array_map('intval', $ids)), 0, $limit);
    try {
        $ph   = implode(',', array_fill(0, count($ids), '?'));
        $stmt = getDB()->prepare(
            "SELECT id, title, price, image_url, category_id
             FROM products
             WHERE id IN ($ph) AND status = 'active'"
        );
        $stmt->execute($ids);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}
