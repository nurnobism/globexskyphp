<?php
/**
 * pages/dropshipping/products.php — Browse Dropship Catalog
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();
require_once __DIR__ . '/../../includes/dropshipping.php';

$db       = getDB();
$page     = max(1, (int)get('page', 1));
$perPage  = 20;

$filters = [
    'q'          => trim(get('q', '')),
    'category'   => get('category', ''),
    'min_price'  => get('min_price', ''),
    'max_price'  => get('max_price', ''),
    'sort'       => get('sort', 'newest'),
];

$catalog = getDropshipCatalog($filters, $page, $perPage);
$products = $catalog['products'];
$total    = $catalog['total'];
$pages    = $catalog['pages'];

// Categories for filter
$categories = [];
try {
    $categories = $db->query('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name')->fetchAll();
} catch (PDOException $e) { /* ignore */ }

$planLimits = checkDropshipPlanLimits((int)$_SESSION['user_id']);

$pageTitle = 'Browse Dropship Catalog';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4 px-4">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
      <h4 class="fw-bold mb-0 text-primary"><i class="bi bi-grid me-2"></i>Dropship Catalog</h4>
      <small class="text-muted"><?= number_format($total) ?> products available for dropshipping</small>
    </div>
    <a href="<?= APP_URL ?>/pages/dropshipping/index.php" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Dashboard
    </a>
  </div>

  <?php if (!$planLimits['allowed']): ?>
  <div class="alert alert-warning mb-4">
    <i class="bi bi-lock me-2"></i>Upgrade to <strong>Pro</strong> or <strong>Enterprise</strong> to import products.
    <a href="<?= APP_URL ?>/pages/supplier/plans.php" class="btn btn-warning btn-sm ms-2">Upgrade</a>
  </div>
  <?php endif; ?>

  <div class="row g-4">
    <!-- Filters Sidebar -->
    <div class="col-lg-3">
      <div class="card border-0 shadow-sm sticky-top" style="top:80px">
        <div class="card-header bg-white border-0"><h6 class="fw-bold mb-0"><i class="bi bi-funnel me-2"></i>Filters</h6></div>
        <div class="card-body">
          <form method="GET" action="">
            <div class="mb-3">
              <label class="form-label small fw-semibold">Search</label>
              <input type="text" name="q" value="<?= e($filters['q']) ?>" class="form-control form-control-sm" placeholder="Search products...">
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Category</label>
              <select name="category" class="form-select form-select-sm">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= $cat['id'] ?>" <?= $filters['category'] == $cat['id'] ? 'selected' : '' ?>>
                    <?= e($cat['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Price Range (Supplier)</label>
              <div class="d-flex gap-2">
                <input type="number" name="min_price" value="<?= e($filters['min_price']) ?>" class="form-control form-control-sm" placeholder="Min">
                <input type="number" name="max_price" value="<?= e($filters['max_price']) ?>" class="form-control form-control-sm" placeholder="Max">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Sort By</label>
              <select name="sort" class="form-select form-select-sm">
                <option value="newest" <?= $filters['sort']==='newest'?'selected':'' ?>>Newest</option>
                <option value="price_asc" <?= $filters['sort']==='price_asc'?'selected':'' ?>>Price: Low to High</option>
                <option value="price_desc" <?= $filters['sort']==='price_desc'?'selected':'' ?>>Price: High to Low</option>
                <option value="popular" <?= $filters['sort']==='popular'?'selected':'' ?>>Most Popular</option>
              </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm w-100">Apply Filters</button>
            <a href="?" class="btn btn-outline-secondary btn-sm w-100 mt-2">Clear</a>
          </form>
        </div>
      </div>
    </div>

    <!-- Product Grid -->
    <div class="col-lg-9">
      <?php if (empty($products)): ?>
        <div class="text-center py-5 text-muted">
          <i class="bi bi-search display-4"></i>
          <p class="mt-3">No products found. Try adjusting your filters.</p>
        </div>
      <?php else: ?>
      <div class="row g-3">
        <?php foreach ($products as $p):
          $images = is_array($p['images']) ? $p['images'] : json_decode($p['images'] ?? '[]', true);
          $img    = !empty($images[0]) ? APP_URL . '/' . $images[0] : 'https://placehold.co/280x200/e9ecef/6c757d?text=Product';
          $suggestedRetail = round((float)$p['cost_price'] * 1.20, 2);
          $profit = round($suggestedRetail - (float)$p['cost_price'], 2);
        ?>
        <div class="col-md-6 col-xl-4">
          <div class="card border-0 shadow-sm h-100 product-card">
            <div style="height:180px;overflow:hidden;background:#f8f9fa;">
              <img src="<?= e($img) ?>" class="w-100 h-100" style="object-fit:cover;" alt="<?= e($p['name']) ?>">
            </div>
            <div class="card-body d-flex flex-column">
              <div class="small text-muted mb-1"><?= e($p['category_name'] ?? '') ?> • <?= e($p['supplier_name'] ?? 'Supplier') ?></div>
              <h6 class="fw-semibold mb-2" style="font-size:.9rem;line-height:1.3">
                <?= e(mb_strimwidth($p['name'], 0, 70, '…')) ?>
              </h6>
              <div class="mt-auto">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <div>
                    <div class="small text-muted">Supplier Price</div>
                    <div class="fw-bold text-primary"><?= formatMoney($p['cost_price']) ?></div>
                  </div>
                  <div class="text-end">
                    <div class="small text-muted">Suggested Retail</div>
                    <div class="fw-bold text-success"><?= formatMoney($suggestedRetail) ?></div>
                  </div>
                </div>
                <div class="text-center text-success small mb-2">
                  <i class="bi bi-cash-coin me-1"></i>Est. profit: <?= formatMoney($profit) ?>/sale
                </div>
                <?php if (($p['order_count'] ?? 0) > 0): ?>
                <div class="text-muted small text-center mb-2">
                  <i class="bi bi-bag-check me-1"></i><?= number_format($p['order_count']) ?> orders
                </div>
                <?php endif; ?>
                <?php if ($planLimits['allowed']): ?>
                <a href="<?= APP_URL ?>/pages/dropshipping/import.php?product_id=<?= $p['id'] ?>" class="btn btn-primary btn-sm w-100">
                  <i class="bi bi-download me-1"></i>Import Product
                </a>
                <?php else: ?>
                <button class="btn btn-secondary btn-sm w-100" disabled>
                  <i class="bi bi-lock me-1"></i>Upgrade to Import
                </button>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Pagination -->
      <?php if ($pages > 1): ?>
      <nav class="mt-4">
        <ul class="pagination justify-content-center">
          <?php for ($i = 1; $i <= min($pages, 10); $i++):
            $q = array_merge($filters, ['page' => $i]);
            $qs = http_build_query($q);
          ?>
          <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link" href="?<?= $qs ?>"><?= $i ?></a>
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
