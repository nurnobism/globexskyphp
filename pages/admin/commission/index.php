<?php
/**
 * pages/admin/commission/index.php — Commission Dashboard (PR #8)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/commission.php';
requireAdmin();

$db    = getDB();
$stats = getAdminCommissionStats();

// Monthly chart data (last 6 months)
$monthlyLabels = [];
$monthlyData   = [];
try {
    $stmt = $db->query(
        'SELECT DATE_FORMAT(created_at, "%Y-%m") AS mo,
                COALESCE(SUM(commission_amount), 0) AS total
         FROM commission_logs
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
         GROUP BY mo
         ORDER BY mo ASC'
    );
    foreach ($stmt->fetchAll() as $row) {
        $monthlyLabels[] = $row['mo'];
        $monthlyData[]   = (float)$row['total'];
    }
} catch (PDOException $e) { /* ignore */ }

// Recent commission logs
$recentLogs = [];
try {
    $stmt = $db->query(
        'SELECT cl.*, u.email, u.company_name
         FROM commission_logs cl
         LEFT JOIN users u ON u.id = cl.supplier_id
         ORDER BY cl.created_at DESC
         LIMIT 15'
    );
    $recentLogs = $stmt->fetchAll();
} catch (PDOException $e) { /* ignore */ }

$pageTitle = 'Commission Dashboard';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-percent text-primary me-2"></i>Commission Dashboard</h3>
        <div class="d-flex gap-2">
            <a href="/pages/admin/commission/tiers.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-layers me-1"></i>Tier Config
            </a>
            <a href="/pages/admin/commission/categories.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-tags me-1"></i>Category Rates
            </a>
            <a href="/pages/admin/finance/commissions.php" class="btn btn-outline-info btn-sm">
                <i class="bi bi-list-ul me-1"></i>All Logs
            </a>
        </div>
    </div>

    <!-- Stats cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                            <i class="bi bi-currency-dollar text-primary fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Total Earned (All Time)</div>
                            <div class="fw-bold fs-5">$<?= number_format($stats['total_commission_earned'], 2) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-success bg-opacity-10 p-3">
                            <i class="bi bi-calendar-month text-success fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">This Month</div>
                            <div class="fw-bold fs-5">$<?= number_format($stats['this_month'], 2) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                            <i class="bi bi-calendar text-warning fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Last Month</div>
                            <div class="fw-bold fs-5">$<?= number_format($stats['last_month'], 2) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-info bg-opacity-10 p-3">
                            <i class="bi bi-receipt text-info fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Total Orders</div>
                            <div class="fw-bold fs-5"><?= number_format($stats['total_orders']) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Trend chart -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-graph-up-arrow text-primary me-2"></i>Commission Trend (6 months)
                </div>
                <div class="card-body">
                    <canvas id="commissionTrendChart" height="80"></canvas>
                </div>
            </div>
        </div>

        <!-- By tier -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-layers text-primary me-2"></i>By GMV Tier
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Tier</th><th class="text-end">Orders</th><th class="text-end">Commission</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($stats['by_tier'] as $row): ?>
                        <tr>
                            <td><span class="badge bg-secondary"><?= e($row['tier'] ?? '—') ?></span></td>
                            <td class="text-end"><?= number_format((int)$row['orders']) ?></td>
                            <td class="text-end fw-semibold">$<?= number_format((float)$row['commission'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($stats['by_tier'])): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">No data yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Top categories -->
    <?php if (!empty($stats['by_category'])): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent fw-semibold">
            <i class="bi bi-tags text-primary me-2"></i>Top Categories by Commission
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Category</th><th class="text-end">Orders</th><th class="text-end">Commission</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($stats['by_category'] as $row): ?>
                    <tr>
                        <td><?= e($row['category'] ?? '—') ?></td>
                        <td class="text-end"><?= number_format((int)$row['orders']) ?></td>
                        <td class="text-end fw-semibold">$<?= number_format((float)$row['commission'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent logs -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-clock-history text-primary me-2"></i>Recent Commission Logs</span>
            <a href="/pages/admin/finance/commissions.php" class="btn btn-link btn-sm p-0">View all →</a>
        </div>
        <?php if (empty($recentLogs)): ?>
        <div class="card-body text-center text-muted py-4">No commission logs yet.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Order</th><th>Supplier</th><th>Subtotal</th>
                        <th>Rate</th><th>Commission</th><th>Tier</th><th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentLogs as $log): ?>
                <tr>
                    <td><a href="/pages/admin/orders/detail.php?id=<?= (int)$log['order_id'] ?>">#<?= (int)$log['order_id'] ?></a></td>
                    <td><?= e($log['company_name'] ?: ($log['email'] ?? '—')) ?></td>
                    <td>$<?= number_format((float)($log['order_subtotal'] ?? $log['order_amount'] ?? 0), 2) ?></td>
                    <td><?= number_format((float)($log['final_rate'] ?? ($log['commission_rate'] ?? 0) / 100) * 100, 2) ?>%</td>
                    <td class="text-primary fw-semibold">$<?= number_format((float)$log['commission_amount'], 2) ?></td>
                    <td><span class="badge bg-secondary"><?= e($log['gmv_tier'] ?? $log['tier'] ?? '—') ?></span></td>
                    <td><?= formatDate($log['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(function () {
    const labels = <?= json_encode($monthlyLabels) ?>;
    const data   = <?= json_encode($monthlyData) ?>;
    new Chart(document.getElementById('commissionTrendChart'), {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Commission ($)',
                data,
                fill: true,
                tension: 0.4,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,0.08)',
                pointBackgroundColor: '#0d6efd',
            }]
        },
        options: { responsive: true, plugins: { legend: { display: false } } }
    });
})();
</script>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
