<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$db = getDB();

// Load cart
$stmt = $db->prepare('SELECT ci.id, ci.quantity, p.id product_id, p.name, p.price, p.stock_qty FROM cart_items ci JOIN products p ON p.id=ci.product_id WHERE ci.user_id=?');
$stmt->execute([$_SESSION['user_id']]);
$cartItems = $stmt->fetchAll();

if (empty($cartItems)) { flashMessage('warning', 'Your cart is empty.'); redirect('/pages/cart/index.php'); }

$subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cartItems));
$tax      = round($subtotal * 0.05, 2);

// Load saved addresses
$addrStmt = $db->prepare('SELECT * FROM addresses WHERE user_id=? ORDER BY is_default DESC');
$addrStmt->execute([$_SESSION['user_id']]);
$addresses = $addrStmt->fetchAll();

$pageTitle = 'Checkout';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <h3 class="fw-bold mb-4"><i class="bi bi-credit-card-fill text-primary me-2"></i>Checkout</h3>

    <div class="row g-4">
        <!-- Left: Shipping + Payment -->
        <div class="col-lg-7">
            <form method="POST" action="/api/orders.php?action=place" id="checkoutForm">
                <?= csrfField() ?>

                <!-- Shipping Address -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold"><i class="bi bi-geo-alt me-2 text-primary"></i>Shipping Address</h6></div>
                    <div class="card-body">
                        <?php if (!empty($addresses)): ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Use saved address</label>
                            <select class="form-select" id="savedAddress" onchange="fillAddress(this.value)">
                                <option value="">— Enter manually —</option>
                                <?php foreach ($addresses as $addr): ?>
                                <option value='<?= htmlspecialchars(json_encode($addr), ENT_QUOTES) ?>'>
                                    <?= e($addr['label']) ?>: <?= e($addr['address_line1']) ?>, <?= e($addr['city']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="full_name" id="f_full_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="tel" name="phone" id="f_phone" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address Line 1 *</label>
                                <input type="text" name="address_line1" id="f_address_line1" class="form-control" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address Line 2</label>
                                <input type="text" name="address_line2" id="f_address_line2" class="form-control">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">City *</label>
                                <input type="text" name="city" id="f_city" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">State</label>
                                <input type="text" name="state" id="f_state" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Postal Code</label>
                                <input type="text" name="postal_code" id="f_postal_code" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Country *</label>
                                <input type="text" name="country" id="f_country" class="form-control" value="US" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold"><i class="bi bi-wallet2 me-2 text-primary"></i>Payment Method</h6></div>
                    <div class="card-body">
                        <?php foreach ([
                            'bank_transfer' => ['Bank Transfer', 'bi-bank2'],
                            'wire_transfer' => ['Wire Transfer', 'bi-arrow-left-right'],
                            'paypal'        => ['PayPal', 'bi-paypal'],
                            'escrow'        => ['Escrow (Trade Assurance)', 'bi-shield-check'],
                        ] as $val => [$label, $icon]): ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="payment_method" value="<?= $val ?>" id="pm_<?= $val ?>" <?= $val==='bank_transfer'?'checked':'' ?>>
                            <label class="form-check-label" for="pm_<?= $val ?>">
                                <i class="bi <?= $icon ?> me-1"></i> <?= $label ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Coupon -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold"><i class="bi bi-tag me-2 text-primary"></i>Coupon Code</h6></div>
                    <div class="card-body">
                        <div class="input-group">
                            <input type="text" name="coupon_code" class="form-control" placeholder="Enter coupon code">
                            <button type="button" class="btn btn-outline-secondary">Apply</button>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="mb-4">
                    <label class="form-label">Order Notes (optional)</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Any special instructions..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100 py-3">
                    <i class="bi bi-check-circle-fill me-2"></i> Place Order — <?= formatMoney($subtotal + $tax) ?>
                </button>
            </form>
        </div>

        <!-- Right: Order Summary -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm sticky-top" style="top:80px">
                <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold">Order Summary</h6></div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($cartItems as $item): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <span class="fw-semibold"><?= e(mb_strimwidth($item['name'], 0, 35, '…')) ?></span>
                                <br><small class="text-muted">Qty: <?= $item['quantity'] ?> × <?= formatMoney($item['price']) ?></small>
                            </div>
                            <span><?= formatMoney($item['price'] * $item['quantity']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="p-3">
                        <dl class="row mb-0">
                            <dt class="col-6">Subtotal</dt><dd class="col-6 text-end"><?= formatMoney($subtotal) ?></dd>
                            <dt class="col-6">Shipping</dt><dd class="col-6 text-end">Free</dd>
                            <dt class="col-6">Tax (5%)</dt><dd class="col-6 text-end"><?= formatMoney($tax) ?></dd>
                        </dl>
                        <hr>
                        <div class="d-flex justify-content-between fw-bold fs-5">
                            <span>Total</span>
                            <span class="text-primary"><?= formatMoney($subtotal + $tax) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function fillAddress(json) {
    if (!json) return;
    const a = JSON.parse(json);
    const fields = ['full_name','phone','address_line1','address_line2','city','state','postal_code','country'];
    fields.forEach(f => {
        const el = document.getElementById('f_'+f);
        if (el) el.value = a[f] || '';
    });
}
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
