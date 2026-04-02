<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireRole(['admin', 'super_admin']);

$db = getDB();
$stats = [
    'users'     => (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'products'  => (int)$db->query('SELECT COUNT(*) FROM products WHERE status="active"')->fetchColumn(),
    'orders'    => (int)$db->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    'suppliers' => (int)$db->query('SELECT COUNT(*) FROM suppliers')->fetchColumn(),
    'revenue'   => (float)$db->query('SELECT COALESCE(SUM(total),0) FROM orders WHERE payment_status="paid"')->fetchColumn(),
    'pending'   => (int)$db->query('SELECT COUNT(*) FROM orders WHERE status="pending"')->fetchColumn(),
    'open_rfqs' => (int)$db->query('SELECT COUNT(*) FROM rfqs WHERE status="open"')->fetchColumn(),
    'contacts'  => (int)$db->query('SELECT COUNT(*) FROM contact_inquiries WHERE status="new"')->fetchColumn(),
];

// Recent orders
$recentOrders = $db->query('SELECT o.*, u.first_name, u.last_name FROM orders o JOIN users u ON u.id=o.buyer_id ORDER BY o.placed_at DESC LIMIT 10')->fetchAll();

// Recent users
$recentUsers = $db->query('SELECT id, email, first_name, last_name, role, created_at FROM users ORDER BY created_at DESC LIMIT 5')->fetchAll();

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-speedometer2 text-primary me-2"></i>Admin Dashboard</h3>
        <div class="d-flex gap-2">
            <a href="/pages/admin/products.php" class="btn btn-outline-primary btn-sm">Products</a>
            <a href="/pages/admin/users.php" class="btn btn-outline-secondary btn-sm">Users</a>
            <a href="/pages/admin/orders.php" class="btn btn-outline-success btn-sm">Orders</a>
            <a href="/pages/admin/settings.php" class="btn btn-outline-dark btn-sm"><i class="bi bi-gear"></i></a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <?php $cards = [
            ['Total Users',     $stats['users'],    'people-fill',      'primary'],
            ['Active Products', $stats['products'], 'box-seam-fill',    'success'],
            ['Total Orders',    $stats['orders'],   'bag-fill',         'warning'],
            ['Revenue (Paid)',  formatMoney($stats['revenue']), 'currency-dollar', 'info'],
            ['Suppliers',       $stats['suppliers'],'building-fill',    'secondary'],
            ['Pending Orders',  $stats['pending'],  'clock-fill',       'danger'],
            ['Open RFQs',       $stats['open_rfqs'],'file-text-fill',   'primary'],
            ['New Contacts',    $stats['contacts'], 'envelope-fill',    'warning'],
        ]; ?>
        <?php foreach ($cards as [$label, $value, $icon, $color]): ?>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-<?= $color ?> bg-opacity-10 p-3">
                        <i class="bi bi-<?= $icon ?> text-<?= $color ?> fs-4"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0"><?= is_numeric($value) ? number_format($value) : $value ?></h5>
                        <small class="text-muted"><?= $label ?></small>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <!-- Recent Orders -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between">
                    <h6 class="mb-0 fw-bold">Recent Orders</h6>
                    <a href="/pages/admin/orders.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light"><tr><th>Order #</th><th>Buyer</th><th>Total</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentOrders as $o): ?>
                        <?php $b=['pending'=>'warning','confirmed'=>'info','shipped'=>'primary','delivered'=>'success','cancelled'=>'danger']; ?>
                        <tr>
                            <td><strong><?= e($o['order_number']) ?></strong></td>
                            <td><?= e($o['first_name'] . ' ' . $o['last_name']) ?></td>
                            <td><?= formatMoney($o['total']) ?></td>
                            <td><span class="badge bg-<?= $b[$o['status']]??'secondary' ?>"><?= ucfirst($o['status']) ?></span></td>
                            <td><?= formatDate($o['placed_at']) ?></td>
                            <td>
                                <form method="POST" action="/api/admin.php?action=update_order_status" class="d-flex gap-1">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                    <select name="status" class="form-select form-select-sm" style="width:130px">
                                        <?php foreach (['pending','confirmed','processing','shipped','delivered','cancelled'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $o['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary">✓</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Users -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between">
                    <h6 class="mb-0 fw-bold">Recent Users</h6>
                    <a href="/pages/admin/users.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($recentUsers as $u): ?>
                    <li class="list-group-item d-flex align-items-center gap-2 py-2">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($u['first_name']) ?>&size=36&background=random"
                             class="rounded-circle" width="36" height="36">
                        <div class="flex-grow-1">
                            <div class="fw-semibold small"><?= e($u['first_name'] . ' ' . $u['last_name']) ?></div>
                            <div class="text-muted small"><?= e($u['email']) ?></div>
                        </div>
                        <span class="badge bg-<?= $u['role']==='admin'?'danger':($u['role']==='supplier'?'primary':'secondary') ?>"><?= $u['role'] ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
