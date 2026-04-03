<?php
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/commission.php';
requireAdmin();

$db = getDB();

// Revenue overview
$stats = [
    'total_revenue'      => 0.0,
    'total_commission'   => 0.0,
    'total_plan_revenue' => 0.0,
    'total_payouts'      => 0.0,
    'today_revenue'      => 0.0,
    'week_revenue'       => 0.0,
    'month_revenue'      => 0.0,
    'ytd_revenue'        => 0.0,
    'pending_payouts'    => 0.0,
];

try {
    $r = $db->query('SELECT COALESCE(SUM(total),0) FROM orders WHERE status NOT IN ("cancelled","refunded")');
    $stats['total_revenue'] = (float)$r->fetchColumn();

    $r = $db->query('SELECT COALESCE(SUM(commission_amount),0) FROM commission_logs');
    $stats['total_commission'] = (float)$r->fetchColumn();

    $r = $db->query('SELECT COALESCE(SUM(amount),0) FROM payout_requests WHERE status IN ("completed","processing")');
    $stats['total_payouts'] = (float)$r->fetchColumn();

    $r = $db->query('SELECT COALESCE(SUM(total),0) FROM orders WHERE DATE(placed_at)=CURDATE() AND status NOT IN ("cancelled","refunded")');
    $stats['today_revenue'] = (float)$r->fetchColumn();

    $r = $db->query('SELECT COALESCE(SUM(total),0) FROM orders WHERE placed_at>=DATE_SUB(NOW(),INTERVAL 7 DAY) AND status NOT IN ("cancelled","refunded")');
    $stats['week_revenue'] = (float)$r->fetchColumn();

    $r = $db->query('SELECT COALESCE(SUM(total),0) FROM orders WHERE placed_at>=DATE_FORMAT(NOW(),"%Y-%m-01") AND status NOT IN ("cancelled","refunded")');
    $stats['month_revenue'] = (float)$r->fetchColumn();

    $r = $db->query('SELECT COALESCE(SUM(total),0) FROM orders WHERE placed_at>=DATE_FORMAT(NOW(),"%Y-01-01") AND status NOT IN ("cancelled","refunded")');
    $stats['ytd_revenue'] = (float)$r->fetchColumn();

    $r = $db->query('SELECT COALESCE(SUM(amount),0) FROM payout_requests WHERE status = "pending"');
    $stats['pending_payouts'] = (float)$r->fetchColumn();
} catch (PDOException $e) { /* ignore */ }

// Chart data: daily revenue last 30 days
$dailyLabels = [];
$dailyData   = [];
try {
    $stmt = $db->query('SELECT DATE(placed_at) AS day, COALESCE(SUM(total),0) AS rev
        FROM orders
        WHERE placed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
          AND status NOT IN ("cancelled","refunded")
        GROUP BY day ORDER BY day ASC');
    foreach ($stmt->fetchAll() as $row) {
        $dailyLabels[] = date('M j', strtotime($row['day']));
        $dailyData[]   = (float)$row['rev'];
    }
} catch (PDOException $e) { /* ignore */ }

// Commission by category
$catLabels = [];
$catData   = [];
try {
    $stmt = $db->query('SELECT c.name, COALESCE(SUM(cl.commission_amount),0) AS total
        FROM commission_logs cl
        JOIN products p ON p.id = (SELECT product_id FROM order_items WHERE order_id = cl.order_id LIMIT 1)
        JOIN categories c ON c.id = p.category_id
        GROUP BY c.id ORDER BY total DESC LIMIT 6');
    foreach ($stmt->fetchAll() as $row) {
        $catLabels[] = $row['name'];
        $catData[]   = (float)$row['total'];
    }
} catch (PDOException $e) { /* ignore */ }

// Recent transactions
$recentTx = [];
try {
    $stmt = $db->query('SELECT o.id, o.order_number, o.total, o.status, o.placed_at, u.email
        FROM orders o LEFT JOIN users u ON u.id = o.buyer_id
        ORDER BY o.placed_at DESC LIMIT 10');
    $recentTx = $stmt->fetchAll();
} catch (PDOException $e) { /* ignore */ }

$pageTitle = 'Finance Dashboard';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-bar-chart-fill text-primary me-2"></i>Finance Dashboard</h3>
        <div class="d-flex gap-2">
            <a href="/pages/admin/finance/commissions.php" class="btn btn-outline-primary btn-sm">Commissions</a>
            <a href="/pages/admin/finance/payouts.php" class="btn btn-outline-success btn-sm">Payouts</a>
            <a href="/pages/admin/finance/invoices.php" class="btn btn-outline-secondary btn-sm">Invoices</a>
        </div>
    </div>

    <!-- Revenue Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body">
                    <div class="text-muted small mb-1">Total Revenue</div>
                    <div class="fs-4 fw-bold text-success">$<?= number_format($stats['total_revenue'], 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body">
                    <div class="text-muted small mb-1">Total Commission</div>
                    <div class="fs-4 fw-bold text-primary">$<?= number_format($stats['total_commission'], 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body">
                    <div class="text-muted small mb-1">Total Payouts</div>
                    <div class="fs-4 fw-bold text-warning">$<?= number_format($stats['total_payouts'], 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body">
                    <div class="text-muted small mb-1">Pending Payouts</div>
                    <div class="fs-4 fw-bold text-danger">$<?= number_format($stats['pending_payouts'], 2) ?></div>
                    <?php if ($stats['pending_payouts'] > 0): ?>
                    <a href="/pages/admin/finance/payouts.php" class="btn btn-danger btn-sm mt-1">Review</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Period Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center py-2">
                <div class="card-body py-2">
                    <div class="text-muted small">Today</div>
                    <div class="fw-bold">$<?= number_format($stats['today_revenue'], 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center py-2">
                <div class="card-body py-2">
                    <div class="text-muted small">This Week</div>
                    <div class="fw-bold">$<?= number_format($stats['week_revenue'], 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center py-2">
                <div class="card-body py-2">
                    <div class="text-muted small">This Month</div>
                    <div class="fw-bold">$<?= number_format($stats['month_revenue'], 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center py-2">
                <div class="card-body py-2">
                    <div class="text-muted small">Year to Date</div>
                    <div class="fw-bold">$<?= number_format($stats['ytd_revenue'], 2) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light fw-semibold">Daily Revenue — Last 30 Days</div>
                <div class="card-body">
                    <canvas id="dailyRevenueChart" height="80"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light fw-semibold">Commission by Category</div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <?php if (!empty($catLabels)): ?>
                    <canvas id="catChart" height="200"></canvas>
                    <?php else: ?>
                    <p class="text-muted small">No data yet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light fw-semibold">Recent Transactions</div>
        <?php if (empty($recentTx)): ?>
        <div class="card-body text-center text-muted py-4">No transactions yet.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Order #</th><th>Buyer</th><th>Total</th><th>Status</th><th>Date</th></tr>
                </thead>
                <tbody>
                <?php foreach ($recentTx as $tx): ?>
                <tr>
                    <td><a href="/pages/admin/orders.php?id=<?= (int)$tx['id'] ?>">#<?= e($tx['order_number'] ?? $tx['id']) ?></a></td>
                    <td><?= e($tx['email'] ?? '—') ?></td>
                    <td>$<?= number_format((float)$tx['total'], 2) ?></td>
                    <td><span class="badge bg-<?= match($tx['status']){'completed'=>'success','pending'=>'warning','cancelled'=>'danger',default=>'secondary'} ?>">
                        <?= ucfirst($tx['status']) ?></span></td>
                    <td><?= formatDate($tx['placed_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    const dailyCtx = document.getElementById('dailyRevenueChart');
    if (dailyCtx) {
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($dailyLabels) ?>,
                datasets: [{ label: 'Revenue ($)', data: <?= json_encode($dailyData) ?>,
                    borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,0.1)', fill: true, tension: 0.3 }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
    }

    <?php if (!empty($catLabels)): ?>
    const catCtx = document.getElementById('catChart');
    if (catCtx) {
        new Chart(catCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($catLabels) ?>,
                datasets: [{ data: <?= json_encode($catData) ?>,
                    backgroundColor: ['#0d6efd','#198754','#dc3545','#ffc107','#0dcaf0','#6f42c1'] }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });
    }
    <?php endif; ?>
})();
</script>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
