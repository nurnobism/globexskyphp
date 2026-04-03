<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireRole(['supplier', 'admin', 'super_admin']);

$db         = getDB();
$supplierId = $_SESSION['user_id'];

// Compute available balance
$totalSales      = 0.0;
$totalCommission = 0.0;
$totalPaid       = 0.0;
try {
    $sStmt = $db->prepare('SELECT COALESCE(SUM(amount),0) FROM supplier_earnings WHERE supplier_id = ? AND type = "sale"');
    $sStmt->execute([$supplierId]);
    $totalSales = (float)$sStmt->fetchColumn();

    $cStmt = $db->prepare('SELECT COALESCE(SUM(amount),0) FROM supplier_earnings WHERE supplier_id = ? AND type = "commission_deduct"');
    $cStmt->execute([$supplierId]);
    $totalCommission = (float)$cStmt->fetchColumn();

    $pStmt = $db->prepare('SELECT COALESCE(SUM(amount),0) FROM supplier_earnings WHERE supplier_id = ? AND type = "payout"');
    $pStmt->execute([$supplierId]);
    $totalPaid = (float)$pStmt->fetchColumn();
} catch (PDOException $e) {
    // Fallback
    try {
        $oStmt = $db->prepare('SELECT COALESCE(SUM(oi.subtotal),0)
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            JOIN products p ON p.id = oi.product_id
            WHERE p.supplier_id = ? AND o.status IN ("completed","delivered","shipped")');
        $oStmt->execute([$supplierId]);
        $totalSales = (float)$oStmt->fetchColumn();
    } catch (PDOException $e2) { /* ignore */ }
}

$pendingPayout = 0.0;
try {
    $ppStmt = $db->prepare('SELECT COALESCE(SUM(amount),0) FROM payout_requests WHERE supplier_id = ? AND status IN ("pending","processing")');
    $ppStmt->execute([$supplierId]);
    $pendingPayout = (float)$ppStmt->fetchColumn();
} catch (PDOException $e) { /* ignore */ }

$availableBalance = max(0, round($totalSales - $totalCommission - $totalPaid - $pendingPayout, 2));

// Payout history
$payouts = [];
try {
    $phStmt = $db->prepare('SELECT * FROM payout_requests WHERE supplier_id = ? ORDER BY created_at DESC');
    $phStmt->execute([$supplierId]);
    $payouts = $phStmt->fetchAll();
} catch (PDOException $e) { /* ignore */ }

$pageTitle = 'Payouts';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-arrow-down-circle text-success me-2"></i>Payouts</h3>
        <a href="/pages/supplier/earnings.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-graph-up me-1"></i> View Earnings
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
        <!-- Payout Request Form -->
        <div class="col-md-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-success text-white fw-semibold">
                    <i class="bi bi-cash me-2"></i>Request Withdrawal
                </div>
                <div class="card-body">
                    <div class="alert alert-info py-2 small mb-3">
                        Available Balance: <strong>$<?= number_format($availableBalance, 2) ?></strong>
                        <?php if ($pendingPayout > 0): ?>
                        <br>Pending: <strong>$<?= number_format($pendingPayout, 2) ?></strong>
                        <?php endif; ?>
                    </div>

                    <?php if ($availableBalance < 50): ?>
                    <div class="alert alert-warning small">
                        Minimum withdrawal is $50. Keep selling to reach the threshold!
                    </div>
                    <?php else: ?>
                    <form method="POST" action="/api/payouts.php?action=request" id="payoutForm">
                        <?= csrfField() ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Amount *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="amount" class="form-control" required
                                       min="50" max="<?= $availableBalance ?>" step="0.01"
                                       placeholder="Min $50">
                            </div>
                            <small class="text-muted">Max: $<?= number_format($availableBalance, 2) ?></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Payout Method *</label>
                            <select name="payout_method" class="form-select" id="methodSelect" required>
                                <option value="">Select method...</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="paypal">PayPal</option>
                                <option value="wise">Wise</option>
                            </select>
                        </div>

                        <!-- Bank Transfer Details -->
                        <div id="bankFields" class="d-none">
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Account Name</label>
                                <input type="text" name="payout_details[account_name]" class="form-control form-control-sm" placeholder="Name on account">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Account Number</label>
                                <input type="text" name="payout_details[account_number]" class="form-control form-control-sm">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Routing Number</label>
                                <input type="text" name="payout_details[routing_number]" class="form-control form-control-sm">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">SWIFT / BIC</label>
                                <input type="text" name="payout_details[swift]" class="form-control form-control-sm">
                            </div>
                        </div>

                        <!-- PayPal / Wise Details -->
                        <div id="emailFields" class="d-none">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Account Email</label>
                                <input type="email" name="payout_details[email]" class="form-control form-control-sm">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-send me-1"></i> Submit Withdrawal Request
                        </button>
                    </form>
                    <?php endif; ?>

                    <div class="mt-3 small text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Payouts are processed within 3–5 business days. Minimum: $50.
                    </div>
                </div>
            </div>
        </div>

        <!-- Payout History -->
        <div class="col-md-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light fw-semibold">Payout History</div>
                <?php if (empty($payouts)): ?>
                <div class="card-body text-center text-muted py-4">No payout requests yet.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($payouts as $p): ?>
                        <tr>
                            <td><?= formatDate($p['created_at']) ?></td>
                            <td class="fw-semibold">$<?= number_format((float)$p['amount'], 2) ?></td>
                            <td>
                                <?= match($p['payout_method']){'bank_transfer'=>'🏦 Bank','paypal'=>'💙 PayPal','wise'=>'💚 Wise',default=>e($p['payout_method'])} ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= match($p['status']){'pending'=>'warning','processing'=>'info','completed'=>'success','rejected'=>'danger',default=>'secondary'} ?>">
                                    <?= ucfirst($p['status']) ?>
                                </span>
                                <?php if ($p['status'] === 'rejected' && $p['admin_note']): ?>
                                <br><small class="text-muted"><?= e($p['admin_note']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><small><?= e($p['reference_number'] ?? '—') ?></small></td>
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

<script>
document.getElementById('methodSelect')?.addEventListener('change', function() {
    const val = this.value;
    document.getElementById('bankFields').classList.toggle('d-none', val !== 'bank_transfer');
    document.getElementById('emailFields').classList.toggle('d-none', val !== 'paypal' && val !== 'wise');
});

document.getElementById('payoutForm')?.addEventListener('submit', function(e) {
    const amount = parseFloat(document.querySelector('[name="amount"]').value);
    const maxBalance = <?= $availableBalance ?>;
    if (amount > maxBalance) {
        e.preventDefault();
        alert('Amount cannot exceed available balance of $' + maxBalance.toFixed(2));
    }
});
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
