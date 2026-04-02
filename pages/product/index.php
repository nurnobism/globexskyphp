<?php
require_once __DIR__ . '/../../includes/middleware.php';

$db       = getDB();
$page     = max(1, (int)get('page', 1));
$q        = trim(get('q', ''));
$category = get('category', '');
$sort     = get('sort', 'created_at');
$dir      = get('dir', 'desc');

$where  = ['p.status = "active"'];
$params = [];

if ($q) {
    $where[]  = 'MATCH(p.name, p.short_desc) AGAINST(? IN BOOLEAN MODE)';
    $params[] = $q . '*';
}
if ($category) {
    $where[]  = 'p.category_id = (SELECT id FROM categories WHERE slug = ? LIMIT 1)';
    $params[] = $category;
}

$allowedSorts = ['name','price','created_at','rating'];
if (!in_array($sort, $allowedSorts)) $sort = 'created_at';
$dir = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';

$sql    = "SELECT p.*, s.company_name supplier_name, s.slug supplier_slug, c.name category_name
           FROM products p
           LEFT JOIN suppliers s ON s.id = p.supplier_id
           LEFT JOIN categories c ON c.id = p.category_id
           WHERE " . implode(' AND ', $where) . " ORDER BY p.$sort $dir";
$result = paginate($db, $sql, $params, $page);
$products = $result['data'];

$catStmt = $db->query('SELECT id, name, slug FROM categories WHERE is_active=1 ORDER BY sort_order');
$categories = $catStmt->fetchAll();

$pageTitle = $q ? 'Search: ' . $q : 'Products';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="row g-4">
        <!-- Sidebar Filters -->
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm sticky-top" style="top:80px">
                <div class="card-header bg-primary text-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-funnel me-2"></i>Filters</h6>
                </div>
                <div class="card-body">
                    <form method="GET">
                        <?php if ($q): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
                        <h6 class="fw-semibold mb-2">Category</h6>
                        <div class="d-flex flex-column gap-1 mb-3">
                            <a href="?q=<?= urlencode($q) ?>" class="text-decoration-none <?= !$category ? 'fw-bold text-primary' : 'text-muted' ?>">All Categories</a>
                            <?php foreach ($categories as $cat): ?>
                            <a href="?category=<?= urlencode($cat['slug']) ?><?= $q ? '&q=' . urlencode($q) : '' ?>"
                               class="text-decoration-none <?= $category === $cat['slug'] ? 'fw-bold text-primary' : 'text-muted' ?> small">
                                <?= e($cat['name']) ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <h6 class="fw-semibold mb-2">Sort By</h6>
                        <select name="sort" class="form-select form-select-sm mb-2" onchange="this.form.submit()">
                            <option value="created_at" <?= $sort==='created_at'?'selected':'' ?>>Newest</option>
                            <option value="price"      <?= $sort==='price'?'selected':'' ?>>Price</option>
                            <option value="rating"     <?= $sort==='rating'?'selected':'' ?>>Rating</option>
                            <option value="name"       <?= $sort==='name'?'selected':'' ?>>Name</option>
                        </select>
                        <select name="dir" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="desc" <?= $dir==='DESC'?'selected':'' ?>>Descending</option>
                            <option value="asc"  <?= $dir==='ASC'?'selected':'' ?>>Ascending</option>
                        </select>
                    </form>
                </div>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <?php if ($q): ?><h5 class="mb-0">Results for "<strong><?= e($q) ?></strong>"</h5><?php else: ?><h5 class="mb-0">All Products</h5><?php endif; ?>
                    <small class="text-muted"><?= $result['total'] ?> products found</small>
                </div>
            </div>

            <?php if (empty($products)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-box-seam display-1 text-muted"></i>
                    <h5 class="mt-3">No products found</h5>
                    <a href="/pages/product/index.php" class="btn btn-outline-primary mt-2">View All Products</a>
                </div>
            <?php else: ?>
            <div class="row row-cols-2 row-cols-md-3 g-3">
                <?php foreach ($products as $p): ?>
                <div class="col">
                    <div class="card h-100 border-0 shadow-sm product-card">
                        <a href="/pages/product/detail.php?slug=<?= urlencode($p['slug']) ?>">
                            <?php $images = json_decode($p['images'] ?? '[]', true); $img = $images[0] ?? null; ?>
                            <img src="<?= $img ? e(APP_URL . '/' . $img) : 'https://via.placeholder.com/300x200?text=' . urlencode($p['name']) ?>"
                                 class="card-img-top" style="height:180px;object-fit:cover;" alt="<?= e($p['name']) ?>">
                        </a>
                        <div class="card-body d-flex flex-column">
                            <small class="text-muted"><?= e($p['category_name'] ?? '') ?></small>
                            <h6 class="card-title mt-1 mb-1">
                                <a href="/pages/product/detail.php?slug=<?= urlencode($p['slug']) ?>" class="text-decoration-none text-dark">
                                    <?= e(mb_strimwidth($p['name'], 0, 60, '…')) ?>
                                </a>
                            </h6>
                            <p class="text-muted small mb-2"><?= e(mb_strimwidth($p['supplier_name'] ?? '', 0, 40, '…')) ?></p>
                            <?php if ($p['rating'] > 0): ?>
                            <div class="text-warning small mb-1">
                                <?= str_repeat('★', starRating($p['rating'])) ?><?= str_repeat('☆', 5 - starRating($p['rating'])) ?>
                                <span class="text-muted">(<?= $p['review_count'] ?>)</span>
                            </div>
                            <?php endif; ?>
                            <div class="mt-auto pt-2 d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fw-bold text-primary fs-5"><?= formatMoney($p['price']) ?></span>
                                    <small class="text-muted">/ <?= e($p['unit'] ?? 'pc') ?></small>
                                </div>
                                <form method="POST" action="/api/cart.php?action=add">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="bi bi-cart-plus"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($result['pages'] > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($q) ?>&category=<?= urlencode($category) ?>&sort=<?= $sort ?>&dir=<?= strtolower($dir) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
