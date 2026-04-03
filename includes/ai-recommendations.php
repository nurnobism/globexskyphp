<?php
/**
 * includes/ai-recommendations.php — AI Product Recommendation Helpers (Phase 8)
 */

require_once __DIR__ . '/deepseek.php';

/**
 * Get AI-personalized product recommendations for a user.
 */
function getPersonalizedRecommendations(int $userId, int $limit = 8): array {
    $db = getDB();
    try {
        $stmt = $db->prepare(
            "SELECT r.id, r.recommended_product_id, r.score, r.reason, r.recommendation_type,
                    p.name, p.price, p.image, p.rating, p.review_count,
                    c.name AS category_name
             FROM ai_recommendations r
             JOIN products p ON p.id = r.recommended_product_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE r.user_id = ? AND r.recommendation_type = 'personalized'
               AND (r.expires_at IS NULL OR r.expires_at > NOW())
             ORDER BY r.score DESC LIMIT ?"
        );
        $stmt->execute([$userId, $limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            return $rows;
        }
    } catch (PDOException $e) { /* fall through to generate */ }

    // Generate fresh recommendations
    try {
        $ai = getDeepSeek();
        $ai->getRecommendations($userId, 'personalized');
        return getPersonalizedRecommendations($userId, $limit);
    } catch (Throwable $e) {
        error_log('AI personalized recs error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Find similar products using AI analysis.
 */
function getSimilarProducts(int $productId, int $limit = 6): array {
    $db = getDB();
    try {
        $stmt = $db->prepare(
            "SELECT r.id, r.recommended_product_id, r.score, r.reason,
                    p.name, p.price, p.image, p.rating, p.review_count,
                    c.name AS category_name
             FROM ai_recommendations r
             JOIN products p ON p.id = r.recommended_product_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE r.source_product_id = ? AND r.recommendation_type = 'similar'
               AND (r.expires_at IS NULL OR r.expires_at > NOW())
             ORDER BY r.score DESC LIMIT ?"
        );
        $stmt->execute([$productId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get frequently bought together products.
 */
function getFrequentlyBoughtTogether(int $productId, int $limit = 4): array {
    $db = getDB();
    try {
        $stmt = $db->prepare(
            "SELECT oi2.product_id, p.name, p.price, p.image, p.rating,
                    COUNT(*) AS frequency, c.name AS category_name
             FROM order_items oi1
             JOIN order_items oi2 ON oi2.order_id = oi1.order_id AND oi2.product_id != oi1.product_id
             JOIN products p ON p.id = oi2.product_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE oi1.product_id = ? AND p.status = 'active'
             GROUP BY oi2.product_id ORDER BY frequency DESC LIMIT ?"
        );
        $stmt->execute([$productId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get AI-curated trending products in a category.
 */
function getTrendingProducts(string $category = '', int $limit = 8): array {
    $db = getDB();
    try {
        if ($category) {
            $stmt = $db->prepare(
                "SELECT p.id, p.name, p.price, p.image, p.rating, p.review_count,
                        c.name AS category_name, s.company_name AS supplier_name
                 FROM products p
                 LEFT JOIN categories c ON c.id = p.category_id
                 LEFT JOIN suppliers s ON s.id = p.supplier_id
                 WHERE p.status = 'active' AND c.name = ?
                 ORDER BY p.view_count DESC, p.rating DESC LIMIT ?"
            );
            $stmt->execute([$category, $limit]);
        } else {
            $stmt = $db->prepare(
                "SELECT p.id, p.name, p.price, p.image, p.rating, p.review_count,
                        c.name AS category_name, s.company_name AS supplier_name
                 FROM products p
                 LEFT JOIN categories c ON c.id = p.category_id
                 LEFT JOIN suppliers s ON s.id = p.supplier_id
                 WHERE p.status = 'active'
                 ORDER BY p.view_count DESC, p.rating DESC LIMIT ?"
            );
            $stmt->execute([$limit]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Record a recommendation click-through.
 */
function recordRecommendationClick(int $recommendationId): bool {
    $db = getDB();
    try {
        $db->prepare("UPDATE ai_recommendations SET is_clicked = 1 WHERE id = ?")
           ->execute([$recommendationId]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Record a recommendation purchase conversion.
 */
function recordRecommendationPurchase(int $recommendationId): bool {
    $db = getDB();
    try {
        $db->prepare("UPDATE ai_recommendations SET is_purchased = 1 WHERE id = ?")
           ->execute([$recommendationId]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Refresh recommendations cache for a user.
 */
function refreshRecommendationsCache(int $userId): bool {
    $db = getDB();
    try {
        $db->prepare("DELETE FROM ai_recommendations WHERE user_id = ? AND recommendation_type = 'personalized'")
           ->execute([$userId]);
        $ai = getDeepSeek();
        $ai->getRecommendations($userId, 'personalized');
        return true;
    } catch (Throwable $e) {
        error_log('Refresh recommendations error: ' . $e->getMessage());
        return false;
    }
}
