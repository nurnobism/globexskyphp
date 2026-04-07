<?php
/**
 * pages/supplier/products/create.php — Multi-Step Product Upload Form
 *
 * Supports all 6 form steps per DOCS/07-product-upload.md:
 *   1. Basic Information  2. Media  3. Pricing
 *   4. Variations         5. Shipping  6. Settings
 *
 * Enforces plan limits (DOCS/05-free-vs-premium.md + includes/plan_limits.php)
 * Respects product_listing feature toggle (DOCS/12-feature-toggle.md)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
requireRole(['supplier', 'admin', 'super_admin']);
require_once __DIR__ . '/../../../includes/plan_limits.php';
require_once __DIR__ . '/../../../includes/feature_toggles.php';

$db = getDB();

// ── Feature toggle check ────────────────────────────────────
$listingEnabled = isFeatureEnabled('product_listing');

// ── Supplier lookup ─────────────────────────────────────────
$suppStmt = $db->prepare('SELECT id FROM suppliers WHERE user_id = ?');
$suppStmt->execute([$_SESSION['user_id']]);
$supplier = $suppStmt->fetch();

if (!$supplier && !isAdmin()) {
    flashMessage('warning', 'Supplier account required to add products.');
    redirect('/pages/supplier/dashboard.php');
}

$supplierId = (int)($supplier['id'] ?? 0);

// ── Plan limits ─────────────────────────────────────────────
$limits        = getRemainingLimits($supplierId);
$canAdd        = canAddProduct($supplierId);
$maxImages     = (int)$limits['images_per_product'];
$planName      = $limits['plan'];
$planSlug      = $limits['plan_slug'];
$canDropship   = $limits['dropshipping'];
$productLimit  = $limits['products']['limit'];   // int or 'Unlimited'
$productUsed   = $limits['products']['used'];

// Pro+ can upload video; enterprise gets size chart + 360
$canVideo      = in_array($planSlug, ['pro', 'enterprise']);
$canSizeChart  = in_array($planSlug, ['pro', 'enterprise']);
$can360        = ($planSlug === 'enterprise');

// ── Categories ───────────────────────────────────────────────
$categories = $db->query(
    'SELECT id, name, parent_id FROM categories WHERE is_active = 1 ORDER BY sort_order, name'
)->fetchAll();

$pageTitle = 'Add New Product';
include __DIR__ . '/../../../includes/header.php';
?>

<?php if (!$listingEnabled): ?>
<!-- Feature disabled notice -->
<div class="container py-5">
    <div class="alert alert-warning d-flex align-items-center gap-3 shadow-sm" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-3 text-warning"></i>
        <div>
            <h5 class="mb-1 fw-bold">Product Listing Temporarily Disabled</h5>
            <p class="mb-0">The product listing feature is currently turned off by platform administrators. Please check back later or <a href="/pages/supplier/dashboard.php">return to your dashboard</a>.</p>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
<?php return; ?>
<?php endif; ?>

<?php if (!$canAdd): ?>
<!-- Plan limit notice -->
<div class="container py-5">
    <div class="alert alert-danger d-flex align-items-center gap-3 shadow-sm" role="alert">
        <i class="bi bi-lock-fill fs-3 text-danger"></i>
        <div>
            <h5 class="mb-1 fw-bold">Product Limit Reached (<?= e($planName) ?> Plan)</h5>
            <p class="mb-0">
                You have used <strong><?= (int)$productUsed ?></strong> of your
                <strong><?= is_numeric($productLimit) ? (int)$productLimit : e($productLimit) ?></strong> product slots.
                <a href="/pages/supplier/plan-upgrade.php" class="alert-link">Upgrade your plan</a> to add more products.
            </p>
        </div>
    </div>
    <a href="/pages/supplier/products.php" class="btn btn-outline-secondary mt-3">
        <i class="bi bi-arrow-left me-1"></i> Back to Products
    </a>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
<?php return; ?>
<?php endif; ?>

<div class="container py-4">
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-0"><i class="bi bi-plus-circle text-primary me-2"></i>Add New Product</h3>
            <small class="text-muted">
                <?= e($planName) ?> Plan &mdash;
                <?= is_numeric($productLimit)
                    ? ((int)$productUsed . ' / ' . (int)$productLimit . ' products used')
                    : ((int)$productUsed . ' products used (Unlimited)') ?>
                &nbsp;&bull;&nbsp; Up to <strong><?= (int)$maxImages ?></strong> images per product
            </small>
        </div>
        <a href="/pages/supplier/products.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Products
        </a>
    </div>

    <!-- Global alert -->
    <div id="formAlert"></div>

    <!-- Step progress bar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-center step-nav flex-wrap gap-2">
                <?php
                $steps = [
                    1 => ['icon' => 'bi-info-circle',   'label' => 'Basic Info'],
                    2 => ['icon' => 'bi-images',         'label' => 'Media'],
                    3 => ['icon' => 'bi-tag',            'label' => 'Pricing'],
                    4 => ['icon' => 'bi-grid-3x3',       'label' => 'Variations'],
                    5 => ['icon' => 'bi-truck',          'label' => 'Shipping'],
                    6 => ['icon' => 'bi-gear',           'label' => 'Settings'],
                ];
                foreach ($steps as $n => $s): ?>
                <button type="button" class="btn btn-sm step-btn <?= $n === 1 ? 'btn-primary' : 'btn-outline-secondary' ?>"
                        data-step="<?= $n ?>" onclick="goToStep(<?= $n ?>)">
                    <i class="<?= $s['icon'] ?> me-1"></i><?= $s['label'] ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <form id="productForm" novalidate>
        <?= csrfField() ?>
        <input type="hidden" id="productId" name="product_id" value="">

        <div class="row g-4">
            <div class="col-lg-9">

                <!-- ═══════════════════════════════════════════
                     STEP 1 — BASIC INFORMATION
                ═══════════════════════════════════════════════ -->
                <div id="step-1" class="step-panel">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h6 class="fw-bold mb-0"><i class="bi bi-info-circle text-primary me-2"></i>Step 1 — Basic Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Product Title <span class="text-danger">*</span></label>
                                <input type="text" id="productName" name="name" class="form-control" required
                                       minlength="10" maxlength="150"
                                       placeholder="e.g. Premium Wireless Bluetooth Headphones">
                                <div class="form-text">10–150 characters</div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Category</label>
                                    <select name="category_id" id="categorySelect" class="form-select">
                                        <option value="">— Select Category —</option>
                                        <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"
                                                data-parent="<?= (int)$cat['parent_id'] ?>">
                                            <?= e($cat['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Brand</label>
                                    <input type="text" name="brand" class="form-control" placeholder="e.g. Sony, Generic">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Short Description <span class="text-danger">*</span></label>
                                <input type="text" name="short_desc" class="form-control" required
                                       minlength="50" maxlength="300"
                                       placeholder="Brief summary shown in listing cards (50–300 characters)">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Full Description</label>
                                <textarea name="description" id="descEditor" class="form-control" rows="8"
                                          placeholder="Detailed product description (supports HTML)..."></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Key Features</label>
                                <div id="featuresContainer">
                                    <div class="input-group mb-2">
                                        <input type="text" name="features[]" class="form-control" placeholder="Feature bullet point">
                                        <button type="button" class="btn btn-outline-danger" onclick="removeFeature(this)"><i class="bi bi-x"></i></button>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addFeature()" id="addFeatureBtn">
                                    <i class="bi bi-plus me-1"></i>Add Feature (up to 10)
                                </button>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Tags</label>
                                <input type="text" name="tags_input" id="tagsInput" class="form-control"
                                       placeholder="Comma-separated, up to 10 tags (e.g. wireless, headphones, bluetooth)">
                                <div class="form-text">Up to 10 tags for search</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Product Condition</label>
                                <select name="condition" class="form-select">
                                    <option value="new">New</option>
                                    <option value="used">Used</option>
                                    <option value="refurbished">Refurbished</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════
                     STEP 2 — MEDIA
                ═══════════════════════════════════════════════ -->
                <div id="step-2" class="step-panel d-none">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h6 class="fw-bold mb-0"><i class="bi bi-images text-primary me-2"></i>Step 2 — Media</h6>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info small">
                                <i class="bi bi-info-circle me-1"></i>
                                <strong><?= e($planName) ?> Plan:</strong>
                                Up to <strong><?= (int)$maxImages ?></strong> gallery images.
                                <?= $canVideo ? 'Video upload <strong>enabled</strong>.' : 'Video upload requires Pro or Enterprise plan.' ?>
                            </div>

                            <!-- Main image -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Main Image <span class="text-danger">*</span></label>
                                <input type="file" id="mainImageInput" name="main_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                                <div class="form-text">Min 800×800 px, JPG/PNG/WebP, max 5 MB</div>
                                <div id="mainImagePreview" class="mt-2"></div>
                            </div>

                            <!-- Gallery images -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    Gallery Images
                                    <span class="badge bg-secondary ms-1"><?= (int)$maxImages ?> max</span>
                                </label>
                                <input type="file" id="galleryInput" name="gallery_images[]"
                                       class="form-control" accept="image/jpeg,image/png,image/webp"
                                       multiple>
                                <div class="form-text">Min 600×600 px, JPG/PNG/WebP, max 5 MB each. Select up to <?= (int)$maxImages ?> images.</div>
                                <div id="galleryPreview" class="d-flex flex-wrap gap-2 mt-2"></div>
                            </div>

                            <!-- Video — Pro+ only -->
                            <?php if ($canVideo): ?>
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Product Video</label>
                                <input type="file" name="product_video" class="form-control" accept="video/mp4">
                                <div class="form-text">MP4, max 2 minutes, max 100 MB</div>
                            </div>
                            <?php else: ?>
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-muted">Product Video</label>
                                <div class="alert alert-secondary small py-2 mb-0">
                                    <i class="bi bi-lock me-1"></i>Video upload is available on Pro and Enterprise plans.
                                    <a href="/pages/supplier/plan-upgrade.php">Upgrade</a>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Size chart — Pro+ only -->
                            <?php if ($canSizeChart): ?>
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Size Chart</label>
                                <input type="file" name="size_chart" class="form-control" accept="image/jpeg,image/png,application/pdf">
                                <div class="form-text">JPG/PNG or PDF</div>
                            </div>
                            <?php endif; ?>

                            <!-- 360° images — Enterprise only -->
                            <?php if ($can360): ?>
                            <div class="mb-4">
                                <label class="form-label fw-semibold">360° Images</label>
                                <input type="file" name="images_360[]" class="form-control"
                                       accept="image/jpeg,image/png" multiple>
                                <div class="form-text">Series of images for 3D spin viewer</div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════
                     STEP 3 — PRICING
                ═══════════════════════════════════════════════ -->
                <div id="step-3" class="step-panel d-none">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h6 class="fw-bold mb-0"><i class="bi bi-tag text-primary me-2"></i>Step 3 — Pricing</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Base Price (USD) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" name="price" id="basePrice" class="form-control"
                                               min="0.01" step="0.01" required placeholder="0.00">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Sale Price (USD)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" name="compare_price" id="salePrice" class="form-control"
                                               min="0" step="0.01" placeholder="0.00">
                                    </div>
                                    <div class="form-text">Must be less than base price</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Currency</label>
                                    <input type="text" class="form-control" value="USD" readonly>
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Stock Quantity</label>
                                    <input type="number" name="stock_qty" class="form-control" min="0" value="0">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Min Order Qty (MOQ)</label>
                                    <input type="number" name="min_order_qty" class="form-control" min="1" value="1">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Max Order Qty</label>
                                    <input type="number" name="max_order_qty" class="form-control" min="1" placeholder="No limit">
                                </div>
                            </div>

                            <!-- Tiered pricing -->
                            <h6 class="fw-semibold mb-2">Tiered Pricing (optional)</h6>
                            <div class="form-text mb-2">Define quantity-based price breaks. Up to 10 tiers.</div>
                            <div id="tiersContainer">
                                <!-- tiers added by JS -->
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addTier()" id="addTierBtn">
                                <i class="bi bi-plus me-1"></i>Add Tier
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════
                     STEP 4 — VARIATIONS
                ═══════════════════════════════════════════════ -->
                <div id="step-4" class="step-panel d-none">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h6 class="fw-bold mb-0"><i class="bi bi-grid-3x3 text-primary me-2"></i>Step 4 — Variations</h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small">Define up to 3 variation types (e.g. Color, Size, Material). The system will auto-generate SKU combinations.</p>

                            <div id="variationTypesContainer"></div>

                            <button type="button" class="btn btn-outline-secondary btn-sm mb-4" onclick="addVariationType()" id="addVarTypeBtn">
                                <i class="bi bi-plus me-1"></i>Add Variation Type
                            </button>

                            <!-- SKU matrix -->
                            <div id="skuMatrixSection" class="d-none">
                                <h6 class="fw-semibold mb-2">Generated SKU Matrix</h6>
                                <div class="form-text mb-2">Customize price, stock, and SKU code per variant.</div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered" id="skuMatrixTable">
                                        <thead class="table-light" id="skuMatrixHead"></thead>
                                        <tbody id="skuMatrixBody"></tbody>
                                    </table>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="generateSkuMatrix()">
                                    <i class="bi bi-arrow-repeat me-1"></i>Regenerate Matrix
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════
                     STEP 5 — SHIPPING
                ═══════════════════════════════════════════════ -->
                <div id="step-5" class="step-panel d-none">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h6 class="fw-bold mb-0"><i class="bi bi-truck text-primary me-2"></i>Step 5 — Shipping</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3 mb-3">
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Weight (kg)</label>
                                    <input type="number" name="weight" class="form-control" min="0" step="0.001" placeholder="0.000">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Length (cm)</label>
                                    <input type="number" name="dim_length" class="form-control" min="0" step="0.1" placeholder="0.0">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Width (cm)</label>
                                    <input type="number" name="dim_width" class="form-control" min="0" step="0.1" placeholder="0.0">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Height (cm)</label>
                                    <input type="number" name="dim_height" class="form-control" min="0" step="0.1" placeholder="0.0">
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Shipping Category</label>
                                    <select name="shipping_category" class="form-select">
                                        <option value="standard">Standard</option>
                                        <option value="fragile">Fragile</option>
                                        <option value="oversized">Oversized</option>
                                        <option value="dangerous">Dangerous Goods</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Processing Time (days)</label>
                                    <input type="number" name="processing_days" class="form-control" min="1" max="30" value="3">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Origin Country</label>
                                    <input type="text" name="origin_country" class="form-control" placeholder="e.g. China, USA">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════
                     STEP 6 — SETTINGS
                ═══════════════════════════════════════════════ -->
                <div id="step-6" class="step-panel d-none">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h6 class="fw-bold mb-0"><i class="bi bi-gear text-primary me-2"></i>Step 6 — Settings</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="draft">Draft (not visible to buyers)</option>
                                        <option value="active">Active (published)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Return Policy</label>
                                    <select name="return_policy" class="form-select">
                                        <option value="no_returns">No Returns</option>
                                        <option value="7_days">7-Day Returns</option>
                                        <option value="30_days">30-Day Returns</option>
                                        <option value="custom">Custom (describe below)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Custom Return Policy</label>
                                <textarea name="return_policy_text" class="form-control" rows="3"
                                          placeholder="Describe your return policy..."></textarea>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="dropshipping_enabled" id="dropshippingToggle"
                                               <?= !$canDropship ? 'disabled' : '' ?>>
                                        <label class="form-check-label" for="dropshippingToggle">
                                            Enable Dropshipping
                                            <?php if (!$canDropship): ?>
                                            <span class="badge bg-secondary ms-1">Pro+</span>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="wholesale_enabled" id="wholesaleToggle">
                                        <label class="form-check-label" for="wholesaleToggle">Enable Wholesale</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="pre_order" id="preOrderToggle">
                                        <label class="form-check-label" for="preOrderToggle">Pre-Order</label>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="is_nsfw" id="nsfwToggle">
                                        <label class="form-check-label" for="nsfwToggle">
                                            Age-Restricted / NSFW
                                            <small class="text-muted d-block">Triggers admin review</small>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="search_index" id="searchIndexToggle" checked>
                                        <label class="form-check-label" for="searchIndexToggle">Include in Search Index</label>
                                    </div>
                                </div>
                            </div>

                            <div id="preOrderDateWrap" class="mb-3 d-none">
                                <label class="form-label fw-semibold">Estimated Ship Date</label>
                                <input type="date" name="pre_order_date" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /col-lg-9 -->

            <!-- ── Sidebar actions ─────────────────────── -->
            <div class="col-lg-3">
                <div class="card border-0 shadow-sm sticky-top" style="top:80px">
                    <div class="card-body">
                        <div id="stepIndicator" class="text-center mb-3">
                            <span class="badge bg-primary fs-6" id="stepBadge">Step 1 of 6</span>
                        </div>

                        <button type="button" class="btn btn-primary w-100 mb-2" id="btnNext" onclick="nextStep()">
                            Next <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary w-100 mb-2 d-none" id="btnPrev" onclick="prevStep()">
                            <i class="bi bi-arrow-left me-1"></i> Previous
                        </button>
                        <button type="button" class="btn btn-success w-100 d-none" id="btnSubmit" onclick="submitProduct()">
                            <i class="bi bi-check-circle me-1"></i> Submit Product
                        </button>
                        <button type="button" class="btn btn-outline-secondary w-100 mt-2" onclick="saveDraft()">
                            <i class="bi bi-save me-1"></i> Save as Draft
                        </button>
                        <a href="/pages/supplier/products.php" class="btn btn-link w-100 text-muted mt-1">Cancel</a>

                        <hr>
                        <div class="small text-muted">
                            <i class="bi bi-shield-check text-success me-1"></i>
                            <strong>Plan:</strong> <?= e($planName) ?><br>
                            <strong>Images:</strong> Up to <?= (int)$maxImages ?>/product<br>
                            <strong>Products:</strong>
                            <?= is_numeric($productLimit)
                                ? ((int)$productUsed . '/' . (int)$productLimit . ' used')
                                : ((int)$productUsed . ' used (Unlimited)') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- /row -->
    </form>
</div>

<script>
/* ── State ──────────────────────────────────────────── */
const TOTAL_STEPS   = 6;
const MAX_IMAGES    = <?= (int)$maxImages ?>;
const MAX_FEATURES  = 10;
const MAX_TAGS      = 10;
const MAX_TIERS     = 10;
const MAX_VAR_TYPES = 3;

let currentStep     = 1;
let tierCount       = 0;
let varTypeCount    = 0;
let savedProductId  = null;

/* ── Step navigation ────────────────────────────────── */
function goToStep(n) {
    document.querySelectorAll('.step-panel').forEach(p => p.classList.add('d-none'));
    document.getElementById('step-' + n).classList.remove('d-none');
    document.querySelectorAll('.step-btn').forEach(b => {
        b.classList.toggle('btn-primary', parseInt(b.dataset.step) === n);
        b.classList.toggle('btn-outline-secondary', parseInt(b.dataset.step) !== n);
    });
    currentStep = n;
    document.getElementById('stepBadge').textContent = 'Step ' + n + ' of ' + TOTAL_STEPS;
    document.getElementById('btnPrev').classList.toggle('d-none', n === 1);
    document.getElementById('btnNext').classList.toggle('d-none', n === TOTAL_STEPS);
    document.getElementById('btnSubmit').classList.toggle('d-none', n !== TOTAL_STEPS);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function nextStep() {
    if (!validateStep(currentStep)) return;
    if (currentStep < TOTAL_STEPS) goToStep(currentStep + 1);
}

function prevStep() {
    if (currentStep > 1) goToStep(currentStep - 1);
}

/* ── Step validation ────────────────────────────────── */
function validateStep(step) {
    const alert = document.getElementById('formAlert');
    if (step === 1) {
        const name = document.getElementById('productName').value.trim();
        if (name.length < 10 || name.length > 150) {
            showAlert('Product title must be 10–150 characters.', 'danger');
            return false;
        }
        const shortDesc = document.querySelector('[name="short_desc"]').value.trim();
        if (shortDesc.length < 50 || shortDesc.length > 300) {
            showAlert('Short description must be 50–300 characters.', 'danger');
            return false;
        }
    }
    if (step === 3) {
        const price = parseFloat(document.getElementById('basePrice').value);
        if (isNaN(price) || price < 0.01) {
            showAlert('Base price must be at least $0.01.', 'danger');
            return false;
        }
        const sale = parseFloat(document.getElementById('salePrice').value || 0);
        if (sale > 0 && sale >= price) {
            showAlert('Sale price must be less than base price.', 'danger');
            return false;
        }
    }
    alert.innerHTML = '';
    return true;
}

/* ── Alert helper ───────────────────────────────────── */
function showAlert(msg, type = 'danger') {
    document.getElementById('formAlert').innerHTML =
        '<div class="alert alert-' + type + ' alert-dismissible"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>' + msg + '</div>';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* ── Features ───────────────────────────────────────── */
function addFeature() {
    const c = document.getElementById('featuresContainer');
    if (c.querySelectorAll('input').length >= MAX_FEATURES) {
        showAlert('Maximum ' + MAX_FEATURES + ' features allowed.', 'warning');
        return;
    }
    const d = document.createElement('div');
    d.className = 'input-group mb-2';
    d.innerHTML = '<input type="text" name="features[]" class="form-control" placeholder="Feature bullet point">' +
                  '<button type="button" class="btn btn-outline-danger" onclick="removeFeature(this)"><i class="bi bi-x"></i></button>';
    c.appendChild(d);
}
function removeFeature(btn) { btn.closest('.input-group').remove(); }

/* ── Tiered pricing ─────────────────────────────────── */
function addTier() {
    if (tierCount >= MAX_TIERS) { showAlert('Maximum ' + MAX_TIERS + ' pricing tiers.', 'warning'); return; }
    tierCount++;
    const c = document.getElementById('tiersContainer');
    const d = document.createElement('div');
    d.className = 'row g-2 mb-2 tier-row align-items-center';
    d.dataset.tier = tierCount;
    d.innerHTML = `<div class="col-3"><input type="number" name="tier_min_qty[]" class="form-control form-control-sm" placeholder="Min Qty" min="1" required></div>
                   <div class="col-3"><input type="number" name="tier_max_qty[]" class="form-control form-control-sm" placeholder="Max Qty (blank=∞)" min="1"></div>
                   <div class="col-3"><div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="number" name="tier_price[]" class="form-control" placeholder="Price" min="0.01" step="0.01" required></div></div>
                   <div class="col-3"><button type="button" class="btn btn-outline-danger btn-sm" onclick="removeTier(this)"><i class="bi bi-x"></i> Remove</button></div>`;
    c.appendChild(d);
    if (tierCount >= MAX_TIERS) document.getElementById('addTierBtn').disabled = true;
}
function removeTier(btn) {
    btn.closest('.tier-row').remove();
    tierCount--;
    document.getElementById('addTierBtn').disabled = false;
}

/* ── Variation types ────────────────────────────────── */
function addVariationType() {
    if (varTypeCount >= MAX_VAR_TYPES) { showAlert('Maximum ' + MAX_VAR_TYPES + ' variation types.', 'warning'); return; }
    varTypeCount++;
    const idx = varTypeCount;
    const c = document.getElementById('variationTypesContainer');
    const d = document.createElement('div');
    d.className = 'card border mb-3 var-type-card';
    d.dataset.varIdx = idx;
    d.innerHTML = `<div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <label class="form-label fw-semibold mb-0">Variation Type ${idx}</label>
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeVarType(this)"><i class="bi bi-x"></i> Remove</button>
        </div>
        <input type="text" name="var_type_name[]" class="form-control mb-2" placeholder="Type name (e.g. Color, Size)" oninput="updateSkuMatrix()">
        <div class="var-values-container">
            <div class="input-group mb-2">
                <input type="text" name="var_values_${idx}[]" class="form-control form-control-sm" placeholder="Value (e.g. Red)" oninput="updateSkuMatrix()">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeVarValue(this)"><i class="bi bi-x"></i></button>
            </div>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addVarValue(this, ${idx})">
            <i class="bi bi-plus me-1"></i>Add Value
        </button>
    </div>`;
    c.appendChild(d);
    if (varTypeCount >= MAX_VAR_TYPES) document.getElementById('addVarTypeBtn').disabled = true;
    updateSkuMatrix();
}
function removeVarType(btn) {
    btn.closest('.var-type-card').remove();
    varTypeCount--;
    document.getElementById('addVarTypeBtn').disabled = false;
    updateSkuMatrix();
}
function addVarValue(btn, idx) {
    const c = btn.previousElementSibling;
    const d = document.createElement('div');
    d.className = 'input-group mb-2';
    d.innerHTML = `<input type="text" name="var_values_${idx}[]" class="form-control form-control-sm" placeholder="Value" oninput="updateSkuMatrix()">
                   <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeVarValue(this)"><i class="bi bi-x"></i></button>`;
    c.appendChild(d);
}
function removeVarValue(btn) {
    btn.closest('.input-group').remove();
    updateSkuMatrix();
}

/* ── SKU matrix generator ───────────────────────────── */
function getVariationData() {
    const cards = document.querySelectorAll('.var-type-card');
    const types = [];
    cards.forEach(card => {
        const name = card.querySelector('[name="var_type_name[]"]').value.trim();
        const values = [...card.querySelectorAll('[name^="var_values_"]')]
            .map(i => i.value.trim()).filter(v => v.length > 0);
        if (name && values.length) types.push({ name, values });
    });
    return types;
}

function cartesian(arrays) {
    return arrays.reduce((acc, arr) => {
        const res = [];
        acc.forEach(prev => arr.forEach(v => res.push([...prev, v])));
        return res;
    }, [[]]);
}

function updateSkuMatrix() {
    const types = getVariationData();
    const section = document.getElementById('skuMatrixSection');
    if (!types.length) { section.classList.add('d-none'); return; }
    section.classList.remove('d-none');
    generateSkuMatrix(types);
}

function generateSkuMatrix(types) {
    types = types || getVariationData();
    if (!types.length) return;

    const combinations = cartesian(types.map(t => t.values));
    const head = document.getElementById('skuMatrixHead');
    const body = document.getElementById('skuMatrixBody');

    // Build header
    let hRow = '<tr>';
    types.forEach(t => { hRow += `<th>${escHtml(t.name)}</th>`; });
    hRow += '<th>SKU Code</th><th>Price ($)</th><th>Stock</th></tr>';
    head.innerHTML = hRow;

    // Build rows
    body.innerHTML = '';
    combinations.forEach((combo, i) => {
        const skuSuffix = combo.map(v => v.replace(/[^a-zA-Z0-9]/g, '').toUpperCase().substring(0, 5)).join('-');
        const tr = document.createElement('tr');
        let cells = combo.map(v => `<td>${escHtml(v)}</td>`).join('');
        cells += `<td><input type="text" name="sku_codes[]" class="form-control form-control-sm" value="SKU-${skuSuffix}" style="min-width:100px"></td>`;
        cells += `<td><input type="number" name="sku_prices[]" class="form-control form-control-sm" min="0" step="0.01" placeholder="Base" style="min-width:80px"></td>`;
        cells += `<td><input type="number" name="sku_stocks[]" class="form-control form-control-sm" min="0" value="0" style="min-width:70px"></td>`;
        // store combo as hidden
        cells += `<td class="d-none"><input type="hidden" name="sku_attributes[]" value='${JSON.stringify(Object.fromEntries(types.map((t,j) => [t.name, combo[j]])))}'></td>`;
        tr.innerHTML = cells;
        body.appendChild(tr);
    });
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ── Image preview ──────────────────────────────────── */
document.getElementById('mainImageInput').addEventListener('change', function() {
    const preview = document.getElementById('mainImagePreview');
    preview.innerHTML = '';
    if (this.files[0]) {
        const img = document.createElement('img');
        img.src = URL.createObjectURL(this.files[0]);
        img.className = 'img-thumbnail';
        img.style.maxHeight = '120px';
        preview.appendChild(img);
    }
});

document.getElementById('galleryInput').addEventListener('change', function() {
    const preview = document.getElementById('galleryPreview');
    preview.innerHTML = '';
    const files = [...this.files].slice(0, MAX_IMAGES);
    if (this.files.length > MAX_IMAGES) {
        showAlert('Your plan allows a maximum of ' + MAX_IMAGES + ' gallery images. Only the first ' + MAX_IMAGES + ' will be uploaded.', 'warning');
    }
    files.forEach(file => {
        const img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.className = 'img-thumbnail';
        img.style.height = '80px';
        img.style.width = '80px';
        img.style.objectFit = 'cover';
        preview.appendChild(img);
    });
});

/* ── Pre-order date toggle ──────────────────────────── */
document.getElementById('preOrderToggle').addEventListener('change', function() {
    document.getElementById('preOrderDateWrap').classList.toggle('d-none', !this.checked);
});

/* ── Collect form data ──────────────────────────────── */
function collectFormData(statusOverride) {
    const form = document.getElementById('productForm');
    const fd   = new FormData(form);

    // Override status if draft save
    if (statusOverride) fd.set('status', statusOverride);

    // Tags — limit to MAX_TAGS
    const rawTags = document.getElementById('tagsInput').value;
    const tags = rawTags.split(',').map(t => t.trim()).filter(t => t).slice(0, MAX_TAGS);
    fd.set('tags', JSON.stringify(tags));

    // Tiered pricing JSON
    const tiers = [];
    document.querySelectorAll('.tier-row').forEach(row => {
        const minQ  = parseInt(row.querySelector('[name="tier_min_qty[]"]').value);
        const maxQ  = row.querySelector('[name="tier_max_qty[]"]').value;
        const price = parseFloat(row.querySelector('[name="tier_price[]"]').value);
        if (!isNaN(minQ) && !isNaN(price)) {
            tiers.push({ min_qty: minQ, max_qty: maxQ ? parseInt(maxQ) : null, price });
        }
    });
    fd.set('tiered_pricing', JSON.stringify(tiers));

    // Variations JSON
    const variations = getVariationData();
    fd.set('variations', JSON.stringify(variations));

    // SKU matrix
    const skuCodes   = [...fd.getAll('sku_codes[]')];
    const skuPrices  = [...fd.getAll('sku_prices[]')];
    const skuStocks  = [...fd.getAll('sku_stocks[]')];
    const skuAttrs   = [...fd.getAll('sku_attributes[]')];
    const skus = skuCodes.map((code, i) => ({
        sku_code:   code,
        price:      skuPrices[i] ? parseFloat(skuPrices[i]) : null,
        stock:      parseInt(skuStocks[i] || 0),
        attributes: skuAttrs[i] ? JSON.parse(skuAttrs[i]) : {},
    }));
    fd.set('skus', JSON.stringify(skus));

    // Remove array fields handled above
    fd.delete('tags_input');

    return fd;
}

/* ── Save draft ─────────────────────────────────────── */
async function saveDraft() {
    if (!validateStep(1)) { goToStep(1); return; }
    const name = document.getElementById('productName').value.trim();
    if (!name) { goToStep(1); showAlert('Please enter a product title before saving.', 'warning'); return; }
    await submitToApi('draft');
}

/* ── Final submit ───────────────────────────────────── */
async function submitProduct() {
    for (let s = 1; s <= TOTAL_STEPS; s++) {
        if (!validateStep(s)) { goToStep(s); return; }
    }
    await submitToApi(null);
}

/* ── API submission ─────────────────────────────────── */
async function submitToApi(statusOverride) {
    const btnSubmit = document.getElementById('btnSubmit');
    const btnDraft  = document.querySelector('[onclick="saveDraft()"]');
    [btnSubmit, btnDraft].forEach(b => { if (b) { b.disabled = true; } });

    try {
        const fd = collectFormData(statusOverride);
        if (savedProductId) {
            fd.set('action', 'update');
            fd.set('id', savedProductId);
        }
        const action = savedProductId ? 'update' : 'create';

        const res  = await fetch('/api/products.php?action=' + action, { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            savedProductId = data.id || savedProductId;
            document.getElementById('productId').value = savedProductId;

            // Upload gallery images
            await uploadImages(savedProductId);

            const dest = statusOverride === 'draft'
                ? '/pages/supplier/products.php?saved=draft'
                : '/pages/supplier/product-edit.php?id=' + savedProductId + '&created=1';
            window.location.href = dest;
        } else {
            showAlert(data.error || 'Failed to save product. Please try again.');
        }
    } catch (err) {
        showAlert('Network error. Please check your connection and try again.');
    } finally {
        [btnSubmit, btnDraft].forEach(b => { if (b) b.disabled = false; });
    }
}

/* ── Upload images after product is created ─────────── */
async function uploadImages(productId) {
    const csrf = document.querySelector('[name="_csrf"]') ? document.querySelector('[name="_csrf"]').value : '';

    // Main image
    const mainInput = document.getElementById('mainImageInput');
    if (mainInput.files[0]) {
        const fd = new FormData();
        fd.append('image', mainInput.files[0]);
        fd.append('product_id', productId);
        fd.append('is_primary', '1');
        if (csrf) fd.append('_csrf', csrf);
        await fetch('/api/products.php?action=upload_image', { method: 'POST', body: fd });
    }

    // Gallery images (limited to plan max)
    const galleryInput = document.getElementById('galleryInput');
    const files = [...galleryInput.files].slice(0, MAX_IMAGES);
    for (const file of files) {
        const fd = new FormData();
        fd.append('image', file);
        fd.append('product_id', productId);
        fd.append('is_primary', '0');
        if (csrf) fd.append('_csrf', csrf);
        await fetch('/api/products.php?action=upload_image', { method: 'POST', body: fd });
    }
}
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
