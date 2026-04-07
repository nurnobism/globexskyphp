<?php
/**
 * includes/plans.php — Supplier Plan Management Library (PR #9)
 *
 * Plan definitions:
 *   Free       — $0/mo   — 10 products, 3 images/product
 *   Pro        — $299/mo — 500 products, 10 images/product, dropshipping (100), livestream, priority support
 *   Enterprise — $999/mo — Unlimited products, 20 images/product, unlimited dropshipping, API, account manager
 *
 * Billing period discounts:
 *   monthly    — 0%
 *   quarterly  — 10%
 *   semi_annual — 15%
 *   annual     — 25%
 */

// ── Default plan definitions ────────────────────────────────────────────────

function getPlans(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    $db = getDB();
    try {
        $stmt = $db->query('SELECT * FROM supplier_plans WHERE is_active = 1 ORDER BY sort_order ASC');
        $rows = $stmt->fetchAll();
        if ($rows) {
            foreach ($rows as &$r) {
                $r['limits_decoded']   = json_decode($r['limits']   ?? '{}', true) ?: [];
                $r['features_decoded'] = json_decode($r['features'] ?? '{}', true) ?: [];
            }
            unset($r);
            $cache = $rows;
            return $cache;
        }
    } catch (PDOException $e) { /* fall through to defaults */ }

    $cache = _defaultPlans();
    return $cache;
}

function getPlan(string $planSlug): ?array
{
    foreach (getPlans() as $plan) {
        if (($plan['slug'] ?? '') === $planSlug) {
            return $plan;
        }
    }
    return null;
}

// ── Subscription management ─────────────────────────────────────────────────

/**
 * Get supplier's current active plan subscription.
 * Returns plan row merged with subscription data, or Free defaults.
 */
function getSupplierActivePlan(int $supplierId): array
{
    static $planCache = [];
    if (isset($planCache[$supplierId])) return $planCache[$supplierId];

    $db = getDB();
    try {
        $stmt = $db->prepare(
            'SELECT ps.*, sp.name, sp.slug, sp.price, sp.commission_discount,
                    sp.features, sp.limits, sp.stripe_price_id
             FROM plan_subscriptions ps
             JOIN supplier_plans sp ON sp.id = ps.plan_id
             WHERE ps.supplier_id = ? AND ps.status IN ("active","trialing","past_due")
             ORDER BY ps.created_at DESC LIMIT 1'
        );
        $stmt->execute([$supplierId]);
        $row = $stmt->fetch();
        if ($row) {
            $row['limits_decoded']   = json_decode($row['limits']   ?? '{}', true) ?: [];
            $row['features_decoded'] = json_decode($row['features'] ?? '{}', true) ?: [];
            $planCache[$supplierId]  = $row;
            return $row;
        }
    } catch (PDOException $e) { /* tables may not exist yet */ }

    $free = _freePlanDefaults();
    $planCache[$supplierId] = $free;
    return $free;
}

/**
 * Subscribe supplier to a plan.
 *
 * @param int    $supplierId
 * @param string $planSlug      free|pro|enterprise
 * @param string $billingPeriod monthly|quarterly|semi_annual|annual
 * @return array ['success'=>bool, 'message'=>string, 'subscription_id'=>int|null]
 */
function subscribeToPlan(int $supplierId, string $planSlug, string $billingPeriod = 'monthly'): array
{
    $plan = getPlan($planSlug);
    if (!$plan) {
        return ['success' => false, 'message' => 'Plan not found.'];
    }

    $db = getDB();

    // Cancel existing active subscriptions
    try {
        $db->prepare(
            'UPDATE plan_subscriptions SET status = "cancelled", updated_at = NOW()
             WHERE supplier_id = ? AND status IN ("active","trialing")'
        )->execute([$supplierId]);
    } catch (PDOException $e) { /* ignore */ }

    $basePrice     = (float)($plan['price'] ?? 0);
    $discount      = getDurationDiscount($billingPeriod);
    $amount        = round($basePrice * (1 - $discount / 100), 2);
    $periodMonths  = _billingPeriodMonths($billingPeriod);
    $periodEnd     = date('Y-m-d H:i:s', strtotime("+{$periodMonths} months"));
    $nextBilling   = date('Y-m-d', strtotime("+{$periodMonths} months"));

    try {
        $stmt = $db->prepare(
            'INSERT INTO plan_subscriptions
                (supplier_id, plan_id, billing_period, amount, status,
                 current_period_start, current_period_end, next_billing_date, created_at, updated_at)
             VALUES (?, ?, ?, ?, "active", NOW(), ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $supplierId,
            (int)$plan['id'],
            $billingPeriod,
            $amount,
            $periodEnd,
            $nextBilling,
        ]);
        $subId = (int)$db->lastInsertId();

        // Record invoice for paid plans
        if ($amount > 0) {
            _recordPlanInvoice($db, $subId, $supplierId, $amount, $billingPeriod, 'paid');
        }

        return ['success' => true, 'message' => 'Subscribed successfully.', 'subscription_id' => $subId];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Upgrade supplier to a higher plan immediately (with proration credit).
 */
function upgradePlan(int $supplierId, string $newPlanSlug): array
{
    $newPlan = getPlan($newPlanSlug);
    if (!$newPlan) return ['success' => false, 'message' => 'Plan not found.'];

    $current = getSupplierActivePlan($supplierId);
    $proration = calculateProration($supplierId, $newPlanSlug);

    // Cancel current subscription
    $db = getDB();
    try {
        $db->prepare(
            'UPDATE plan_subscriptions SET status = "cancelled", updated_at = NOW()
             WHERE supplier_id = ? AND status IN ("active","trialing")'
        )->execute([$supplierId]);
    } catch (PDOException $e) { /* ignore */ }

    $billingPeriod = $current['billing_period'] ?? 'monthly';
    $periodMonths  = _billingPeriodMonths($billingPeriod);
    $periodEnd     = date('Y-m-d H:i:s', strtotime("+{$periodMonths} months"));
    $nextBilling   = date('Y-m-d', strtotime("+{$periodMonths} months"));
    $amount        = max(0, $proration['amount_due']);

    try {
        $stmt = $db->prepare(
            'INSERT INTO plan_subscriptions
                (supplier_id, plan_id, billing_period, amount, status,
                 current_period_start, current_period_end, next_billing_date, created_at, updated_at)
             VALUES (?, ?, ?, ?, "active", NOW(), ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $supplierId,
            (int)$newPlan['id'],
            $billingPeriod,
            $amount,
            $periodEnd,
            $nextBilling,
        ]);
        $subId = (int)$db->lastInsertId();

        if ($amount > 0) {
            _recordPlanInvoice($db, $subId, $supplierId, $amount, $billingPeriod, 'paid',
                'Upgrade to ' . $newPlan['name']);
        }

        // Invalidate cache
        unset($GLOBALS['_planCache'][$supplierId]);

        return [
            'success'         => true,
            'message'         => 'Upgraded to ' . $newPlan['name'] . ' plan successfully.',
            'subscription_id' => $subId,
            'amount_charged'  => $amount,
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Schedule downgrade to a lower plan at end of current billing period.
 */
function downgradePlan(int $supplierId, string $newPlanSlug): array
{
    $newPlan = getPlan($newPlanSlug);
    if (!$newPlan) return ['success' => false, 'message' => 'Plan not found.'];

    $db = getDB();
    try {
        $db->prepare(
            'UPDATE plan_subscriptions
             SET cancel_at_period_end = 1, updated_at = NOW()
             WHERE supplier_id = ? AND status IN ("active","trialing")
             ORDER BY created_at DESC LIMIT 1'
        )->execute([$supplierId]);
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }

    return [
        'success' => true,
        'message' => 'Your plan will be downgraded to ' . $newPlan['name'] . ' at the end of the current billing cycle.',
    ];
}

/**
 * Cancel subscription — reverts to Free at period end.
 */
function cancelPlan(int $supplierId): array
{
    $db = getDB();
    try {
        $db->prepare(
            'UPDATE plan_subscriptions
             SET cancel_at_period_end = 1, updated_at = NOW()
             WHERE supplier_id = ? AND status IN ("active","trialing")'
        )->execute([$supplierId]);
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
    return ['success' => true, 'message' => 'Subscription cancelled. Access continues until end of billing period.'];
}

// ── Limit checking ───────────────────────────────────────────────────────────

/**
 * Check if supplier is within a specific plan limit.
 *
 * Keys: max_products, max_images_per_product, max_dropship_products,
 *       can_livestream, can_api, can_featured
 *
 * Returns:
 *   ['allowed'=>true,  'current'=>8, 'limit'=>10]
 *   ['allowed'=>false, 'current'=>10, 'limit'=>10, 'upgrade_message'=>'...']
 */
function checkPlanLimit(int $supplierId, string $limitKey): array
{
    $plan   = getSupplierActivePlan($supplierId);
    $limits = $plan['limits_decoded'];
    $db     = getDB();

    switch ($limitKey) {
        case 'max_products':
            $limit   = (int)($limits['products'] ?? 10);
            $current = _countProducts($db, $supplierId);
            if ($limit < 0) return ['allowed' => true, 'current' => $current, 'limit' => 'unlimited'];
            $allowed = $current < $limit;
            return [
                'allowed'         => $allowed,
                'current'         => $current,
                'limit'           => $limit,
                'upgrade_message' => $allowed ? null : _upgradeMessage('products', $limit, $plan['name'] ?? 'Free'),
            ];

        case 'max_images_per_product':
            $limit = (int)($limits['images_per_product'] ?? 3);
            return ['allowed' => true, 'current' => 0, 'limit' => $limit];

        case 'max_dropship_products':
            $dropship = $limits['dropshipping'] ?? false;
            if (!$dropship) {
                return [
                    'allowed'         => false,
                    'current'         => 0,
                    'limit'           => 0,
                    'upgrade_message' => _upgradeMessage('dropshipping', 0, $plan['name'] ?? 'Free'),
                ];
            }
            $limit = $dropship === true ? -1 : (int)($limits['max_dropship_products'] ?? 100);
            $current = _countDropshipProducts($db, $supplierId);
            if ($limit < 0) return ['allowed' => true, 'current' => $current, 'limit' => 'unlimited'];
            $allowed = $current < $limit;
            return [
                'allowed'         => $allowed,
                'current'         => $current,
                'limit'           => $limit,
                'upgrade_message' => $allowed ? null : _upgradeMessage('dropshipping', $limit, $plan['name'] ?? 'Free'),
            ];

        case 'can_livestream':
            $limit = (int)($limits['livestream_per_week'] ?? 0);
            if ($limit < 0) return ['allowed' => true, 'current' => 0, 'limit' => 'unlimited'];
            if ($limit === 0) {
                return [
                    'allowed'         => false,
                    'current'         => 0,
                    'limit'           => 0,
                    'upgrade_message' => _upgradeMessage('livestream', 0, $plan['name'] ?? 'Free'),
                ];
            }
            $current = _countLivestreamsThisWeek($db, $supplierId);
            $allowed = $current < $limit;
            return [
                'allowed'         => $allowed,
                'current'         => $current,
                'limit'           => $limit,
                'upgrade_message' => $allowed ? null : _upgradeMessage('livestream', $limit, $plan['name'] ?? 'Free'),
            ];

        case 'can_api':
            $apiAccess = $limits['api_access'] ?? false;
            $allowed   = $apiAccess && $apiAccess !== '0' && $apiAccess !== false;
            return [
                'allowed'         => (bool)$allowed,
                'current'         => (bool)$allowed ? 1 : 0,
                'limit'           => 'feature',
                'upgrade_message' => $allowed ? null : _upgradeMessage('api', 0, $plan['name'] ?? 'Free'),
            ];

        default:
            return ['allowed' => false, 'current' => 0, 'limit' => 0, 'upgrade_message' => 'Unknown limit key.'];
    }
}

/**
 * Enforce plan limits before an action; throws or returns error array if exceeded.
 * Returns null if allowed, error array if not.
 */
function enforcePlanLimits(int $supplierId, string $action): ?array
{
    $limitKey = match ($action) {
        'create_product'  => 'max_products',
        'upload_image'    => 'max_images_per_product',
        'add_dropship'    => 'max_dropship_products',
        'start_livestream'=> 'can_livestream',
        'use_api'         => 'can_api',
        default           => null,
    };

    if (!$limitKey) return null;

    $check = checkPlanLimit($supplierId, $limitKey);
    if (!$check['allowed']) {
        return [
            'error'   => $check['upgrade_message'] ?? 'Plan limit reached.',
            'limit'   => $check['limit'],
            'current' => $check['current'],
        ];
    }
    return null;
}

// ── Quota & features ─────────────────────────────────────────────────────────

/**
 * Get remaining quota for a limit key.
 * Returns int or 'unlimited'.
 */
function getRemainingQuota(int $supplierId, string $limitKey): int|string
{
    $check = checkPlanLimit($supplierId, $limitKey);
    if ($check['limit'] === 'unlimited') return 'unlimited';
    $limit   = (int)($check['limit'] ?? 0);
    $current = (int)($check['current'] ?? 0);
    return max(0, $limit - $current);
}

/**
 * Get features list for a plan (for comparison table).
 */
function getPlanFeatures(string $planSlug): array
{
    $db = getDB();
    try {
        $stmt = $db->prepare(
            'SELECT pf.* FROM plan_features pf
             JOIN supplier_plans sp ON sp.id = pf.plan_id
             WHERE sp.slug = ?
             ORDER BY pf.sort_order ASC'
        );
        $stmt->execute([$planSlug]);
        $rows = $stmt->fetchAll();
        if ($rows) return $rows;
    } catch (PDOException $e) { /* fall through */ }

    // Inline defaults if table not yet seeded
    return _defaultFeatures($planSlug);
}

/**
 * Return discount percentage for a billing period.
 */
function getDurationDiscount(string $billingPeriod): float
{
    return match ($billingPeriod) {
        'quarterly'   => 10.0,
        'semi_annual' => 15.0,
        'annual'      => 25.0,
        default       => 0.0,
    };
}

/**
 * Calculate proration amount when upgrading mid-cycle.
 */
function calculateProration(int $supplierId, string $newPlanSlug): array
{
    $current     = getSupplierActivePlan($supplierId);
    $newPlan     = getPlan($newPlanSlug);
    $newPrice    = (float)($newPlan['price'] ?? 0);
    $currentPrice = (float)($current['price'] ?? 0);

    $daysRemaining  = 0;
    $proratedCredit = 0.0;

    if (!empty($current['current_period_end'])) {
        $daysInPeriod   = _billingPeriodMonths($current['billing_period'] ?? 'monthly') * 30;
        $endTs          = strtotime($current['current_period_end']);
        $daysRemaining  = max(0, (int)ceil(($endTs - time()) / 86400));
        $proratedCredit = round(($currentPrice / $daysInPeriod) * $daysRemaining, 2);
    }

    $amountDue = max(0, round($newPrice - $proratedCredit, 2));

    return [
        'current_plan'    => $current['name'] ?? 'Free',
        'new_plan'        => $newPlan['name'] ?? $newPlanSlug,
        'new_plan_price'  => $newPrice,
        'days_remaining'  => $daysRemaining,
        'prorated_credit' => $proratedCredit,
        'amount_due'      => $amountDue,
    ];
}

/**
 * Get billing history (invoices) for a supplier.
 */
function getPlanBillingHistory(int $supplierId, int $limit = 20, int $offset = 0): array
{
    $db = getDB();
    try {
        $stmt = $db->prepare(
            'SELECT * FROM plan_invoices
             WHERE supplier_id = ?
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->execute([$supplierId, $limit, $offset]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// ── Admin helpers ────────────────────────────────────────────────────────────

/**
 * Admin: manually set a supplier's plan.
 */
function adminSetSupplierPlan(int $supplierId, string $planSlug, string $billingPeriod = 'monthly'): array
{
    return subscribeToPlan($supplierId, $planSlug, $billingPeriod);
}

/**
 * Get subscription counts by plan (for admin dashboard).
 */
function getPlanSubscriberCounts(): array
{
    $db = getDB();
    try {
        $stmt = $db->query(
            'SELECT sp.name, sp.slug, COUNT(ps.id) AS subscriber_count,
                    COALESCE(SUM(ps.amount),0) AS mrr
             FROM supplier_plans sp
             LEFT JOIN plan_subscriptions ps ON ps.plan_id = sp.id AND ps.status = "active"
             WHERE sp.is_active = 1
             GROUP BY sp.id
             ORDER BY sp.sort_order ASC'
        );
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// ── Internal helpers ─────────────────────────────────────────────────────────

function _billingPeriodMonths(string $period): int
{
    return match ($period) {
        'quarterly'   => 3,
        'semi_annual' => 6,
        'annual'      => 12,
        default       => 1,
    };
}

function _countProducts(PDO $db, int $supplierId): int
{
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM products WHERE supplier_id = ? AND status != "archived"');
        $stmt->execute([$supplierId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) { return 0; }
}

function _countDropshipProducts(PDO $db, int $supplierId): int
{
    try {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM products WHERE supplier_id = ? AND is_dropship = 1 AND status != "archived"'
        );
        $stmt->execute([$supplierId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) { return 0; }
}

function _countLivestreamsThisWeek(PDO $db, int $supplierId): int
{
    try {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM livestreams WHERE supplier_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
        );
        $stmt->execute([$supplierId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) { return 0; }
}

function _upgradeMessage(string $feature, int|string $limit, string $planName): string
{
    return match ($feature) {
        'products'    => "You've reached your {$planName} plan limit of {$limit} products. Upgrade to Pro for 500 products or Enterprise for unlimited!",
        'dropshipping'=> "Dropshipping is not available on the {$planName} plan. Upgrade to Pro or Enterprise to enable dropshipping.",
        'livestream'  => "Livestreaming is not available on the {$planName} plan. Upgrade to Pro or Enterprise to go live.",
        'api'         => "API access is not available on the {$planName} plan. Upgrade to Pro or Enterprise to use the API.",
        default       => "You've reached your {$planName} plan limit. Please upgrade your plan.",
    };
}

function _recordPlanInvoice(
    PDO $db,
    int $subId,
    int $supplierId,
    float $amount,
    string $billingPeriod,
    string $status = 'paid',
    string $description = ''
): void {
    try {
        $db->prepare(
            'INSERT INTO plan_invoices
                (subscription_id, supplier_id, amount, billing_period, status, description, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        )->execute([$subId, $supplierId, $amount, $billingPeriod, $status, $description]);
    } catch (PDOException $e) { /* non-fatal */ }
}

function _freePlanDefaults(): array
{
    return [
        'id'                  => 0,
        'name'                => 'Free',
        'slug'                => 'free',
        'price'               => 0,
        'billing_period'      => 'monthly',
        'amount'              => 0,
        'commission_discount' => 0,
        'status'              => 'active',
        'stripe_price_id'     => null,
        'limits_decoded'      => [
            'products'              => 10,
            'images_per_product'    => 3,
            'featured_per_month'    => 0,
            'livestream_per_week'   => 0,
            'dropshipping'          => false,
            'api_access'            => false,
        ],
        'features_decoded'    => [
            'support'   => 'community',
            'analytics' => 'basic',
            'badge'     => 'none',
        ],
    ];
}

function _defaultPlans(): array
{
    return [
        [
            'id' => 1, 'name' => 'Free', 'slug' => 'free', 'price' => 0,
            'billing_period' => 'monthly', 'commission_discount' => 0, 'sort_order' => 1,
            'is_active' => 1, 'stripe_price_id' => null,
            'limits_decoded'   => ['products' => 10, 'images_per_product' => 3, 'featured_per_month' => 0,
                                   'livestream_per_week' => 0, 'dropshipping' => false, 'api_access' => false],
            'features_decoded' => ['badge' => 'none', 'analytics' => 'basic', 'support' => 'community'],
        ],
        [
            'id' => 2, 'name' => 'Pro', 'slug' => 'pro', 'price' => 299,
            'billing_period' => 'monthly', 'commission_discount' => 15, 'sort_order' => 2,
            'is_active' => 1, 'stripe_price_id' => null,
            'limits_decoded'   => ['products' => 500, 'images_per_product' => 10, 'featured_per_month' => 2,
                                   'livestream_per_week' => 2, 'dropshipping' => true, 'api_access' => 'basic'],
            'features_decoded' => ['badge' => 'pro', 'analytics' => 'advanced', 'support' => 'email', 'custom_store' => true],
        ],
        [
            'id' => 3, 'name' => 'Enterprise', 'slug' => 'enterprise', 'price' => 999,
            'billing_period' => 'monthly', 'commission_discount' => 30, 'sort_order' => 3,
            'is_active' => 1, 'stripe_price_id' => null,
            'limits_decoded'   => ['products' => -1, 'images_per_product' => 20, 'featured_per_month' => -1,
                                   'livestream_per_week' => -1, 'dropshipping' => true, 'api_access' => 'full'],
            'features_decoded' => ['badge' => 'enterprise', 'analytics' => 'full_ai', 'support' => 'phone_email',
                                   'custom_store' => true, 'custom_domain' => true],
        ],
    ];
}

function _defaultFeatures(string $planSlug): array
{
    $map = [
        'free'       => [
            ['feature_key' => 'max_products',          'feature_value' => '10',          'feature_label' => 'Products'],
            ['feature_key' => 'max_images_per_product','feature_value' => '3',           'feature_label' => 'Images / product'],
            ['feature_key' => 'max_dropship_products', 'feature_value' => '0',           'feature_label' => 'Dropship products'],
            ['feature_key' => 'can_livestream',        'feature_value' => '0',           'feature_label' => 'Livestream'],
            ['feature_key' => 'can_api',               'feature_value' => '0',           'feature_label' => 'API access'],
            ['feature_key' => 'commission_discount',   'feature_value' => '0',           'feature_label' => 'Commission discount'],
            ['feature_key' => 'support_level',         'feature_value' => 'Community',   'feature_label' => 'Support'],
        ],
        'pro'        => [
            ['feature_key' => 'max_products',          'feature_value' => '500',         'feature_label' => 'Products'],
            ['feature_key' => 'max_images_per_product','feature_value' => '10',          'feature_label' => 'Images / product'],
            ['feature_key' => 'max_dropship_products', 'feature_value' => '100',         'feature_label' => 'Dropship products'],
            ['feature_key' => 'can_livestream',        'feature_value' => '1',           'feature_label' => 'Livestream'],
            ['feature_key' => 'can_api',               'feature_value' => 'basic',       'feature_label' => 'API access'],
            ['feature_key' => 'commission_discount',   'feature_value' => '15',          'feature_label' => 'Commission discount'],
            ['feature_key' => 'support_level',         'feature_value' => 'Priority email', 'feature_label' => 'Support'],
        ],
        'enterprise' => [
            ['feature_key' => 'max_products',          'feature_value' => 'Unlimited',   'feature_label' => 'Products'],
            ['feature_key' => 'max_images_per_product','feature_value' => '20',          'feature_label' => 'Images / product'],
            ['feature_key' => 'max_dropship_products', 'feature_value' => 'Unlimited',   'feature_label' => 'Dropship products'],
            ['feature_key' => 'can_livestream',        'feature_value' => '1',           'feature_label' => 'Livestream'],
            ['feature_key' => 'can_api',               'feature_value' => 'full',        'feature_label' => 'API access'],
            ['feature_key' => 'commission_discount',   'feature_value' => '30',          'feature_label' => 'Commission discount'],
            ['feature_key' => 'support_level',         'feature_value' => 'Dedicated manager', 'feature_label' => 'Support'],
        ],
    ];
    return $map[$planSlug] ?? [];
}
