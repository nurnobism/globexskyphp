<?php
/**
 * GlobexSky Commission Engine (PR #8)
 *
 * Tiered Commission Rates — based on 90-day rolling GMV (admin-configurable):
 *   Starter    $0 – $10K    → 12%
 *   Growth     $10K – $50K  → 10%
 *   Scale      $50K – $200K →  8%
 *   Enterprise $200K+       →  6%
 *
 * Category Overrides (admin-configurable per category):
 *   Electronics 8% · Fashion 15% · Food 10% · Industrial 7%
 *
 * Supplier Plan Discounts:
 *   Free        0% discount
 *   Pro        15% discount
 *   Enterprise 30% discount
 *
 * Final formula:
 *   commission = order_subtotal × base_rate × (1 − category_adjustment) × (1 − plan_discount)
 *
 * Where:
 *   - base_rate         = tier rate (fraction, e.g. 0.12)
 *   - category_adjustment = fraction applied when a category override exists:
 *       effective_rate = category_override_rate; category_adjustment = 1 − (category_override_rate / base_rate)
 *   - plan_discount     = fraction discount from supplier plan (e.g. 0.15 for Pro)
 */

// ─────────────────────────────────────────────────────────────────────────────
// Main calculation
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Calculate commission for an order (called after order payment is confirmed).
 *
 * @param int $orderId
 * @return array{base_rate:float,category_rate:float,plan_discount:float,final_rate:float,
 *               commission_amount:float,net_supplier_amount:float}|false
 */
function calculateCommission(int $orderId): array|false
{
    $db = getDB();

    // Load order + first item's supplier/category (commission is per-order, single supplier)
    $stmt = $db->prepare(
        'SELECT o.id, o.subtotal, o.total,
                oi.supplier_id, p.category_id
         FROM orders o
         LEFT JOIN order_items oi ON oi.order_id = o.id
         LEFT JOIN products    p  ON p.id = oi.product_id
         WHERE o.id = ?
         LIMIT 1'
    );
    $stmt->execute([$orderId]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }

    $supplierId   = (int)($row['supplier_id'] ?? 0);
    $categoryId   = (int)($row['category_id'] ?? 0);
    $orderSubtotal = (float)($row['subtotal'] ?? $row['total'] ?? 0);

    if ($orderSubtotal <= 0) {
        return false;
    }

    // 1. Determine tier → base_rate
    $tierInfo  = getSupplierGmvTier($supplierId);
    $baseRate  = $tierInfo['base_rate'];   // fraction, e.g. 0.12
    $tierName  = $tierInfo['tier_name'];

    // 2. Category override
    $categoryRate  = 0.0;
    $effectiveRate = $baseRate;
    if ($categoryId > 0) {
        $catOverride = getCategoryCommissionRate($categoryId);
        if ($catOverride !== null) {
            $categoryRate  = $catOverride;   // fraction
            $effectiveRate = $catOverride;
        }
    }

    // category_adjustment = 1 − (effective_rate / base_rate)
    $categoryAdj = ($baseRate > 0 && $categoryRate > 0)
        ? (1 - $effectiveRate / $baseRate)
        : 0.0;

    // 3. Plan discount
    $planDiscount = 0.0;
    if ($supplierId > 0) {
        $planDiscount = getSupplierPlanDiscount($supplierId);
    }

    // 4. Final rate & commission
    $finalRate       = $baseRate * (1 - $categoryAdj) * (1 - $planDiscount);
    $commissionAmt   = round($orderSubtotal * $finalRate, 2);
    $netSupplierAmt  = round($orderSubtotal - $commissionAmt, 2);

    $data = [
        'base_rate'           => $baseRate,
        'category_rate'       => $categoryRate,
        'plan_discount'       => $planDiscount,
        'final_rate'          => round($finalRate, 6),
        'commission_amount'   => $commissionAmt,
        'net_supplier_amount' => $netSupplierAmt,
        'gmv_tier'            => $tierName,
        'order_subtotal'      => $orderSubtotal,
    ];

    // 5. Log
    logCommission($orderId, $supplierId, $data);

    return $data;
}

// ─────────────────────────────────────────────────────────────────────────────
// GMV Tier
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Calculate 90-day rolling GMV for a supplier and return tier name + base rate.
 *
 * @return array{tier_name:string,base_rate:float,gmv_90d:float}
 */
function getSupplierGmvTier(int $supplierId): array
{
    $db = getDB();
    $gmv90d = 0.0;

    try {
        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(oi.subtotal), 0)
             FROM order_items oi
             JOIN orders   o ON o.id = oi.order_id
             JOIN products p ON p.id = oi.product_id
             WHERE p.supplier_id = ?
               AND o.placed_at  >= DATE_SUB(NOW(), INTERVAL 90 DAY)
               AND o.status NOT IN ("cancelled","refunded")'
        );
        $stmt->execute([$supplierId]);
        $gmv90d = (float)$stmt->fetchColumn();
    } catch (PDOException $e) {
        // tables may not be available in test env
    }

    $tiers = getCommissionTierConfig();
    foreach ($tiers as $tier) {
        $max = $tier['max_gmv'];
        if ($gmv90d >= (float)$tier['min_gmv']
            && ($max === null || $gmv90d <= (float)$max)
        ) {
            return [
                'tier_name' => (string)($tier['tier_name'] ?? 'Starter'),
                'base_rate' => (float)($tier['base_rate'] ?? 0.12),
                'gmv_90d'   => $gmv90d,
            ];
        }
    }

    return ['tier_name' => 'Starter', 'base_rate' => 0.12, 'gmv_90d' => $gmv90d];
}

// ─────────────────────────────────────────────────────────────────────────────
// Category rate
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Get category-specific commission override rate (fraction) or null if none.
 */
function getCategoryCommissionRate(int $categoryId): ?float
{
    if ($categoryId <= 0) {
        return null;
    }
    $db = getDB();
    try {
        // Support both "rate" (schema_v3) and "override_rate" (schema_v15) columns
        $stmt = $db->prepare(
            'SELECT COALESCE(override_rate, rate, NULL) AS r
             FROM category_commission_rates
             WHERE category_id = ? AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute([$categoryId]);
        $val = $stmt->fetchColumn();
        return ($val !== false && $val !== null) ? (float)$val : null;
    } catch (PDOException $e) {
        return null;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Plan discount
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Get the active plan commission discount fraction for a supplier.
 * Free = 0.0, Pro = 0.15, Enterprise = 0.30
 */
function getSupplierPlanDiscount(int $supplierId): float
{
    $db = getDB();
    try {
        $stmt = $db->prepare(
            'SELECT sp.commission_discount
             FROM plan_subscriptions ps
             JOIN supplier_plans     sp ON sp.id = ps.plan_id
             WHERE ps.supplier_id = ? AND ps.status = "active"
             ORDER BY ps.created_at DESC
             LIMIT 1'
        );
        $stmt->execute([$supplierId]);
        $val = $stmt->fetchColumn();
        if ($val !== false) {
            $d = (float)$val;
            // Normalise: stored as percent (15) → fraction (0.15)
            return $d > 1 ? $d / 100 : $d;
        }
    } catch (PDOException $e) {
        // plan tables may not exist yet
    }
    return 0.0;
}

// ─────────────────────────────────────────────────────────────────────────────
// Logging
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Insert a commission log entry (idempotent: skip if order already logged).
 */
function logCommission(int $orderId, int $supplierId, array $data): void
{
    $db = getDB();
    try {
        // Prefer new schema_v15 columns, fall back to v3 columns
        $db->prepare(
            'INSERT IGNORE INTO commission_logs
                (order_id, supplier_id,
                 order_subtotal, gmv_tier, base_rate, category_rate,
                 plan_discount, final_rate, commission_amount, net_amount,
                 order_amount, commission_rate, tier,
                 category_rate_applied, plan_discount_applied, details,
                 created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        )->execute([
            $orderId,
            $supplierId,
            $data['order_subtotal']      ?? 0,
            $data['gmv_tier']            ?? '',
            $data['base_rate']           ?? 0,
            $data['category_rate']       ?? 0,
            $data['plan_discount']       ?? 0,
            $data['final_rate']          ?? 0,
            $data['commission_amount']   ?? 0,
            $data['net_supplier_amount'] ?? 0,
            // v3 compat columns
            $data['order_subtotal']      ?? 0,
            round(($data['final_rate'] ?? 0) * 100, 4),
            $data['gmv_tier']            ?? '',
            ($data['category_rate'] ?? 0) > 0 ? 1 : 0,
            round(($data['plan_discount'] ?? 0) * 100, 4),
            json_encode($data),
        ]);
    } catch (PDOException $e) {
        // Try minimal v3 insert as fallback
        try {
            $db->prepare(
                'INSERT IGNORE INTO commission_logs
                    (order_id, supplier_id, order_amount, commission_rate,
                     commission_amount, tier, category_rate_applied,
                     plan_discount_applied, details, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            )->execute([
                $orderId,
                $supplierId,
                $data['order_subtotal']    ?? 0,
                round(($data['final_rate'] ?? 0) * 100, 4),
                $data['commission_amount'] ?? 0,
                $data['gmv_tier']          ?? '',
                ($data['category_rate'] ?? 0) > 0 ? 1 : 0,
                round(($data['plan_discount'] ?? 0) * 100, 4),
                json_encode($data),
            ]);
        } catch (PDOException $e2) {
            error_log('logCommission error: ' . $e2->getMessage());
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Commission history / stats
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Get paginated commission logs for a supplier (or all if supplierId = 0).
 */
function getCommissionLogs(int $supplierId, array $filters = [], int $page = 1, int $perPage = 20): array
{
    $db     = getDB();
    $where  = [];
    $params = [];

    if ($supplierId > 0) {
        $where[]  = 'cl.supplier_id = ?';
        $params[] = $supplierId;
    }
    if (!empty($filters['from'])) {
        $where[]  = 'cl.created_at >= ?';
        $params[] = $filters['from'] . ' 00:00:00';
    }
    if (!empty($filters['to'])) {
        $where[]  = 'cl.created_at <= ?';
        $params[] = $filters['to'] . ' 23:59:59';
    }
    if (!empty($filters['tier'])) {
        $where[]  = 'cl.gmv_tier = ?';
        $params[] = $filters['tier'];
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $offset      = ($page - 1) * $perPage;

    try {
        $cStmt = $db->prepare("SELECT COUNT(*) FROM commission_logs cl $whereClause");
        $cStmt->execute($params);
        $total = (int)$cStmt->fetchColumn();

        $stmt = $db->prepare(
            "SELECT cl.*, u.email, u.company_name
             FROM commission_logs cl
             LEFT JOIN users u ON u.id = cl.supplier_id
             $whereClause
             ORDER BY cl.created_at DESC
             LIMIT $perPage OFFSET $offset"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    } catch (PDOException $e) {
        $total = 0;
        $rows  = [];
    }

    return [
        'data'      => $rows,
        'total'     => $total,
        'page'      => $page,
        'per_page'  => $perPage,
        'pages'     => $total > 0 ? (int)ceil($total / $perPage) : 1,
    ];
}

/**
 * Get commission statistics for a supplier.
 */
function getCommissionStats(int $supplierId): array
{
    $db    = getDB();
    $stats = [
        'total_commission_paid' => 0.0,
        'current_tier'          => 'Starter',
        'base_rate'             => 0.12,
        'plan_discount'         => 0.0,
        'effective_rate'        => 0.12,
        'this_month'            => 0.0,
        'last_month'            => 0.0,
        'total_orders'          => 0,
        'gmv_90d'               => 0.0,
    ];

    try {
        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(commission_amount), 0) FROM commission_logs WHERE supplier_id = ?'
        );
        $stmt->execute([$supplierId]);
        $stats['total_commission_paid'] = (float)$stmt->fetchColumn();

        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(commission_amount), 0) FROM commission_logs
             WHERE supplier_id = ? AND created_at >= DATE_FORMAT(NOW(), "%Y-%m-01")'
        );
        $stmt->execute([$supplierId]);
        $stats['this_month'] = (float)$stmt->fetchColumn();

        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(commission_amount), 0) FROM commission_logs
             WHERE supplier_id = ?
               AND created_at >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), "%Y-%m-01")
               AND created_at <  DATE_FORMAT(NOW(), "%Y-%m-01")'
        );
        $stmt->execute([$supplierId]);
        $stats['last_month'] = (float)$stmt->fetchColumn();

        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM commission_logs WHERE supplier_id = ?'
        );
        $stmt->execute([$supplierId]);
        $stats['total_orders'] = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        // ignore — table may not exist
    }

    $tierInfo = getSupplierGmvTier($supplierId);
    $stats['current_tier'] = $tierInfo['tier_name'];
    $stats['base_rate']    = $tierInfo['base_rate'];
    $stats['gmv_90d']      = $tierInfo['gmv_90d'];
    $planDiscount          = getSupplierPlanDiscount($supplierId);
    $stats['plan_discount']  = $planDiscount;
    $stats['effective_rate'] = round($tierInfo['base_rate'] * (1 - $planDiscount), 6);

    return $stats;
}

/**
 * Get platform-wide admin commission statistics.
 */
function getAdminCommissionStats(): array
{
    $db    = getDB();
    $stats = [
        'total_commission_earned' => 0.0,
        'this_month'              => 0.0,
        'last_month'              => 0.0,
        'total_orders'            => 0,
        'by_tier'                 => [],
        'by_category'             => [],
    ];

    try {
        $r = $db->query('SELECT COALESCE(SUM(commission_amount), 0) FROM commission_logs');
        $stats['total_commission_earned'] = (float)$r->fetchColumn();

        $r = $db->query(
            'SELECT COALESCE(SUM(commission_amount), 0) FROM commission_logs
             WHERE created_at >= DATE_FORMAT(NOW(), "%Y-%m-01")'
        );
        $stats['this_month'] = (float)$r->fetchColumn();

        $r = $db->query(
            'SELECT COALESCE(SUM(commission_amount), 0) FROM commission_logs
             WHERE created_at >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), "%Y-%m-01")
               AND created_at <  DATE_FORMAT(NOW(), "%Y-%m-01")'
        );
        $stats['last_month'] = (float)$r->fetchColumn();

        $r = $db->query('SELECT COUNT(*) FROM commission_logs');
        $stats['total_orders'] = (int)$r->fetchColumn();

        // By GMV tier
        $r = $db->query(
            'SELECT gmv_tier AS tier,
                    COUNT(*) AS orders,
                    COALESCE(SUM(commission_amount), 0) AS commission
             FROM commission_logs
             GROUP BY gmv_tier
             ORDER BY commission DESC'
        );
        $stats['by_tier'] = $r->fetchAll();

        // By category (join category_commission_rates)
        $r = $db->query(
            'SELECT COALESCE(c.name, CONCAT("Category #", cl_cat.category_id)) AS category,
                    COUNT(*) AS orders,
                    COALESCE(SUM(commission_amount), 0) AS commission
             FROM commission_logs cl
             JOIN (
                 SELECT oi.order_id, p.category_id
                 FROM order_items oi
                 JOIN products p ON p.id = oi.product_id
                 GROUP BY oi.order_id
             ) cl_cat ON cl_cat.order_id = cl.order_id
             LEFT JOIN categories c ON c.id = cl_cat.category_id
             GROUP BY cl_cat.category_id
             ORDER BY commission DESC
             LIMIT 10'
        );
        $stats['by_category'] = $r->fetchAll();
    } catch (PDOException $e) {
        // ignore
    }

    return $stats;
}

/**
 * Recalculate commission for an order (admin override scenario).
 * Deletes old log entry and re-runs calculation.
 */
function recalculateCommission(int $orderId): array|false
{
    $db = getDB();
    try {
        $db->prepare('DELETE FROM commission_logs WHERE order_id = ?')->execute([$orderId]);
    } catch (PDOException $e) {
        // ignore
    }
    return calculateCommission($orderId);
}

// ─────────────────────────────────────────────────────────────────────────────
// Tier config helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Load commission tier config from DB (falls back to hard-coded defaults).
 * Uses commission_tier_config (v15) first, then commission_tiers (v3).
 */
function getCommissionTierConfig(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }

    $db = getDB();

    // Try new table first (schema_v15)
    try {
        $stmt = $db->query(
            'SELECT tier_name, min_gmv, max_gmv, base_rate
             FROM commission_tier_config
             WHERE is_active = 1
             ORDER BY sort_order ASC, min_gmv ASC'
        );
        $rows = $stmt->fetchAll();
        if ($rows) {
            $cfg = $rows;
            return $cfg;
        }
    } catch (PDOException $e) {
        // table does not exist yet
    }

    // Fall back to old commission_tiers table (schema_v3)
    try {
        $stmt = $db->query(
            'SELECT tier_name,
                    min_monthly_sales AS min_gmv,
                    max_monthly_sales AS max_gmv,
                    rate / 100        AS base_rate
             FROM commission_tiers
             WHERE is_active = 1
             ORDER BY min_monthly_sales ASC'
        );
        $rows = $stmt->fetchAll();
        if ($rows) {
            $cfg = $rows;
            return $cfg;
        }
    } catch (PDOException $e) {
        // table does not exist yet
    }

    // Hard-coded defaults (90-day GMV thresholds)
    $cfg = [
        ['tier_name' => 'Starter',    'min_gmv' =>      0, 'max_gmv' =>   9999.99, 'base_rate' => 0.12],
        ['tier_name' => 'Growth',     'min_gmv' =>  10000, 'max_gmv' =>  49999.99, 'base_rate' => 0.10],
        ['tier_name' => 'Scale',      'min_gmv' =>  50000, 'max_gmv' => 199999.99, 'base_rate' => 0.08],
        ['tier_name' => 'Enterprise', 'min_gmv' => 200000, 'max_gmv' =>      null, 'base_rate' => 0.06],
    ];
    return $cfg;
}

// ─────────────────────────────────────────────────────────────────────────────
// Legacy / compatibility helpers (used by existing code in the codebase)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Legacy: alias for getCommissionTierConfig().
 */
function getCommissionTiers(): array
{
    $tiers = getCommissionTierConfig();
    // Normalise to legacy shape (rate as percent, min/max as monthly sales)
    return array_map(static function (array $t): array {
        return [
            'tier_name'          => $t['tier_name'],
            'min_monthly_sales'  => $t['min_gmv'],
            'max_monthly_sales'  => $t['max_gmv'],
            'rate'               => isset($t['base_rate']) ? (float)$t['base_rate'] * 100 : 12,
            'base_rate'          => $t['base_rate'] ?? 0.12,
        ];
    }, $tiers);
}

/**
 * Legacy: Determine current tier name based on monthly sales (for backward compat).
 */
function calculateSupplierTier(int $supplierId): string
{
    return getSupplierGmvTier($supplierId)['tier_name'];
}

/**
 * Legacy: Get total commission earned this month for a supplier.
 */
function getMonthlyCommissionTotal(int $supplierId): float
{
    $db = getDB();
    try {
        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(commission_amount), 0) FROM commission_logs
             WHERE supplier_id = ? AND created_at >= DATE_FORMAT(NOW(), "%Y-%m-01")'
        );
        $stmt->execute([$supplierId]);
        return (float)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0.0;
    }
}

/**
 * Legacy: Commission summary (daily/weekly/monthly/yearly).
 */
function getCommissionSummary(int $supplierId, string $period = 'monthly'): array
{
    $db    = getDB();
    $since = match ($period) {
        'daily'  => date('Y-m-d 00:00:00'),
        'weekly' => date('Y-m-d 00:00:00', strtotime('-7 days')),
        'yearly' => date('Y-01-01 00:00:00'),
        default  => date('Y-m-01 00:00:00'),
    };
    try {
        $stmt = $db->prepare(
            'SELECT COUNT(*)                              AS total_orders,
                    COALESCE(SUM(order_subtotal), 0)      AS total_sales,
                    COALESCE(SUM(commission_amount), 0)   AS total_commission,
                    COALESCE(AVG(final_rate) * 100, 0)    AS avg_rate
             FROM commission_logs
             WHERE supplier_id = ? AND created_at >= ?'
        );
        $stmt->execute([$supplierId, $since]);
        return $stmt->fetch() ?: [];
    } catch (PDOException $e) {
        // Try v3 fallback columns
        try {
            $stmt = $db->prepare(
                'SELECT COUNT(*)                            AS total_orders,
                        COALESCE(SUM(order_amount), 0)      AS total_sales,
                        COALESCE(SUM(commission_amount), 0) AS total_commission,
                        COALESCE(AVG(commission_rate), 0)   AS avg_rate
                 FROM commission_logs
                 WHERE supplier_id = ? AND created_at >= ?'
            );
            $stmt->execute([$supplierId, $since]);
            return $stmt->fetch() ?: [];
        } catch (PDOException $e2) {
            return [];
        }
    }
}

/**
 * Legacy: Apply plan discount to a base commission rate.
 */
function applyPlanDiscount(float $baseRate, int $planId): float
{
    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT commission_discount FROM supplier_plans WHERE id = ?');
        $stmt->execute([$planId]);
        $discount = (float)($stmt->fetchColumn() ?: 0);
        $d        = $discount > 1 ? $discount / 100 : $discount;
        return round($baseRate * (1 - $d), 6);
    } catch (PDOException $e) {
        return $baseRate;
    }
}

/**
 * Legacy: Get effective rate (percent) for a supplier + category combination.
 * Used by existing pages that have not been migrated to calculateCommission().
 */
function getEffectiveRate(int $supplierId, int $categoryId): float
{
    $tierInfo    = getSupplierGmvTier($supplierId);
    $baseRate    = $tierInfo['base_rate'];       // fraction
    $effectiveRate = $baseRate;

    $catOverride = getCategoryCommissionRate($categoryId);
    if ($catOverride !== null) {
        $effectiveRate = $catOverride;
    }

    $planDiscount  = getSupplierPlanDiscount($supplierId);
    $finalRate     = $effectiveRate * (1 - $planDiscount);
    return round($finalRate * 100, 4);  // return as percent for legacy callers
}
