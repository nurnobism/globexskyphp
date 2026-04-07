<?php
/**
 * pages/supplier/billing/invoices.php — Invoice List Page (PR #10)
 *
 * Supplier view of all their invoices with filtering and download.
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/invoices.php';

requireRole(['supplier', 'admin', 'super_admin']);

$db         = getDB();
$supplierId = (int)$_SESSION['user_id'];

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$filters = [];
if (!empty($_GET['type']))      $filters['type']      = $_GET['type'];
if (!empty($_GET['status']))    $filters['status']    = $_GET['status'];
if (!empty($_GET['date_from'])) $filters['date_from'] = $_GET['date_from'];
if (!empty($_GET['date_to']))   $filters['date_to']   = $_GET['date_to'];

$result    = getInvoices($supplierId, $filters, $page, $perPage);
$invoices  = $result['rows'];
$total     = $result['total'];
$totalPages = (int)ceil($total / $perPage);

$stats = getInvoiceStats($supplierId);

$pageTitle = 'My Invoices';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container py-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-receipt text-primary me-2"></i>My Invoices</h3>
        <a href="/pages/supplier/billing/addons.php" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-bag-plus me-1"></i>Add-On Store
        </a>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 bg-primary text-white">
                <div class="card-body py-3 text-center">
                    <div class="fs-4 fw-bold">$<?= number_format((float)$stats['total_spent'], 2) ?></div>
                    <div class="small opacity-75">Total Spent</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-success text-white">
                <div class="card-body py-3 text-center">
                    <div class="fs-4 fw-bold">$<?= number_format((float)$stats['plans_spent'], 2) ?></div>
                    <div class="small opacity-75">Plans</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-info text-white">
                <div class="card-body py-3 text-center">
                    <div class="fs-4 fw-bold">$<?= number_format((float)$stats['addons_spent'], 2) ?></div>
                    <div class="small opacity-75">Add-Ons</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-secondary text-white">
                <div class="card-body py-3 text-center">
                    <div class="fs-4 fw-bold"><?= (int)$stats['invoice_count'] ?></div>
                    <div class="small opacity-75">Invoices</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <form method="get" class="row g-2 mb-4">
        <div class="col-md-3">
            <select name="type" class="form-select form-select-sm">
                <option value="">All Types</option>
                <option value="plan_subscription" <?= ($_GET['type'] ?? '') === 'plan_subscription' ? 'selected' : '' ?>>Plan Subscription</option>
                <option value="addon_purchase" <?= ($_GET['type'] ?? '') === 'addon_purchase' ? 'selected' : '' ?>>Add-On Purchase</option>
                <option value="refund" <?= ($_GET['type'] ?? '') === 'refund' ? 'selected' : '' ?>>Refund</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                <option value="paid" <?= ($_GET['status'] ?? '') === 'paid' ? 'selected' : '' ?>>Paid</option>
                <option value="pending" <?= ($_GET['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="refunded" <?= ($_GET['status'] ?? '') === 'refunded' ? 'selected' : '' ?>>Refunded</option>
            </select>
        </div>
        <div class="col-md-2">
            <input type="date" name="date_from" class="form-control form-control-sm"
                value="<?= htmlspecialchars($_GET['date_from'] ?? '', ENT_QUOTES) ?>" placeholder="From">
        </div>
        <div class="col-md-2">
            <input type="date" name="date_to" class="form-control form-control-sm"
                value="<?= htmlspecialchars($_GET['date_to'] ?? '', ENT_QUOTES) ?>" placeholder="To">
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
        </div>
        <div class="col-md-1">
            <a href="/pages/supplier/billing/invoices.php" class="btn btn-outline-secondary btn-sm w-100">Clear</a>
        </div>
    </form>

    <!-- Invoice Table -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Invoice #</th>
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
                                    <span class="badge bg-success">✅ Paid</span>
                                <?php elseif ($inv['status'] === 'pending'): ?>
                                    <span class="badge bg-warning text-dark">⏳ Pending</span>
                                <?php elseif ($inv['status'] === 'refunded'): ?>
                                    <span class="badge bg-secondary">↩️ Refunded</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark"><?= htmlspecialchars($inv['status'], ENT_QUOTES) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="/pages/supplier/billing/invoice-detail.php?id=<?= (int)$inv['id'] ?>"
                                    class="btn btn-sm btn-outline-primary me-1">
                                    <i class="bi bi-eye"></i> View
                                </a>
                                <a href="/api/invoices.php?action=download&invoice_id=<?= (int)$inv['id'] ?>"
                                    target="_blank" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-download"></i> Download
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($invoices)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No invoices found.</td></tr>
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
                <a class="page-link" href="?page=<?= $p ?>&<?= http_build_query(array_filter($filters)) ?>">
                    <?= $p ?>
                </a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
