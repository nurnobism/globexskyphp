<?php
require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/variations.php';
require_once __DIR__ . '/../../includes/coupons.php';

$slug = get('slug', '');
$id   = (int)get('id', 0);

$db = getDB();
$stmt = $db->prepare('SELECT p.*, s.company_name supplier_name, s.slug supplier_slug, s.id supplier_id, s.rating supplier_rating, c.name category_name
    FROM products p
    LEFT JOIN suppliers s ON s.id = p.supplier_id
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE ' . ($slug ? 'p.slug=?' : 'p.id=?') . ' AND p.status="active"');
$stmt->execute([$slug ?: $id]);
$product = $stmt->fetch();
if (!$product) { http_response_code(404); flashMessage('danger', 'Product not found.'); redirect('/pages/product/index.php'); }

// Increment view count
$db->prepare('UPDATE products SET view_count=view_count+1 WHERE id=?')->execute([$product['id']]);

// Variation types + options (for buyer selector)
$variationTypes = getAvailableVariations($product['id']);
$hasVariations  = !empty($variationTypes);

// Variants (legacy product_variants table, fallback if no variation types)
$vStmt = $db->prepare('SELECT * FROM product_variants WHERE product_id=?');
$vStmt->execute([$product['id']]);
$variants = $vStmt->fetchAll();

// Reviews
$rStmt = $db->prepare('SELECT r.*, u.first_name, u.last_name, u.avatar FROM reviews r
    JOIN users u ON u.id=r.user_id WHERE r.product_id=? AND r.status="approved" ORDER BY r.created_at DESC LIMIT 10');
$rStmt->execute([$product['id']]);
$reviews = $rStmt->fetchAll();

// Related products
$relStmt = $db->prepare('SELECT p.*, s.company_name supplier_name FROM products p
    LEFT JOIN suppliers s ON s.id=p.supplier_id
    WHERE p.category_id=? AND p.id!=? AND p.status="active" ORDER BY RAND() LIMIT 6');
$relStmt->execute([$product['category_id'], $product['id']]);
$related = $relStmt->fetchAll();

$images  = json_decode($product['images'] ?? '[]', true) ?: [];
$specs   = json_decode($product['specifications'] ?? '{}', true) ?: [];

// Promotion check
$activePromotion = getActivePromotionForProduct((int)$product['id']);
$promoPrice = $activePromotion ? getPromotionPriceCalc((float)$product['price'], $activePromotion) : null;

$pageTitle = $product['name'];
$pageDesc  = $product['short_desc'] ?? $product['name'];
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item"><a href="/pages/product/index.php">Products</a></li>
            <?php if ($product['category_name']): ?>
            <li class="breadcrumb-item"><a href="/pages/product/index.php?category=<?= urlencode($product['category_id']) ?>"><?= e($product['category_name']) ?></a></li>
            <?php endif; ?>
            <li class="breadcrumb-item active"><?= e(mb_strimwidth($product['name'], 0, 40, '…')) ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <!-- Images -->
        <div class="col-lg-5">
            <div id="productCarousel" class="carousel slide border rounded shadow-sm" data-bs-ride="carousel">
                <div class="carousel-inner">
                    <?php if (!empty($images)): ?>
                    <?php foreach ($images as $i => $img): ?>
                    <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                        <img src="<?= e(APP_URL . '/' . $img) ?>" class="d-block w-100" style="height:380px;object-fit:contain;background:#f8f9fa" alt="Product Image <?= $i+1 ?>">
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div class="carousel-item active">
                        <img src="https://via.placeholder.com/500x380?text=<?= urlencode($product['name']) ?>" class="d-block w-100" style="height:380px;object-fit:contain;background:#f8f9fa" alt="Product">
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (count($images) > 1): ?>
                <button class="carousel-control-prev" type="button" data-bs-target="#productCarousel" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
                <button class="carousel-control-next" type="button" data-bs-target="#productCarousel" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
                <?php endif; ?>
            </div>
            <?php if (count($images) > 1): ?>
            <div class="d-flex gap-2 mt-2 overflow-auto">
                <?php foreach ($images as $i => $img): ?>
                <img src="<?= e(APP_URL . '/' . $img) ?>" class="border rounded cursor-pointer" width="60" height="60" style="object-fit:cover;cursor:pointer"
                     onclick="document.querySelectorAll('.carousel-item')[<?= $i ?>].parentElement.querySelectorAll('.carousel-item').forEach((el,j)=>{el.classList.toggle('active',j==<?= $i ?>)})">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Product Info -->
        <div class="col-lg-7">
            <?php if ($product['category_name']): ?>
            <span class="badge bg-light text-dark border mb-2"><?= e($product['category_name']) ?></span>
            <?php endif; ?>
            <h2 class="fw-bold mb-2"><?= e($product['name']) ?></h2>

            <?php if ($product['rating'] > 0): ?>
            <div class="d-flex align-items-center gap-2 mb-2">
                <div class="text-warning">
                    <?= str_repeat('★', starRating($product['rating'])) ?><?= str_repeat('☆', 5 - starRating($product['rating'])) ?>
                </div>
                <span class="text-muted"><?= number_format($product['rating'], 1) ?> (<?= $product['review_count'] ?> reviews)</span>
                <span class="text-muted">|</span>
                <span class="text-muted"><?= number_format($product['view_count']) ?> views</span>
            </div>
            <?php endif; ?>

            <div class="d-flex align-items-end gap-3 mb-3 flex-wrap">
                <?php if ($promoPrice !== null): ?>
                <span class="fs-2 fw-bold text-danger"><?= formatMoney($promoPrice) ?></span>
                <span class="fs-5 text-muted text-decoration-line-through"><?= formatMoney($product['price']) ?></span>
                <?php
                    $savePct = round((1 - $promoPrice / max(0.01, (float)$product['price'])) * 100);
                ?>
                <span class="badge bg-danger fs-6">🔥 <?= $savePct ?>% OFF</span>
                <?php else: ?>
                <span class="fs-2 fw-bold text-primary"><?= formatMoney($product['price']) ?></span>
                <?php if ($product['compare_price'] && $product['compare_price'] > $product['price']): ?>
                <span class="fs-5 text-muted text-decoration-line-through"><?= formatMoney($product['compare_price']) ?></span>
                <span class="badge bg-danger">SAVE <?= round((1 - $product['price']/$product['compare_price'])*100) ?>%</span>
                <?php endif; ?>
                <?php endif; ?>
                <span class="text-muted small">/ <?= e($product['unit'] ?? 'piece') ?></span>
            </div>
            <?php if ($activePromotion): ?>
            <div class="alert alert-warning py-2 px-3 mb-3 d-inline-flex align-items-center gap-2">
                <i class="bi bi-lightning-charge-fill text-warning"></i>
                <span>
                    <strong><?= e($activePromotion['name']) ?></strong>
                    — Sale ends
                    <strong id="promoCountdown" data-end="<?= e($activePromotion['end_date']) ?>"></strong>
                </span>
            </div>
            <?php endif; ?>

            <?php if ($product['short_desc']): ?>
            <p class="text-muted mb-3"><?= nl2br(e($product['short_desc'])) ?></p>
            <?php endif; ?>

            <div class="row g-2 mb-3">
                <div class="col-auto">
                    <span class="badge bg-<?= $product['stock_qty'] > 0 ? 'success' : 'danger' ?> py-2 px-3">
                        <i class="bi bi-<?= $product['stock_qty'] > 0 ? 'check-circle' : 'x-circle' ?> me-1"></i>
                        <?= $product['stock_qty'] > 0 ? 'In Stock (' . $product['stock_qty'] . ')' : 'Out of Stock' ?>
                    </span>
                </div>
                <div class="col-auto">
                    <span class="badge bg-light text-dark border py-2 px-3">
                        <i class="bi bi-box me-1"></i> Min. Order: <?= $product['min_order_qty'] ?> <?= e($product['unit'] ?? 'pc') ?>
                    </span>
                </div>
            </div>

            <!-- Variation Selector (product_variations system) -->
            <?php if ($hasVariations): ?>
            <div class="mb-3" id="variationSelector">
                <?php foreach ($variationTypes as $vt): ?>
                <div class="mb-2">
                    <label class="form-label fw-semibold"><?= e($vt['name']) ?></label>
                    <div class="d-flex flex-wrap gap-2" data-type-id="<?= $vt['id'] ?>">
                        <?php foreach ($vt['options'] as $opt): ?>
                        <button type="button"
                                class="btn btn-sm variation-option <?= $opt['is_available'] ? 'btn-outline-secondary' : 'btn-outline-secondary opacity-50' ?>"
                                data-type-id="<?= $vt['id'] ?>"
                                data-option-id="<?= $opt['id'] ?>"
                                data-value="<?= e($opt['value']) ?>"
                                <?= !$opt['is_available'] ? 'title="Out of stock"' : '' ?>>
                            <?= e($opt['value']) ?>
                            <?php if (!$opt['is_available']): ?>
                            <i class="bi bi-x-circle text-danger ms-1"></i>
                            <?php endif; ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <div id="skuInfo" class="mt-2" style="display:none">
                    <div class="alert alert-info py-2 small">
                        <span id="skuStockInfo"></span>
                        <span id="skuCodeInfo" class="ms-2 text-muted"></span>
                    </div>
                </div>
            </div>
            <!-- Legacy Variants fallback when no variation types -->
            <?php elseif (!empty($variants)): ?>
            <div class="mb-3">
                <label class="form-label fw-semibold">Options</label>
                <select id="variantSelect" class="form-select w-auto">
                    <option value="">Select option...</option>
                    <?php foreach ($variants as $v): ?>
                    <?php $attrs = json_decode($v['attributes'] ?? '{}', true); ?>
                    <option value="<?= $v['id'] ?>" data-price="<?= $v['price'] ?>">
                        <?= e(implode(', ', array_map(fn($k, $attrValue) => "$k: $attrValue", array_keys($attrs), $attrs))) ?>
                        <?php if ($v['price']): ?> — <?= formatMoney($v['price']) ?><?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Add to Cart -->
            <?php if ($product['stock_qty'] > 0 || $hasVariations): ?>
            <form id="addToCartForm" class="d-flex align-items-center gap-3 mb-3">
                <?= csrfField() ?>
                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                <input type="hidden" name="variant_id" id="variantId" value="">
                <input type="hidden" name="sku_id" id="skuId" value="">
                <div class="input-group" style="width:130px">
                    <button type="button" class="btn btn-outline-secondary" onclick="changeQty(-1)">-</button>
                    <input type="number" name="quantity" id="qtyInput" class="form-control text-center" value="<?= $product['min_order_qty'] ?>" min="<?= $product['min_order_qty'] ?>">
                    <button type="button" class="btn btn-outline-secondary" onclick="changeQty(1)">+</button>
                </div>
                <button type="submit" class="btn btn-primary btn-lg px-4" id="addToCartBtn"
                        <?= $hasVariations ? 'disabled title="Please select all options first"' : '' ?>>
                    <i class="bi bi-cart-plus me-1"></i> Add to Cart
                </button>
            </form>
            <?php endif; ?>

            <div class="d-flex gap-2 mb-4 flex-wrap">
                <!-- Wishlist toggle -->
                <?php if (isLoggedIn()):
                    $wlStatus = (function() use ($product) {
                        try {
                            $s = getDB()->prepare('SELECT id FROM wishlist_items WHERE user_id = ? AND product_id = ?');
                            $s->execute([$_SESSION['user_id'], $product['id']]);
                            return (bool)$s->fetch();
                        } catch (\Exception $e) { return false; }
                    })();
                ?>
                <button type="button"
                        id="wishlistBtn"
                        class="btn btn-<?= $wlStatus ? 'danger' : 'outline-danger' ?>"
                        onclick="toggleWishlist(<?= $product['id'] ?>)"
                        title="<?= $wlStatus ? 'Remove from wishlist' : 'Add to wishlist' ?>">
                    <i class="bi bi-heart<?= $wlStatus ? '-fill' : '' ?> me-1"></i>
                    <span id="wishlistBtnText"><?= $wlStatus ? 'Saved' : 'Wishlist' ?></span>
                </button>
                <?php else: ?>
                <a href="<?= APP_URL ?>/pages/auth/login.php" class="btn btn-outline-secondary"
                   title="Login to save to wishlist">
                    <i class="bi bi-heart me-1"></i> Login to Save
                </a>
                <?php endif; ?>

                <a href="<?= APP_URL ?>/pages/rfq/create.php?product_id=<?= $product['id'] ?>" class="btn btn-outline-primary">
                    <i class="bi bi-file-text me-1"></i> Request Quote
                </a>
                <?php if ($product['supplier_slug']): ?>
                <a href="<?= APP_URL ?>/pages/supplier/profile.php?slug=<?= urlencode($product['supplier_slug']) ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-shop me-1"></i> <?= e(mb_strimwidth($product['supplier_name'], 0, 25, '…')) ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tabs: Description, Specs, Reviews -->
    <ul class="nav nav-tabs mt-5" id="productTabs">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#description">Description</a></li>
        <?php if (!empty($specs)): ?><li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#specifications">Specifications</a></li><?php endif; ?>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#reviews">Reviews (<?= count($reviews) ?>)</a></li>
    </ul>
    <div class="tab-content border border-top-0 p-4 rounded-bottom shadow-sm mb-5">
        <div class="tab-pane fade show active" id="description">
            <?= $product['description'] ? nl2br(e($product['description'])) : '<p class="text-muted">No description available.</p>' ?>
        </div>
        <?php if (!empty($specs)): ?>
        <div class="tab-pane fade" id="specifications">
            <table class="table table-bordered">
                <tbody>
                <?php foreach ($specs as $key => $val): ?>
                <tr><th class="bg-light" style="width:30%"><?= e($key) ?></th><td><?= e(is_array($val) ? implode(', ', $val) : $val) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <div class="tab-pane fade" id="reviews">
            <?php if (!empty($reviews)): ?>
            <?php foreach ($reviews as $r): ?>
            <div class="border-bottom py-3">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <img src="<?= $r['avatar'] ? e(APP_URL . '/' . $r['avatar']) : 'https://ui-avatars.com/api/?name=' . urlencode($r['first_name']) . '&size=32' ?>"
                         class="rounded-circle" width="32" height="32">
                    <strong><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong>
                    <span class="text-warning"><?= str_repeat('★', $r['rating']) ?><?= str_repeat('☆', 5-$r['rating']) ?></span>
                    <small class="text-muted ms-auto"><?= formatDate($r['created_at']) ?></small>
                </div>
                <?php if ($r['title']): ?><h6 class="mb-1"><?= e($r['title']) ?></h6><?php endif; ?>
                <p class="mb-0 text-muted"><?= e($r['body']) ?></p>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <p class="text-muted">No reviews yet. <a href="/pages/auth/login.php">Login</a> to write the first review.</p>
            <?php endif; ?>

            <?php if (isLoggedIn()): ?>
            <div class="mt-4">
                <h6 class="fw-bold">Write a Review</h6>
                <form method="POST" action="/api/reviews.php?action=create">
                    <?= csrfField() ?>
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    <input type="hidden" name="_redirect" value="<?= e($_SERVER['REQUEST_URI']) ?>">
                    <div class="mb-2">
                        <label class="form-label">Rating</label>
                        <select name="rating" class="form-select w-auto">
                            <?php for ($s = 5; $s >= 1; $s--): ?><option value="<?= $s ?>"><?= str_repeat('★', $s) ?></option><?php endfor; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <input type="text" name="title" class="form-control" placeholder="Review title (optional)">
                    </div>
                    <div class="mb-2">
                        <textarea name="body" class="form-control" rows="3" placeholder="Share your experience..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Submit Review</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Related Products -->
    <?php if (!empty($related)): ?>
    <h4 class="fw-bold mb-3">Related Products</h4>
    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-6 g-3 mb-5">
        <?php foreach ($related as $rp): ?>
        <div class="col">
            <div class="card h-100 border-0 shadow-sm">
                <?php $rimgs = json_decode($rp['images'] ?? '[]', true); ?>
                <a href="/pages/product/detail.php?slug=<?= urlencode($rp['slug']) ?>">
                    <img src="<?= !empty($rimgs[0]) ? e(APP_URL . '/' . $rimgs[0]) : 'https://via.placeholder.com/150?text=P' ?>" class="card-img-top" style="height:120px;object-fit:cover" alt="">
                </a>
                <div class="card-body py-2 px-2">
                    <p class="small mb-0 fw-semibold"><?= e(mb_strimwidth($rp['name'], 0, 40, '…')) ?></p>
                    <span class="text-primary small fw-bold"><?= formatMoney($rp['price']) ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
const _CSRF = '<?= e(csrfToken()) ?>';
const _CART_API = '<?= APP_URL ?>/api/cart.php';
const _WISH_API = '<?= APP_URL ?>/api/wishlist.php';

function changeQty(delta) {
    const input = document.getElementById('qtyInput');
    const min = parseInt(input.min) || 1;
    input.value = Math.max(min, parseInt(input.value || min) + delta);
}
document.getElementById('variantSelect')?.addEventListener('change', function() {
    document.getElementById('variantId').value = this.value;
});

// AJAX Add to Cart
document.getElementById('addToCartForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('addToCartBtn');
    const data = new URLSearchParams({
        _csrf_token: _CSRF,
        product_id:  document.querySelector('[name=product_id]').value,
        variant_id:  document.getElementById('variantId')?.value || '',
        quantity:    document.getElementById('qtyInput')?.value || 1,
    });
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Adding…';

    fetch(`${_CART_API}?action=add`, { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Added!';
                btn.classList.replace('btn-primary', 'btn-success');
                if (typeof updateCartBadge === 'function') updateCartBadge(res.count);
                setTimeout(() => {
                    btn.innerHTML = '<i class="bi bi-cart-plus me-1"></i> Add to Cart';
                    btn.classList.replace('btn-success', 'btn-primary');
                    btn.disabled = false;
                }, 2000);
            } else {
                alert(res.message || 'Could not add to cart.');
                btn.innerHTML = '<i class="bi bi-cart-plus me-1"></i> Add to Cart';
                btn.disabled = false;
            }
        })
        .catch(() => {
            alert('Network error. Please try again.');
            btn.innerHTML = '<i class="bi bi-cart-plus me-1"></i> Add to Cart';
            btn.disabled = false;
        });
});

// Wishlist toggle
function toggleWishlist(productId) {
    const btn       = document.getElementById('wishlistBtn');
    const btnText   = document.getElementById('wishlistBtnText');
    const isInList  = btn.classList.contains('btn-danger');
    const action    = isInList ? 'remove' : 'add';
    const body      = new URLSearchParams({ _csrf_token: _CSRF, product_id: productId });

    btn.disabled = true;
    fetch(`${_WISH_API}?action=${action}`, { method: 'POST', body })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            if (res.success) {
                if (action === 'add') {
                    btn.classList.replace('btn-outline-danger', 'btn-danger');
                    btnText.textContent = 'Saved';
                    btn.querySelector('i').className = 'bi bi-heart-fill me-1';
                } else {
                    btn.classList.replace('btn-danger', 'btn-outline-danger');
                    btnText.textContent = 'Wishlist';
                    btn.querySelector('i').className = 'bi bi-heart me-1';
                }
                if (typeof updateWishlistBadge === 'function' && res.count !== undefined) {
                    updateWishlistBadge(res.count);
                }
            } else {
                alert(res.message || 'Could not update wishlist.');
            }
        })
        .catch(() => { btn.disabled = false; alert('Network error.'); });
}

// ── Variation Selector (product_variations SKU system) ─────────────────────
(function() {
    const hasVariations = <?= json_encode($hasVariations) ?>;
    if (!hasVariations) return;

    const productId   = <?= (int)$product['id'] ?>;
    const selected    = {}; // typeId => optionId
    const addCartBtn  = document.getElementById('addToCartBtn');
    const skuIdInput  = document.getElementById('skuId');
    const skuInfo     = document.getElementById('skuInfo');
    const skuStockEl  = document.getElementById('skuStockInfo');
    const skuCodeEl   = document.getElementById('skuCodeInfo');
    const priceEl     = document.querySelector('.fs-2.fw-bold.text-primary');
    const basePriceFmt= priceEl ? priceEl.textContent : '';

    document.querySelectorAll('.variation-option').forEach(btn => {
        btn.addEventListener('click', function() {
            const typeId   = this.dataset.typeId;
            const optionId = this.dataset.optionId;

            // Toggle selection within this type
            document.querySelectorAll(`.variation-option[data-type-id="${typeId}"]`).forEach(b => {
                b.classList.remove('btn-primary');
                b.classList.add('btn-outline-secondary');
            });
            this.classList.remove('btn-outline-secondary');
            this.classList.add('btn-primary');
            selected[typeId] = optionId;

            // Check if all types have been selected
            const allTypeIds = <?= json_encode(array_column($variationTypes, 'id')) ?>;
            const allSelected = allTypeIds.every(id => selected[String(id)] !== undefined);

            if (!allSelected) {
                addCartBtn.disabled = true;
                addCartBtn.title = 'Please select all options first';
                skuInfo.style.display = 'none';
                skuIdInput.value = '';
                return;
            }

            // Look up the SKU via API
            fetch(`<?= APP_URL ?>/api/products.php?action=get_skus&product_id=${encodeURIComponent(productId)}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    const targetOpts = new Set(Object.values(selected).map(String));
                    const sku = (data.data || []).find(s => {
                        const opts = new Set((s.variation_options || []).map(o => String(o.option_id)));
                        if (opts.size !== targetOpts.size) return false;
                        for (const id of targetOpts) if (!opts.has(id)) return false;
                        return true;
                    });

                    if (sku && sku.is_active) {
                        skuIdInput.value = sku.id;
                        const inStock = sku.stock > 0;
                        skuInfo.style.display = '';
                        skuStockEl.innerHTML = inStock
                            ? `<span class="text-success"><i class="bi bi-check-circle me-1"></i>In Stock (${sku.stock} units)</span>`
                            : `<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Out of Stock</span>`;
                        skuCodeEl.textContent = sku.sku_code ? `SKU: ${sku.sku_code}` : '';

                        // Update displayed price if SKU has a price override
                        if (sku.price && priceEl) {
                            priceEl.textContent = '$' + parseFloat(sku.price).toFixed(2);
                        } else if (priceEl) {
                            priceEl.textContent = basePriceFmt;
                        }

                        addCartBtn.disabled = !inStock;
                        addCartBtn.title = inStock ? '' : 'This combination is out of stock';
                    } else {
                        skuIdInput.value = '';
                        skuInfo.style.display = '';
                        skuStockEl.innerHTML = `<span class="text-danger"><i class="bi bi-x-circle me-1"></i>This combination is unavailable</span>`;
                        skuCodeEl.textContent = '';
                        addCartBtn.disabled = true;
                        addCartBtn.title = 'Combination unavailable';
                    }
                })
                .catch(() => {
                    addCartBtn.disabled = false;
                });
        });
    });
})();

// Promotion countdown timer
(function() {
    const el = document.getElementById('promoCountdown');
    if (!el) return;
    const endDate = new Date(el.dataset.end.replace(' ', 'T'));
    function tick() {
        const diff = endDate - Date.now();
        if (diff <= 0) { el.textContent = 'Ended'; return; }
        const d = Math.floor(diff / 86400000);
        const h = Math.floor((diff % 86400000) / 3600000);
        const m = Math.floor((diff % 3600000) / 60000);
        const s = Math.floor((diff % 60000) / 1000);
        el.textContent = (d > 0 ? d + 'd ' : '') + h + 'h ' + m + 'm ' + s + 's';
    }
    tick();
    setInterval(tick, 1000);
})();
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
