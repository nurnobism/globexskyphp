<?php
/**
 * pages/supplier/orders.php — Supplier Orders
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireRole(['supplier', 'admin', 'super_admin']);

$db = getDB();

$suppStmt = $db->prepare('SELECT id FROM suppliers WHERE user_id = ?');
$suppStmt->execute([$_SESSION['user_id']]);
$supplier = $suppStmt->fetch();

if (!$supplier && !isAdmin()) {
    flashMessage('warning', 'Supplier account not found.');
    redirect('/pages/supplier/dashboard.php');
}

$supplierId   = $supplier['id'] ?? 0;
$page         = max(1, (int)get('page', 1));
$filterStatus = get('status', '');

$where  = ['p.supplier_id = ?'];
$params = [$supplierId];
if ($filterStatus) { $where[] = 'o.status = ?'; $params[] = $filterStatus; }

$sql = 'SELECT o.id, o.order_number, o.status, o.payment_status, o.total, o.placed_at,
               u.first_name, u.last_name, u.email,
               COUNT(oi.id) item_count
        FROM orders o
        JOIN order_items oi ON oi.order_id = o.id
        JOIN products p ON p.id = oi.product_id
        JOIN users u ON u.id = o.buyer_id
        WHERE ' . implode(' AND ', $where) . '
        GROUP BY o.id
        ORDER BY o.placed_at DESC';

$result = paginate($db, $sql, $params, $page);
$orders = $result['data'];

$pageTitle = 'Supplier Orders';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-bag-check text-primary me-2"></i>Orders</h3>
        <a href="/pages/supplier/dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Dashboard</a>
    </div>

    <!-- Status Tabs -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <?php foreach (['' => 'All', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'] as $s => $label): ?>
        <a href="?status=<?= $s ?>" class="btn btn-sm <?= $filterStatus===$s?'btn-primary':'btn-outline-secondary' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($orders)): ?>
    <div class="text-center py-5">
        <i class="bi bi-bag-x display-1 text-muted"></i>
        <h5 class="mt-3">No orders found</h5>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr><th>Order #</th><th>Buyer</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th></tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o):
                    $sb = ['pending'=>'warning','confirmed'=>'info','processing'=>'info','shipped'=>'primary','delivered'=>'success','cancelled'=>'danger','refunded'=>'secondary'];
                    $pb = ['paid'=>'success','pending'=>'warning','failed'=>'danger','refunded'=>'secondary'];
                ?>
                <tr>
                    <td class="fw-semibold"><?= e($o['order_number']) ?></td>
                    <td>
                        <div><?= e($o['first_name'] . ' ' . $o['last_name']) ?></div>
                        <small class="text-muted"><?= e($o['email']) ?></small>
                    </td>
                    <td><?= $o['item_count'] ?></td>
                    <td><?= formatMoney($o['total']) ?></td>
                    <td><span class="badge bg-<?= $pb[$o['payment_status']]??'secondary' ?>"><?= ucfirst($o['payment_status']) ?></span></td>
                    <td><span class="badge bg-<?= $sb[$o['status']]??'secondary' ?>"><?= ucfirst($o['status']) ?></span></td>
                    <td><?= formatDate($o['placed_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($result['pages'] > 1): ?>
    <nav class="mt-4"><ul class="pagination justify-content-center">
        <?php for ($i=1;$i<=$result['pages'];$i++): ?>
        <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&status=<?= e($filterStatus) ?>"><?= $i ?></a></li>
        <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
