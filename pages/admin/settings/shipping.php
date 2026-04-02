<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();
$db = getDB();
$stmt = $db->query("SELECT setting_key, setting_value FROM platform_settings WHERE setting_key LIKE 'shipping_%'");
$s = [];
foreach ($stmt->fetchAll() as $r) { $s[$r['setting_key']] = $r['setting_value']; }
$pageTitle = 'Shipping Configuration';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-truck-fill text-warning me-2"></i>Shipping Configuration</h3>
        <a href="/pages/admin/settings.php" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="/api/admin.php?action=update_settings">
                        <?= csrfField() ?>
                        <h6 class="fw-bold mb-3">Default Rates</h6>
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label fw-semibold">Standard Rate ($/kg)</label><input type="number" name="shipping_standard_rate" class="form-control" step="0.01" min="0" value="<?= e($s['shipping_standard_rate']??'8.50') ?>"></div>
                            <div class="col-md-4"><label class="form-label fw-semibold">Express Multiplier</label><input type="number" name="shipping_express_mult" class="form-control" step="0.01" min="1" value="<?= e($s['shipping_express_mult']??'1.6') ?>"></div>
                            <div class="col-md-4"><label class="form-label fw-semibold">Priority Multiplier</label><input type="number" name="shipping_priority_mult" class="form-control" step="0.01" min="1" value="<?= e($s['shipping_priority_mult']??'2.2') ?>"></div>
                            <div class="col-md-6"><label class="form-label fw-semibold">Free Shipping Threshold ($)</label><input type="number" name="shipping_free_threshold" class="form-control" step="0.01" min="0" value="<?= e($s['shipping_free_threshold']??'0') ?>" placeholder="0 = disabled"></div>
                            <div class="col-md-6"><label class="form-label fw-semibold">Default Currency</label><select name="shipping_currency" class="form-select"><option value="USD" <?= ($s['shipping_currency']??'USD')==='USD'?'selected':'' ?>>USD</option><option value="EUR" <?= ($s['shipping_currency']??'')==='EUR'?'selected':'' ?>>EUR</option><option value="GBP" <?= ($s['shipping_currency']??'')==='GBP'?'selected':'' ?>>GBP</option></select></div>
                        </div>
                        <hr class="my-3">
                        <h6 class="fw-bold mb-3">Restricted Items</h6>
                        <div class="mb-3"><label class="form-label fw-semibold">Prohibited Items (one per line)</label><textarea name="shipping_prohibited_items" class="form-control" rows="4"><?= e($s['shipping_prohibited_items']??'') ?></textarea></div>
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i>Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
