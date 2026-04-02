<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAdmin();

$db     = getDB();
$page   = max(1,(int)get('page',1));
$status = get('status','');
$where  = ['1=1']; $params = [];
if ($status) { $where[] = 'o.status=?'; $params[] = $status; }
$sql    = 'SELECT o.*, u.first_name, u.last_name, u.email FROM orders o JOIN users u ON u.id=o.buyer_id WHERE ' . implode(' AND ',$where) . ' ORDER BY o.placed_at DESC';
$result = paginate($db, $sql, $params, $page);
$orders = $result['data'];

$pageTitle = 'Admin — Orders';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-bag-fill text-primary me-2"></i>Order Management</h3>
        <a href="/pages/admin/dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <?php foreach (['','pending','confirmed','processing','shipped','delivered','cancelled'] as $s): ?>
        <a href="?status=<?= $s ?>" class="btn btn-sm <?= $status===$s?'btn-primary':'btn-outline-secondary' ?>"><?= $s?ucfirst($s):'All' ?></a>
        <?php endforeach; ?>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr><th>Order #</th><th>Buyer</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th><th>Update Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                <?php
                $sb=['pending'=>'warning','confirmed'=>'info','processing'=>'info','shipped'=>'primary','delivered'=>'success','cancelled'=>'danger'];
                $pb=['paid'=>'success','pending'=>'warning','failed'=>'danger','refunded'=>'secondary'];
                ?>
                <tr>
                    <td><strong><?= e($o['order_number']) ?></strong></td>
                    <td>
                        <div><?= e($o['first_name'].' '.$o['last_name']) ?></div>
                        <small class="text-muted"><?= e($o['email']) ?></small>
                    </td>
                    <td><?= formatMoney($o['total']) ?></td>
                    <td><span class="badge bg-<?= $pb[$o['payment_status']]??'secondary' ?>"><?= ucfirst($o['payment_status']) ?></span></td>
                    <td><span class="badge bg-<?= $sb[$o['status']]??'secondary' ?>"><?= ucfirst($o['status']) ?></span></td>
                    <td><?= formatDate($o['placed_at']) ?></td>
                    <td>
                        <form method="POST" action="/api/admin.php?action=update_order_status" class="d-flex gap-1">
                            <?= csrfField() ?>
                            <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                            <select name="status" class="form-select form-select-sm" style="width:130px">
                                <?php foreach (['pending','confirmed','processing','shipped','delivered','cancelled','refunded'] as $s): ?>
                                <option value="<?= $s ?>" <?= $o['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($result['pages'] > 1): ?>
    <nav class="mt-4"><ul class="pagination justify-content-center">
        <?php for ($i=1;$i<=$result['pages'];$i++): ?>
        <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&status=<?= e($status) ?>"><?= $i ?></a></li>
        <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
