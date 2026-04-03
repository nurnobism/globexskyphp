<?php
/**
 * pages/dropshipping/import.php — Import Product to Dropship Store
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();
require_once __DIR__ . '/../../includes/dropshipping.php';

$db        = getDB();
$userId    = (int)$_SESSION['user_id'];
$productId = (int)get('product_id', 0);

// Plan check
$planLimits = checkDropshipPlanLimits($userId);
if (!$planLimits['allowed']) {
    redirect(APP_URL . '/pages/supplier/plans.php');
}

// Load product
$product = null;
if ($productId) {
    try {
        $stmt = $db->prepare('SELECT p.*, s.company_name AS supplier_name
            FROM products p LEFT JOIN suppliers s ON s.user_id = p.supplier_id
            WHERE p.id = ? AND p.status = "active"');
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
    } catch (PDOException $e) { /* ignore */ }
}
if (!$product) {
    redirect(APP_URL . '/pages/dropshipping/products.php');
}

$images = json_decode($product['images'] ?? '[]', true);
$originalPrice = (float)($product['cost_price'] ?? $product['price'] ?? 0);

// Handle POST
$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid CSRF token. Please try again.';
    } else {
        $markupType  = in_array($_POST['markup_type'] ?? '', ['percentage','fixed']) ? $_POST['markup_type'] : 'percentage';
        $markupValue = max(0, (float)($_POST['markup_value'] ?? 20));
        $customTitle = trim($_POST['custom_title'] ?? $product['name']);
        $customDesc  = trim($_POST['custom_description'] ?? '');
        $autoSync    = isset($_POST['auto_sync']) ? 1 : 0;

        $result = importProduct($userId, $productId, $markupType, $markupValue, [
            'custom_title'       => $customTitle,
            'custom_description' => $customDesc,
            'auto_sync'          => $autoSync,
        ]);

        if ($result['success']) {
            redirect(APP_URL . '/pages/dropshipping/my-products.php?imported=1');
        } else {
            $error = $result['error'] ?? 'Import failed';
        }
    }
}

$pageTitle = 'Import Product — ' . e($product['name']);
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-lg-9">
      <div class="d-flex align-items-center gap-3 mb-4">
        <a href="<?= APP_URL ?>/pages/dropshipping/products.php" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-arrow-left"></i>
        </a>
        <h4 class="fw-bold mb-0 text-primary"><i class="bi bi-download me-2"></i>Import Product</h4>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
      <?php endif; ?>

      <!-- Plan limit warning -->
      <?php if ($planLimits['max_count'] !== 'Unlimited'): ?>
      <div class="alert alert-light border mb-4">
        <i class="bi bi-bar-chart me-2"></i>
        <?= $planLimits['current_count'] ?>/<?= $planLimits['max_count'] ?> products imported
        <?php if ($planLimits['current_count'] >= (int)$planLimits['max_count'] - 5): ?>
          <span class="text-warning ms-2"><i class="bi bi-exclamation-triangle"></i> Near limit!</span>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="">
        <?= csrfField() ?>
        <input type="hidden" name="product_id" value="<?= $productId ?>">

        <div class="row g-4">
          <!-- Product preview -->
          <div class="col-md-4">
            <div class="card border-0 shadow-sm">
              <div style="height:200px;background:#f8f9fa;overflow:hidden;">
                <?php if (!empty($images[0])): ?>
                  <img src="<?= e(APP_URL . '/' . $images[0]) ?>" class="w-100 h-100" style="object-fit:cover;" alt="">
                <?php else: ?>
                  <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                    <i class="bi bi-image fs-1"></i>
                  </div>
                <?php endif; ?>
              </div>
              <div class="card-body">
                <div class="small text-muted"><?= e($product['supplier_name'] ?? '') ?></div>
                <div class="fw-semibold"><?= e($product['name']) ?></div>
                <div class="mt-2">
                  <span class="text-muted small">Supplier Price:</span>
                  <span class="fw-bold text-primary ms-1"><?= formatMoney($originalPrice) ?></span>
                </div>
              </div>
            </div>
          </div>

          <!-- Import form -->
          <div class="col-md-8">
            <div class="card border-0 shadow-sm">
              <div class="card-body">
                <h6 class="fw-bold mb-3">Customize Product</h6>
                <div class="mb-3">
                  <label class="form-label">Product Title</label>
                  <input type="text" name="custom_title" value="<?= e($product['name']) ?>" class="form-control" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">Custom Description</label>
                  <textarea name="custom_description" rows="4" class="form-control" placeholder="Custom product description (leave blank to use original)"><?= e($product['description'] ?? '') ?></textarea>
                </div>

                <hr>
                <h6 class="fw-bold mb-3">Markup Configuration</h6>
                <div class="row g-3 mb-3">
                  <div class="col-md-6">
                    <label class="form-label">Markup Type</label>
                    <select name="markup_type" id="markupType" class="form-select" onchange="calcPrice()">
                      <option value="percentage">Percentage (%)</option>
                      <option value="fixed">Fixed Amount ($)</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Markup Value</label>
                    <input type="number" name="markup_value" id="markupValue" value="20" min="0.01" step="0.01" class="form-control" oninput="calcPrice()">
                  </div>
                </div>

                <!-- Real-time price preview -->
                <div class="alert alert-light border" id="pricePreview">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <div class="small text-muted">Supplier price: <strong class="text-dark"><?= formatMoney($originalPrice) ?></strong></div>
                      <div class="small text-muted">Your markup: <strong class="text-primary" id="markupDisplay">$2.00 (20%)</strong></div>
                      <div class="mt-1 fw-bold">Selling price: <span class="text-success fs-5" id="sellingPrice"><?= formatMoney($originalPrice * 1.2) ?></span></div>
                    </div>
                    <div class="text-end">
                      <div class="small text-muted">Est. profit/sale</div>
                      <div class="fw-bold text-success fs-5" id="profitDisplay"><?= formatMoney($originalPrice * 0.2 * 0.97) ?></div>
                      <div class="small text-muted">after 3% platform fee</div>
                    </div>
                  </div>
                </div>

                <div class="form-check mb-3">
                  <input class="form-check-input" type="checkbox" name="auto_sync" id="autoSync" value="1" checked>
                  <label class="form-check-label" for="autoSync">
                    Auto-sync price &amp; stock with supplier
                  </label>
                </div>

                <div class="d-flex gap-2">
                  <button type="submit" class="btn btn-primary">
                    <i class="bi bi-download me-2"></i>Import to My Store
                  </button>
                  <a href="<?= APP_URL ?>/pages/dropshipping/products.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const originalPrice = <?= json_encode($originalPrice) ?>;
function calcPrice() {
  const type  = document.getElementById('markupType').value;
  const val   = parseFloat(document.getElementById('markupValue').value) || 0;
  let markup, selling;
  if (type === 'percentage') {
    const pct = Math.max(5, Math.min(300, val));
    markup  = originalPrice * pct / 100;
    selling = originalPrice + markup;
    document.getElementById('markupDisplay').textContent = '$' + markup.toFixed(2) + ' (' + pct + '%)';
  } else {
    markup  = Math.max(0.01, val);
    selling = originalPrice + markup;
    document.getElementById('markupDisplay').textContent = '$' + markup.toFixed(2);
  }
  const profit = markup - selling * 0.03;
  document.getElementById('sellingPrice').textContent = '$' + selling.toFixed(2);
  document.getElementById('profitDisplay').textContent = '$' + Math.max(0, profit).toFixed(2);
}
calcPrice();
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
