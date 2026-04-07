<?php
/**
 * pages/supplier/billing/invoices.php — Invoice List (PR #9)
 *
 * Table: Invoice #, Date, Plan, Amount, Duration, Status, Actions
 * PDF download link per invoice
 * Filter by date range and status
 */

require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/plans.php';
requireRole(['supplier', 'admin', 'super_admin']);

$supplierId = (int)$_SESSION['user_id'];
$db         = getDB();

// Filters
$filterStatus    = htmlspecialchars($_GET['status']     ?? '', ENT_QUOTES, 'UTF-8');
$filterDateFrom  = htmlspecialchars($_GET['date_from']  ?? '', ENT_QUOTES, 'UTF-8');
$filterDateTo    = htmlspecialchars($_GET['date_to']    ?? '', ENT_QUOTES, 'UTF-8');
$page            = max(1, (int)($_GET['page'] ?? 1));
$perPage         = 20;
$offset          = ($page - 1) * $perPage;

// Single invoice PDF download
$downloadId = (int)($_GET['id'] ?? 0);
if ($downloadId > 0) {
    // Simple text-based invoice (no PDF lib needed)
    $inv = null;
    try {
        $stmt = $db->prepare(
            'SELECT pi.*, sp.name AS plan_name, u.email, CONCAT(u.first_name," ",u.last_name) AS supplier_name
             FROM plan_invoices pi
             LEFT JOIN plan_subscriptions ps ON ps.id = pi.subscription_id
             LEFT JOIN supplier_plans sp ON sp.id = ps.plan_id
             LEFT JOIN users u ON u.id = pi.supplier_id
             WHERE pi.id = ? AND pi.supplier_id = ?'
        );
        $stmt->execute([$downloadId, $supplierId]);
        $inv = $stmt->fetch();
    } catch (PDOException $e) { /* ignore */ }

    if ($inv) {
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="invoice-' . htmlspecialchars($inv['invoice_number']) . '.txt"');
        echo "GlobexSky Invoice\n";
        echo str_repeat('=', 40) . "\n";
        echo "Invoice #: " . $inv['invoice_number'] . "\n";
        echo "Date:      " . date('M j, Y', strtotime($inv['created_at'])) . "\n";
        echo "Supplier:  " . trim($inv['supplier_name'] ?? '') . "\n";
        echo "Email:     " . ($inv['email'] ?? '') . "\n";
        echo str_repeat('-', 40) . "\n";
        echo "Description: " . ($inv['description'] ?? ($inv['plan_name'] . ' plan subscription')) . "\n";
        echo "Amount:      $" . number_format((float)$inv['amount'], 2) . " " . ($inv['currency'] ?? 'USD') . "\n";
        echo "Status:      " . strtoupper($inv['status'] ?? 'pending') . "\n";
        if ($inv['paid_at']) {
            echo "Paid At:     " . date('M j, Y H:i', strtotime($inv['paid_at'])) . "\n";
        }
        echo str_repeat('=', 40) . "\n";
        echo "Thank you for your business!\n";
        echo "Support: support@globexsky.com\n";
        exit;
    }
}

// Build query
$where  = ['pi.supplier_id = ?'];
$params = [$supplierId];

if ($filterStatus) {
    $where[]  = 'pi.status = ?';
    $params[] = $filterStatus;
}
if ($filterDateFrom) {
    $where[]  = 'DATE(pi.created_at) >= ?';
    $params[] = $filterDateFrom;
}
if ($filterDateTo) {
    $where[]  = 'DATE(pi.created_at) <= ?';
    $params[] = $filterDateTo;
}

$whereSQL = implode(' AND ', $where);

$invoices = [];
$total    = 0;
try {
    $countStmt = $db->prepare("SELECT COUNT(*) FROM plan_invoices pi WHERE $whereSQL");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $listStmt = $db->prepare(
        "SELECT pi.*, sp.name AS plan_name, ps.duration
         FROM plan_invoices pi
         LEFT JOIN plan_subscriptions ps ON ps.id = pi.subscription_id
         LEFT JOIN supplier_plans sp ON sp.id = ps.plan_id
         WHERE $whereSQL
         ORDER BY pi.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $listParams   = array_merge($params, [$perPage, $offset]);
    $listStmt->execute($listParams);
    $invoices = $listStmt->fetchAll();
} catch (PDOException $e) {
    $invoices = [];
}

$totalPages = max(1, (int)ceil($total / $perPage));

$pageTitle = 'Invoices';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0"><i class="bi bi-file-earmark-text me-2"></i>Invoices</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="/pages/supplier/billing/index.php">Billing</a></li>
                    <li class="breadcrumb-item active">Invoices</li>
                </ol>
            </nav>
        </div>
        <a href="/pages/supplier/billing/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Billing
        </a>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="paid"     <?= $filterStatus === 'paid'     ? 'selected' : '' ?>>Paid</option>
                        <option value="pending"  <?= $filterStatus === 'pending'  ? 'selected' : '' ?>>Pending</option>
                        <option value="failed"   <?= $filterStatus === 'failed'   ? 'selected' : '' ?>>Failed</option>
                        <option value="refunded" <?= $filterStatus === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($filterDateFrom) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($filterDateTo) ?>">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-search me-1"></i>Filter
                    </button>
                    <a href="?" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Invoice Table -->
    <div class="card">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><?= number_format($total) ?> invoice<?= $total !== 1 ? 's' : '' ?></span>
        </div>
        <?php if ($invoices): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th>Plan</th>
                        <th>Duration</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td class="font-monospace small"><?= htmlspecialchars($inv['invoice_number'] ?? '') ?></td>
                        <td><?= $inv['created_at'] ? htmlspecialchars(date('M j, Y', strtotime($inv['created_at']))) : '—' ?></td>
                        <td><?= htmlspecialchars($inv['plan_name'] ?? '—') ?></td>
                        <td class="text-capitalize"><?= htmlspecialchars($inv['duration'] ?? 'monthly') ?></td>
                        <td class="fw-semibold">$<?= number_format((float)($inv['amount'] ?? 0), 2) ?></td>
                        <td>
                            <?php
                            echo match($inv['status'] ?? '') {
                                'paid'     => '<span class="badge bg-success">Paid</span>',
                                'pending'  => '<span class="badge bg-warning text-dark">Pending</span>',
                                'failed'   => '<span class="badge bg-danger">Failed</span>',
                                'refunded' => '<span class="badge bg-secondary">Refunded</span>',
                                default    => '<span class="badge bg-light text-dark">—</span>',
                            };
                            ?>
                        </td>
                        <td>
                            <a href="?id=<?= (int)$inv['id'] ?>"
                               class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-download me-1"></i>Download
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <nav>
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link"
                           href="?page=<?= $i ?>&status=<?= urlencode($filterStatus) ?>&date_from=<?= urlencode($filterDateFrom) ?>&date_to=<?= urlencode($filterDateTo) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-receipt fs-1 d-block mb-3"></i>
            No invoices found.
            <?php if ($filterStatus || $filterDateFrom || $filterDateTo): ?>
            <br><a href="?" class="btn btn-outline-secondary btn-sm mt-2">Clear filters</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
