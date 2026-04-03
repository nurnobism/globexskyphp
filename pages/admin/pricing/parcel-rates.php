<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();

$db = getDB();
$rates = [];
$dbError = null;
try {
    $rates = $db->query("SELECT * FROM shipping_rates ORDER BY origin_country ASC, destination_country ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $dbError = $e->getMessage();
}

$pageTitle = 'Parcel Shipping Rates';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-box-seam text-primary me-2"></i>Parcel Shipping Rates</h3>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#rateModal" onclick="openAddModal()">
                <i class="bi bi-plus-circle me-1"></i> Add Rate
            </button>
            <a href="/pages/admin/pricing/index.php" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
    </div>

    <?php if ($dbError): ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Could not load rates: <?= e($dbError) ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Origin</th>
                        <th>Destination</th>
                        <th>Base Rate</th>
                        <th>Rate/kg</th>
                        <th>Standard</th>
                        <th>Express</th>
                        <th>Priority</th>
                        <th>Economy</th>
                        <th>Insurance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rates)): ?>
                <tr><td colspan="10" class="text-center text-muted py-4">No shipping rates defined yet.</td></tr>
                <?php else: ?>
                <?php foreach ($rates as $r): ?>
                <tr>
                    <td><strong><?= e($r['origin_country']) ?></strong></td>
                    <td><?= e($r['destination_country']) ?></td>
                    <td>$<?= number_format((float)($r['base_rate'] ?? 0), 2) ?></td>
                    <td>$<?= number_format((float)($r['rate_per_kg'] ?? 0), 2) ?>/kg</td>
                    <td>
                        <small><?= number_format((float)($r['method_multiplier_standard'] ?? 1.0), 2) ?>x</small><br>
                        <small class="text-muted"><?= e($r['estimated_days_standard'] ?? '') ?></small>
                    </td>
                    <td>
                        <small><?= number_format((float)($r['method_multiplier_express'] ?? 1.6), 2) ?>x</small><br>
                        <small class="text-muted"><?= e($r['estimated_days_express'] ?? '') ?></small>
                    </td>
                    <td>
                        <small><?= number_format((float)($r['method_multiplier_priority'] ?? 2.2), 2) ?>x</small><br>
                        <small class="text-muted"><?= e($r['estimated_days_priority'] ?? '') ?></small>
                    </td>
                    <td>
                        <small><?= number_format((float)($r['method_multiplier_economy'] ?? 0.8), 2) ?>x</small><br>
                        <small class="text-muted"><?= e($r['estimated_days_economy'] ?? '') ?></small>
                    </td>
                    <td><?= number_format((float)($r['insurance_rate'] ?? 0), 2) ?>%</td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-outline-primary" onclick="openEditModal(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" action="/api/parcels.php?action=delete_rate" onsubmit="return confirm('Delete this rate?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="rate_id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Rate Modal -->
<div class="modal fade" id="rateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rateModalTitle">Add Shipping Rate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="rateForm" action="/api/parcels.php?action=save_rate">
                <?= csrfField() ?>
                <input type="hidden" name="rate_id" id="rateId" value="">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Origin Country *</label>
                            <input type="text" name="origin_country" id="originCountry" class="form-control" placeholder="e.g. US" required maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Destination Country *</label>
                            <input type="text" name="destination_country" id="destinationCountry" class="form-control" placeholder="e.g. GB" required maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Base Rate ($) *</label>
                            <input type="number" name="base_rate" id="baseRate" class="form-control" min="0" step="0.01" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Rate per kg ($) *</label>
                            <input type="number" name="rate_per_kg" id="ratePerKg" class="form-control" min="0" step="0.01" required>
                        </div>
                        <div class="col-12"><hr class="my-1"><h6 class="text-muted">Method Multipliers &amp; Estimated Days</h6></div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Standard Multiplier</label>
                            <input type="number" name="method_multiplier_standard" id="standardMultiplier" class="form-control" min="0" step="0.01" value="1.00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Standard Est. Days</label>
                            <input type="text" name="estimated_days_standard" id="standardDays" class="form-control" placeholder="e.g. 5-10" value="5-10" maxlength="20">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Express Multiplier</label>
                            <input type="number" name="method_multiplier_express" id="expressMultiplier" class="form-control" min="0" step="0.01" value="2.00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Express Est. Days</label>
                            <input type="text" name="estimated_days_express" id="expressDays" class="form-control" placeholder="e.g. 2-5" value="2-5" maxlength="20">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Priority Multiplier</label>
                            <input type="number" name="method_multiplier_priority" id="priorityMultiplier" class="form-control" min="0" step="0.01" value="3.00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Priority Est. Days</label>
                            <input type="text" name="estimated_days_priority" id="priorityDays" class="form-control" placeholder="e.g. 1-3" value="1-3" maxlength="20">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Economy Multiplier</label>
                            <input type="number" name="method_multiplier_economy" id="economyMultiplier" class="form-control" min="0" step="0.01" value="0.70">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Economy Est. Days</label>
                            <input type="text" name="estimated_days_economy" id="economyDays" class="form-control" placeholder="e.g. 10-20" value="10-20" maxlength="20">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Insurance Rate (%)</label>
                            <input type="number" name="insurance_rate" id="insuranceRate" class="form-control" min="0" step="0.01" value="2.00">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="rateSubmitBtn">Add Rate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('rateModalTitle').textContent = 'Add Shipping Rate';
    document.getElementById('rateSubmitBtn').textContent = 'Add Rate';
    document.getElementById('rateId').value = '';
    document.getElementById('rateForm').reset();
    document.getElementById('standardMultiplier').value = '1.00';
    document.getElementById('standardDays').value = '5-10';
    document.getElementById('expressMultiplier').value = '2.00';
    document.getElementById('expressDays').value = '2-5';
    document.getElementById('priorityMultiplier').value = '3.00';
    document.getElementById('priorityDays').value = '1-3';
    document.getElementById('economyMultiplier').value = '0.70';
    document.getElementById('economyDays').value = '10-20';
    document.getElementById('insuranceRate').value = '2.00';
}

function openEditModal(rate) {
    document.getElementById('rateModalTitle').textContent = 'Edit Shipping Rate';
    document.getElementById('rateSubmitBtn').textContent = 'Save Changes';
    document.getElementById('rateId').value = rate.id || '';
    document.getElementById('originCountry').value = rate.origin_country || '';
    document.getElementById('destinationCountry').value = rate.destination_country || '';
    document.getElementById('baseRate').value = rate.base_rate || '';
    document.getElementById('ratePerKg').value = rate.rate_per_kg || '';
    document.getElementById('standardMultiplier').value = rate.method_multiplier_standard || '1.00';
    document.getElementById('standardDays').value = rate.estimated_days_standard || '5-10';
    document.getElementById('expressMultiplier').value = rate.method_multiplier_express || '2.00';
    document.getElementById('expressDays').value = rate.estimated_days_express || '2-5';
    document.getElementById('priorityMultiplier').value = rate.method_multiplier_priority || '3.00';
    document.getElementById('priorityDays').value = rate.estimated_days_priority || '1-3';
    document.getElementById('economyMultiplier').value = rate.method_multiplier_economy || '0.70';
    document.getElementById('economyDays').value = rate.estimated_days_economy || '10-20';
    document.getElementById('insuranceRate').value = rate.insurance_rate || '2.00';
    var modal = new bootstrap.Modal(document.getElementById('rateModal'));
    modal.show();
}
</script>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
