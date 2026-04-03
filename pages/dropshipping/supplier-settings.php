<?php
/**
 * pages/dropshipping/supplier-settings.php — Supplier Dropship Configuration
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();
requireRole(['supplier', 'admin', 'super_admin']);

$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$error  = '';
$success = '';

// Get supplier_id from user
$supplierId = 0;
try {
    $stmt = $db->prepare('SELECT id FROM suppliers WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $sup = $stmt->fetch();
    $supplierId = $sup ? (int)$sup['id'] : $userId;
} catch (PDOException $e) {
    $supplierId = $userId;
}

// Load existing settings
$settings = null;
try {
    $stmt = $db->prepare('SELECT * FROM supplier_dropship_settings WHERE supplier_id = ? LIMIT 1');
    $stmt->execute([$supplierId]);
    $settings = $stmt->fetch() ?: null;
} catch (PDOException $e) { /* ignore */ }

// Handle form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid CSRF token.';
    } else {
        $allowDropshipping    = isset($_POST['allow_dropshipping']) ? 1 : 0;
        $minMarkup            = max(1, min(100, (float)post('min_markup_percent', 5)));
        $maxMarkup            = max($minMarkup, min(500, (float)post('max_markup_percent', 300)));
        $whiteLabelAvailable  = isset($_POST['white_label_available']) ? 1 : 0;
        $autoApprove          = isset($_POST['auto_approve_dropshippers']) ? 1 : 0;
        $processingDays       = max(1, min(30, (int)post('processing_time_days', 3)));
        $returnPolicy         = in_array(post('return_policy'), ['no_returns','7_days','14_days','30_days'])
                                ? post('return_policy') : '14_days';
        $dropshipTerms        = trim(post('dropship_terms', ''));

        try {
            if ($settings) {
                $upd = $db->prepare('UPDATE supplier_dropship_settings SET
                    allow_dropshipping = ?, min_markup_percent = ?, max_markup_percent = ?,
                    white_label_available = ?, auto_approve_dropshippers = ?,
                    processing_time_days = ?, return_policy = ?, dropship_terms = ?,
                    updated_at = NOW()
                    WHERE supplier_id = ?');
                $upd->execute([$allowDropshipping, $minMarkup, $maxMarkup, $whiteLabelAvailable,
                    $autoApprove, $processingDays, $returnPolicy, $dropshipTerms, $supplierId]);
            } else {
                $ins = $db->prepare('INSERT INTO supplier_dropship_settings
                    (supplier_id, allow_dropshipping, min_markup_percent, max_markup_percent,
                     white_label_available, auto_approve_dropshippers, processing_time_days,
                     return_policy, dropship_terms)
                    VALUES (?,?,?,?,?,?,?,?,?)');
                $ins->execute([$supplierId, $allowDropshipping, $minMarkup, $maxMarkup,
                    $whiteLabelAvailable, $autoApprove, $processingDays, $returnPolicy, $dropshipTerms]);
            }
            $success = 'Dropshipping settings saved successfully!';

            // Reload settings
            $stmt = $db->prepare('SELECT * FROM supplier_dropship_settings WHERE supplier_id = ? LIMIT 1');
            $stmt->execute([$supplierId]);
            $settings = $stmt->fetch() ?: null;
        } catch (PDOException $e) {
            $error = 'Failed to save settings.';
        }
    }
}

$pageTitle = 'Supplier Dropship Settings';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <div class="d-flex align-items-center gap-3 mb-4">
        <a href="<?= APP_URL ?>/pages/supplier/dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
        <h4 class="fw-bold mb-0 text-primary"><i class="bi bi-shop-window me-2"></i>Dropshipping Settings</h4>
      </div>

      <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

      <form method="POST" action="">
        <?= csrfField() ?>

        <div class="card border-0 shadow-sm mb-4">
          <div class="card-header bg-white"><h6 class="fw-bold mb-0">Enable Dropshipping</h6></div>
          <div class="card-body">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" name="allow_dropshipping" id="allowDropship"
                <?= ($settings && $settings['allow_dropshipping']) ? 'checked' : '' ?>>
              <label class="form-check-label fw-semibold" for="allowDropship">Allow Dropshippers to Sell My Products</label>
              <div class="form-text">When enabled, dropshippers can import and resell your products.</div>
            </div>
          </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
          <div class="card-header bg-white"><h6 class="fw-bold mb-0">Markup Rules</h6></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Minimum Markup (%)</label>
                <input type="number" name="min_markup_percent" value="<?= e($settings['min_markup_percent'] ?? '5') ?>"
                  class="form-control" min="1" max="100" step="0.01">
                <div class="form-text">Dropshippers must add at least this percentage markup.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Maximum Markup (%)</label>
                <input type="number" name="max_markup_percent" value="<?= e($settings['max_markup_percent'] ?? '300') ?>"
                  class="form-control" min="5" max="500" step="0.01">
                <div class="form-text">Maximum markup allowed for your products.</div>
              </div>
            </div>
          </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
          <div class="card-header bg-white"><h6 class="fw-bold mb-0">Fulfillment &amp; Policy</h6></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Processing Time (days)</label>
                <input type="number" name="processing_time_days" value="<?= e($settings['processing_time_days'] ?? '3') ?>"
                  class="form-control" min="1" max="30">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Return Policy</label>
                <select name="return_policy" class="form-select">
                  <?php foreach (['no_returns'=>'No Returns','7_days'=>'7 Days','14_days'=>'14 Days','30_days'=>'30 Days'] as $v => $l): ?>
                  <option value="<?= $v ?>" <?= ($settings['return_policy'] ?? '14_days') === $v ? 'selected' : '' ?>><?= $l ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="form-check form-switch mt-3">
              <input class="form-check-input" type="checkbox" name="white_label_available" id="whiteLabel"
                <?= ($settings && $settings['white_label_available']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="whiteLabel">White-Label Shipping Available (Pro+)</label>
              <div class="form-text">Ship with dropshipper's brand on the label.</div>
            </div>
            <div class="form-check form-switch mt-3">
              <input class="form-check-input" type="checkbox" name="auto_approve_dropshippers" id="autoApprove"
                <?= ($settings && $settings['auto_approve_dropshippers']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="autoApprove">Auto-Approve Dropshipper Applications</label>
              <div class="form-text">If off, you must manually approve each dropshipper.</div>
            </div>
          </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
          <div class="card-header bg-white"><h6 class="fw-bold mb-0">Terms &amp; Conditions</h6></div>
          <div class="card-body">
            <textarea name="dropship_terms" rows="5" class="form-control"
              placeholder="Your dropshipping terms and conditions..."><?= e($settings['dropship_terms'] ?? '') ?></textarea>
          </div>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Settings</button>
          <a href="<?= APP_URL ?>/pages/supplier/dashboard.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
