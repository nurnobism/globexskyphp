<?php
/**
 * pages/dropshipping/supplier-dropshippers.php — View Dropshippers Selling Your Products
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

// Handle approve/reject applications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = post('action', '');
    $appId  = (int)post('application_id', 0);

    if ($appId && in_array($action, ['approve', 'reject'])) {
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';
        $reason    = trim(post('rejection_reason', ''));
        try {
            $upd = $db->prepare('UPDATE dropship_applications SET status = ?, reviewed_at = NOW(),
                reviewed_by = ?, rejection_reason = ? WHERE id = ? AND supplier_id = ?');
            $upd->execute([$newStatus, $userId, $reason, $appId, $supplierId]);
        } catch (PDOException $e) { /* ignore */ }
    }
    redirect(APP_URL . '/pages/dropshipping/supplier-dropshippers.php');
}

// Active dropshippers (from dropship_products table)
$dropshippers = [];
try {
    $stmt = $db->prepare("SELECT u.id, u.first_name, u.last_name, u.email, u.avatar,
        ds.store_name, ds.store_slug, ds.total_orders AS store_orders, ds.total_revenue AS store_revenue,
        COUNT(dp.id) AS product_count,
        (SELECT COUNT(*) FROM dropship_orders dor WHERE dor.supplier_id = ? AND dor.dropshipper_id = u.id) AS order_count
        FROM dropship_products dp
        JOIN users u ON u.id = dp.dropshipper_id
        LEFT JOIN dropship_stores ds ON ds.user_id = dp.dropshipper_id
        WHERE dp.supplier_id = ? AND dp.is_active = 1
        GROUP BY u.id, u.first_name, u.last_name, u.email, u.avatar,
                 ds.store_name, ds.store_slug, ds.total_orders, ds.total_revenue
        ORDER BY product_count DESC");
    $stmt->execute([$supplierId, $supplierId]);
    $dropshippers = $stmt->fetchAll();
} catch (PDOException $e) { /* ignore */ }

// Pending applications
$pendingApps = [];
try {
    $stmt = $db->prepare("SELECT da.*, u.first_name, u.last_name, u.email, u.avatar,
        ds.store_name
        FROM dropship_applications da
        JOIN users u ON u.id = da.dropshipper_id
        LEFT JOIN dropship_stores ds ON ds.id = da.store_id
        WHERE da.supplier_id = ? AND da.status = 'pending'
        ORDER BY da.created_at DESC");
    $stmt->execute([$supplierId]);
    $pendingApps = $stmt->fetchAll();
} catch (PDOException $e) { /* ignore */ }

$pageTitle = 'My Dropshippers';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4 px-4">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
      <h4 class="fw-bold mb-0 text-primary"><i class="bi bi-people me-2"></i>My Dropshippers</h4>
      <small class="text-muted">Manage dropshippers selling your products</small>
    </div>
    <a href="<?= APP_URL ?>/pages/dropshipping/supplier-settings.php" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-gear me-1"></i>Dropship Settings
    </a>
  </div>

  <!-- Pending Applications -->
  <?php if (!empty($pendingApps)): ?>
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-warning bg-opacity-10 border-0 py-3">
      <h6 class="fw-bold mb-0 text-warning"><i class="bi bi-hourglass-split me-2"></i>Pending Applications (<?= count($pendingApps) ?>)</h6>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr><th class="ps-3">Applicant</th><th>Store</th><th>Message</th><th>Applied</th><th>Actions</th></tr>
          </thead>
          <tbody>
          <?php foreach ($pendingApps as $app): ?>
            <tr>
              <td class="ps-3">
                <div class="d-flex align-items-center gap-2">
                  <?php if (!empty($app['avatar'])): ?>
                    <img src="<?= e($app['avatar']) ?>" width="32" height="32" class="rounded-circle" alt="">
                  <?php else: ?>
                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width:32px;height:32px;">
                      <i class="bi bi-person text-muted"></i>
                    </div>
                  <?php endif; ?>
                  <div>
                    <div class="fw-semibold small"><?= e(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? '')) ?></div>
                    <div class="text-muted" style="font-size:.75rem"><?= e($app['email'] ?? '') ?></div>
                  </div>
                </div>
              </td>
              <td class="small"><?= e($app['store_name'] ?? '—') ?></td>
              <td class="small text-muted"><?= e(mb_strimwidth($app['message'] ?? '—', 0, 80, '…')) ?></td>
              <td class="small text-muted"><?= formatDate($app['created_at'] ?? '') ?></td>
              <td>
                <form method="POST" class="d-inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">
                  <button type="submit" name="action" value="approve" class="btn btn-success btn-sm me-1">
                    <i class="bi bi-check"></i> Approve
                  </button>
                  <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm"
                    onclick="this.form.querySelector('[name=rejection_reason]').value = prompt('Rejection reason (optional):') || '';">
                    <i class="bi bi-x"></i> Reject
                  </button>
                  <input type="hidden" name="rejection_reason" value="">
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Active Dropshippers -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 py-3">
      <h6 class="fw-bold mb-0"><i class="bi bi-person-check me-2 text-success"></i>Active Dropshippers (<?= count($dropshippers) ?>)</h6>
    </div>
    <div class="card-body p-0">
      <?php if (empty($dropshippers)): ?>
        <div class="text-center py-5 text-muted">
          <i class="bi bi-people display-3"></i>
          <p class="mt-3">No dropshippers are selling your products yet.</p>
        </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Dropshipper</th>
              <th>Store Name</th>
              <th>Products Imported</th>
              <th>Orders</th>
              <th>Revenue Generated</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($dropshippers as $ds): ?>
            <tr>
              <td class="ps-3">
                <div class="d-flex align-items-center gap-2">
                  <?php if (!empty($ds['avatar'])): ?>
                    <img src="<?= e($ds['avatar']) ?>" width="32" height="32" class="rounded-circle" alt="">
                  <?php else: ?>
                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width:32px;height:32px;">
                      <i class="bi bi-person text-muted"></i>
                    </div>
                  <?php endif; ?>
                  <div>
                    <div class="fw-semibold small"><?= e(($ds['first_name'] ?? '') . ' ' . ($ds['last_name'] ?? '')) ?></div>
                    <div class="text-muted" style="font-size:.75rem"><?= e($ds['email'] ?? '') ?></div>
                  </div>
                </div>
              </td>
              <td>
                <?php if (!empty($ds['store_slug'])): ?>
                  <a href="<?= APP_URL ?>/pages/dropshipping/store-preview.php?store=<?= urlencode($ds['store_slug']) ?>" class="text-decoration-none small">
                    <?= e($ds['store_name'] ?? '—') ?>
                  </a>
                <?php else: ?>
                  <span class="text-muted small"><?= e($ds['store_name'] ?? '—') ?></span>
                <?php endif; ?>
              </td>
              <td class="fw-semibold"><?= number_format($ds['product_count']) ?></td>
              <td><?= number_format($ds['order_count']) ?></td>
              <td class="fw-semibold text-success"><?= formatMoney($ds['store_revenue'] ?? 0) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
