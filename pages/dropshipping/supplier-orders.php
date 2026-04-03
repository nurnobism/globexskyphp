<?php
/**
 * pages/dropshipping/supplier-orders.php — Supplier Dropship Orders View
 * Supplier does NOT see markup/earning info — only order basics and shipping.
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();
requireRole(['supplier', 'admin', 'super_admin']);

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

// Get supplier_id
$supplierId = 0;
try {
    $stmt = $db->prepare('SELECT id FROM suppliers WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $sup = $stmt->fetch();
    $supplierId = $sup ? (int)$sup['id'] : $userId;
} catch (PDOException $e) {
    $supplierId = $userId;
}

// Handle status update + tracking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action   = post('action', '');
    $orderId  = (int)post('order_id', 0);

    if ($orderId && $action === 'update_status') {
        $newStatus   = post('new_status', '');
        $trackingNum = trim(post('tracking_number', ''));
        $trackingUrl = trim(post('tracking_url', ''));
        $allowed     = ['processing','shipped','delivered','cancelled'];

        if (in_array($newStatus, $allowed)) {
            try {
                $sets   = ['status = ?', 'updated_at = NOW()'];
                $params = [$newStatus];

                if ($newStatus === 'shipped') {
                    $sets[] = 'shipped_at = NOW()';
                    if ($trackingNum) { $sets[] = 'tracking_number = ?'; $params[] = $trackingNum; }
                    if ($trackingUrl) { $sets[] = 'tracking_url = ?'; $params[] = $trackingUrl; }
                } elseif ($newStatus === 'delivered') {
                    $sets[] = 'delivered_at = NOW()';
                }

                $params[] = $orderId;
                $params[] = $supplierId;
                $db->prepare('UPDATE dropship_orders SET ' . implode(', ', $sets) . ' WHERE id = ? AND supplier_id = ?')
                   ->execute($params);

                // Mark earnings available on delivery
                if ($newStatus === 'delivered') {
                    try {
                        $db->prepare("UPDATE dropship_earnings SET status = 'available',
                            available_at = DATE_ADD(NOW(), INTERVAL 7 DAY)
                            WHERE dropship_order_id = ? AND status = 'pending'")
                           ->execute([$orderId]);
                    } catch (PDOException $e) { /* ignore */ }
                }
            } catch (PDOException $e) { /* ignore */ }
        }
    }
    redirect(APP_URL . '/pages/dropshipping/supplier-orders.php');
}

// Filters
$statusFilter = get('status', 'all');
$page    = max(1, (int)get('page', 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = ['do.supplier_id = ?'];
$params = [$supplierId];

if ($statusFilter !== 'all' && in_array($statusFilter, ['pending','routed','processing','shipped','delivered','cancelled'])) {
    $where[]  = 'do.status = ?';
    $params[] = $statusFilter;
}

$whereClause = implode(' AND ', $where);

$orders     = [];
$totalOrders = 0;
try {
    $countStmt = $db->prepare("SELECT COUNT(*) FROM dropship_orders do WHERE $whereClause");
    $countStmt->execute($params);
    $totalOrders = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare("SELECT do.id, do.order_id, do.status, do.original_price, do.is_white_label,
        do.white_label_brand, do.tracking_number, do.tracking_url, do.created_at,
        do.routed_at, do.shipped_at, do.delivered_at,
        o.order_number, u.first_name AS cust_first, u.last_name AS cust_last,
        a.address_line1, a.city, a.state, a.postal_code, a.country,
        dp.custom_title AS product_title
        FROM dropship_orders do
        LEFT JOIN orders o ON o.id = do.order_id
        LEFT JOIN users u ON u.id = do.customer_id
        LEFT JOIN addresses a ON a.user_id = do.customer_id AND a.is_default = 1
        LEFT JOIN dropship_products dp ON dp.store_id = do.store_id AND dp.original_product_id = (
            SELECT oi.product_id FROM order_items oi WHERE oi.order_id = do.order_id LIMIT 1
        )
        WHERE $whereClause
        ORDER BY do.created_at DESC
        LIMIT ? OFFSET ?");
    $stmt->execute(array_merge($params, [$perPage, $offset]));
    $orders = $stmt->fetchAll();
} catch (PDOException $e) { /* ignore */ }

$totalPages = (int)ceil($totalOrders / $perPage);

$pageTitle = 'Supplier Dropship Orders';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4 px-4">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
      <h4 class="fw-bold mb-0 text-primary"><i class="bi bi-truck me-2"></i>Dropship Orders</h4>
      <small class="text-muted">Orders to fulfill from dropshippers — <?= number_format($totalOrders) ?> total</small>
    </div>
    <a href="<?= APP_URL ?>/pages/dropshipping/supplier-settings.php" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-gear me-1"></i>Dropship Settings
    </a>
  </div>

  <!-- Filters -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2">
      <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
        <span class="small fw-semibold me-2">Status:</span>
        <?php foreach (['all'=>'All','pending'=>'Pending','routed'=>'Routed','processing'=>'Processing','shipped'=>'Shipped','delivered'=>'Delivered'] as $k=>$v): ?>
        <a href="?status=<?= $k ?>" class="btn btn-sm btn-<?= $statusFilter===$k?'primary':'outline-secondary' ?>"><?= $v ?></a>
        <?php endforeach; ?>
      </form>
    </div>
  </div>

  <!-- Orders Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <?php if (empty($orders)): ?>
        <div class="text-center py-5 text-muted">
          <i class="bi bi-inbox display-3"></i>
          <p class="mt-3">No dropship orders yet.</p>
        </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Order #</th>
              <th>Product</th>
              <th>Ship To</th>
              <th>Price</th>
              <th>Status</th>
              <th>White-Label</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($orders as $ord):
            $sc = match($ord['status']) {
              'delivered'=>'success','shipped'=>'primary','processing'=>'info',
              'routed'=>'info','cancelled'=>'danger',default=>'secondary'
            };
            $address = trim(($ord['address_line1'] ?? '') . ', ' . ($ord['city'] ?? '') . ' ' . ($ord['state'] ?? '') . ' ' . ($ord['postal_code'] ?? '') . ', ' . ($ord['country'] ?? ''), ', ');
          ?>
            <tr>
              <td class="ps-3 fw-semibold small"><?= e($ord['order_number'] ?? '#' . $ord['order_id']) ?></td>
              <td class="small"><?= e(mb_strimwidth($ord['product_title'] ?? 'Product', 0, 40, '…')) ?></td>
              <td class="small text-muted">
                <div><?= e(($ord['cust_first'] ?? '') . ' ' . ($ord['cust_last'] ?? '')) ?></div>
                <div style="font-size:.75rem"><?= e($address ?: '—') ?></div>
              </td>
              <td class="fw-semibold"><?= formatMoney($ord['original_price']) ?></td>
              <td><span class="badge bg-<?= $sc ?>"><?= e(ucfirst($ord['status'])) ?></span></td>
              <td>
                <?php if ($ord['is_white_label']): ?>
                  <span class="badge bg-info"><i class="bi bi-tag me-1"></i><?= e($ord['white_label_brand'] ?? 'Yes') ?></span>
                <?php else: ?>
                  <span class="text-muted small">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (in_array($ord['status'], ['routed','processing'])): ?>
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                  data-bs-target="#updateModal<?= $ord['id'] ?>">
                  <i class="bi bi-pencil me-1"></i>Update
                </button>

                <!-- Update Modal -->
                <div class="modal fade" id="updateModal<?= $ord['id'] ?>" tabindex="-1">
                  <div class="modal-dialog">
                    <div class="modal-content">
                      <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                        <div class="modal-header">
                          <h6 class="modal-title fw-bold">Update Order <?= e($ord['order_number'] ?? '#' . $ord['order_id']) ?></h6>
                          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                          <div class="mb-3">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="new_status" class="form-select">
                              <option value="processing" <?= $ord['status']==='processing'?'selected':'' ?>>Processing</option>
                              <option value="shipped">Shipped</option>
                              <option value="delivered">Delivered</option>
                              <option value="cancelled">Cancelled</option>
                            </select>
                          </div>
                          <div class="mb-3">
                            <label class="form-label fw-semibold">Tracking Number</label>
                            <input type="text" name="tracking_number" value="<?= e($ord['tracking_number'] ?? '') ?>" class="form-control" placeholder="Tracking #">
                          </div>
                          <div class="mb-3">
                            <label class="form-label fw-semibold">Tracking URL</label>
                            <input type="url" name="tracking_url" value="<?= e($ord['tracking_url'] ?? '') ?>" class="form-control" placeholder="https://...">
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                          <button type="submit" class="btn btn-primary">Update</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
                <?php elseif ($ord['status'] === 'shipped' && !empty($ord['tracking_number'])): ?>
                  <span class="small text-muted"><i class="bi bi-truck me-1"></i><?= e($ord['tracking_number']) ?></span>
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

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <nav class="mt-3">
    <ul class="pagination justify-content-center">
      <?php for ($i = 1; $i <= min($totalPages, 10); $i++): ?>
      <li class="page-item <?= $i === $page ? 'active' : '' ?>">
        <a class="page-link" href="?status=<?= urlencode($statusFilter) ?>&page=<?= $i ?>"><?= $i ?></a>
      </li>
      <?php endfor; ?>
    </ul>
  </nav>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
