<?php
/**
 * pages/admin/finance/payout-detail.php — Admin Payout Detail (PR #11)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/payouts.php';
requireAdmin();

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    redirect('/pages/admin/finance/payouts.php');
}

$payout = getPayoutRequest($id);
if (!$payout) {
    redirect('/pages/admin/finance/payouts.php');
}

// Supplier info
$supplier = null;
try {
    $stmt = $db->prepare(
        'SELECT id, email, first_name, last_name, company_name, created_at,
                kyc_status, role
         FROM users WHERE id = ? LIMIT 1'
    );
    $stmt->execute([(int)$payout['supplier_id']]);
    $supplier = $stmt->fetch();
} catch (PDOException $e) { /* ignore */ }

// Total orders & earnings for this supplier
$supplierStats = ['total_orders' => 0, 'total_earnings' => 0.0];
try {
    $stmt = $db->prepare(
        'SELECT COUNT(DISTINCT order_id) AS orders, COALESCE(SUM(net_amount), 0) AS earnings
         FROM commission_logs WHERE supplier_id = ?'
    );
    $stmt->execute([(int)$payout['supplier_id']]);
    $row = $stmt->fetch();
    if ($row) {
        $supplierStats = ['total_orders' => (int)$row['orders'], 'total_earnings' => (float)$row['earnings']];
    }
} catch (PDOException $e) { /* ignore */ }

$balance       = getSupplierBalance((int)$payout['supplier_id']);
$detailDecoded = json_decode($payout['payout_details'] ?? '{}', true) ?: [];

$pageTitle = 'Payout #' . $id . ' — Admin Detail';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-arrow-down-circle text-success me-2"></i>Payout #<?= $id ?></h3>
        <a href="/pages/admin/finance/payouts.php" class="btn btn-outline-secondary btn-sm">← Payout Queue</a>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success"><?= e($_GET['success']) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger"><?= e($_GET['error']) ?></div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- Payout Details -->
        <div class="col-lg-8">

            <!-- Status & Amount Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small">Request Amount</div>
                        <div class="display-6 fw-bold text-success">$<?= number_format((float)$payout['amount'], 2) ?></div>
                        <div class="mt-1"><?= payoutStatusBadge($payout['status']) ?></div>
                    </div>
                    <div class="text-end small text-muted">
                        <div>Via: <?= payoutMethodLabel($payout['payout_method']) ?></div>
                        <div>Requested: <?= $payout['created_at'] ? date('M j, Y g:i A', strtotime($payout['created_at'])) : '—' ?></div>
                        <?php if ($payout['approved_at']): ?>
                        <div>Approved: <?= date('M j, Y g:i A', strtotime($payout['approved_at'])) ?></div>
                        <?php endif; ?>
                        <?php if ($payout['completed_at']): ?>
                        <div>Completed: <?= date('M j, Y g:i A', strtotime($payout['completed_at'])) ?></div>
                        <?php endif; ?>
                        <?php if ($payout['rejected_at']): ?>
                        <div>Rejected: <?= date('M j, Y g:i A', strtotime($payout['rejected_at'])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Account Details (unmasked for admin) -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold">Account Details (Full)</h6>
                </div>
                <div class="card-body">
                    <?php if ($detailDecoded): ?>
                    <table class="table table-sm">
                        <?php foreach ($detailDecoded as $k => $v): ?>
                        <tr>
                            <th class="text-muted" style="width:40%"><?= e(ucwords(str_replace('_', ' ', $k))) ?></th>
                            <td><code><?= e($v) ?></code></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <?php else: ?>
                    <p class="text-muted mb-0">No account details stored.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Earnings Verification -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold">Balance Verification</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="small text-muted">Total Earned</div>
                            <div class="fw-semibold">$<?= number_format($balance['total_earned'], 2) ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="small text-muted">Already Paid</div>
                            <div class="fw-semibold">$<?= number_format($balance['total_paid'], 2) ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="small text-muted">Pending Payouts</div>
                            <div class="fw-semibold">$<?= number_format($balance['pending_payouts'], 2) ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="small text-muted">In Hold</div>
                            <div class="fw-semibold">$<?= number_format($balance['in_hold'], 2) ?></div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                                <span class="fw-semibold">Available Balance</span>
                                <span class="fw-bold text-success fs-5">$<?= number_format($balance['available_balance'], 2) ?></span>
                            </div>
                            <?php $requestedAmt = (float)$payout['amount']; ?>
                            <?php if ($requestedAmt > $balance['available_balance'] + $balance['pending_payouts']): ?>
                            <div class="alert alert-danger mt-2 mb-0 small">
                                ⚠️ Warning: Requested amount exceeds available balance.
                            </div>
                            <?php else: ?>
                            <div class="alert alert-success mt-2 mb-0 small">
                                ✅ Requested amount is within available balance.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($payout['rejection_reason']) || !empty($payout['admin_note'])): ?>
            <div class="card border-0 shadow-sm border-danger border-start border-3 mb-4">
                <div class="card-body">
                    <h6 class="fw-semibold text-danger mb-1">Rejection Reason</h6>
                    <p class="mb-0"><?= e($payout['rejection_reason'] ?: $payout['admin_note']) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($payout['transaction_ref'] || $payout['reference_number']): ?>
            <div class="card border-0 shadow-sm border-success border-start border-3 mb-4">
                <div class="card-body">
                    <h6 class="fw-semibold text-success mb-1">Transaction Reference</h6>
                    <code><?= e($payout['transaction_ref'] ?: $payout['reference_number']) ?></code>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar: Supplier Info + Actions -->
        <div class="col-lg-4">

            <!-- Supplier Info -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold">Supplier</h6>
                </div>
                <div class="card-body">
                    <?php if ($supplier): ?>
                    <p class="mb-1 fw-semibold"><?= e($supplier['company_name'] ?: ($supplier['first_name'] . ' ' . $supplier['last_name'])) ?></p>
                    <p class="mb-1 text-muted small"><?= e($supplier['email']) ?></p>
                    <p class="mb-2 small">
                        KYC: <span class="badge bg-<?= match($supplier['kyc_status'] ?? 'pending') {
                            'verified' => 'success', 'pending' => 'warning', 'rejected' => 'danger', default => 'secondary'
                        } ?>"><?= ucfirst(e($supplier['kyc_status'] ?? 'pending')) ?></span>
                    </p>
                    <p class="mb-1 small text-muted">Member since: <?= $supplier['created_at'] ? date('M Y', strtotime($supplier['created_at'])) : '—' ?></p>
                    <hr>
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted">Total Orders</span>
                        <strong><?= $supplierStats['total_orders'] ?></strong>
                    </div>
                    <div class="d-flex justify-content-between small mt-1">
                        <span class="text-muted">Total Earnings</span>
                        <strong>$<?= number_format($supplierStats['total_earnings'], 2) ?></strong>
                    </div>
                    <?php else: ?>
                    <p class="text-muted mb-0">Supplier #<?= (int)$payout['supplier_id'] ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Admin Actions -->
            <?php if ($payout['status'] === 'pending'): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold">Approve Payout</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="/api/payouts.php?action=admin_approve"
                          data-confirm="Approve this payout?"
                          data-redirect="/pages/admin/finance/payout-detail.php?id=<?= $id ?>&success=Payout+approved">
                        <?= csrfField() ?>
                        <input type="hidden" name="payout_id" value="<?= $id ?>">
                        <div class="mb-3">
                            <label class="form-label small">Transaction Reference (optional)</label>
                            <input type="text" name="transaction_ref" class="form-control form-control-sm"
                                   placeholder="Wire / reference number">
                        </div>
                        <button type="submit" class="btn btn-success w-100">✅ Approve</button>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold">Reject Payout</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="/api/payouts.php?action=admin_reject"
                          data-confirm="Reject this payout?"
                          data-redirect="/pages/admin/finance/payout-detail.php?id=<?= $id ?>&success=Payout+rejected">
                        <?= csrfField() ?>
                        <input type="hidden" name="payout_id" value="<?= $id ?>">
                        <div class="mb-3">
                            <label class="form-label small">Rejection Reason <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control form-control-sm" rows="3" required
                                      placeholder="Explain why this payout is rejected…"></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger w-100">❌ Reject</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($payout['status'] === 'processing'): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold">Mark Completed</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="/api/payouts.php?action=admin_complete"
                          data-confirm="Mark this payout as completed?"
                          data-redirect="/pages/admin/finance/payout-detail.php?id=<?= $id ?>&success=Payout+completed">
                        <?= csrfField() ?>
                        <input type="hidden" name="payout_id" value="<?= $id ?>">
                        <div class="mb-3">
                            <label class="form-label small">Final Transaction Reference</label>
                            <input type="text" name="transaction_ref" class="form-control form-control-sm"
                                   placeholder="Transaction / wire reference">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">💸 Mark Completed</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Admin Notes -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold">Admin Notes (Internal)</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="/api/payouts.php?action=admin_notes">
                        <?= csrfField() ?>
                        <input type="hidden" name="payout_id" value="<?= $id ?>">
                        <textarea name="admin_notes" class="form-control form-control-sm" rows="3"
                                  placeholder="Internal notes visible only to admins…"><?= e($payout['admin_notes'] ?? '') ?></textarea>
                        <button type="submit" class="btn btn-outline-secondary btn-sm mt-2 w-100">Save Notes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Handle forms with data-redirect: submit via fetch and redirect on success
document.querySelectorAll('form[data-redirect]').forEach(form => {
    // Each form has a data-confirm attribute for the confirmation message
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        if (!this.checkValidity()) { this.reportValidity(); return; }
        const confirmMsg = this.dataset.confirm ?? 'Confirm this action?';
        if (!confirm(confirmMsg)) return;

        const fd       = new FormData(this);
        const redirect = this.dataset.redirect;
        try {
            const resp = await fetch(this.action, { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.success) {
                window.location.href = redirect;
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
            }
        } catch (err) {
            // Fallback: submit normally
            this.removeAttribute('data-redirect');
            this.submit();
        }
    });
});
</script>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
