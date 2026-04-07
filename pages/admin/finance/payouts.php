<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();

$db   = getDB();
$page = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;
$status = $_GET['status'] ?? '';

$where  = $status ? 'WHERE pr.status = ?' : '';
$params = $status ? [$status] : [];

$payouts = [];
$total   = 0;
try {
    $cStmt = $db->prepare("SELECT COUNT(*) FROM payout_requests pr $where");
    $cStmt->execute($params);
    $total = (int)$cStmt->fetchColumn();

    $stmt = $db->prepare("SELECT pr.*, u.email, u.company_name
        FROM payout_requests pr LEFT JOIN users u ON u.id = pr.supplier_id
        $where ORDER BY pr.created_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $payouts = $stmt->fetchAll();
} catch (PDOException $e) { /* ignore */ }

$pages = max(1, (int)ceil($total / $limit));

$pageTitle = 'Payout Requests';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-arrow-down-circle text-success me-2"></i>Payout Requests</h3>
        <a href="/pages/admin/finance/index.php" class="btn btn-outline-secondary btn-sm">← Finance</a>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success"><?= e($_GET['success']) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger"><?= e($_GET['error']) ?></div>
    <?php endif; ?>

    <!-- Status Filter Tabs -->
    <ul class="nav nav-pills mb-3">
        <?php foreach ([''=>'All', 'pending'=>'Pending', 'processing'=>'Processing', 'completed'=>'Completed', 'rejected'=>'Rejected'] as $s => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $status === $s ? 'active' : '' ?>" href="?status=<?= $s ?>">
                <?= $label ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <div class="card border-0 shadow-sm">
        <?php if (empty($payouts)): ?>
        <div class="card-body text-center text-muted py-5">No payout requests found.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th><th>Supplier</th><th>Amount</th><th>Method</th>
                        <th>Status</th><th>Requested</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($payouts as $p): ?>
                <tr>
                    <td>#<?= (int)$p['id'] ?></td>
                    <td>
                        <?= e($p['company_name'] ?: $p['email'] ?? '—') ?>
                        <br><small class="text-muted"><?= e($p['email'] ?? '') ?></small>
                    </td>
                    <td class="fw-semibold">$<?= number_format((float)$p['amount'], 2) ?></td>
                    <td>
                        <?= match($p['payout_method']){'bank_transfer'=>'🏦 Bank Transfer','paypal'=>'💙 PayPal','wise'=>'💚 Wise',default=>e($p['payout_method'])} ?>
                    </td>
                    <td>
                        <span class="badge bg-<?= match($p['status']){'pending'=>'warning','processing'=>'info','completed'=>'success','rejected'=>'danger',default=>'secondary'} ?>">
                            <?= ucfirst($p['status']) ?>
                        </span>
                        <?php if (!empty($p['admin_note'])): ?>
                        <br><small class="text-muted"><?= e($p['admin_note']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= formatDate($p['created_at']) ?></td>
                    <td>
                        <a href="/pages/admin/finance/payout-detail.php?id=<?= (int)$p['id'] ?>"
                           class="btn btn-sm btn-outline-primary">👁️ View</a>
                        <?php if ($p['status'] === 'pending'): ?>
                        <form method="POST" action="/api/payouts.php?action=admin_approve" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="payout_id" value="<?= (int)$p['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-success"
                                    onclick="return confirm('Approve this payout?')">✅ Approve</button>
                        </form>
                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal"
                                data-bs-target="#rejectModal" data-id="<?= (int)$p['id'] ?>">Reject</button>
                        <?php elseif ($p['status'] === 'processing'): ?>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                data-bs-target="#completeModal" data-id="<?= (int)$p['id'] ?>">Mark Complete</button>
                        <?php else: ?>
                        <span class="text-muted small"><?= $p['reference_number'] ? 'Ref: ' . e($p['reference_number']) : '—' ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
        <div class="card-footer bg-light d-flex justify-content-center">
            <nav><ul class="pagination pagination-sm mb-0">
                <?php for ($i = 1; $i <= min($pages, 10); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?status=<?= e($status) ?>&page=<?= $i ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
            </ul></nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Payout</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/api/payouts.php?action=admin_reject">
                <?= csrfField() ?>
                <input type="hidden" name="payout_id" id="rejectId">
                <div class="modal-body">
                    <label class="form-label fw-semibold">Reason for rejection</label>
                    <textarea name="reason" class="form-control" rows="3" required
                              placeholder="Explain why this payout is being rejected..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Payout</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Complete Modal -->
<div class="modal fade" id="completeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mark Payout Complete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/api/payouts.php?action=admin_complete">
                <?= csrfField() ?>
                <input type="hidden" name="payout_id" id="completeId">
                <div class="modal-body">
                    <label class="form-label fw-semibold">Transaction Reference</label>
                    <input type="text" name="transaction_ref" class="form-control"
                           placeholder="Transaction / wire reference number">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Mark Complete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('rejectModal')?.addEventListener('show.bs.modal', function(e) {
    document.getElementById('rejectId').value = e.relatedTarget?.dataset?.id ?? '';
});
document.getElementById('completeModal')?.addEventListener('show.bs.modal', function(e) {
    document.getElementById('completeId').value = e.relatedTarget?.dataset?.id ?? '';
});
</script>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
