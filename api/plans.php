<?php
/**
 * api/plans.php — Supplier Plans API (PR #9)
 *
 * Actions:
 *   list                  — GET:  All available plans with features/pricing
 *   current               — GET:  Supplier's current plan + usage (auth)
 *   subscribe             — POST: Subscribe to a plan
 *   upgrade               — POST: Upgrade plan with proration
 *   downgrade             — POST: Schedule downgrade at end of billing cycle
 *   cancel                — POST: Cancel subscription (downgrade to Free)
 *   usage                 — GET:  Plan usage / limits dashboard data
 *   billing_history       — GET:  Subscription billing history / invoices
 *   update_payment_method — POST: Update Stripe payment method
 */

require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/plans.php';

header('Content-Type: application/json');

$db     = getDB();
$action = $_REQUEST['action'] ?? '';

function plansApiJson(mixed $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

switch ($action) {

    // ── List all available plans ──────────────────────────────────────────
    case 'list':
        $plans = getPlans();
        $result = [];
        foreach ($plans as $p) {
            $result[] = [
                'id'                     => $p['id'],
                'name'                   => $p['name'],
                'slug'                   => $p['slug'],
                'price_monthly'          => (float)($p['price_monthly'] ?? $p['price'] ?? 0),
                'price_quarterly'        => (float)($p['price_quarterly']   ?? 0),
                'price_semi_annual'      => (float)($p['price_semi_annual'] ?? 0),
                'price_annual'           => (float)($p['price_annual']      ?? 0),
                'commission_discount'    => (float)($p['commission_discount'] ?? 0),
                'max_products'           => (int)($p['max_products']           ?? 10),
                'max_images_per_product' => (int)($p['max_images_per_product'] ?? 3),
                'max_shipping_templates' => (int)($p['max_shipping_templates'] ?? 1),
                'max_dropship_imports'   => (int)($p['max_dropship_imports']   ?? 0),
                'max_featured_listings'  => (int)($p['max_featured_listings']  ?? 0),
                'max_livestreams'        => (int)($p['max_livestreams']        ?? 0),
                'features'               => $p['features_decoded'] ?? [],
                'sort_order'             => (int)($p['sort_order'] ?? 0),
                'is_active'              => (bool)($p['is_active'] ?? true),
            ];
        }
        plansApiJson(['success' => true, 'plans' => $result]);

    // ── Get supplier's current plan ───────────────────────────────────────
    case 'current':
        requireLogin();
        $supplierId = (int)($_GET['supplier_id'] ?? $_SESSION['user_id']);
        if (!isAdmin()) $supplierId = (int)$_SESSION['user_id'];

        $plan    = getCurrentPlan($supplierId);
        $usage   = getPlanUsage($supplierId);
        $expiry  = getPlanExpiry($supplierId);
        $active  = isPlanActive($supplierId);

        plansApiJson([
            'success' => true,
            'plan'    => [
                'id'                  => $plan['id'],
                'name'                => $plan['name'],
                'slug'                => $plan['slug'],
                'commission_discount' => (float)($plan['commission_discount'] ?? 0),
                'features'            => $plan['features_decoded'] ?? [],
            ],
            'usage'   => $usage,
            'expiry'  => $expiry,
            'active'  => $active,
        ]);

    // ── Subscribe to a plan ───────────────────────────────────────────────
    case 'subscribe':
        requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') plansApiJson(['error' => 'POST required'], 405);
        if (!verifyCsrf()) plansApiJson(['error' => 'Invalid CSRF token'], 403);

        $supplierId = (int)$_SESSION['user_id'];
        $planId     = (int)($_POST['plan_id'] ?? 0);
        $duration   = in_array($_POST['duration'] ?? '', array_keys(PLAN_DURATION_DISCOUNTS))
            ? $_POST['duration']
            : 'monthly';
        $paymentMethodId = htmlspecialchars(trim($_POST['payment_method_id'] ?? ''), ENT_QUOTES, 'UTF-8');

        if (!$planId) plansApiJson(['error' => 'plan_id required'], 400);

        $plan = getPlan($planId);
        if (!$plan) plansApiJson(['error' => 'Plan not found'], 404);

        // For paid plans without a payment method, return Stripe Checkout redirect
        $totalPrice = getPlanTotalPrice($plan, $duration);
        if ($totalPrice > 0 && !$paymentMethodId) {
            plansApiJson([
                'success'      => true,
                'requires_payment' => true,
                'redirect'     => '/pages/supplier/plan-upgrade.php?plan_id=' . $planId . '&duration=' . urlencode($duration),
                'plan'         => ['id' => $plan['id'], 'name' => $plan['name']],
                'total_price'  => $totalPrice,
                'duration'     => $duration,
            ]);
        }

        $result = subscribeToPlan($supplierId, $planId, $duration, $paymentMethodId);
        if (!($result['success'] ?? false)) {
            plansApiJson(['error' => $result['error'] ?? 'Subscription failed'], 422);
        }

        plansApiJson([
            'success'               => true,
            'message'               => 'Subscribed to ' . $plan['name'] . ' plan',
            'subscription_id'       => $result['subscription_id'],
            'stripe_subscription_id'=> $result['stripe_subscription_id'],
        ]);

    // ── Upgrade plan ──────────────────────────────────────────────────────
    case 'upgrade':
        requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') plansApiJson(['error' => 'POST required'], 405);
        if (!verifyCsrf()) plansApiJson(['error' => 'Invalid CSRF token'], 403);

        $supplierId = (int)$_SESSION['user_id'];
        $planId     = (int)($_POST['plan_id'] ?? 0);
        $duration   = in_array($_POST['duration'] ?? '', array_keys(PLAN_DURATION_DISCOUNTS))
            ? $_POST['duration']
            : 'monthly';

        if (!$planId) plansApiJson(['error' => 'plan_id required'], 400);

        $newPlan = getPlan($planId);
        if (!$newPlan) plansApiJson(['error' => 'Plan not found'], 404);

        $totalPrice = getPlanTotalPrice($newPlan, $duration);
        if ($totalPrice > 0) {
            // Redirect to upgrade checkout
            plansApiJson([
                'success'          => true,
                'requires_payment' => true,
                'redirect'         => '/pages/supplier/plan-upgrade.php?plan_id=' . $planId . '&duration=' . urlencode($duration) . '&mode=upgrade',
                'plan'             => ['id' => $newPlan['id'], 'name' => $newPlan['name']],
                'total_price'      => $totalPrice,
                'duration'         => $duration,
            ]);
        }

        $result = upgradePlan($supplierId, $planId, $duration);
        if (!($result['success'] ?? false)) {
            plansApiJson(['error' => $result['error'] ?? 'Upgrade failed'], 422);
        }

        plansApiJson([
            'success'          => true,
            'message'          => 'Upgraded to ' . $newPlan['name'] . ' plan',
            'prorated_credit'  => $result['prorated_credit'] ?? 0,
            'charge_amount'    => $result['charge_amount'] ?? 0,
            'subscription_id'  => $result['subscription_id'] ?? null,
        ]);

    // ── Downgrade plan ────────────────────────────────────────────────────
    case 'downgrade':
        requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') plansApiJson(['error' => 'POST required'], 405);
        if (!verifyCsrf()) plansApiJson(['error' => 'Invalid CSRF token'], 403);

        $supplierId = (int)$_SESSION['user_id'];
        $planId     = (int)($_POST['plan_id'] ?? 0);
        if (!$planId) plansApiJson(['error' => 'plan_id required'], 400);

        $result = downgradePlan($supplierId, $planId);
        if (!($result['success'] ?? false)) {
            plansApiJson(['error' => $result['error'] ?? 'Downgrade failed'], 422);
        }

        plansApiJson([
            'success'        => true,
            'message'        => 'Downgrade scheduled',
            'effective_date' => $result['effective_date'],
            'warnings'       => $result['warnings'] ?? [],
        ]);

    // ── Cancel subscription ───────────────────────────────────────────────
    case 'cancel':
        requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') plansApiJson(['error' => 'POST required'], 405);
        if (!verifyCsrf()) plansApiJson(['error' => 'Invalid CSRF token'], 403);

        $supplierId = (int)$_SESSION['user_id'];

        $result = cancelPlan($supplierId);
        if (!($result['success'] ?? false)) {
            plansApiJson(['error' => $result['error'] ?? 'Cancellation failed'], 422);
        }

        plansApiJson([
            'success'        => true,
            'message'        => 'Subscription cancelled. You will be downgraded to Free on ' . ($result['effective_date'] ?? 'end of billing cycle'),
            'effective_date' => $result['effective_date'],
        ]);

    // ── Plan usage ────────────────────────────────────────────────────────
    case 'usage':
        requireLogin();
        $supplierId = (int)($_GET['supplier_id'] ?? $_SESSION['user_id']);
        if (!isAdmin()) $supplierId = (int)$_SESSION['user_id'];

        $usage  = getPlanUsage($supplierId);
        $expiry = getPlanExpiry($supplierId);
        $active = isPlanActive($supplierId);

        plansApiJson([
            'success' => true,
            'usage'   => $usage,
            'expiry'  => $expiry,
            'active'  => $active,
        ]);

    // ── Billing history ───────────────────────────────────────────────────
    case 'billing_history':
        requireLogin();
        $supplierId = (int)($_GET['supplier_id'] ?? $_SESSION['user_id']);
        if (!isAdmin()) $supplierId = (int)$_SESSION['user_id'];

        $invoices = getPlanInvoices($supplierId);

        // Also fetch subscription rows
        $subscriptions = [];
        try {
            $stmt = $db->prepare(
                'SELECT ps.*, sp.name AS plan_name
                 FROM plan_subscriptions ps
                 LEFT JOIN supplier_plans sp ON sp.id = ps.plan_id
                 WHERE ps.supplier_id = ?
                 ORDER BY ps.created_at DESC
                 LIMIT 20'
            );
            $stmt->execute([$supplierId]);
            $subscriptions = $stmt->fetchAll();
        } catch (PDOException $e) { /* ignore */ }

        plansApiJson([
            'success'       => true,
            'invoices'      => $invoices,
            'subscriptions' => $subscriptions,
        ]);

    // ── Update payment method ─────────────────────────────────────────────
    case 'update_payment_method':
        requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') plansApiJson(['error' => 'POST required'], 405);
        if (!verifyCsrf()) plansApiJson(['error' => 'Invalid CSRF token'], 403);

        $supplierId      = (int)$_SESSION['user_id'];
        $paymentMethodId = htmlspecialchars(trim($_POST['payment_method_id'] ?? ''), ENT_QUOTES, 'UTF-8');

        if (!$paymentMethodId) plansApiJson(['error' => 'payment_method_id required'], 400);

        // Get customer ID
        $sub = _getActiveSubscriptionRow($supplierId, $db);
        if (!$sub || !$sub['stripe_customer_id']) {
            plansApiJson(['error' => 'No active paid subscription found'], 404);
        }

        try {
            // Attach and set as default
            _stripeCurl('POST', "/payment_methods/{$paymentMethodId}/attach", [
                'customer' => $sub['stripe_customer_id'],
            ]);
            _stripeCurl('POST', "/customers/{$sub['stripe_customer_id']}", [
                'invoice_settings[default_payment_method]' => $paymentMethodId,
            ]);
            plansApiJson(['success' => true, 'message' => 'Payment method updated']);
        } catch (RuntimeException $e) {
            plansApiJson(['error' => $e->getMessage()], 422);
        }

    // ── Check limit ───────────────────────────────────────────────────────
    case 'check_limit':
        requireLogin();
        $supplierId = (int)$_SESSION['user_id'];
        $limitKey   = htmlspecialchars($_GET['limit_key'] ?? '', ENT_QUOTES, 'UTF-8');

        if (!$limitKey) plansApiJson(['error' => 'limit_key required'], 400);

        $reached = checkPlanLimit($supplierId, $limitKey);
        plansApiJson([
            'success'  => true,
            'reached'  => $reached,
            'prompt'   => $reached ? getUpgradePrompt($supplierId, $limitKey) : null,
        ]);

    default:
        plansApiJson(['error' => 'Unknown action'], 400);
}
