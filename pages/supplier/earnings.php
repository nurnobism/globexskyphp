<?php
require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/commission.php';
requireRole(['supplier', 'admin', 'super_admin']);

$db         = getDB();
$supplierId = $_SESSION['user_id'];

// Summary figures
$summary = getCommissionSummary($supplierId, 'monthly');

// Available balance (from api/payouts logic)
$totalSales      = 0.0;
$totalCommission = 0.0;
$totalPaid       = 0.0;
try {
    $sStmt = $db->prepare('SELECT COALESCE(SUM(amount),0) FROM supplier_earnings WHERE supplier_id = ? AND type = "sale"');
    $sStmt->execute([$supplierId]);
    $totalSales = (float)$sStmt->fetchColumn();

    $cStmt = $db->prepare('SELECT COALESCE(SUM(amount),0) FROM supplier_earnings WHERE supplier_id = ? AND type = "commission_deduct"');
    $cStmt->execute([$supplierId]);
    $totalCommission = (float)$cStmt->fetchColumn();

    $pStmt = $db->prepare('SELECT COALESCE(SUM(amount),0) FROM supplier_earnings WHERE supplier_id = ? AND type = "payout"');
    $pStmt->execute([$supplierId]);
    $totalPaid = (float)$pStmt->fetchColumn();
} catch (PDOException $e) {
    // Fallback: use orders
    try {
        $oStmt = $db->prepare('SELECT COALESCE(SUM(oi.subtotal),0)
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            JOIN products p ON p.id = oi.product_id
            WHERE p.supplier_id = ? AND o.status IN ("completed","delivered","shipped")');
        $oStmt->execute([$supplierId]);
        $totalSales = (float)$oStmt->fetchColumn();
    } catch (PDOException $e2) { /* ignore */ }
    $totalCommission = (float)($summary['total_commission'] ?? 0);
}

$pendingPayout = 0.0;
try {
    $ppStmt = $db->prepare('SELECT COALESCE(SUM(amount),0) FROM payout_requests WHERE supplier_id = ? AND status IN ("pending","processing")');
    $ppStmt->execute([$supplierId]);
    $pendingPayout = (float)$ppStmt->fetchColumn();
} catch (PDOException $e) { /* ignore */ }

$availableBalance = max(0, $totalSales - $totalCommission - $totalPaid - $pendingPayout);

// Per-order earnings table
$orderEarnings = [];
try {
    $oeStmt = $db->prepare('SELECT cl.*, o.order_number FROM commission_logs cl
        LEFT JOIN orders o ON o.id = cl.order_id
        WHERE cl.supplier_id = ?
        ORDER BY cl.created_at DESC LIMIT 50');
    $oeStmt->execute([$supplierId]);
    $orderEarnings = $oeStmt->fetchAll();
} catch (PDOException $e) { /* ignore */ }

// Chart data (last 30 days)
$chartLabels = [];
$chartData   = [];
try {
    $chStmt = $db->prepare('SELECT DATE(created_at) AS day,
        COALESCE(SUM(order_amount - commission_amount),0) AS net_earning
        FROM commission_logs
        WHERE supplier_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY day ORDER BY day ASC');
    $chStmt->execute([$supplierId]);
    $chartRows = $chStmt->fetchAll();
    foreach ($chartRows as $row) {
        $chartLabels[] = date('M j', strtotime($row['day']));
        $chartData[]   = (float)$row['net_earning'];
    }
} catch (PDOException $e) { /* ignore */ }

$pageTitle = 'My Earnings';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-cash-coin text-success me-2"></i>My Earnings</h3>
        <a href="/pages/supplier/payouts.php" class="btn btn-success">
            <i class="bi bi-arrow-down-circle me-1"></i> Request Withdrawal
        </a>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body">
                    <div class="text-muted small mb-1">Total Earned</div>
                    <div class="fs-4 fw-bold text-success">$<?= number_format($totalSales, 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body">
                    <div class="text-muted small mb-1">Total Commission</div>
                    <div class="fs-4 fw-bold text-danger">-$<?= number_format($totalCommission, 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body">
                    <div class="text-muted small mb-1">Available Balance</div>
                    <div class="fs-4 fw-bold text-primary">$<?= number_format($availableBalance, 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body">
                    <div class="text-muted small mb-1">Pending Payout</div>
                    <div class="fs-4 fw-bold text-warning">$<?= number_format($pendingPayout, 2) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Earnings Chart -->
    <?php if (!empty($chartLabels)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-light fw-semibold">Earnings — Last 30 Days</div>
        <div class="card-body">
            <canvas id="earningsChart" height="80"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <!-- Per-Order Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light fw-semibold">Order Earnings Breakdown</div>
        <?php if (empty($orderEarnings)): ?>
        <div class="card-body text-center text-muted py-4">No earnings recorded yet.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Order #</th>
                        <th>Order Amount</th>
                        <th>Commission %</th>
                        <th>Commission $</th>
                        <th>Net Earning</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orderEarnings as $row): ?>
                <tr>
                    <td><a href="/pages/order/detail.php?id=<?= (int)$row['order_id'] ?>">#<?= e($row['order_number'] ?? $row['order_id']) ?></a></td>
                    <td>$<?= number_format((float)$row['order_amount'], 2) ?></td>
                    <td><?= number_format((float)$row['commission_rate'], 1) ?>%</td>
                    <td class="text-danger">-$<?= number_format((float)$row['commission_amount'], 2) ?></td>
                    <td class="text-success fw-semibold">$<?= number_format((float)$row['order_amount'] - (float)$row['commission_amount'], 2) ?></td>
                    <td><?= formatDate($row['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($chartLabels)): ?>
<script>
(function() {
    const ctx = document.getElementById('earningsChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: 'Net Earnings ($)',
                data: <?= json_encode($chartData) ?>,
                borderColor: '#198754',
                backgroundColor: 'rgba(25,135,84,0.1)',
                fill: true,
                tension: 0.3,
            }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
})();
</script>
<?php endif; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
