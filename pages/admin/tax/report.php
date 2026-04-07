<?php
/**
 * pages/admin/tax/report.php — Tax Report & Exemptions (PR #12)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/tax_engine.php';
requireAdmin();

$db = getDB();

// Filters
$dateFrom = trim($_GET['date_from'] ?? date('Y-m-01'));
$dateTo   = trim($_GET['date_to']   ?? date('Y-m-d'));
$groupBy  = in_array($_GET['group_by'] ?? '', ['country','month','category']) ? $_GET['group_by'] : 'country';

// Summary
$summaryMonth   = getTaxSummary('month');
$summaryQuarter = getTaxSummary('quarter');
$summaryYear    = getTaxSummary('year');

// Report data
$reportRows = getTaxReport($dateFrom, $dateTo, $groupBy);

// Exemptions
$exPage = max(1, (int)($_GET['ex_page'] ?? 1));
$exemptions = getTaxExemptions($exPage, 20);

// Handle add exemption
$exMessage = '';
$exError   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_exemption'])) {
    verifyCsrf();
    $userId  = (int)($_POST['user_id']           ?? 0);
    $type    = trim($_POST['exemption_type']     ?? 'full');
    $cert    = trim($_POST['certificate_number'] ?? '');
    $expiry  = trim($_POST['expiry_date']        ?? '');
    $admin   = (int)$_SESSION['user_id'];

    if ($userId <= 0) {
        $exError = 'User ID is required.';
    } else {
        $ok = setTaxExemption($userId, $type, $cert, $expiry, $admin);
        $exMessage = $ok ? 'Tax exemption granted.' : 'Failed to grant exemption.';
        $exemptions = getTaxExemptions($exPage, 20);
    }
}

// Handle revoke exemption
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_exemption'])) {
    verifyCsrf();
    $exId = (int)($_POST['exemption_id'] ?? 0);
    if ($exId > 0) {
        try {
            $db->prepare('UPDATE tax_exemptions SET is_active = 0 WHERE id = ?')->execute([$exId]);
            $exMessage = 'Exemption revoked.';
        } catch (PDOException $e) { $exError = 'Failed to revoke.'; }
        $exemptions = getTaxExemptions($exPage, 20);
    }
}

$pageTitle = 'Tax Report';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-bar-chart-fill text-primary me-2"></i>Tax Report</h3>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-gear me-1"></i>Tax Config</a>
            <a href="rates.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-table me-1"></i>Rates</a>
            <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>" class="btn btn-outline-success btn-sm">
                <i class="bi bi-download me-1"></i>Export CSV
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small mb-1">Tax Collected — This Month</div>
                            <div class="fw-bold fs-4"><?= formatMoney($summaryMonth['total_tax']) ?></div>
                            <small class="text-muted">from <?= number_format($summaryMonth['orders_count']) ?> orders</small>
                        </div>
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3"><i class="bi bi-calendar-month text-primary fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small mb-1">Tax Collected — This Quarter</div>
                            <div class="fw-bold fs-4"><?= formatMoney($summaryQuarter['total_tax']) ?></div>
                            <small class="text-muted">from <?= number_format($summaryQuarter['orders_count']) ?> orders</small>
                        </div>
                        <div class="rounded-circle bg-success bg-opacity-10 p-3"><i class="bi bi-calendar3 text-success fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small mb-1">Tax Collected — This Year</div>
                            <div class="fw-bold fs-4"><?= formatMoney($summaryYear['total_tax']) ?></div>
                            <small class="text-muted">from <?= number_format($summaryYear['orders_count']) ?> orders</small>
                        </div>
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3"><i class="bi bi-calendar-year text-warning fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Group By</label>
                    <select name="group_by" class="form-select form-select-sm">
                        <option value="country"  <?= $groupBy === 'country'  ? 'selected' : '' ?>>Country</option>
                        <option value="month"    <?= $groupBy === 'month'    ? 'selected' : '' ?>>Month</option>
                        <option value="category" <?= $groupBy === 'category' ? 'selected' : '' ?>>Category</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
                    <a href="report.php" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Report Table -->
    <div class="card border-0 shadow-sm mb-5">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold">
                Tax Report: <?= e($dateFrom) ?> → <?= e($dateTo) ?>
                <span class="badge bg-secondary ms-2">by <?= ucfirst($groupBy) ?></span>
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= ucfirst($groupBy) ?></th>
                        <th>Orders</th>
                        <th>Taxable Amount</th>
                        <th>Tax Collected</th>
                        <th>Avg Rate</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($reportRows)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No tax data for this period.</td></tr>
                <?php else: ?>
                <?php
                $totalTax   = array_sum(array_column($reportRows, 'tax_collected'));
                $totalTaxable = array_sum(array_column($reportRows, 'taxable_amount'));
                foreach ($reportRows as $row):
                    $avgRate = ($row['taxable_amount'] > 0) ? round($row['tax_collected'] / $row['taxable_amount'] * 100, 2) : 0;
                ?>
                <tr>
                    <td class="fw-semibold"><?= e($row['grp'] ?? '—') ?></td>
                    <td><?= number_format((int)$row['orders_count']) ?></td>
                    <td><?= formatMoney((float)$row['taxable_amount']) ?></td>
                    <td class="fw-bold text-primary"><?= formatMoney((float)$row['tax_collected']) ?></td>
                    <td><?= $avgRate ?>%</td>
                </tr>
                <?php endforeach; ?>
                <tr class="table-light fw-bold">
                    <td>TOTAL</td>
                    <td><?= number_format(array_sum(array_column($reportRows,'orders_count'))) ?></td>
                    <td><?= formatMoney($totalTaxable) ?></td>
                    <td class="text-primary"><?= formatMoney($totalTax) ?></td>
                    <td><?= $totalTaxable > 0 ? round($totalTax/$totalTaxable*100,2) : 0 ?>%</td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tax Exemptions -->
    <div id="exemptions" class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold"><i class="bi bi-shield-check text-warning me-2"></i>Tax Exemptions</h6>
            <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#addExemptionModal">
                <i class="bi bi-plus-circle me-1"></i>Grant Exemption
            </button>
        </div>

        <?php if ($exMessage): ?>
        <div class="alert alert-success m-3 mb-0 py-2"><i class="bi bi-check-circle me-2"></i><?= e($exMessage) ?></div>
        <?php endif; ?>
        <?php if ($exError): ?>
        <div class="alert alert-danger m-3 mb-0 py-2"><i class="bi bi-exclamation-circle me-2"></i><?= e($exError) ?></div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>User</th><th>Type</th><th>Certificate</th><th>Expiry</th><th>Granted By</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php if (empty($exemptions['items'])): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No tax exemptions found.</td></tr>
                <?php else: ?>
                <?php foreach ($exemptions['items'] as $ex): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($ex['user_name'] ?? 'User #' . $ex['user_id']) ?></div>
                        <small class="text-muted"><?= e($ex['user_email'] ?? '') ?></small>
                    </td>
                    <td><span class="badge bg-warning text-dark"><?= e(ucfirst(str_replace('_',' ',$ex['exemption_type']))) ?></span></td>
                    <td><?= $ex['certificate_number'] ? e($ex['certificate_number']) : '<span class="text-muted">—</span>' ?></td>
                    <td><?= $ex['expiry_date'] ? e($ex['expiry_date']) : '<span class="text-success">No expiry</span>' ?></td>
                    <td><?= e($ex['granted_by_name'] ?? 'Admin') ?></td>
                    <td>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Revoke this exemption?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="revoke_exemption" value="1">
                            <input type="hidden" name="exemption_id" value="<?= (int)$ex['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle me-1"></i>Revoke</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($exemptions['last_page'] > 1): ?>
        <div class="card-footer bg-white py-2 d-flex justify-content-between align-items-center">
            <small class="text-muted">Page <?= $exPage ?> of <?= $exemptions['last_page'] ?></small>
            <div class="d-flex gap-1">
                <?php if ($exPage > 1): ?><a href="?ex_page=<?= $exPage-1 ?>#exemptions" class="btn btn-sm btn-outline-secondary">‹ Prev</a><?php endif; ?>
                <?php if ($exPage < $exemptions['last_page']): ?><a href="?ex_page=<?= $exPage+1 ?>#exemptions" class="btn btn-sm btn-outline-secondary">Next ›</a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Exemption Modal -->
<div class="modal fade" id="addExemptionModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?= csrfField() ?>
            <input type="hidden" name="add_exemption" value="1">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-shield-plus text-warning me-2"></i>Grant Tax Exemption</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">User ID *</label>
                    <input type="number" name="user_id" class="form-control" placeholder="Enter user ID" min="1" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Exemption Type *</label>
                    <select name="exemption_type" class="form-select" required>
                        <option value="full">Full Exemption</option>
                        <option value="b2b">B2B (Business)</option>
                        <option value="non_profit">Non-Profit</option>
                        <option value="government">Government</option>
                        <option value="partial">Partial</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Certificate Number</label>
                    <input type="text" name="certificate_number" class="form-control" placeholder="Optional certificate/registration number">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Expiry Date</label>
                    <input type="date" name="expiry_date" class="form-control">
                    <small class="text-muted">Leave blank for permanent exemption.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning"><i class="bi bi-shield-check me-1"></i>Grant Exemption</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
