<?php
/**
 * pages/supplier/plans/upgrade.php — Upgrade Confirmation (PR #9)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/plans.php';
requireRole(['supplier', 'admin', 'super_admin']);

$db            = getDB();
$supplierId    = $_SESSION['user_id'];
$planSlug      = $_GET['plan'] ?? '';
$billingPeriod = in_array($_GET['billing'] ?? '', ['monthly','quarterly','semi_annual','annual'])
    ? $_GET['billing']
    : 'monthly';

$newPlan = getPlan($planSlug);
if (!$newPlan || (float)($newPlan['price'] ?? 0) <= 0) {
    header('Location: /pages/supplier/plans/index.php?error=' . urlencode('Please select a valid paid plan to upgrade.'));
    exit;
}

$currentPlan = getSupplierActivePlan($supplierId);
$proration   = calculateProration($supplierId, $planSlug);
$discount    = getDurationDiscount($billingPeriod);
$basePrice   = (float)($newPlan['price'] ?? 0);
$discounted  = round($basePrice * (1 - $discount / 100), 2);

// If upgrading, amount due is discounted price minus proration credit
$amountDue   = max(0, round($discounted - (float)($proration['prorated_credit'] ?? 0), 2));

$pageTitle = 'Upgrade to ' . ($newPlan['name'] ?? 'Plan');
include __DIR__ . '/../../../includes/header.php';

$periodLabel = [
    'monthly'    => '1 month',
    'quarterly'  => '3 months',
    'semi_annual'=> '6 months',
    'annual'     => '12 months',
][$billingPeriod] ?? '1 month';
?>
<div class="container py-5">
    <div class="d-flex align-items-center mb-4">
        <a href="/pages/supplier/plans/index.php?billing=<?= urlencode($billingPeriod) ?>"
           class="btn btn-outline-secondary btn-sm me-3">
            <i class="bi bi-arrow-left me-1"></i> Back to Plans
        </a>
        <h3 class="fw-bold mb-0">
            <i class="bi bi-arrow-up-circle text-success me-2"></i>Upgrade to <?= e($newPlan['name']) ?>
        </h3>
    </div>

    <div class="row g-4 justify-content-center">
        <!-- Plan comparison -->
        <div class="col-md-7">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light fw-semibold">
                    <i class="bi bi-arrow-left-right me-2 text-primary"></i>Plan Comparison
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <!-- Current plan -->
                        <div class="col-6">
                            <div class="rounded border p-3 h-100 bg-light">
                                <div class="small text-muted mb-1">Current Plan</div>
                                <h6 class="fw-bold mb-1"><?= e($currentPlan['name'] ?? 'Free') ?></h6>
                                <div class="text-muted small">
                                    <?= (float)($currentPlan['price'] ?? 0) > 0
                                        ? '$' . number_format((float)$currentPlan['price'], 0) . '/mo'
                                        : 'Free' ?>
                                </div>
                                <?php $clim = $currentPlan['limits_decoded'] ?? []; ?>
                                <ul class="list-unstyled small mt-2 mb-0 text-muted">
                                    <li><i class="bi bi-box-seam me-1"></i>
                                        <?= ($clim['products'] ?? 10) < 0 ? 'Unlimited' : ($clim['products'] ?? 10) ?> products</li>
                                    <li><i class="bi bi-images me-1"></i>
                                        <?= $clim['images_per_product'] ?? 3 ?> images/product</li>
                                    <li><i class="bi bi-truck me-1"></i>Dropship:
                                        <?= empty($clim['dropshipping']) ? 'No' : 'Yes' ?></li>
                                </ul>
                            </div>
                        </div>
                        <!-- New plan -->
                        <div class="col-6">
                            <div class="rounded border border-success p-3 h-100">
                                <div class="small text-success mb-1 fw-semibold">New Plan ✓</div>
                                <h6 class="fw-bold mb-1"><?= e($newPlan['name'] ?? '') ?></h6>
                                <div class="text-muted small">
                                    $<?= number_format($basePrice, 0) ?>/mo
                                    <?php if ($discount > 0): ?>
                                    <span class="badge bg-success ms-1">-<?= $discount ?>% off</span>
                                    <?php endif; ?>
                                </div>
                                <?php $nlim = $newPlan['limits_decoded'] ?? []; ?>
                                <ul class="list-unstyled small mt-2 mb-0">
                                    <li class="text-success"><i class="bi bi-box-seam me-1"></i>
                                        <?= ($nlim['products'] ?? 10) < 0 ? 'Unlimited' : ($nlim['products'] ?? 10) ?> products</li>
                                    <li class="text-success"><i class="bi bi-images me-1"></i>
                                        <?= $nlim['images_per_product'] ?? 3 ?> images/product</li>
                                    <li class="text-success"><i class="bi bi-truck me-1"></i>Dropship:
                                        <?= empty($nlim['dropshipping']) ? 'No' : 'Yes' ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment summary -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light fw-semibold">
                    <i class="bi bi-calculator me-2 text-primary"></i>Payment Summary
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted">New plan price</td>
                            <td class="text-end">$<?= number_format($basePrice, 2) ?>/mo</td>
                        </tr>
                        <?php if ($discount > 0): ?>
                        <tr>
                            <td class="text-muted">
                                <?= ucwords(str_replace('_', '-', $billingPeriod)) ?> billing discount (<?= $discount ?>%)
                            </td>
                            <td class="text-end text-success">
                                -$<?= number_format(round($basePrice * $discount / 100, 2), 2) ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if ((float)($proration['prorated_credit'] ?? 0) > 0): ?>
                        <tr>
                            <td class="text-muted">
                                Prorated credit from <?= e($proration['current_plan'] ?? '') ?>
                                (<?= $proration['days_remaining'] ?> days remaining)
                            </td>
                            <td class="text-end text-success">
                                -$<?= number_format((float)$proration['prorated_credit'], 2) ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr class="border-top">
                            <td class="fw-bold pt-2">Total due today</td>
                            <td class="text-end fw-bold pt-2 fs-5">
                                $<?= number_format($amountDue, 2) ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted small" colspan="2">
                                Then $<?= number_format($discounted, 2) ?>/mo (billed every <?= $periodLabel ?>)
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Confirm panel -->
        <div class="col-md-5">
            <div class="card border-0 shadow-sm sticky-top" style="top:1rem">
                <div class="card-header bg-success text-white fw-semibold">
                    <i class="bi bi-check-circle me-2"></i>Confirm Upgrade
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="fw-semibold mb-1">Upgrading to: <span class="text-success"><?= e($newPlan['name']) ?></span></div>
                        <div class="text-muted small">Billing: <?= e(ucwords(str_replace('_', '-', $billingPeriod))) ?></div>
                        <div class="text-muted small">Amount due: <strong>$<?= number_format($amountDue, 2) ?></strong></div>
                    </div>

                    <?php if (!empty($newPlan['stripe_price_id'])): ?>
                    <!-- Stripe payment form -->
                    <div id="stripePaymentSection">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Card Number</label>
                            <div id="card-element" class="form-control py-2 border"></div>
                        </div>
                        <div id="card-errors" class="text-danger small mb-2" role="alert"></div>
                    </div>
                    <?php endif; ?>

                    <form id="upgradeForm" method="POST" action="/api/plans.php?action=upgrade">
                        <?= csrfField() ?>
                        <input type="hidden" name="plan_id"        value="<?= (int)$newPlan['id'] ?>">
                        <input type="hidden" name="billing_period" value="<?= htmlspecialchars($billingPeriod) ?>">
                        <input type="hidden" name="amount_due"     value="<?= $amountDue ?>">
                        <input type="hidden" name="stripe_token"   id="stripeToken" value="">

                        <button type="submit" class="btn btn-success w-100 mb-2" id="upgradeBtn">
                            <i class="bi bi-check-circle me-2"></i>
                            <?= $amountDue > 0 ? 'Pay $' . htmlspecialchars(number_format($amountDue, 2)) . ' &amp; Upgrade' : 'Confirm Upgrade' ?>
                        </button>
                        <a href="/pages/supplier/plans/index.php" class="btn btn-outline-secondary w-100 btn-sm">
                            Cancel
                        </a>
                    </form>

                    <p class="text-muted small mt-3 mb-0 text-center">
                        <i class="bi bi-lock me-1"></i>Secured by Stripe. Cancel anytime.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
