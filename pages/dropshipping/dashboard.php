<?php
/**
 * pages/dropshipping/dashboard.php — Dropshipper Dashboard
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db     = getDB();
$userId = $_SESSION['user_id'];

// Stats
$stImported = $db->prepare('SELECT COUNT(*) FROM dropship_imports WHERE user_id = ?');
$stImported->execute([$userId]);
$importedCount = (int)$stImported->fetchColumn();

$stOrders = $db->prepare('SELECT COUNT(*), COALESCE(SUM(profit_amount), 0) FROM dropship_orders WHERE dropshipper_id = ?');
$stOrders->execute([$userId]);
[$totalOrders, $totalProfit] = $stOrders->fetch(\PDO::FETCH_NUM);

$stPending = $db->prepare('SELECT COUNT(*) FROM dropship_orders WHERE dropshipper_id = ? AND status = "pending"');
$stPending->execute([$userId]);
$pendingOrders = (int)$stPending->fetchColumn();

// Imported products list
$stProducts = $db->prepare("
    SELECT di.id, di.markup_pct, di.imported_at,
           p.name, p.images, p.cost_price, p.slug, p.unit,
           s.company_name AS supplier_name,
           ROUND(p.cost_price * (1 + di.markup_pct / 100), 2) AS sell_price
    FROM dropship_imports di
    JOIN products p  ON p.id  = di.product_id
    LEFT JOIN suppliers s ON s.id = p.supplier_id
    WHERE di.user_id = ?
    ORDER BY di.imported_at DESC
    LIMIT 50
");
$stProducts->execute([$userId]);
$imported = $stProducts->fetchAll();

// Recent orders
$stRecentOrders = $db->prepare("
    SELECT do.id, do.status, do.profit_amount, do.created_at, do.routed_at,
           p.name AS product_name,
           o.order_number
    FROM dropship_orders do
    JOIN products p ON p.id = do.product_id
    JOIN orders o   ON o.id = do.order_id
    WHERE do.dropshipper_id = ?
    ORDER BY do.created_at DESC
    LIMIT 10
");
$stRecentOrders->execute([$userId]);
$recentOrders = $stRecentOrders->fetchAll();

// Monthly profit data for chart (last 6 months)
$stChart = $db->prepare("
    SELECT DATE_FORMAT(created_at, '%b') AS month,
           COALESCE(SUM(profit_amount), 0) AS profit
    FROM dropship_orders
    WHERE dropshipper_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY MIN(created_at)
");
$stChart->execute([$userId]);
$chartData = $stChart->fetchAll();

$pageTitle = 'Dropshipping Dashboard';
include __DIR__ . '/../../includes/header.php';
?>

<style>
  :root { --ds-primary: #FF6B35; --ds-secondary: #1B2A4A; }
  .stat-card { border-left: 4px solid var(--ds-primary); }
  .stat-card.secondary { border-left-color: var(--ds-secondary); }
  .stat-icon { width:52px;height:52px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.4rem; }
  .status-badge { font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; }
  .tbl-hover tbody tr:hover { background:#fff8f5; }
</style>

<div class="container-fluid py-4 px-4">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
      <h4 class="fw-bold mb-0" style="color:var(--ds-secondary)">
        <i class="bi bi-speedometer2 me-2" style="color:var(--ds-primary)"></i>Dropshipping Dashboard
      </h4>
      <small class="text-muted">Welcome back, <?= e($_SESSION['user_name'] ?? 'Dropshipper') ?></small>
    </div>
    <a href="/pages/dropshipping/index.php" class="btn text-white" style="background:var(--ds-primary)">
      <i class="bi bi-plus-circle me-1"></i>Browse Catalog
    </a>
  </div>

  <!-- Stat Cards -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm stat-card h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="stat-icon" style="background:#fff3ee">
            <i class="bi bi-box-seam" style="color:var(--ds-primary)"></i>
          </div>
          <div>
            <div class="text-muted small">Imported Products</div>
            <div class="fs-4 fw-bold"><?= number_format($importedCount) ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm stat-card secondary h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="stat-icon" style="background:#eef0f8">
            <i class="bi bi-bag-check" style="color:var(--ds-secondary)"></i>
          </div>
          <div>
            <div class="text-muted small">Total Orders</div>
            <div class="fs-4 fw-bold"><?= number_format($totalOrders) ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm stat-card h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="stat-icon" style="background:#e8f5e9">
            <i class="bi bi-cash-coin" style="color:#2e7d32"></i>
          </div>
          <div>
            <div class="text-muted small">Total Profit</div>
            <div class="fs-4 fw-bold text-success"><?= formatMoney($totalProfit) ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm stat-card secondary h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="stat-icon" style="background:#fff8e1">
            <i class="bi bi-hourglass-split" style="color:#f57f17"></i>
          </div>
          <div>
            <div class="text-muted small">Pending Routing</div>
            <div class="fs-4 fw-bold text-warning"><?= number_format($pendingOrders) ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">

    <!-- Profit Chart -->
    <div class="col-lg-7">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-white border-0 pt-3 pb-0 px-4">
          <h6 class="fw-bold mb-0" style="color:var(--ds-secondary)">
            <i class="bi bi-bar-chart-line me-2" style="color:var(--ds-primary)"></i>Monthly Profit (Last 6 Months)
          </h6>
        </div>
        <div class="card-body">
          <canvas id="profitChart" height="120"></canvas>
        </div>
      </div>
    </div>

    <!-- Sync Status -->
    <div class="col-lg-5">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-white border-0 pt-3 pb-0 px-4">
          <h6 class="fw-bold mb-0" style="color:var(--ds-secondary)">
            <i class="bi bi-arrow-repeat me-2" style="color:var(--ds-primary)"></i>Sync Status
          </h6>
        </div>
        <div class="card-body">
          <ul class="list-group list-group-flush">
            <li class="list-group-item d-flex justify-content-between px-0">
              <span><i class="bi bi-box-seam me-2 text-muted"></i>Product catalog</span>
              <span class="badge bg-success">Synced</span>
            </li>
            <li class="list-group-item d-flex justify-content-between px-0">
              <span><i class="bi bi-tag me-2 text-muted"></i>Pricing rules</span>
              <span class="badge bg-success">Active</span>
            </li>
            <li class="list-group-item d-flex justify-content-between px-0">
              <span><i class="bi bi-currency-dollar me-2 text-muted"></i>Inventory levels</span>
              <span class="badge bg-warning text-dark">Pending</span>
            </li>
            <li class="list-group-item d-flex justify-content-between px-0">
              <span><i class="bi bi-truck me-2 text-muted"></i>Order routing</span>
              <span class="badge <?= $pendingOrders > 0 ? 'bg-warning text-dark' : 'bg-success' ?>">
                <?= $pendingOrders > 0 ? $pendingOrders . ' pending' : 'Up to date' ?>
              </span>
            </li>
          </ul>
          <a href="/pages/dropshipping/settings.php" class="btn btn-sm btn-outline-secondary w-100 mt-3">
            <i class="bi bi-gear me-1"></i>Manage Settings
          </a>
        </div>
      </div>
    </div>

    <!-- Imported Products Table -->
    <div class="col-12">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 pt-3 pb-2 px-4 d-flex justify-content-between align-items-center">
          <h6 class="fw-bold mb-0" style="color:var(--ds-secondary)">
            <i class="bi bi-cloud-download me-2" style="color:var(--ds-primary)"></i>Imported Products
          </h6>
          <a href="/pages/dropshipping/index.php" class="btn btn-sm text-white" style="background:var(--ds-primary)">
            + Add More
          </a>
        </div>
        <div class="card-body p-0">
          <?php if (empty($imported)): ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-inbox display-4"></i>
              <p class="mt-2">No products imported yet.</p>
              <a href="/pages/dropshipping/index.php" class="btn btn-sm text-white" style="background:var(--ds-primary)">Browse Catalog</a>
            </div>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover tbl-hover mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th class="ps-4">Product</th>
                  <th>Supplier</th>
                  <th>Cost</th>
                  <th>Your Price</th>
                  <th>Markup</th>
                  <th>Imported</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($imported as $imp):
                $images = json_decode($imp['images'] ?? '[]', true);
                $img    = !empty($images[0]) ? e(APP_URL . '/' . $images[0]) : 'https://placehold.co/40x40/1B2A4A/white?text=P';
              ?>
                <tr>
                  <td class="ps-4">
                    <div class="d-flex align-items-center gap-2">
                      <img src="<?= $img ?>" width="40" height="40"
                           class="rounded" style="object-fit:cover;" alt="">
                      <a href="/pages/product/detail.php?slug=<?= urlencode($imp['slug']) ?>"
                         class="text-decoration-none text-dark fw-semibold small">
                        <?= e(mb_strimwidth($imp['name'], 0, 50, '…')) ?>
                      </a>
                    </div>
                  </td>
                  <td class="small text-muted"><?= e($imp['supplier_name'] ?? '—') ?></td>
                  <td class="fw-semibold"><?= formatMoney($imp['cost_price']) ?></td>
                  <td class="fw-bold" style="color:var(--ds-primary)"><?= formatMoney($imp['sell_price']) ?></td>
                  <td><span class="badge bg-light text-dark border"><?= $imp['markup_pct'] ?>%</span></td>
                  <td class="text-muted small"><?= date('d M Y', strtotime($imp['imported_at'])) ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Recent Orders -->
    <div class="col-12">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 pt-3 pb-2 px-4">
          <h6 class="fw-bold mb-0" style="color:var(--ds-secondary)">
            <i class="bi bi-receipt me-2" style="color:var(--ds-primary)"></i>Recent Dropship Orders
          </h6>
        </div>
        <div class="card-body p-0">
          <?php if (empty($recentOrders)): ?>
            <div class="text-center py-4 text-muted"><i class="bi bi-inbox"></i> No orders yet.</div>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th class="ps-4">Order #</th>
                  <th>Product</th>
                  <th>Profit</th>
                  <th>Status</th>
                  <th>Date</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($recentOrders as $ord): ?>
                <tr>
                  <td class="ps-4 fw-semibold small"><?= e($ord['order_number']) ?></td>
                  <td class="small"><?= e(mb_strimwidth($ord['product_name'], 0, 40, '…')) ?></td>
                  <td class="text-success fw-semibold"><?= formatMoney($ord['profit_amount'] ?? 0) ?></td>
                  <td>
                    <?php
                    $sc = match($ord['status']) {
                        'pending'            => 'warning text-dark',
                        'routed_to_supplier' => 'info text-dark',
                        'shipped'            => 'primary',
                        'delivered'          => 'success',
                        'cancelled'          => 'danger',
                        default              => 'secondary',
                    };
                    ?>
                    <span class="badge bg-<?= $sc ?> status-badge"><?= e(str_replace('_', ' ', $ord['status'])) ?></span>
                  </td>
                  <td class="text-muted small"><?= date('d M Y', strtotime($ord['created_at'])) ?></td>
                  <td>
                    <?php if ($ord['status'] === 'pending'): ?>
                    <button class="btn btn-xs btn-outline-primary btn-sm route-btn"
                            data-order-id="<?= $ord['id'] ?>">
                      <i class="bi bi-send me-1"></i>Route
                    </button>
                    <?php else: ?>
                    <span class="text-muted small">—</span>
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
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
const CSRF = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;

// Profit chart
const chartLabels  = <?= json_encode(array_column($chartData, 'month')) ?>;
const chartProfits = <?= json_encode(array_map(fn($r) => (float)$r['profit'], $chartData)) ?>;

new Chart(document.getElementById('profitChart'), {
  type: 'bar',
  data: {
    labels: chartLabels.length ? chartLabels : ['No data'],
    datasets: [{
      label: 'Profit ($)',
      data: chartProfits.length ? chartProfits : [0],
      backgroundColor: '#FF6B35',
      borderRadius: 6,
    }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { callback: v => '$' + v } } }
  }
});

// Route order buttons
document.querySelectorAll('.route-btn').forEach(btn => {
  btn.addEventListener('click', async () => {
    if (!confirm('Route this order to the supplier?')) return;
    btn.disabled = true;

    const body = new URLSearchParams({ csrf_token: CSRF, order_id: btn.dataset.orderId });
    const res  = await fetch('/api/dropshipping.php?action=route', { method: 'POST', body });
    const data = await res.json();

    if (data.success) {
      btn.closest('tr').querySelector('td:nth-child(4)').innerHTML =
        '<span class="badge bg-info text-dark status-badge">routed to supplier</span>';
      btn.remove();
    } else {
      alert(data.error || 'Failed to route order');
      btn.disabled = false;
    }
  });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
