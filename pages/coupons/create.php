<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

if (!isAdmin()) {
    flashMessage('danger', 'Admin access required.');
    redirect('/');
}

$pageTitle = 'Create Coupon';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item"><a href="/pages/coupons/">Coupons</a></li>
            <li class="breadcrumb-item active">Create</li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Create New Coupon</h4>
                </div>
                <div class="card-body">
                    <div id="alertContainer"></div>

                    <form id="couponForm" method="post" action="/api/coupons.php?action=create">
                        <?= csrfField() ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="code" class="form-label">Coupon Code <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-tag"></i></span>
                                    <input type="text" class="form-control text-uppercase" id="code" name="code" required
                                           placeholder="e.g. SAVE20" pattern="[A-Za-z0-9_-]+" maxlength="50">
                                    <button type="button" class="btn btn-outline-secondary" id="generateCode" title="Generate random code">
                                        <i class="bi bi-shuffle"></i>
                                    </button>
                                </div>
                                <div class="form-text">Letters, numbers, hyphens, and underscores only.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="type" class="form-label">Discount Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="">Select type...</option>
                                    <option value="percentage">Percentage (%)</option>
                                    <option value="fixed">Fixed Amount ($)</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="value" class="form-label">Discount Value <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text" id="valuePrefix">$</span>
                                    <input type="number" class="form-control" id="value" name="value" required
                                           min="0" step="0.01" placeholder="0.00">
                                    <span class="input-group-text d-none" id="valueSuffix">%</span>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="min_order" class="form-label">Minimum Order Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-currency-dollar"></i></span>
                                    <input type="number" class="form-control" id="min_order" name="min_order"
                                           min="0" step="0.01" placeholder="0.00">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="max_uses" class="form-label">Maximum Uses</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-hash"></i></span>
                                    <input type="number" class="form-control" id="max_uses" name="max_uses"
                                           min="0" step="1" placeholder="Unlimited">
                                </div>
                                <div class="form-text">Leave empty for unlimited uses.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="datetime-local" class="form-control" id="start_date" name="start_date">
                            </div>

                            <div class="col-md-6">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="datetime-local" class="form-control" id="end_date" name="end_date">
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-between">
                            <a href="/pages/coupons/" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Create Coupon
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('type')?.addEventListener('change', function() {
    const prefix = document.getElementById('valuePrefix');
    const suffix = document.getElementById('valueSuffix');
    if (this.value === 'percentage') {
        prefix.classList.add('d-none');
        suffix.classList.remove('d-none');
    } else {
        prefix.classList.remove('d-none');
        suffix.classList.add('d-none');
    }
});

document.getElementById('generateCode')?.addEventListener('click', function() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let code = '';
    for (let i = 0; i < 8; i++) code += chars.charAt(Math.floor(Math.random() * chars.length));
    document.getElementById('code').value = code;
});

document.getElementById('couponForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const form = this;
    const alert = document.getElementById('alertContainer');

    fetch(form.action, { method: 'POST', body: new FormData(form) })
        .then(r => r.json())
        .then(data => {
            if (data.success || data.status === 'success') {
                alert.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Coupon created successfully!</div>';
                setTimeout(() => window.location.href = '/pages/coupons/', 1500);
            } else {
                alert.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>' + (data.message || data.error || 'Failed to create coupon') + '</div>';
            }
        })
        .catch(() => {
            alert.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>Network error. Please try again.</div>';
        });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
