<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();

$db = getDB();

$stats = ['total' => 0, 'in_transit' => 0, 'delivered_today' => 0, 'pending' => 0, 'failed' => 0];
$recentShipments = [];
$chartByMethod   = [];
$chartDaily      = [];

try {
    $stmt = $db->query("SELECT COUNT(*) FROM parcel_shipments");
    $stats['total'] = (int)$stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM parcel_shipments WHERE status='in_transit'");
    $stats['in_transit'] = (int)$stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM parcel_shipments WHERE status='delivered' AND DATE(actual_delivery)=CURDATE()");
    $stats['delivered_today'] = (int)$stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM parcel_shipments WHERE status='pending'");
    $stats['pending'] = (int)$stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM parcel_shipments WHERE status='failed'");
    $stats['failed'] = (int)$stmt->fetchColumn();

    $stmt = $db->query(
        "SELECT ps.*, u.first_name, u.last_name, u.email
         FROM parcel_shipments ps
         LEFT JOIN users u ON u.id = ps.user_id
         ORDER BY ps.created_at DESC LIMIT 10"
    );
    $recentShipments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->query(
        "SELECT shipping_method, COUNT(*) AS cnt FROM parcel_shipments GROUP BY shipping_method"
    );
    $chartByMethod = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->query(
        "SELECT DATE(created_at) AS day, COUNT(*) AS cnt
         FROM parcel_shipments
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         GROUP BY DATE(created_at)
         ORDER BY day ASC"
    );
    $chartDaily = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Tables may not exist yet — gracefully degrade
}

$statusColors = [
    'pending'          => 'warning',
    'processing'       => 'info',
    'picked_up'        => 'info',
    'in_transit'       => 'primary',
    'out_for_delivery' => 'primary',
    'delivered'        => 'success',
    'failed'           => 'danger',
    'cancelled'        => 'secondary',
    'returned'         => 'dark',
];

$pageTitle = 'Logistics Overview';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-truck text-primary me-2"></i>Logistics Overview</h3>
        <a href="/pages/admin/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Dashboard
        </a>
    </div>

    <!-- Quick Nav -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="/pages/admin/logistics/index.php" class="btn btn-primary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Overview</a>
        <a href="/pages/admin/logistics/parcels.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-seam me-1"></i>Parcels</a>
        <a href="/pages/admin/logistics/carriers.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-person-badge me-1"></i>Carriers</a>
        <a href="/pages/admin/logistics/carry-requests.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left-right me-1"></i>Carry Requests</a>
        <a href="/pages/admin/logistics/rates.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-currency-dollar me-1"></i>Rates</a>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <div class="display-6 fw-bold text-primary"><?= $stats['total'] ?></div>
                    <div class="small text-muted mt-1">Total Shipments</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <div class="display-6 fw-bold text-info"><?= $stats['in_transit'] ?></div>
                    <div class="small text-muted mt-1">In Transit</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <div class="display-6 fw-bold text-success"><?= $stats['delivered_today'] ?></div>
                    <div class="small text-muted mt-1">Delivered Today</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <div class="display-6 fw-bold text-warning"><?= $stats['pending'] ?></div>
                    <div class="small text-muted mt-1">Pending</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <div class="display-6 fw-bold text-danger"><?= $stats['failed'] ?></div>
                    <div class="small text-muted mt-1">Failed</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Shipments by Method</div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="pieChart" style="max-height:220px"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Daily Shipments — Last 30 Days</div>
                <div class="card-body">
                    <canvas id="lineChart" style="max-height:220px"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Shipments -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Recent Shipments</span>
            <a href="/pages/admin/logistics/parcels.php" class="btn btn-sm btn-outline-primary">View All</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Tracking #</th>
                        <th>Sender</th>
                        <th>Route</th>
                        <th>Method</th>
                        <th>Weight</th>
                        <th>Cost</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($recentShipments)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No shipments found.</td></tr>
                <?php endif; ?>
                <?php foreach ($recentShipments as $s): ?>
                <tr>
                    <td><strong><?= e($s['tracking_number']) ?></strong></td>
                    <td>
                        <div><?= e(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? '')) ?></div>
                        <small class="text-muted"><?= e($s['email'] ?? '') ?></small>
                    </td>
                    <td class="small">
                        <?= e($s['sender_city'] ?? '?') ?> (<?= e($s['sender_country'] ?? '') ?>)
                        <i class="bi bi-arrow-right"></i>
                        <?= e($s['receiver_city'] ?? '?') ?> (<?= e($s['receiver_country'] ?? '') ?>)
                    </td>
                    <td><?= e(ucfirst($s['shipping_method'] ?? '—')) ?></td>
                    <td><?= isset($s['weight']) ? e($s['weight']) . ' kg' : '—' ?></td>
                    <td><?= isset($s['shipping_cost']) ? formatMoney($s['shipping_cost']) : '—' ?></td>
                    <td>
                        <span class="badge bg-<?= $statusColors[$s['status']] ?? 'secondary' ?>">
                            <?= e(ucfirst(str_replace('_', ' ', $s['status']))) ?>
                        </span>
                    </td>
                    <td><?= formatDate($s['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    var pieData = <?= json_encode($chartByMethod) ?>;
    var dailyData = <?= json_encode($chartDaily) ?>;

    // Pie chart
    var pieLabels = pieData.map(function(r){ return r.shipping_method || 'Unknown'; });
    var pieCounts = pieData.map(function(r){ return r.cnt; });
    var pieColors = ['#0d6efd','#198754','#ffc107','#dc3545','#0dcaf0'];
    new Chart(document.getElementById('pieChart'), {
        type: 'doughnut',
        data: { labels: pieLabels, datasets: [{ data: pieCounts, backgroundColor: pieColors }] },
        options: { plugins: { legend: { position: 'bottom' } }, maintainAspectRatio: true }
    });

    // Line chart
    var lineLabels = dailyData.map(function(r){ return r.day; });
    var lineCounts = dailyData.map(function(r){ return r.cnt; });
    new Chart(document.getElementById('lineChart'), {
        type: 'line',
        data: {
            labels: lineLabels,
            datasets: [{
                label: 'Shipments',
                data: lineCounts,
                fill: true,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,0.1)',
                tension: 0.3
            }]
        },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
})();
</script>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
