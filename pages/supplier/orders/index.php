<?php
/**
 * pages/supplier/orders/index.php — Supplier Order Dashboard (PR #7)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/orders.php';
requireRole(['supplier', 'admin', 'super_admin']);

$db = getDB();
$userId = (int)$_SESSION['user_id'];

// Resolve supplierId
$suppStmt = $db->prepare('SELECT id, business_name, commission_rate FROM suppliers WHERE user_id = ?');
$suppStmt->execute([$userId]);
$supplier = $suppStmt->fetch();

if (!$supplier && !isAdmin()) {
    flashMessage('warning', 'Supplier account not found.');
    redirect('/pages/supplier/dashboard.php');
}

$supplierId = $supplier ? (int)$supplier['id'] : 0;

$page         = max(1, (int)get('page', 1));
$filterStatus = get('status', '');
$search       = trim(get('search', ''));

$filters = [
    'status'    => $filterStatus,
    'search'    => $search,
    'date_from' => get('date_from', ''),
    'date_to'   => get('date_to', ''),
];

$result = getSupplierOrders($db, $supplierId, $filters, $page, 20);
$orders = $result['data'];

// Dashboard stats
$stats = getOrderStats($db, $supplierId, 'supplier');

$commRate = (float)($supplier['commission_rate'] ?? 0);

$pageTitle = 'Supplier Orders';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h3 class="fw-bold mb-0"><i class="bi bi-bag-check text-primary me-2"></i>Orders</h3>
        <div class="d-flex gap-2">
            <a href="/api/orders.php?action=export&status=<?= e($filterStatus) ?>&date_from=<?= e($filters['date_from']) ?>&date_to=<?= e($filters['date_to']) ?>"
               class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-download me-1"></i>Export CSV
            </a>
            <a href="/pages/supplier/dashboard.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Dashboard
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-1">New Today</div>
                    <div class="fs-3 fw-bold text-warning"><?= (int)($stats['new_today'] ?? 0) ?></div>
                    <div class="small text-muted">orders received</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-1">Processing</div>
                    <div class="fs-3 fw-bold text-info"><?= (int)($stats['processing_count'] ?? 0) ?></div>
                    <div class="small text-muted">orders in progress</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-1">Shipped</div>
                    <div class="fs-3 fw-bold text-primary"><?= (int)($stats['shipped_count'] ?? 0) ?></div>
                    <div class="small text-muted">awaiting delivery</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-1">Revenue This Month</div>
                    <div class="fs-3 fw-bold text-success"><?= formatMoney((float)($stats['revenue_month'] ?? 0)) ?></div>
                    <div class="small text-muted">gross sales</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search order # or buyer…" value="<?= e($search) ?>">
        </div>
        <div class="col-md-2">
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from']) ?>">
        </div>
        <div class="col-md-2">
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to']) ?>">
        </div>
        <div class="col-md-2">
            <input type="hidden" name="status" value="<?= e($filterStatus) ?>">
            <button class="btn btn-primary btn-sm w-100" type="submit"><i class="bi bi-filter me-1"></i>Filter</button>
        </div>
        <div class="col-md-2">
            <a href="?" class="btn btn-outline-secondary btn-sm w-100">Clear</a>
        </div>
    </form>

    <!-- Status Tabs -->
    <ul class="nav nav-tabs mb-3">
        <?php foreach (['' => 'All', 'pending' => 'New', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'] as $s => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $filterStatus === $s ? 'active' : '' ?>"
               href="?status=<?= e($s) ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                <?= e($label) ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <!-- Orders Table -->
    <?php if (empty($orders)): ?>
    <div class="text-center py-5">
        <i class="bi bi-bag-x display-1 text-muted"></i>
        <h5 class="mt-3 text-muted">No orders found</h5>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Buyer</th>
                        <th class="text-center">Items</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Commission</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o):
                    $orderBase = (float)($o['supplier_subtotal'] ?? $o['total']);
                    $commission = $orderBase * $commRate / 100;
                    $netEarnings = $orderBase - $commission;
                ?>
                <tr>
                    <td class="fw-semibold"><?= e($o['order_number']) ?></td>
                    <td class="text-muted"><?= formatDate($o['placed_at']) ?></td>
                    <td>
                        <div><?= e(trim($o['first_name'] . ' ' . $o['last_name'])) ?></div>
                        <small class="text-muted"><?= e(maskEmail($o['buyer_email'])) ?></small>
                    </td>
                    <td class="text-center"><?= (int)$o['item_count'] ?></td>
                    <td class="text-end fw-semibold"><?= formatMoney((float)($o['supplier_subtotal'] ?? $o['total'])) ?></td>
                    <td class="text-end text-danger">-<?= formatMoney($commission) ?></td>
                    <td>
                        <span class="badge bg-<?= getOrderStatusBadgeClass($o['status']) ?>">
                            <?= e(ucfirst($o['status'])) ?>
                        </span>
                    </td>
                    <td>
                        <a href="/pages/supplier/orders/detail.php?order_id=<?= (int)$o['id'] ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i>
                        </a>
                        <?php
                            $validNext = getValidStatusTransitions($o['status'], 'supplier');
                        ?>
                        <?php if (in_array('processing', $validNext, true)): ?>
                        <button class="btn btn-sm btn-success" onclick="acceptOrder(<?= (int)$o['id'] ?>)" title="Accept / Start Processing">
                            <i class="bi bi-check-lg"></i>
                        </button>
                        <?php elseif (in_array('shipped', $validNext, true)): ?>
                        <button class="btn btn-sm btn-primary" onclick="showShipModal(<?= (int)$o['id'] ?>)" title="Mark as Shipped">
                            <i class="bi bi-truck"></i>
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
            <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>&status=<?= e($filterStatus) ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Ship Order Modal -->
<div class="modal fade" id="shipModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="shipForm" method="POST" action="/api/orders.php?action=add_tracking">
                <?= csrfField() ?>
                <input type="hidden" name="order_id" id="shipOrderId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-truck me-2"></i>Add Tracking Info</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Carrier</label>
                        <input type="text" name="carrier" class="form-control" placeholder="e.g. FedEx, UPS, DHL">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tracking Number <span class="text-danger">*</span></label>
                        <input type="text" name="tracking_number" class="form-control" required placeholder="Tracking number">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tracking URL (optional)</label>
                        <input type="url" name="tracking_url" class="form-control" placeholder="https://...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-truck me-1"></i>Mark as Shipped</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const csrfToken = document.querySelector('[name=_csrf_token]').value;

function acceptOrder(id) {
    if (!confirm('Accept this order and start processing?')) return;
    const fd = new FormData();
    fd.append('order_id', id);
    fd.append('status', 'processing');
    fd.append('_csrf_token', csrfToken);
    fetch('/api/orders.php?action=update_status', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.success) location.reload(); else alert(d.message); });
}

function showShipModal(id) {
    document.getElementById('shipOrderId').value = id;
    new bootstrap.Modal(document.getElementById('shipModal')).show();
}

document.getElementById('shipForm').addEventListener('submit', function(e) {
    e.preventDefault();
    fetch(this.action, { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(d => { if (d.success) location.reload(); else alert(d.message); });
});
</script>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
