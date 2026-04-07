<?php
/**
 * pages/admin/billing/invoices.php — All Platform Invoices (Admin) (PR #10)
 *
 * Table of all invoices across all suppliers with filtering and CSV export.
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/invoices.php';

requireAdmin();

$db      = getDB();
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;

$filters = [];
if (!empty($_GET['type']))        $filters['type']      = $_GET['type'];
if (!empty($_GET['status']))      $filters['status']    = $_GET['status'];
if (!empty($_GET['date_from']))   $filters['date_from'] = $_GET['date_from'];
if (!empty($_GET['date_to']))     $filters['date_to']   = $_GET['date_to'];

// Supplier ID filter (admin can drill into a specific supplier)
$filterSupplierId = (int)($_GET['supplier_id'] ?? 0);

// CSV export — streamed in batches to avoid memory exhaustion on large datasets
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="invoices-export-' . date('Ymd') . '.csv"');
    $csvOut  = fopen('php://output', 'w');
    fputcsv($csvOut, ['Invoice #', 'Supplier', 'Date', 'Type', 'Total', 'Status', 'Payment Ref']);
    $exportPage    = 1;
    $exportPerPage = 500;
    do {
        $chunk = getInvoices($filterSupplierId, $filters, $exportPage, $exportPerPage);
        foreach ($chunk['rows'] as $inv) {
            fputcsv($csvOut, [
                $inv['invoice_number'],
                $inv['supplier_name'] ?? $inv['supplier_id'],
                date('Y-m-d', strtotime($inv['created_at'])),
                $inv['type'],
                $inv['total'],
                $inv['status'],
                $inv['payment_ref'] ?? '',
            ]);
        }
        $exportPage++;
    } while (count($chunk['rows']) === $exportPerPage);
    fclose($csvOut);
    exit;
}

$result     = getInvoices($filterSupplierId, $filters, $page, $perPage);
$invoices   = $result['rows'];
$total      = $result['total'];
$totalPages = (int)ceil($total / $perPage);

// Platform-wide revenue totals
$totals = [];
try {
    $tStmt  = $db->query("SELECT
        COALESCE(SUM(total), 0) AS grand_total,
        COALESCE(SUM(CASE WHEN status = 'paid' THEN total ELSE 0 END), 0) AS paid_total,
        COUNT(*) AS invoice_count
        FROM invoices");
    $totals = $tStmt->fetch() ?: [];
} catch (PDOException $e) { /* ignore */ }

$pageTitle = 'Admin — All Invoices';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-receipt text-primary me-2"></i>All Invoices</h3>
        <div>
            <a href="/pages/admin/billing/addons.php" class="btn btn-outline-secondary btn-sm me-2">
                <i class="bi bi-bag-plus me-1"></i>Add-Ons
            </a>
            <a href="?export=1&<?= http_build_query(array_filter($filters)) ?>" class="btn btn-success btn-sm">
                <i class="bi bi-download me-1"></i>Export CSV
            </a>
        </div>
    </div>

    <!-- Revenue Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 bg-primary text-white">
                <div class="card-body py-3 text-center">
                    <div class="fs-3 fw-bold">$<?= number_format((float)($totals['grand_total'] ?? 0), 2) ?></div>
                    <div class="small opacity-75">Total Invoiced</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-success text-white">
                <div class="card-body py-3 text-center">
                    <div class="fs-3 fw-bold">$<?= number_format((float)($totals['paid_total'] ?? 0), 2) ?></div>
                    <div class="small opacity-75">Collected (Paid)</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-info text-white">
                <div class="card-body py-3 text-center">
                    <div class="fs-3 fw-bold"><?= (int)($totals['invoice_count'] ?? 0) ?></div>
                    <div class="small opacity-75">Total Invoices</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-secondary text-white">
                <div class="card-body py-3 text-center">
                    <div class="fs-3 fw-bold"><?= $total ?></div>
                    <div class="small opacity-75">Filtered Results</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <form method="get" class="row g-2 mb-4">
        <div class="col-md-2">
            <input type="number" name="supplier_id" class="form-control form-control-sm"
                placeholder="Supplier ID"
                value="<?= $filterSupplierId ?: '' ?>">
        </div>
        <div class="col-md-2">
            <select name="type" class="form-select form-select-sm">
                <option value="">All Types</option>
                <option value="plan_subscription" <?= ($filters['type'] ?? '') === 'plan_subscription' ? 'selected' : '' ?>>Plan</option>
                <option value="addon_purchase" <?= ($filters['type'] ?? '') === 'addon_purchase' ? 'selected' : '' ?>>Add-On</option>
                <option value="refund" <?= ($filters['type'] ?? '') === 'refund' ? 'selected' : '' ?>>Refund</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                <option value="paid" <?= ($filters['status'] ?? '') === 'paid' ? 'selected' : '' ?>>Paid</option>
                <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="refunded" <?= ($filters['status'] ?? '') === 'refunded' ? 'selected' : '' ?>>Refunded</option>
            </select>
        </div>
        <div class="col-md-2">
            <input type="date" name="date_from" class="form-control form-control-sm"
                value="<?= htmlspecialchars($filters['date_from'] ?? '', ENT_QUOTES) ?>">
        </div>
        <div class="col-md-2">
            <input type="date" name="date_to" class="form-control form-control-sm"
                value="<?= htmlspecialchars($filters['date_to'] ?? '', ENT_QUOTES) ?>">
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
        </div>
        <div class="col-md-1">
            <a href="/pages/admin/billing/invoices.php" class="btn btn-outline-secondary btn-sm w-100">Clear</a>
        </div>
    </form>

    <!-- Table -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Invoice #</th>
                            <th>Supplier</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($inv['invoice_number'], ENT_QUOTES) ?></strong></td>
                            <td>
                                <?= htmlspecialchars($inv['supplier_name'] ?? '', ENT_QUOTES) ?>
                                <?php if (!empty($inv['company_name'])): ?>
                                    <div class="text-muted small"><?= htmlspecialchars($inv['company_name'], ENT_QUOTES) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M d, Y', strtotime($inv['created_at'])) ?></td>
                            <td>
                                <?php if ($inv['type'] === 'plan_subscription'): ?>
                                    <span class="badge bg-primary">Plan</span>
                                <?php elseif ($inv['type'] === 'addon_purchase'): ?>
                                    <span class="badge bg-info text-dark">Add-On</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Refund</span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold">$<?= number_format((float)$inv['total'], 2) ?></td>
                            <td>
                                <?php if ($inv['status'] === 'paid'): ?>
                                    <span class="badge bg-success">Paid</span>
                                <?php elseif ($inv['status'] === 'pending'): ?>
                                    <span class="badge bg-warning text-dark">Pending</span>
                                <?php elseif ($inv['status'] === 'refunded'): ?>
                                    <span class="badge bg-secondary">Refunded</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark"><?= htmlspecialchars($inv['status'], ENT_QUOTES) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="/api/invoices.php?action=download&invoice_id=<?= (int)$inv['id'] ?>"
                                    target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($invoices)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No invoices found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination justify-content-center">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $p ?>&<?= http_build_query(array_filter(array_merge($filters, $filterSupplierId ? ['supplier_id' => $filterSupplierId] : []))) ?>">
                    <?= $p ?>
                </a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
