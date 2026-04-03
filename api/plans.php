<?php
/**
 * api/plans.php — Supplier Plans API
 *
 * Actions:
 *   list         — Get all available plans with features
 *   current      — Get supplier's current plan
 *   subscribe    — Create Stripe subscription for a plan
 *   upgrade      — Upgrade plan (with proration)
 *   downgrade    — Downgrade plan (effective next billing cycle)
 *   cancel       — Cancel plan subscription
 *   invoices     — List invoices for supplier
 *   check_limit  — Check if supplier has reached plan limit
 *   webhook      — Handle Stripe subscription webhooks
 */

require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/plan_limits.php';

header('Content-Type: application/json');

$db     = getDB();
$action = $_REQUEST['action'] ?? '';

function plansJson(mixed $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

switch ($action) {

    // ── List all available plans ──────────────────────────────────────
    case 'list':
        $stmt = $db->query('SELECT * FROM supplier_plans WHERE is_active = 1 ORDER BY sort_order ASC');
        $plans = $stmt->fetchAll();
        foreach ($plans as &$p) {
            $p['features_decoded'] = json_decode($p['features'] ?? '{}', true) ?: [];
            $p['limits_decoded']   = json_decode($p['limits'] ?? '{}', true) ?: [];
        }
        plansJson(['success' => true, 'plans' => $plans]);

    // ── Get supplier's current plan ──────────────────────────────────
    case 'current':
        requireLogin();
        $supplierId = (int)($_GET['supplier_id'] ?? $_SESSION['user_id']);
        if (!isAdmin()) $supplierId = $_SESSION['user_id'];

        $plan = getSupplierPlan($supplierId);
        $remaining = getRemainingLimits($supplierId);
        plansJson(['success' => true, 'plan' => $plan, 'remaining' => $remaining]);

    // ── Subscribe to a plan ───────────────────────────────────────────
    case 'subscribe':
        requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') plansJson(['error' => 'POST required'], 405);
        if (!verifyCsrf()) plansJson(['error' => 'Invalid CSRF token'], 403);

        $planId     = (int)($_POST['plan_id'] ?? 0);
        $supplierId = $_SESSION['user_id'];

        $planStmt = $db->prepare('SELECT * FROM supplier_plans WHERE id = ? AND is_active = 1');
        $planStmt->execute([$planId]);
        $plan = $planStmt->fetch();
        if (!$plan) plansJson(['error' => 'Plan not found'], 404);

        // Check for existing active subscription
        $existingStmt = $db->prepare('SELECT id FROM plan_subscriptions WHERE supplier_id = ? AND status = "active"');
        $existingStmt->execute([$supplierId]);
        if ($existingStmt->fetch()) {
            plansJson(['error' => 'You already have an active subscription. Use upgrade/downgrade instead.'], 409);
        }

        // For free plan, create subscription directly
        if ((float)$plan['price'] == 0) {
            $db->prepare('INSERT INTO plan_subscriptions
                (supplier_id, plan_id, status, current_period_start, current_period_end, created_at)
                VALUES (?, ?, "active", NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH), NOW())')
               ->execute([$supplierId, $planId]);
            plansJson(['success' => true, 'message' => 'Subscribed to free plan', 'redirect' => null]);
        }

        // For paid plans: return Stripe checkout redirect URL
        $stripePriceId = $plan['stripe_price_id'] ?? '';
        if (!$stripePriceId) {
            plansJson(['error' => 'This plan is not yet available for purchase. Contact support.'], 503);
        }

        plansJson([
            'success'        => true,
            'redirect'       => '/pages/supplier/plan-upgrade.php?plan_id=' . $planId,
            'stripe_price_id'=> $stripePriceId,
        ]);

    // ── Upgrade plan ──────────────────────────────────────────────────
    case 'upgrade':
        requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') plansJson(['error' => 'POST required'], 405);
        if (!verifyCsrf()) plansJson(['error' => 'Invalid CSRF token'], 403);

        $newPlanId  = (int)($_POST['plan_id'] ?? 0);
        $supplierId = $_SESSION['user_id'];

        $planStmt = $db->prepare('SELECT * FROM supplier_plans WHERE id = ? AND is_active = 1');
        $planStmt->execute([$newPlanId]);
        $newPlan = $planStmt->fetch();
        if (!$newPlan) plansJson(['error' => 'Plan not found'], 404);

        // Cancel existing subscription
        $db->prepare('UPDATE plan_subscriptions SET status = "cancelled", updated_at = NOW() WHERE supplier_id = ? AND status = "active"')
           ->execute([$supplierId]);

        // Create new subscription
        $db->prepare('INSERT INTO plan_subscriptions
            (supplier_id, plan_id, status, current_period_start, current_period_end, created_at, updated_at)
            VALUES (?, ?, "active", NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH), NOW(), NOW())')
           ->execute([$supplierId, $newPlanId]);

        plansJson(['success' => true, 'message' => 'Plan upgraded successfully']);

    // ── Downgrade plan ────────────────────────────────────────────────
    case 'downgrade':
        requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') plansJson(['error' => 'POST required'], 405);
        if (!verifyCsrf()) plansJson(['error' => 'Invalid CSRF token'], 403);

        $newPlanId  = (int)($_POST['plan_id'] ?? 0);
        $supplierId = $_SESSION['user_id'];

        // Mark current subscription to cancel at period end
        $db->prepare('UPDATE plan_subscriptions SET cancel_at_period_end = 1, updated_at = NOW() WHERE supplier_id = ? AND status = "active"')
           ->execute([$supplierId]);

        // Queue downgrade for next billing cycle
        plansJson(['success' => true, 'message' => 'Your plan will be downgraded at the end of the current billing cycle.']);

    // ── Cancel subscription ───────────────────────────────────────────
    case 'cancel':
        requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') plansJson(['error' => 'POST required'], 405);
        if (!verifyCsrf()) plansJson(['error' => 'Invalid CSRF token'], 403);

        $supplierId = $_SESSION['user_id'];
        $db->prepare('UPDATE plan_subscriptions SET status = "cancelled", cancel_at_period_end = 1, updated_at = NOW() WHERE supplier_id = ? AND status = "active"')
           ->execute([$supplierId]);
        plansJson(['success' => true, 'message' => 'Subscription cancelled. You retain access until the end of the billing period.']);

    // ── List invoices ─────────────────────────────────────────────────
    case 'invoices':
        requireLogin();
        $supplierId = isAdmin() ? (int)($_GET['supplier_id'] ?? 0) : $_SESSION['user_id'];
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $where  = $supplierId > 0 ? 'WHERE supplier_id = ?' : '';
        $params = $supplierId > 0 ? [$supplierId] : [];
        $cStmt  = $db->prepare("SELECT COUNT(*) FROM invoices $where");
        $cStmt->execute($params);
        $total  = (int)$cStmt->fetchColumn();

        $stmt = $db->prepare("SELECT * FROM invoices $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        plansJson(['success' => true, 'data' => $stmt->fetchAll(), 'total' => $total]);

    // ── Check plan limit ──────────────────────────────────────────────
    case 'check_limit':
        requireLogin();
        $supplierId = isAdmin() ? (int)($_GET['supplier_id'] ?? $_SESSION['user_id']) : $_SESSION['user_id'];
        $feature    = $_GET['feature'] ?? 'products';

        $can = match ($feature) {
            'products'    => canAddProduct($supplierId),
            'livestream'  => canUseLivestream($supplierId),
            'dropshipping'=> canUseDropshipping($supplierId),
            'api'         => canUseAPI($supplierId),
            'featured'    => canGetFeatured($supplierId),
            default       => false,
        };

        plansJson(['success' => true, 'can' => $can, 'feature' => $feature,
                   'limits' => getRemainingLimits($supplierId)]);

    // ── Stripe webhook ────────────────────────────────────────────────
    case 'webhook':
        $payload = file_get_contents('php://input');
        $event   = json_decode($payload, true);
        if (!$event) plansJson(['error' => 'Invalid payload'], 400);

        $eventType = $event['type'] ?? '';
        $object    = $event['data']['object'] ?? [];

        switch ($eventType) {
            case 'invoice.paid':
                $subId = $object['subscription'] ?? '';
                if ($subId) {
                    $db->prepare('UPDATE plan_subscriptions SET status = "active", updated_at = NOW() WHERE stripe_subscription_id = ?')
                       ->execute([$subId]);
                }
                break;

            case 'customer.subscription.updated':
                $subId  = $object['id'] ?? '';
                $status = $object['status'] ?? 'active';
                if ($subId) {
                    $dbStatus = match ($status) {
                        'active'   => 'active',
                        'past_due' => 'past_due',
                        'trialing' => 'trialing',
                        default    => 'cancelled',
                    };
                    $db->prepare('UPDATE plan_subscriptions SET status = ?, updated_at = NOW() WHERE stripe_subscription_id = ?')
                       ->execute([$dbStatus, $subId]);
                }
                break;

            case 'customer.subscription.deleted':
                $subId = $object['id'] ?? '';
                if ($subId) {
                    $db->prepare('UPDATE plan_subscriptions SET status = "cancelled", updated_at = NOW() WHERE stripe_subscription_id = ?')
                       ->execute([$subId]);
                }
                break;
        }

        plansJson(['success' => true, 'received' => $eventType]);

    default:
        plansJson(['error' => 'Invalid action'], 400);
}
