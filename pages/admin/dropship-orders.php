<?php
/**
 * pages/admin/dropship-orders.php — Admin All Dropship Orders
 * Full transparency: supplier price, markup, dropshipper earning, platform fee
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireRole(['admin', 'super_admin']);

$db = getDB();

// Filters
$statusFilter      = get('status', 'all');
$dateFrom          = get('date_from', '');
$dateTo            = get('date_to', '');
$supplierFilter    = get('supplier', '');
$dropshipperFilter = get('dropshipper', '');
$page    = max(1, (int)get('page', 1));
$perPage = 30;
$offset  = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if ($statusFilter !== 'all' && in_array($statusFilter, ['pending','routed','processing','shipped','delivered','cancelled','refunded'])) {
    $where[]  = 'do.status = ?';
    $params[] = $statusFilter;
}
if ($dateFrom) {
    $where[]  = 'do.created_at >= ?';
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo) {
    $where[]  = 'do.created_at <= ?';
    $params[] = $dateTo . ' 23:59:59';
}
if ($supplierFilter) {
    $where[]  = 'do.supplier_id = ?';
    $params[] = (int)$supplierFilter;
}
if ($dropshipperFilter) {
    $where[]  = 'do.dropshipper_id = ?';
    $params[] = (int)$dropshipperFilter;
}

$whereClause = implode(' AND ', $where);

$orders      = [];
$totalOrders = 0;

// CSV Export
$exportCsv = get('export') === 'csv';

try {
    $countStmt = $db->prepare("SELECT COUNT(*) FROM dropship_orders do WHERE $whereClause");
    $countStmt->execute($params);
    $totalOrders = (int)$countStmt->fetchColumn();

    if ($exportCsv) {
        // Export all matching (no pagination)
        $stmt = $db->prepare("SELECT do.*, o.order_number, 
            ud.first_name AS ds_first, ud.last_name AS ds_last,
            us.first_name AS sup_first, us.last_name AS sup_last
            FROM dropship_orders do
            LEFT JOIN orders o ON o.id = do.order_id
            LEFT JOIN users ud ON ud.id = do.dropshipper_id
            LEFT JOIN users us ON us.id = do.supplier_id
            WHERE $whereClause ORDER BY do.created_at DESC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="dropship_orders_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Order #','Dropshipper','Supplier','Supplier Price','Selling Price','Markup','Platform Fee','Dropshipper Earning','Status','Date']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['order_number'] ?? $r['order_id'],
                ($r['ds_first'] ?? '') . ' ' . ($r['ds_last'] ?? ''),
                ($r['sup_first'] ?? '') . ' ' . ($r['sup_last'] ?? ''),
                $r['original_price'], $r['selling_price'], $r['markup_amount'],
                $r['platform_earning'], $r['dropshipper_earning'],
                $r['status'], $r['created_at'],
            ]);
        }
        fclose($out);
        exit;
    }

    $stmt = $db->prepare("SELECT do.*, o.order_number,
        ud.first_name AS ds_first, ud.last_name AS ds_last, ud.email AS ds_email,
        us.first_name AS sup_first, us.last_name AS sup_last
        FROM dropship_orders do
        LEFT JOIN orders o ON o.id = do.order_id
        LEFT JOIN users ud ON ud.id = do.dropshipper_id
        LEFT JOIN users us ON us.id = do.supplier_id
        WHERE $whereClause
        ORDER BY do.created_at DESC
        LIMIT ? OFFSET ?");
    $stmt->execute(array_merge($params, [$perPage, $offset]));
    $orders = $stmt->fetchAll();
} catch (PDOException $e) { /* ignore */ }

$totalPages = (int)ceil($totalOrders / $perPage);

$pageTitle = 'Admin — All Dropship Orders';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
      <h3 class="fw-bold"><i class="bi bi-receipt text-primary me-2"></i>All Dropship Orders</h3>
      <small class="text-muted"><?= number_format($totalOrders) ?> total orders</small>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= APP_URL ?>/pages/admin/dropshipping.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Dashboard
      </a>
      <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>" class="btn btn-outline-success btn-sm">
        <i class="bi bi-download me-1"></i>Export CSV
      </a>
    </div>
  </div>

  <!-- Filters -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-2">
          <label class="form-label small mb-0">Status</label>
          <select name="status" class="form-select form-select-sm">
            <option value="all">All</option>
            <?php foreach (['pending','routed','processing','shipped','delivered','cancelled','refunded'] as $s): ?>
            <option value="<?= $s ?>" <?= $statusFilter===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small mb-0">From</label>
          <input type="date" name="date_from" value="<?= e($dateFrom) ?>" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
          <label class="form-label small mb-0">To</label>
          <input type="date" name="date_to" value="<?= e($dateTo) ?>" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
          <label class="form-label small mb-0">Supplier ID</label>
          <input type="number" name="supplier" value="<?= e($supplierFilter) ?>" class="form-control form-control-sm" placeholder="ID">
        </div>
        <div class="col-md-2">
          <label class="form-label small mb-0">Dropshipper ID</label>
          <input type="number" name="dropshipper" value="<?= e($dropshipperFilter) ?>" class="form-control form-control-sm" placeholder="ID">
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Orders Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <?php if (empty($orders)): ?>
        <div class="text-center py-5 text-muted"><i class="bi bi-inbox display-3"></i><p class="mt-3">No orders found.</p></div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Order #</th>
              <th>Dropshipper</th>
              <th>Supplier Price</th>
              <th>Selling Price</th>
              <th>Markup</th>
              <th>DS Earning</th>
              <th>Platform Fee</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($orders as $o):
            $sc = match($o['status']) {
              'delivered'=>'success','shipped'=>'primary','processing'=>'info','cancelled'=>'danger',default=>'secondary'
            };
          ?>
            <tr>
              <td class="ps-3 fw-semibold"><?= e($o['order_number'] ?? '#'.$o['order_id']) ?></td>
              <td>
                <div><?= e(($o['ds_first'] ?? '') . ' ' . ($o['ds_last'] ?? '')) ?></div>
                <div class="text-muted" style="font-size:.72rem"><?= e($o['ds_email'] ?? '') ?></div>
              </td>
              <td><?= formatMoney($o['original_price']) ?></td>
              <td class="fw-bold"><?= formatMoney($o['selling_price']) ?></td>
              <td class="text-info"><?= formatMoney($o['markup_amount']) ?></td>
              <td class="text-success"><?= formatMoney($o['dropshipper_earning']) ?></td>
              <td class="text-danger"><?= formatMoney($o['platform_earning']) ?></td>
              <td><span class="badge bg-<?= $sc ?>"><?= e(ucfirst($o['status'])) ?></span></td>
              <td class="text-muted"><?= formatDate($o['created_at'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <nav class="mt-3">
    <ul class="pagination justify-content-center">
      <?php for ($i = 1; $i <= min($totalPages, 15); $i++):
        $qs = http_build_query(array_merge($_GET, ['page' => $i]));
      ?>
      <li class="page-item <?= $i === $page ? 'active' : '' ?>">
        <a class="page-link" href="?<?= $qs ?>"><?= $i ?></a>
      </li>
      <?php endfor; ?>
    </ul>
  </nav>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
