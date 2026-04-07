<?php
/**
 * pages/admin/tax/rates.php — Tax Rate Management (PR #12)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/tax_engine.php';
require_once __DIR__ . '/../../../includes/countries.php';
requireAdmin();

$db      = getDB();
$message = '';
$error   = '';

// Handle add / edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_rate'])) {
    verifyCsrf();
    $countryCode = strtoupper(trim($_POST['country_code'] ?? ''));
    $stateCode   = strtoupper(trim($_POST['state_code']   ?? ''));
    $stateName   = trim($_POST['state_name']   ?? '');
    $countryName = trim($_POST['country_name'] ?? '');
    $rate        = (float)($_POST['rate']      ?? 0);
    $taxName     = trim($_POST['tax_name']     ?? 'Tax');

    if (strlen($countryCode) !== 2) {
        $error = 'Country code must be 2 letters.';
    } elseif ($rate < 0 || $rate > 100) {
        $error = 'Rate must be between 0 and 100.';
    } else {
        if ($countryName === '') $countryName = getCountryName($countryCode);
        $ok = setCountryTaxRate($countryCode, $rate, $taxName, $stateCode, $stateName, $countryName);
        $message = $ok ? 'Tax rate saved.' : 'Failed to save rate.';
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_rate'])) {
    verifyCsrf();
    $rateId = (int)($_POST['rate_id'] ?? 0);
    if ($rateId > 0) {
        deleteCountryTaxRate($rateId);
        $message = 'Rate deleted.';
    }
}

// Pagination & filter
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 50;
$offset     = ($page - 1) * $perPage;
$filterCode = strtoupper(trim($_GET['country'] ?? ''));
$filterName = trim($_GET['q'] ?? '');

// Build query
try {
    $where  = 'WHERE 1=1';
    $params = [];
    if ($filterCode !== '') { $where .= ' AND country_code = ?'; $params[] = $filterCode; }
    if ($filterName !== '') { $where .= ' AND (country_name LIKE ? OR state_name LIKE ? OR tax_name LIKE ?)'; $params = array_merge($params, ["%$filterName%","%$filterName%","%$filterName%"]); }

    $total = (int)$db->prepare("SELECT COUNT(*) FROM tax_rates $where")->execute($params) && ($stmt2 = $db->prepare("SELECT COUNT(*) FROM tax_rates $where")) && $stmt2->execute($params) ? $stmt2->fetchColumn() : 0;

    $stmt  = $db->prepare("SELECT * FROM tax_rates $where ORDER BY country_name ASC, state_name ASC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $rates = $stmt->fetchAll();
    $lastPage = (int)ceil(max(1, $total) / $perPage);
} catch (PDOException $e) {
    $rates    = [];
    $total    = 0;
    $lastPage = 1;
}

$countries = getCountryList();

$pageTitle = 'Tax Rate Management';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-table text-success me-2"></i>Tax Rate Management</h3>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Tax Config</a>
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addRateModal">
                <i class="bi bi-plus-circle me-1"></i>Add Rate
            </button>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i><?= e($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">Search</label>
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="Country, state or tax name…" value="<?= e($filterName) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Country Code</label>
                    <input type="text" name="country" class="form-control form-control-sm" placeholder="e.g. US" value="<?= e($filterCode) ?>" maxlength="2">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Search</button>
                    <a href="rates.php" class="btn btn-outline-secondary btn-sm ms-1">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Rates Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Tax Rates <span class="badge bg-secondary ms-1"><?= number_format($total) ?></span></span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Country</th>
                        <th>State/Province</th>
                        <th>Rate</th>
                        <th>Tax Name</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rates)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No tax rates found.</td></tr>
                <?php else: ?>
                <?php foreach ($rates as $r): ?>
                <tr>
                    <td class="text-muted small"><?= (int)$r['id'] ?></td>
                    <td>
                        <strong><?= e($r['country_name'] ?: $r['country_code']) ?></strong>
                        <span class="badge bg-light text-dark border ms-1"><?= e($r['country_code']) ?></span>
                    </td>
                    <td><?= $r['state_name'] ? e($r['state_name']) . ' <span class="text-muted">(' . e($r['state_code']) . ')</span>' : '<span class="text-muted">—</span>' ?></td>
                    <td><strong><?= number_format((float)$r['rate'], 2) ?>%</strong></td>
                    <td><?= e($r['tax_name']) ?></td>
                    <td><?= $r['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="editRate(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this rate?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="delete_rate" value="1">
                            <input type="hidden" name="rate_id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($lastPage > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
            <small class="text-muted">Page <?= $page ?> of <?= $lastPage ?></small>
            <div class="d-flex gap-1">
                <?php if ($page > 1): ?><a href="?page=<?= $page-1 ?>&q=<?= urlencode($filterName) ?>&country=<?= urlencode($filterCode) ?>" class="btn btn-sm btn-outline-secondary">‹ Prev</a><?php endif; ?>
                <?php if ($page < $lastPage): ?><a href="?page=<?= $page+1 ?>&q=<?= urlencode($filterName) ?>&country=<?= urlencode($filterCode) ?>" class="btn btn-sm btn-outline-secondary">Next ›</a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add / Edit Modal -->
<div class="modal fade" id="addRateModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?= csrfField() ?>
            <input type="hidden" name="save_rate" value="1">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="rateModalTitle"><i class="bi bi-plus-circle text-success me-2"></i>Add Tax Rate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Country *</label>
                    <select name="country_code" id="rateCountryCode" class="form-select" required>
                        <option value="">Select country…</option>
                        <?php foreach ($countries as $c): ?>
                        <option value="<?= e($c['code']) ?>" data-name="<?= e($c['name']) ?>"><?= $c['flag'] ?> <?= e($c['name']) ?> (<?= e($c['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="country_name" id="rateCountryName">
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label fw-semibold">State Code</label>
                        <input type="text" name="state_code" id="rateStateCode" class="form-control" placeholder="e.g. CA" maxlength="10">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">State Name</label>
                        <input type="text" name="state_name" id="rateStateName" class="form-control" placeholder="e.g. California">
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Rate (%) *</label>
                        <div class="input-group">
                            <input type="number" name="rate" id="rateValue" class="form-control" step="0.01" min="0" max="100" required>
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Tax Name</label>
                        <input type="text" name="tax_name" id="rateTaxName" class="form-control" placeholder="VAT, GST, Sales Tax…" value="Tax">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success"><i class="bi bi-save me-1"></i>Save Rate</button>
            </div>
        </form>
    </div>
</div>

<script>
const rateModal = document.getElementById('addRateModal');

// Sync country name hidden field
document.getElementById('rateCountryCode').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    document.getElementById('rateCountryName').value = opt.dataset.name || '';
});

function editRate(r) {
    document.getElementById('rateModalTitle').innerHTML = '<i class="bi bi-pencil text-primary me-2"></i>Edit Tax Rate';
    document.getElementById('rateCountryCode').value = r.country_code || '';
    document.getElementById('rateCountryName').value = r.country_name || '';
    document.getElementById('rateStateCode').value   = r.state_code  || '';
    document.getElementById('rateStateName').value   = r.state_name  || '';
    document.getElementById('rateValue').value       = r.rate        || 0;
    document.getElementById('rateTaxName').value     = r.tax_name    || 'Tax';
    new bootstrap.Modal(rateModal).show();
}
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
