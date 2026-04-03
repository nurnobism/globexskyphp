<?php
/**
 * GlobexSky Commission Engine
 *
 * Commission is calculated on every order and deducted from supplier earnings.
 *
 * Tiered Commission Rates (default, admin-configurable):
 * - Tier 1: $0 - $1,000/month sales → 12% commission
 * - Tier 2: $1,001 - $10,000/month → 10% commission
 * - Tier 3: $10,001 - $50,000/month → 8% commission
 * - Tier 4: $50,001+/month → 6% commission
 *
 * Category Overrides (admin sets per category):
 * - Electronics: 8%
 * - Fashion: 15%
 * - Industrial: 6%
 * - Documents: 3%
 *
 * Supplier Plan Discounts:
 * - Free plan: 0% discount (full commission)
 * - Pro plan ($299/mo): 15% discount on commission
 * - Enterprise plan ($999/mo): 30% discount on commission
 */

/**
 * Calculate commission for an order (called after order is placed/paid).
 * Returns commission amount (float) or false on failure.
 */
function calculateCommission(int $orderId): float|false
{
    $db = getDB();

    // Load order
    $stmt = $db->prepare('SELECT o.*, p.category_id, p.supplier_id FROM orders o
        LEFT JOIN order_items oi ON oi.order_id = o.id
        LEFT JOIN products p ON p.id = oi.product_id
        WHERE o.id = ?
        LIMIT 1');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) return false;

    $supplierId = (int)($order['supplier_id'] ?? 0);
    $categoryId = (int)($order['category_id'] ?? 0);
    $orderAmount = (float)($order['total'] ?? $order['total_amount'] ?? 0);

    if ($orderAmount <= 0) return false;

    $rate    = getEffectiveRate($supplierId, $categoryId);
    $amount  = round($orderAmount * $rate / 100, 2);

    // Get plan discount
    $planDiscount = 0.0;
    try {
        $pStmt = $db->prepare('SELECT sp.commission_discount FROM plan_subscriptions ps
            JOIN supplier_plans sp ON sp.id = ps.plan_id
            WHERE ps.supplier_id = ? AND ps.status = "active"
            ORDER BY ps.created_at DESC LIMIT 1');
        $pStmt->execute([$supplierId]);
        $planDiscount = (float)($pStmt->fetchColumn() ?: 0);
    } catch (PDOException $e) { /* plans table may not exist yet */ }

    $finalAmount = $amount * (1 - $planDiscount / 100);
    $finalAmount = round($finalAmount, 2);

    // Log commission
    $tier = calculateSupplierTier($supplierId);
    logCommission($orderId, $supplierId, $orderAmount, $rate, [
        'tier'             => $tier,
        'plan_discount'    => $planDiscount,
        'category_id'      => $categoryId,
        'final_commission' => $finalAmount,
    ]);

    return $finalAmount;
}

/**
 * Determine current tier name based on monthly sales.
 */
function calculateSupplierTier(int $supplierId): string
{
    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT COALESCE(SUM(o.total),0) FROM orders o
            JOIN order_items oi ON oi.order_id = o.id
            JOIN products p ON p.id = oi.product_id
            WHERE p.supplier_id = ?
              AND o.placed_at >= DATE_FORMAT(NOW(), "%Y-%m-01")
              AND o.status NOT IN ("cancelled","refunded")');
        $stmt->execute([$supplierId]);
        $monthlySales = (float)$stmt->fetchColumn();
    } catch (PDOException $e) {
        $monthlySales = 0;
    }

    $tiers = getCommissionTiers();
    foreach ($tiers as $tier) {
        $max = $tier['max_monthly_sales'];
        if ($monthlySales >= (float)$tier['min_monthly_sales'] && ($max === null || $monthlySales <= (float)$max)) {
            return $tier['tier_name'] ?? 'Starter';
        }
    }
    return 'Starter';
}

/**
 * Get the effective commission rate for a supplier + category combination.
 */
function getEffectiveRate(int $supplierId, int $categoryId): float
{
    $db = getDB();

    // 1. Check category-specific rate
    if ($categoryId > 0) {
        try {
            $stmt = $db->prepare('SELECT rate FROM category_commission_rates WHERE category_id = ? AND is_active = 1');
            $stmt->execute([$categoryId]);
            $catRate = $stmt->fetchColumn();
            if ($catRate !== false) {
                return (float)$catRate;
            }
        } catch (PDOException $e) { /* table may not exist */ }
    }

    // 2. Tier-based rate
    $db2 = getDB();
    try {
        $stmt = $db2->prepare('SELECT COALESCE(SUM(o.total),0) FROM orders o
            JOIN order_items oi ON oi.order_id = o.id
            JOIN products p ON p.id = oi.product_id
            WHERE p.supplier_id = ?
              AND o.placed_at >= DATE_FORMAT(NOW(), "%Y-%m-01")
              AND o.status NOT IN ("cancelled","refunded")');
        $stmt->execute([$supplierId]);
        $monthlySales = (float)$stmt->fetchColumn();
    } catch (PDOException $e) {
        $monthlySales = 0;
    }

    $tiers = getCommissionTiers();
    foreach ($tiers as $tier) {
        $max = $tier['max_monthly_sales'];
        if ($monthlySales >= (float)$tier['min_monthly_sales'] && ($max === null || $monthlySales <= (float)$max)) {
            return (float)$tier['rate'];
        }
    }

    return 12.0; // default fallback
}

/**
 * Apply supplier plan discount to a base commission rate.
 */
function applyPlanDiscount(float $baseRate, int $planId): float
{
    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT commission_discount FROM supplier_plans WHERE id = ?');
        $stmt->execute([$planId]);
        $discount = (float)($stmt->fetchColumn() ?: 0);
        return round($baseRate * (1 - $discount / 100), 4);
    } catch (PDOException $e) {
        return $baseRate;
    }
}

/**
 * Record commission log entry.
 */
function logCommission(int $orderId, int $supplierId, float $amount, float $rate, array $details = []): void
{
    $db = getDB();
    try {
        $commAmount   = round($amount * $rate / 100, 2);
        $planDiscount = (float)($details['plan_discount'] ?? 0);
        $tier         = $details['tier'] ?? '';
        $catApplied   = isset($details['category_id']) && $details['category_id'] > 0 ? 1 : 0;

        $stmt = $db->prepare('INSERT INTO commission_logs
            (order_id, supplier_id, order_amount, commission_rate, commission_amount, tier,
             category_rate_applied, plan_discount_applied, details, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([
            $orderId,
            $supplierId,
            $amount,
            $rate,
            $commAmount,
            $tier,
            $catApplied,
            $planDiscount,
            json_encode($details),
        ]);
    } catch (PDOException $e) {
        error_log('logCommission error: ' . $e->getMessage());
    }
}

/**
 * Get total commission earned this month for a supplier.
 */
function getMonthlyCommissionTotal(int $supplierId): float
{
    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT COALESCE(SUM(commission_amount),0) FROM commission_logs
            WHERE supplier_id = ? AND created_at >= DATE_FORMAT(NOW(), "%Y-%m-01")');
        $stmt->execute([$supplierId]);
        return (float)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0.0;
    }
}

/**
 * Get commission summary for a supplier over a given period.
 * $period: 'daily' | 'weekly' | 'monthly' | 'yearly'
 */
function getCommissionSummary(int $supplierId, string $period = 'monthly'): array
{
    $db = getDB();
    $since = match ($period) {
        'daily'   => date('Y-m-d 00:00:00'),
        'weekly'  => date('Y-m-d 00:00:00', strtotime('-7 days')),
        'yearly'  => date('Y-01-01 00:00:00'),
        default   => date('Y-m-01 00:00:00'), // monthly
    };
    try {
        $stmt = $db->prepare('SELECT
            COUNT(*) AS total_orders,
            COALESCE(SUM(order_amount),0) AS total_sales,
            COALESCE(SUM(commission_amount),0) AS total_commission,
            COALESCE(AVG(commission_rate),0) AS avg_rate
            FROM commission_logs
            WHERE supplier_id = ? AND created_at >= ?');
        $stmt->execute([$supplierId, $since]);
        return $stmt->fetch() ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Load commission tiers from DB (with hard-coded fallback).
 */
function getCommissionTiers(): array
{
    static $tiers = null;
    if ($tiers !== null) return $tiers;

    $db = getDB();
    try {
        $stmt = $db->query('SELECT * FROM commission_tiers WHERE is_active = 1 ORDER BY min_monthly_sales ASC');
        $rows = $stmt->fetchAll();
        if ($rows) {
            $tiers = $rows;
            return $tiers;
        }
    } catch (PDOException $e) { /* table may not exist */ }

    // Hard-coded defaults
    $tiers = [
        ['min_monthly_sales' => 0,        'max_monthly_sales' => 1000,  'rate' => 12, 'tier_name' => 'Starter'],
        ['min_monthly_sales' => 1000.01,  'max_monthly_sales' => 10000, 'rate' => 10, 'tier_name' => 'Growth'],
        ['min_monthly_sales' => 10000.01, 'max_monthly_sales' => 50000, 'rate' => 8,  'tier_name' => 'Scale'],
        ['min_monthly_sales' => 50000.01, 'max_monthly_sales' => null,  'rate' => 6,  'tier_name' => 'Enterprise'],
    ];
    return $tiers;
}
