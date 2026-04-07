<?php
/**
 * pages/supplier/products/variations.php — Variation Management
 *
 * Allows suppliers to:
 *  1. Define up to 3 variation types (e.g. Color, Size, Material)
 *  2. Generate & review the SKU matrix from those types
 *  3. Inline-edit per-SKU price, stock, weight, image, active toggle
 *  4. View summary stats (total SKUs, stock, price range)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/variations.php';
requireRole(['supplier', 'admin', 'super_admin']);

$db = getDB();

$productId = (int)get('product_id', 0);
if (!$productId) {
    flashMessage('danger', 'Product ID required.');
    redirect('/pages/supplier/products.php');
}

// Get supplier record
$suppStmt = $db->prepare('SELECT id FROM suppliers WHERE user_id = ?');
$suppStmt->execute([$_SESSION['user_id']]);
$supplier = $suppStmt->fetch();

// Verify product ownership
if (isAdmin()) {
    $pStmt = $db->prepare('SELECT id, name, price FROM products WHERE id = ?');
    $pStmt->execute([$productId]);
} else {
    if (!$supplier) {
        flashMessage('warning', 'Supplier account required.');
        redirect('/pages/supplier/dashboard.php');
    }
    $pStmt = $db->prepare('SELECT id, name, price FROM products WHERE id = ? AND supplier_id = ?');
    $pStmt->execute([$productId, $supplier['id']]);
}
$product = $pStmt->fetch();
if (!$product) {
    flashMessage('danger', 'Product not found or access denied.');
    redirect('/pages/supplier/products.php');
}

$supplierId   = isAdmin() ? 0 : (int)$supplier['id'];
$variationTypes = getVariationTypes($productId);
$skuMatrix      = getSkuMatrix($productId);

// Summary stats
$totalSkus  = count($skuMatrix);
$totalStock = array_sum(array_column($skuMatrix, 'stock'));
$prices     = array_column($skuMatrix, 'price');
$minPrice   = $prices ? min($prices) : (float)$product['price'];
$maxPrice   = $prices ? max($prices) : (float)$product['price'];

$pageTitle = 'Manage Variations — ' . $product['name'];
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0"><i class="bi bi-layers text-primary me-2"></i>Product Variations</h4>
            <small class="text-muted"><?= e($product['name']) ?></small>
        </div>
        <div class="d-flex gap-2">
            <a href="/pages/supplier/product-edit.php?id=<?= $productId ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back to Edit
            </a>
            <a href="/pages/supplier/products.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-grid me-1"></i>All Products
            </a>
        </div>
    </div>

    <div id="pageAlert"></div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-primary" id="summarySkuCount"><?= $totalSkus ?></div>
                <div class="text-muted small">Total SKUs</div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-success" id="summaryStock"><?= number_format($totalStock) ?></div>
                <div class="text-muted small">Total Stock</div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-warning" id="summaryPriceRange">
                    <?php if ($totalSkus): ?>
                        $<?= number_format($minPrice, 2) ?><?= $minPrice != $maxPrice ? ' – $' . number_format($maxPrice, 2) : '' ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </div>
                <div class="text-muted small">Price Range</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Section 1: Define Variation Types -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0"><i class="bi bi-tags me-2 text-primary"></i>Variation Types</h6>
                    <span class="badge bg-secondary" id="typeCount"><?= count($variationTypes) ?>/3</span>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Define up to 3 variation dimensions (e.g. Color, Size, Material).
                        Values are comma-separated (e.g. <em>Red, Blue, Black</em>).
                    </p>

                    <!-- Existing variation types -->
                    <div id="existingTypes">
                        <?php foreach ($variationTypes as $vt): ?>
                        <div class="card mb-2 border variation-type-card" data-type-id="<?= $vt['id'] ?>">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <strong class="text-primary type-name-display"><?= e($vt['name']) ?></strong>
                                    <button type="button" class="btn btn-outline-danger btn-sm btn-delete-type"
                                            data-type-id="<?= $vt['id'] ?>"
                                            title="Delete this variation type">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                                <div class="mb-2">
                                    <?php foreach ($vt['options'] as $opt): ?>
                                    <span class="badge bg-light text-dark border me-1"><?= e($opt['value']) ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <div class="d-flex gap-2">
                                    <input type="text" class="form-control form-control-sm edit-type-name"
                                           value="<?= e($vt['name']) ?>" placeholder="Type name">
                                    <input type="text" class="form-control form-control-sm edit-type-values"
                                           value="<?= e(implode(', ', array_column($vt['options'], 'value'))) ?>"
                                           placeholder="Values (comma-separated)">
                                    <button type="button" class="btn btn-primary btn-sm btn-update-type"
                                            data-type-id="<?= $vt['id'] ?>">
                                        <i class="bi bi-check"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Add new variation type form -->
                    <div id="addTypeForm" <?= count($variationTypes) >= 3 ? 'style="display:none"' : '' ?>>
                        <div class="card border-dashed border-primary mb-2">
                            <div class="card-body p-3">
                                <h6 class="fw-semibold mb-2 text-primary"><i class="bi bi-plus-circle me-1"></i>Add Variation Type</h6>
                                <div class="mb-2">
                                    <input type="text" id="newTypeName" class="form-control form-control-sm"
                                           placeholder="Type name (e.g. Color)">
                                </div>
                                <div class="mb-2">
                                    <input type="text" id="newTypeValues" class="form-control form-control-sm"
                                           placeholder="Values: Red, Blue, Black, White">
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted" id="newCombinationPreview"></small>
                                    <button type="button" class="btn btn-primary btn-sm" id="btnAddType">
                                        <i class="bi bi-plus me-1"></i>Add Type
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (count($variationTypes) >= 3): ?>
                    <div class="alert alert-warning py-2 small mb-2" id="maxTypesAlert">
                        <i class="bi bi-info-circle me-1"></i>Maximum of 3 variation types reached.
                    </div>
                    <?php endif; ?>

                    <!-- SKU count preview -->
                    <div class="alert alert-info py-2 small mt-2" id="combinationPreview" style="display:none">
                        <i class="bi bi-calculator me-1"></i>
                        <span id="previewText">This will generate <strong id="previewCount">0</strong> SKUs.</span>
                    </div>

                    <!-- Generate button -->
                    <div class="d-grid mt-3">
                        <button type="button" class="btn btn-success" id="btnGenerateSkus"
                                <?= empty($variationTypes) ? 'disabled' : '' ?>>
                            <i class="bi bi-lightning-fill me-1"></i>Generate SKU Matrix
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: SKU Matrix Table -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0"><i class="bi bi-table me-2 text-primary"></i>SKU Matrix</h6>
                    <button type="button" class="btn btn-primary btn-sm" id="btnSaveAll"
                            <?= empty($skuMatrix) ? 'disabled' : '' ?>>
                        <i class="bi bi-save me-1"></i>Save All
                    </button>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($skuMatrix)): ?>
                    <div class="text-center py-5 text-muted" id="emptySkuMessage">
                        <i class="bi bi-grid-3x3 fs-1 d-block mb-2 opacity-25"></i>
                        <p>No SKUs yet. Define variation types and click <strong>Generate SKU Matrix</strong>.</p>
                    </div>
                    <?php endif; ?>

                    <div class="table-responsive" id="skuTableWrapper" <?= empty($skuMatrix) ? 'style="display:none"' : '' ?>>
                        <table class="table table-sm table-hover align-middle mb-0" id="skuTable">
                            <thead class="table-light">
                                <tr>
                                    <?php if (!empty($variationTypes)): ?>
                                    <?php foreach ($variationTypes as $vt): ?>
                                    <th class="ps-3"><?= e($vt['name']) ?></th>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                    <th>SKU Code</th>
                                    <th>Price ($)</th>
                                    <th>Stock</th>
                                    <th>Weight</th>
                                    <th>Active</th>
                                </tr>
                            </thead>
                            <tbody id="skuTableBody">
                                <?php foreach ($skuMatrix as $sku): ?>
                                <tr data-sku-id="<?= $sku['id'] ?>">
                                    <?php foreach ($sku['variation_options'] as $opt): ?>
                                    <td class="ps-3"><span class="badge bg-light text-dark border"><?= e($opt['option_value']) ?></span></td>
                                    <?php endforeach; ?>
                                    <td>
                                        <input type="text" class="form-control form-control-sm sku-field"
                                               name="sku_code" value="<?= e($sku['sku_code'] ?? '') ?>"
                                               style="min-width:100px">
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm sku-field"
                                               name="price" value="<?= e($sku['price']) ?>"
                                               min="0" step="0.01" style="min-width:70px">
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm sku-field"
                                               name="stock" value="<?= e($sku['stock']) ?>"
                                               min="0" step="1" style="min-width:60px">
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm sku-field"
                                               name="weight_override" value="<?= e($sku['weight_override'] ?? '') ?>"
                                               min="0" step="0.01" placeholder="—" style="min-width:60px">
                                    </td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input type="checkbox" class="form-check-input sku-field"
                                                   name="is_active" <?= $sku['is_active'] ? 'checked' : '' ?>>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const PRODUCT_ID = <?= $productId ?>;
const CSRF_TOKEN = '<?= csrfToken() ?>';
const API_BASE   = '/api/products.php';

// ── Utility ────────────────────────────────────────────────────────────────
function showAlert(msg, type = 'success') {
    const el = document.getElementById('pageAlert');
    el.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show py-2" role="alert">
        ${msg}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

async function apiPost(action, data) {
    const form = new FormData();
    form.append('_csrf_token', CSRF_TOKEN);
    for (const [k, v] of Object.entries(data)) {
        if (Array.isArray(v)) {
            v.forEach(item => form.append(k + '[]', item));
        } else {
            form.append(k, v ?? '');
        }
    }
    const res = await fetch(`${API_BASE}?action=${action}`, { method: 'POST', body: form });
    return res.json();
}

// ── Combination preview ─────────────────────────────────────────────────────
function updateCombinationPreview() {
    const cards = document.querySelectorAll('.variation-type-card');
    let count = 1;
    cards.forEach(card => {
        const valuesInput = card.querySelector('.edit-type-values');
        if (!valuesInput) return;
        const vals = valuesInput.value.split(',').map(v => v.trim()).filter(Boolean);
        count *= vals.length || 1;
    });

    // Also factor in new type form if it has values
    const newVals = document.getElementById('newTypeValues').value.split(',').map(v => v.trim()).filter(Boolean);
    if (newVals.length) count *= newVals.length;

    if (cards.length > 0 || newVals.length) {
        document.getElementById('combinationPreview').style.display = '';
        document.getElementById('previewCount').textContent = count;
    } else {
        document.getElementById('combinationPreview').style.display = 'none';
    }
}

document.getElementById('newTypeValues').addEventListener('input', updateCombinationPreview);
document.querySelectorAll('.edit-type-values').forEach(el => el.addEventListener('input', updateCombinationPreview));
updateCombinationPreview();

// ── Add variation type ──────────────────────────────────────────────────────
document.getElementById('btnAddType').addEventListener('click', async () => {
    const name   = document.getElementById('newTypeName').value.trim();
    const values = document.getElementById('newTypeValues').value.split(',').map(v => v.trim()).filter(Boolean);
    if (!name)         { showAlert('Type name is required.', 'warning'); return; }
    if (!values.length) { showAlert('At least one value is required.', 'warning'); return; }

    const res = await apiPost('add_variation_type', { product_id: PRODUCT_ID, type_name: name, values: values });
    if (res.success) {
        showAlert('Variation type added! Reload to see updated form.', 'success');
        setTimeout(() => location.reload(), 800);
    } else {
        showAlert(res.error || 'Failed to add variation type.', 'danger');
    }
});

// ── Update variation type ───────────────────────────────────────────────────
document.querySelectorAll('.btn-update-type').forEach(btn => {
    btn.addEventListener('click', async () => {
        const card     = btn.closest('.variation-type-card');
        const typeId   = btn.dataset.typeId;
        const name     = card.querySelector('.edit-type-name').value.trim();
        const rawVals  = card.querySelector('.edit-type-values').value;
        const values   = rawVals.split(',').map(v => v.trim()).filter(Boolean);

        if (!name)         { showAlert('Type name required.', 'warning'); return; }
        if (!values.length) { showAlert('At least one value required.', 'warning'); return; }

        const res = await apiPost('update_variation_type', { type_id: typeId, type_name: name, values: values });
        if (res.success) {
            showAlert('Variation type updated.', 'success');
            card.querySelector('.type-name-display').textContent = name;
        } else {
            showAlert(res.error || 'Update failed.', 'danger');
        }
    });
});

// ── Delete variation type ───────────────────────────────────────────────────
document.querySelectorAll('.btn-delete-type').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('Delete this variation type? All generated SKUs for this product will also be deleted.')) return;
        const typeId = btn.dataset.typeId;
        const res    = await apiPost('delete_variation_type', { type_id: typeId });
        if (res.success) {
            showAlert('Variation type deleted.', 'success');
            setTimeout(() => location.reload(), 600);
        } else {
            showAlert(res.error || 'Delete failed.', 'danger');
        }
    });
});

// ── Generate SKU matrix ─────────────────────────────────────────────────────
document.getElementById('btnGenerateSkus').addEventListener('click', async () => {
    const existing = document.querySelectorAll('#skuTableBody tr').length;
    if (existing > 0 && !confirm('Regenerating SKUs will delete all existing custom SKU data. Continue?')) return;

    const btn = document.getElementById('btnGenerateSkus');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generating…';

    const res = await apiPost('generate_skus', { product_id: PRODUCT_ID });
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-lightning-fill me-1"></i>Generate SKU Matrix';

    if (res.success) {
        showAlert(`${res.count} SKUs generated successfully!`, 'success');
        setTimeout(() => location.reload(), 800);
    } else {
        showAlert(res.error || 'Generation failed.', 'danger');
    }
});

// ── Save All (bulk update) ──────────────────────────────────────────────────
document.getElementById('btnSaveAll').addEventListener('click', async () => {
    const rows  = document.querySelectorAll('#skuTableBody tr');
    const skus  = [];
    rows.forEach(row => {
        const skuData = { id: row.dataset.skuId };
        row.querySelectorAll('.sku-field').forEach(field => {
            if (field.type === 'checkbox') {
                skuData[field.name] = field.checked ? 1 : 0;
            } else {
                skuData[field.name] = field.value;
            }
        });
        skus.push(skuData);
    });

    const btn = document.getElementById('btnSaveAll');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

    const form = new FormData();
    form.append('_csrf_token', CSRF_TOKEN);
    form.append('product_id', PRODUCT_ID);
    form.append('skus', JSON.stringify(skus));

    const res = await fetch(`${API_BASE}?action=bulk_update_skus`, { method: 'POST', body: form });
    const data = await res.json();

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-save me-1"></i>Save All';

    if (data.success) {
        showAlert(`${data.updated} SKUs saved successfully!`, 'success');
    } else {
        showAlert(data.error || 'Save failed.', 'danger');
    }
});
</script>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
