<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$db      = getDB();
$page    = max(1, (int)get('page', 1));
$status  = get('status', '');
$where   = ['o.buyer_id = ?'];
$params  = [$_SESSION['user_id']];
if ($status) { $where[] = 'o.status = ?'; $params[] = $status; }

$sql  = 'SELECT o.*, (SELECT COUNT(*) FROM order_items WHERE order_id=o.id) item_count FROM orders o WHERE ' . implode(' AND ', $where) . ' ORDER BY o.placed_at DESC';
$result = paginate($db, $sql, $params, $page);
$orders = $result['data'];

$pageTitle = 'My Orders';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-bag-fill text-primary me-2"></i>My Orders</h3>
    </div>

    <!-- Status filter -->
    <div class="mb-3 d-flex flex-wrap gap-2">
        <?php foreach (['','pending','confirmed','processing','shipped','delivered','cancelled'] as $s): ?>
        <a href="?status=<?= $s ?>" class="btn btn-sm <?= $status === $s ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <?= $s ? ucfirst($s) : 'All' ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($orders)): ?>
        <div class="text-center py-5">
            <i class="bi bi-bag-x display-1 text-muted"></i>
            <h5 class="mt-3">No orders yet</h5>
            <a href="/pages/product/index.php" class="btn btn-primary mt-2">Start Shopping</a>
        </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Order #</th><th>Items</th><th>Total</th>
                        <th>Status</th><th>Payment</th><th>Date</th><th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td><strong><?= e($order['order_number']) ?></strong></td>
                    <td><?= $order['item_count'] ?> item(s)</td>
                    <td><?= formatMoney($order['total']) ?></td>
                    <td>
                        <?php
                        $badges = ['pending'=>'warning','confirmed'=>'info','processing'=>'info','shipped'=>'primary','delivered'=>'success','cancelled'=>'danger','refunded'=>'secondary'];
                        $b = $badges[$order['status']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?= $b ?>"><?= ucfirst($order['status']) ?></span>
                    </td>
                    <td>
                        <?php $pb = ['paid'=>'success','pending'=>'warning','failed'=>'danger','refunded'=>'secondary'];
                        $p = $pb[$order['payment_status']] ?? 'secondary'; ?>
                        <span class="badge bg-<?= $p ?>"><?= ucfirst($order['payment_status']) ?></span>
                    </td>
                    <td><?= formatDate($order['placed_at']) ?></td>
                    <td>
                        <a href="/pages/order/detail.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i> View
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
            <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>&status=<?= e($status) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
