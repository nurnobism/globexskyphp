<?php
require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../config/stripe.php';
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

$stripePublishableKey = defined('STRIPE_PUBLISHABLE_KEY') ? STRIPE_PUBLISHABLE_KEY : '';

$pageTitle = 'Checkout';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <h3 class="fw-bold mb-4"><i class="bi bi-credit-card-fill text-primary me-2"></i>Checkout</h3>

    <div id="checkoutAlert"></div>

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

                <!-- Shipping Method -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold"><i class="bi bi-truck me-2 text-primary"></i>Shipping Method</h6></div>
                    <div class="card-body">
                        <input type="hidden" name="shipping_method" id="shippingMethodInput" value="standard">
                        <?php
                        $shippingOptions = [
                            'standard' => ['label' => 'Standard Shipping', 'desc' => '5–10 business days', 'icon' => 'bi-truck'],
                            'express'  => ['label' => 'Express Shipping',  'desc' => '2–4 business days',  'icon' => 'bi-lightning-fill'],
                            'priority' => ['label' => 'Priority Shipping', 'desc' => '1–2 business days',  'icon' => 'bi-rocket-fill'],
                        ];
                        foreach ($shippingOptions as $val => $opt): ?>
                        <div class="form-check mb-2 p-3 border rounded <?= $val==='standard'?'border-primary bg-primary bg-opacity-10':'' ?>" id="ship_box_<?= $val ?>">
                            <input class="form-check-input" type="radio" name="_shipping_ui" value="<?= $val ?>" id="sm_<?= $val ?>" <?= $val==='standard'?'checked':'' ?> onchange="updateShipping('<?= $val ?>')">
                            <label class="form-check-label d-flex justify-content-between w-100 ps-1" for="sm_<?= $val ?>">
                                <span><i class="bi <?= $opt['icon'] ?> me-2"></i><strong><?= $opt['label'] ?></strong><br><small class="text-muted"><?= $opt['desc'] ?></small></span>
                                <span class="fw-bold" id="ship_fee_<?= $val ?>">—</span>
                            </label>
                        </div>
                        <?php endforeach; ?>
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
                            'cod'           => ['Cash on Delivery', 'bi-cash-coin'],
                            'stripe'        => ['Credit / Debit Card', 'bi-credit-card-2-front'],
                        ] as $val => [$label, $icon]): ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="payment_method" value="<?= $val ?>" id="pm_<?= $val ?>" <?= $val==='bank_transfer'?'checked':'' ?> onchange="onPaymentChange()">
                            <label class="form-check-label" for="pm_<?= $val ?>">
                                <i class="bi <?= $icon ?> me-1"></i> <?= $label ?>
                            </label>
                        </div>
                        <?php endforeach; ?>

                        <!-- Stripe Card Element -->
                        <div id="stripeSection" class="mt-3" style="display:none">
                            <label class="form-label fw-semibold">Card Details</label>
                            <div id="card-element" class="form-control py-2"></div>
                            <div id="card-errors" class="text-danger small mt-1"></div>
                        </div>
                    </div>
                </div>

                <!-- Coupon -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold"><i class="bi bi-tag me-2 text-primary"></i>Coupon Code</h6></div>
                    <div class="card-body">
                        <div class="input-group">
                            <input type="text" name="coupon_code" id="couponInput" class="form-control" placeholder="Enter coupon code">
                            <button type="button" class="btn btn-outline-secondary" onclick="applyCoupon()">Apply</button>
                        </div>
                        <div id="couponResult" class="mt-2"></div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="mb-4">
                    <label class="form-label">Order Notes (optional)</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Any special instructions..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100 py-3" id="placeOrderBtn">
                    <i class="bi bi-check-circle-fill me-2"></i> Place Order — <span id="totalDisplay"><?= formatMoney($subtotal + $tax) ?></span>
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
                            <dt class="col-6">Shipping</dt><dd class="col-6 text-end" id="summaryShipping">Calculating…</dd>
                            <dt class="col-6">Tax (5%)</dt><dd class="col-6 text-end"><?= formatMoney($tax) ?></dd>
                            <dt class="col-6 text-success" id="discountLabel" style="display:none">Discount</dt>
                            <dd class="col-6 text-end text-success" id="discountValue" style="display:none"></dd>
                        </dl>
                        <hr>
                        <div class="d-flex justify-content-between fw-bold fs-5">
                            <span>Total</span>
                            <span class="text-primary" id="summaryTotal"><?= formatMoney($subtotal + $tax) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($stripePublishableKey): ?>
<script src="https://js.stripe.com/v3/"></script>
<?php endif; ?>

<script>
const SUBTOTAL    = <?= $subtotal ?>;
const TAX         = <?= $tax ?>;
const STRIPE_KEY  = <?= json_encode($stripePublishableKey) ?>;
let shippingFee   = 0;
let discount      = 0;
let stripeCard    = null;
let stripeObj     = STRIPE_KEY ? Stripe(STRIPE_KEY) : null;

if (stripeObj) {
    const elements = stripeObj.elements();
    stripeCard = elements.create('card');
}

function updateShipping(method) {
    document.getElementById('shippingMethodInput').value = method;
    ['standard','express','priority'].forEach(m => {
        const box = document.getElementById('ship_box_' + m);
        if (box) {
            box.classList.remove('border-primary','bg-primary','bg-opacity-10');
            if (m === method) box.classList.add('border-primary','bg-primary','bg-opacity-10');
        }
    });
    const fees = { standard: SUBTOTAL >= 100 ? 0 : 9.99, express: 19.99, priority: 29.99 };
    shippingFee = fees[method] ?? 0;
    document.getElementById('ship_fee_standard').textContent = SUBTOTAL >= 100 ? 'Free' : '$9.99';
    document.getElementById('ship_fee_express').textContent  = '$19.99';
    document.getElementById('ship_fee_priority').textContent = '$29.99';
    document.getElementById('summaryShipping').textContent   = shippingFee === 0 ? 'Free' : '$' + shippingFee.toFixed(2);
    recalcTotal();
}

function recalcTotal() {
    const total = Math.max(0, SUBTOTAL + shippingFee + TAX - discount);
    const fmt   = '$' + total.toFixed(2);
    document.getElementById('summaryTotal').textContent  = fmt;
    document.getElementById('totalDisplay').textContent  = fmt;
}

function onPaymentChange() {
    const val = document.querySelector('input[name=payment_method]:checked')?.value;
    const sec = document.getElementById('stripeSection');
    if (val === 'stripe' && stripeObj && stripeCard) {
        sec.style.display = 'block';
        stripeCard.mount('#card-element');
        stripeCard.on('change', e => {
            document.getElementById('card-errors').textContent = e.error ? e.error.message : '';
        });
    } else {
        sec.style.display = 'none';
        if (stripeCard) try { stripeCard.unmount(); } catch(e) {}
    }
}

async function applyCoupon() {
    const code = document.getElementById('couponInput').value.trim();
    if (!code) return;
    const formData = new FormData();
    formData.append('coupon_code', code);
    formData.append('subtotal', SUBTOTAL);
    formData.append('_csrf_token', document.querySelector('input[name=_csrf_token]').value);
    const res  = await fetch('/api/checkout.php?action=apply_coupon', { method: 'POST', body: formData });
    const data = await res.json();
    const el   = document.getElementById('couponResult');
    if (data.valid) {
        discount = data.discount;
        el.innerHTML = '<div class="alert alert-success p-2 small">Coupon applied! Saving ' + (data.coupon.type === 'percent' ? data.coupon.value + '%' : '$' + parseFloat(data.coupon.value).toFixed(2)) + '</div>';
        document.getElementById('discountLabel').style.display = '';
        document.getElementById('discountValue').style.display = '';
        document.getElementById('discountValue').textContent   = '-$' + discount.toFixed(2);
        recalcTotal();
    } else {
        el.innerHTML = '<div class="alert alert-danger p-2 small">' + (data.error || 'Invalid coupon') + '</div>';
    }
}

document.getElementById('checkoutForm').addEventListener('submit', async function(e) {
    const payMethod = document.querySelector('input[name=payment_method]:checked')?.value;
    if (payMethod !== 'stripe') return; // let form submit normally for non-Stripe

    e.preventDefault();
    if (!stripeObj || !stripeCard) {
        document.getElementById('checkoutAlert').innerHTML = '<div class="alert alert-danger">Stripe is not available.</div>';
        return;
    }

    const btn = document.getElementById('placeOrderBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing…';

    const formData = new FormData(this);
    try {
        const res  = await fetch('/api/checkout.php?action=place_order', { method: 'POST', body: formData });
        const data = await res.json();

        if (!data.success && !data.client_secret) {
            document.getElementById('checkoutAlert').innerHTML = '<div class="alert alert-danger">' + (data.error || 'Order failed.') + '</div>';
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i> Place Order';
            return;
        }

        const result = await stripeObj.confirmCardPayment(data.client_secret, {
            payment_method: { card: stripeCard }
        });

        if (result.error) {
            document.getElementById('card-errors').textContent = result.error.message;
            document.getElementById('checkoutAlert').innerHTML = '<div class="alert alert-danger">' + result.error.message + '</div>';
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i> Place Order';
        } else {
            window.location.href = '/pages/order/confirmation.php?id=' + data.order_id;
        }
    } catch (err) {
        document.getElementById('checkoutAlert').innerHTML = '<div class="alert alert-danger">Network error. Please try again.</div>';
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i> Place Order';
    }
});

function fillAddress(json) {
    if (!json) return;
    const a = JSON.parse(json);
    const fields = ['full_name','phone','address_line1','address_line2','city','state','postal_code','country'];
    fields.forEach(f => {
        const el = document.getElementById('f_'+f);
        if (el) el.value = a[f] || '';
    });
}

// Init
updateShipping('standard');
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
