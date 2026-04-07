<?php
require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/cart.php';

if (!isFeatureEnabled('cart_checkout')) {
    $pageTitle = 'Shopping Cart';
    include __DIR__ . '/../../includes/header.php';
    echo '<div class="container py-5 text-center"><h4>Shopping cart is currently unavailable.</h4></div>';
    include __DIR__ . '/../../includes/footer.php';
    exit;
}

$userId    = isLoggedIn() ? (int)$_SESSION['user_id'] : 0;
$groups    = getCartGroupedBySupplier($userId);
$cartItems = getCart($userId);
$subtotal  = getCartTotal($userId);
$issues    = validateCartStock($userId);
$issueMap  = [];
foreach ($issues as $issue) {
    $key = $issue['id'] > 0 ? 'id_' . $issue['id'] : 'pid_' . $issue['product_id'];
    $issueMap[$key] = $issue;
}

$pageTitle = 'Shopping Cart';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <h3 class="fw-bold mb-4"><i class="bi bi-cart3 text-primary me-2"></i>Shopping Cart
        <span class="badge bg-secondary fs-6 ms-2" id="cartCountBadge"><?= count($cartItems) ?></span>
    </h3>

    <?php if (!empty($issues)): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
        <div>
            <strong>Stock warning:</strong> Some items in your cart have stock issues.
            <ul class="mb-0 mt-1">
                <?php foreach ($issues as $issue): if ($issue['issue'] === 'low_stock') continue; ?>
                <li><?= e($issue['product_name']) ?> — <?= e($issue['message']) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($cartItems)): ?>
    <div class="text-center py-5">
        <i class="bi bi-cart-x display-1 text-muted"></i>
        <h5 class="mt-3 fw-semibold">Your cart is empty</h5>
        <p class="text-muted">Add items from our product catalogue to get started.</p>
        <a href="<?= APP_URL ?>/pages/product/index.php" class="btn btn-primary mt-2">
            <i class="bi bi-shop me-1"></i> Start Shopping
        </a>
    </div>
    <?php else: ?>
    <div class="row g-4">
        <!-- Cart Items -->
        <div class="col-lg-8">
            <?php foreach ($groups as $group): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                    <span class="fw-semibold text-muted small">
                        <i class="bi bi-shop me-1"></i><?= e($group['supplier_name']) ?>
                    </span>
                    <span class="small text-muted">
                        Subtotal: <strong><?= formatMoney($group['subtotal']) ?></strong>
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:45%">Product</th>
                                <th>Price</th>
                                <th>Qty</th>
                                <th>Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="cartBody">
                        <?php foreach ($group['items'] as $item):
                            $itemKey    = $item['id'] > 0 ? 'id_' . $item['id'] : 'pid_' . $item['product_id'];
                            $itemIssue  = $issueMap[$itemKey] ?? null;
                            $rowId      = $item['id'] > 0 ? $item['id'] : 'g_' . $item['product_id'];
                            $img        = $item['image'] ? APP_URL . '/' . $item['image'] : 'https://via.placeholder.com/60?text=P';
                        ?>
                        <tr id="row-<?= $rowId ?>" class="<?= $itemIssue && $itemIssue['issue'] === 'out_of_stock' ? 'table-danger' : '' ?>">
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?= e($img) ?>" width="60" height="60"
                                         style="object-fit:cover;border-radius:6px;flex-shrink:0" alt="">
                                    <div>
                                        <a href="<?= APP_URL ?>/pages/product/detail.php?slug=<?= urlencode($item['slug']) ?>"
                                           class="text-decoration-none fw-semibold text-dark">
                                            <?= e(mb_strimwidth($item['product_name'], 0, 55, '…')) ?>
                                        </a>
                                        <?php if ($item['sku_info']): ?>
                                        <br><small class="text-muted"><?= e($item['sku_info']) ?></small>
                                        <?php endif; ?>
                                        <?php if ($itemIssue): ?>
                                        <br><small class="<?= $itemIssue['issue'] === 'out_of_stock' ? 'text-danger' : 'text-warning' ?> fw-semibold">
                                            <i class="bi bi-exclamation-circle me-1"></i><?= e($itemIssue['message']) ?>
                                        </small>
                                        <?php elseif ($item['stock_qty'] <= 5 && $item['stock_qty'] > 0): ?>
                                        <br><small class="text-warning fw-semibold">
                                            <i class="bi bi-exclamation-circle me-1"></i>Only <?= (int)$item['stock_qty'] ?> left
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="fw-semibold"><?= formatMoney($item['unit_price']) ?></td>
                            <td>
                                <div class="input-group" style="width:120px">
                                    <button class="btn btn-outline-secondary btn-sm"
                                            onclick="cartAdjust(<?= (int)$item['id'] ?>, <?= (int)$item['product_id'] ?>, -1, this)"
                                            <?= ($itemIssue && $itemIssue['issue'] === 'out_of_stock') ? 'disabled' : '' ?>>
                                        <i class="bi bi-dash"></i>
                                    </button>
                                    <input type="number" class="form-control form-control-sm text-center qty-input"
                                           id="qty-<?= $rowId ?>"
                                           value="<?= (int)$item['quantity'] ?>"
                                           min="1"
                                           max="<?= (int)$item['stock_qty'] ?>"
                                           data-item-id="<?= (int)$item['id'] ?>"
                                           data-product-id="<?= (int)$item['product_id'] ?>"
                                           data-price="<?= $item['unit_price'] ?>"
                                           <?= ($itemIssue && $itemIssue['issue'] === 'out_of_stock') ? 'disabled' : '' ?>
                                           onchange="cartSetQty(<?= (int)$item['id'] ?>, <?= (int)$item['product_id'] ?>, this.value)">
                                    <button class="btn btn-outline-secondary btn-sm"
                                            onclick="cartAdjust(<?= (int)$item['id'] ?>, <?= (int)$item['product_id'] ?>, 1, this)"
                                            <?= ($itemIssue && $itemIssue['issue'] === 'out_of_stock') ? 'disabled' : '' ?>>
                                        <i class="bi bi-plus"></i>
                                    </button>
                                </div>
                            </td>
                            <td class="fw-semibold" id="subtotal-<?= $rowId ?>">
                                <?= formatMoney($item['subtotal']) ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-danger"
                                        onclick="cartRemove(<?= (int)$item['id'] ?>, <?= (int)$item['product_id'] ?>)"
                                        title="Remove item">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="d-flex justify-content-between mt-2">
                <a href="<?= APP_URL ?>/pages/product/index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Continue Shopping
                </a>
                <button class="btn btn-outline-danger" onclick="cartClear()">
                    <i class="bi bi-trash me-1"></i> Clear Cart
                </button>
            </div>
        </div>

        <!-- Order Summary Sidebar -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm sticky-top" style="top:80px">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold">Order Summary</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-6 fw-normal">Items (<span id="summaryCount"><?= count($cartItems) ?></span>)</dt>
                        <dd class="col-6 text-end" id="summarySubtotal"><?= formatMoney($subtotal) ?></dd>
                        <dt class="col-6 fw-normal text-muted small">Shipping</dt>
                        <dd class="col-6 text-end text-muted small">Calculated at checkout</dd>
                        <dt class="col-6 fw-normal text-muted small">Tax (est.)</dt>
                        <dd class="col-6 text-end text-muted small">Calculated at checkout</dd>
                    </dl>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between fw-bold fs-5">
                        <span>Subtotal</span>
                        <span class="text-primary" id="summaryTotal"><?= formatMoney($subtotal) ?></span>
                    </div>
                    <a href="<?= APP_URL ?>/pages/checkout/index.php" class="btn btn-primary w-100 py-2 mt-3" id="checkoutBtn">
                        <i class="bi bi-credit-card me-1"></i> Proceed to Checkout
                    </a>
                    <a href="<?= APP_URL ?>/pages/product/index.php" class="btn btn-outline-secondary w-100 py-2 mt-2">
                        <i class="bi bi-shop me-1"></i> Continue Shopping
                    </a>
                    <?php if (!isLoggedIn()): ?>
                    <p class="text-muted small text-center mt-2 mb-0">
                        <a href="<?= APP_URL ?>/pages/auth/login.php">Login</a> to save your cart across devices.
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Clear Cart Confirmation Modal -->
    <div class="modal fade" id="clearCartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h6 class="modal-title fw-bold">Clear Cart?</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-muted small">
                    This will remove all items from your cart. This action cannot be undone.
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-danger" id="confirmClearBtn">Clear Cart</button>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>
</div>

<script>
const CSRF = '<?= e(csrfToken()) ?>';
const CART_API = '<?= APP_URL ?>/api/cart.php';

function cartPost(action, data, onSuccess) {
    const body = new URLSearchParams({ _csrf_token: CSRF, ...data });
    fetch(`${CART_API}?action=${action}`, { method: 'POST', body })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (typeof onSuccess === 'function') onSuccess(res);
                updateCartBadge();
            } else {
                alert(res.message || 'An error occurred.');
            }
        })
        .catch(() => alert('Network error. Please try again.'));
}

function cartAdjust(itemId, productId, delta, btn) {
    const rowId = itemId > 0 ? itemId : 'g_' + productId;
    const input = document.getElementById('qty-' + rowId);
    if (!input) return;
    const newQty = Math.max(1, parseInt(input.value) + delta);
    input.value = newQty;
    cartSetQty(itemId, productId, newQty);
}

let _qtyTimer = {};
function cartSetQty(itemId, productId, qty) {
    const timerKey = itemId > 0 ? itemId : productId;
    clearTimeout(_qtyTimer[timerKey]);
    _qtyTimer[timerKey] = setTimeout(() => {
        const rowId = itemId > 0 ? itemId : 'g_' + productId;
        const input = document.getElementById('qty-' + rowId);
        const price = parseFloat(input?.dataset?.price || 0);
        const idParam = itemId > 0 ? itemId : productId;

        cartPost('update', { cart_item_id: idParam, quantity: qty }, res => {
            // Update row subtotal
            if (input && price > 0) {
                const sub = document.getElementById('subtotal-' + rowId);
                if (sub) sub.textContent = '$' + (price * qty).toFixed(2);
            }
            // Update summary
            if (res.total !== undefined) {
                document.querySelectorAll('#summarySubtotal, #summaryTotal').forEach(el => {
                    el.textContent = '$' + parseFloat(res.total).toFixed(2);
                });
            }
        });
    }, 400);
}

function cartRemove(itemId, productId) {
    const idParam = itemId > 0 ? itemId : productId;
    const rowId   = itemId > 0 ? itemId : 'g_' + productId;
    cartPost('remove', { cart_item_id: idParam }, res => {
        const row = document.getElementById('row-' + rowId);
        if (row) {
            row.style.transition = 'opacity 0.3s';
            row.style.opacity = '0';
            setTimeout(() => { row.remove(); updateSummary(res); }, 300);
        } else {
            location.reload();
        }
    });
}

function cartClear() {
    const modal = new bootstrap.Modal(document.getElementById('clearCartModal'));
    modal.show();
    document.getElementById('confirmClearBtn').onclick = () => {
        modal.hide();
        cartPost('clear', {}, () => location.reload());
    };
}

function updateSummary(res) {
    if (res.total !== undefined) {
        document.querySelectorAll('#summarySubtotal, #summaryTotal').forEach(el => {
            el.textContent = '$' + parseFloat(res.total).toFixed(2);
        });
    }
    if (res.count !== undefined) {
        const badge = document.getElementById('summaryCount');
        if (badge) badge.textContent = res.count;
        const cartBadge = document.getElementById('cartCountBadge');
        if (cartBadge) cartBadge.textContent = res.count;
    }
    if (res.count === 0) {
        location.reload();
    }
}

function updateCartBadge() {
    fetch(`${CART_API}?action=count`)
        .then(r => r.json())
        .then(res => {
            if (res.count !== undefined) {
                document.querySelectorAll('[data-cart-badge]').forEach(el => {
                    el.textContent = res.count > 99 ? '99+' : res.count;
                    el.setAttribute('aria-label', 'Cart items: ' + res.count);
                });
            }
        }).catch(() => {});
}
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
