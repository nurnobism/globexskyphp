<?php
/**
 * pages/supplier/billing/index.php — Supplier Billing Dashboard (PR #9)
 *
 * Shows:
 *  - Current plan info (name, status, next billing, amount)
 *  - Usage meters with progress bars
 *  - Billing history table
 *  - Payment method
 *  - Actions: Upgrade, Cancel, Download Invoice
 */

require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/plans.php';
requireRole(['supplier', 'admin', 'super_admin']);

$supplierId  = (int)$_SESSION['user_id'];
$currentPlan = getCurrentPlan($supplierId);
$usage       = getPlanUsage($supplierId);
$expiry      = getPlanExpiry($supplierId);
$active      = isPlanActive($supplierId);
$invoices    = getPlanInvoices($supplierId, 10);

// Current subscription details
$subscription = null;
try {
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT ps.*, sp.name AS plan_name, sp.price_monthly AS plan_price
         FROM plan_subscriptions ps
         JOIN supplier_plans sp ON sp.id = ps.plan_id
         WHERE ps.supplier_id = ? AND ps.status IN ("active","trialing","past_due")
         ORDER BY ps.created_at DESC LIMIT 1'
    );
    $stmt->execute([$supplierId]);
    $subscription = $stmt->fetch() ?: null;
} catch (PDOException $e) { /* ignore */ }

$pageTitle = 'Billing & Subscription';
include __DIR__ . '/../../../includes/header.php';

function usagePercent(int $used, int $max): int {
    if ($max < 0) return 0;
    if ($max === 0) return 0;
    return min(100, (int)round($used / $max * 100));
}
function usageColor(int $pct): string {
    if ($pct >= 90) return 'danger';
    if ($pct >= 75) return 'warning';
    return 'success';
}
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0"><i class="bi bi-credit-card me-2"></i>Billing &amp; Subscription</h2>
        <a href="/pages/supplier/plans.php" class="btn btn-primary">
            <i class="bi bi-arrow-up-circle me-1"></i>Upgrade Plan
        </a>
    </div>

    <div class="row g-4">
        <!-- Left: Plan Info + Usage -->
        <div class="col-lg-8">
            <!-- Current Plan Card -->
            <div class="card mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-star-fill me-2 text-primary"></i>Current Plan</h5>
                    <?php
                    $statusBadge = $active ? 'bg-success' : 'bg-danger';
                    $statusLabel = $active ? 'Active' : 'Expired';
                    if ($subscription && ($subscription['cancel_at_period_end'] ?? 0)) {
                        $statusBadge = 'bg-warning text-dark';
                        $statusLabel = 'Cancelling';
                    }
                    ?>
                    <span class="badge <?= $statusBadge ?> fs-6"><?= $statusLabel ?></span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="border rounded p-3 text-center">
                                <div class="text-muted small mb-1">Plan</div>
                                <div class="fs-4 fw-bold"><?= htmlspecialchars($currentPlan['name'] ?? 'Free') ?></div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="border rounded p-3 text-center">
                                <div class="text-muted small mb-1">Monthly Price</div>
                                <div class="fs-4 fw-bold">
                                    $<?= number_format((float)($currentPlan['price_monthly'] ?? $currentPlan['price'] ?? 0), 0) ?>/mo
                                </div>
                            </div>
                        </div>
                        <?php if ($subscription): ?>
                        <div class="col-sm-6">
                            <div class="border rounded p-3 text-center">
                                <div class="text-muted small mb-1">Billing Cycle</div>
                                <div class="fw-semibold text-capitalize"><?= htmlspecialchars($subscription['duration'] ?? 'monthly') ?></div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="border rounded p-3 text-center">
                                <div class="text-muted small mb-1">
                                    <?= ($subscription['cancel_at_period_end'] ?? 0) ? 'Cancels On' : 'Next Billing' ?>
                                </div>
                                <div class="fw-semibold">
                                    <?= $expiry ? htmlspecialchars(date('M j, Y', strtotime($expiry))) : 'N/A' ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($subscription && ($subscription['cancel_at_period_end'] ?? 0)): ?>
                    <div class="alert alert-warning mt-3 mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Your subscription will be cancelled on
                        <strong><?= $expiry ? htmlspecialchars(date('M j, Y', strtotime($expiry))) : 'end of billing period' ?></strong>
                        and you will be moved to the Free plan.
                    </div>
                    <?php endif; ?>
                </div>
                <?php if ($subscription && !($subscription['cancel_at_period_end'] ?? 0) && ($currentPlan['slug'] ?? 'free') !== 'free'): ?>
                <div class="card-footer d-flex gap-2">
                    <a href="/pages/supplier/plans.php" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-arrow-up-circle me-1"></i>Change Plan
                    </a>
                    <button class="btn btn-outline-danger btn-sm" id="cancelSubBtn">
                        <i class="bi bi-x-circle me-1"></i>Cancel Subscription
                    </button>
                </div>
                <?php elseif (($currentPlan['slug'] ?? 'free') === 'free'): ?>
                <div class="card-footer">
                    <a href="/pages/supplier/plans.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-star me-1"></i>Upgrade to Pro
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Usage Meters -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-speedometer2 me-2"></i>Usage</h5>
                </div>
                <div class="card-body">
                    <?php
                    $meters = [
                        ['label' => 'Products',            'used' => $usage['used_products'],           'max' => $usage['max_products'],           'icon' => 'bi-box-seam'],
                        ['label' => 'Shipping Templates',  'used' => $usage['used_shipping_templates'], 'max' => $usage['max_shipping_templates'],  'icon' => 'bi-truck'],
                        ['label' => 'Dropship Imports/mo', 'used' => $usage['used_dropship_imports'],   'max' => $usage['max_dropship_imports'],    'icon' => 'bi-arrow-left-right'],
                        ['label' => 'Featured Listings/mo','used' => $usage['used_featured_listings'],  'max' => $usage['max_featured_listings'],   'icon' => 'bi-star'],
                        ['label' => 'Livestreams/week',    'used' => $usage['used_livestreams'],        'max' => $usage['max_livestreams'],         'icon' => 'bi-camera-video'],
                    ];
                    foreach ($meters as $m):
                        $max  = (int)$m['max'];
                        $used = (int)$m['used'];
                        if ($max === 0) continue;
                        $pct   = usagePercent($used, $max);
                        $color = usageColor($pct);
                    ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-semibold">
                                <i class="bi <?= $m['icon'] ?> me-2 text-primary"></i>
                                <?= htmlspecialchars($m['label']) ?>
                            </span>
                            <span class="text-muted small">
                                <?= number_format($used) ?> / <?= $max < 0 ? '<span class="text-success fw-bold">Unlimited</span>' : number_format($max) ?>
                            </span>
                        </div>
                        <div class="progress" style="height:10px">
                            <div class="progress-bar bg-<?= $color ?>"
                                 role="progressbar"
                                 style="width:<?= $max < 0 ? 5 : $pct ?>%"
                                 aria-valuenow="<?= $used ?>"
                                 aria-valuemin="0"
                                 aria-valuemax="<?= $max < 0 ? $used : $max ?>">
                            </div>
                        </div>
                        <?php if ($pct >= 90 && $max > 0): ?>
                        <small class="text-danger">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            <?= htmlspecialchars(getUpgradePrompt($supplierId, 'max_' . strtolower(str_replace(['/mo', '/week', ' '], ['', '', '_'], $m['label'])))) ?>
                        </small>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Billing History -->
            <div class="card">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-receipt me-2"></i>Billing History</h5>
                    <a href="/pages/supplier/billing/invoices.php" class="btn btn-outline-secondary btn-sm">View All</a>
                </div>
                <?php if ($invoices): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice #</th>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td class="font-monospace small"><?= htmlspecialchars($inv['invoice_number'] ?? '') ?></td>
                                <td><?= $inv['created_at'] ? htmlspecialchars(date('M j, Y', strtotime($inv['created_at']))) : '—' ?></td>
                                <td><?= htmlspecialchars($inv['description'] ?? $inv['plan_name'] ?? 'Plan subscription') ?></td>
                                <td class="fw-semibold">$<?= number_format((float)($inv['amount'] ?? 0), 2) ?></td>
                                <td>
                                    <?php
                                    $sBadge = match($inv['status'] ?? '') {
                                        'paid'     => '<span class="badge bg-success">Paid</span>',
                                        'pending'  => '<span class="badge bg-warning text-dark">Pending</span>',
                                        'failed'   => '<span class="badge bg-danger">Failed</span>',
                                        'refunded' => '<span class="badge bg-secondary">Refunded</span>',
                                        default    => '<span class="badge bg-light text-dark">—</span>',
                                    };
                                    echo $sBadge;
                                    ?>
                                </td>
                                <td>
                                    <a href="/pages/supplier/billing/invoices.php?id=<?= (int)($inv['id'] ?? 0) ?>"
                                       class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-download me-1"></i>PDF
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="card-body text-center text-muted py-4">
                    <i class="bi bi-receipt fs-1 mb-2 d-block"></i>
                    No billing history yet.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Payment Method + Quick Actions -->
        <div class="col-lg-4">
            <!-- Payment Method -->
            <?php if ($subscription && $subscription['stripe_customer_id']): ?>
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-credit-card me-2"></i>Payment Method</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-credit-card-2-front fs-2 text-primary"></i>
                        <div>
                            <div class="fw-semibold">Card on file</div>
                            <div class="text-muted small">Managed via Stripe</div>
                        </div>
                    </div>
                    <a href="/pages/supplier/plan-upgrade.php?mode=update_payment"
                       class="btn btn-outline-primary btn-sm w-100 mt-3">
                        <i class="bi bi-pencil me-1"></i>Update Payment Method
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Commission Discount -->
            <?php $disc = (float)($currentPlan['commission_discount'] ?? 0); if ($disc > 0): ?>
            <div class="card mb-4 border-success">
                <div class="card-body text-center">
                    <i class="bi bi-percent fs-1 text-success"></i>
                    <h4 class="fw-bold text-success mt-2"><?= $disc ?>% OFF</h4>
                    <p class="text-muted mb-0">Commission discount on all your sales orders</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Links -->
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-lightning me-2"></i>Quick Actions</h6>
                </div>
                <div class="list-group list-group-flush">
                    <a href="/pages/supplier/plans.php"
                       class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                        <i class="bi bi-arrow-up-circle text-primary"></i> Change Plan
                    </a>
                    <a href="/pages/supplier/billing/invoices.php"
                       class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                        <i class="bi bi-file-text text-secondary"></i> All Invoices
                    </a>
                    <a href="/pages/supplier/earnings.php"
                       class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                        <i class="bi bi-wallet2 text-success"></i> Earnings
                    </a>
                    <a href="/pages/supplier/payouts.php"
                       class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                        <i class="bi bi-bank text-info"></i> Payouts
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Subscription Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-x-circle me-2"></i>Cancel Subscription</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel your <strong><?= htmlspecialchars($currentPlan['name'] ?? '') ?></strong> subscription?</p>
                <ul class="text-muted">
                    <li>Your plan stays active until <strong><?= $expiry ? htmlspecialchars(date('M j, Y', strtotime($expiry))) : 'end of billing period' ?></strong></li>
                    <li>After that, you'll be moved to the <strong>Free</strong> plan</li>
                    <li>Products exceeding Free plan limits will become inactive</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Subscription</button>
                <form method="POST" action="/api/plans.php">
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                    <button type="submit" class="btn btn-danger">Cancel Subscription</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const cancelBtn = document.getElementById('cancelSubBtn');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            new bootstrap.Modal(document.getElementById('cancelModal')).show();
        });
    }
})();
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
