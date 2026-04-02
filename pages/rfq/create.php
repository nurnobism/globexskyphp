<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$db = getDB();
$catStmt = $db->query('SELECT id, name FROM categories WHERE is_active=1 ORDER BY sort_order');
$categories = $catStmt->fetchAll();

$productId  = (int)get('product_id', 0);
$supplierId = (int)get('supplier_id', 0);
$product    = null;
if ($productId) {
    $s = $db->prepare('SELECT id, name FROM products WHERE id=?'); $s->execute([$productId]); $product = $s->fetch();
}

$pageTitle = 'Request for Quotation';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-file-text-fill me-2"></i>Submit Request for Quotation</h5>
                </div>
                <div class="card-body p-4">
                    <?php if ($product): ?>
                    <div class="alert alert-info d-flex align-items-center gap-2">
                        <i class="bi bi-info-circle"></i>
                        Requesting quote for: <strong><?= e($product['name']) ?></strong>
                    </div>
                    <?php endif; ?>
                    <form method="POST" action="/api/rfq.php?action=create">
                        <?= csrfField() ?>
                        <input type="hidden" name="product_id" value="<?= $productId ?>">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">RFQ Title *</label>
                                <input type="text" name="title" class="form-control" placeholder="e.g., 500 units of stainless steel bolts" required value="<?= $product ? e('Quote for: ' . $product['name']) : '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Category</label>
                                <select name="category_id" class="form-select">
                                    <option value="">Select category...</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Quantity</label>
                                <input type="number" name="quantity" class="form-control" min="1" placeholder="e.g., 500">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Unit</label>
                                <input type="text" name="unit" class="form-control" placeholder="e.g., pieces, kg">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Target Price (per unit)</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="target_price" class="form-control" step="0.01" min="0" placeholder="0.00">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Destination Country</label>
                                <input type="text" name="destination_country" class="form-control" placeholder="e.g., United States">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Deadline</label>
                                <input type="date" name="deadline" class="form-control" min="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Description / Requirements</label>
                                <textarea name="description" class="form-control" rows="5"
                                    placeholder="Describe your requirements in detail: specifications, quality standards, packaging, certifications needed..."></textarea>
                            </div>
                        </div>
                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-send me-1"></i> Submit RFQ</button>
                            <a href="/pages/rfq/index.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
