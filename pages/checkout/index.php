<?php
/**
 * pages/checkout/index.php — Multi-Step Checkout Page (PR #6 rewrite)
 *
 * Step 1 — Shipping Address
 * Step 2 — Order Review
 * Step 3 — Payment Method
 * Step 4 — Confirm & Pay
 */

require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/checkout.php';
require_once __DIR__ . '/../../includes/stripe-handler.php';
require_once __DIR__ . '/../../includes/tax_engine.php';
require_once __DIR__ . '/../../config/stripe.php';

requireLogin();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

if (!isFeatureEnabled('cart_checkout')) {
    flashMessage('warning', 'Checkout is temporarily unavailable.');
    redirect('/pages/cart/index.php');
}

$cartItems = getCart($userId);
if (empty($cartItems)) {
    flashMessage('warning', 'Your cart is empty.');
    redirect('/pages/cart/index.php');
}

$addresses      = getShippingAddresses($userId);
$supplierGroups = getCartGroupedBySupplier($userId);

$defaultAddr = null;
foreach ($addresses as $addr) {
    if ($addr['is_default']) { $defaultAddr = $addr; break; }
}
if (!$defaultAddr && !empty($addresses)) {
    $defaultAddr = $addresses[0];
}
$totals = calculateOrderTotals($cartItems, $defaultAddr ? (int)$defaultAddr['id'] : 0);

$stripeKeys          = getStripeKeys();
$stripePublishableKey = $stripeKeys['publishable_key'];

$showCod          = isFeatureEnabled('cod_payment');
$showBankTransfer = isFeatureEnabled('bank_transfer_payment');

$taxMode      = getTaxMode();
$taxLabel     = getTaxSetting('tax_label', 'Tax');
$taxEnabled   = isFeatureEnabled('tax_calculation');

$pageTitle = 'Checkout';
include __DIR__ . '/../../includes/header.php';
?>

<style>
.step-circle {
    width:40px;height:40px;border-radius:50%;background:#e9ecef;
    display:flex;align-items:center;justify-content:center;
    color:#6c757d;font-size:1.1rem;transition:all .2s;
}
.step-circle.active{background:#0d6efd;color:#fff;}
.step-circle.done{background:#198754;color:#fff;}
.step-connector{height:2px;background:#e9ecef;min-width:20px;transition:background .2s;flex:1;}
.step-connector.done{background:#198754;}
.address-card{cursor:pointer;transition:all .15s;}
.address-card:hover{border-color:#0d6efd!important;}
.address-card.selected-addr{border-color:#0d6efd!important;background:rgba(13,110,253,.06);}
.payment-option{transition:border-color .15s;}
.payment-option.selected{border-color:#0d6efd!important;background:rgba(13,110,253,.04);}
</style>

<div class="container py-4" id="checkoutApp">

    <!-- Progress Indicator -->
    <div class="d-flex align-items-center justify-content-center mb-4 gap-1">
        <?php $stepDefs = [1=>['bi-geo-alt','Address'],2=>['bi-cart3','Review'],3=>['bi-credit-card','Payment'],4=>['bi-check-circle','Confirm']]; ?>
        <?php foreach ($stepDefs as $num => [$icon, $label]): ?>
        <div class="d-flex flex-column align-items-center" id="step-ind-<?= $num ?>">
            <div class="step-circle <?= $num === 1 ? 'active' : '' ?>"><i class="bi <?= $icon ?>"></i></div>
            <small class="text-muted mt-1"><?= $label ?></small>
        </div>
        <?php if ($num < 4): ?><div class="step-connector" id="connector-<?= $num ?>"></div><?php endif; ?>
        <?php endforeach; ?>
    </div>

    <div id="checkoutAlert"></div>

    <div class="row g-4">
        <div class="col-lg-7">

            <!-- ===== STEP 1: Shipping Address ===== -->
            <div id="checkoutStep1" class="checkout-panel">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-geo-alt-fill text-primary me-2"></i>Step 1: Shipping Address</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($addresses)): ?>
                        <label class="form-label fw-semibold">Saved Addresses</label>
                        <div class="row g-2 mb-3" id="savedAddressCards">
                            <?php foreach ($addresses as $addr): ?>
                            <div class="col-md-6">
                                <div class="address-card p-3 border rounded <?= $addr['is_default'] ? 'selected-addr' : '' ?>"
                                     data-addr-id="<?= (int)$addr['id'] ?>"
                                     onclick="selectAddress(<?= (int)$addr['id'] ?>)">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <strong><?= e($addr['full_name'] ?? '') ?></strong>
                                        <?php if ($addr['is_default']): ?>
                                        <span class="badge bg-primary">Default</span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted d-block mt-1">
                                        <?= e($addr['address_line1']) ?><?= $addr['address_line2'] ? ', '.e($addr['address_line2']) : '' ?><br>
                                        <?= e($addr['city']) ?><?= $addr['state'] ? ', '.e($addr['state']) : '' ?> <?= e($addr['postal_code'] ?? '') ?><br>
                                        <?= e($addr['country']) ?><?= $addr['phone'] ? ' · '.e($addr['phone']) : '' ?>
                                    </small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm mb-3" onclick="toggleNewAddressForm()">
                            <i class="bi bi-plus-circle me-1"></i> Add New Address
                        </button>
                        <?php endif; ?>

                        <div id="newAddressForm" <?= empty($addresses) ? '' : 'style="display:none"' ?>>
                            <?php if (!empty($addresses)): ?><hr class="my-3"><?php endif; ?>
                            <h6 class="fw-semibold mb-3"><?= empty($addresses) ? 'Enter Shipping Address' : 'New Address' ?></h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" id="na_full_name" class="form-control" placeholder="John Doe">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone *</label>
                                    <input type="tel" id="na_phone" class="form-control" placeholder="+1 555 000 0000">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Address Line 1 *</label>
                                    <input type="text" id="na_address_line1" class="form-control" placeholder="123 Main St">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Address Line 2</label>
                                    <input type="text" id="na_address_line2" class="form-control" placeholder="Apt 4B (optional)">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">City *</label>
                                    <input type="text" id="na_city" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">State</label>
                                    <input type="text" id="na_state" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Postal Code</label>
                                    <input type="text" id="na_postal_code" class="form-control">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Country *</label>
                                    <select id="na_country" class="form-select">
                                        <option value="US">United States</option>
                                        <option value="GB">United Kingdom</option>
                                        <option value="CA">Canada</option>
                                        <option value="AU">Australia</option>
                                        <option value="DE">Germany</option>
                                        <option value="FR">France</option>
                                        <option value="JP">Japan</option>
                                        <option value="SG">Singapore</option>
                                        <option value="BD">Bangladesh</option>
                                        <option value="IN">India</option>
                                        <option value="AE">UAE</option>
                                        <option value="SA">Saudi Arabia</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="na_is_default">
                                        <label class="form-check-label" for="na_is_default">Set as default address</label>
                                    </div>
                                </div>
                            </div>
                            <div id="newAddrError" class="text-danger small mt-2"></div>
                            <button type="button" class="btn btn-primary mt-3" onclick="saveNewAddress()">
                                <i class="bi bi-save me-1"></i> Save Address
                            </button>
                        </div>
                    </div>
                </div>
                <div class="text-end">
                    <button type="button" class="btn btn-primary px-4" onclick="goToStep(2)">
                        Continue to Review <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>
                <?php if ($taxMode === 'vat'): ?>
                <!-- VAT Number Input (for VAT mode B2B buyers) -->
                <div class="card border-0 shadow-sm mt-3">
                    <div class="card-body py-3">
                        <label class="form-label fw-semibold small"><i class="bi bi-eu me-1 text-info"></i>VAT Number <span class="text-muted fw-normal">(optional — for B2B reverse charge)</span></label>
                        <div class="input-group input-group-sm">
                            <input type="text" id="vatNumberInput" class="form-control" placeholder="e.g. DE123456789" maxlength="20">
                            <button type="button" class="btn btn-outline-info" onclick="validateVat()">
                                <i class="bi bi-check-circle me-1"></i>Validate
                            </button>
                        </div>
                        <div id="vatValidationMsg" class="mt-1 small"></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- ===== STEP 2: Order Review ===== -->
            <div id="checkoutStep2" class="checkout-panel d-none">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-cart3 text-primary me-2"></i>Step 2: Order Review</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php foreach ($supplierGroups as $group): ?>
                        <div class="p-3 border-bottom">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-shop text-muted me-2"></i>
                                <span class="fw-semibold"><?= e($group['supplier_name']) ?></span>
                            </div>
                            <?php foreach ($group['items'] as $item): ?>
                            <div class="d-flex align-items-center py-2 border-top">
                                <?php if ($item['image']): ?>
                                <img src="<?= e($item['image']) ?>" alt="" class="rounded me-3" style="width:56px;height:56px;object-fit:cover">
                                <?php else: ?>
                                <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center" style="width:56px;height:56px">
                                    <i class="bi bi-image text-muted"></i>
                                </div>
                                <?php endif; ?>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold"><?= e(mb_strimwidth($item['product_name'], 0, 50, '…')) ?></div>
                                    <?php if ($item['sku_info']): ?><small class="text-muted"><?= e($item['sku_info']) ?></small><?php endif; ?>
                                    <div class="text-muted small">Qty: <?= (int)$item['quantity'] ?> × <?= formatMoney($item['unit_price']) ?></div>
                                </div>
                                <div class="fw-bold"><?= formatMoney($item['subtotal']) ?></div>
                            </div>
                            <?php endforeach; ?>
                            <div class="d-flex justify-content-between pt-2 text-muted small">
                                <span>Subtotal</span>
                                <span class="fw-semibold"><?= formatMoney($group['subtotal']) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <p class="text-muted small"><i class="bi bi-pencil me-1"></i><a href="/pages/cart/index.php">Edit cart</a></p>
                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" onclick="goToStep(1)">
                        <i class="bi bi-arrow-left me-1"></i> Back
                    </button>
                    <button type="button" class="btn btn-primary px-4" onclick="goToStep(3)">
                        Continue to Payment <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>

            <!-- ===== STEP 3: Payment Method ===== -->
            <div id="checkoutStep3" class="checkout-panel d-none">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-wallet2 text-primary me-2"></i>Step 3: Payment Method</h6>
                    </div>
                    <div class="card-body">

                        <!-- Stripe Card -->
                        <div class="payment-option mb-3 p-3 border rounded <?= $stripePublishableKey ? '' : 'd-none' ?>" id="pm_opt_stripe">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" value="stripe" id="pm_stripe"
                                       checked onchange="onPaymentChange()">
                                <label class="form-check-label d-flex align-items-center gap-2" for="pm_stripe">
                                    <i class="bi bi-credit-card-2-front fs-5 text-primary"></i>
                                    <div>
                                        <strong>Credit / Debit Card</strong>
                                        <small class="d-block text-muted">Visa, Mastercard, Amex — Secured by Stripe</small>
                                    </div>
                                </label>
                            </div>
                            <div id="stripeSection" class="mt-3 ps-4">
                                <div id="card-element" class="form-control py-2" style="min-height:44px"></div>
                                <div id="card-errors" class="text-danger small mt-1"></div>
                            </div>
                        </div>

                        <?php if ($showCod): ?>
                        <!-- COD -->
                        <div class="payment-option mb-3 p-3 border rounded" id="pm_opt_cod">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" value="cod" id="pm_cod"
                                       <?= !$stripePublishableKey ? 'checked' : '' ?> onchange="onPaymentChange()">
                                <label class="form-check-label d-flex align-items-center gap-2" for="pm_cod">
                                    <i class="bi bi-cash-coin fs-5 text-success"></i>
                                    <div>
                                        <strong>Cash on Delivery</strong>
                                        <small class="d-block text-muted">Pay when your order arrives</small>
                                    </div>
                                </label>
                            </div>
                            <div id="codSection" class="mt-2 ps-4 d-none">
                                <div class="alert alert-light border p-2 small mb-0">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Pay the delivery person when your order arrives. No online payment needed.
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($showBankTransfer): ?>
                        <!-- Bank Transfer -->
                        <div class="payment-option mb-3 p-3 border rounded" id="pm_opt_bank">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" value="bank_transfer" id="pm_bank"
                                       onchange="onPaymentChange()">
                                <label class="form-check-label d-flex align-items-center gap-2" for="pm_bank">
                                    <i class="bi bi-bank fs-5 text-secondary"></i>
                                    <div>
                                        <strong>Bank Transfer</strong>
                                        <small class="d-block text-muted">Manual transfer — order held until confirmed</small>
                                    </div>
                                </label>
                            </div>
                            <div id="bankSection" class="mt-2 ps-4 d-none">
                                <div class="alert alert-light border p-2 small mb-0">
                                    <i class="bi bi-info-circle me-1"></i>
                                    After placing your order, you will receive bank account details to complete the transfer.
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" onclick="goToStep(2)">
                        <i class="bi bi-arrow-left me-1"></i> Back
                    </button>
                    <button type="button" class="btn btn-primary px-4" onclick="goToStep(4)">
                        Review Order <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>

            <!-- ===== STEP 4: Confirm & Pay ===== -->
            <div id="checkoutStep4" class="checkout-panel d-none">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-check-circle text-primary me-2"></i>Step 4: Confirm &amp; Pay</h6>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-2">
                            <dt class="col-4 text-muted">Ship to</dt>
                            <dd class="col-8" id="confirmAddress">—</dd>
                            <dt class="col-4 text-muted">Payment</dt>
                            <dd class="col-8" id="confirmPaymentMethod">—</dd>
                        </dl>
                        <hr>
                        <div class="d-flex justify-content-between fw-bold fs-5">
                            <span>Total to Pay</span>
                            <span class="text-primary" id="confirmTotal"><?= formatMoney($totals['total']) ?></span>
                        </div>
                    </div>
                </div>

                <div id="nonStripeMsg" class="alert alert-info d-none"></div>

                <div id="orderSuccessMsg" class="alert alert-success d-none">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <strong>Order placed!</strong> Redirecting…
                </div>

                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" id="btnBack4" onclick="goToStep(3)">
                        <i class="bi bi-arrow-left me-1"></i> Back
                    </button>
                    <button type="button" class="btn btn-primary btn-lg px-5" id="placeOrderBtn" onclick="placeOrder()">
                        <i class="bi bi-lock-fill me-1"></i>
                        <span id="placeOrderBtnTxt">Place Order</span>
                    </button>
                </div>
            </div>

        </div><!-- /col-lg-7 -->

        <!-- Order Summary Sidebar -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm sticky-top" style="top:80px">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold">Order Summary</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" style="max-height:260px;overflow-y:auto">
                        <?php foreach ($cartItems as $item): ?>
                        <div class="list-group-item px-3 py-2 d-flex align-items-center gap-2">
                            <?php if ($item['image']): ?>
                            <img src="<?= e($item['image']) ?>" alt="" class="rounded" style="width:40px;height:40px;object-fit:cover">
                            <?php else: ?>
                            <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width:40px;height:40px">
                                <i class="bi bi-image text-muted small"></i>
                            </div>
                            <?php endif; ?>
                            <div class="flex-grow-1">
                                <div class="fw-semibold small"><?= e(mb_strimwidth($item['product_name'], 0, 35, '…')) ?></div>
                                <div class="text-muted" style="font-size:.8rem">×<?= (int)$item['quantity'] ?></div>
                            </div>
                            <span class="small fw-semibold"><?= formatMoney($item['subtotal']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="p-3">
                        <dl class="row mb-0 small">
                            <dt class="col-6">Subtotal</dt>
                            <dd class="col-6 text-end" id="sumSubtotal"><?= formatMoney($totals['subtotal']) ?></dd>
                            <dt class="col-6">Shipping</dt>
                            <dd class="col-6 text-end" id="sumShipping">
                                <?= $totals['shipping'] > 0 ? formatMoney($totals['shipping']) : '<span class="text-success">Free</span>' ?>
                            </dd>
                            <?php if ($taxEnabled): ?>
                            <dt class="col-6" id="taxLabel"><?= e($taxLabel) ?></dt>
                            <dd class="col-6 text-end" id="sumTax"><?= formatMoney($totals['tax'] ?? 0) ?></dd>
                            <?php else: ?>
                            <dt class="col-6">Tax</dt>
                            <dd class="col-6 text-end" id="sumTax"><?= formatMoney($totals['tax'] ?? 0) ?></dd>
                            <?php endif; ?>
                        </dl>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between fw-bold">
                            <span>Total</span>
                            <span class="text-primary" id="sumTotal"><?= formatMoney($totals['total']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /row -->
</div><!-- /container -->

<?php if ($stripePublishableKey): ?>
<script src="https://js.stripe.com/v3/"></script>
<?php endif; ?>

<script>
const CSRF_TOKEN      = <?= json_encode(csrfToken()) ?>;
const STRIPE_PUB_KEY  = <?= json_encode($stripePublishableKey) ?>;
const SHOW_COD        = <?= json_encode($showCod) ?>;
const SHOW_BANK       = <?= json_encode($showBankTransfer) ?>;
const TAX_MODE        = <?= json_encode($taxMode) ?>;
const TAX_ENABLED     = <?= json_encode($taxEnabled) ?>;

let currentStep    = 1;
let selectedAddrId = <?= $defaultAddr ? (int)$defaultAddr['id'] : 'null' ?>;
let stripeObj      = null;
let stripeCard     = null;
let cardMounted    = false;

const PM_LABELS = { stripe: 'Credit / Debit Card (Stripe)', cod: 'Cash on Delivery', bank_transfer: 'Bank Transfer' };

if (typeof Stripe !== 'undefined' && STRIPE_PUB_KEY) {
    stripeObj = Stripe(STRIPE_PUB_KEY);
    const elements = stripeObj.elements();
    stripeCard = elements.create('card', { style: { base: { fontSize: '16px', color: '#343a40' } } });
}

// ---------- Step navigation ----------
function goToStep(step) {
    if (step === 2 && !selectedAddrId) {
        showAlert('Please select or add a shipping address before continuing.', 'warning');
        return;
    }
    document.querySelectorAll('.checkout-panel').forEach(el => el.classList.add('d-none'));
    document.getElementById('checkoutStep' + step).classList.remove('d-none');
    updateStepIndicators(step);

    const pm = getSelectedPM();
    if ((step === 3 || step === 4) && stripeCard && pm === 'stripe' && !cardMounted) {
        stripeCard.mount('#card-element');
        cardMounted = true;
        stripeCard.on('change', e => {
            document.getElementById('card-errors').textContent = e.error ? e.error.message : '';
        });
    }
    if (step === 4) fillConfirmStep();
    currentStep = step;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function updateStepIndicators(activeStep) {
    for (let i = 1; i <= 4; i++) {
        const circle = document.querySelector('#step-ind-' + i + ' .step-circle');
        if (!circle) continue;
        circle.classList.remove('active', 'done');
        if (i < activeStep) circle.classList.add('done');
        else if (i === activeStep) circle.classList.add('active');
    }
    for (let i = 1; i <= 3; i++) {
        const c = document.getElementById('connector-' + i);
        if (c) c.classList.toggle('done', i < activeStep);
    }
}

// ---------- Address ----------
function selectAddress(addrId) {
    selectedAddrId = addrId;
    document.querySelectorAll('.address-card').forEach(card => {
        const sel = parseInt(card.dataset.addrId) === addrId;
        card.classList.toggle('selected-addr', sel);
    });
    recalcTotals(addrId);
}

function toggleNewAddressForm() {
    const f = document.getElementById('newAddressForm');
    f.style.display = f.style.display === 'none' ? 'block' : 'none';
}

async function saveNewAddress() {
    const formData = new FormData();
    formData.append('_csrf_token',   CSRF_TOKEN);
    formData.append('full_name',     document.getElementById('na_full_name').value.trim());
    formData.append('phone',         document.getElementById('na_phone').value.trim());
    formData.append('address_line1', document.getElementById('na_address_line1').value.trim());
    formData.append('address_line2', document.getElementById('na_address_line2').value.trim());
    formData.append('city',          document.getElementById('na_city').value.trim());
    formData.append('state',         document.getElementById('na_state').value.trim());
    formData.append('postal_code',   document.getElementById('na_postal_code').value.trim());
    formData.append('country',       document.getElementById('na_country').value);
    formData.append('is_default',    document.getElementById('na_is_default').checked ? '1' : '0');

    const errEl = document.getElementById('newAddrError');
    errEl.textContent = '';
    try {
        const res  = await fetch('/api/checkout.php?action=add_address', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        } else {
            errEl.textContent = data.errors ? Object.values(data.errors).join(' ') : (data.error || 'Failed to save address.');
        }
    } catch (e) {
        errEl.textContent = 'Network error. Please try again.';
    }
}

async function recalcTotals(addrId) {
    try {
        const fd = new FormData();
        fd.append('_csrf_token', CSRF_TOKEN);
        fd.append('address_id',  addrId);
        const res  = await fetch('/api/checkout.php?action=calculate_totals', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.total !== undefined) {
            const fmt = v => '$' + parseFloat(v).toFixed(2);
            document.getElementById('sumSubtotal').textContent = fmt(data.subtotal);
            document.getElementById('sumShipping').textContent = parseFloat(data.shipping) > 0 ? fmt(data.shipping) : 'Free';
            document.getElementById('sumTax').textContent      = fmt(data.tax);
            document.getElementById('sumTotal').textContent    = fmt(data.total);
            document.getElementById('confirmTotal').textContent = fmt(data.total);
        }
        // Also recalculate tax via our tax engine if feature enabled
        if (TAX_ENABLED && data.subtotal > 0) {
            recalcTax(addrId, data.subtotal);
        }
    } catch (e) { /* silent */ }
}

async function recalcTax(addrId, subtotal) {
    try {
        // Get country/state from selected address card
        const addrCard = document.querySelector('.address-card.selected-addr');
        if (!addrCard) return;
        const addrText = addrCard.querySelector('small')?.textContent || '';
        // We only need tax if mode != fixed (fixed is already in the server totals)
        if (TAX_MODE === 'fixed') return;

        const vatInput = document.getElementById('vatNumberInput');
        const vatNumber = vatInput ? vatInput.value.trim() : '';

        const fd = new FormData();
        fd.append('_csrf_token',  CSRF_TOKEN);
        fd.append('subtotal',     subtotal);
        fd.append('address_id',   addrId);
        fd.append('vat_number',   vatNumber);
        const res  = await fetch('/api/tax.php?action=calculate', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success && data.data) {
            const t = data.data;
            const fmt = v => '$' + parseFloat(v).toFixed(2);
            document.getElementById('sumTax').textContent = fmt(t.tax_amount);
            // Update total
            const sub = parseFloat(document.getElementById('sumSubtotal').textContent.replace('$','')) || 0;
            const sh  = parseFloat(document.getElementById('sumShipping').textContent.replace('$','').replace('Free','0')) || 0;
            document.getElementById('sumTotal').textContent = fmt(sub + sh + parseFloat(t.tax_amount));
            document.getElementById('confirmTotal').textContent = document.getElementById('sumTotal').textContent;

            // Show reverse charge message if applicable
            const vatMsg = document.getElementById('vatValidationMsg');
            if (vatMsg && t.is_reverse_charge) {
                vatMsg.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>VAT reverse charge applied — 0% VAT</span>';
            }
        }
    } catch (e) { /* silent */ }
}

async function validateVat() {
    const vatInput = document.getElementById('vatNumberInput');
    const msgEl    = document.getElementById('vatValidationMsg');
    if (!vatInput || !msgEl) return;
    const vatNumber = vatInput.value.trim();
    if (!vatNumber) { msgEl.innerHTML = '<span class="text-muted">Enter a VAT number to validate.</span>'; return; }

    msgEl.innerHTML = '<span class="text-muted"><span class="spinner-border spinner-border-sm me-1"></span>Validating…</span>';
    try {
        const fd = new FormData();
        fd.append('_csrf_token',  CSRF_TOKEN);
        fd.append('vat_number',   vatNumber);
        const res  = await fetch('/api/tax.php?action=validate_vat', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success && data.data) {
            if (data.data.valid) {
                msgEl.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Valid VAT number — reverse charge may apply</span>';
                if (selectedAddrId) recalcTax(selectedAddrId, parseFloat(document.getElementById('sumSubtotal').textContent.replace('$','')) || 0);
            } else {
                msgEl.innerHTML = '<span class="text-warning"><i class="bi bi-exclamation-circle me-1"></i>Invalid VAT number format</span>';
            }
        }
    } catch (e) {
        msgEl.innerHTML = '<span class="text-danger">Validation failed. Please try again.</span>';
    }
}

// ---------- Payment method ----------
function getSelectedPM() {
    return document.querySelector('input[name=payment_method]:checked')?.value || 'stripe';
}

function onPaymentChange() {
    const pm = getSelectedPM();
    // Show/hide Stripe card element
    const stripeSection = document.getElementById('stripeSection');
    if (stripeSection) stripeSection.style.display = pm === 'stripe' ? 'block' : 'none';
    // Show/hide COD info
    const codSection = document.getElementById('codSection');
    if (codSection) codSection.classList.toggle('d-none', pm !== 'cod');
    // Show/hide Bank info
    const bankSection = document.getElementById('bankSection');
    if (bankSection) bankSection.classList.toggle('d-none', pm !== 'bank_transfer');

    // Highlight selected option
    document.querySelectorAll('.payment-option').forEach(el => el.classList.remove('selected'));
    const selOpt = document.getElementById('pm_opt_' + pm.replace('_transfer',''));
    if (!selOpt && pm === 'bank_transfer') {
        const b = document.getElementById('pm_opt_bank');
        if (b) b.classList.add('selected');
    } else if (selOpt) {
        selOpt.classList.add('selected');
    }

    // Mount/unmount Stripe card
    if (pm === 'stripe' && stripeCard && !cardMounted) {
        stripeCard.mount('#card-element');
        cardMounted = true;
        stripeCard.on('change', e => {
            document.getElementById('card-errors').textContent = e.error ? e.error.message : '';
        });
    }
}

// ---------- Confirm step ----------
function fillConfirmStep() {
    // Address label
    const cards = document.querySelectorAll('.address-card');
    let addrText = '—';
    cards.forEach(card => {
        if (parseInt(card.dataset.addrId) === selectedAddrId) {
            addrText = card.querySelector('small')?.textContent.trim().replace(/\s+/g,' ') || '—';
        }
    });
    document.getElementById('confirmAddress').textContent = addrText;

    // Payment method
    const pm = getSelectedPM();
    document.getElementById('confirmPaymentMethod').textContent = PM_LABELS[pm] || pm;
}

// ---------- Place order ----------
async function placeOrder() {
    const btn    = document.getElementById('placeOrderBtn');
    const btnTxt = document.getElementById('placeOrderBtnTxt');
    const pm     = getSelectedPM();

    if (!selectedAddrId) {
        showAlert('Please select a shipping address.', 'warning');
        goToStep(1);
        return;
    }

    btn.disabled = true;
    btnTxt.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing…';

    const fd = new FormData();
    fd.append('_csrf_token',    CSRF_TOKEN);
    fd.append('address_id',     selectedAddrId);
    fd.append('payment_method', pm);

    let data;
    try {
        const res = await fetch('/api/checkout.php?action=create_payment_intent', { method: 'POST', body: fd });
        data = await res.json();
    } catch (e) {
        showAlert('Network error. Please try again.', 'danger');
        resetBtn(btn, btnTxt);
        return;
    }

    if (!data.success) {
        const errMsg = data.errors ? Object.values(data.errors).join(' ') : (data.error || 'Order failed.');
        showAlert(errMsg, 'danger');
        resetBtn(btn, btnTxt);
        return;
    }

    // Stripe card payment
    if (pm === 'stripe') {
        if (!stripeObj || !stripeCard) {
            showAlert('Stripe is not available. Please try another payment method.', 'danger');
            resetBtn(btn, btnTxt);
            return;
        }
        const result = await stripeObj.confirmCardPayment(data.client_secret, {
            payment_method: { card: stripeCard }
        });
        if (result.error) {
            document.getElementById('card-errors').textContent = result.error.message;
            showAlert(result.error.message, 'danger');
            resetBtn(btn, btnTxt);
            return;
        }

        // Confirm on server
        const cfd = new FormData();
        cfd.append('_csrf_token',        CSRF_TOKEN);
        cfd.append('order_id',           data.order_id);
        cfd.append('payment_intent_id',  result.paymentIntent.id);
        try {
            const cres  = await fetch('/api/checkout.php?action=confirm_order', { method: 'POST', body: cfd });
            const cdata = await cres.json();
            if (cdata.success) {
                showSuccessAndRedirect(data.order_id);
                return;
            } else {
                showAlert(cdata.error || 'Confirmation failed.', 'danger');
                resetBtn(btn, btnTxt);
                return;
            }
        } catch (e) {
            showAlert('Confirmation error. Please contact support.', 'danger');
            resetBtn(btn, btnTxt);
            return;
        }
    }

    // COD / Bank Transfer
    const msgEl = document.getElementById('nonStripeMsg');
    if (pm === 'cod') {
        msgEl.className = 'alert alert-success';
        msgEl.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i>Order placed successfully! Pay on delivery.';
        msgEl.classList.remove('d-none');
    } else if (pm === 'bank_transfer') {
        let bankHtml = '<i class="bi bi-bank me-2"></i><strong>Order placed!</strong> Please transfer to:<br>';
        if (data.bank_details && Object.keys(data.bank_details).length) {
            for (const [k, v] of Object.entries(data.bank_details)) {
                bankHtml += '<br><strong>' + k.replace(/_/g,' ') + ':</strong> ' + escHtml(v);
            }
        }
        msgEl.className = 'alert alert-info';
        msgEl.innerHTML = bankHtml;
        msgEl.classList.remove('d-none');
    }

    document.getElementById('orderSuccessMsg').classList.remove('d-none');
    document.getElementById('btnBack4').classList.add('d-none');
    btn.classList.add('d-none');

    setTimeout(() => {
        window.location.href = '/pages/checkout/confirmation.php?order_id=' + data.order_id;
    }, 2000);
}

// ---------- Utilities ----------
function showAlert(msg, type) {
    const el = document.getElementById('checkoutAlert');
    el.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible"><button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>' + escHtml(msg) + '</div>';
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function resetBtn(btn, btnTxt) {
    btn.disabled = false;
    btnTxt.innerHTML = 'Place Order';
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Initialise
onPaymentChange();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
