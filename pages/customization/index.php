<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$db = getDB();
$products = $db->query("SELECT id, name, price FROM products ORDER BY name LIMIT 200")->fetchAll();
$selectedId = (int)get('product_id', 0);

$pageTitle = 'Product Customization';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item active">Product Customization</li>
        </ol>
    </nav>

    <h1 class="h2 mb-4"><i class="bi bi-brush me-2"></i>Product Customization</h1>

    <div id="alertContainer"></div>

    <form id="customizationForm" method="post" action="/api/customization.php?action=save">
        <?= csrfField() ?>
        <div class="row g-4">
            <!-- Options Panel -->
            <div class="col-lg-7">
                <div class="card shadow-sm mb-4">
                    <div class="card-header"><h5 class="mb-0"><i class="bi bi-box me-2"></i>Select Product</h5></div>
                    <div class="card-body">
                        <select class="form-select" id="product_id" name="product_id" required>
                            <option value="">Choose a product...</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= e($p['id']) ?>" <?= $p['id'] == $selectedId ? 'selected' : '' ?>>
                                    <?= e($p['name']) ?> — <?= formatMoney($p['price'] ?? 0) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header"><h5 class="mb-0"><i class="bi bi-palette me-2"></i>Customization Options</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <!-- Color -->
                            <div class="col-md-6">
                                <label for="color" class="form-label fw-bold">Color</label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="color" class="form-control form-control-color" id="color" name="color"
                                           value="#3B82F6" title="Choose color">
                                    <input type="text" class="form-control" id="colorHex" value="#3B82F6" readonly>
                                </div>
                                <div class="mt-2">
                                    <?php
                                    $presets = ['#EF4444','#F59E0B','#10B981','#3B82F6','#8B5CF6','#EC4899','#1F2937','#F3F4F6'];
                                    foreach ($presets as $c): ?>
                                        <button type="button" class="btn btn-sm border me-1 mb-1 color-preset"
                                                data-color="<?= $c ?>"
                                                style="width:30px;height:30px;background:<?= $c ?>;border-radius:50%">&nbsp;</button>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Size -->
                            <div class="col-md-6">
                                <label for="size" class="form-label fw-bold">Size</label>
                                <select class="form-select" id="size" name="size">
                                    <option value="xs">Extra Small (XS)</option>
                                    <option value="s">Small (S)</option>
                                    <option value="m" selected>Medium (M)</option>
                                    <option value="l">Large (L)</option>
                                    <option value="xl">Extra Large (XL)</option>
                                    <option value="xxl">2X Large (XXL)</option>
                                    <option value="custom">Custom Size</option>
                                </select>
                            </div>

                            <!-- Material -->
                            <div class="col-md-6">
                                <label for="material" class="form-label fw-bold">Material</label>
                                <select class="form-select" id="material" name="material">
                                    <option value="standard">Standard</option>
                                    <option value="premium">Premium (+15%)</option>
                                    <option value="eco">Eco-Friendly (+10%)</option>
                                    <option value="metal">Metal (+25%)</option>
                                    <option value="wood">Wood (+20%)</option>
                                    <option value="leather">Leather (+30%)</option>
                                </select>
                            </div>

                            <!-- Engraving -->
                            <div class="col-md-6">
                                <label for="engraving" class="form-label fw-bold">Engraving Text</label>
                                <input type="text" class="form-control" id="engraving" name="engraving"
                                       placeholder="Your custom text" maxlength="50">
                                <div class="form-text"><span id="engravingCount">0</span>/50 characters</div>
                            </div>

                            <!-- Quantity -->
                            <div class="col-md-6">
                                <label for="quantity" class="form-label fw-bold">Quantity</label>
                                <input type="number" class="form-control" id="quantity" name="quantity"
                                       value="1" min="1" max="10000">
                            </div>

                            <!-- Notes -->
                            <div class="col-12">
                                <label for="notes" class="form-label fw-bold">Additional Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"
                                          placeholder="Any special requirements or instructions..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview Panel -->
            <div class="col-lg-5">
                <div class="card shadow-sm sticky-top" style="top:100px">
                    <div class="card-header"><h5 class="mb-0"><i class="bi bi-eye me-2"></i>Preview</h5></div>
                    <div class="card-body text-center">
                        <div id="previewArea" class="border rounded d-flex align-items-center justify-content-center mb-3"
                             style="height:250px;background:#f8f9fa;transition:background 0.3s">
                            <div>
                                <i class="bi bi-box-seam display-3" id="previewIcon" style="color:#3B82F6"></i>
                                <p id="previewEngraving" class="mt-2 fw-bold text-muted fst-italic"></p>
                            </div>
                        </div>

                        <table class="table table-sm text-start mb-3">
                            <tr><td class="text-muted">Product</td><td class="fw-bold" id="previewProduct">—</td></tr>
                            <tr><td class="text-muted">Color</td><td id="previewColor"><span class="badge" style="background:#3B82F6">#3B82F6</span></td></tr>
                            <tr><td class="text-muted">Size</td><td id="previewSize">Medium (M)</td></tr>
                            <tr><td class="text-muted">Material</td><td id="previewMaterial">Standard</td></tr>
                            <tr><td class="text-muted">Engraving</td><td id="previewEngravingText">—</td></tr>
                        </table>

                        <button type="submit" class="btn btn-primary w-100" id="saveBtn">
                            <i class="bi bi-save me-2"></i>Save Customization
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
const colorInput = document.getElementById('color');
const colorHex = document.getElementById('colorHex');
const previewIcon = document.getElementById('previewIcon');

colorInput?.addEventListener('input', function() {
    colorHex.value = this.value;
    previewIcon.style.color = this.value;
    document.getElementById('previewColor').innerHTML = '<span class="badge" style="background:' + this.value + '">' + this.value + '</span>';
});

document.querySelectorAll('.color-preset').forEach(btn => {
    btn.addEventListener('click', function() {
        const c = this.dataset.color;
        colorInput.value = c;
        colorHex.value = c;
        previewIcon.style.color = c;
        document.getElementById('previewColor').innerHTML = '<span class="badge" style="background:' + c + '">' + c + '</span>';
    });
});

document.getElementById('size')?.addEventListener('change', function() {
    document.getElementById('previewSize').textContent = this.options[this.selectedIndex].text;
});

document.getElementById('material')?.addEventListener('change', function() {
    document.getElementById('previewMaterial').textContent = this.options[this.selectedIndex].text;
});

document.getElementById('engraving')?.addEventListener('input', function() {
    document.getElementById('engravingCount').textContent = this.value.length;
    const text = this.value || '—';
    document.getElementById('previewEngraving').textContent = this.value ? '"' + this.value + '"' : '';
    document.getElementById('previewEngravingText').textContent = text;
});

document.getElementById('product_id')?.addEventListener('change', function() {
    document.getElementById('previewProduct').textContent = this.options[this.selectedIndex].text || '—';
});

document.getElementById('customizationForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const alert = document.getElementById('alertContainer');
    const btn = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

    fetch(this.action, { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-save me-2"></i>Save Customization';
            if (data.success || data.status === 'success') {
                alert.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Customization saved!</div>';
            } else {
                alert.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>' + (data.message || data.error || 'Failed to save') + '</div>';
            }
            window.scrollTo({top: 0, behavior: 'smooth'});
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-save me-2"></i>Save Customization';
            alert.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>Network error. Please try again.</div>';
        });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
