<?php
/**
 * pages/supplier/marketing/coupon-form.php — Create / Edit Coupon (PR #13)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/coupons.php';
requireRole(['supplier', 'admin', 'super_admin']);

$user       = getCurrentUser();
$supplierId = (int)$user['id'];
$couponId   = (int)($_GET['id'] ?? 0);
$coupon     = $couponId > 0 ? getCoupon($couponId) : null;
$isEdit     = $coupon !== null;

// Ownership check for supplier
if ($isEdit && $user['role'] === 'supplier' && (int)$coupon['created_by'] !== $supplierId) {
    flashMessage('danger', 'Unauthorized.');
    redirect('/pages/supplier/marketing/coupons.php');
}

// Load supplier's products for multi-select
$db = getDB();
$myProducts = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM products WHERE supplier_id = ? AND status = 'active' ORDER BY name ASC");
    $stmt->execute([$supplierId]);
    $myProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* ignore */ }

$appliedProducts = $coupon ? (json_decode($coupon['applicable_products_json'] ?? '[]', true) ?: []) : [];

$pageTitle = $isEdit ? 'Edit Coupon' : 'Create Coupon';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container py-4" style="max-width:720px">

    <div class="d-flex align-items-center mb-4 gap-3">
        <a href="/pages/supplier/marketing/coupons.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <h4 class="mb-0 fw-bold"><?= $isEdit ? 'Edit Coupon' : 'Create New Coupon' ?></h4>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form id="couponForm">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <?php if ($isEdit): ?>
                <input type="hidden" name="coupon_id" value="<?= $couponId ?>">
                <?php endif; ?>

                <!-- Code -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Coupon Code <small class="text-muted fw-normal">(leave blank to auto-generate)</small></label>
                    <div class="input-group">
                        <input type="text" id="couponCode" name="code" class="form-control text-uppercase"
                               placeholder="e.g. SUMMER20" maxlength="50" style="text-transform:uppercase"
                               value="<?= $isEdit ? e($coupon['code']) : '' ?>">
                        <button type="button" class="btn btn-outline-secondary" onclick="generateCode()">
                            <i class="bi bi-shuffle me-1"></i>Generate
                        </button>
                    </div>
                    <div id="codeMsg" class="form-text"></div>
                </div>

                <!-- Type -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Type</label>
                    <select name="type" id="couponType" class="form-select" onchange="onTypeChange()">
                        <option value="percentage"    <?= ($coupon['type'] ?? '') === 'percentage'    ? 'selected' : '' ?>>Percentage Off (%)</option>
                        <option value="fixed"         <?= ($coupon['type'] ?? '') === 'fixed'         ? 'selected' : '' ?>>Fixed Amount Off ($)</option>
                        <option value="free_shipping" <?= ($coupon['type'] ?? '') === 'free_shipping' ? 'selected' : '' ?>>Free Shipping</option>
                    </select>
                </div>

                <!-- Value -->
                <div class="mb-3" id="valueRow">
                    <label class="form-label fw-semibold" id="valueLabel">Discount Value</label>
                    <div class="input-group">
                        <span class="input-group-text" id="valuePrefix">%</span>
                        <input type="number" name="value" id="couponValue" class="form-control"
                               min="0.01" step="0.01" max="100"
                               value="<?= $isEdit ? e($coupon['value']) : '' ?>" required>
                    </div>
                    <div id="previewLine" class="form-text text-success"></div>
                </div>

                <!-- Min order / Max discount -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Minimum Order Amount ($)</label>
                        <input type="number" name="min_order_amount" class="form-control" min="0" step="0.01"
                               value="<?= $isEdit ? e($coupon['min_order_amount']) : '0' ?>">
                    </div>
                    <div class="col-md-6" id="maxDiscountRow">
                        <label class="form-label fw-semibold">Max Discount Cap ($) <small class="text-muted">for % type</small></label>
                        <input type="number" name="max_discount_amount" class="form-control" min="0" step="0.01"
                               value="<?= $isEdit && $coupon['max_discount_amount'] ? e($coupon['max_discount_amount']) : '' ?>"
                               placeholder="No cap">
                    </div>
                </div>

                <!-- Usage limits -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Total Usage Limit <small class="text-muted">(0 = unlimited)</small></label>
                        <input type="number" name="usage_limit" class="form-control" min="0" step="1"
                               value="<?= $isEdit ? e($coupon['usage_limit'] ?? 0) : '0' ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Per-User Limit</label>
                        <input type="number" name="per_user_limit" class="form-control" min="1" step="1"
                               value="<?= $isEdit ? e($coupon['per_user_limit'] ?? 1) : '1' ?>">
                    </div>
                </div>

                <!-- Valid dates -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Valid From</label>
                        <input type="datetime-local" name="valid_from" class="form-control"
                               value="<?= $isEdit && $coupon['valid_from'] ? date('Y-m-d\TH:i', strtotime($coupon['valid_from'])) : '' ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Valid To</label>
                        <input type="datetime-local" name="valid_to" class="form-control"
                               value="<?= $isEdit && $coupon['valid_to'] ? date('Y-m-d\TH:i', strtotime($coupon['valid_to'])) : '' ?>">
                    </div>
                </div>

                <!-- Applicable products -->
                <?php if (!empty($myProducts)): ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Applicable Products</label>
                    <select name="applicable_products[]" class="form-select" multiple size="5">
                        <option value="" <?= empty($appliedProducts) ? 'selected' : '' ?>>All My Products</option>
                        <?php foreach ($myProducts as $mp): ?>
                        <option value="<?= (int)$mp['id'] ?>" <?= in_array((int)$mp['id'], $appliedProducts) ? 'selected' : '' ?>>
                            <?= e($mp['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Hold Ctrl/Cmd to select multiple. Leave as "All My Products" for all.</div>
                </div>
                <?php endif; ?>

                <!-- Description -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Description / Notes</label>
                    <textarea name="description" class="form-control" rows="2" maxlength="255"
                              placeholder="Internal notes about this coupon"><?= $isEdit ? e($coupon['description'] ?? '') : '' ?></textarea>
                </div>

                <!-- Active toggle -->
                <div class="mb-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="isActive" value="1"
                               <?= (!$isEdit || $coupon['is_active']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>
                </div>

                <!-- Preview -->
                <div id="couponPreview" class="alert alert-success d-none mb-3">
                    <i class="bi bi-calculator me-1"></i>
                    <span id="previewText"></span>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success px-4">
                        <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Save Changes' : 'Create Coupon' ?>
                    </button>
                    <a href="/pages/supplier/marketing/coupons.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN   = <?= json_encode(csrfToken()) ?>;
const IS_EDIT      = <?= json_encode($isEdit) ?>;
const ACTION       = IS_EDIT ? 'update' : 'create';

function onTypeChange() {
    const type  = document.getElementById('couponType').value;
    const prefix = document.getElementById('valuePrefix');
    const maxRow = document.getElementById('maxDiscountRow');
    const valInput = document.getElementById('couponValue');
    if (type === 'percentage') {
        prefix.textContent = '%';
        valInput.max = 100;
        maxRow.style.display = '';
    } else if (type === 'fixed') {
        prefix.textContent = '$';
        valInput.max = 99999;
        maxRow.style.display = 'none';
    } else {
        prefix.textContent = '—';
        maxRow.style.display = 'none';
    }
    updatePreview();
}

function updatePreview() {
    const type  = document.getElementById('couponType').value;
    const value = parseFloat(document.getElementById('couponValue').value) || 0;
    const previewEl = document.getElementById('couponPreview');
    const previewTx = document.getElementById('previewText');
    const sampleOrder = 100;
    let saved = 0;
    let msg = '';
    if (type === 'percentage' && value > 0) {
        saved = Math.round(sampleOrder * value / 100 * 100) / 100;
        msg = `On a $${sampleOrder.toFixed(2)} order, this coupon saves the buyer $${saved.toFixed(2)}`;
        previewEl.classList.remove('d-none');
    } else if (type === 'fixed' && value > 0) {
        saved = Math.min(value, sampleOrder);
        msg = `On a $${sampleOrder.toFixed(2)} order, this coupon saves the buyer $${saved.toFixed(2)}`;
        previewEl.classList.remove('d-none');
    } else if (type === 'free_shipping') {
        msg = 'This coupon waives the shipping fee for the buyer.';
        previewEl.classList.remove('d-none');
    } else {
        previewEl.classList.add('d-none');
    }
    previewTx.textContent = msg;
}

function generateCode() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let suffix = '';
    for (let i = 0; i < 6; i++) suffix += chars[Math.floor(Math.random() * chars.length)];
    document.getElementById('couponCode').value = 'PROMO-' + suffix;
    checkCodeUnique();
}

function checkCodeUnique() {
    const code = document.getElementById('couponCode').value.trim().toUpperCase();
    const msgEl = document.getElementById('codeMsg');
    if (!code) return;
    fetch('/api/coupons.php?action=get&code=' + encodeURIComponent(code))
        .then(r => r.json())
        .then(d => {
            if (d.success && (!IS_EDIT || d.data.id != <?= $couponId ?>)) {
                msgEl.className = 'form-text text-danger';
                msgEl.textContent = '✕ Code already in use';
            } else {
                msgEl.className = 'form-text text-success';
                msgEl.textContent = '✓ Code is available';
            }
        });
}

document.getElementById('couponCode').addEventListener('blur', checkCodeUnique);
document.getElementById('couponValue').addEventListener('input', updatePreview);
onTypeChange();

document.getElementById('couponForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form    = e.target;
    const btn     = form.querySelector('[type=submit]');
    const oldTxt  = btn.textContent;
    btn.disabled  = true;
    btn.textContent = 'Saving…';

    const fd = new FormData(form);
    // Ensure checkbox value
    if (!form.querySelector('#isActive').checked) fd.set('is_active', '0');

    fetch('/api/coupons.php?action=' + ACTION, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            btn.textContent = oldTxt;
            if (d.success) {
                window.location.href = '/pages/supplier/marketing/coupons.php';
            } else {
                alert(d.message || 'Error saving coupon');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.textContent = oldTxt;
            alert('Network error');
        });
});
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
