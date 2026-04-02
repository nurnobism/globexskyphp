<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$db   = getDB();
$page = max(1, (int)get('page', 1));
$sql  = 'SELECT r.*, c.name category_name, (SELECT COUNT(*) FROM rfq_quotes WHERE rfq_id=r.id) quote_count FROM rfqs r LEFT JOIN categories c ON c.id=r.category_id WHERE r.buyer_id=? ORDER BY r.created_at DESC';
$result = paginate($db, $sql, [$_SESSION['user_id']], $page);
$rfqs   = $result['data'];

$pageTitle = 'My RFQs';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-file-text-fill text-primary me-2"></i>My RFQs</h3>
        <a href="/pages/rfq/create.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i> New RFQ</a>
    </div>

    <?php if (empty($rfqs)): ?>
    <div class="text-center py-5">
        <i class="bi bi-file-earmark-x display-1 text-muted"></i>
        <h5 class="mt-3">No RFQs yet</h5>
        <a href="/pages/rfq/create.php" class="btn btn-primary mt-2">Submit Your First RFQ</a>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>RFQ #</th><th>Title</th><th>Category</th><th>Qty</th><th>Status</th><th>Quotes</th><th>Date</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($rfqs as $rfq): ?>
                <?php $badges=['open'=>'success','closed'=>'secondary','awarded'=>'primary','cancelled'=>'danger']; ?>
                <tr>
                    <td><strong><?= e($rfq['rfq_number']) ?></strong></td>
                    <td><?= e(mb_strimwidth($rfq['title'], 0, 40, '…')) ?></td>
                    <td><small><?= e($rfq['category_name'] ?? '—') ?></small></td>
                    <td><?= $rfq['quantity'] ? e($rfq['quantity'] . ' ' . $rfq['unit']) : '—' ?></td>
                    <td><span class="badge bg-<?= $badges[$rfq['status']] ?? 'secondary' ?>"><?= ucfirst($rfq['status']) ?></span></td>
                    <td><span class="badge bg-info"><?= $rfq['quote_count'] ?> quotes</span></td>
                    <td><?= formatDate($rfq['created_at']) ?></td>
                    <td>
                        <?php if ($rfq['status'] === 'open'): ?>
                        <form method="POST" action="/api/rfq.php?action=cancel" class="d-inline" onsubmit="return confirm('Cancel this RFQ?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="rfq_id" value="<?= $rfq['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($result['pages'] > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a></li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
