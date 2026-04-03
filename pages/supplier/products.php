<?php
/**
 * pages/supplier/products.php — Supplier Product List
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

$supplierId = $supplier['id'] ?? 0;
$page       = max(1, (int)get('page', 1));
$q          = trim(get('q', ''));
$filterStatus = get('status', '');

$where  = ['p.supplier_id = ?', 'p.status != "archived"'];
$params = [$supplierId];
if ($q)           { $where[] = 'p.name LIKE ?'; $params[] = "%$q%"; }
if ($filterStatus){ $where[] = 'p.status = ?';  $params[] = $filterStatus; }

$sql    = 'SELECT p.*, c.name category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE ' . implode(' AND ', $where) . ' ORDER BY p.created_at DESC';
$result = paginate($db, $sql, $params, $page);
$products = $result['data'];

$pageTitle = 'My Products';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-box-seam text-primary me-2"></i>My Products</h3>
        <a href="/pages/supplier/product-add.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i> Add Product</a>
    </div>

    <form method="GET" class="row g-2 mb-4">
        <div class="col-md-5"><input type="text" name="q" class="form-control" placeholder="Search products..." value="<?= e($q) ?>"></div>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">All Status</option>
                <?php foreach (['active','draft','inactive','pending_review'] as $s): ?>
                <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto"><button type="submit" class="btn btn-primary">Search</button></div>
        <?php if ($q||$filterStatus): ?><div class="col-auto"><a href="?" class="btn btn-outline-secondary">Clear</a></div><?php endif; ?>
    </form>

    <?php if (empty($products)): ?>
    <div class="text-center py-5">
        <i class="bi bi-box-seam display-1 text-muted"></i>
        <h5 class="mt-3">No products found</h5>
        <a href="/pages/supplier/product-add.php" class="btn btn-primary mt-2"><i class="bi bi-plus-circle me-1"></i> Add Your First Product</a>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr><th>ID</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Date</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($products as $p):
                    $badge = ['active'=>'success','draft'=>'warning','inactive'=>'secondary','pending_review'=>'info','archived'=>'dark'];
                ?>
                <tr>
                    <td><?= $p['id'] ?></td>
                    <td class="fw-semibold"><?= e(mb_strimwidth($p['name'],0,40,'…')) ?></td>
                    <td><?= e($p['category_name'] ?? '—') ?></td>
                    <td><?= formatMoney($p['price']) ?></td>
                    <td><?= $p['stock_qty'] ?></td>
                    <td><span class="badge bg-<?= $badge[$p['status']]??'secondary' ?>"><?= e(str_replace('_',' ',ucfirst($p['status']))) ?></span></td>
                    <td><?= formatDate($p['created_at']) ?></td>
                    <td>
                        <a href="/pages/supplier/product-edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <?php if ($p['status'] === 'active'): ?>
                        <a href="/pages/product/detail.php?slug=<?= urlencode($p['slug']) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">View</a>
                        <?php endif; ?>
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
        <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($q) ?>&status=<?= e($filterStatus) ?>"><?= $i ?></a></li>
        <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
