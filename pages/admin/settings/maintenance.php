<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();
$db = getDB();
$stmt = $db->query("SELECT setting_key, setting_value FROM platform_settings WHERE setting_key LIKE 'maintenance_%'");
$s = [];
foreach ($stmt->fetchAll() as $r) { $s[$r['setting_key']] = $r['setting_value']; }
$isActive = ($s['maintenance_enabled'] ?? '0') === '1';
$pageTitle = 'Maintenance Mode';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-wrench-fill text-warning me-2"></i>Maintenance Mode</h3>
        <a href="/pages/admin/settings.php" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <?php if ($isActive): ?>
            <div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <strong>Maintenance mode is currently ACTIVE.</strong> Only admins can access the site.
            </div>
            <?php endif; ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="/api/admin.php?action=update_settings">
                        <?= csrfField() ?>
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="maintenance_enabled" id="maintenanceEnabled" <?= $isActive?'checked':'' ?>>
                                    <label class="form-check-label fw-semibold fs-5" for="maintenanceEnabled">Enable Maintenance Mode</label>
                                </div>
                                <div class="form-text">When enabled, visitors will see the maintenance page. Admins can still log in.</div>
                            </div>
                            <div class="col-12"><label class="form-label fw-semibold">Maintenance Message</label><textarea name="maintenance_message" class="form-control" rows="3"><?= e($s['maintenance_message']??'We are currently performing scheduled maintenance. We will be back shortly!') ?></textarea></div>
                            <div class="col-md-6"><label class="form-label fw-semibold">Estimated End Time</label><input type="datetime-local" name="maintenance_end_time" class="form-control" value="<?= e($s['maintenance_end_time']??'') ?>"></div>
                            <div class="col-12"><label class="form-label fw-semibold">Allowed IP Addresses</label><textarea name="maintenance_allowed_ips" class="form-control font-monospace small" rows="3" placeholder="One IP per line (admins always allowed)"><?= e($s['maintenance_allowed_ips']??'') ?></textarea></div>
                        </div>
                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-<?= $isActive?'danger':'primary' ?> px-4">
                                <i class="bi bi-save me-1"></i><?= $isActive?'Update &amp; Keep Active':'Save Settings' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
