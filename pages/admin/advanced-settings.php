<?php
/**
 * pages/admin/advanced-settings.php — Advanced Admin Settings (Phase 9)
 */
require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/feature_toggles.php';
requireRole(['admin', 'super_admin']);

$db = getDB();

// Load feature toggles
$features = getAllFeatureToggles();

// System health checks
$health = [];
try {
    $db->query('SELECT 1');
    $health['database'] = ['status' => 'ok', 'label' => 'Database'];
} catch (Exception $e) {
    $health['database'] = ['status' => 'error', 'label' => 'Database'];
}

$health['php'] = ['status' => 'ok', 'label' => 'PHP ' . PHP_VERSION];
$health['disk'] = [
    'status' => disk_free_space('/') > 500 * 1024 * 1024 ? 'ok' : 'warning',
    'label'  => 'Disk: ' . round(disk_free_space('/') / 1024 / 1024 / 1024, 1) . ' GB free'
];

$deepseekKey = getenv('DEEPSEEK_API_KEY');
$health['ai'] = [
    'status' => !empty($deepseekKey) ? 'ok' : 'warning',
    'label'  => 'DeepSeek AI ' . (!empty($deepseekKey) ? 'configured' : 'not configured')
];

$pageTitle = 'Advanced Admin Settings';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-gear-wide-connected text-primary me-2"></i>Advanced Settings</h3>
        <a href="/pages/admin/dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Dashboard
        </a>
    </div>

    <div class="row g-4">
        <!-- System Health -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-heart-pulse-fill text-danger me-2"></i>System Health</h6>
                </div>
                <div class="card-body">
                    <?php foreach ($health as $key => $item): ?>
                    <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                        <span><?= e($item['label']) ?></span>
                        <?php $color = $item['status'] === 'ok' ? 'success' : ($item['status'] === 'warning' ? 'warning' : 'danger'); ?>
                        <span class="badge bg-<?= $color ?>">
                            <i class="bi bi-<?= $item['status'] === 'ok' ? 'check-circle' : 'exclamation-triangle' ?> me-1"></i>
                            <?= ucfirst($item['status']) ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Feature Toggles -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-toggles text-primary me-2"></i>Feature Toggles</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($features)): ?>
                        <p class="text-muted">No feature toggles configured.</p>
                    <?php else: ?>
                    <form method="POST" action="/api/admin.php?action=toggle_feature">
                        <?= csrfField() ?>
                        <?php foreach ($features as $feat): ?>
                        <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                            <div>
                                <span class="fw-semibold"><?= e(str_replace('_', ' ', ucfirst($feat['feature_name']))) ?></span>
                                <?php if ($feat['description']): ?>
                                    <br><small class="text-muted"><?= e($feat['description']) ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox"
                                       name="features[<?= e($feat['feature_name']) ?>]"
                                       value="1"
                                       <?= $feat['is_enabled'] ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i>Save Toggles
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-lightning-fill text-warning me-2"></i>Quick Admin Links</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php $links = [
                            ['/pages/admin/kyc-management.php', 'shield-check', 'success', 'KYC Management'],
                            ['/pages/admin/user-management.php', 'people-fill', 'primary', 'User Management'],
                            ['/pages/admin/ai-dashboard.php', 'robot', 'info', 'AI Dashboard'],
                            ['/pages/admin/settings.php', 'gear-fill', 'secondary', 'Platform Settings'],
                            ['/pages/admin/orders.php', 'bag-fill', 'warning', 'Orders'],
                            ['/pages/admin/products.php', 'box-seam-fill', 'danger', 'Products'],
                        ]; ?>
                        <?php foreach ($links as [$url, $icon, $color, $label]): ?>
                        <div class="col-6 col-md-2">
                            <a href="<?= $url ?>" class="text-decoration-none">
                                <div class="card border-0 bg-<?= $color ?> bg-opacity-10 text-center p-3 h-100">
                                    <i class="bi bi-<?= $icon ?> text-<?= $color ?> fs-3 mb-2"></i>
                                    <small class="fw-semibold text-<?= $color ?>"><?= $label ?></small>
                                </div>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
