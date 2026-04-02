<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$pageTitle = 'Redeem Coupon';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item"><a href="/pages/coupons/">Coupons</a></li>
            <li class="breadcrumb-item active">Redeem</li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0"><i class="bi bi-gift me-2"></i>Redeem Coupon</h4>
                </div>
                <div class="card-body">
                    <div id="alertContainer"></div>

                    <form id="redeemForm">
                        <?= csrfField() ?>

                        <div class="mb-3">
                            <label for="coupon_code" class="form-label">Coupon Code <span class="text-danger">*</span></label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text"><i class="bi bi-ticket-perforated"></i></span>
                                <input type="text" class="form-control text-uppercase" id="coupon_code" name="code"
                                       required placeholder="Enter coupon code" autocomplete="off">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="order_amount" class="form-label">Order Amount <span class="text-danger">*</span></label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text"><i class="bi bi-currency-dollar"></i></span>
                                <input type="number" class="form-control" id="order_amount" name="order_amount"
                                       required min="0" step="0.01" placeholder="0.00">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success btn-lg w-100" id="applyBtn">
                            <i class="bi bi-check-circle me-2"></i>Apply Coupon
                        </button>
                    </form>

                    <!-- Discount Result -->
                    <div id="discountResult" class="d-none mt-4">
                        <hr>
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5 class="card-title text-success"><i class="bi bi-check-circle-fill me-2"></i>Coupon Applied!</h5>
                                <div class="row text-center mt-3">
                                    <div class="col-4">
                                        <small class="text-muted d-block">Order Amount</small>
                                        <span class="fs-5 fw-bold" id="resultOrderAmount">$0.00</span>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted d-block">Discount</small>
                                        <span class="fs-5 fw-bold text-danger" id="resultDiscount">-$0.00</span>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted d-block">Final Total</small>
                                        <span class="fs-5 fw-bold text-success" id="resultTotal">$0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-info-circle me-2"></i>How It Works</h6>
                    <ol class="mb-0 small text-muted">
                        <li>Enter your coupon code above</li>
                        <li>Enter your order amount to see the discount</li>
                        <li>Click "Apply Coupon" to validate and calculate</li>
                        <li>The discount will be applied at checkout</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('redeemForm')?.addEventListener('submit', function(e) {
    e.preventDefault();

    const code = document.getElementById('coupon_code').value.trim();
    const amount = parseFloat(document.getElementById('order_amount').value);
    const alertContainer = document.getElementById('alertContainer');
    const resultDiv = document.getElementById('discountResult');
    const btn = document.getElementById('applyBtn');

    if (!code || !amount || amount <= 0) {
        alertContainer.innerHTML = '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Please fill in all fields.</div>';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Validating...';
    alertContainer.innerHTML = '';
    resultDiv.classList.add('d-none');

    const formData = new FormData(this);
    fetch('/api/coupons.php?action=validate', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Apply Coupon';

        if (data.valid || data.success || data.status === 'success') {
            const discount = parseFloat(data.discount || 0);
            const total = Math.max(0, amount - discount);

            document.getElementById('resultOrderAmount').textContent = '$' + amount.toFixed(2);
            document.getElementById('resultDiscount').textContent = '-$' + discount.toFixed(2);
            document.getElementById('resultTotal').textContent = '$' + total.toFixed(2);
            resultDiv.classList.remove('d-none');
            alertContainer.innerHTML = '';
        } else {
            alertContainer.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>' +
                (data.message || data.error || 'Invalid coupon code') + '</div>';
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Apply Coupon';
        alertContainer.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>Network error. Please try again.</div>';
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
