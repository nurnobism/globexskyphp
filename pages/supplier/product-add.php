<?php
/**
 * pages/supplier/product-add.php — Add New Product
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireRole(['supplier', 'admin', 'super_admin']);

$db = getDB();

$suppStmt = $db->prepare('SELECT id FROM suppliers WHERE user_id = ?');
$suppStmt->execute([$_SESSION['user_id']]);
$supplier = $suppStmt->fetch();

if (!$supplier && !isAdmin()) {
    flashMessage('warning', 'Supplier account required to add products.');
    redirect('/pages/supplier/dashboard.php');
}

$categories = $db->query('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();

$pageTitle = 'Add New Product';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-plus-circle text-primary me-2"></i>Add New Product</h3>
        <a href="/pages/supplier/products.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Back to Products</a>
    </div>

    <div id="formAlert"></div>

    <form id="productForm" method="POST" action="/api/products.php?action=create" enctype="multipart/form-data">
        <?= csrfField() ?>

        <div class="row g-4">
            <div class="col-lg-8">
                <!-- Basic Info -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3"><h6 class="fw-bold mb-0">Product Information</h6></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Product Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g. Premium Wireless Headphones">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Short Description</label>
                            <input type="text" name="short_desc" class="form-control" maxlength="255" placeholder="Brief summary (shown in listings)">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="6" placeholder="Full product description..."></textarea>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Category</label>
                                <select name="category_id" class="form-select">
                                    <option value="">— Select Category —</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Status</label>
                                <select name="status" class="form-select">
                                    <option value="draft">Draft</option>
                                    <option value="active">Active</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pricing & Inventory -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3"><h6 class="fw-bold mb-0">Pricing &amp; Inventory</h6></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Price (USD) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="price" class="form-control" min="0" step="0.01" required placeholder="0.00">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Compare Price (Sale)</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="compare_price" class="form-control" min="0" step="0.01" placeholder="0.00">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Stock Qty</label>
                                <input type="number" name="stock_qty" class="form-control" min="0" value="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Min Order Qty</label>
                                <input type="number" name="min_order_qty" class="form-control" min="1" value="1">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Images -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3"><h6 class="fw-bold mb-0">Product Images</h6></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Primary Image</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
                            <div class="form-text">JPEG, PNG, or WebP. Max 5MB.</div>
                        </div>
                        <div id="imagePreviewList" class="d-flex flex-wrap gap-2"></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm sticky-top" style="top:80px">
                    <div class="card-body">
                        <button type="submit" class="btn btn-primary w-100 mb-2" name="_redirect" value="edit">
                            <i class="bi bi-check-circle me-1"></i> Save Product
                        </button>
                        <button type="submit" class="btn btn-outline-primary w-100 mb-2" name="_redirect" value="variations">
                            <i class="bi bi-layers me-1"></i> Save &amp; Add Variations
                        </button>
                        <a href="/pages/supplier/products.php" class="btn btn-outline-secondary w-100">Cancel</a>
                        <hr>
                        <small class="text-muted">Products with status <strong>draft</strong> are not visible to buyers until set to <strong>active</strong>.</small>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.getElementById('productForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = e.submitter || this.querySelector('[type=submit]');
    const redirect = btn.value || 'edit';
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';
    const formData = new FormData(this);
    try {
        const res = await fetch(this.action, { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            if (redirect === 'variations') {
                window.location.href = '/pages/supplier/products/variations.php?product_id=' + data.id;
            } else {
                window.location.href = '/pages/supplier/product-edit.php?id=' + data.id + '&created=1';
            }
        } else {
            document.getElementById('formAlert').innerHTML = '<div class="alert alert-danger">' + (data.error || 'Failed to save product.') + '</div>';
            btn.disabled = false;
            btn.innerHTML = btn.value === 'variations'
                ? '<i class="bi bi-layers me-1"></i> Save &amp; Add Variations'
                : '<i class="bi bi-check-circle me-1"></i> Save Product';
        }
    } catch (err) {
        document.getElementById('formAlert').innerHTML = '<div class="alert alert-danger">Network error. Please try again.</div>';
        btn.disabled = false;
        btn.innerHTML = btn.value === 'variations'
            ? '<i class="bi bi-layers me-1"></i> Save &amp; Add Variations'
            : '<i class="bi bi-check-circle me-1"></i> Save Product';
    }
});
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
