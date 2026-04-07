<?php
/**
 * pages/supplier/plans/billing.php — Billing & Invoices (PR #9)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/plans.php';
requireRole(['supplier', 'admin', 'super_admin']);

$db          = getDB();
$supplierId  = $_SESSION['user_id'];
$currentPlan = getSupplierActivePlan($supplierId);

// Current subscription details
$subscription = null;
try {
    $stmt = $db->prepare(
        'SELECT ps.*, sp.name AS plan_name, sp.price AS plan_price, sp.slug AS plan_slug
         FROM plan_subscriptions ps
         JOIN supplier_plans sp ON sp.id = ps.plan_id
         WHERE ps.supplier_id = ? AND ps.status IN ("active","past_due","trialing")
         ORDER BY ps.created_at DESC LIMIT 1'
    );
    $stmt->execute([$supplierId]);
    $subscription = $stmt->fetch() ?: null;
} catch (PDOException $e) { /* ignore */ }

// Billing history from plan_invoices
$invoices = getPlanBillingHistory($supplierId, 20);

// Fallback to generic invoices table
if (empty($invoices)) {
    try {
        $iStmt = $db->prepare('SELECT * FROM invoices WHERE supplier_id = ? ORDER BY created_at DESC LIMIT 20');
        $iStmt->execute([$supplierId]);
        $invoices = $iStmt->fetchAll();
    } catch (PDOException $e) { /* ignore */ }
}

$pageTitle = 'Billing & Invoices';
include __DIR__ . '/../../../includes/header.php';

$statusBadge = function (string $status): string {
    return match ($status) {
        'active'   => '<span class="badge bg-success">Active</span>',
        'trialing' => '<span class="badge bg-info">Trial</span>',
        'past_due' => '<span class="badge bg-warning text-dark">Past Due</span>',
        'cancelled'=> '<span class="badge bg-secondary">Cancelled</span>',
        default    => '<span class="badge bg-light text-dark">' . htmlspecialchars(ucfirst($status)) . '</span>',
    };
};

$invoiceBadge = function (string $status): string {
    return match ($status) {
        'paid'    => '<span class="badge bg-success">Paid</span>',
        'pending' => '<span class="badge bg-warning text-dark">Pending</span>',
        'failed'  => '<span class="badge bg-danger">Failed</span>',
        'refunded'=> '<span class="badge bg-info">Refunded</span>',
        default   => '<span class="badge bg-light text-dark">' . htmlspecialchars(ucfirst($status)) . '</span>',
    };
};
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-receipt text-primary me-2"></i>Billing &amp; Invoices</h3>
        <a href="/pages/supplier/plans/index.php" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-arrow-up-circle me-1"></i> View Plans
        </a>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i><?= e($_GET['success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle me-2"></i><?= e($_GET['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Current Plan Card -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-light fw-semibold">
                    <i class="bi bi-layers me-2 text-primary"></i>Current Plan
                </div>
                <div class="card-body">
                    <h5 class="fw-bold mb-1"><?= e($currentPlan['name'] ?? 'Free') ?></h5>
                    <?php if ((float)($currentPlan['price'] ?? 0) > 0): ?>
                    <p class="text-muted mb-2">$<?= number_format((float)$currentPlan['price'], 2) ?>/month</p>
                    <?php else: ?>
                    <p class="text-muted mb-2">Free plan</p>
                    <?php endif; ?>

                    <?php if ($subscription): ?>
                    <ul class="list-unstyled small text-muted mb-3">
                        <li class="mb-1">
                            <i class="bi bi-circle-fill me-1" style="font-size:.5rem"></i>
                            Status: <?= $statusBadge($subscription['status']) ?>
                        </li>
                        <?php if (!empty($subscription['billing_period'])): ?>
                        <li class="mb-1">
                            <i class="bi bi-calendar-range me-1"></i>
                            Billing: <?= ucwords(str_replace('_', '-', $subscription['billing_period'])) ?>
                        </li>
                        <?php endif; ?>
                        <?php if (!empty($subscription['next_billing_date'])): ?>
                        <li class="mb-1">
                            <i class="bi bi-arrow-repeat me-1"></i>
                            <?php $nbTs = strtotime($subscription['next_billing_date']); ?>
                            Next billing: <strong><?= htmlspecialchars($nbTs ? date('M j, Y', $nbTs) : '—') ?></strong>
                        </li>
                        <?php elseif (!empty($subscription['current_period_end'])): ?>
                        <li class="mb-1">
                            <i class="bi bi-arrow-repeat me-1"></i>
                            <?php $peTs = strtotime($subscription['current_period_end']); ?>
                            Period ends: <strong><?= htmlspecialchars($peTs ? date('M j, Y', $peTs) : '—') ?></strong>
                        </li>
                        <?php endif; ?>
                        <?php if ($subscription['cancel_at_period_end']): ?>
                        <li class="mt-2">
                            <span class="badge bg-warning text-dark">
                                <i class="bi bi-exclamation-triangle me-1"></i>Cancels at period end
                            </span>
                        </li>
                        <?php endif; ?>
                    </ul>
                    <?php else: ?>
                    <p class="text-muted small mb-3">No active subscription. You are on the free plan.</p>
                    <?php endif; ?>

                    <a href="/pages/supplier/plans/index.php" class="btn btn-primary btn-sm me-2">
                        <i class="bi bi-arrow-up-circle me-1"></i> Upgrade Plan
                    </a>
                    <?php if ($subscription && !$subscription['cancel_at_period_end'] && (float)($currentPlan['price'] ?? 0) > 0): ?>
                    <button type="button" class="btn btn-outline-danger btn-sm"
                            data-bs-toggle="modal" data-bs-target="#cancelModal">
                        <i class="bi bi-x-circle me-1"></i> Cancel Subscription
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Payment Method Card -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-light fw-semibold">
                    <i class="bi bi-credit-card me-2 text-primary"></i>Payment Method
                </div>
                <div class="card-body">
                    <?php
                    $cardLast4 = null;
                    $cardBrand = null;
                    if ($subscription && !empty($subscription['stripe_subscription_id'])) {
                        $cardLast4 = '••••';
                        $cardBrand = 'Card';
                    }
                    if ($cardLast4): ?>
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-credit-card-2-front fs-3 text-primary me-3"></i>
                        <div>
                            <div class="fw-semibold"><?= e($cardBrand) ?></div>
                            <div class="text-muted small">ending in <?= e($cardLast4) ?></div>
                        </div>
                    </div>
                    <a href="/pages/supplier/plans/upgrade.php?update_payment=1" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-pencil me-1"></i> Update Payment Method
                    </a>
                    <?php else: ?>
                    <p class="text-muted small mb-3">No payment method on file.</p>
                    <?php if ($subscription && (float)($currentPlan['price'] ?? 0) > 0): ?>
                    <a href="/pages/supplier/plans/upgrade.php?plan=<?= urlencode($currentPlan['slug'] ?? 'pro') ?>"
                       class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-circle me-1"></i> Add Payment Method
                    </a>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Invoice History -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-light fw-semibold">
            <i class="bi bi-file-earmark-text me-2 text-primary"></i>Invoice History
        </div>
        <?php if (empty($invoices)): ?>
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            No invoices yet. Invoices will appear here after your first billing cycle.
        </div>
        <?php else: ?>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Date</th>
                            <th>Description</th>
                            <th>Billing Period</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">PDF</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td class="ps-3 small text-muted">
                                <?= htmlspecialchars(isset($inv['created_at']) ? date('M j, Y', strtotime($inv['created_at'])) : '—') ?>
                            </td>
                            <td class="small">
                                <?= e($inv['description'] ?? ($inv['plan_name'] ?? 'Plan subscription')) ?>
                            </td>
                            <td class="small text-muted">
                                <?= e(ucwords(str_replace('_', '-', $inv['billing_period'] ?? 'monthly'))) ?>
                            </td>
                            <td class="text-end fw-semibold">
                                $<?= number_format((float)($inv['amount'] ?? 0), 2) ?>
                            </td>
                            <td class="text-center">
                                <?= $invoiceBadge($inv['status'] ?? 'paid') ?>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($inv['pdf_url'])): ?>
                                <a href="<?= e($inv['pdf_url']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm py-0 px-2">
                                    <i class="bi bi-download"></i>
                                </a>
                                <?php else: ?>
                                <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Cancel Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Cancel Subscription</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel your <strong><?= e($currentPlan['name'] ?? '') ?></strong> subscription?</p>
                <p class="text-muted small mb-0">
                    Your plan access continues until the end of the current billing period.
                    After that, you will revert to the Free plan (10 products, basic features).
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Subscription</button>
                <form method="POST" action="/api/plans.php?action=cancel" class="d-inline">
                    <?= csrfField() ?>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-circle me-1"></i> Cancel Subscription
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
