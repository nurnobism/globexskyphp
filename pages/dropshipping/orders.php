<?php
/**
 * pages/dropshipping/orders.php — Dropship Orders
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();
require_once __DIR__ . '/../../includes/dropshipping.php';

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

$statusFilter = get('status', 'all');
$validStatuses = ['all','pending','processing','routed','shipped','delivered','cancelled','refunded'];
if (!in_array($statusFilter, $validStatuses)) $statusFilter = 'all';

$dateFrom = get('date_from', '');
$dateTo   = get('date_to', '');
$page     = max(1, (int)get('page', 1));
$perPage  = 20;
$offset   = ($page - 1) * $perPage;

// Build WHERE and params
$where  = ['do.dropshipper_id = ?'];
$params = [$userId];

if ($statusFilter !== 'all') {
    $where[]  = 'do.status = ?';
    $params[] = $statusFilter;
}
if ($dateFrom) {
    $where[]  = 'DATE(do.created_at) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[]  = 'DATE(do.created_at) <= ?';
    $params[] = $dateTo;
}

$whereClause = implode(' AND ', $where);

// CSV export
if (get('export') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="dropship-orders-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Order #', 'Customer', 'Supplier Price', 'Selling Price', 'Profit', 'Status', 'Date']);
    try {
        $exportStmt = $db->prepare("
            SELECT do.*, o.order_number, u.first_name, u.last_name
            FROM dropship_orders do
            LEFT JOIN orders o ON o.id = do.order_id
            LEFT JOIN users u ON u.id = do.customer_id
            WHERE $whereClause ORDER BY do.created_at DESC
        ");
        $exportStmt->execute($params);
        foreach ($exportStmt->fetchAll() as $row) {
            fputcsv($out, [
                $row['order_number'] ?? ('#' . $row['order_id']),
                trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                number_format((float)$row['original_price'], 2),
                number_format((float)$row['selling_price'], 2),
                number_format((float)$row['dropshipper_earning'], 2),
                $row['status'],
                date('Y-m-d', strtotime($row['created_at'])),
            ]);
        }
    } catch (PDOException $e) { /* ignore */ }
    fclose($out);
    exit;
}

// Stat counts
$statCounts = ['total' => 0, 'pending' => 0, 'processing' => 0, 'delivered' => 0];
try {
    $sStmt = $db->prepare('SELECT status, COUNT(*) AS cnt FROM dropship_orders WHERE dropshipper_id = ? GROUP BY status');
    $sStmt->execute([$userId]);
    foreach ($sStmt->fetchAll() as $row) {
        $statCounts['total'] += (int)$row['cnt'];
        if (isset($statCounts[$row['status']])) {
            $statCounts[$row['status']] = (int)$row['cnt'];
        }
    }
} catch (PDOException $e) { /* ignore */ }

// Count with filters
$totalFiltered = 0;
try {
    $cStmt = $db->prepare("SELECT COUNT(*) FROM dropship_orders do WHERE $whereClause");
    $cStmt->execute($params);
    $totalFiltered = (int)$cStmt->fetchColumn();
} catch (PDOException $e) { /* ignore */ }

$pages = max(1, (int)ceil($totalFiltered / $perPage));
$page  = min($page, $pages);

// Orders
$orders = [];
try {
    $oStmt = $db->prepare("
        SELECT do.*, o.order_number, u.first_name, u.last_name, u.email
        FROM dropship_orders do
        LEFT JOIN orders o ON o.id = do.order_id
        LEFT JOIN users u ON u.id = do.customer_id
        WHERE $whereClause
        ORDER BY do.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $oStmt->execute(array_merge($params, [$perPage, $offset]));
    $orders = $oStmt->fetchAll();
} catch (PDOException $e) { /* ignore */ }

$statusColors = [
    'delivered' => 'success', 'shipped' => 'primary', 'processing' => 'info',
    'routed' => 'info', 'pending' => 'warning', 'cancelled' => 'danger',
    'refunded' => 'secondary',
];

// Build CSV export URL preserving filters
$exportParams = array_filter(['status' => $statusFilter !== 'all' ? $statusFilter : '', 'date_from' => $dateFrom, 'date_to' => $dateTo, 'export' => 'csv']);
$exportUrl = '?' . http_build_query($exportParams);

$pageTitle = 'Dropship Orders';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4 px-4">
  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
      <h4 class="fw-bold mb-0 text-primary"><i class="bi bi-receipt me-2"></i>Dropship Orders</h4>
      <small class="text-muted"><?= number_format($totalFiltered) ?> order<?= $totalFiltered !== 1 ? 's' : '' ?></small>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= e($exportUrl) ?>" class="btn btn-outline-success btn-sm">
        <i class="bi bi-download me-1"></i>Export CSV
      </a>
      <a href="<?= APP_URL ?>/pages/dropshipping/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Dashboard
      </a>
    </div>
  </div>

  <!-- Stat Cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm">
        <div class="card-body text-center">
          <div class="fs-4 fw-bold text-primary"><?= number_format($statCounts['total']) ?></div>
          <div class="small text-muted">Total Orders</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm">
        <div class="card-body text-center">
          <div class="fs-4 fw-bold text-warning"><?= number_format($statCounts['pending']) ?></div>
          <div class="small text-muted">Pending</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm">
        <div class="card-body text-center">
          <div class="fs-4 fw-bold text-info"><?= number_format($statCounts['processing']) ?></div>
          <div class="small text-muted">Processing</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm">
        <div class="card-body text-center">
          <div class="fs-4 fw-bold text-success"><?= number_format($statCounts['delivered']) ?></div>
          <div class="small text-muted">Delivered</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
      <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label small fw-semibold">Status</label>
          <select name="status" class="form-select form-select-sm">
            <?php foreach ($validStatuses as $s): ?>
            <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-semibold">Date From</label>
          <input type="date" name="date_from" value="<?= e($dateFrom) ?>" class="form-control form-control-sm">
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-semibold">Date To</label>
          <input type="date" name="date_to" value="<?= e($dateTo) ?>" class="form-control form-control-sm">
        </div>
        <div class="col-md-3 d-flex gap-2">
          <button type="submit" class="btn btn-primary btn-sm">Filter</button>
          <a href="?" class="btn btn-outline-secondary btn-sm">Clear</a>
        </div>
      </form>
    </div>
  </div>

  <!-- Orders Table -->
  <?php if (empty($orders)): ?>
    <div class="text-center py-5 text-muted">
      <i class="bi bi-receipt display-4"></i>
      <p class="mt-3">No orders found for the selected filters.</p>
    </div>
  <?php else: ?>
  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Order #</th>
            <th>Customer</th>
            <th>Supplier Price</th>
            <th>Selling Price</th>
            <th>Your Profit</th>
            <th>Status</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $ord):
          $statusColor  = $statusColors[$ord['status']] ?? 'secondary';
          $customerName = trim(($ord['first_name'] ?? '') . ' ' . ($ord['last_name'] ?? ''));
          $markup       = (float)$ord['selling_price'] - (float)$ord['original_price'];
          $platformFee  = (float)$ord['selling_price'] * 0.03;
          $profit       = (float)$ord['dropshipper_earning'];
        ?>
        <tr>
          <td class="ps-3 fw-semibold small"><?= e($ord['order_number'] ?? '#' . $ord['order_id']) ?></td>
          <td class="small">
            <?php if ($customerName): ?>
              <div><?= e($customerName) ?></div>
              <div class="text-muted" style="font-size:.75rem"><?= e($ord['email'] ?? '') ?></div>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td class="small"><?= formatMoney($ord['original_price']) ?></td>
          <td class="small"><?= formatMoney($ord['selling_price']) ?></td>
          <td class="small fw-semibold text-success"><?= formatMoney($profit) ?></td>
          <td><span class="badge bg-<?= $statusColor ?>"><?= e(ucfirst($ord['status'])) ?></span></td>
          <td class="small text-muted"><?= date('d M Y', strtotime($ord['created_at'])) ?></td>
          <td>
            <button class="btn btn-sm btn-outline-primary" type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#detail-<?= $ord['id'] ?>"
                    aria-expanded="false">
              <i class="bi bi-info-circle"></i> Details
            </button>
          </td>
        </tr>
        <!-- Detail collapse row -->
        <tr class="table-light">
          <td colspan="8" class="p-0">
            <div class="collapse" id="detail-<?= $ord['id'] ?>">
              <div class="p-3">
                <div class="row g-3">
                  <div class="col-md-6">
                    <h6 class="fw-semibold mb-2">Price Breakdown</h6>
                    <table class="table table-sm table-borderless mb-0">
                      <tr><td class="text-muted">Supplier Price:</td><td class="fw-semibold"><?= formatMoney($ord['original_price']) ?></td></tr>
                      <tr><td class="text-muted">Your Markup:</td><td class="fw-semibold text-primary"><?= formatMoney($markup) ?></td></tr>
                      <tr><td class="text-muted">Platform Fee (3%):</td><td class="fw-semibold text-danger">-<?= formatMoney($platformFee) ?></td></tr>
                      <tr class="border-top"><td class="fw-bold">Your Profit:</td><td class="fw-bold text-success"><?= formatMoney($profit) ?></td></tr>
                    </table>
                  </div>
                  <div class="col-md-6">
                    <h6 class="fw-semibold mb-2">Order Info</h6>
                    <div class="small">
                      <div><span class="text-muted">Internal ID:</span> <?= $ord['id'] ?></div>
                      <div><span class="text-muted">Order ID:</span> <?= $ord['order_id'] ?></div>
                      <?php if (!empty($ord['tracking_number'])): ?>
                      <div class="mt-2">
                        <span class="text-muted">Tracking #:</span>
                        <strong><?= e($ord['tracking_number']) ?></strong>
                        <?php if (!empty($ord['tracking_url'])): ?>
                          <a href="<?= e($ord['tracking_url']) ?>" target="_blank" class="ms-2">
                            <i class="bi bi-box-arrow-up-right"></i> Track
                          </a>
                        <?php endif; ?>
                      </div>
                      <?php else: ?>
                      <div class="text-muted mt-2">No tracking info yet.</div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pagination -->
  <?php if ($pages > 1): ?>
  <nav class="mt-4">
    <ul class="pagination justify-content-center">
      <?php for ($i = 1; $i <= min($pages, 10); $i++):
        $pqs = http_build_query(array_filter(['status' => $statusFilter !== 'all' ? $statusFilter : '', 'date_from' => $dateFrom, 'date_to' => $dateTo, 'page' => $i]));
      ?>
      <li class="page-item <?= $i === $page ? 'active' : '' ?>">
        <a class="page-link" href="?<?= $pqs ?>"><?= $i ?></a>
      </li>
      <?php endfor; ?>
    </ul>
  </nav>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
