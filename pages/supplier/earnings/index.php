<?php
/**
 * pages/supplier/earnings/index.php — Supplier Earnings Dashboard (PR #11)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/payouts.php';
requireRole(['supplier', 'admin', 'super_admin']);

$db         = getDB();
$supplierId = (int)$_SESSION['user_id'];

// Balance overview
$balance = getSupplierBalance($supplierId);

// Earnings breakdown for chart (last 30 days, daily)
$earningsTrend = getEarningsBreakdown($supplierId, 'daily', date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));

// Quick stats: this month vs last month
$thisMonth = ['orders' => 0, 'revenue' => 0.0, 'commission' => 0.0, 'net' => 0.0];
$lastMonth = ['orders' => 0, 'net' => 0.0];
try {
    $stmt = $db->prepare(
        'SELECT COUNT(DISTINCT cl.order_id) AS orders,
                COALESCE(SUM(cl.order_subtotal), 0) AS revenue,
                COALESCE(SUM(cl.commission_amount), 0) AS commission,
                COALESCE(SUM(cl.net_amount), 0) AS net
         FROM commission_logs cl
         WHERE cl.supplier_id = ?
           AND cl.created_at >= DATE_FORMAT(NOW(), "%Y-%m-01")'
    );
    $stmt->execute([$supplierId]);
    $row = $stmt->fetch();
    if ($row) {
        $thisMonth = [
            'orders'     => (int)$row['orders'],
            'revenue'    => (float)$row['revenue'],
            'commission' => (float)$row['commission'],
            'net'        => (float)$row['net'],
        ];
    }

    $stmt = $db->prepare(
        'SELECT COUNT(DISTINCT cl.order_id) AS orders, COALESCE(SUM(cl.net_amount), 0) AS net
         FROM commission_logs cl
         WHERE cl.supplier_id = ?
           AND cl.created_at >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), "%Y-%m-01")
           AND cl.created_at < DATE_FORMAT(NOW(), "%Y-%m-01")'
    );
    $stmt->execute([$supplierId]);
    $row = $stmt->fetch();
    if ($row) {
        $lastMonth = ['orders' => (int)$row['orders'], 'net' => (float)$row['net']];
    }
} catch (PDOException $e) { /* ignore */ }

$netChange = $lastMonth['net'] > 0
    ? round((($thisMonth['net'] - $lastMonth['net']) / $lastMonth['net']) * 100, 1)
    : ($thisMonth['net'] > 0 ? 100.0 : 0.0);

$avgOrderValue = $thisMonth['orders'] > 0 ? round($thisMonth['revenue'] / $thisMonth['orders'], 2) : 0.0;

// Recent orders with earnings
$recentOrders = [];
try {
    $cutoff = date('Y-m-d H:i:s', strtotime('-7 days'));
    $stmt   = $db->prepare(
        'SELECT
            o.id AS order_id,
            o.order_number,
            o.placed_at,
            o.status,
            o.delivered_at,
            o.hold_released_at,
            cl.order_subtotal AS gross_amount,
            cl.final_rate,
            cl.commission_amount,
            cl.net_amount,
            CONCAT(u.first_name, " ", u.last_name) AS buyer_name,
            DATE_ADD(COALESCE(o.delivered_at, o.updated_at), INTERVAL :days DAY) AS hold_expires_at
         FROM commission_logs cl
         JOIN orders o ON o.id = cl.order_id
         LEFT JOIN users u ON u.id = o.buyer_id
         WHERE cl.supplier_id = :sid
         ORDER BY o.placed_at DESC
         LIMIT 20'
    );
    $stmt->execute([':days' => PAYOUT_HOLD_DAYS, ':sid' => $supplierId]);
    $recentOrders = $stmt->fetchAll();
} catch (PDOException $e) { /* ignore */ }

$pageTitle = 'My Earnings';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-graph-up-arrow text-success me-2"></i>My Earnings</h3>
        <div class="d-flex gap-2">
            <a href="/pages/supplier/earnings/withdraw.php" class="btn btn-success btn-sm">
                <i class="bi bi-cash-coin me-1"></i>Withdraw
            </a>
            <a href="/pages/supplier/earnings/history.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-clock-history me-1"></i>History
            </a>
            <a href="/pages/supplier/earnings/methods.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-wallet2 me-1"></i>Methods
            </a>
        </div>
    </div>

    <!-- Balance Overview Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-success bg-opacity-10 p-3 fs-4">💰</div>
                        <div>
                            <div class="text-muted small">Available Balance</div>
                            <div class="fw-bold fs-5 text-success">$<?= number_format($balance['available_balance'], 2) ?></div>
                        </div>
                    </div>
                    <a href="/pages/supplier/earnings/withdraw.php" class="btn btn-success btn-sm w-100 mt-3">Withdraw</a>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3 fs-4">⏳</div>
                        <div>
                            <div class="text-muted small">In Hold (7-day)</div>
                            <div class="fw-bold fs-5">$<?= number_format($balance['in_hold'], 2) ?></div>
                        </div>
                    </div>
                    <a href="#holdDetails" data-bs-toggle="collapse" class="btn btn-outline-warning btn-sm w-100 mt-3">View Details</a>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-info bg-opacity-10 p-3 fs-4">📤</div>
                        <div>
                            <div class="text-muted small">Pending Payouts</div>
                            <div class="fw-bold fs-5">$<?= number_format($balance['pending_payouts'], 2) ?></div>
                        </div>
                    </div>
                    <a href="/pages/supplier/earnings/history.php?status=pending" class="btn btn-outline-info btn-sm w-100 mt-3">View Queue</a>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3 fs-4">✅</div>
                        <div>
                            <div class="text-muted small">Total Paid Out</div>
                            <div class="fw-bold fs-5">$<?= number_format($balance['total_paid'], 2) ?></div>
                        </div>
                    </div>
                    <a href="/pages/supplier/earnings/history.php?status=completed" class="btn btn-outline-primary btn-sm w-100 mt-3">View History</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold">Earnings Trend</h6>
                    <div class="btn-group btn-group-sm" id="trendToggle">
                        <button class="btn btn-outline-secondary active" data-days="7">7d</button>
                        <button class="btn btn-outline-secondary" data-days="30">30d</button>
                        <button class="btn btn-outline-secondary" data-days="90">90d</button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($earningsTrend)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-bar-chart fs-1 d-block mb-2"></i>No earnings data yet.
                    </div>
                    <?php else: ?>
                    <canvas id="earningsChart" height="120"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold">This Month</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Orders</span>
                        <span class="fw-semibold"><?= $thisMonth['orders'] ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Gross Revenue</span>
                        <span class="fw-semibold">$<?= number_format($thisMonth['revenue'], 2) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Commission</span>
                        <span class="fw-semibold text-danger">−$<?= number_format($thisMonth['commission'], 2) ?></span>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="fw-semibold">Net Earnings</span>
                        <span class="fw-bold text-success">$<?= number_format($thisMonth['net'], 2) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">vs Last Month</span>
                        <span class="badge bg-<?= $netChange >= 0 ? 'success' : 'danger' ?>">
                            <?= $netChange >= 0 ? '↑' : '↓' ?> <?= abs($netChange) ?>%
                        </span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Avg. Order Value</span>
                        <span class="fw-semibold small">$<?= number_format($avgOrderValue, 2) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hold Details (collapsible) -->
    <div class="collapse mb-4" id="holdDetails">
        <?php $holdOrders = getHoldingFunds($supplierId); ?>
        <?php if ($holdOrders): ?>
        <div class="card border-0 shadow-sm border-warning border-start border-3">
            <div class="card-header bg-warning bg-opacity-10">
                <h6 class="mb-0 fw-semibold">⏳ Orders in 7-Day Hold</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Order #</th><th>Net Amount</th><th>Delivered At</th><th>Hold Expires</th><th>Days Left</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($holdOrders as $h): ?>
                    <tr>
                        <td><a href="/pages/supplier/orders/detail.php?id=<?= (int)$h['order_id'] ?>">#<?= (int)$h['order_id'] ?></a></td>
                        <td class="fw-semibold">$<?= number_format((float)$h['amount'], 2) ?></td>
                        <td><?= $h['delivered_at'] ? date('M j, Y', strtotime($h['delivered_at'])) : '—' ?></td>
                        <td><?= $h['hold_expires_at'] ? date('M j, Y', strtotime($h['hold_expires_at'])) : '—' ?></td>
                        <td>
                            <span class="badge bg-warning text-dark"><?= (int)$h['days_remaining'] ?> days</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-info">No funds currently in hold period.</div>
        <?php endif; ?>
    </div>

    <!-- Recent Orders & Earnings Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semibold">Recent Orders & Earnings</h6>
            <a href="/pages/supplier/earnings/commission.php" class="btn btn-outline-secondary btn-sm">Full Commission History →</a>
        </div>
        <?php if (empty($recentOrders)): ?>
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>No earnings data yet.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Buyer</th>
                        <th>Gross</th>
                        <th>Commission</th>
                        <th>Net</th>
                        <th>Status</th>
                        <th>Hold Expires</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentOrders as $o):
                    $inHold = ($o['status'] === 'delivered' && empty($o['hold_released_at']));
                ?>
                <tr class="<?= $inHold ? 'table-warning' : '' ?>">
                    <td>
                        <a href="/pages/supplier/orders/detail.php?id=<?= (int)$o['order_id'] ?>">
                            #<?= e($o['order_number'] ?: (string)$o['order_id']) ?>
                        </a>
                    </td>
                    <td><small><?= $o['placed_at'] ? date('M j, Y', strtotime($o['placed_at'])) : '—' ?></small></td>
                    <td><?= e($o['buyer_name'] ?: '—') ?></td>
                    <td>$<?= number_format((float)$o['gross_amount'], 2) ?></td>
                    <td class="text-danger">
                        <?= round((float)$o['final_rate'] * 100, 1) ?>%
                        <small>(−$<?= number_format((float)$o['commission_amount'], 2) ?>)</small>
                    </td>
                    <td class="fw-semibold text-success">$<?= number_format((float)$o['net_amount'], 2) ?></td>
                    <td>
                        <span class="badge bg-<?= match($o['status']) {
                            'completed' => 'success', 'delivered' => 'warning',
                            'shipped' => 'info', 'cancelled' => 'danger', default => 'secondary'
                        } ?>">
                            <?= ucfirst(e($o['status'])) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($inHold && $o['hold_expires_at']): ?>
                        <small class="text-warning fw-semibold">
                            <?= date('M j', strtotime($o['hold_expires_at'])) ?>
                        </small>
                        <?php else: ?>
                        <small class="text-muted">—</small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($earningsTrend)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
const trendData = <?= json_encode($earningsTrend) ?>;
const ctx       = document.getElementById('earningsChart')?.getContext('2d');
if (ctx) {
    new Chart(ctx, {
        type: 'line',
        data: {
            labels:   trendData.map(r => r.period_label),
            datasets: [{
                label:           'Net Earnings',
                data:            trendData.map(r => parseFloat(r.net_earnings)),
                borderColor:     '#198754',
                backgroundColor: 'rgba(25,135,84,0.1)',
                fill:            true,
                tension:         0.3,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { callback: v => '$' + v.toLocaleString() } }
            }
        }
    });
}

// Trend toggle buttons reload page with different date range
document.querySelectorAll('#trendToggle button').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('#trendToggle button').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
    });
});
</script>
<?php endif; ?>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
