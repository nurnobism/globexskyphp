<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();

$db       = getDB();
$rates    = [];
$settings = [];
$editRate = null;

$editId = (int)get('edit', 0);

try {
    $stmt  = $db->query(
        "SELECT * FROM shipping_rates ORDER BY origin_country ASC, destination_country ASC"
    );
    $rates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($editId > 0) {
        $stmt    = $db->prepare("SELECT * FROM shipping_rates WHERE id = ?");
        $stmt->execute([$editId]);
        $editRate = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
} catch (PDOException $e) {
    // Tables may not exist yet
}

try {
    $stmt     = $db->query("SELECT * FROM carry_service_settings ORDER BY setting_key ASC");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Tables may not exist yet
}

$pageTitle = 'Shipping Rates';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-currency-dollar text-primary me-2"></i>Shipping Rates</h3>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#rateModal">
                <i class="bi bi-plus-lg me-1"></i>Add Rate
            </button>
            <a href="/pages/admin/logistics/index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Overview
            </a>
        </div>
    </div>

    <!-- Quick Nav -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="/pages/admin/logistics/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Overview</a>
        <a href="/pages/admin/logistics/parcels.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-seam me-1"></i>Parcels</a>
        <a href="/pages/admin/logistics/carriers.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-person-badge me-1"></i>Carriers</a>
        <a href="/pages/admin/logistics/carry-requests.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left-right me-1"></i>Carry Requests</a>
        <a href="/pages/admin/logistics/rates.php" class="btn btn-primary btn-sm"><i class="bi bi-currency-dollar me-1"></i>Rates</a>
    </div>

    <!-- Shipping Rates Table -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold">Shipping Rates</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Origin</th>
                        <th>Destination</th>
                        <th>Base Rate</th>
                        <th>Rate/kg</th>
                        <th>Standard ×</th>
                        <th>Express ×</th>
                        <th>Priority ×</th>
                        <th>Economy ×</th>
                        <th>Insurance %</th>
                        <th>Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rates)): ?>
                <tr><td colspan="11" class="text-center text-muted py-4">No shipping rates configured.</td></tr>
                <?php endif; ?>
                <?php foreach ($rates as $r): ?>
                <tr>
                    <td><strong><?= e($r['origin_country']) ?></strong></td>
                    <td><strong><?= e($r['destination_country']) ?></strong></td>
                    <td><?= formatMoney($r['base_rate']) ?></td>
                    <td><?= formatMoney($r['rate_per_kg']) ?></td>
                    <td><?= e($r['method_multiplier_standard']) ?>×
                        <small class="text-muted"><?= e($r['estimated_days_standard'] ?? '') ?> days</small>
                    </td>
                    <td><?= e($r['method_multiplier_express']) ?>×
                        <small class="text-muted"><?= e($r['estimated_days_express'] ?? '') ?> days</small>
                    </td>
                    <td><?= e($r['method_multiplier_priority']) ?>×
                        <small class="text-muted"><?= e($r['estimated_days_priority'] ?? '') ?> days</small>
                    </td>
                    <td><?= e($r['method_multiplier_economy']) ?>×
                        <small class="text-muted"><?= e($r['estimated_days_economy'] ?? '') ?> days</small>
                    </td>
                    <td><?= e($r['insurance_rate']) ?>%</td>
                    <td>
                        <?php if ($r['is_active']): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?edit=<?= (int)$r['id'] ?>"
                           class="btn btn-sm btn-outline-primary"
                           data-bs-toggle="modal"
                           data-bs-target="#rateModal">
                            <i class="bi bi-pencil"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Carry Service Settings -->
    <?php if (!empty($settings)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold">Carry Service Settings</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Setting Key</th>
                        <th>Value</th>
                        <th>Description</th>
                        <th>Last Updated</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($settings as $s): ?>
                <tr>
                    <td><code><?= e($s['setting_key']) ?></code></td>
                    <td><?= e($s['setting_value'] ?? '—') ?></td>
                    <td class="text-muted"><?= e($s['description'] ?? '') ?></td>
                    <td class="text-nowrap"><?= formatDateTime($s['updated_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-info">No carry service settings found.</div>
    <?php endif; ?>
</div>

<!-- Add/Edit Rate Modal -->
<div class="modal fade" id="rateModal" tabindex="-1" aria-labelledby="rateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="/api/admin.php?action=update_shipping_rate">
                <?= csrfField() ?>
                <?php if ($editRate): ?>
                <input type="hidden" name="rate_id" value="<?= (int)$editRate['id'] ?>">
                <?php endif; ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="rateModalLabel">
                        <i class="bi bi-currency-dollar me-2"></i><?= $editRate ? 'Edit' : 'Add' ?> Shipping Rate
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Origin Country (ISO 2) <span class="text-danger">*</span></label>
                            <input type="text" name="origin_country" class="form-control"
                                   maxlength="2" placeholder="e.g. CN"
                                   value="<?= e($editRate['origin_country'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Destination Country (ISO 2) <span class="text-danger">*</span></label>
                            <input type="text" name="destination_country" class="form-control"
                                   maxlength="2" placeholder="e.g. US"
                                   value="<?= e($editRate['destination_country'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Base Rate (USD) <span class="text-danger">*</span></label>
                            <input type="number" name="base_rate" class="form-control"
                                   step="0.01" min="0" placeholder="5.00"
                                   value="<?= e($editRate['base_rate'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Rate per kg (USD) <span class="text-danger">*</span></label>
                            <input type="number" name="rate_per_kg" class="form-control"
                                   step="0.01" min="0" placeholder="3.50"
                                   value="<?= e($editRate['rate_per_kg'] ?? '') ?>" required>
                        </div>
                        <div class="col-12"><hr class="my-1"><p class="small fw-semibold text-muted mb-0">Method Multipliers</p></div>
                        <div class="col-md-3">
                            <label class="form-label small">Standard ×</label>
                            <input type="number" name="method_multiplier_standard" class="form-control form-control-sm"
                                   step="0.01" min="0"
                                   value="<?= e($editRate['method_multiplier_standard'] ?? '1.00') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Express ×</label>
                            <input type="number" name="method_multiplier_express" class="form-control form-control-sm"
                                   step="0.01" min="0"
                                   value="<?= e($editRate['method_multiplier_express'] ?? '2.00') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Priority ×</label>
                            <input type="number" name="method_multiplier_priority" class="form-control form-control-sm"
                                   step="0.01" min="0"
                                   value="<?= e($editRate['method_multiplier_priority'] ?? '3.00') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Economy ×</label>
                            <input type="number" name="method_multiplier_economy" class="form-control form-control-sm"
                                   step="0.01" min="0"
                                   value="<?= e($editRate['method_multiplier_economy'] ?? '0.70') ?>">
                        </div>
                        <div class="col-12"><hr class="my-1"><p class="small fw-semibold text-muted mb-0">Estimated Delivery Days</p></div>
                        <div class="col-md-3">
                            <label class="form-label small">Standard</label>
                            <input type="text" name="estimated_days_standard" class="form-control form-control-sm"
                                   placeholder="5-10"
                                   value="<?= e($editRate['estimated_days_standard'] ?? '5-10') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Express</label>
                            <input type="text" name="estimated_days_express" class="form-control form-control-sm"
                                   placeholder="2-5"
                                   value="<?= e($editRate['estimated_days_express'] ?? '2-5') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Priority</label>
                            <input type="text" name="estimated_days_priority" class="form-control form-control-sm"
                                   placeholder="1-3"
                                   value="<?= e($editRate['estimated_days_priority'] ?? '1-3') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Economy</label>
                            <input type="text" name="estimated_days_economy" class="form-control form-control-sm"
                                   placeholder="10-20"
                                   value="<?= e($editRate['estimated_days_economy'] ?? '10-20') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Insurance Rate (%)</label>
                            <input type="number" name="insurance_rate" class="form-control"
                                   step="0.01" min="0"
                                   value="<?= e($editRate['insurance_rate'] ?? '2.00') ?>">
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" value="1" class="form-check-input" id="rateActive"
                                       <?= !isset($editRate) || !empty($editRate['is_active']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="rateActive">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Rate
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editRate): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = new bootstrap.Modal(document.getElementById('rateModal'));
    modal.show();
});
</script>
<?php endif; ?>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
