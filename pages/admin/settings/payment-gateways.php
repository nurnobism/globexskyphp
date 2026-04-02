<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();
$db = getDB();
$stmt = $db->query("SELECT setting_key, setting_value FROM platform_settings WHERE setting_key LIKE 'payment_%'");
$s = [];
foreach ($stmt->fetchAll() as $r) { $s[$r['setting_key']] = $r['setting_value']; }
$pageTitle = 'Payment Gateway Settings';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-credit-card-fill text-success me-2"></i>Payment Gateway Settings</h3>
        <a href="/pages/admin/settings.php" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
    <div class="row g-4">
        <?php $gateways = [['stripe','Stripe','bi-stripe','success'],['paypal','PayPal','bi-paypal','primary'],['razorpay','Razorpay','bi-credit-card','warning']]; ?>
        <?php foreach ($gateways as [$gw, $name, $icon, $color]): ?>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header d-flex align-items-center gap-2 py-3">
                    <i class="<?= $icon ?> text-<?= $color ?> fs-4"></i>
                    <h6 class="mb-0 fw-bold"><?= $name ?></h6>
                    <div class="ms-auto form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="<?= $gw ?>_enabled" <?= ($s["payment_{$gw}_enabled"]??'0')==='1'?'checked':'' ?>>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" action="/api/admin.php?action=update_settings">
                        <?= csrfField() ?>
                        <input type="hidden" name="payment_<?= $gw ?>_enabled" id="<?= $gw ?>_enabled_val" value="<?= ($s["payment_{$gw}_enabled"]??'0') ?>">
                        <div class="mb-2"><label class="form-label small fw-semibold">API Key</label><input type="password" name="payment_<?= $gw ?>_key" class="form-control form-control-sm" placeholder="•••••••••••••••"></div>
                        <div class="mb-2"><label class="form-label small fw-semibold">Secret Key</label><input type="password" name="payment_<?= $gw ?>_secret" class="form-control form-control-sm" placeholder="•••••••••••••••"></div>
                        <div class="mb-3"><label class="form-label small fw-semibold">Mode</label><select name="payment_<?= $gw ?>_mode" class="form-select form-select-sm"><option value="sandbox" <?= ($s["payment_{$gw}_mode"]??'sandbox')==='sandbox'?'selected':'' ?>>Sandbox</option><option value="live" <?= ($s["payment_{$gw}_mode"]??'')==='live'?'selected':'' ?>>Live</option></select></div>
                        <button type="submit" class="btn btn-primary btn-sm w-100">Save</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
