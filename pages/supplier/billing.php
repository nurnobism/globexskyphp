<?php
require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/plan_limits.php';
requireRole(['supplier', 'admin', 'super_admin']);

$db          = getDB();
$supplierId  = $_SESSION['user_id'];
$currentPlan = getSupplierPlan($supplierId);

// Current subscription details
$subscription = null;
try {
    $stmt = $db->prepare('SELECT ps.*, sp.name AS plan_name, sp.price AS plan_price
        FROM plan_subscriptions ps JOIN supplier_plans sp ON sp.id = ps.plan_id
        WHERE ps.supplier_id = ? AND ps.status IN ("active","past_due","trialing")
        ORDER BY ps.created_at DESC LIMIT 1');
    $stmt->execute([$supplierId]);
    $subscription = $stmt->fetch() ?: null;
} catch (PDOException $e) { /* ignore */ }

// Invoices
$invoices = [];
try {
    $iStmt = $db->prepare('SELECT * FROM invoices WHERE supplier_id = ? ORDER BY created_at DESC LIMIT 20');
    $iStmt->execute([$supplierId]);
    $invoices = $iStmt->fetchAll();
} catch (PDOException $e) { /* ignore */ }

$pageTitle = 'Billing & Subscription';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-receipt text-primary me-2"></i>Billing & Subscription</h3>
        <a href="/pages/supplier/plans.php" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-arrow-up-circle me-1"></i> View Plans
        </a>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= e($_GET['success']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= e($_GET['error']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Current Plan -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-light fw-semibold">Current Plan</div>
                <div class="card-body">
                    <h5 class="fw-bold mb-1"><?= e($currentPlan['name'] ?? 'Free') ?></h5>
                    <?php if ((float)($currentPlan['price'] ?? 0) > 0): ?>
                    <p class="text-muted mb-2">$<?= number_format((float)$currentPlan['price'], 2) ?>/month</p>
                    <?php else: ?>
                    <p class="text-muted mb-2">Free</p>
                    <?php endif; ?>
                    <?php if ($subscription): ?>
                    <div class="small text-muted">
                        <div class="mb-1">
                            <i class="bi bi-calendar me-1"></i>
                            Status: <span class="badge bg-<?= $subscription['status'] === 'active' ? 'success' : 'warning' ?>">
                                <?= ucfirst($subscription['status']) ?>
                            </span>
                        </div>
                        <?php if (!empty($subscription['current_period_end'])): ?>
                        <div class="mb-1">
                            <i class="bi bi-arrow-repeat me-1"></i>
                            Next billing: <?= formatDate($subscription['current_period_end']) ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($subscription['cancel_at_period_end']): ?>
                        <div class="text-warning">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Cancels at end of period
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div class="mt-3 d-flex gap-2">
                        <a href="/pages/supplier/plans.php" class="btn btn-primary btn-sm">
                            <i class="bi bi-arrow-up-circle me-1"></i> Upgrade
                        </a>
                        <?php if ($subscription && !$subscription['cancel_at_period_end'] && $subscription['status'] === 'active'): ?>
                        <form method="POST" action="/api/plans.php?action=cancel"
                              onsubmit="return confirm('Cancel your subscription?')">
                            <?= csrfField() ?>
                            <button type="submit" class="btn btn-outline-danger btn-sm">Cancel Plan</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Method -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-light fw-semibold">Payment Method</div>
                <div class="card-body">
                    <?php if ($subscription && !empty($subscription['stripe_subscription_id'])): ?>
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-credit-card-2-front fs-3 text-primary me-3"></i>
                        <div>
                            <div class="fw-semibold">Card on file via Stripe</div>
                            <small class="text-muted">Managed securely by Stripe</small>
                        </div>
                    </div>
                    <a href="https://billing.stripe.com/p/login" target="_blank" rel="noopener"
                       class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Manage on Stripe
                    </a>
                    <?php else: ?>
                    <p class="text-muted small">No payment method on file. Subscribe to a paid plan to add one.</p>
                    <a href="/pages/supplier/plans.php" class="btn btn-outline-primary btn-sm">View Plans</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Invoice History -->
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light fw-semibold">Invoice History</div>
                <?php if (empty($invoices)): ?>
                <div class="card-body text-center text-muted py-4">No invoices yet.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice #</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td><strong><?= e($inv['invoice_number']) ?></strong></td>
                            <td><span class="badge bg-secondary"><?= e(ucfirst($inv['type'])) ?></span></td>
                            <td>$<?= number_format((float)$inv['total'], 2) ?></td>
                            <td>
                                <span class="badge bg-<?= match($inv['status']){'paid'=>'success','overdue'=>'danger','cancelled'=>'secondary',default=>'warning'} ?>">
                                    <?= ucfirst($inv['status']) ?>
                                </span>
                            </td>
                            <td><?= formatDate($inv['created_at']) ?></td>
                            <td>
                                <?php if (!empty($inv['pdf_url'])): ?>
                                <a href="<?= e($inv['pdf_url']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-download"></i> PDF
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
