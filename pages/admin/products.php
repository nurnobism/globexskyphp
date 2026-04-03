<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAdmin();

$db     = getDB();
$page   = max(1,(int)get('page',1));
$status = get('status','');
$q      = get('q','');
$tab    = get('tab','all'); // 'all' or 'pending_review'

if ($tab === 'pending_review') {
    $status = 'pending_review';
}

$where  = ['1=1']; $params = [];
if ($status) { $where[] = 'p.status=?'; $params[] = $status; }
if ($q)      { $where[] = 'p.name LIKE ?'; $params[] = "%$q%"; }
$sql    = 'SELECT p.*, s.company_name supplier_name FROM products p LEFT JOIN suppliers s ON s.id=p.supplier_id WHERE ' . implode(' AND ',$where) . ' ORDER BY p.created_at DESC';
$result = paginate($db, $sql, $params, $page);
$products = $result['data'];

// Count pending review
$pendingCount = (int)$db->query('SELECT COUNT(*) FROM products WHERE status = "pending_review"')->fetchColumn();

$pageTitle = 'Admin — Products';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-box-seam-fill text-primary me-2"></i>Product Management</h3>
        <a href="/pages/admin/dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item"><a class="nav-link <?= $tab==='all'?'active':'' ?>" href="?tab=all">All Products</a></li>
        <li class="nav-item">
            <a class="nav-link <?= $tab==='pending_review'?'active':'' ?>" href="?tab=pending_review">
                Pending Review <?php if ($pendingCount > 0): ?><span class="badge bg-danger ms-1"><?= $pendingCount ?></span><?php endif; ?>
            </a>
        </li>
    </ul>

    <form method="GET" class="row g-2 mb-4">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <div class="col-md-4"><input type="text" name="q" class="form-control" placeholder="Search products..." value="<?= e($q) ?>"></div>
        <?php if ($tab === 'all'): ?>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">All Status</option>
                <?php foreach (['active','draft','inactive','archived','pending_review'] as $s): ?>
                <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-auto"><button type="submit" class="btn btn-primary">Search</button></div>
        <?php if ($q||($status&&$tab==='all')): ?><div class="col-auto"><a href="?tab=<?= e($tab) ?>" class="btn btn-outline-secondary">Clear</a></div><?php endif; ?>
    </form>

    <?php if ($tab === 'pending_review' && !empty($products)): ?>
    <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>These products are awaiting admin review before going live.</div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr><th>ID</th><th>Name</th><th>Supplier</th><th>Price</th><th>Stock</th><th>Status</th><th>Rating</th><th>Date</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($products as $p): ?>
                <?php $b=['active'=>'success','draft'=>'warning','inactive'=>'secondary','archived'=>'dark','pending_review'=>'info','rejected'=>'danger']; ?>
                <tr>
                    <td><?= $p['id'] ?></td>
                    <td>
                        <a href="/pages/product/detail.php?slug=<?= urlencode($p['slug']) ?>" class="text-decoration-none">
                            <?= e(mb_strimwidth($p['name'],0,40,'…')) ?>
                        </a>
                    </td>
                    <td><?= e($p['supplier_name'] ?? '—') ?></td>
                    <td><?= formatMoney($p['price']) ?></td>
                    <td><?= $p['stock_qty'] ?></td>
                    <td><span class="badge bg-<?= $b[$p['status']]??'secondary' ?>"><?= e(str_replace('_',' ',ucfirst($p['status']))) ?></span></td>
                    <td><?= $p['rating'] > 0 ? number_format($p['rating'],1) . ' ★' : '—' ?></td>
                    <td><?= formatDate($p['created_at']) ?></td>
                    <td>
                        <a href="/pages/product/detail.php?slug=<?= urlencode($p['slug']) ?>" class="btn btn-sm btn-outline-primary">View</a>
                        <?php if ($p['status'] === 'pending_review'): ?>
                        <form method="POST" action="/api/products.php?action=toggle_status" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="status" value="active">
                            <button type="submit" class="btn btn-sm btn-success">Approve</button>
                        </form>
                        <form method="POST" action="/api/products.php?action=toggle_status" class="d-inline" onsubmit="return confirm('Reject this product?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="status" value="rejected">
                            <button type="submit" class="btn btn-sm btn-danger">Reject</button>
                        </form>
                        <?php else: ?>
                        <form method="POST" action="/api/products.php?action=delete" class="d-inline" onsubmit="return confirm('Archive this product?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Archive</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($products)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No products found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($result['pages'] > 1): ?>
    <nav class="mt-4"><ul class="pagination justify-content-center">
        <?php for ($i=1;$i<=$result['pages'];$i++): ?>
        <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($q) ?>&status=<?= e($status) ?>&tab=<?= e($tab) ?>"><?= $i ?></a></li>
        <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

$db     = getDB();
$page   = max(1,(int)get('page',1));
$status = get('status','');
$q      = get('q','');
$where  = ['1=1']; $params = [];
if ($status) { $where[] = 'p.status=?'; $params[] = $status; }
if ($q)      { $where[] = 'p.name LIKE ?'; $params[] = "%$q%"; }
$sql    = 'SELECT p.*, s.company_name supplier_name FROM products p LEFT JOIN suppliers s ON s.id=p.supplier_id WHERE ' . implode(' AND ',$where) . ' ORDER BY p.created_at DESC';
$result = paginate($db, $sql, $params, $page);
$products = $result['data'];

$pageTitle = 'Admin — Products';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-box-seam-fill text-primary me-2"></i>Product Management</h3>
        <a href="/pages/admin/dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
    </div>

    <form method="GET" class="row g-2 mb-4">
        <div class="col-md-4"><input type="text" name="q" class="form-control" placeholder="Search products..." value="<?= e($q) ?>"></div>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">All Status</option>
                <?php foreach (['active','draft','inactive','archived'] as $s): ?>
                <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto"><button type="submit" class="btn btn-primary">Search</button></div>
        <?php if ($q||$status): ?><div class="col-auto"><a href="?" class="btn btn-outline-secondary">Clear</a></div><?php endif; ?>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr><th>ID</th><th>Name</th><th>Supplier</th><th>Price</th><th>Stock</th><th>Status</th><th>Rating</th><th>Date</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($products as $p): ?>
                <?php $b=['active'=>'success','draft'=>'warning','inactive'=>'secondary','archived'=>'dark']; ?>
                <tr>
                    <td><?= $p['id'] ?></td>
                    <td>
                        <a href="/pages/product/detail.php?slug=<?= urlencode($p['slug']) ?>" class="text-decoration-none">
                            <?= e(mb_strimwidth($p['name'],0,40,'…')) ?>
                        </a>
                    </td>
                    <td><?= e($p['supplier_name'] ?? '—') ?></td>
                    <td><?= formatMoney($p['price']) ?></td>
                    <td><?= $p['stock_qty'] ?></td>
                    <td><span class="badge bg-<?= $b[$p['status']]??'secondary' ?>"><?= $p['status'] ?></span></td>
                    <td><?= $p['rating'] > 0 ? number_format($p['rating'],1) . ' ★' : '—' ?></td>
                    <td><?= formatDate($p['created_at']) ?></td>
                    <td>
                        <a href="/pages/product/detail.php?slug=<?= urlencode($p['slug']) ?>" class="btn btn-sm btn-outline-primary">View</a>
                        <form method="POST" action="/api/products.php?action=delete" class="d-inline" onsubmit="return confirm('Archive this product?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Archive</button>
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
        <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($q) ?>&status=<?= e($status) ?>"><?= $i ?></a></li>
        <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
