<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$pageTitle = 'Create Escrow';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item"><a href="/pages/escrow/">Escrow</a></li>
            <li class="breadcrumb-item active">Create</li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-shield-plus me-2"></i>Create Escrow Transaction</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Escrow protects both buyers and sellers by holding funds securely until both parties are satisfied.
                    </div>

                    <div id="alertContainer"></div>

                    <form id="escrowForm" method="post" action="/api/escrow.php?action=create">
                        <?= csrfField() ?>

                        <div class="mb-3">
                            <label for="order_id" class="form-label">Order ID <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-receipt"></i></span>
                                <input type="text" class="form-control" id="order_id" name="order_id" required
                                       placeholder="Enter order ID or reference">
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-8">
                                <label for="amount" class="form-label">Amount <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-currency-dollar"></i></span>
                                    <input type="number" class="form-control" id="amount" name="amount" required
                                           min="0.01" step="0.01" placeholder="0.00">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="currency" class="form-label">Currency <span class="text-danger">*</span></label>
                                <select class="form-select" id="currency" name="currency" required>
                                    <option value="USD" selected>USD ($)</option>
                                    <option value="EUR">EUR (€)</option>
                                    <option value="GBP">GBP (£)</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4"
                                      placeholder="Describe the transaction, items involved, terms agreed upon..."></textarea>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between">
                            <a href="/pages/escrow/" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="bi bi-shield-plus me-1"></i>Create Escrow
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-body">
                    <h6><i class="bi bi-question-circle me-2"></i>How Escrow Works</h6>
                    <div class="row text-center mt-3 g-2">
                        <div class="col-3">
                            <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-2" style="width:48px;height:48px">
                                <i class="bi bi-1-circle text-primary fs-5"></i>
                            </div>
                            <small class="d-block text-muted">Create Escrow</small>
                        </div>
                        <div class="col-3">
                            <div class="rounded-circle bg-info bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-2" style="width:48px;height:48px">
                                <i class="bi bi-2-circle text-info fs-5"></i>
                            </div>
                            <small class="d-block text-muted">Funds Held</small>
                        </div>
                        <div class="col-3">
                            <div class="rounded-circle bg-warning bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-2" style="width:48px;height:48px">
                                <i class="bi bi-3-circle text-warning fs-5"></i>
                            </div>
                            <small class="d-block text-muted">Goods Delivered</small>
                        </div>
                        <div class="col-3">
                            <div class="rounded-circle bg-success bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-2" style="width:48px;height:48px">
                                <i class="bi bi-4-circle text-success fs-5"></i>
                            </div>
                            <small class="d-block text-muted">Funds Released</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('escrowForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const alert = document.getElementById('alertContainer');
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating...';

    fetch(this.action, { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-shield-plus me-1"></i>Create Escrow';
            if (data.success || data.status === 'success') {
                alert.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Escrow created!</div>';
                const newId = data.id || data.escrow_id;
                setTimeout(() => window.location.href = newId ? '/pages/escrow/detail.php?id=' + newId : '/pages/escrow/', 1500);
            } else {
                alert.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>' + (data.message || data.error || 'Failed to create escrow') + '</div>';
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-shield-plus me-1"></i>Create Escrow';
            alert.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>Network error. Please try again.</div>';
        });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
