<?php
/**
 * pages/dropshipping/index.php — Dropshipping Dashboard
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();
require_once __DIR__ . '/../../includes/dropshipping.php';

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

// Load or create store
$store = null;
try {
    $storeStmt = $db->prepare('SELECT * FROM dropship_stores WHERE user_id = ? LIMIT 1');
    $storeStmt->execute([$userId]);
    $store = $storeStmt->fetch() ?: null;
} catch (PDOException $e) { /* ignore */ }

// Stats
$importedCount = 0; $activeCount = 0; $totalOrders = 0;
$totalEarnings = 0.0; $pendingEarnings = 0.0;

if ($store) {
    $storeId = (int)$store['id'];
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM dropship_products WHERE store_id = ?');
        $stmt->execute([$storeId]);
        $importedCount = (int)$stmt->fetchColumn();

        $stmt = $db->prepare('SELECT COUNT(*) FROM dropship_products WHERE store_id = ? AND is_active = 1');
        $stmt->execute([$storeId]);
        $activeCount = (int)$stmt->fetchColumn();

        $stmt = $db->prepare('SELECT COUNT(*) FROM dropship_orders WHERE store_id = ?');
        $stmt->execute([$storeId]);
        $totalOrders = (int)$stmt->fetchColumn();
    } catch (PDOException $e) { /* ignore */ }
}

// Earnings
try {
    $eStmt = $db->prepare("SELECT COALESCE(SUM(net_amount),0) FROM dropship_earnings WHERE dropshipper_id = ?");
    $eStmt->execute([$userId]);
    $totalEarnings = (float)$eStmt->fetchColumn();

    $eStmt = $db->prepare("SELECT COALESCE(SUM(net_amount),0) FROM dropship_earnings WHERE dropshipper_id = ? AND status='pending'");
    $eStmt->execute([$userId]);
    $pendingEarnings = (float)$eStmt->fetchColumn();
} catch (PDOException $e) { /* ignore */ }

// Conversion rate
$conversionRate = 0.0;
if ($importedCount > 0 && $totalOrders > 0) {
    $conversionRate = round($totalOrders / $importedCount * 100, 1);
}

// Recent orders (last 10)
$recentOrders = [];
try {
    $stmt = $db->prepare('SELECT do.*, o.order_number FROM dropship_orders do
        LEFT JOIN orders o ON o.id = do.order_id
        WHERE do.dropshipper_id = ? ORDER BY do.created_at DESC LIMIT 10');
    $stmt->execute([$userId]);
    $recentOrders = $stmt->fetchAll();
} catch (PDOException $e) { /* ignore */ }

// Earnings chart data (last 30 days)
$chartLabels = []; $chartData = [];
try {
    $cStmt = $db->prepare("SELECT DATE(created_at) AS day, COALESCE(SUM(net_amount),0) AS earnings
        FROM dropship_earnings WHERE dropshipper_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at) ORDER BY day");
    $cStmt->execute([$userId]);
    foreach ($cStmt->fetchAll() as $row) {
        $chartLabels[] = date('d M', strtotime($row['day']));
        $chartData[]   = (float)$row['earnings'];
    }
} catch (PDOException $e) { /* ignore */ }

// Plan limits
$planLimits = checkDropshipPlanLimits($userId);

$pageTitle = 'Dropshipping Dashboard';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4 px-4">
  <!-- Hero / Header -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h4 class="fw-bold mb-0 text-primary"><i class="bi bi-shop me-2"></i>Dropshipping Dashboard</h4>
      <small class="text-muted">Start Dropshipping with GlobexSky</small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= APP_URL ?>/pages/dropshipping/products.php" class="btn btn-primary btn-sm"><i class="bi bi-grid me-1"></i>Browse Catalog</a>
      <a href="<?= APP_URL ?>/pages/dropshipping/my-products.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-box-seam me-1"></i>My Products</a>
      <a href="<?= APP_URL ?>/pages/dropshipping/orders.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-receipt me-1"></i>Orders</a>
      <a href="<?= APP_URL ?>/pages/dropshipping/store.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-gear me-1"></i>Store Settings</a>
    </div>
  </div>

  <?php if (!$store): ?>
  <!-- Create Store CTA -->
  <div class="alert alert-info d-flex align-items-center gap-3 mb-4">
    <i class="bi bi-shop fs-3 text-primary"></i>
    <div>
      <strong>Create Your Dropshipping Store</strong><br>
      Set up your store to start selling products without holding inventory.
      <a href="<?= APP_URL ?>/pages/dropshipping/store.php" class="btn btn-primary btn-sm ms-3">Create Store</a>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!$planLimits['allowed']): ?>
  <div class="alert alert-warning mb-4">
    <i class="bi bi-lock me-2"></i>
    <strong>Upgrade Required:</strong> Dropshipping is available on Pro and Enterprise plans.
    <a href="<?= APP_URL ?>/pages/supplier/plans.php" class="btn btn-warning btn-sm ms-2">Upgrade Plan</a>
  </div>
  <?php endif; ?>

  <!-- Plan Limit Indicator -->
  <?php if ($planLimits['allowed'] && $planLimits['max_count'] !== 'Unlimited'): ?>
  <div class="alert alert-light border mb-4">
    <i class="bi bi-bar-chart me-2 text-primary"></i>
    <strong><?= $planLimits['current_count'] ?>/<?= $planLimits['max_count'] ?> products imported</strong>
    (<?= ucfirst($planLimits['plan']) ?> Plan)
    <?php if ($planLimits['current_count'] >= $planLimits['max_count'] * 0.8): ?>
      <span class="text-warning ms-2"><i class="bi bi-exclamation-triangle"></i> Near limit</span>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Stat Cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-xl-2-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center">
          <div class="fs-3 fw-bold text-primary"><?= number_format($importedCount) ?></div>
          <div class="small text-muted">Products Imported</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-2-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center">
          <div class="fs-3 fw-bold text-success"><?= number_format($activeCount) ?></div>
          <div class="small text-muted">Active Products</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-2-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center">
          <div class="fs-3 fw-bold text-info"><?= number_format($totalOrders) ?></div>
          <div class="small text-muted">Total Orders</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-2-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center">
          <div class="fs-3 fw-bold text-warning"><?= formatMoney($totalEarnings) ?></div>
          <div class="small text-muted">Total Earnings</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-2-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center">
          <div class="fs-3 fw-bold"><?= $conversionRate ?>%</div>
          <div class="small text-muted">Conversion Rate</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <!-- Earnings Chart -->
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 py-3">
          <h6 class="fw-bold mb-0"><i class="bi bi-graph-up me-2 text-primary"></i>Earnings (Last 30 Days)</h6>
        </div>
        <div class="card-body">
          <canvas id="earningsChart" height="100"></canvas>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 py-3">
          <h6 class="fw-bold mb-0"><i class="bi bi-lightning me-2 text-warning"></i>Quick Actions</h6>
        </div>
        <div class="card-body d-grid gap-2">
          <a href="<?= APP_URL ?>/pages/dropshipping/products.php" class="btn btn-primary">
            <i class="bi bi-search me-2"></i>Browse Catalog
          </a>
          <a href="<?= APP_URL ?>/pages/dropshipping/my-products.php" class="btn btn-outline-primary">
            <i class="bi bi-box-seam me-2"></i>Manage Products
          </a>
          <a href="<?= APP_URL ?>/pages/dropshipping/orders.php" class="btn btn-outline-secondary">
            <i class="bi bi-receipt me-2"></i>View Orders
          </a>
          <a href="<?= APP_URL ?>/pages/dropshipping/earnings.php" class="btn btn-outline-success">
            <i class="bi bi-cash-coin me-2"></i>Earnings Dashboard
          </a>
          <a href="<?= APP_URL ?>/pages/dropshipping/store.php" class="btn btn-outline-secondary">
            <i class="bi bi-gear me-2"></i>Store Settings
          </a>
        </div>
      </div>
    </div>

    <!-- Recent Orders Table -->
    <div class="col-12">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
          <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Orders</h6>
          <a href="<?= APP_URL ?>/pages/dropshipping/orders.php" class="btn btn-sm btn-outline-primary">View All</a>
        </div>
        <div class="card-body p-0">
          <?php if (empty($recentOrders)): ?>
            <div class="text-center py-4 text-muted"><i class="bi bi-inbox fs-2"></i><p class="mt-2">No orders yet.</p></div>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr><th class="ps-3">Order #</th><th>Supplier Price</th><th>Selling Price</th><th>Profit</th><th>Status</th><th>Date</th></tr>
              </thead>
              <tbody>
              <?php foreach ($recentOrders as $ord):
                $statusColor = match($ord['status']) {
                  'delivered' => 'success', 'shipped' => 'primary', 'processing' => 'info',
                  'routed' => 'info', 'cancelled' => 'danger', 'refunded' => 'warning', default => 'secondary'
                };
              ?>
                <tr>
                  <td class="ps-3 fw-semibold small"><?= e($ord['order_number'] ?? '#' . $ord['order_id']) ?></td>
                  <td><?= formatMoney($ord['original_price']) ?></td>
                  <td><?= formatMoney($ord['selling_price']) ?></td>
                  <td class="text-success fw-semibold"><?= formatMoney($ord['dropshipper_earning']) ?></td>
                  <td><span class="badge bg-<?= $statusColor ?>"><?= e(ucfirst($ord['status'])) ?></span></td>
                  <td class="text-muted small"><?= date('d M Y', strtotime($ord['created_at'])) ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
const labels = <?= json_encode($chartLabels) ?>;
const data   = <?= json_encode($chartData) ?>;
new Chart(document.getElementById('earningsChart'), {
  type: 'line',
  data: {
    labels: labels.length ? labels : ['No data'],
    datasets: [{ label: 'Earnings ($)', data: data.length ? data : [0],
      borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,0.08)',
      fill: true, tension: 0.3 }]
  },
  options: { plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { callback: v => '$' + v } } } }
});
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
