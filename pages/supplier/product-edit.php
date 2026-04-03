<?php
/**
 * pages/supplier/product-edit.php — Edit Product
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireRole(['supplier', 'admin', 'super_admin']);

$db = getDB();

$productId = (int)get('id', 0);
if (!$productId) {
    flashMessage('danger', 'Product ID required.');
    redirect('/pages/supplier/products.php');
}

// Get supplier record
$suppStmt = $db->prepare('SELECT id FROM suppliers WHERE user_id = ?');
$suppStmt->execute([$_SESSION['user_id']]);
$supplier = $suppStmt->fetch();

// Load product — verify ownership unless admin
if (isAdmin()) {
    $pStmt = $db->prepare('SELECT p.*, c.name category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ?');
    $pStmt->execute([$productId]);
} else {
    if (!$supplier) {
        flashMessage('warning', 'Supplier account required.');
        redirect('/pages/supplier/dashboard.php');
    }
    $pStmt = $db->prepare('SELECT p.*, c.name category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ? AND p.supplier_id = ?');
    $pStmt->execute([$productId, $supplier['id']]);
}

$product = $pStmt->fetch();
if (!$product) {
    flashMessage('danger', 'Product not found or access denied.');
    redirect('/pages/supplier/products.php');
}

// Load product images
$imgStmt = $db->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order, id');
$imgStmt->execute([$productId]);
$images = $imgStmt->fetchAll();

$categories = $db->query('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();

$created = get('created', '') === '1';

$pageTitle = 'Edit: ' . $product['name'];
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Product</h3>
        <a href="/pages/supplier/products.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Back to Products</a>
    </div>

    <?php if ($created): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> Product created successfully! You can now add more details or images.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div id="formAlert"></div>

    <form id="productForm" method="POST" action="/api/products.php?action=update" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="<?= $product['id'] ?>">

        <div class="row g-4">
            <div class="col-lg-8">
                <!-- Basic Info -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3"><h6 class="fw-bold mb-0">Product Information</h6></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Product Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required value="<?= e($product['name']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Short Description</label>
                            <input type="text" name="short_desc" class="form-control" maxlength="255" value="<?= e($product['short_desc'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="6"><?= e($product['description'] ?? '') ?></textarea>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Category</label>
                                <select name="category_id" class="form-select">
                                    <option value="">— Select Category —</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $product['category_id']==$cat['id']?'selected':'' ?>><?= e($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Status</label>
                                <select name="status" class="form-select">
                                    <?php foreach (['draft','active','inactive'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $product['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                                    <?php endforeach; ?>
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
                                    <input type="number" name="price" class="form-control" min="0" step="0.01" required value="<?= e($product['price']) ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Compare Price</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="compare_price" class="form-control" min="0" step="0.01" value="<?= e($product['compare_price'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Stock Qty</label>
                                <input type="number" name="stock_qty" class="form-control" min="0" value="<?= e($product['stock_qty']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Min Order Qty</label>
                                <input type="number" name="min_order_qty" class="form-control" min="1" value="<?= e($product['min_order_qty']) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Images -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3"><h6 class="fw-bold mb-0">Product Images</h6></div>
                    <div class="card-body">
                        <?php if (!empty($images)): ?>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <?php foreach ($images as $img): ?>
                            <div class="position-relative">
                                <img src="<?= e(APP_URL . '/' . ltrim($img['image_url'], '/')) ?>" class="rounded border" width="80" height="80" style="object-fit:cover" alt="">
                                <?php if ($img['is_primary']): ?><span class="badge bg-primary position-absolute top-0 start-0" style="font-size:0.6rem">Primary</span><?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <label class="form-label">Upload New Image</label>
                        <div class="input-group">
                            <input type="file" name="image" id="imageUpload" class="form-control" accept="image/*">
                            <button type="button" class="btn btn-outline-primary" onclick="uploadImage()">Upload</button>
                        </div>
                        <div class="form-text">JPEG, PNG, or WebP. Max 5MB.</div>
                        <div id="uploadResult" class="mt-2"></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm sticky-top" style="top:80px">
                    <div class="card-body">
                        <button type="submit" class="btn btn-primary w-100 mb-2">
                            <i class="bi bi-check-circle me-1"></i> Update Product
                        </button>
                        <a href="/pages/supplier/products.php" class="btn btn-outline-secondary w-100 mb-3">Cancel</a>
                        <?php if ($product['status'] === 'active'): ?>
                        <a href="/pages/product/detail.php?slug=<?= urlencode($product['slug']) ?>" class="btn btn-outline-info w-100" target="_blank">
                            <i class="bi bi-eye me-1"></i> Preview
                        </a>
                        <?php endif; ?>
                        <hr>
                        <dl class="small mb-0">
                            <dt class="text-muted">Created</dt><dd><?= formatDate($product['created_at']) ?></dd>
                            <dt class="text-muted">Updated</dt><dd><?= formatDate($product['updated_at'] ?? $product['created_at']) ?></dd>
                            <dt class="text-muted">Slug</dt><dd class="text-break"><?= e($product['slug']) ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.getElementById('productForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('[type=submit]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';
    const formData = new FormData(this);
    try {
        const res = await fetch(this.action, { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            document.getElementById('formAlert').innerHTML = '<div class="alert alert-success">Product updated successfully!</div>';
        } else {
            document.getElementById('formAlert').innerHTML = '<div class="alert alert-danger">' + (data.error || 'Update failed.') + '</div>';
        }
    } catch (err) {
        document.getElementById('formAlert').innerHTML = '<div class="alert alert-danger">Network error. Please try again.</div>';
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Update Product';
    window.scrollTo(0, 0);
});

async function uploadImage() {
    const fileInput = document.getElementById('imageUpload');
    if (!fileInput.files.length) { alert('Please select an image first.'); return; }
    const formData = new FormData();
    formData.append('image', fileInput.files[0]);
    formData.append('product_id', <?= json_encode((int)$product['id']) ?>);
    formData.append('_csrf_token', '<?= e(csrfToken()) ?>');
    const res = await fetch('/api/products.php?action=upload_image', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
        document.getElementById('uploadResult').innerHTML = '<div class="alert alert-success p-2 small">Uploaded! <a href="' + data.url + '" target="_blank">View</a></div>';
        setTimeout(() => location.reload(), 1500);
    } else {
        document.getElementById('uploadResult').innerHTML = '<div class="alert alert-danger p-2 small">' + (data.error || 'Upload failed.') + '</div>';
    }
}
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
