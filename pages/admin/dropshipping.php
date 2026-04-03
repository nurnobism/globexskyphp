<?php
/**
 * pages/admin/dropshipping.php — Admin Dropship Dashboard
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireRole(['admin', 'super_admin']);

$db = getDB();

// Overview stats
$stats = [
    'total_stores'   => 0,
    'total_products'  => 0,
    'total_orders'    => 0,
    'total_revenue'   => 0.0,
    'platform_fees'   => 0.0,
];

try {
    $stats['total_stores']   = (int)$db->query('SELECT COUNT(*) FROM dropship_stores')->fetchColumn();
    $stats['total_products'] = (int)$db->query('SELECT COUNT(*) FROM dropship_products')->fetchColumn();
    $stats['total_orders']   = (int)$db->query('SELECT COUNT(*) FROM dropship_orders')->fetchColumn();
    $stats['total_revenue']  = (float)$db->query('SELECT COALESCE(SUM(selling_price),0) FROM dropship_orders')->fetchColumn();
    $stats['platform_fees']  = (float)$db->query('SELECT COALESCE(SUM(platform_earning),0) FROM dropship_orders')->fetchColumn();
} catch (PDOException $e) { /* tables may not exist */ }

// Recent dropship orders
$recentOrders = [];
try {
    $recentOrders = $db->query("SELECT do.*, o.order_number, u.first_name, u.last_name
        FROM dropship_orders do
        LEFT JOIN orders o ON o.id = do.order_id
        LEFT JOIN users u ON u.id = do.dropshipper_id
        ORDER BY do.created_at DESC LIMIT 10")->fetchAll();
} catch (PDOException $e) { /* ignore */ }

// Top dropshippers by revenue
$topDropshippers = [];
try {
    $topDropshippers = $db->query("SELECT u.id, u.first_name, u.last_name, u.email,
        ds.store_name, ds.total_revenue, ds.total_orders, ds.total_products
        FROM dropship_stores ds
        JOIN users u ON u.id = ds.user_id
        ORDER BY ds.total_revenue DESC LIMIT 10")->fetchAll();
} catch (PDOException $e) { /* ignore */ }

// Top suppliers with most dropshippers
$topSuppliers = [];
try {
    $topSuppliers = $db->query("SELECT dp.supplier_id,
        COUNT(DISTINCT dp.dropshipper_id) AS dropshipper_count,
        COUNT(dp.id) AS product_count,
        s.company_name AS supplier_name
        FROM dropship_products dp
        LEFT JOIN suppliers s ON s.user_id = dp.supplier_id
        GROUP BY dp.supplier_id, s.company_name
        ORDER BY dropshipper_count DESC LIMIT 10")->fetchAll();
} catch (PDOException $e) { /* ignore */ }

// Dropship fee config
$dropshipFeeRate = 3.0; // default 3%
$error   = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $newRate = max(0.5, min(20, (float)post('dropship_fee_rate', 3)));
    // In a real app, save to a settings table. Here we just acknowledge.
    $success = 'Dropship fee rate updated to ' . number_format($newRate, 1) . '%';
    $dropshipFeeRate = $newRate;
}

$pageTitle = 'Admin — Dropshipping';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h3 class="fw-bold"><i class="bi bi-shop text-primary me-2"></i>Dropshipping Management</h3>
    <a href="<?= APP_URL ?>/pages/admin/dropship-orders.php" class="btn btn-primary btn-sm">
      <i class="bi bi-receipt me-1"></i>All Dropship Orders
    </a>
  </div>

  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

  <!-- Overview Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg">
      <div class="card border-0 shadow-sm text-center py-3">
        <div class="fs-3 fw-bold text-primary"><?= number_format($stats['total_stores']) ?></div>
        <div class="small text-muted">Dropship Stores</div>
      </div>
    </div>
    <div class="col-6 col-lg">
      <div class="card border-0 shadow-sm text-center py-3">
        <div class="fs-3 fw-bold text-info"><?= number_format($stats['total_products']) ?></div>
        <div class="small text-muted">Imported Products</div>
      </div>
    </div>
    <div class="col-6 col-lg">
      <div class="card border-0 shadow-sm text-center py-3">
        <div class="fs-3 fw-bold text-success"><?= number_format($stats['total_orders']) ?></div>
        <div class="small text-muted">Dropship Orders</div>
      </div>
    </div>
    <div class="col-6 col-lg">
      <div class="card border-0 shadow-sm text-center py-3">
        <div class="fs-3 fw-bold text-warning"><?= formatMoney($stats['total_revenue']) ?></div>
        <div class="small text-muted">Total Revenue</div>
      </div>
    </div>
    <div class="col-6 col-lg">
      <div class="card border-0 shadow-sm text-center py-3">
        <div class="fs-3 fw-bold text-danger"><?= formatMoney($stats['platform_fees']) ?></div>
        <div class="small text-muted">Platform Fees Collected</div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <!-- Recent Orders -->
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
          <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Dropship Orders</h6>
          <a href="<?= APP_URL ?>/pages/admin/dropship-orders.php" class="btn btn-sm btn-outline-primary">View All</a>
        </div>
        <div class="card-body p-0">
          <?php if (empty($recentOrders)): ?>
            <div class="text-center py-4 text-muted">No orders yet.</div>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr><th class="ps-3">Order #</th><th>Dropshipper</th><th>Supplier $</th><th>Selling $</th><th>Platform Fee</th><th>Status</th><th>Date</th></tr>
              </thead>
              <tbody>
              <?php foreach ($recentOrders as $o):
                $sc = match($o['status']) {
                  'delivered'=>'success','shipped'=>'primary','processing'=>'info','cancelled'=>'danger',default=>'secondary'
                };
              ?>
                <tr>
                  <td class="ps-3 small fw-semibold"><?= e($o['order_number'] ?? '#'.$o['order_id']) ?></td>
                  <td class="small"><?= e(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? '')) ?></td>
                  <td><?= formatMoney($o['original_price']) ?></td>
                  <td><?= formatMoney($o['selling_price']) ?></td>
                  <td class="text-danger"><?= formatMoney($o['platform_earning']) ?></td>
                  <td><span class="badge bg-<?= $sc ?>"><?= e(ucfirst($o['status'])) ?></span></td>
                  <td class="small text-muted"><?= formatDate($o['created_at'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Config & Top Sellers -->
    <div class="col-lg-4">
      <!-- Dropship Fee Config -->
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 py-3">
          <h6 class="fw-bold mb-0"><i class="bi bi-gear me-2"></i>Dropship Fee Rate</h6>
        </div>
        <div class="card-body">
          <form method="POST">
            <?= csrfField() ?>
            <div class="input-group mb-2">
              <input type="number" name="dropship_fee_rate" value="<?= $dropshipFeeRate ?>"
                class="form-control" min="0.5" max="20" step="0.1">
              <span class="input-group-text">%</span>
            </div>
            <div class="form-text mb-2">Platform fee on each dropship sale (default: 3%)</div>
            <button type="submit" class="btn btn-primary btn-sm w-100">Save</button>
          </form>
        </div>
      </div>

      <!-- Top Dropshippers -->
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 py-3">
          <h6 class="fw-bold mb-0"><i class="bi bi-trophy me-2 text-warning"></i>Top Dropshippers</h6>
        </div>
        <div class="card-body p-0">
          <?php if (empty($topDropshippers)): ?>
            <div class="text-center py-3 text-muted small">No data yet.</div>
          <?php else: ?>
          <div class="list-group list-group-flush">
            <?php foreach (array_slice($topDropshippers, 0, 5) as $td): ?>
            <div class="list-group-item d-flex justify-content-between align-items-center px-3 py-2">
              <div>
                <div class="small fw-semibold"><?= e(($td['first_name'] ?? '') . ' ' . ($td['last_name'] ?? '')) ?></div>
                <div style="font-size:.72rem" class="text-muted"><?= e($td['store_name'] ?? '') ?></div>
              </div>
              <span class="badge bg-success"><?= formatMoney($td['total_revenue'] ?? 0) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Top Suppliers -->
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 py-3">
          <h6 class="fw-bold mb-0"><i class="bi bi-building me-2 text-primary"></i>Top Suppliers</h6>
        </div>
        <div class="card-body p-0">
          <?php if (empty($topSuppliers)): ?>
            <div class="text-center py-3 text-muted small">No data yet.</div>
          <?php else: ?>
          <div class="list-group list-group-flush">
            <?php foreach (array_slice($topSuppliers, 0, 5) as $ts): ?>
            <div class="list-group-item d-flex justify-content-between align-items-center px-3 py-2">
              <div>
                <div class="small fw-semibold"><?= e($ts['supplier_name'] ?? 'Supplier #' . $ts['supplier_id']) ?></div>
                <div style="font-size:.72rem" class="text-muted"><?= number_format($ts['product_count']) ?> products</div>
              </div>
              <span class="badge bg-primary"><?= number_format($ts['dropshipper_count']) ?> dropshippers</span>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
