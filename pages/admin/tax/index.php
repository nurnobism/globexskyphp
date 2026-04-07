<?php
/**
 * pages/admin/tax/index.php — Tax Configuration Dashboard (PR #12)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/tax_engine.php';
require_once __DIR__ . '/../../../includes/countries.php';
requireAdmin();

$db = getDB();

// Load current config
$taxMode              = getTaxMode();
$fixedRate            = (float)getTaxSetting('tax_fixed_rate',   '10.00');
$defaultRate          = (float)getTaxSetting('tax_default_rate', '10.00');
$taxInclusive         = getTaxSetting('tax_inclusive',          '0') === '1';
$showTaxOnProduct     = getTaxSetting('show_tax_on_product',    '1') === '1';
$taxLabel             = getTaxSetting('tax_label',              'Tax');
$viesEnabled          = getTaxSetting('vies_validation_enabled','0') === '1';

// Summary stats
$summaryMonth   = getTaxSummary('month');
$summaryQuarter = getTaxSummary('quarter');
$summaryYear    = getTaxSummary('year');

// Recent tax rates (first 20)
$recentRates = getAllTaxRates(1, 20);

$saved = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    verifyCsrf();
    $db2 = getDB();
    $updates = [
        'tax_mode'               => in_array($_POST['tax_mode'] ?? '', ['fixed','per_country','vat']) ? $_POST['tax_mode'] : $taxMode,
        'tax_fixed_rate'         => (string)max(0, min(100, (float)($_POST['tax_fixed_rate'] ?? $fixedRate))),
        'tax_default_rate'       => (string)max(0, min(100, (float)($_POST['tax_default_rate'] ?? $defaultRate))),
        'tax_inclusive'          => isset($_POST['tax_inclusive']) ? '1' : '0',
        'show_tax_on_product'    => isset($_POST['show_tax_on_product']) ? '1' : '0',
        'tax_label'              => substr(strip_tags($_POST['tax_label'] ?? 'Tax'), 0, 50),
        'vies_validation_enabled'=> isset($_POST['vies_validation_enabled']) ? '1' : '0',
    ];
    foreach ($updates as $k => $v) {
        try {
            $db2->prepare("INSERT INTO system_settings (setting_key, setting_value)
                           VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")
                ->execute([$k, $v]);
        } catch (PDOException $e) { /* ignore */ }
    }
    $taxMode          = $updates['tax_mode'];
    $fixedRate        = (float)$updates['tax_fixed_rate'];
    $defaultRate      = (float)$updates['tax_default_rate'];
    $taxInclusive     = $updates['tax_inclusive'] === '1';
    $showTaxOnProduct = $updates['show_tax_on_product'] === '1';
    $taxLabel         = $updates['tax_label'];
    $viesEnabled      = $updates['vies_validation_enabled'] === '1';
    $saved = 'Tax configuration saved.';
}

$pageTitle = 'Tax Configuration';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-receipt-cutoff text-primary me-2"></i>Tax Configuration</h3>
        <div class="d-flex gap-2">
            <a href="rates.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-table me-1"></i>Manage Rates</a>
            <a href="report.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-bar-chart me-1"></i>Tax Report</a>
        </div>
    </div>

    <?php if ($saved): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i><?= e($saved) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3"><i class="bi bi-calendar-month text-primary fs-4"></i></div>
                        <div>
                            <div class="text-muted small">This Month</div>
                            <div class="fw-bold fs-5"><?= formatMoney($summaryMonth['total_tax']) ?></div>
                            <small class="text-muted"><?= number_format($summaryMonth['orders_count']) ?> orders</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-success bg-opacity-10 p-3"><i class="bi bi-calendar3 text-success fs-4"></i></div>
                        <div>
                            <div class="text-muted small">This Quarter</div>
                            <div class="fw-bold fs-5"><?= formatMoney($summaryQuarter['total_tax']) ?></div>
                            <small class="text-muted"><?= number_format($summaryQuarter['orders_count']) ?> orders</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3"><i class="bi bi-calendar-year text-warning fs-4"></i></div>
                        <div>
                            <div class="text-muted small">This Year</div>
                            <div class="fw-bold fs-5"><?= formatMoney($summaryYear['total_tax']) ?></div>
                            <small class="text-muted"><?= number_format($summaryYear['orders_count']) ?> orders</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Config Form -->
        <div class="col-lg-8">
            <form method="POST" action="">
                <?= csrfField() ?>
                <input type="hidden" name="save_config" value="1">

                <!-- Tax Mode -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-toggles2 text-primary me-2"></i>Tax Mode</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php
                            $modes = [
                                'fixed'       => ['icon'=>'bi-pin-fill','color'=>'primary','title'=>'Fixed Rate','desc'=>'Single tax rate applied to all orders regardless of location.'],
                                'per_country' => ['icon'=>'bi-globe-americas','color'=>'success','title'=>'Per-Country','desc'=>'Different rates per country and state/province (e.g., US 7%, UK 20%).'],
                                'vat'         => ['icon'=>'bi-eu','color'=>'info','title'=>'VAT Mode','desc'=>'EU-style VAT with reverse charge for B2B buyers with a valid VAT number.'],
                            ];
                            foreach ($modes as $modeVal => $m): ?>
                            <div class="col-md-4">
                                <label class="d-block cursor-pointer">
                                    <input type="radio" name="tax_mode" value="<?= $modeVal ?>" <?= $taxMode === $modeVal ? 'checked' : '' ?> class="btn-check" id="mode_<?= $modeVal ?>">
                                    <div class="card border-2 h-100 mode-card <?= $taxMode === $modeVal ? "border-{$m['color']}" : 'border-light' ?>" for="mode_<?= $modeVal ?>">
                                        <div class="card-body text-center py-3">
                                            <i class="bi <?= $m['icon'] ?> text-<?= $m['color'] ?> fs-2 d-block mb-2"></i>
                                            <div class="fw-semibold"><?= $m['title'] ?></div>
                                            <small class="text-muted"><?= $m['desc'] ?></small>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Fixed Rate Config -->
                <div class="card border-0 shadow-sm mb-4" id="fixedConfig" <?= $taxMode !== 'fixed' ? 'style="display:none"' : '' ?>>
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-pin-fill text-primary me-2"></i>Fixed Rate Configuration</h6>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-end g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Tax Rate (%)</label>
                                <div class="input-group">
                                    <input type="number" name="tax_fixed_rate" class="form-control" value="<?= $fixedRate ?>" min="0" max="100" step="0.01" id="fixedRateInput">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="alert alert-info mb-0 py-2">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Preview: On a <strong>$100.00</strong> order, tax = <strong id="fixedPreview"><?= number_format($fixedRate, 2) ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Per-Country Config -->
                <div class="card border-0 shadow-sm mb-4" id="countryConfig" <?= $taxMode !== 'per_country' ? 'style="display:none"' : '' ?>>
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-globe-americas text-success me-2"></i>Per-Country Rate Configuration</h6>
                        <a href="rates.php" class="btn btn-sm btn-outline-success"><i class="bi bi-plus-circle me-1"></i>Manage Rates</a>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Default/Fallback Rate (%)</label>
                                <div class="input-group">
                                    <input type="number" name="tax_default_rate" class="form-control" value="<?= $defaultRate ?>" min="0" max="100" step="0.01">
                                    <span class="input-group-text">%</span>
                                </div>
                                <small class="text-muted">Used when no specific country rate is set.</small>
                            </div>
                        </div>
                        <?php if (!empty($recentRates)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light"><tr><th>Country</th><th>State</th><th>Rate</th><th>Tax Name</th></tr></thead>
                                <tbody>
                                <?php foreach (array_slice($recentRates, 0, 8) as $r): ?>
                                <tr>
                                    <td><?= e($r['country_name'] ?: $r['country_code']) ?></td>
                                    <td><?= $r['state_name'] ? e($r['state_name']) : '<span class="text-muted">—</span>' ?></td>
                                    <td><?= number_format((float)$r['rate'], 2) ?>%</td>
                                    <td><?= e($r['tax_name']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <a href="rates.php" class="btn btn-link btn-sm p-0 mt-2">View all <?= count($recentRates) ?>+ rates →</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- VAT Config -->
                <div class="card border-0 shadow-sm mb-4" id="vatConfig" <?= $taxMode !== 'vat' ? 'style="display:none"' : '' ?>>
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-eu text-info me-2"></i>VAT Configuration</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Default VAT Rate (%)</label>
                                <div class="input-group">
                                    <input type="number" name="tax_default_rate" class="form-control" value="<?= $defaultRate ?>" min="0" max="100" step="0.01">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="vies_validation_enabled" id="viesToggle" <?= $viesEnabled ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="viesToggle">
                                        Enable VIES live VAT number validation
                                        <small class="text-muted d-block">Calls EU VIES API to verify VAT numbers in real-time (requires internet access)</small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="alert alert-light border mb-0 py-2 small">
                                    <i class="bi bi-info-circle text-info me-1"></i>
                                    <strong>Reverse charge:</strong> When a B2B buyer provides a valid EU VAT number and is in a different EU country, VAT is 0% (reverse charge applies).
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- General Settings -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-gear text-secondary me-2"></i>General Tax Settings</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Tax Label</label>
                                <select name="tax_label" class="form-select">
                                    <?php foreach (['Tax','VAT','GST','Sales Tax','HST','PST'] as $lbl): ?>
                                    <option value="<?= $lbl ?>" <?= $taxLabel === $lbl ? 'selected' : '' ?>><?= $lbl ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="tax_inclusive" id="taxInclusive" <?= $taxInclusive ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="taxInclusive">
                                        Tax-inclusive pricing
                                        <small class="text-muted d-block">Prices already include tax</small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="show_tax_on_product" id="showTaxProduct" <?= $showTaxOnProduct ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="showTaxProduct">
                                        Show tax on product page
                                        <small class="text-muted d-block">Display tax info next to product price</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-save me-1"></i> Save Configuration
                </button>
            </form>
        </div>

        <!-- Right sidebar -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-info-circle text-secondary me-2"></i>Current Status</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-6 text-muted">Tax Mode</dt>
                        <dd class="col-6"><span class="badge bg-primary"><?= ucfirst(str_replace('_',' ',$taxMode)) ?></span></dd>
                        <dt class="col-6 text-muted">Default Rate</dt>
                        <dd class="col-6"><?= $defaultRate ?>%</dd>
                        <dt class="col-6 text-muted">Tax Label</dt>
                        <dd class="col-6"><?= e($taxLabel) ?></dd>
                        <dt class="col-6 text-muted">Tax Inclusive</dt>
                        <dd class="col-6"><?= $taxInclusive ? '<span class="text-success">Yes</span>' : '<span class="text-muted">No</span>' ?></dd>
                        <dt class="col-6 text-muted">Show on Product</dt>
                        <dd class="col-6"><?= $showTaxOnProduct ? '<span class="text-success">Yes</span>' : '<span class="text-muted">No</span>' ?></dd>
                        <dt class="col-6 text-muted">VIES Validation</dt>
                        <dd class="col-6"><?= $viesEnabled ? '<span class="text-success">Enabled</span>' : '<span class="text-muted">Disabled</span>' ?></dd>
                    </dl>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-link-45deg text-secondary me-2"></i>Quick Links</h6>
                </div>
                <div class="list-group list-group-flush">
                    <a href="rates.php" class="list-group-item list-group-item-action py-2 small">
                        <i class="bi bi-table me-2 text-success"></i>Manage Tax Rates
                    </a>
                    <a href="report.php" class="list-group-item list-group-item-action py-2 small">
                        <i class="bi bi-bar-chart me-2 text-primary"></i>Tax Reports
                    </a>
                    <a href="report.php#exemptions" class="list-group-item list-group-item-action py-2 small">
                        <i class="bi bi-shield-check me-2 text-warning"></i>Tax Exemptions
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle config sections based on selected tax mode
document.querySelectorAll('input[name="tax_mode"]').forEach(radio => {
    radio.addEventListener('change', () => {
        const mode = radio.value;
        document.getElementById('fixedConfig').style.display   = mode === 'fixed'       ? '' : 'none';
        document.getElementById('countryConfig').style.display = mode === 'per_country' ? '' : 'none';
        document.getElementById('vatConfig').style.display     = mode === 'vat'         ? '' : 'none';
        document.querySelectorAll('.mode-card').forEach(c => c.classList.remove('border-primary','border-success','border-info'));
        const map = {fixed:'border-primary', per_country:'border-success', vat:'border-info'};
        radio.nextElementSibling.classList.add(map[mode] || 'border-primary');
    });
});

// Fixed rate preview
const fixedInput = document.getElementById('fixedRateInput');
const preview    = document.getElementById('fixedPreview');
if (fixedInput && preview) {
    fixedInput.addEventListener('input', () => {
        const val = parseFloat(fixedInput.value) || 0;
        preview.textContent = '$' + val.toFixed(2);
    });
}
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
