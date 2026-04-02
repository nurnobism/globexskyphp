<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();
$db = getDB();
$stmt = $db->query("SELECT setting_key, setting_value FROM platform_settings WHERE setting_key LIKE 'tax_%'");
$s = [];
foreach ($stmt->fetchAll() as $r) { $s[$r['setting_key']] = $r['setting_value']; }
$pageTitle = 'Tax Configuration';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-receipt-cutoff text-danger me-2"></i>Tax Configuration</h3>
        <a href="/pages/admin/settings.php" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="/api/admin.php?action=update_settings">
                        <?= csrfField() ?>
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="tax_enabled" id="taxEnabled" <?= ($s['tax_enabled']??'0')==='1'?'checked':'' ?>>
                                    <label class="form-check-label fw-semibold" for="taxEnabled">Enable Tax Calculation</label>
                                </div>
                            </div>
                            <div class="col-md-4"><label class="form-label fw-semibold">Default Tax Rate (%)</label><input type="number" name="tax_default_rate" class="form-control" step="0.01" min="0" max="100" value="<?= e($s['tax_default_rate']??'0') ?>"></div>
                            <div class="col-md-4"><label class="form-label fw-semibold">Tax Label</label><input type="text" name="tax_label" class="form-control" value="<?= e($s['tax_label']??'VAT') ?>" placeholder="e.g., VAT, GST, Sales Tax"></div>
                            <div class="col-md-4"><label class="form-label fw-semibold">Tax Inclusive Pricing</label><select name="tax_inclusive" class="form-select"><option value="0" <?= ($s['tax_inclusive']??'0')==='0'?'selected':'' ?>>Exclusive (added at checkout)</option><option value="1" <?= ($s['tax_inclusive']??'')==='1'?'selected':'' ?>>Inclusive (price includes tax)</option></select></div>
                            <div class="col-12"><label class="form-label fw-semibold">Tax Registration Number</label><input type="text" name="tax_reg_number" class="form-control" value="<?= e($s['tax_reg_number']??'') ?>" placeholder="Your business tax ID / VAT number"></div>
                        </div>
                        <div class="mt-4"><button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i>Save</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
