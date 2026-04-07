<?php
/**
 * pages/admin/orders/index.php — Admin Order Dashboard (PR #7)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/orders.php';
requireAdmin();

$db = getDB();

$page    = max(1, (int)get('page', 1));
$filters = [
    'status'         => get('status', ''),
    'payment_method' => get('payment_method', ''),
    'supplier_id'    => get('supplier_id', ''),
    'buyer_id'       => get('buyer_id', ''),
    'date_from'      => get('date_from', ''),
    'date_to'        => get('date_to', ''),
    'search'         => trim(get('search', '')),
];

$result = getAdminOrders($db, $filters, $page, 20);
$orders = $result['data'];
$stats  = getOrderStats($db, 0, 'admin');

// Load supplier list for filter dropdown
$suppList = $db->query('SELECT s.id, s.business_name FROM suppliers s ORDER BY s.business_name')->fetchAll();

$pageTitle = 'Admin — Orders';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h3 class="fw-bold mb-0"><i class="bi bi-bag-fill text-primary me-2"></i>Order Management</h3>
        <div class="d-flex gap-2">
            <a href="/api/orders.php?action=export&<?= http_build_query(array_filter($filters)) ?>"
               class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-download me-1"></i>Export CSV
            </a>
            <a href="/pages/admin/dashboard.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Dashboard
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Total Orders</div>
                    <div class="fs-3 fw-bold"><?= number_format((int)($stats['total_orders'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Orders Today</div>
                    <div class="fs-3 fw-bold text-primary"><?= (int)($stats['orders_today'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Revenue Today</div>
                    <div class="fs-3 fw-bold text-success"><?= formatMoney((float)($stats['revenue_today'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Revenue This Month</div>
                    <div class="fs-3 fw-bold text-info"><?= formatMoney((float)($stats['revenue_month'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Order #, buyer email…" value="<?= e($filters['search']) ?>">
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                <?php foreach (['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'] as $s): ?>
                <option value="<?= $s ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="payment_method" class="form-select form-select-sm">
                <option value="">All Payments</option>
                <?php foreach (['stripe', 'bank_transfer', 'paypal', 'cod'] as $pm): ?>
                <option value="<?= $pm ?>" <?= $filters['payment_method'] === $pm ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $pm)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="supplier_id" class="form-select form-select-sm">
                <option value="">All Suppliers</option>
                <?php foreach ($suppList as $s): ?>
                <option value="<?= (int)$s['id'] ?>" <?= (string)$filters['supplier_id'] === (string)$s['id'] ? 'selected' : '' ?>>
                    <?= e($s['business_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1">
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from']) ?>">
        </div>
        <div class="col-md-1">
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to']) ?>">
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-filter"></i></button>
        </div>
    </form>

    <!-- Bulk Actions -->
    <form id="bulkForm" class="mb-3">
        <?= csrfField() ?>
        <div class="d-flex gap-2 align-items-center">
            <select name="bulk_status" class="form-select form-select-sm" style="width:auto">
                <option value="">Bulk: Change Status</option>
                <?php foreach (['confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'] as $s): ?>
                <option value="<?= $s ?>"><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-warning btn-sm">Apply to Selected</button>
            <span class="text-muted small" id="selectedCount">0 selected</span>
        </div>
    </form>

    <!-- Orders Table -->
    <?php if (empty($orders)): ?>
    <div class="text-center py-5">
        <i class="bi bi-inbox display-1 text-muted"></i>
        <h5 class="mt-3 text-muted">No orders found</h5>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th><input type="checkbox" id="selectAll" class="form-check-input"></th>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Buyer</th>
                        <th class="text-center">Items</th>
                        <th class="text-end">Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td><input type="checkbox" class="form-check-input order-check" value="<?= (int)$o['id'] ?>"></td>
                    <td class="fw-semibold"><?= e($o['order_number']) ?></td>
                    <td class="text-muted"><?= formatDate($o['placed_at']) ?></td>
                    <td>
                        <div><?= e(trim($o['first_name'] . ' ' . $o['last_name'])) ?></div>
                        <small class="text-muted"><?= e($o['buyer_email']) ?></small>
                    </td>
                    <td class="text-center"><?= (int)$o['item_count'] ?></td>
                    <td class="text-end fw-semibold"><?= formatMoney((float)$o['total']) ?></td>
                    <td>
                        <span class="badge bg-<?= $o['payment_status'] === 'paid' ? 'success' : ($o['payment_status'] === 'failed' ? 'danger' : 'warning') ?>">
                            <?= ucfirst($o['payment_status']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-<?= getOrderStatusBadgeClass($o['status']) ?>">
                            <?= ucfirst($o['status']) ?>
                        </span>
                    </td>
                    <td>
                        <a href="/pages/admin/orders/detail.php?order_id=<?= (int)$o['id'] ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i>
                        </a>
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
                <a class="page-link" href="?page=<?= $page - 1 ?>&<?= http_build_query(array_filter($filters)) ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($result['pages'], $page + 2); $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_filter($filters)) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            <?php if ($page < $result['pages']): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?= $page + 1 ?>&<?= http_build_query(array_filter($filters)) ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
// Select all checkbox
document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll('.order-check').forEach(cb => cb.checked = this.checked);
    updateCount();
});
document.querySelectorAll('.order-check').forEach(cb => cb.addEventListener('change', updateCount));

function updateCount() {
    const n = document.querySelectorAll('.order-check:checked').length;
    document.getElementById('selectedCount').textContent = n + ' selected';
}

document.getElementById('bulkForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const ids = [...document.querySelectorAll('.order-check:checked')].map(cb => cb.value);
    const status = this.querySelector('[name=bulk_status]').value;
    if (!ids.length || !status) { alert('Select orders and a target status.'); return; }
    if (!confirm('Update ' + ids.length + ' order(s) to "' + status + '"?')) return;

    const fd = new FormData(this);
    fd.append('order_ids', JSON.stringify(ids));
    fd.append('status', status);

    fetch('/api/orders.php?action=bulk_update', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { alert(d.message); if (d.success) location.reload(); });
});
</script>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
