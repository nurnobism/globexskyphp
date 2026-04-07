<?php
/**
 * Plan Limits Enforcement
 *
 * Called before supplier actions to check plan limits.
 */

/**
 * Get the current supplier plan (with limits) for a supplier.
 */
function getSupplierPlan(int $supplierId): array
{
    static $cache = [];
    if (isset($cache[$supplierId])) return $cache[$supplierId];

    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT sp.* FROM plan_subscriptions ps
            JOIN supplier_plans sp ON sp.id = ps.plan_id
            WHERE ps.supplier_id = ? AND ps.status = "active"
            ORDER BY ps.created_at DESC LIMIT 1');
        $stmt->execute([$supplierId]);
        $plan = $stmt->fetch();
        if ($plan) {
            $plan['limits_decoded']   = json_decode($plan['limits'] ?? '{}', true) ?: [];
            $plan['features_decoded'] = json_decode($plan['features'] ?? '{}', true) ?: [];
            $cache[$supplierId] = $plan;
            return $plan;
        }
    } catch (PDOException $e) { /* tables may not exist */ }

    // Default free plan
    $free = [
        'id'                  => 0,
        'name'                => 'Free',
        'slug'                => 'free',
        'price'               => 0,
        'commission_discount' => 0,
        'limits_decoded'      => [
            'products'              => 10,
            'images_per_product'    => 3,
            'featured_per_month'    => 0,
            'livestream_per_week'   => 0,
            'dropshipping'          => false,
            'api_access'            => false,
        ],
        'features_decoded'    => ['support' => 'community', 'analytics' => 'basic', 'badge' => 'none'],
    ];
    $cache[$supplierId] = $free;
    return $free;
}

/**
 * Check if supplier can add another product.
 */
function canAddProduct(int $supplierId): bool
{
    $plan   = getSupplierPlan($supplierId);
    $limit  = (int)($plan['limits_decoded']['products'] ?? 10);
    if ($limit < 0) return true; // unlimited

    $db   = getDB();
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM products WHERE supplier_id = ? AND status != "archived"');
        $stmt->execute([$supplierId]);
        return (int)$stmt->fetchColumn() < $limit;
    } catch (PDOException $e) {
        return true;
    }
}

/**
 * Check if supplier can upload $count more images for a product.
 */
function canUploadImages(int $supplierId, int $count = 1): bool
{
    $plan  = getSupplierPlan($supplierId);
    $limit = (int)($plan['limits_decoded']['images_per_product'] ?? 3);
    return $count <= $limit;
}

/**
 * Check if supplier can start a new livestream session this week.
 */
function canUseLivestream(int $supplierId): bool
{
    $plan  = getSupplierPlan($supplierId);
    $limit = (int)($plan['limits_decoded']['livestream_per_week'] ?? 0);
    if ($limit < 0) return true;
    if ($limit === 0) return false;

    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM livestreams
            WHERE supplier_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
        $stmt->execute([$supplierId]);
        return (int)$stmt->fetchColumn() < $limit;
    } catch (PDOException $e) {
        return true;
    }
}

/**
 * Check if supplier's plan allows dropshipping.
 */
function canUseDropshipping(int $supplierId): bool
{
    $plan = getSupplierPlan($supplierId);
    return (bool)($plan['limits_decoded']['dropshipping'] ?? false);
}

/**
 * Check if supplier's plan allows API access.
 */
function canUseAPI(int $supplierId): bool
{
    $plan      = getSupplierPlan($supplierId);
    $apiAccess = $plan['limits_decoded']['api_access'] ?? false;
    return $apiAccess !== false && $apiAccess !== null && $apiAccess !== '';
}

/**
 * Check if supplier can feature another listing this month.
 */
function canGetFeatured(int $supplierId): bool
{
    $plan  = getSupplierPlan($supplierId);
    $limit = (int)($plan['limits_decoded']['featured_per_month'] ?? 0);
    if ($limit < 0) return true;
    if ($limit === 0) return false;

    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM featured_products
            WHERE supplier_id = ? AND featured_at >= DATE_FORMAT(NOW(), "%Y-%m-01")');
        $stmt->execute([$supplierId]);
        return (int)$stmt->fetchColumn() < $limit;
    } catch (PDOException $e) {
        return true;
    }
}

/**
 * Return badge HTML for a supplier based on their plan.
 */
function getPlanBadge(int $supplierId): string
{
    $plan  = getSupplierPlan($supplierId);
    $badge = $plan['features_decoded']['badge'] ?? 'none';
    return match ($badge) {
        'pro'        => '<span class="badge bg-primary ms-1"><i class="bi bi-star-fill me-1"></i>Pro Seller</span>',
        'enterprise' => '<span class="badge bg-warning text-dark ms-1"><i class="bi bi-gem me-1"></i>Enterprise</span>',
        default      => '',
    };
}

/**
 * Return all plan limits with current usage for a supplier.
 */
function getRemainingLimits(int $supplierId): array
{
    $plan   = getSupplierPlan($supplierId);
    $limits = $plan['limits_decoded'];
    $db     = getDB();

    $productCount = 0;
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM products WHERE supplier_id = ? AND status != "archived"');
        $stmt->execute([$supplierId]);
        $productCount = (int)$stmt->fetchColumn();
    } catch (PDOException $e) { /* ignore */ }

    $featuredCount = 0;
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM featured_products
            WHERE supplier_id = ? AND featured_at >= DATE_FORMAT(NOW(), "%Y-%m-01")');
        $stmt->execute([$supplierId]);
        $featuredCount = (int)$stmt->fetchColumn();
    } catch (PDOException $e) { /* ignore */ }

    $livestreamCount = 0;
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM livestreams
            WHERE supplier_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
        $stmt->execute([$supplierId]);
        $livestreamCount = (int)$stmt->fetchColumn();
    } catch (PDOException $e) { /* ignore */ }

    $prodLimit = (int)($limits['products'] ?? 10);
    $featLimit = (int)($limits['featured_per_month'] ?? 0);
    $liveLimit = (int)($limits['livestream_per_week'] ?? 0);

    return [
        'plan'               => $plan['name'] ?? 'Free',
        'plan_slug'          => $plan['slug'] ?? 'free',
        'products'           => [
            'used'      => $productCount,
            'limit'     => $prodLimit < 0 ? 'Unlimited' : $prodLimit,
            'remaining' => $prodLimit < 0 ? 'Unlimited' : max(0, $prodLimit - $productCount),
        ],
        'images_per_product' => (int)($limits['images_per_product'] ?? 3),
        'featured'           => [
            'used'      => $featuredCount,
            'limit'     => $featLimit < 0 ? 'Unlimited' : $featLimit,
            'remaining' => $featLimit < 0 ? 'Unlimited' : max(0, $featLimit - $featuredCount),
        ],
        'livestream'         => [
            'used'      => $livestreamCount,
            'limit'     => $liveLimit < 0 ? 'Unlimited' : $liveLimit,
            'remaining' => $liveLimit < 0 ? 'Unlimited' : max(0, $liveLimit - $livestreamCount),
        ],
        'dropshipping'       => (bool)($limits['dropshipping'] ?? false),
        'api_access'         => $limits['api_access'] ?? false,
        'commission_discount'=> (float)($plan['commission_discount'] ?? 0),
    ];
}
