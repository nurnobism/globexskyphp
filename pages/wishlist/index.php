<?php
/**
 * pages/wishlist/index.php — My Wishlist
 */
require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/wishlist.php';
require_once __DIR__ . '/../../includes/cart.php';

requireLogin();

$uid     = (int)$_SESSION['user_id'];
$page    = max(1, (int)get('page', 1));
$perPage = 20;
$sort    = get('sort', 'date_added');
$allowed_sorts = ['date_added', 'price_asc', 'price_desc', 'name'];
if (!in_array($sort, $allowed_sorts)) $sort = 'date_added';

$result = getWishlist($uid, $page, $perPage, $sort);
$items  = $result['items'];
$total  = $result['total'];
$totalPages = $result['total_pages'];

$pageTitle = 'My Wishlist';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <!-- Header row -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold mb-0">
                <i class="bi bi-heart-fill text-danger me-2"></i>My Wishlist
            </h3>
            <p class="text-muted small mb-0"><?= $total ?> saved item<?= $total !== 1 ? 's' : '' ?></p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <!-- Sort -->
            <form method="GET" class="d-flex align-items-center gap-2 mb-0">
                <label class="small fw-semibold mb-0 text-muted">Sort:</label>
                <select name="sort" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                    <option value="date_added"  <?= $sort === 'date_added'  ? 'selected' : '' ?>>Date Added</option>
                    <option value="price_asc"   <?= $sort === 'price_asc'   ? 'selected' : '' ?>>Price: Low → High</option>
                    <option value="price_desc"  <?= $sort === 'price_desc'  ? 'selected' : '' ?>>Price: High → Low</option>
                    <option value="name"        <?= $sort === 'name'        ? 'selected' : '' ?>>Name</option>
                </select>
            </form>
            <a href="<?= APP_URL ?>/pages/product/index.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-shop me-1"></i>Browse Products
            </a>
        </div>
    </div>

    <?php if (empty($items)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-heart display-3"></i>
        <h5 class="mt-3 fw-semibold">Your wishlist is empty</h5>
        <p>Save products you love and find them here later.</p>
        <a href="<?= APP_URL ?>/pages/product/index.php" class="btn btn-primary">
            <i class="bi bi-shop me-1"></i>Explore Products
        </a>
    </div>
    <?php else: ?>
    <div class="row g-4">
        <?php foreach ($items as $item):
            $img     = $item['image'] ? APP_URL . '/' . $item['image'] : 'https://via.placeholder.com/200?text=P';
            $inStock = (int)($item['stock_qty'] ?? 0) > 0;
        ?>
        <div class="col-sm-6 col-lg-4 col-xl-3" id="wl-item-<?= (int)$item['wishlist_id'] ?>">
            <div class="card h-100 border-0 shadow-sm overflow-hidden">
                <div class="position-relative" style="height:200px;overflow:hidden">
                    <a href="<?= APP_URL ?>/pages/product/detail.php?slug=<?= urlencode($item['slug']) ?>">
                        <img src="<?= e($img) ?>" alt="<?= e($item['name']) ?>" class="w-100 h-100" style="object-fit:cover">
                    </a>
                    <!-- Remove button -->
                    <button class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 rounded-circle d-flex align-items-center justify-content-center"
                            style="width:30px;height:30px;padding:0"
                            onclick="wishlistRemove(<?= (int)$item['product_id'] ?>, <?= (int)$item['wishlist_id'] ?>)"
                            title="Remove from wishlist">
                        <i class="bi bi-x-lg" style="font-size:.7rem"></i>
                    </button>
                    <?php if (!$inStock): ?>
                    <span class="badge bg-secondary position-absolute bottom-0 start-0 m-2">Out of Stock</span>
                    <?php elseif ((int)$item['stock_qty'] <= 5): ?>
                    <span class="badge bg-warning text-dark position-absolute bottom-0 start-0 m-2">Only <?= (int)$item['stock_qty'] ?> left</span>
                    <?php endif; ?>
                </div>
                <div class="card-body d-flex flex-column py-3">
                    <a href="<?= APP_URL ?>/pages/product/detail.php?slug=<?= urlencode($item['slug']) ?>"
                       class="text-decoration-none text-dark fw-semibold mb-1 lh-sm" style="font-size:.9rem">
                        <?= e(mb_strimwidth($item['name'], 0, 55, '…')) ?>
                    </a>
                    <?php if (!empty($item['supplier_name'])): ?>
                    <small class="text-muted mb-2"><?= e($item['supplier_name']) ?></small>
                    <?php endif; ?>
                    <small class="text-muted mb-2">Added <?= formatDate($item['added_at']) ?></small>
                    <div class="fw-bold text-primary mb-3 mt-auto fs-5">
                        <?= formatMoney((float)$item['price'], $item['currency'] ?? 'USD') ?>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-fill"
                                onclick="moveToCart(<?= (int)$item['product_id'] ?>, <?= (int)$item['wishlist_id'] ?>)"
                                <?= $inStock ? '' : 'disabled' ?>>
                            <i class="bi bi-cart-plus me-1"></i>Move to Cart
                        </button>
                        <a href="<?= APP_URL ?>/pages/product/detail.php?slug=<?= urlencode($item['slug']) ?>"
                           class="btn btn-outline-secondary btn-sm" title="View product">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="mt-5">
        <ul class="pagination justify-content-center">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page - 1 ?>&sort=<?= urlencode($sort) ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $p ?>&sort=<?= urlencode($sort) ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page + 1 ?>&sort=<?= urlencode($sort) ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>

    <?php endif; ?>
</div>

<!-- Move to Cart variation modal -->
<div class="modal fade" id="moveToCartModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold">Move to Cart</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Quantity</label>
                    <div class="input-group" style="max-width:140px">
                        <button class="btn btn-outline-secondary" onclick="moveQtyAdj(-1)"><i class="bi bi-dash"></i></button>
                        <input type="number" id="moveQty" class="form-control text-center" value="1" min="1">
                        <button class="btn btn-outline-secondary" onclick="moveQtyAdj(1)"><i class="bi bi-plus"></i></button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="confirmMoveBtn">
                    <i class="bi bi-cart-plus me-1"></i>Move to Cart
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF      = '<?= e(csrfToken()) ?>';
const WISH_API  = '<?= APP_URL ?>/api/wishlist.php';
const CART_API  = '<?= APP_URL ?>/api/cart.php';

let _movePid = 0, _moveWid = 0;

function wishlistRemove(productId, wishlistId) {
    if (!confirm('Remove this item from your wishlist?')) return;
    const body = new URLSearchParams({ _csrf_token: CSRF, product_id: productId });
    fetch(`${WISH_API}?action=remove`, { method: 'POST', body })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                const el = document.getElementById('wl-item-' + wishlistId);
                if (el) { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; setTimeout(() => el.remove(), 300); }
                updateWishlistBadge(res.count);
            } else {
                alert(res.message || 'Failed to remove.');
            }
        }).catch(() => alert('Network error.'));
}

function moveToCart(productId, wishlistId) {
    _movePid = productId;
    _moveWid = wishlistId;
    document.getElementById('moveQty').value = 1;
    const modal = new bootstrap.Modal(document.getElementById('moveToCartModal'));
    modal.show();

    document.getElementById('confirmMoveBtn').onclick = () => {
        const qty = Math.max(1, parseInt(document.getElementById('moveQty').value));
        const body = new URLSearchParams({ _csrf_token: CSRF, product_id: _movePid, quantity: qty });
        fetch(`${WISH_API}?action=move_to_cart`, { method: 'POST', body })
            .then(r => r.json())
            .then(res => {
                modal.hide();
                if (res.success) {
                    const el = document.getElementById('wl-item-' + _moveWid);
                    if (el) { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; setTimeout(() => el.remove(), 300); }
                    updateWishlistBadge(res.wishlist_count);
                    updateCartBadge(res.cart_count);
                    showToast('Item moved to cart!', 'success');
                } else {
                    alert(res.message || 'Failed to move to cart.');
                }
            }).catch(() => alert('Network error.'));
    };
}

function moveQtyAdj(delta) {
    const input = document.getElementById('moveQty');
    input.value = Math.max(1, parseInt(input.value) + delta);
}

function showToast(msg, type = 'success') {
    const toastHtml = `
        <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0 show" role="alert" style="min-width:200px">
            <div class="d-flex">
                <div class="toast-body">${msg}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>`;
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
    container.insertAdjacentHTML('beforeend', toastHtml);
    setTimeout(() => container.querySelector('.toast')?.remove(), 3000);
}
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
