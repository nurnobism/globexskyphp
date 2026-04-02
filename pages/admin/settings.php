<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAdmin();

$db = getDB();
$stmt = $db->query('SELECT `key`, `value`, group_name FROM settings ORDER BY group_name, `key`');
$settings = [];
foreach ($stmt->fetchAll() as $row) { $settings[$row['key']] = $row['value']; }

$pageTitle = 'Admin — Settings';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-gear-fill text-primary me-2"></i>Platform Settings</h3>
        <a href="/pages/admin/dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
    </div>

    <form method="POST" action="/api/admin.php?action=settings">
        <?= csrfField() ?>
        <input type="hidden" name="_redirect" value="/pages/admin/settings.php">

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold">General Settings</h6></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Site Name</label>
                        <input type="text" name="site_name" class="form-control" value="<?= e($settings['site_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Site Tagline</label>
                        <input type="text" name="site_tagline" class="form-control" value="<?= e($settings['site_tagline'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Contact Email</label>
                        <input type="email" name="contact_email" class="form-control" value="<?= e($settings['contact_email'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Currency</label>
                        <input type="text" name="currency" class="form-control" value="<?= e($settings['currency'] ?? 'USD') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Currency Symbol</label>
                        <input type="text" name="currency_symbol" class="form-control" value="<?= e($settings['currency_symbol'] ?? '$') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Items Per Page</label>
                        <input type="number" name="items_per_page" class="form-control" value="<?= e($settings['items_per_page'] ?? '20') ?>" min="5" max="100">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Registration</label>
                        <select name="registration_enabled" class="form-select">
                            <option value="1" <?= ($settings['registration_enabled']??'1')==='1'?'selected':'' ?>>Enabled</option>
                            <option value="0" <?= ($settings['registration_enabled']??'1')==='0'?'selected':'' ?>>Disabled</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Maintenance Mode</label>
                        <select name="maintenance_mode" class="form-select">
                            <option value="0" <?= ($settings['maintenance_mode']??'0')==='0'?'selected':'' ?>>Off</option>
                            <option value="1" <?= ($settings['maintenance_mode']??'0')==='1'?'selected':'' ?>>On</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <button type="submit" class="btn btn-primary px-5"><i class="bi bi-check-circle me-1"></i> Save Settings</button>
    </form>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
