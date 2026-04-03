<?php
/**
 * pages/dropshipping/store-preview.php — Public Storefront
 * Accessible without auth: /pages/dropshipping/store-preview.php?store=store-slug
 */
require_once __DIR__ . '/../../includes/middleware.php';

$db   = getDB();
$slug = trim(get('store', ''));

// Load store by slug
$store = null;
if ($slug) {
    try {
        $stmt = $db->prepare('SELECT ds.*, u.first_name, u.last_name
            FROM dropship_stores ds
            LEFT JOIN users u ON u.id = ds.user_id
            WHERE ds.store_slug = ? AND ds.is_active = 1 LIMIT 1');
        $stmt->execute([$slug]);
        $store = $stmt->fetch() ?: null;
    } catch (PDOException $e) { /* ignore */ }
}

if (!$store) {
    $pageTitle = 'Store Not Found';
    include __DIR__ . '/../../includes/header.php';
    echo '<div class="container py-5 text-center"><i class="bi bi-shop display-1 text-muted"></i>';
    echo '<h3 class="mt-3">Store not found</h3><p class="text-muted">The store you are looking for does not exist or is inactive.</p>';
    echo '<a href="' . APP_URL . '/" class="btn btn-primary">Go Home</a></div>';
    include __DIR__ . '/../../includes/footer.php';
    exit;
}

$storeId    = (int)$store['id'];
$themeColor = $store['theme_color'] ?: '#0d6efd';

// Load active products
$page    = max(1, (int)get('page', 1));
$perPage = 24;
$offset  = ($page - 1) * $perPage;
$products = [];
$totalProducts = 0;

try {
    $countStmt = $db->prepare('SELECT COUNT(*) FROM dropship_products WHERE store_id = ? AND is_active = 1');
    $countStmt->execute([$storeId]);
    $totalProducts = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare('SELECT dp.*, p.images AS original_images, p.slug AS product_slug
        FROM dropship_products dp
        LEFT JOIN products p ON p.id = dp.original_product_id
        WHERE dp.store_id = ? AND dp.is_active = 1
        ORDER BY dp.import_date DESC
        LIMIT ? OFFSET ?');
    $stmt->execute([$storeId, $perPage, $offset]);
    $products = $stmt->fetchAll();
} catch (PDOException $e) { /* ignore */ }

$totalPages = (int)ceil($totalProducts / $perPage);

$pageTitle = e($store['store_name']) . ' — Store';
include __DIR__ . '/../../includes/header.php';
?>

<style>
  .store-header { background: <?= e($themeColor) ?>; min-height: 200px; position: relative; }
  .store-header .overlay { background: linear-gradient(135deg, rgba(0,0,0,.4) 0%, rgba(0,0,0,.1) 100%); position: absolute; inset:0; }
  .store-header-content { position: relative; z-index: 1; }
  .product-card:hover { transform: translateY(-3px); transition: .2s; box-shadow: 0 .5rem 1rem rgba(0,0,0,.1) !important; }
</style>

<!-- Store Header / Banner -->
<div class="store-header d-flex align-items-center text-white"
  <?php if (!empty($store['banner_url'])): ?>
    style="background: url('<?= e($store['banner_url']) ?>') center/cover no-repeat, <?= e($themeColor) ?>;"
  <?php endif; ?>>
  <div class="overlay"></div>
  <div class="container store-header-content py-5">
    <div class="d-flex align-items-center gap-4">
      <?php if (!empty($store['logo_url'])): ?>
        <img src="<?= e($store['logo_url']) ?>" alt="Logo"
             class="rounded-circle bg-white p-1" style="width:80px;height:80px;object-fit:cover;">
      <?php else: ?>
        <div class="bg-white rounded-circle d-flex align-items-center justify-content-center"
             style="width:80px;height:80px;">
          <i class="bi bi-shop fs-1" style="color:<?= e($themeColor) ?>"></i>
        </div>
      <?php endif; ?>
      <div>
        <h2 class="fw-bold mb-1"><?= e($store['store_name']) ?></h2>
        <?php if (!empty($store['store_description'])): ?>
          <p class="mb-0 opacity-75"><?= e($store['store_description']) ?></p>
        <?php endif; ?>
        <div class="mt-2 small opacity-75">
          <i class="bi bi-box-seam me-1"></i><?= number_format($totalProducts) ?> Products
          <span class="mx-2">•</span>
          <i class="bi bi-bag-check me-1"></i><?= number_format($store['total_orders'] ?? 0) ?> Orders
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Products Grid -->
<div class="container py-4">
  <?php if (empty($products)): ?>
    <div class="text-center py-5 text-muted">
      <i class="bi bi-inbox display-3"></i>
      <h5 class="mt-3">No products yet</h5>
      <p>This store hasn't added any products yet.</p>
    </div>
  <?php else: ?>
  <div class="row g-3">
    <?php foreach ($products as $p):
      $images = json_decode($p['custom_images'] ?? '[]', true);
      if (empty($images)) $images = json_decode($p['original_images'] ?? '[]', true);
      $img = !empty($images[0]) ? APP_URL . '/' . $images[0] : 'https://placehold.co/280x200/e9ecef/6c757d?text=Product';
      $title = !empty($p['custom_title']) ? $p['custom_title'] : 'Product';
    ?>
    <div class="col-6 col-md-4 col-lg-3">
      <div class="card border-0 shadow-sm h-100 product-card">
        <div style="height:180px;overflow:hidden;background:#f8f9fa;">
          <img src="<?= e($img) ?>" class="w-100 h-100" style="object-fit:cover;" alt="<?= e($title) ?>">
        </div>
        <div class="card-body d-flex flex-column">
          <h6 class="fw-semibold mb-2" style="font-size:.85rem;line-height:1.3;">
            <?= e(mb_strimwidth($title, 0, 60, '…')) ?>
          </h6>
          <div class="mt-auto">
            <div class="fw-bold fs-5" style="color:<?= e($themeColor) ?>"><?= formatMoney($p['selling_price']) ?></div>
            <?php if ((float)$p['original_price'] < (float)$p['selling_price']): ?>
            <div class="text-muted small text-decoration-line-through"><?= formatMoney($p['original_price']) ?></div>
            <?php endif; ?>
            <form method="POST" action="<?= APP_URL ?>/api/cart.php?action=add" class="mt-2">
              <?= csrfField() ?>
              <input type="hidden" name="product_id" value="<?= (int)$p['original_product_id'] ?>">
              <input type="hidden" name="quantity" value="1">
              <button type="submit" class="btn btn-sm w-100 text-white" style="background:<?= e($themeColor) ?>">
                <i class="bi bi-cart-plus me-1"></i>Add to Cart
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <nav class="mt-4">
    <ul class="pagination justify-content-center">
      <?php for ($i = 1; $i <= min($totalPages, 10); $i++): ?>
      <li class="page-item <?= $i === $page ? 'active' : '' ?>">
        <a class="page-link" href="?store=<?= urlencode($slug) ?>&page=<?= $i ?>"><?= $i ?></a>
      </li>
      <?php endfor; ?>
    </ul>
  </nav>
  <?php endif; ?>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
