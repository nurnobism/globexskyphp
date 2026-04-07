<?php
/**
 * pages/account/orders/index.php — Buyer: My Orders (PR #7)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/orders.php';
requireLogin();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

$page         = max(1, (int)get('page', 1));
$filterStatus = get('status', '');
$search       = trim(get('search', ''));

$filters = [
    'status'    => $filterStatus,
    'search'    => $search,
    'date_from' => get('date_from', ''),
    'date_to'   => get('date_to', ''),
];

$result = getBuyerOrders($db, $userId, $filters, $page, 15);
$orders = $result['data'];

// Tab counts
$tabStatuses = ['', 'pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
$tabLabels   = ['' => 'All', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];

$pageTitle = 'My Orders';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="bi bi-bag-check me-2 text-primary"></i>My Orders</h2>
        <a href="/pages/account/profile.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-person me-1"></i>Account</a>
    </div>

    <!-- Search -->
    <form method="GET" class="mb-3">
        <input type="hidden" name="status" value="<?= e($filterStatus) ?>">
        <div class="input-group" style="max-width:400px">
            <input type="text" name="search" class="form-control" placeholder="Search orders…" value="<?= e($search) ?>">
            <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button>
        </div>
    </form>

    <!-- Status tabs -->
    <ul class="nav nav-tabs mb-4">
        <?php foreach ($tabLabels as $s => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $filterStatus === $s ? 'active' : '' ?>"
               href="?status=<?= e($s) ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                <?= e($label) ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <?php if (empty($orders)): ?>
    <div class="text-center py-5">
        <i class="bi bi-bag-x display-1 text-muted"></i>
        <h5 class="mt-3 text-muted">No orders yet</h5>
        <p class="text-muted">You haven't placed any orders<?= $filterStatus ? ' with this status' : '' ?>.</p>
        <a href="/" class="btn btn-primary mt-2"><i class="bi bi-shop me-1"></i>Start Shopping!</a>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td class="fw-semibold"><?= e($o['order_number']) ?></td>
                    <td class="text-muted small"><?= formatDate($o['placed_at']) ?></td>
                    <td><?= (int)$o['item_count'] ?> item<?= $o['item_count'] != 1 ? 's' : '' ?></td>
                    <td>
                        <span class="badge bg-<?= getOrderStatusBadgeClass($o['status']) ?>">
                            <?= e(ucfirst($o['status'])) ?>
                        </span>
                    </td>
                    <td class="fw-semibold"><?= formatMoney((float)$o['total']) ?></td>
                    <td>
                        <a href="/pages/account/orders/detail.php?order_id=<?= (int)$o['id'] ?>"
                           class="btn btn-sm btn-outline-primary me-1">
                            <i class="bi bi-eye me-1"></i>View
                        </a>
                        <?php if (in_array($o['status'], ['pending', 'confirmed'], true)): ?>
                        <button class="btn btn-sm btn-outline-danger"
                                onclick="cancelOrder(<?= (int)$o['id'] ?>)">
                            <i class="bi bi-x-circle me-1"></i>Cancel
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($result['pages'] > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?= $page - 1 ?>&status=<?= e($filterStatus) ?>&search=<?= urlencode($search) ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($result['pages'], $page + 2); $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>&status=<?= e($filterStatus) ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            <?php if ($page < $result['pages']): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?= $page + 1 ?>&status=<?= e($filterStatus) ?>&search=<?= urlencode($search) ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Cancel Order Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="cancelForm" method="POST" action="/api/orders.php?action=cancel">
                <?= csrfField() ?>
                <input type="hidden" name="order_id" id="cancelOrderId">
                <div class="modal-header">
                    <h5 class="modal-title">Cancel Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Reason (optional)</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Why are you cancelling?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Order</button>
                    <button type="submit" class="btn btn-danger">Cancel Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function cancelOrder(orderId) {
    document.getElementById('cancelOrderId').value = orderId;
    new bootstrap.Modal(document.getElementById('cancelModal')).show();
}
document.getElementById('cancelForm').addEventListener('submit', function(e) {
    e.preventDefault();
    fetch(this.action, { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(data => {
            if (data.success) { location.reload(); }
            else { alert(data.message || 'Failed to cancel order.'); }
        });
});
</script>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
