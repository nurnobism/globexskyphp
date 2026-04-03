<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();

$db = getDB();

// Filters
$supplierId = (int)($_GET['supplier_id'] ?? 0);
$from       = $_GET['from'] ?? '';
$to         = $_GET['to'] ?? '';
$page       = max(1, (int)($_GET['page'] ?? 1));
$limit      = 30;
$offset     = ($page - 1) * $limit;

$where  = [];
$params = [];
if ($supplierId > 0) { $where[] = 'cl.supplier_id = ?'; $params[] = $supplierId; }
if ($from)           { $where[] = 'cl.created_at >= ?'; $params[] = $from . ' 00:00:00'; }
if ($to)             { $where[] = 'cl.created_at <= ?'; $params[] = $to . ' 23:59:59'; }

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$logs        = [];
$total       = 0;

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="commission_logs_' . date('Y-m-d') . '.csv"');
    $stmt = $db->prepare("SELECT cl.id, cl.order_id, u.email AS supplier_email, cl.order_amount,
        cl.commission_rate, cl.commission_amount, cl.tier, cl.created_at
        FROM commission_logs cl LEFT JOIN users u ON u.id = cl.supplier_id $whereClause
        ORDER BY cl.created_at DESC");
    $stmt->execute($params);
    $fp = fopen('php://output', 'w');
    fputcsv($fp, ['ID','Order ID','Supplier','Order Amount','Rate %','Commission $','Tier','Date']);
    while ($row = $stmt->fetch()) {
        fputcsv($fp, [$row['id'], $row['order_id'], $row['supplier_email'],
            $row['order_amount'], $row['commission_rate'], $row['commission_amount'],
            $row['tier'], $row['created_at']]);
    }
    fclose($fp);
    exit;
}

try {
    $cStmt = $db->prepare("SELECT COUNT(*) FROM commission_logs cl $whereClause");
    $cStmt->execute($params);
    $total = (int)$cStmt->fetchColumn();

    $stmt = $db->prepare("SELECT cl.*, u.email, u.company_name
        FROM commission_logs cl LEFT JOIN users u ON u.id = cl.supplier_id
        $whereClause ORDER BY cl.created_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
} catch (PDOException $e) { /* table may not exist */ }

// Summary totals
$totals = ['order_amount' => 0.0, 'commission_amount' => 0.0];
try {
    $tStmt = $db->prepare("SELECT COALESCE(SUM(order_amount),0) AS oa, COALESCE(SUM(commission_amount),0) AS ca
        FROM commission_logs cl $whereClause");
    $tStmt->execute($params);
    $row = $tStmt->fetch();
    $totals['order_amount']      = (float)($row['oa'] ?? 0);
    $totals['commission_amount'] = (float)($row['ca'] ?? 0);
} catch (PDOException $e) { /* ignore */ }

$pages     = max(1, (int)ceil($total / $limit));
$pageTitle = 'Commission Logs';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-percent text-primary me-2"></i>Commission Logs</h3>
        <div class="d-flex gap-2">
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>"
               class="btn btn-outline-success btn-sm">
                <i class="bi bi-download me-1"></i> Export CSV
            </a>
            <a href="/pages/admin/finance/index.php" class="btn btn-outline-secondary btn-sm">← Finance</a>
        </div>
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
                <label class="form-label small fw-semibold">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
            </div>
        </div>
    </form>

    <!-- Summary -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center py-2">
                <div class="card-body py-2">
                    <div class="text-muted small">Records</div>
                    <div class="fw-bold"><?= number_format($total) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center py-2">
                <div class="card-body py-2">
                    <div class="text-muted small">Order Volume</div>
                    <div class="fw-bold">$<?= number_format($totals['order_amount'], 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center py-2">
                <div class="card-body py-2">
                    <div class="text-muted small">Total Commission</div>
                    <div class="fw-bold text-primary">$<?= number_format($totals['commission_amount'], 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center py-2">
                <div class="card-body py-2">
                    <div class="text-muted small">Avg Commission %</div>
                    <div class="fw-bold"><?= $totals['order_amount'] > 0 ? number_format($totals['commission_amount'] / $totals['order_amount'] * 100, 1) : '0' ?>%</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <?php if (empty($logs)): ?>
        <div class="card-body text-center text-muted py-5">No commission logs found.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th><th>Order</th><th>Supplier</th>
                        <th>Order $</th><th>Rate</th><th>Commission $</th>
                        <th>Tier</th><th>Plan Disc.</th><th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= (int)$log['id'] ?></td>
                    <td><a href="/pages/admin/orders.php?id=<?= (int)$log['order_id'] ?>">#<?= (int)$log['order_id'] ?></a></td>
                    <td><?= e($log['company_name'] ?: $log['email'] ?? '—') ?></td>
                    <td>$<?= number_format((float)$log['order_amount'], 2) ?></td>
                    <td><?= number_format((float)$log['commission_rate'], 1) ?>%</td>
                    <td class="text-primary fw-semibold">$<?= number_format((float)$log['commission_amount'], 2) ?></td>
                    <td><span class="badge bg-secondary"><?= e($log['tier'] ?? '—') ?></span></td>
                    <td><?= $log['plan_discount_applied'] > 0 ? number_format((float)$log['plan_discount_applied'], 1) . '%' : '—' ?></td>
                    <td><?= formatDate($log['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
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
