<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();
$db = getDB();
$stmt = $db->query("SELECT setting_key, setting_value FROM platform_settings WHERE setting_key LIKE 'mail_%'");
$s = [];
foreach ($stmt->fetchAll() as $r) { $s[$r['setting_key']] = $r['setting_value']; }
$pageTitle = 'Email / SMTP Settings';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-envelope-fill text-primary me-2"></i>Email / SMTP Settings</h3>
        <a href="/pages/admin/settings.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="/api/admin.php?action=update_settings">
                        <?= csrfField() ?>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label fw-semibold">SMTP Host</label><input type="text" name="mail_host" class="form-control" value="<?= e($s['mail_host'] ?? '') ?>" placeholder="smtp.example.com"></div>
                            <div class="col-md-3"><label class="form-label fw-semibold">SMTP Port</label><input type="number" name="mail_port" class="form-control" value="<?= e($s['mail_port'] ?? '587') ?>"></div>
                            <div class="col-md-3"><label class="form-label fw-semibold">Encryption</label><select name="mail_encryption" class="form-select"><option value="tls" <?= ($s['mail_encryption']??'tls')==='tls'?'selected':'' ?>>TLS</option><option value="ssl" <?= ($s['mail_encryption']??'')==='ssl'?'selected':'' ?>>SSL</option><option value="" <?= ($s['mail_encryption']??'')===''?'selected':'' ?>>None</option></select></div>
                            <div class="col-md-6"><label class="form-label fw-semibold">SMTP Username</label><input type="text" name="mail_username" class="form-control" value="<?= e($s['mail_username'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label fw-semibold">SMTP Password</label><input type="password" name="mail_password" class="form-control" placeholder="Leave blank to keep current"></div>
                            <div class="col-md-6"><label class="form-label fw-semibold">From Name</label><input type="text" name="mail_from_name" class="form-control" value="<?= e($s['mail_from_name'] ?? 'GlobexSky') ?>"></div>
                            <div class="col-md-6"><label class="form-label fw-semibold">From Email</label><input type="email" name="mail_from_address" class="form-control" value="<?= e($s['mail_from_address'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label fw-semibold">Reply-To Email</label><input type="email" name="mail_reply_to" class="form-control" value="<?= e($s['mail_reply_to'] ?? '') ?>"></div>
                        </div>
                        <div class="mt-4"><button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i>Save Settings</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
