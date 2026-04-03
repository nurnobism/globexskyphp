<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();

$db          = getDB();
$page        = max(1, (int)($_GET['page'] ?? 1));
$limit       = 20;
$offset      = ($page - 1) * $limit;
$supplierId  = (int)($_GET['supplier_id'] ?? 0);
$statusFilter = $_GET['status'] ?? '';

$where  = [];
$params = [];
if ($supplierId > 0)    { $where[] = 'supplier_id = ?'; $params[] = $supplierId; }
if ($statusFilter)      { $where[] = 'status = ?';      $params[] = $statusFilter; }
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$invoices = [];
$total    = 0;
try {
    $cStmt = $db->prepare("SELECT COUNT(*) FROM invoices $whereClause");
    $cStmt->execute($params);
    $total = (int)$cStmt->fetchColumn();

    $stmt = $db->prepare("SELECT i.*, u.email, u.company_name
        FROM invoices i LEFT JOIN users u ON u.id = i.supplier_id
        $whereClause ORDER BY i.created_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $invoices = $stmt->fetchAll();
} catch (PDOException $e) { /* table may not exist */ }

$pages     = max(1, (int)ceil($total / $limit));
$pageTitle = 'Invoices';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-file-earmark-text text-primary me-2"></i>Invoices</h3>
        <a href="/pages/admin/finance/index.php" class="btn btn-outline-secondary btn-sm">← Finance</a>
    </div>

    <!-- Filters -->
    <form method="GET" class="card border-0 shadow-sm p-3 mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Supplier ID</label>
                <input type="number" name="supplier_id" class="form-control form-control-sm"
                       value="<?= $supplierId ?: '' ?>" placeholder="All">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach (['draft','sent','paid','overdue','cancelled'] as $s): ?>
                    <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <?php if (empty($invoices)): ?>
        <div class="card-body text-center text-muted py-5">No invoices found.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Invoice #</th><th>Supplier</th><th>Type</th>
                        <th>Amount</th><th>Tax</th><th>Total</th>
                        <th>Status</th><th>Date</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($invoices as $inv): ?>
                <tr>
                    <td><strong><?= e($inv['invoice_number']) ?></strong></td>
                    <td>
                        <?= e($inv['company_name'] ?: $inv['email'] ?? '—') ?>
                        <br><small class="text-muted"><?= e($inv['email'] ?? '') ?></small>
                    </td>
                    <td><span class="badge bg-secondary"><?= e(ucfirst(str_replace('_', ' ', $inv['type']))) ?></span></td>
                    <td>$<?= number_format((float)$inv['amount'], 2) ?></td>
                    <td><?= (float)$inv['tax_amount'] > 0 ? '$' . number_format((float)$inv['tax_amount'], 2) : '—' ?></td>
                    <td class="fw-semibold">$<?= number_format((float)$inv['total'], 2) ?></td>
                    <td>
                        <span class="badge bg-<?= match($inv['status']){'paid'=>'success','overdue'=>'danger','cancelled'=>'secondary','sent'=>'info',default=>'warning'} ?>">
                            <?= ucfirst($inv['status']) ?>
                        </span>
                    </td>
                    <td><?= formatDate($inv['created_at']) ?></td>
                    <td>
                        <?php if (!empty($inv['pdf_url'])): ?>
                        <a href="<?= e($inv['pdf_url']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-filetype-pdf"></i> PDF
                        </a>
                        <?php else: ?>
                        <button class="btn btn-sm btn-outline-primary"
                                onclick="window.print()">
                            <i class="bi bi-printer"></i> Print
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
        <div class="card-footer bg-light d-flex justify-content-center">
            <nav><ul class="pagination pagination-sm mb-0">
                <?php for ($i = 1; $i <= min($pages, 10); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
            </ul></nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
