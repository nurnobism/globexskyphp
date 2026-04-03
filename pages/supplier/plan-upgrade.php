<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireRole(['supplier', 'admin', 'super_admin']);

$db     = getDB();
$planId = (int)($_GET['plan_id'] ?? 0);

// Load plan
$plan = null;
if ($planId > 0) {
    try {
        $stmt = $db->prepare('SELECT * FROM supplier_plans WHERE id = ? AND is_active = 1');
        $stmt->execute([$planId]);
        $plan = $stmt->fetch();
    } catch (PDOException $e) { /* ignore */ }
}

if (!$plan || (float)$plan['price'] <= 0) {
    header('Location: /pages/supplier/plans.php?error=' . urlencode('Invalid plan selected'));
    exit;
}

$plan['limits_decoded']   = json_decode($plan['features'] ?? '{}', true) ?: [];
$plan['features_decoded'] = json_decode($plan['features'] ?? '{}', true) ?: [];

// Check current subscription for proration
$currentPlan = null;
try {
    $csStmt = $db->prepare('SELECT ps.*, sp.price AS current_price, sp.name AS current_name
        FROM plan_subscriptions ps JOIN supplier_plans sp ON sp.id = ps.plan_id
        WHERE ps.supplier_id = ? AND ps.status = "active" ORDER BY ps.created_at DESC LIMIT 1');
    $csStmt->execute([$_SESSION['user_id']]);
    $currentPlan = $csStmt->fetch() ?: null;
} catch (PDOException $e) { /* ignore */ }

// Proration calculation
$proratedAmount  = (float)$plan['price'];
$daysRemaining   = 0;
$proratedCredit  = 0.0;
if ($currentPlan && (float)($currentPlan['current_price'] ?? 0) > 0 && !empty($currentPlan['current_period_end'])) {
    $daysInPeriod    = 30;
    $endTimestamp    = strtotime($currentPlan['current_period_end']);
    $daysRemaining   = max(0, (int)ceil(($endTimestamp - time()) / 86400));
    $proratedCredit  = round(((float)$currentPlan['current_price'] / $daysInPeriod) * $daysRemaining, 2);
    $proratedAmount  = max(0, round((float)$plan['price'] - $proratedCredit, 2));
}

$pageTitle = 'Upgrade to ' . ($plan['name'] ?? 'Plan');
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5" style="max-width:640px">
    <div class="d-flex align-items-center mb-4">
        <a href="/pages/supplier/plans.php" class="btn btn-outline-secondary btn-sm me-3">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <h3 class="fw-bold mb-0">Upgrade to <?= e($plan['name']) ?></h3>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= e($_GET['success']) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger"><?= e($_GET['error']) ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Order Summary</h5>
        </div>
        <div class="card-body">
            <div class="d-flex justify-content-between mb-2">
                <span><?= e($plan['name']) ?> Plan (Monthly)</span>
                <strong>$<?= number_format((float)$plan['price'], 2) ?></strong>
            </div>
            <?php if ($proratedCredit > 0): ?>
            <div class="d-flex justify-content-between mb-2 text-success">
                <span>Proration credit (<?= $daysRemaining ?> days remaining on <?= e($currentPlan['current_name']) ?>)</span>
                <strong>-$<?= number_format($proratedCredit, 2) ?></strong>
            </div>
            <?php endif; ?>
            <hr>
            <div class="d-flex justify-content-between fw-bold fs-5">
                <span>Due Today</span>
                <span class="text-primary">$<?= number_format($proratedAmount, 2) ?></span>
            </div>
            <small class="text-muted d-block mt-1">Then $<?= number_format((float)$plan['price'], 2) ?>/month thereafter. Cancel anytime.</small>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h6 class="fw-bold mb-3"><i class="bi bi-credit-card me-2"></i>Payment via Stripe</h6>
            <p class="text-muted small">You will be redirected to Stripe's secure checkout to complete your payment.</p>

            <?php if (!empty($plan['stripe_price_id'])): ?>
            <form method="POST" action="/api/plans.php?action=subscribe">
                <?= csrfField() ?>
                <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
                <button type="submit" class="btn btn-primary w-100 btn-lg">
                    <i class="bi bi-lock-fill me-2"></i>
                    Proceed to Checkout — $<?= number_format($proratedAmount, 2) ?>
                </button>
            </form>
            <?php else: ?>
            <div class="alert alert-warning">
                <i class="bi bi-info-circle me-2"></i>
                Stripe checkout is not yet configured for this plan. Please contact support to upgrade.
            </div>
            <?php endif; ?>

            <div class="text-center mt-3">
                <small class="text-muted">
                    <i class="bi bi-shield-check text-success me-1"></i>
                    Secured by Stripe. We never store your card details.
                </small>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
