<?php
/**
 * pages/dropshipping/my-products.php — Manage Imported Dropship Products
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();
require_once __DIR__ . '/../../includes/dropshipping.php';

$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$page   = max(1, (int)get('page', 1));
$tab    = in_array(get('tab', 'all'), ['all', 'active', 'inactive']) ? get('tab', 'all') : 'all';
$perPage = 20;
$offset  = ($page - 1) * $perPage;

// Handle single-product remove
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (post('action') === 'remove')) {
    if (verifyCsrf()) {
        $removeId = (int)post('product_id', 0);
        if ($removeId) {
            try {
                $stmt = $db->prepare('DELETE FROM dropship_products WHERE id = ? AND dropshipper_id = ?');
                $stmt->execute([$removeId, $userId]);
            } catch (PDOException $e) { /* ignore */ }
        }
    }
    redirect(APP_URL . '/pages/dropshipping/my-products.php?tab=' . urlencode($tab));
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('bulk_action')) {
    if (verifyCsrf()) {
        $bulkAction  = post('bulk_action');
        $selectedRaw = $_POST['selected_ids'] ?? [];
        $selectedIds = array_map('intval', (array)$selectedRaw);
        $selectedIds = array_filter($selectedIds);

        if (!empty($selectedIds)) {
            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
            try {
                if ($bulkAction === 'remove') {
                    $params = array_merge($selectedIds, [$userId]);
                    $stmt = $db->prepare("DELETE FROM dropship_products WHERE id IN ($placeholders) AND dropshipper_id = ?");
                    $stmt->execute($params);
                } elseif ($bulkAction === 'activate') {
                    $params = array_merge($selectedIds, [$userId]);
                    $stmt = $db->prepare("UPDATE dropship_products SET is_active = 1 WHERE id IN ($placeholders) AND dropshipper_id = ?");
                    $stmt->execute($params);
                } elseif ($bulkAction === 'deactivate') {
                    $params = array_merge($selectedIds, [$userId]);
                    $stmt = $db->prepare("UPDATE dropship_products SET is_active = 0 WHERE id IN ($placeholders) AND dropshipper_id = ?");
                    $stmt->execute($params);
                }
            } catch (PDOException $e) { /* ignore */ }
        }
    }
    redirect(APP_URL . '/pages/dropshipping/my-products.php?tab=' . urlencode($tab));
}

// Build WHERE clause
$baseWhere  = 'dp.dropshipper_id = ?';
$baseParams = [$userId];
if ($tab === 'active') {
    $baseWhere  .= ' AND dp.is_active = 1';
} elseif ($tab === 'inactive') {
    $baseWhere  .= ' AND dp.is_active = 0';
}

// Count query
$total = 0;
try {
    $cStmt = $db->prepare("SELECT COUNT(*) FROM dropship_products dp JOIN products p ON p.id = dp.original_product_id WHERE $baseWhere");
    $cStmt->execute($baseParams);
    $total = (int)$cStmt->fetchColumn();
} catch (PDOException $e) { /* ignore */ }

$pages = max(1, (int)ceil($total / $perPage));
$page  = min($page, $pages);

// Products query
$products = [];
try {
    $stmt = $db->prepare("
        SELECT dp.*, p.name AS original_name, p.images, p.cost_price AS current_supplier_price,
               p.status AS product_status, s.company_name AS supplier_name,
               (SELECT COUNT(*) FROM dropship_orders WHERE dropship_product_id = dp.id) AS order_count
        FROM dropship_products dp
        JOIN products p ON p.id = dp.original_product_id
        LEFT JOIN suppliers s ON s.user_id = dp.supplier_id
        WHERE $baseWhere
        ORDER BY dp.import_date DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute(array_merge($baseParams, [$perPage, $offset]));
    $products = $stmt->fetchAll();
} catch (PDOException $e) { /* ignore */ }

// Tab counts
$tabCounts = ['all' => 0, 'active' => 0, 'inactive' => 0];
try {
    $tcStmt = $db->prepare("SELECT is_active, COUNT(*) AS cnt FROM dropship_products WHERE dropshipper_id = ? GROUP BY is_active");
    $tcStmt->execute([$userId]);
    foreach ($tcStmt->fetchAll() as $row) {
        $tabCounts['all'] += (int)$row['cnt'];
        $tabCounts[$row['is_active'] ? 'active' : 'inactive'] = (int)$row['cnt'];
    }
} catch (PDOException $e) { /* ignore */ }

$pageTitle = 'My Dropship Products';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4 px-4">
  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
      <h4 class="fw-bold mb-0 text-primary"><i class="bi bi-box-seam me-2"></i>My Dropship Products</h4>
      <small class="text-muted"><?= number_format($total) ?> imported product<?= $total !== 1 ? 's' : '' ?></small>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= APP_URL ?>/pages/dropshipping/products.php" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>Import More
      </a>
      <a href="<?= APP_URL ?>/pages/dropshipping/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Dashboard
      </a>
    </div>
  </div>

  <?php if (get('imported') === '1'): ?>
  <div class="alert alert-success alert-dismissible fade show mb-4">
    <i class="bi bi-check-circle me-2"></i><strong>Product imported successfully!</strong>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <!-- Tabs -->
  <ul class="nav nav-tabs mb-4">
    <?php foreach (['all' => 'All', 'active' => 'Active', 'inactive' => 'Inactive'] as $tabKey => $tabLabel): ?>
    <li class="nav-item">
      <a class="nav-link <?= $tab === $tabKey ? 'active' : '' ?>"
         href="?tab=<?= $tabKey ?>">
        <?= $tabLabel ?>
        <span class="badge <?= $tab === $tabKey ? 'bg-primary' : 'bg-secondary' ?> ms-1">
          <?= $tabCounts[$tabKey] ?>
        </span>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>

  <?php if (empty($products)): ?>
    <div class="text-center py-5 text-muted">
      <i class="bi bi-box-seam display-4"></i>
      <p class="mt-3">No products found.
        <?php if ($tab === 'all'): ?>
          <a href="<?= APP_URL ?>/pages/dropshipping/products.php">Browse the catalog</a> to import products.
        <?php endif; ?>
      </p>
    </div>
  <?php else: ?>

  <!-- Bulk Actions + Table -->
  <form method="POST" action="" id="bulkForm">
    <?= csrfField() ?>
    <div class="card border-0 shadow-sm">
      <!-- Bulk action bar -->
      <div class="card-header bg-white border-0 py-3 d-flex align-items-center gap-3 flex-wrap">
        <div class="form-check mb-0">
          <input class="form-check-input" type="checkbox" id="selectAll">
          <label class="form-check-label small" for="selectAll">Select All</label>
        </div>
        <select name="bulk_action" class="form-select form-select-sm" style="width:auto">
          <option value="">Bulk Actions</option>
          <option value="activate">Activate Selected</option>
          <option value="deactivate">Deactivate Selected</option>
          <option value="remove">Remove Selected</option>
        </select>
        <button type="submit" class="btn btn-sm btn-outline-primary"
                onclick="return confirm('Apply bulk action to selected products?')">Apply</button>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3" style="width:40px"></th>
              <th>Product</th>
              <th>Supplier</th>
              <th>Supplier Price</th>
              <th>Your Price</th>
              <th>Markup</th>
              <th>Orders</th>
              <th>Status</th>
              <th>Sync</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($products as $dp):
            $images      = is_array($dp['images']) ? $dp['images'] : json_decode($dp['images'] ?? '[]', true);
            $img         = !empty($images[0]) ? APP_URL . '/' . $images[0] : 'https://placehold.co/48x48/e9ecef/6c757d?text=P';
            $title       = e($dp['custom_title'] ?: $dp['original_name']);
            $priceChanged = (float)$dp['current_supplier_price'] !== (float)$dp['supplier_price'];
            $markupDisplay = $dp['markup_type'] === 'percentage'
              ? number_format((float)$dp['markup_value'], 1) . '%'
              : formatMoney($dp['markup_value']);
          ?>
          <tr>
            <td class="ps-3">
              <input type="checkbox" name="selected_ids[]" value="<?= $dp['id'] ?>" class="form-check-input row-check">
            </td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <img src="<?= e($img) ?>" width="48" height="48" style="object-fit:cover;border-radius:6px;" alt="">
                <div>
                  <div class="fw-semibold small"><?= mb_strimwidth($title, 0, 60, '…') ?></div>
                  <div class="text-muted" style="font-size:.75rem">ID: <?= $dp['id'] ?></div>
                </div>
              </div>
            </td>
            <td class="small text-muted"><?= e($dp['supplier_name'] ?? '—') ?></td>
            <td class="small">
              <?= formatMoney($dp['supplier_price']) ?>
              <?php if ($priceChanged): ?>
                <br><span class="badge bg-warning text-dark" style="font-size:.7rem">Price Changed</span>
              <?php endif; ?>
            </td>
            <td class="fw-semibold small text-success"><?= formatMoney($dp['selling_price']) ?></td>
            <td class="small"><?= e($markupDisplay) ?></td>
            <td class="small text-center"><?= (int)$dp['order_count'] ?></td>
            <td>
              <?php if ($dp['is_active']): ?>
                <span class="badge bg-success">Active</span>
              <?php else: ?>
                <span class="badge bg-secondary">Inactive</span>
              <?php endif; ?>
            </td>
            <td class="small">
              <?php if ($dp['auto_sync']): ?>
                <span class="badge bg-success">Auto</span>
              <?php else: ?>
                <span class="badge bg-secondary">Manual</span>
              <?php endif; ?>
              <?php if ($dp['last_synced_at']): ?>
                <div class="text-muted" style="font-size:.7rem"><?= date('d M', strtotime($dp['last_synced_at'])) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <div class="d-flex gap-1 flex-wrap">
                <!-- Edit Markup -->
                <button type="button" class="btn btn-sm btn-outline-primary btn-edit-markup"
                        data-id="<?= $dp['id'] ?>"
                        data-title="<?= e($dp['custom_title'] ?: $dp['original_name']) ?>"
                        data-markup-type="<?= e($dp['markup_type']) ?>"
                        data-markup-value="<?= e($dp['markup_value']) ?>"
                        data-supplier-price="<?= e($dp['supplier_price']) ?>"
                        data-bs-toggle="modal" data-bs-target="#editMarkupModal">
                  <i class="bi bi-pencil"></i>
                </button>
                <!-- Toggle active -->
                <button type="button" class="btn btn-sm <?= $dp['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?> btn-toggle"
                        data-id="<?= $dp['id'] ?>"
                        title="<?= $dp['is_active'] ? 'Deactivate' : 'Activate' ?>">
                  <i class="bi bi-<?= $dp['is_active'] ? 'pause-circle' : 'play-circle' ?>"></i>
                </button>
                <!-- Remove -->
                <button type="button" class="btn btn-sm btn-outline-danger btn-remove"
                        data-id="<?= $dp['id'] ?>"
                        title="Remove">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </form>

  <!-- Pagination -->
  <?php if ($pages > 1): ?>
  <nav class="mt-4">
    <ul class="pagination justify-content-center">
      <?php for ($i = 1; $i <= min($pages, 10); $i++): ?>
      <li class="page-item <?= $i === $page ? 'active' : '' ?>">
        <a class="page-link" href="?tab=<?= urlencode($tab) ?>&page=<?= $i ?>"><?= $i ?></a>
      </li>
      <?php endfor; ?>
    </ul>
  </nav>
  <?php endif; ?>
  <?php endif; ?>
</div>

<!-- Edit Markup Modal -->
<div class="modal fade" id="editMarkupModal" tabindex="-1" aria-labelledby="editMarkupModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editMarkupModalLabel"><i class="bi bi-pencil me-2"></i>Edit Markup</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="text-muted small mb-3" id="modalProductTitle"></div>
        <div class="mb-3">
          <label class="form-label">Markup Type</label>
          <select id="modalMarkupType" class="form-select" onchange="updateModalPreview()">
            <option value="percentage">Percentage (%)</option>
            <option value="fixed">Fixed Amount ($)</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Markup Value</label>
          <input type="number" id="modalMarkupValue" class="form-control" min="0.01" step="0.01" oninput="updateModalPreview()">
        </div>
        <div class="alert alert-light border" id="modalPricePreview">
          <div class="small text-muted">Supplier price: <strong id="modalSupplierPrice"></strong></div>
          <div class="fw-bold">Selling price: <span class="text-success" id="modalSellingPrice"></span></div>
          <div class="small text-muted">Est. profit: <span class="text-success" id="modalProfit"></span> (after 3% fee)</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveMarkupBtn">
          <i class="bi bi-check-circle me-1"></i>Save Markup
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Hidden forms for toggle/remove -->
<form id="toggleForm" method="POST" action="/api/dropshipping.php?action=toggle_product" style="display:none">
  <?= csrfField() ?>
  <input type="hidden" name="dropship_product_id" id="toggleProductId">
</form>

<form id="removeForm" method="POST" action="" style="display:none">
  <?= csrfField() ?>
  <input type="hidden" name="action" value="remove">
  <input type="hidden" name="product_id" id="removeProductId">
</form>

<script>
const CSRF_TOKEN = document.querySelector('#removeForm input[name="csrf_token"]')?.value || '';

// Select all checkboxes
document.getElementById('selectAll')?.addEventListener('change', function () {
  document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
});

// Edit markup modal population
document.querySelectorAll('.btn-edit-markup').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('modalProductTitle').textContent = btn.dataset.title;
    document.getElementById('modalMarkupType').value         = btn.dataset.markupType;
    document.getElementById('modalMarkupValue').value        = btn.dataset.markupValue;
    document.getElementById('saveMarkupBtn').dataset.id      = btn.dataset.id;
    document.getElementById('saveMarkupBtn').dataset.supplierPrice = btn.dataset.supplierPrice;
    document.getElementById('modalSupplierPrice').textContent = '$' + parseFloat(btn.dataset.supplierPrice).toFixed(2);
    updateModalPreview();
  });
});

function updateModalPreview() {
  const type     = document.getElementById('modalMarkupType').value;
  const val      = parseFloat(document.getElementById('modalMarkupValue').value) || 0;
  const supplier = parseFloat(document.getElementById('saveMarkupBtn')?.dataset.supplierPrice) || 0;
  let markup, selling;
  if (type === 'percentage') {
    markup  = supplier * val / 100;
    selling = supplier + markup;
  } else {
    markup  = val;
    selling = supplier + markup;
  }
  const profit = markup - selling * 0.03;
  document.getElementById('modalSellingPrice').textContent = '$' + selling.toFixed(2);
  document.getElementById('modalProfit').textContent       = '$' + Math.max(0, profit).toFixed(2);
}

// Save markup via AJAX
document.getElementById('saveMarkupBtn')?.addEventListener('click', async () => {
  const id    = document.getElementById('saveMarkupBtn').dataset.id;
  const type  = document.getElementById('modalMarkupType').value;
  const value = document.getElementById('modalMarkupValue').value;
  const body  = new URLSearchParams({ csrf_token: CSRF_TOKEN, dropship_product_id: id, markup_type: type, markup_value: value });
  const btn   = document.getElementById('saveMarkupBtn');
  btn.disabled = true; btn.textContent = 'Saving…';
  try {
    const res  = await fetch('/api/dropshipping.php?action=update_markup', { method: 'POST', body });
    const data = await res.json();
    if (data.success) {
      bootstrap.Modal.getInstance(document.getElementById('editMarkupModal')).hide();
      location.reload();
    } else {
      alert(data.error || 'Failed to update markup');
      btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Save Markup';
    }
  } catch (e) {
    alert('Network error. Please try again.');
    btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Save Markup';
  }
});

// Toggle active/inactive
document.querySelectorAll('.btn-toggle').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('toggleProductId').value = btn.dataset.id;
    document.getElementById('toggleForm').submit();
  });
});

// Remove single product
document.querySelectorAll('.btn-remove').forEach(btn => {
  btn.addEventListener('click', () => {
    if (confirm('Remove this product from your store?')) {
      document.getElementById('removeProductId').value = btn.dataset.id;
      document.getElementById('removeForm').submit();
    }
  });
});
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
