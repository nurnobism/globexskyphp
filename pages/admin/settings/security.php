<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();
$db = getDB();
$stmt = $db->query("SELECT setting_key, setting_value FROM platform_settings WHERE setting_key LIKE 'security_%'");
$s = [];
foreach ($stmt->fetchAll() as $r) { $s[$r['setting_key']] = $r['setting_value']; }
$pageTitle = 'Security Settings';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-shield-lock-fill text-danger me-2"></i>Security Settings</h3>
        <a href="/pages/admin/settings.php" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="/api/admin.php?action=update_settings">
                        <?= csrfField() ?>
                        <h6 class="fw-bold mb-3">Authentication</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="security_2fa_enabled" id="2fa" <?= ($s['security_2fa_enabled']??'0')==='1'?'checked':'' ?>><label class="form-check-label fw-semibold" for="2fa">Enable Two-Factor Authentication (2FA)</label></div></div>
                            <div class="col-md-6"><label class="form-label fw-semibold">Session Lifetime (minutes)</label><input type="number" name="security_session_lifetime" class="form-control" min="5" value="<?= e($s['security_session_lifetime']??'120') ?>"></div>
                            <div class="col-md-6"><label class="form-label fw-semibold">Max Login Attempts</label><input type="number" name="security_max_login_attempts" class="form-control" min="1" value="<?= e($s['security_max_login_attempts']??'5') ?>"></div>
                            <div class="col-md-6"><label class="form-label fw-semibold">Lockout Duration (minutes)</label><input type="number" name="security_lockout_duration" class="form-control" min="1" value="<?= e($s['security_lockout_duration']??'30') ?>"></div>
                            <div class="col-md-6"><label class="form-label fw-semibold">Min Password Length</label><input type="number" name="security_min_password_length" class="form-control" min="6" value="<?= e($s['security_min_password_length']??'8') ?>"></div>
                        </div>
                        <h6 class="fw-bold mb-3">Rate Limiting</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="security_rate_limit_enabled" id="rateLimit" <?= ($s['security_rate_limit_enabled']??'1')==='1'?'checked':'' ?>><label class="form-check-label fw-semibold" for="rateLimit">Enable API Rate Limiting</label></div></div>
                            <div class="col-md-6"><label class="form-label fw-semibold">API Rate Limit (requests/minute)</label><input type="number" name="security_api_rate_limit" class="form-control" min="1" value="<?= e($s['security_api_rate_limit']??'60') ?>"></div>
                        </div>
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i>Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
