<?php
/**
 * pages/supplier/earnings/history.php — Payout History Page (PR #11)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/payouts.php';
requireRole(['supplier', 'admin', 'super_admin']);

$db         = getDB();
$supplierId = (int)$_SESSION['user_id'];

// Filters
$filterStatus = $_GET['status'] ?? '';
$filterMethod = $_GET['method'] ?? '';
$filterFrom   = $_GET['date_from'] ?? '';
$filterTo     = $_GET['date_to'] ?? '';
$page         = max(1, (int)($_GET['page'] ?? 1));

$filters = [
    'status' => $filterStatus,
    'method' => $filterMethod,
    'from'   => $filterFrom,
    'to'     => $filterTo,
];

$result  = getPayoutRequests($supplierId, $filters, $page, 15);
$payouts = $result['rows'];
$total   = $result['total'];
$pages   = $result['pages'];

// Detail modal param
$detailId = (int)($_GET['detail'] ?? 0);
$detail   = $detailId ? getPayoutRequest($detailId) : null;
// Only allow viewing own details
if ($detail && (int)$detail['supplier_id'] !== $supplierId) {
    $detail = null;
}

$pageTitle = 'Payout History';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-clock-history text-primary me-2"></i>Payout History</h3>
        <div class="d-flex gap-2">
            <a href="/pages/supplier/earnings/withdraw.php" class="btn btn-success btn-sm">
                <i class="bi bi-cash-coin me-1"></i>New Withdrawal
            </a>
            <a href="/pages/supplier/earnings/" class="btn btn-outline-secondary btn-sm">← Earnings</a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-auto">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <?php foreach (['pending','processing','completed','rejected','cancelled'] as $s): ?>
                        <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <select name="method" class="form-select form-select-sm">
                        <option value="">All Methods</option>
                        <option value="bank_transfer" <?= $filterMethod === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                        <option value="paypal"        <?= $filterMethod === 'paypal'        ? 'selected' : '' ?>>PayPal</option>
                        <option value="wise"          <?= $filterMethod === 'wise'          ? 'selected' : '' ?>>Wise</option>
                    </select>
                </div>
                <div class="col-auto">
                    <input type="date" name="date_from" class="form-control form-control-sm"
                           value="<?= e($filterFrom) ?>" placeholder="From">
                </div>
                <div class="col-auto">
                    <input type="date" name="date_to" class="form-control form-control-sm"
                           value="<?= e($filterTo) ?>" placeholder="To">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="?" class="btn btn-outline-secondary btn-sm">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Results summary -->
    <?php if ($total > 0): ?>
    <p class="text-muted small mb-2">Showing <?= count($payouts) ?> of <?= $total ?> requests</p>
    <?php endif; ?>

    <!-- Payout History Table -->
    <div class="card border-0 shadow-sm">
        <?php if (empty($payouts)): ?>
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>No payout requests found.
            <br><a href="/pages/supplier/earnings/withdraw.php" class="btn btn-success btn-sm mt-2">Make First Withdrawal</a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Request ID</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Transaction Ref</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($payouts as $p): ?>
                <tr>
                    <td><span class="fw-semibold">#<?= (int)$p['id'] ?></span></td>
                    <td><small><?= $p['created_at'] ? date('M j, Y', strtotime($p['created_at'])) : '—' ?></small></td>
                    <td class="fw-semibold">$<?= number_format((float)$p['amount'], 2) ?></td>
                    <td><?= payoutMethodLabel($p['payout_method']) ?></td>
                    <td><?= payoutStatusBadge($p['status']) ?></td>
                    <td>
                        <small class="text-muted">
                            <?= e($p['transaction_ref'] ?: ($p['reference_number'] ?: '—')) ?>
                        </small>
                    </td>
                    <td>
                        <a href="?detail=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i> Details
                        </a>
                        <?php if ($p['status'] === 'pending'): ?>
                        <form method="POST" action="/api/payouts.php?action=cancel" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="payout_id" value="<?= (int)$p['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Cancel this payout request?')">
                                Cancel
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
        <div class="card-footer bg-light d-flex justify-content-center">
            <nav><ul class="pagination pagination-sm mb-0">
                <?php for ($i = 1; $i <= min($pages, 10); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                        <?= $i ?>
                    </a>
                </li>
                <?php endfor; ?>
            </ul></nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Detail Modal -->
<?php if ($detail): ?>
<div class="modal fade show d-block" id="detailModal" tabindex="-1" style="background:rgba(0,0,0,.5);">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payout Request #<?= (int)$detail['id'] ?></h5>
                <a href="?" class="btn-close"></a>
            </div>
            <div class="modal-body">
                <?php
                $detailDecoded = json_decode($detail['payout_details'] ?? '{}', true) ?: [];
                $maskedDetails = maskPayoutDetails($detailDecoded);
                ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr><th>Amount</th><td class="fw-bold text-success">$<?= number_format((float)$detail['amount'], 2) ?></td></tr>
                            <tr><th>Method</th><td><?= payoutMethodLabel($detail['payout_method']) ?></td></tr>
                            <tr><th>Status</th><td><?= payoutStatusBadge($detail['status']) ?></td></tr>
                            <tr><th>Requested</th><td><?= $detail['created_at'] ? date('M j, Y g:i A', strtotime($detail['created_at'])) : '—' ?></td></tr>
                            <?php if ($detail['approved_at']): ?>
                            <tr><th>Approved</th><td><?= date('M j, Y g:i A', strtotime($detail['approved_at'])) ?></td></tr>
                            <?php endif; ?>
                            <?php if ($detail['completed_at']): ?>
                            <tr><th>Completed</th><td><?= date('M j, Y g:i A', strtotime($detail['completed_at'])) ?></td></tr>
                            <?php endif; ?>
                            <?php if ($detail['rejected_at']): ?>
                            <tr><th>Rejected</th><td><?= date('M j, Y g:i A', strtotime($detail['rejected_at'])) ?></td></tr>
                            <?php endif; ?>
                            <?php if ($detail['transaction_ref'] || $detail['reference_number']): ?>
                            <tr><th>Transaction Ref</th><td><?= e($detail['transaction_ref'] ?: $detail['reference_number']) ?></td></tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-semibold">Account Details</h6>
                        <table class="table table-sm">
                        <?php foreach ($maskedDetails as $k => $v): ?>
                            <tr>
                                <th class="text-muted small"><?= e(ucwords(str_replace('_', ' ', $k))) ?></th>
                                <td><?= e($v) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </table>
                    </div>
                    <?php if (!empty($detail['rejection_reason']) || !empty($detail['admin_note'])): ?>
                    <div class="col-12">
                        <div class="alert alert-danger">
                            <strong>Rejection Reason:</strong>
                            <?= e($detail['rejection_reason'] ?: $detail['admin_note']) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer">
                <a href="?" class="btn btn-secondary">Close</a>
                <?php if ($detail['status'] === 'pending'): ?>
                <form method="POST" action="/api/payouts.php?action=cancel">
                    <?= csrfField() ?>
                    <input type="hidden" name="payout_id" value="<?= (int)$detail['id'] ?>">
                    <button type="submit" class="btn btn-danger"
                            onclick="return confirm('Cancel this payout request?')">Cancel Request</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
