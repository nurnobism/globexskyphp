<?php
/**
 * pages/admin/shipping/settings.php — Global Shipping Settings (PR #14)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/shipping.php';
requireAdmin();

$db = getDB();

$settingKeys = [
    'shipping_free_threshold',
    'shipping_default_handling',
    'shipping_weight_unit',
    'shipping_dimension_unit',
    'shipping_show_estimate',
];

$settings = [];
try {
    $placeholders = implode(',', array_fill(0, count($settingKeys), '?'));
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($placeholders)");
    $stmt->execute($settingKeys);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) { /* ignore */ }

$s = fn(string $key, string $default = '') => htmlspecialchars($settings[$key] ?? $default);

$pageTitle = 'Shipping Settings';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-gear text-secondary me-2"></i>Global Shipping Settings</h3>
        <a href="/pages/admin/shipping/zones.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-globe2 me-1"></i>Zones
        </a>
    </div>

    <div id="settingsAlert"></div>

    <div class="row g-4">
        <div class="col-lg-7">
            <form id="settingsForm" class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-sliders me-2"></i>Configuration
                </div>
                <div class="card-body">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($_SESSION['_csrf_token'] ?? '') ?>">

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Global Free Shipping Threshold ($)</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" name="shipping_free_threshold"
                                   value="<?= $s('shipping_free_threshold', '0') ?>" min="0" step="0.01">
                        </div>
                        <div class="form-text">Orders at or above this amount qualify for free shipping. Set to 0 to disable.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Default Handling Time (days)</label>
                        <input type="number" class="form-control" name="shipping_default_handling"
                               value="<?= $s('shipping_default_handling', '1') ?>" min="0" max="30">
                        <div class="form-text">Default number of business days suppliers need to dispatch an order.</div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Weight Unit</label>
                            <select class="form-select" name="shipping_weight_unit">
                                <option value="kg" <?= ($settings['shipping_weight_unit'] ?? 'kg') === 'kg' ? 'selected' : '' ?>>Kilograms (kg)</option>
                                <option value="lb" <?= ($settings['shipping_weight_unit'] ?? 'kg') === 'lb' ? 'selected' : '' ?>>Pounds (lb)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Dimension Unit</label>
                            <select class="form-select" name="shipping_dimension_unit">
                                <option value="cm" <?= ($settings['shipping_dimension_unit'] ?? 'cm') === 'cm' ? 'selected' : '' ?>>Centimetres (cm)</option>
                                <option value="in" <?= ($settings['shipping_dimension_unit'] ?? 'cm') === 'in' ? 'selected' : '' ?>>Inches (in)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="shipping_show_estimate"
                                   id="showEstimate" value="1"
                                   <?= ($settings['shipping_show_estimate'] ?? '1') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="showEstimate">
                                Show shipping estimate on product page
                            </label>
                        </div>
                        <div class="form-text ms-4">When enabled, buyers can get a shipping estimate from the product detail page.</div>
                    </div>
                </div>
                <div class="card-footer bg-white text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Settings
                    </button>
                </div>
            </form>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-info-circle me-2"></i>Quick Reference
                </div>
                <div class="card-body small text-muted">
                    <p><strong>Flat Rate:</strong> Fixed cost per order regardless of weight or value.</p>
                    <p><strong>Weight-Based:</strong> base_cost + (weight × per_kg_cost). Useful for heavy items.</p>
                    <p><strong>Price-Based:</strong> base_cost treated as % of cart subtotal, plus optional per-item fee.</p>
                    <p class="mb-0"><strong>Free (threshold):</strong> $0 when cart total ≥ free_above_amount.</p>
                </div>
            </div>

            <div class="card border-0 shadow-sm mt-3">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-link-45deg me-2"></i>Quick Links
                </div>
                <div class="list-group list-group-flush">
                    <a href="/pages/admin/shipping/zones.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-globe2 me-2 text-primary"></i>Manage Zones
                    </a>
                    <a href="/pages/admin/shipping/methods.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-truck me-2 text-success"></i>Manage Methods
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('settingsForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    if (!e.target.querySelector('#showEstimate').checked) fd.set('shipping_show_estimate', '0');

    // Build update payload for each setting key
    const keys = ['shipping_free_threshold','shipping_default_handling','shipping_weight_unit','shipping_dimension_unit','shipping_show_estimate'];
    const results = await Promise.all(keys.map(async key => {
        const val = fd.get(key) ?? '0';
        const sfD = new FormData();
        sfD.set('_csrf_token', fd.get('_csrf_token'));
        sfD.set('setting_key',   key);
        sfD.set('setting_value', val);
        const res = await fetch('/api/admin-settings.php?action=update_setting', { method: 'POST', body: sfD });
        return res.ok;
    }));

    const alertEl = document.getElementById('settingsAlert');
    if (results.every(Boolean)) {
        alertEl.innerHTML = '<div class="alert alert-success alert-dismissible fade show">Settings saved successfully. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    } else {
        alertEl.innerHTML = '<div class="alert alert-danger">Some settings could not be saved.</div>';
    }
    window.scrollTo(0, 0);
});
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
