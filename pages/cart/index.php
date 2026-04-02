<?php
require_once __DIR__ . '/../../includes/middleware.php';

$db = getDB();

// Load cart items
if (isLoggedIn()) {
    $stmt = $db->prepare('SELECT ci.id, ci.quantity, p.id product_id, p.name, p.price, p.images, p.slug, p.stock_qty, p.unit, s.company_name supplier_name
        FROM cart_items ci JOIN products p ON p.id=ci.product_id
        LEFT JOIN suppliers s ON s.id=p.supplier_id WHERE ci.user_id=?');
    $stmt->execute([$_SESSION['user_id']]);
    $cartItems = $stmt->fetchAll();
} else {
    $cartItems = array_values($_SESSION['cart'] ?? []);
}

$subtotal = array_sum(array_map(fn($i) => ($i['price'] ?? 0) * ($i['quantity'] ?? 0), $cartItems));

$pageTitle = 'Shopping Cart';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <h3 class="fw-bold mb-4"><i class="bi bi-cart3 text-primary me-2"></i>Shopping Cart</h3>

    <?php if (empty($cartItems)): ?>
    <div class="text-center py-5">
        <i class="bi bi-cart-x display-1 text-muted"></i>
        <h5 class="mt-3">Your cart is empty</h5>
        <a href="/pages/product/index.php" class="btn btn-primary mt-2"><i class="bi bi-shop me-1"></i> Continue Shopping</a>
    </div>
    <?php else: ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr><th style="width:50%">Product</th><th>Price</th><th>Qty</th><th>Total</th><th></th></tr>
                        </thead>
                        <tbody id="cartBody">
                        <?php foreach ($cartItems as $item): ?>
                        <?php
                        $imgArr = is_string($item['images'] ?? '') ? json_decode($item['images'], true) : ($item['images'] ?? []);
                        $img    = (is_array($imgArr) && !empty($imgArr[0])) ? APP_URL . '/' . $imgArr[0] : 'https://via.placeholder.com/60?text=P';
                        $itemTotal = ($item['price'] ?? 0) * ($item['quantity'] ?? 0);
                        ?>
                        <tr id="row-<?= $item['id'] ?? $item['product_id'] ?>">
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?= e($img) ?>" width="60" height="60" style="object-fit:cover;border-radius:6px" alt="">
                                    <div>
                                        <a href="/pages/product/detail.php?slug=<?= urlencode($item['slug'] ?? '') ?>" class="text-decoration-none fw-semibold text-dark">
                                            <?= e(mb_strimwidth($item['name'] ?? '', 0, 50, '…')) ?>
                                        </a>
                                        <br><small class="text-muted"><?= e($item['supplier_name'] ?? '') ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?= formatMoney($item['price'] ?? 0) ?></td>
                            <td>
                                <div class="input-group" style="width:110px">
                                    <button class="btn btn-outline-secondary btn-sm" onclick="updateCart(<?= $item['id'] ?? $item['product_id'] ?>, -1, this)">-</button>
                                    <input type="number" class="form-control form-control-sm text-center qty-input" value="<?= $item['quantity'] ?>" min="1" style="width:45px"
                                           onchange="setCartQty(<?= $item['id'] ?? $item['product_id'] ?>, this.value)">
                                    <button class="btn btn-outline-secondary btn-sm" onclick="updateCart(<?= $item['id'] ?? $item['product_id'] ?>, 1, this)">+</button>
                                </div>
                            </td>
                            <td class="fw-semibold row-total" id="total-<?= $item['id'] ?? $item['product_id'] ?>"><?= formatMoney($itemTotal) ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(<?= $item['id'] ?? $item['product_id'] ?>)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="d-flex justify-content-between mt-3">
                <a href="/pages/product/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Continue Shopping</a>
                <button class="btn btn-outline-danger" onclick="clearCart()"><i class="bi bi-trash me-1"></i> Clear Cart</button>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold">Order Summary</h6></div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-6">Subtotal</dt>
                        <dd class="col-6 text-end" id="subtotal"><?= formatMoney($subtotal) ?></dd>
                        <dt class="col-6 text-muted small">Shipping</dt>
                        <dd class="col-6 text-end text-muted small">Calculated at checkout</dd>
                        <dt class="col-6 text-muted small">Tax (5%)</dt>
                        <dd class="col-6 text-end text-muted small"><?= formatMoney($subtotal * 0.05) ?></dd>
                    </dl>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold fs-5">
                        <span>Estimated Total</span>
                        <span class="text-primary" id="estimated"><?= formatMoney($subtotal * 1.05) ?></span>
                    </div>
                    <a href="/pages/checkout/index.php" class="btn btn-primary w-100 py-2 mt-3">
                        <i class="bi bi-credit-card me-1"></i> Proceed to Checkout
                    </a>
                    <?php if (!isLoggedIn()): ?>
                    <p class="text-muted small text-center mt-2 mb-0">
                        <a href="/pages/auth/login.php">Login</a> to save your cart
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function postAction(url, data) {
    const form = document.createElement('form');
    form.method = 'POST'; form.action = url;
    data._csrf_token = '<?= e(csrfToken()) ?>';
    for (const [k,v] of Object.entries(data)) {
        const i = document.createElement('input'); i.type='hidden'; i.name=k; i.value=v; form.appendChild(i);
    }
    document.body.appendChild(form); form.submit();
}
function updateCart(id, delta, btn) {
    const row = document.getElementById('row-'+id);
    const input = row?.querySelector('.qty-input');
    if (!input) return;
    const newQty = Math.max(1, parseInt(input.value)+delta);
    setCartQty(id, newQty);
}
function setCartQty(id, qty) {
    postAction('/api/cart.php?action=update', {item_id: id, quantity: qty});
}
function removeFromCart(id) {
    postAction('/api/cart.php?action=remove', {item_id: id});
}
function clearCart() {
    if (confirm('Clear all items from cart?')) postAction('/api/cart.php?action=clear', {});
}
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
