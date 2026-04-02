<?php
require_once __DIR__ . '/../../includes/middleware.php';

$db   = getDB();
$slug = get('slug', '');
$id   = (int)get('id', 0);
$edit = isset($_GET['edit']) && isLoggedIn() && isSupplier();

$stmt = $db->prepare('SELECT s.*, u.email, u.first_name, u.last_name FROM suppliers s JOIN users u ON u.id=s.user_id WHERE ' . ($id ? 's.id=?' : 's.slug=?'));
$stmt->execute([$id ?: $slug]);
$supplier = $stmt->fetch();

if (!$supplier && !$edit) {
    // Maybe they want to set up their own profile
    if (isLoggedIn() && isSupplier()) {
        $stmt2 = $db->prepare('SELECT s.* FROM suppliers s WHERE s.user_id=?');
        $stmt2->execute([$_SESSION['user_id']]);
        $supplier = $stmt2->fetch();
    }
    if (!$supplier) { flashMessage('danger', 'Supplier not found.'); redirect('/pages/supplier/index.php'); }
}

// Products
$pStmt = $db->prepare('SELECT * FROM products WHERE supplier_id=? AND status="active" ORDER BY created_at DESC LIMIT 12');
$pStmt->execute([$supplier['id']]);
$products = $pStmt->fetchAll();

$pageTitle = $supplier['company_name'];
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <!-- Cover Banner -->
    <div class="rounded shadow-sm mb-4 overflow-hidden" style="height:200px;background:<?= $supplier['banner'] ? 'url('.e(APP_URL.'/'.$supplier['banner']).')center/cover' : 'linear-gradient(135deg,#0d6efd,#6610f2)' ?>">
        <div class="d-flex align-items-end h-100 p-4" style="background:rgba(0,0,0,0.3)">
            <div class="d-flex align-items-center gap-4">
                <img src="<?= $supplier['logo'] ? e(APP_URL.'/'.$supplier['logo']) : 'https://ui-avatars.com/api/?name=' . urlencode($supplier['company_name']) . '&background=fff&color=0d6efd&size=80' ?>"
                     class="rounded bg-white p-2" width="80" height="80" style="object-fit:contain">
                <div class="text-white">
                    <h2 class="fw-bold mb-0"><?= e($supplier['company_name']) ?></h2>
                    <?php if ($supplier['verified']): ?><span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Verified Supplier</span><?php endif; ?>
                    <?php if ($supplier['country']): ?><span class="badge bg-light text-dark ms-1"><i class="bi bi-geo-alt"></i> <?= e($supplier['city']?$supplier['city'].', ':'') . e($supplier['country']) ?></span><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Stats & Info -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Company Info</h6>
                    <dl class="row small mb-0">
                        <?php if ($supplier['established_year']): ?><dt class="col-5 text-muted">Established</dt><dd class="col-7"><?= $supplier['established_year'] ?></dd><?php endif; ?>
                        <?php if ($supplier['employee_count']): ?><dt class="col-5 text-muted">Employees</dt><dd class="col-7"><?= e($supplier['employee_count']) ?></dd><?php endif; ?>
                        <?php if ($supplier['annual_revenue']): ?><dt class="col-5 text-muted">Revenue</dt><dd class="col-7"><?= e($supplier['annual_revenue']) ?></dd><?php endif; ?>
                        <?php if ($supplier['website']): ?><dt class="col-5 text-muted">Website</dt><dd class="col-7"><a href="<?= e($supplier['website']) ?>" target="_blank">Visit <i class="bi bi-box-arrow-up-right small"></i></a></dd><?php endif; ?>
                        <?php if ($supplier['response_time']): ?><dt class="col-5 text-muted">Response</dt><dd class="col-7"><?= e($supplier['response_time']) ?></dd><?php endif; ?>
                        <?php if ($supplier['rating'] > 0): ?><dt class="col-5 text-muted">Rating</dt><dd class="col-7 text-warning">★ <?= number_format($supplier['rating'],1) ?>/5</dd><?php endif; ?>
                    </dl>
                </div>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-6">
                    <div class="card border-0 shadow-sm text-center p-3">
                        <h4 class="fw-bold text-primary mb-0"><?= $supplier['total_products'] ?></h4>
                        <small class="text-muted">Products</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card border-0 shadow-sm text-center p-3">
                        <h4 class="fw-bold text-success mb-0"><?= $supplier['total_orders'] ?></h4>
                        <small class="text-muted">Orders</small>
                    </div>
                </div>
            </div>
            <?php if (isLoggedIn()): ?>
            <a href="/pages/rfq/create.php?supplier_id=<?= $supplier['id'] ?>" class="btn btn-primary w-100 mb-2">
                <i class="bi bi-file-text me-1"></i> Request Quote
            </a>
            <?php endif; ?>
        </div>

        <!-- Products -->
        <div class="col-lg-8">
            <?php if ($supplier['description']): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-2">About</h6>
                    <p class="text-muted mb-0"><?= nl2br(e($supplier['description'])) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <h5 class="fw-bold mb-3">Products (<?= count($products) ?>)</h5>
            <?php if (empty($products)): ?>
            <div class="alert alert-light">No products listed yet.</div>
            <?php else: ?>
            <div class="row row-cols-2 row-cols-md-3 g-3">
                <?php foreach ($products as $p): ?>
                <?php $imgs = json_decode($p['images'] ?? '[]', true); ?>
                <div class="col">
                    <div class="card h-100 border-0 shadow-sm">
                        <a href="/pages/product/detail.php?slug=<?= urlencode($p['slug']) ?>">
                            <img src="<?= !empty($imgs[0]) ? e(APP_URL.'/'.$imgs[0]) : 'https://via.placeholder.com/200?text=P' ?>"
                                 class="card-img-top" style="height:140px;object-fit:cover" alt="">
                        </a>
                        <div class="card-body py-2 px-2">
                            <p class="small fw-semibold mb-1"><?= e(mb_strimwidth($p['name'], 0, 40, '…')) ?></p>
                            <span class="text-primary small fw-bold"><?= formatMoney($p['price']) ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($products) >= 12): ?>
            <div class="text-center mt-3">
                <a href="/pages/product/index.php?supplier_id=<?= $supplier['id'] ?>" class="btn btn-outline-primary">View All Products</a>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
