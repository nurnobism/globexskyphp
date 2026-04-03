<?php
/**
 * pages/dropshipping/store.php — Store Settings
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();
require_once __DIR__ . '/../../includes/dropshipping.php';

$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$error  = '';
$success = '';

// Load existing store
$store = null;
try {
    $stmt = $db->prepare('SELECT * FROM dropship_stores WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $store = $stmt->fetch() ?: null;
} catch (PDOException $e) { /* ignore */ }

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid CSRF token. Please try again.';
    } else {
        $storeName  = trim(post('store_name', ''));
        $storeSlug  = trim(post('store_slug', ''));
        $storeDesc  = trim(post('store_description', ''));
        $logoUrl    = trim(post('logo_url', ''));
        $bannerUrl  = trim(post('banner_url', ''));
        $themeColor = trim(post('theme_color', '#0d6efd'));
        $customDomain = trim(post('custom_domain', ''));

        if (empty($storeName)) {
            $error = 'Store name is required.';
        } else {
            // Sanitize slug
            if (empty($storeSlug)) {
                $storeSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $storeName));
                $storeSlug = trim($storeSlug, '-');
            } else {
                $storeSlug = strtolower(preg_replace('/[^a-z0-9\-]/', '', $storeSlug));
            }

            // Check slug uniqueness
            try {
                $checkSlug = $db->prepare('SELECT id FROM dropship_stores WHERE store_slug = ? AND user_id != ?');
                $checkSlug->execute([$storeSlug, $userId]);
                if ($checkSlug->fetch()) {
                    $storeSlug .= '-' . rand(100, 999);
                }
            } catch (PDOException $e) { /* ignore */ }

            try {
                if ($store) {
                    // Update
                    $upd = $db->prepare('UPDATE dropship_stores SET
                        store_name = ?, store_slug = ?, store_description = ?,
                        logo_url = ?, banner_url = ?, theme_color = ?, custom_domain = ?,
                        updated_at = NOW()
                        WHERE id = ? AND user_id = ?');
                    $upd->execute([$storeName, $storeSlug, $storeDesc, $logoUrl, $bannerUrl,
                        $themeColor, $customDomain, $store['id'], $userId]);
                } else {
                    // Create
                    $ins = $db->prepare('INSERT INTO dropship_stores
                        (user_id, store_name, store_slug, store_description, logo_url, banner_url, theme_color, custom_domain)
                        VALUES (?,?,?,?,?,?,?,?)');
                    $ins->execute([$userId, $storeName, $storeSlug, $storeDesc, $logoUrl, $bannerUrl,
                        $themeColor, $customDomain]);
                }
                $success = 'Store settings saved successfully!';

                // Reload store
                $stmt = $db->prepare('SELECT * FROM dropship_stores WHERE user_id = ? LIMIT 1');
                $stmt->execute([$userId]);
                $store = $stmt->fetch() ?: null;
            } catch (PDOException $e) {
                $error = 'Failed to save store settings.';
            }
        }
    }
}

$pageTitle = 'Store Settings';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <div class="d-flex align-items-center gap-3 mb-4">
        <a href="<?= APP_URL ?>/pages/dropshipping/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
        <h4 class="fw-bold mb-0 text-primary"><i class="bi bi-shop me-2"></i>Store Settings</h4>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
      <?php endif; ?>

      <?php if ($store): ?>
      <div class="alert alert-light border mb-4">
        <i class="bi bi-eye me-2"></i>
        <strong>Store Preview:</strong>
        <a href="<?= APP_URL ?>/pages/dropshipping/store-preview.php?store=<?= urlencode($store['store_slug']) ?>" target="_blank" class="ms-2">
          <?= APP_URL ?>/pages/dropshipping/store-preview.php?store=<?= e($store['store_slug']) ?>
        </a>
      </div>
      <?php endif; ?>

      <form method="POST" action="">
        <?= csrfField() ?>
        <div class="card border-0 shadow-sm mb-4">
          <div class="card-header bg-white"><h6 class="fw-bold mb-0">Basic Information</h6></div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label fw-semibold">Store Name <span class="text-danger">*</span></label>
              <input type="text" name="store_name" value="<?= e($store['store_name'] ?? '') ?>" class="form-control" required placeholder="My Awesome Store">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Store URL Slug</label>
              <div class="input-group">
                <span class="input-group-text"><?= APP_URL ?>/store/</span>
                <input type="text" name="store_slug" value="<?= e($store['store_slug'] ?? '') ?>" class="form-control" placeholder="my-store" pattern="[a-z0-9\-]+">
              </div>
              <div class="form-text">Only lowercase letters, numbers, and hyphens. Auto-generated if left empty.</div>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Store Description</label>
              <textarea name="store_description" rows="3" class="form-control" placeholder="Describe your store..."><?= e($store['store_description'] ?? '') ?></textarea>
            </div>
          </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
          <div class="card-header bg-white"><h6 class="fw-bold mb-0">Branding</h6></div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label fw-semibold">Logo URL</label>
              <input type="url" name="logo_url" value="<?= e($store['logo_url'] ?? '') ?>" class="form-control" placeholder="https://example.com/logo.png">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Banner URL</label>
              <input type="url" name="banner_url" value="<?= e($store['banner_url'] ?? '') ?>" class="form-control" placeholder="https://example.com/banner.jpg">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Theme Color</label>
              <div class="d-flex align-items-center gap-3">
                <input type="color" name="theme_color" value="<?= e($store['theme_color'] ?? '#0d6efd') ?>" class="form-control form-control-color" style="width:60px;height:40px;">
                <span class="text-muted small">Choose your store's primary color</span>
              </div>
            </div>
          </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
          <div class="card-header bg-white"><h6 class="fw-bold mb-0">Custom Domain (Enterprise)</h6></div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label fw-semibold">Custom Domain</label>
              <input type="text" name="custom_domain" value="<?= e($store['custom_domain'] ?? '') ?>" class="form-control" placeholder="store.yourdomain.com">
              <div class="form-text"><i class="bi bi-gem text-warning"></i> Custom domains are available for Enterprise plan subscribers.</div>
            </div>
          </div>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $store ? 'Save Settings' : 'Create Store' ?></button>
          <a href="<?= APP_URL ?>/pages/dropshipping/index.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
