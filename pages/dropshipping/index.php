<?php
/**
 * pages/dropshipping/index.php — Dropshipping Product Catalog
 */
require_once __DIR__ . '/../../includes/middleware.php';

$db       = getDB();
$page     = max(1, (int)get('page', 1));
$q        = trim(get('q', ''));
$category = get('category', '');

$where  = ['p.status = "active"', 'p.dropship_eligible = 1'];
$params = [];

if ($q) {
    $where[]  = 'p.name LIKE ?';
    $params[] = '%' . $q . '%';
}
if ($category) {
    $where[]  = 'p.category_id = ?';
    $params[] = $category;
}

$whereClause = implode(' AND ', $where);
$sql = "
    SELECT p.id, p.name, p.slug, p.images, p.unit, p.cost_price, p.price, p.short_desc,
           s.company_name AS supplier_name,
           c.name AS category_name, c.id AS category_id,
           ROUND(p.cost_price * (1 + COALESCE(mr.markup_pct, 50) / 100), 2) AS suggested_retail,
           COALESCE(mr.markup_pct, 50) AS default_markup_pct
    FROM products p
    LEFT JOIN suppliers s  ON s.id  = p.supplier_id
    LEFT JOIN categories c ON c.id  = p.category_id
    LEFT JOIN dropship_markup_rules mr ON mr.category_id = p.category_id
    WHERE $whereClause
    ORDER BY p.created_at DESC
";

$result   = paginate($db, $sql, $params, $page);
$products = $result['data'];

$catStmt    = $db->query('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name');
$categories = $catStmt->fetchAll();

$pageTitle = 'Dropshipping Catalog';
include __DIR__ . '/../../includes/header.php';
?>

<style>
  :root { --ds-primary: #FF6B35; --ds-secondary: #1B2A4A; }
  .ds-badge-margin { background: #e8f5e9; color: #2e7d32; font-size:.75rem; }
  .product-card { transition: transform .15s, box-shadow .15s; }
  .product-card:hover { transform: translateY(-3px); box-shadow: 0 .5rem 1.5rem rgba(0,0,0,.12) !important; }
  .btn-import { background: var(--ds-primary); border-color: var(--ds-primary); color: #fff; }
  .btn-import:hover { background: #e55a24; border-color: #e55a24; color: #fff; }
  .badge-ds { background: var(--ds-secondary); }
  .sidebar-header { background: var(--ds-secondary); }
</style>

<div class="container-fluid py-4 px-4">
  <div class="row g-4">

    <!-- Sidebar -->
    <div class="col-lg-3 col-xl-2">
      <div class="card border-0 shadow-sm sticky-top" style="top:80px">
        <div class="card-header sidebar-header text-white py-3">
          <h6 class="mb-0 fw-bold"><i class="bi bi-box-seam me-2"></i>Dropshipping</h6>
        </div>
        <div class="card-body p-3">
          <form method="GET" id="filterForm">
            <div class="mb-3">
              <input type="search" name="q" class="form-control form-control-sm"
                     placeholder="Search products…" value="<?= e($q) ?>">
            </div>
            <h6 class="text-uppercase fw-semibold small text-muted mb-2">Category</h6>
            <div class="d-flex flex-column gap-1 mb-3">
              <a href="?" class="text-decoration-none small <?= !$category ? 'fw-bold' : 'text-muted' ?>"
                 style="<?= !$category ? 'color:var(--ds-primary)' : '' ?>">
                All Categories
              </a>
              <?php foreach ($categories as $cat): ?>
              <a href="?category=<?= urlencode($cat['id']) ?><?= $q ? '&q=' . urlencode($q) : '' ?>"
                 class="text-decoration-none small <?= $category == $cat['id'] ? 'fw-bold' : 'text-muted' ?>"
                 style="<?= $category == $cat['id'] ? 'color:var(--ds-primary)' : '' ?>">
                <?= e($cat['name']) ?>
              </a>
              <?php endforeach; ?>
            </div>
            <button type="submit" class="btn btn-sm w-100 btn-import">
              <i class="bi bi-search me-1"></i>Search
            </button>
          </form>
          <hr>
          <a href="/pages/dropshipping/dashboard.php" class="btn btn-sm btn-outline-secondary w-100 mb-2">
            <i class="bi bi-speedometer2 me-1"></i>My Dashboard
          </a>
          <a href="/pages/dropshipping/settings.php" class="btn btn-sm btn-outline-secondary w-100">
            <i class="bi bi-gear me-1"></i>Settings
          </a>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="col-lg-9 col-xl-10">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
          <h4 class="mb-0 fw-bold" style="color:var(--ds-secondary)">
            <?= $q ? 'Results for "' . e($q) . '"' : 'Dropshipping Catalog' ?>
          </h4>
          <small class="text-muted"><?= number_format($result['total']) ?> products available for dropshipping</small>
        </div>
        <span class="badge badge-ds text-white px-3 py-2 fs-6">
          <i class="bi bi-truck me-1"></i>No Inventory Required
        </span>
      </div>

      <?php if (empty($products)): ?>
        <div class="text-center py-5 text-muted">
          <i class="bi bi-box-seam display-1"></i>
          <h5 class="mt-3">No dropshippable products found</h5>
          <a href="/pages/dropshipping/index.php" class="btn btn-import mt-2">View All Products</a>
        </div>
      <?php else: ?>

      <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-3">
        <?php foreach ($products as $p):
          $images   = json_decode($p['images'] ?? '[]', true);
          $img      = !empty($images[0]) ? e(APP_URL . '/' . $images[0]) : 'https://placehold.co/300x200/1B2A4A/white?text=' . rawurlencode($p['name']);
          $cost     = (float)$p['cost_price'];
          $retail   = (float)$p['suggested_retail'];
          $margin   = $retail > 0 ? round((($retail - $cost) / $retail) * 100) : 0;
          $markup   = (float)$p['default_markup_pct'];
        ?>
        <div class="col">
          <div class="card h-100 border-0 shadow-sm product-card">
            <div class="position-relative">
              <img src="<?= $img ?>" class="card-img-top"
                   style="height:160px;object-fit:cover;" alt="<?= e($p['name']) ?>">
              <span class="position-absolute top-0 end-0 m-2 badge ds-badge-margin">
                <?= $margin ?>% margin
              </span>
            </div>
            <div class="card-body d-flex flex-column p-3">
              <small class="text-muted mb-1"><?= e($p['category_name'] ?? '') ?></small>
              <h6 class="card-title mb-1 lh-sm">
                <?= e(mb_strimwidth($p['name'], 0, 55, '…')) ?>
              </h6>
              <p class="text-muted small mb-2">
                <i class="bi bi-building me-1"></i><?= e(mb_strimwidth($p['supplier_name'] ?? '', 0, 35, '…')) ?>
              </p>

              <div class="row g-0 mb-3 mt-auto">
                <div class="col-6 border-end pe-2">
                  <div class="text-muted" style="font-size:.7rem">COST PRICE</div>
                  <div class="fw-bold text-dark"><?= formatMoney($cost) ?></div>
                </div>
                <div class="col-6 ps-2">
                  <div class="text-muted" style="font-size:.7rem">RETAIL PRICE</div>
                  <div class="fw-bold" style="color:var(--ds-primary)"><?= formatMoney($retail) ?></div>
                </div>
              </div>

              <!-- Markup slider -->
              <div class="mb-3">
                <label class="form-label small text-muted mb-1 d-flex justify-content-between">
                  <span>Markup</span>
                  <span id="markup-val-<?= $p['id'] ?>"><?= $markup ?>%</span>
                </label>
                <input type="range" class="form-range markup-slider" min="0" max="200" step="5"
                       value="<?= $markup ?>"
                       data-product-id="<?= $p['id'] ?>"
                       data-cost="<?= $cost ?>"
                       id="slider-<?= $p['id'] ?>">
                <div class="text-end small text-muted">
                  Sell for: <strong id="sell-price-<?= $p['id'] ?>"><?= formatMoney($retail) ?></strong>
                </div>
              </div>

              <button class="btn btn-import btn-sm w-100 import-btn"
                      data-product-id="<?= $p['id'] ?>"
                      data-product-name="<?= e($p['name']) ?>">
                <i class="bi bi-cloud-download me-1"></i>Import to Store
              </button>
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
            <a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($q) ?>&category=<?= urlencode($category) ?>">
              <?= $i ?>
            </a>
          </li>
          <?php endfor; ?>
        </ul>
      </nav>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Toast notification -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:9999">
  <div id="importToast" class="toast align-items-center text-white border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body" id="toastMsg">Product imported!</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<script>
const CSRF = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;

// Markup sliders — update displayed price live
document.querySelectorAll('.markup-slider').forEach(slider => {
  slider.addEventListener('input', () => {
    const pid   = slider.dataset.productId;
    const cost  = parseFloat(slider.dataset.cost);
    const pct   = parseFloat(slider.value);
    const sell  = (cost * (1 + pct / 100)).toFixed(2);
    document.getElementById('markup-val-'  + pid).textContent = pct + '%';
    document.getElementById('sell-price-' + pid).textContent  = '$' + sell;
  });
});

// Import button
document.querySelectorAll('.import-btn').forEach(btn => {
  btn.addEventListener('click', async () => {
    const pid    = btn.dataset.productId;
    const name   = btn.dataset.productName;
    const slider = document.getElementById('slider-' + pid);
    const markup = slider ? parseFloat(slider.value) : 50;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Importing…';

    const body = new URLSearchParams({ csrf_token: CSRF, product_id: pid, markup_pct: markup });

    try {
      const res  = await fetch('/api/dropshipping.php?action=import', { method: 'POST', body });
      const data = await res.json();
      showToast(data.message || (data.success ? 'Imported!' : data.error), data.success ? 'bg-success' : 'bg-warning');
      if (data.success) {
        btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Imported';
      } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-cloud-download me-1"></i>Import to Store';
      }
    } catch (e) {
      showToast('Network error — please try again', 'bg-danger');
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-cloud-download me-1"></i>Import to Store';
    }
  });
});

function showToast(msg, bgClass) {
  const toast = document.getElementById('importToast');
  document.getElementById('toastMsg').textContent = msg;
  toast.className = 'toast align-items-center text-white border-0 ' + bgClass;
  bootstrap.Toast.getOrCreateInstance(toast).show();
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
