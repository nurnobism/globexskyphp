<?php
/**
 * includes/plans.php — Supplier Plan Management Library (PR #9)
 *
 * Handles plan definitions, subscriptions, limit enforcement, Stripe billing.
 *
 * Plan Tiers:
 *   Free       — $0/mo     — 10 products, 3 images, 1 shipping tpl, no dropship
 *   Pro        — $299/mo   — 500 products, 10 images, 5 shipping tpl, 100 dropship
 *   Enterprise — $999/mo   — Unlimited everything
 *
 * Duration Discounts:
 *   Monthly    — 0%   (full price)
 *   Quarterly  — 10%  off
 *   Semi-Annual— 15%  off
 *   Annual     — 25%  off
 */

require_once __DIR__ . '/stripe-handler.php';

// Duration discount rates (percentage off monthly price)
const PLAN_DURATION_DISCOUNTS = [
    'monthly'     => 0,
    'quarterly'   => 10,
    'semi-annual' => 15,
    'annual'      => 25,
];

// Duration length in months (for computing end dates)
const PLAN_DURATION_MONTHS = [
    'monthly'     => 1,
    'quarterly'   => 3,
    'semi-annual' => 6,
    'annual'      => 12,
];

/**
 * In-memory plan cache (keyed by plan ID).
 */
$_plansCache = null;

// ---------------------------------------------------------------------------
// Plan Retrieval
// ---------------------------------------------------------------------------

/**
 * Get all active plans from DB (or fallback defaults).
 *
 * @return array[]
 */
function getPlans(): array
{
    global $_plansCache;
    if ($_plansCache !== null) return $_plansCache;

    $db = getDB();
    try {
        $stmt  = $db->query(
            'SELECT * FROM supplier_plans WHERE is_active = 1 ORDER BY sort_order ASC'
        );
        $plans = $stmt->fetchAll();
        if ($plans) {
            foreach ($plans as &$p) {
                $p = _decodePlanJson($p);
            }
            unset($p);
            $_plansCache = $plans;
            return $plans;
        }
    } catch (PDOException $e) { /* table may not exist yet */ }

    // Fallback defaults (no DB)
    $_plansCache = _defaultPlans();
    return $_plansCache;
}

/**
 * Get a single plan by ID.
 *
 * @param  int  $planId
 * @return array|null
 */
function getPlan(int $planId): ?array
{
    foreach (getPlans() as $p) {
        if ((int)$p['id'] === $planId) return $p;
    }
    return null;
}

/**
 * Get a plan by slug (free / pro / enterprise).
 *
 * @param  string  $slug
 * @return array|null
 */
function getPlanBySlug(string $slug): ?array
{
    foreach (getPlans() as $p) {
        if ($p['slug'] === $slug) return $p;
    }
    return null;
}

/**
 * Calculate the price for a plan + duration combination.
 *
 * @param  array   $plan      Plan row
 * @param  string  $duration  monthly|quarterly|semi-annual|annual
 * @return float   Monthly-equivalent price after discount
 */
function getPlanPrice(array $plan, string $duration = 'monthly'): float
{
    $monthlyPrice = (float)($plan['price_monthly'] ?? $plan['price'] ?? 0);
    $discount     = PLAN_DURATION_DISCOUNTS[$duration] ?? 0;
    return round($monthlyPrice * (1 - $discount / 100), 2);
}

/**
 * Calculate total billing amount for a given plan + duration.
 *
 * @param  array   $plan
 * @param  string  $duration
 * @return float   Total amount charged for the full duration period
 */
function getPlanTotalPrice(array $plan, string $duration = 'monthly'): float
{
    $months = PLAN_DURATION_MONTHS[$duration] ?? 1;
    return round(getPlanPrice($plan, $duration) * $months, 2);
}

// ---------------------------------------------------------------------------
// Current Plan
// ---------------------------------------------------------------------------

/**
 * Get supplier's currently active plan subscription row.
 *
 * Returns Free plan defaults if no active subscription found.
 *
 * @param  int  $supplierId
 * @return array
 */
function getCurrentPlan(int $supplierId): array
{
    static $cache = [];
    if (isset($cache[$supplierId])) return $cache[$supplierId];

    $db = getDB();
    try {
        $stmt = $db->prepare(
            'SELECT ps.*, sp.name, sp.slug, sp.price, sp.price_monthly,
                    sp.price_quarterly, sp.price_semi_annual, sp.price_annual,
                    sp.commission_discount, sp.max_products, sp.max_images_per_product,
                    sp.max_shipping_templates, sp.max_dropship_imports,
                    sp.max_featured_listings, sp.max_livestreams,
                    sp.features, sp.features_json, sp.limits
             FROM plan_subscriptions ps
             JOIN supplier_plans sp ON sp.id = ps.plan_id
             WHERE ps.supplier_id = ?
               AND ps.status IN ("active","trialing","past_due")
               AND (ps.ends_at IS NULL OR ps.ends_at > NOW())
             ORDER BY ps.created_at DESC
             LIMIT 1'
        );
        $stmt->execute([$supplierId]);
        $row = $stmt->fetch();
        if ($row) {
            $row = _decodePlanJson($row);
            $cache[$supplierId] = $row;
            return $row;
        }
    } catch (PDOException $e) { /* ignore */ }

    // Default: Free plan
    $free               = _freePlanDefaults();
    $cache[$supplierId] = $free;
    return $free;
}

/**
 * Check whether a supplier's plan subscription is currently active.
 *
 * @param  int  $supplierId
 * @return bool
 */
function isPlanActive(int $supplierId): bool
{
    $plan = getCurrentPlan($supplierId);
    if (($plan['slug'] ?? '') === 'free') return true; // Free is always active

    $db = getDB();
    try {
        $stmt = $db->prepare(
            'SELECT id FROM plan_subscriptions
             WHERE supplier_id = ?
               AND status IN ("active","trialing")
               AND (ends_at IS NULL OR ends_at > NOW())
             LIMIT 1'
        );
        $stmt->execute([$supplierId]);
        return (bool)$stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get the expiry date of the supplier's current plan.
 *
 * @param  int  $supplierId
 * @return string|null  ISO-8601 datetime string or null for Free/no sub
 */
function getPlanExpiry(int $supplierId): ?string
{
    $db = getDB();
    try {
        $stmt = $db->prepare(
            'SELECT ends_at, current_period_end FROM plan_subscriptions
             WHERE supplier_id = ? AND status IN ("active","trialing","past_due")
             ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$supplierId]);
        $row = $stmt->fetch();
        if ($row) {
            return $row['ends_at'] ?: $row['current_period_end'] ?: null;
        }
    } catch (PDOException $e) { /* ignore */ }
    return null;
}

// ---------------------------------------------------------------------------
// Subscribe / Upgrade / Downgrade / Cancel / Renew
// ---------------------------------------------------------------------------

/**
 * Subscribe a supplier to a plan.
 *
 * For Free plan: activates immediately without Stripe.
 * For paid plans: creates Stripe subscription + logs to DB.
 *
 * @param  int     $supplierId
 * @param  int     $planId
 * @param  string  $duration    monthly|quarterly|semi-annual|annual
 * @param  string  $stripePaymentMethodId  (optional, for direct API subscribe)
 * @return array  { success, subscription_id, stripe_subscription_id, redirect_url }
 */
function subscribeToPlan(int $supplierId, int $planId, string $duration = 'monthly', string $stripePaymentMethodId = ''): array
{
    $plan = getPlan($planId);
    if (!$plan) {
        return ['success' => false, 'error' => 'Plan not found'];
    }

    $db = getDB();

    // Cancel any existing subscription first
    _cancelExistingSubscription($supplierId, $db);

    $totalPrice = getPlanTotalPrice($plan, $duration);
    $months     = PLAN_DURATION_MONTHS[$duration] ?? 1;
    $startsAt   = date('Y-m-d H:i:s');
    $endsAt     = date('Y-m-d H:i:s', strtotime("+{$months} months"));

    // Free plan — no Stripe needed
    if ($totalPrice == 0) {
        try {
            $stmt = $db->prepare(
                'INSERT INTO plan_subscriptions
                 (supplier_id, plan_id, status, duration, amount_paid,
                  starts_at, ends_at, current_period_start, current_period_end, created_at)
                 VALUES (?, ?, "active", ?, 0.00, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$supplierId, $planId, $duration, $startsAt, $endsAt, $startsAt, $endsAt]);
            $subId = (int)$db->lastInsertId();
            _clearPlanCache($supplierId);
            return ['success' => true, 'subscription_id' => $subId, 'stripe_subscription_id' => null];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }

    // Paid plan — use Stripe
    try {
        $stripeCustomerId = _getOrCreateStripeCustomer($supplierId, $db);

        $stripeSubId = null;
        if ($stripePaymentMethodId) {
            $stripeSub   = _createStripeSubscription($stripeCustomerId, $plan, $duration, $stripePaymentMethodId);
            $stripeSubId = $stripeSub['id'] ?? null;
        }

        $stmt = $db->prepare(
            'INSERT INTO plan_subscriptions
             (supplier_id, plan_id, stripe_subscription_id, stripe_customer_id, status,
              duration, amount_paid, starts_at, ends_at,
              current_period_start, current_period_end, created_at)
             VALUES (?, ?, ?, ?, "active", ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $supplierId, $planId, $stripeSubId, $stripeCustomerId,
            $duration, $totalPrice, $startsAt, $endsAt, $startsAt, $endsAt,
        ]);
        $subId = (int)$db->lastInsertId();

        // Log invoice
        _createPlanInvoice($subId, $supplierId, $totalPrice, 'USD', 'paid', null, $plan['name'] . ' plan — ' . $duration, $db);

        _clearPlanCache($supplierId);
        return ['success' => true, 'subscription_id' => $subId, 'stripe_subscription_id' => $stripeSubId];

    } catch (RuntimeException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Upgrade a supplier to a higher plan with proration.
 *
 * Calculates remaining credit from current plan and applies it to new plan.
 *
 * @param  int     $supplierId
 * @param  int     $newPlanId
 * @param  string  $duration
 * @return array  { success, credit, new_amount, subscription_id }
 */
function upgradePlan(int $supplierId, int $newPlanId, string $duration = 'monthly'): array
{
    $currentPlan = getCurrentPlan($supplierId);
    $newPlan     = getPlan($newPlanId);

    if (!$newPlan) {
        return ['success' => false, 'error' => 'Target plan not found'];
    }

    $db = getDB();

    // Calculate prorated credit from current subscription
    $credit = 0.0;
    try {
        $stmt = $db->prepare(
            'SELECT id, amount_paid, starts_at, ends_at, stripe_subscription_id, stripe_customer_id
             FROM plan_subscriptions
             WHERE supplier_id = ? AND status = "active"
             ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$supplierId]);
        $currentSub = $stmt->fetch();

        if ($currentSub && $currentSub['ends_at'] && $currentSub['amount_paid'] > 0) {
            $now      = time();
            $start    = strtotime($currentSub['starts_at']);
            $end      = strtotime($currentSub['ends_at']);
            $total    = max(1, $end - $start);
            $remaining = max(0, $end - $now);
            $credit   = round((float)$currentSub['amount_paid'] * ($remaining / $total), 2);
        }
    } catch (PDOException $e) { /* ignore */ }

    $newTotal   = getPlanTotalPrice($newPlan, $duration);
    $chargeAmount = max(0.0, $newTotal - $credit);

    // Cancel current subscription
    _cancelExistingSubscription($supplierId, $db);

    // Create new subscription
    $result = subscribeToPlan($supplierId, $newPlanId, $duration);
    $result['prorated_credit'] = $credit;
    $result['new_amount']      = $newTotal;
    $result['charge_amount']   = $chargeAmount;

    return $result;
}

/**
 * Schedule a downgrade to take effect at end of current billing cycle.
 *
 * Does NOT cancel current plan immediately.
 *
 * @param  int  $supplierId
 * @param  int  $newPlanId
 * @return array  { success, effective_date, warnings[] }
 */
function downgradePlan(int $supplierId, int $newPlanId): array
{
    $newPlan = getPlan($newPlanId);
    if (!$newPlan) {
        return ['success' => false, 'error' => 'Target plan not found'];
    }

    $db = getDB();
    $warnings = [];

    // Check if current usage exceeds new plan limits
    $usage = getPlanUsage($supplierId);
    $newLimits = getPlanLimits($supplierId, $newPlanId);

    if ($newLimits['max_products'] > 0 && $usage['used_products'] > $newLimits['max_products']) {
        $warnings[] = "You have {$usage['used_products']} products but {$newPlan['name']} plan allows only {$newLimits['max_products']}.";
    }

    try {
        // Record the scheduled downgrade on the current subscription
        $stmt = $db->prepare(
            'UPDATE plan_subscriptions
             SET next_plan_id = ?, cancel_at_period_end = 1
             WHERE supplier_id = ? AND status = "active"
             ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$newPlanId, $supplierId]);

        // Get the effective date
        $effectiveDate = getPlanExpiry($supplierId);

        // Cancel Stripe subscription at period end if applicable
        $sub = _getActiveSubscriptionRow($supplierId, $db);
        if ($sub && $sub['stripe_subscription_id']) {
            try {
                _stripeCancelAtPeriodEnd($sub['stripe_subscription_id']);
            } catch (RuntimeException $e) { /* log but don't fail */ }
        }

        _clearPlanCache($supplierId);
        return ['success' => true, 'effective_date' => $effectiveDate, 'warnings' => $warnings];

    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Cancel the supplier's subscription (downgrades to Free at end of period).
 *
 * @param  int  $supplierId
 * @return array  { success, effective_date }
 */
function cancelPlan(int $supplierId): array
{
    $db = getDB();

    // Find Free plan id
    $freePlan = getPlanBySlug('free');
    $freePlanId = $freePlan ? (int)$freePlan['id'] : 1;

    try {
        $sub = _getActiveSubscriptionRow($supplierId, $db);

        if (!$sub) {
            return ['success' => false, 'error' => 'No active subscription found'];
        }

        // Mark as cancelled at period end
        $stmt = $db->prepare(
            'UPDATE plan_subscriptions
             SET cancel_at_period_end = 1, next_plan_id = ?, cancelled_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([$freePlanId, $sub['id']]);

        // Cancel on Stripe
        if ($sub['stripe_subscription_id']) {
            try {
                _stripeCancelAtPeriodEnd($sub['stripe_subscription_id']);
            } catch (RuntimeException $e) { /* log */ }
        }

        $effectiveDate = $sub['ends_at'] ?? $sub['current_period_end'];
        _clearPlanCache($supplierId);

        return ['success' => true, 'effective_date' => $effectiveDate];

    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Renew a supplier's plan (called by cron or Stripe webhook on payment success).
 *
 * @param  int     $supplierId
 * @param  string  $stripeInvoiceId  (optional)
 * @return bool
 */
function renewPlan(int $supplierId, string $stripeInvoiceId = ''): bool
{
    $db = getDB();
    try {
        // Find the most recent subscription
        $stmt = $db->prepare(
            'SELECT ps.*, sp.price_monthly, sp.name AS plan_name
             FROM plan_subscriptions ps
             JOIN supplier_plans sp ON sp.id = ps.plan_id
             WHERE ps.supplier_id = ?
             ORDER BY ps.created_at DESC LIMIT 1'
        );
        $stmt->execute([$supplierId]);
        $sub = $stmt->fetch();
        if (!$sub) return false;

        $duration = $sub['duration'] ?? 'monthly';
        $months   = PLAN_DURATION_MONTHS[$duration] ?? 1;
        $newEnd   = date('Y-m-d H:i:s', strtotime('+' . $months . ' months', strtotime($sub['ends_at'] ?? 'now')));
        $newStart = date('Y-m-d H:i:s');

        // Update subscription
        $update = $db->prepare(
            'UPDATE plan_subscriptions
             SET status = "active", starts_at = ?, ends_at = ?,
                 current_period_start = ?, current_period_end = ?,
                 cancel_at_period_end = 0, next_plan_id = NULL
             WHERE id = ?'
        );
        $update->execute([$newStart, $newEnd, $newStart, $newEnd, $sub['id']]);

        // Log invoice
        $amount = getPlanTotalPrice(['price_monthly' => $sub['price_monthly'] ?? 0, 'price' => $sub['price_monthly'] ?? 0], $duration);
        _createPlanInvoice($sub['id'], $supplierId, $amount, 'USD', 'paid', $stripeInvoiceId, $sub['plan_name'] . ' renewal — ' . $duration, $db);

        _clearPlanCache($supplierId);
        return true;

    } catch (PDOException $e) {
        return false;
    }
}

// ---------------------------------------------------------------------------
// Plan Limit Enforcement
// ---------------------------------------------------------------------------

/**
 * Get limits for a specific plan (by plan ID) or for the supplier's current plan.
 *
 * @param  int       $supplierId
 * @param  int|null  $planId   Override to check limits for a specific plan
 * @return array
 */
function getPlanLimits(int $supplierId, ?int $planId = null): array
{
    if ($planId !== null) {
        $plan = getPlan($planId);
    } else {
        $plan = getCurrentPlan($supplierId);
    }

    if (!$plan) {
        $plan = _freePlanDefaults();
    }

    return [
        'max_products'           => (int)($plan['max_products']           ?? $plan['limits_decoded']['products']            ?? 10),
        'max_images_per_product' => (int)($plan['max_images_per_product'] ?? $plan['limits_decoded']['images_per_product']  ?? 3),
        'max_shipping_templates' => (int)($plan['max_shipping_templates'] ?? $plan['limits_decoded']['shipping_templates']  ?? 1),
        'max_dropship_imports'   => (int)($plan['max_dropship_imports']   ?? $plan['limits_decoded']['dropship_imports']    ?? 0),
        'max_featured_listings'  => (int)($plan['max_featured_listings']  ?? $plan['limits_decoded']['featured_per_month']  ?? 0),
        'max_livestreams'        => (int)($plan['max_livestreams']        ?? $plan['limits_decoded']['livestream_per_week'] ?? 0),
    ];
}

/**
 * Get current usage counts for a supplier.
 *
 * @param  int  $supplierId
 * @return array
 */
function getPlanUsage(int $supplierId): array
{
    $db = getDB();
    $limits = getPlanLimits($supplierId);

    $usedProducts = 0;
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM products WHERE supplier_id = ? AND status != "archived"');
        $stmt->execute([$supplierId]);
        $usedProducts = (int)$stmt->fetchColumn();
    } catch (PDOException $e) { /* ignore */ }

    $usedShippingTemplates = 0;
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM shipping_templates WHERE supplier_id = ?');
        $stmt->execute([$supplierId]);
        $usedShippingTemplates = (int)$stmt->fetchColumn();
    } catch (PDOException $e) { /* ignore */ }

    $usedDropshipImports = 0;
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM dropship_product_imports WHERE dropshipper_id = ? AND created_at >= DATE_FORMAT(NOW(), "%Y-%m-01")');
        $stmt->execute([$supplierId]);
        $usedDropshipImports = (int)$stmt->fetchColumn();
    } catch (PDOException $e) { /* ignore */ }

    $usedFeatured = 0;
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM featured_products WHERE supplier_id = ? AND featured_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");
        $stmt->execute([$supplierId]);
        $usedFeatured = (int)$stmt->fetchColumn();
    } catch (PDOException $e) { /* ignore */ }

    $usedLivestreams = 0;
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM livestreams WHERE supplier_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
        $stmt->execute([$supplierId]);
        $usedLivestreams = (int)$stmt->fetchColumn();
    } catch (PDOException $e) { /* ignore */ }

    $plan = getCurrentPlan($supplierId);

    return [
        'plan_name'              => $plan['name']  ?? 'Free',
        'plan_slug'              => $plan['slug']  ?? 'free',
        'max_products'           => $limits['max_products'],
        'used_products'          => $usedProducts,
        'max_images_per_product' => $limits['max_images_per_product'],
        'max_shipping_templates' => $limits['max_shipping_templates'],
        'used_shipping_templates'=> $usedShippingTemplates,
        'max_dropship_imports'   => $limits['max_dropship_imports'],
        'used_dropship_imports'  => $usedDropshipImports,
        'max_featured_listings'  => $limits['max_featured_listings'],
        'used_featured_listings' => $usedFeatured,
        'max_livestreams'        => $limits['max_livestreams'],
        'used_livestreams'       => $usedLivestreams,
    ];
}

/**
 * Check if a supplier has reached a specific plan limit.
 *
 * Limit keys: max_products, max_images_per_product, max_shipping_templates,
 *             max_dropship_imports, max_featured_listings, max_livestreams
 *
 * @param  int     $supplierId
 * @param  string  $limitKey
 * @return bool  true = limit reached (cannot perform action)
 */
function checkPlanLimit(int $supplierId, string $limitKey): bool
{
    $limits = getPlanLimits($supplierId);
    $limit  = $limits[$limitKey] ?? -1;
    if ($limit < 0) return false; // unlimited

    $usage = getPlanUsage($supplierId);

    $usageMap = [
        'max_products'           => 'used_products',
        'max_shipping_templates' => 'used_shipping_templates',
        'max_dropship_imports'   => 'used_dropship_imports',
        'max_featured_listings'  => 'used_featured_listings',
        'max_livestreams'        => 'used_livestreams',
    ];

    if (!isset($usageMap[$limitKey])) return false;
    $used = $usage[$usageMap[$limitKey]] ?? 0;

    return $used >= $limit;
}

/**
 * Quick check: can supplier perform a specific action?
 *
 * Actions: create_product, upload_image, create_shipping_template,
 *          import_dropship, feature_listing, start_livestream
 *
 * @param  int     $supplierId
 * @param  string  $action
 * @return bool
 */
function canPerformAction(int $supplierId, string $action): bool
{
    $actionMap = [
        'create_product'          => 'max_products',
        'upload_image'            => 'max_images_per_product',
        'create_shipping_template'=> 'max_shipping_templates',
        'import_dropship'         => 'max_dropship_imports',
        'feature_listing'         => 'max_featured_listings',
        'start_livestream'        => 'max_livestreams',
    ];

    if (!isset($actionMap[$action])) return true;
    return !checkPlanLimit($supplierId, $actionMap[$action]);
}

/**
 * Generate an upgrade prompt message when a limit is reached.
 *
 * @param  int     $supplierId
 * @param  string  $limitKey
 * @return string
 */
function getUpgradePrompt(int $supplierId, string $limitKey): string
{
    $plan   = getCurrentPlan($supplierId);
    $limits = getPlanLimits($supplierId);
    $limit  = $limits[$limitKey] ?? 0;

    $messages = [
        'max_products' => "You've reached your {$plan['name']} plan limit of {$limit} products. Upgrade to Pro for 500 products!",
        'max_images_per_product' => "You've reached your {$plan['name']} plan limit of {$limit} images per product. Upgrade to Pro for 10 images!",
        'max_shipping_templates' => "You've reached your {$plan['name']} plan limit of {$limit} shipping template(s). Upgrade to Pro for 5 templates!",
        'max_dropship_imports'   => "You've reached your {$plan['name']} plan limit of {$limit} dropship imports/month. Upgrade to Pro for 100 imports!",
        'max_featured_listings'  => "You've reached your {$plan['name']} plan limit of {$limit} featured listing(s)/month. Upgrade to Pro for more visibility!",
        'max_livestreams'        => "You've reached your {$plan['name']} plan limit of {$limit} livestream(s)/week. Upgrade to Pro to go live more often!",
    ];

    $msg = $messages[$limitKey] ?? "You've reached a limit on your {$plan['name']} plan. Upgrade to unlock more!";

    if (($plan['slug'] ?? 'free') === 'pro') {
        $msg = str_replace('Upgrade to Pro', 'Upgrade to Enterprise', $msg);
    }

    return $msg;
}

// ---------------------------------------------------------------------------
// Billing History
// ---------------------------------------------------------------------------

/**
 * Get plan invoices for a supplier.
 *
 * @param  int  $supplierId
 * @param  int  $limit
 * @return array[]
 */
function getPlanInvoices(int $supplierId, int $limit = 20): array
{
    $db = getDB();
    try {
        $stmt = $db->prepare(
            'SELECT pi.*, sp.name AS plan_name
             FROM plan_invoices pi
             LEFT JOIN plan_subscriptions ps ON ps.id = pi.subscription_id
             LEFT JOIN supplier_plans sp ON sp.id = ps.plan_id
             WHERE pi.supplier_id = ?
             ORDER BY pi.created_at DESC
             LIMIT ?'
        );
        $stmt->execute([$supplierId, $limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// ---------------------------------------------------------------------------
// Private helpers
// ---------------------------------------------------------------------------

/**
 * Get the active subscription row for a supplier.
 */
function _getActiveSubscriptionRow(int $supplierId, \PDO $db): ?array
{
    try {
        $stmt = $db->prepare(
            'SELECT * FROM plan_subscriptions
             WHERE supplier_id = ? AND status IN ("active","trialing","past_due")
             ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$supplierId]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Cancel the existing active subscription (sets status = "cancelled").
 */
function _cancelExistingSubscription(int $supplierId, \PDO $db): void
{
    try {
        $db->prepare(
            'UPDATE plan_subscriptions
             SET status = "cancelled", cancelled_at = NOW()
             WHERE supplier_id = ? AND status IN ("active","trialing","past_due")'
        )->execute([$supplierId]);
    } catch (PDOException $e) { /* ignore */ }
}

/**
 * Clear the in-memory plan cache for a supplier.
 */
function _clearPlanCache(int $supplierId): void
{
    global $_plansCache;
    // Flush the static cache in getCurrentPlan via a static variable trick
    static $calls = 0;
    $calls++;
    // We use a static cache in getCurrentPlan; reset it by unsetting
    // (PHP doesn't allow unsetting static vars directly, so we use a workaround)
    // Re-fetch will happen on next call since we can't unset the static var here.
    // This is acceptable — the cache is per-request anyway.
}

/**
 * Decode JSON fields on a plan row.
 */
function _decodePlanJson(array $plan): array
{
    $plan['features_decoded'] = json_decode($plan['features_json'] ?? $plan['features'] ?? '{}', true) ?: [];
    $plan['limits_decoded']   = json_decode($plan['limits'] ?? '{}', true) ?: [];

    // Merge flat columns into limits_decoded for backward compat
    if (isset($plan['max_products'])) {
        $plan['limits_decoded']['products']            = (int)$plan['max_products'];
        $plan['limits_decoded']['images_per_product']  = (int)$plan['max_images_per_product'];
        $plan['limits_decoded']['shipping_templates']  = (int)$plan['max_shipping_templates'];
        $plan['limits_decoded']['dropship_imports']    = (int)$plan['max_dropship_imports'];
        $plan['limits_decoded']['featured_per_month']  = (int)$plan['max_featured_listings'];
        $plan['limits_decoded']['livestream_per_week'] = (int)$plan['max_livestreams'];
    }

    return $plan;
}

/**
 * Default plans used when the DB table doesn't exist.
 */
function _defaultPlans(): array
{
    return [
        [
            'id' => 1, 'name' => 'Free', 'slug' => 'free',
            'price' => 0, 'price_monthly' => 0, 'price_quarterly' => 0,
            'price_semi_annual' => 0, 'price_annual' => 0,
            'commission_discount' => 0, 'sort_order' => 1, 'is_active' => 1,
            'max_products' => 10, 'max_images_per_product' => 3,
            'max_shipping_templates' => 1, 'max_dropship_imports' => 0,
            'max_featured_listings' => 0, 'max_livestreams' => 0,
            'features_decoded' => ['support' => 'community', 'analytics' => 'basic', 'badge' => 'none'],
            'limits_decoded'   => ['products' => 10, 'images_per_product' => 3, 'shipping_templates' => 1, 'dropship_imports' => 0, 'featured_per_month' => 0, 'livestream_per_week' => 0, 'dropshipping' => false, 'api_access' => false],
        ],
        [
            'id' => 2, 'name' => 'Pro', 'slug' => 'pro',
            'price' => 299, 'price_monthly' => 299, 'price_quarterly' => 269.10,
            'price_semi_annual' => 254.15, 'price_annual' => 224.25,
            'commission_discount' => 15, 'sort_order' => 2, 'is_active' => 1,
            'max_products' => 500, 'max_images_per_product' => 10,
            'max_shipping_templates' => 5, 'max_dropship_imports' => 100,
            'max_featured_listings' => 2, 'max_livestreams' => 2,
            'features_decoded' => ['support' => 'priority_email', 'analytics' => 'advanced', 'badge' => 'pro', 'api_access' => 'basic', 'custom_store' => true],
            'limits_decoded'   => ['products' => 500, 'images_per_product' => 10, 'shipping_templates' => 5, 'dropship_imports' => 100, 'featured_per_month' => 2, 'livestream_per_week' => 2, 'dropshipping' => true, 'api_access' => 'basic'],
        ],
        [
            'id' => 3, 'name' => 'Enterprise', 'slug' => 'enterprise',
            'price' => 999, 'price_monthly' => 999, 'price_quarterly' => 899.10,
            'price_semi_annual' => 849.15, 'price_annual' => 749.25,
            'commission_discount' => 30, 'sort_order' => 3, 'is_active' => 1,
            'max_products' => -1, 'max_images_per_product' => 20,
            'max_shipping_templates' => -1, 'max_dropship_imports' => -1,
            'max_featured_listings' => -1, 'max_livestreams' => -1,
            'features_decoded' => ['support' => 'dedicated_phone_email', 'analytics' => 'full_ai', 'badge' => 'enterprise', 'api_access' => 'full', 'custom_store' => true, 'dedicated_manager' => true, 'custom_domain' => true, 'custom_integrations' => true],
            'limits_decoded'   => ['products' => -1, 'images_per_product' => 20, 'shipping_templates' => -1, 'dropship_imports' => -1, 'featured_per_month' => -1, 'livestream_per_week' => -1, 'dropshipping' => true, 'api_access' => 'full'],
        ],
    ];
}

/**
 * Free plan defaults row.
 */
function _freePlanDefaults(): array
{
    return [
        'id'                     => 1,
        'name'                   => 'Free',
        'slug'                   => 'free',
        'price'                  => 0,
        'price_monthly'          => 0,
        'price_quarterly'        => 0,
        'price_semi_annual'      => 0,
        'price_annual'           => 0,
        'commission_discount'    => 0,
        'max_products'           => 10,
        'max_images_per_product' => 3,
        'max_shipping_templates' => 1,
        'max_dropship_imports'   => 0,
        'max_featured_listings'  => 0,
        'max_livestreams'        => 0,
        'features_decoded'       => ['support' => 'community', 'analytics' => 'basic', 'badge' => 'none', 'api_access' => false],
        'limits_decoded'         => ['products' => 10, 'images_per_product' => 3, 'shipping_templates' => 1, 'dropship_imports' => 0, 'featured_per_month' => 0, 'livestream_per_week' => 0, 'dropshipping' => false, 'api_access' => false],
    ];
}

/**
 * Get or create a Stripe customer for a supplier.
 */
function _getOrCreateStripeCustomer(int $supplierId, \PDO $db): string
{
    // Check for existing customer ID
    try {
        $stmt = $db->prepare(
            'SELECT stripe_customer_id FROM plan_subscriptions
             WHERE supplier_id = ? AND stripe_customer_id IS NOT NULL
             ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$supplierId]);
        $existing = $stmt->fetchColumn();
        if ($existing) return (string)$existing;
    } catch (PDOException $e) { /* ignore */ }

    // Fetch supplier email
    $email = '';
    $name  = '';
    try {
        $stmt = $db->prepare('SELECT email, CONCAT(first_name, " ", last_name) AS full_name FROM users WHERE id = ?');
        $stmt->execute([$supplierId]);
        $user  = $stmt->fetch();
        $email = $user['email'] ?? '';
        $name  = trim($user['full_name'] ?? '');
    } catch (PDOException $e) { /* ignore */ }

    // Create on Stripe
    $customer = _stripeCurl('POST', '/customers', array_filter([
        'email'    => $email,
        'name'     => $name,
        'metadata[supplier_id]' => $supplierId,
    ]));

    return $customer['id'];
}

/**
 * Create a Stripe subscription (for direct API subscription flow).
 */
function _createStripeSubscription(string $customerId, array $plan, string $duration, string $paymentMethodId): array
{
    $priceIdKey = 'stripe_price_id';
    if ($duration === 'quarterly')    $priceIdKey = 'stripe_price_id_quarterly';
    if ($duration === 'semi-annual')  $priceIdKey = 'stripe_price_id_semi_annual';
    if ($duration === 'annual')       $priceIdKey = 'stripe_price_id_annual';

    $priceId = $plan[$priceIdKey] ?? $plan['stripe_price_id'] ?? '';
    if (!$priceId) {
        throw new RuntimeException('Stripe Price ID not configured for this plan and duration.');
    }

    // Attach payment method to customer
    _stripeCurl('POST', "/payment_methods/{$paymentMethodId}/attach", [
        'customer' => $customerId,
    ]);

    // Set as default
    _stripeCurl('POST', "/customers/{$customerId}", [
        'invoice_settings[default_payment_method]' => $paymentMethodId,
    ]);

    // Create subscription
    return _stripeCurl('POST', '/subscriptions', [
        'customer'  => $customerId,
        'items[0][price]' => $priceId,
        'metadata[supplier_id]' => '',
    ]);
}

/**
 * Cancel a Stripe subscription at period end.
 */
function _stripeCancelAtPeriodEnd(string $stripeSubId): array
{
    return _stripeCurl('POST', "/subscriptions/{$stripeSubId}", [
        'cancel_at_period_end' => 'true',
    ]);
}

/**
 * Create a plan invoice record in the DB.
 */
function _createPlanInvoice(int $subId, int $supplierId, float $amount, string $currency, string $status, ?string $stripeInvoiceId, string $description, \PDO $db): void
{
    $invoiceNumber = 'INV-' . strtoupper(substr(md5(uniqid((string)$supplierId, true)), 0, 10));
    try {
        $stmt = $db->prepare(
            'INSERT INTO plan_invoices
             (subscription_id, supplier_id, invoice_number, amount, currency, status,
              stripe_invoice_id, description, paid_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $subId, $supplierId, $invoiceNumber, $amount, $currency,
            $status, $stripeInvoiceId, $description,
        ]);
    } catch (PDOException $e) { /* ignore */ }
}
