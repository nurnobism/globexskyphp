<?php
/**
 * pages/inspection/request.php — Inspection Request Form
 */

require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$pageTitle = 'Request Inspection';
include __DIR__ . '/../../includes/header.php';

$types = [
    'pre_production'    => ['label' => 'Pre-Production',     'price' => 150, 'desc' => 'Verify materials & factory readiness before production begins.'],
    'during_production' => ['label' => 'During Production',  'price' => 200, 'desc' => 'Monitor quality during manufacturing at the 20–80% stage.'],
    'pre_shipment'      => ['label' => 'Pre-Shipment',       'price' => 180, 'desc' => 'Final QC check before goods leave the factory.'],
    'full_audit'        => ['label' => 'Full Factory Audit', 'price' => 500, 'desc' => 'Comprehensive audit of the entire factory & processes.'],
];
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="mb-4">
                <a href="/pages/inspection/tracking.php" class="text-decoration-none text-muted">
                    <i class="bi bi-arrow-left me-1"></i>Back to Inspections
                </a>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header py-3" style="background:#1B2A4A;">
                    <h5 class="mb-0 text-white"><i class="bi bi-clipboard2-check me-2"></i>Request Quality Inspection</h5>
                </div>
                <div class="card-body p-4">
                    <div id="alertBox" class="d-none"></div>
                    <form id="inspectionForm">
                        <?= csrfField() ?>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Supplier Name <span class="text-danger">*</span></label>
                                <input type="text" name="supplier_name" class="form-control" placeholder="e.g. Shenzhen Tech Co." required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Product Name <span class="text-danger">*</span></label>
                                <input type="text" name="product_name" class="form-control" placeholder="e.g. Wireless Earbuds Model X" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Quantity (units) <span class="text-danger">*</span></label>
                                <input type="number" name="quantity" class="form-control" min="1" placeholder="500" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Inspection Date <span class="text-danger">*</span></label>
                                <input type="date" name="inspection_date" class="form-control" min="<?= date('Y-m-d', strtotime('+3 days')) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Estimated Price</label>
                                <div class="form-control bg-light fw-bold" id="priceDisplay" style="color:#FF6B35;">Select a type below</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Factory Address <span class="text-danger">*</span></label>
                                <textarea name="factory_address" class="form-control" rows="2" placeholder="Full factory address including city and country" required></textarea>
                            </div>
                        </div>

                        <h6 class="fw-bold mb-3">Select Inspection Type <span class="text-danger">*</span></h6>
                        <div class="row g-3 mb-4" id="typeCards">
                            <?php foreach ($types as $key => $t): ?>
                            <div class="col-md-6">
                                <label class="d-block h-100 cursor-pointer">
                                    <input type="radio" name="inspection_type" value="<?= $key ?>" class="d-none type-radio"
                                           data-price="<?= $t['price'] ?>">
                                    <div class="card h-100 border-2 type-card p-3" style="cursor:pointer;">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="fw-bold"><?= $t['label'] ?></div>
                                                <small class="text-muted"><?= $t['desc'] ?></small>
                                            </div>
                                            <span class="badge fs-6 ms-2" style="background:#FF6B35;">$<?= $t['price'] ?></span>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-lg text-white fw-semibold" style="background:#FF6B35;">
                                <i class="bi bi-send me-2"></i>Submit Inspection Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('.type-radio').forEach(r => {
    r.addEventListener('change', function () {
        document.querySelectorAll('.type-card').forEach(c => c.classList.remove('border-warning', 'bg-light'));
        this.closest('.type-card').classList.add('border-warning', 'bg-light');
        document.getElementById('priceDisplay').textContent = '$' + this.dataset.price + '.00';
    });
});
document.getElementById('inspectionForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = this.querySelector('[type=submit]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting…';
    const res = await fetch('/api/inspections.php?action=request', {method:'POST', body: new FormData(this)});
    const data = await res.json();
    const box = document.getElementById('alertBox');
    box.className = 'alert ' + (data.success ? 'alert-success' : 'alert-danger');
    box.textContent = data.success
        ? `Request submitted! Reference: ${data.reference_no} — Price: $${data.price}`
        : (data.error || 'Submission failed.');
    box.classList.remove('d-none');
    if (data.success) this.reset();
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-send me-2"></i>Submit Inspection Request';
});
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
