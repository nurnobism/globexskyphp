<?php
/**
 * pages/wishlist/index.php — My Wishlist
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$db  = getDB();
$uid = $_SESSION['user_id'];

$stmt = $db->prepare(
    'SELECT w.id, w.added_at created_at, p.id product_id, p.name, p.slug, p.price, p.images, p.stock_qty, p.currency,
            s.company_name supplier_name
     FROM wishlist_items w
     JOIN products p ON p.id = w.product_id
     LEFT JOIN suppliers s ON s.id = p.supplier_id
     WHERE w.user_id = ?
     ORDER BY w.added_at DESC'
);
$stmt->execute([$uid]);
$items = $stmt->fetchAll();

$pageTitle = 'My Wishlist';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold mb-0"><i class="bi bi-heart-fill text-danger me-2"></i>My Wishlist</h3>
            <p class="text-muted small mb-0"><?= count($items) ?> saved item<?= count($items) !== 1 ? 's' : '' ?></p>
        </div>
        <a href="/pages/product/index.php" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-shop me-1"></i>Continue Shopping
        </a>
    </div>

    <?php if (empty($items)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-heart display-3"></i>
        <h5 class="mt-3">Your wishlist is empty</h5>
        <p>Save products you love to your wishlist and find them here later.</p>
        <a href="/pages/product/index.php" class="btn btn-primary">
            <i class="bi bi-shop me-1"></i>Explore Products
        </a>
    </div>
    <?php else: ?>
    <div class="row g-4">
        <?php foreach ($items as $item): ?>
        <?php
        $imgArr = is_string($item['images'] ?? '') ? json_decode($item['images'], true) : ($item['images'] ?? []);
        $img    = (is_array($imgArr) && !empty($imgArr[0])) ? APP_URL . '/' . $imgArr[0] : 'https://via.placeholder.com/200?text=P';
        $inStock = (int)($item['stock_qty'] ?? 0) > 0;
        ?>
        <div class="col-sm-6 col-lg-4 col-xl-3" id="wl-item-<?= (int)$item['id'] ?>">
            <div class="card h-100 border-0 shadow-sm overflow-hidden">
                <div class="position-relative" style="height:200px;overflow:hidden">
                    <a href="/pages/product/detail.php?slug=<?= urlencode($item['slug']) ?>">
                        <img src="<?= e($img) ?>" alt="" class="w-100 h-100" style="object-fit:cover">
                    </a>
                    <button class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 rounded-circle"
                            style="width:32px;height:32px;padding:0"
                            onclick="removeWishlist(<?= (int)$item['id'] ?>)"
                            title="Remove from wishlist">
                        <i class="bi bi-x-lg"></i>
                    </button>
                    <?php if (!$inStock): ?>
                    <span class="badge bg-secondary position-absolute bottom-0 start-0 m-2">Out of Stock</span>
                    <?php endif; ?>
                </div>
                <div class="card-body d-flex flex-column">
                    <a href="/pages/product/detail.php?slug=<?= urlencode($item['slug']) ?>"
                       class="text-decoration-none text-dark fw-semibold mb-1">
                        <?= e(mb_strimwidth($item['name'], 0, 50, '…')) ?>
                    </a>
                    <?php if (!empty($item['supplier_name'])): ?>
                    <small class="text-muted mb-2"><?= e($item['supplier_name']) ?></small>
                    <?php endif; ?>
                    <div class="fw-bold text-primary mb-3 mt-auto">
                        <?= formatMoney((float)$item['price'], $item['currency'] ?? 'USD') ?>
                    </div>
                    <form method="post" action="/api/cart.php?action=add" class="mb-0">
                        <input type="hidden" name="_csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="product_id" value="<?= (int)$item['product_id'] ?>">
                        <input type="hidden" name="quantity" value="1">
                        <input type="hidden" name="_redirect" value="/pages/cart/index.php">
                        <button type="submit" class="btn btn-primary w-100 btn-sm" <?= $inStock ? '' : 'disabled' ?>>
                            <i class="bi bi-cart-plus me-1"></i>Add to Cart
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function removeWishlist(id) {
    if (!confirm('Remove this item from your wishlist?')) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/api/wishlist.php?action=remove';
    const fields = {_csrf_token: '<?= e(csrfToken()) ?>', wishlist_id: id, _redirect: window.location.href};
    for (const [k, v] of Object.entries(fields)) {
        const i = document.createElement('input'); i.type = 'hidden'; i.name = k; i.value = v;
        form.appendChild(i);
    }
    document.body.appendChild(form);
    form.submit();
}
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
