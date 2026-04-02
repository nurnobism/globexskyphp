<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();
$db = getDB();
$stmt = $db->query("SELECT setting_key, setting_value FROM platform_settings WHERE setting_key LIKE 'seo_%'");
$s = [];
foreach ($stmt->fetchAll() as $r) { $s[$r['setting_key']] = $r['setting_value']; }
$pageTitle = 'SEO Settings';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-search text-info me-2"></i>SEO Settings</h3>
        <a href="/pages/admin/settings.php" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="/api/admin.php?action=update_settings">
                        <?= csrfField() ?>
                        <div class="row g-3">
                            <div class="col-12"><label class="form-label fw-semibold">Site Title</label><input type="text" name="seo_site_title" class="form-control" value="<?= e($s['seo_site_title']??'GlobexSky — Global B2B Trade Platform') ?>"></div>
                            <div class="col-12"><label class="form-label fw-semibold">Meta Description</label><textarea name="seo_meta_description" class="form-control" rows="3" maxlength="160"><?= e($s['seo_meta_description']??'') ?></textarea><div class="form-text">Max 160 characters</div></div>
                            <div class="col-12"><label class="form-label fw-semibold">Meta Keywords</label><input type="text" name="seo_meta_keywords" class="form-control" value="<?= e($s['seo_meta_keywords']??'') ?>" placeholder="b2b, wholesale, trade, suppliers (comma separated)"></div>
                            <div class="col-md-6"><label class="form-label fw-semibold">Google Analytics ID</label><input type="text" name="seo_ga_id" class="form-control" value="<?= e($s['seo_ga_id']??'') ?>" placeholder="G-XXXXXXXXXX"></div>
                            <div class="col-md-6"><label class="form-label fw-semibold">Google Search Console</label><input type="text" name="seo_gsc_verification" class="form-control" value="<?= e($s['seo_gsc_verification']??'') ?>" placeholder="Verification meta tag content"></div>
                            <div class="col-12">
                                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="seo_robots_index" id="seoIndex" <?= ($s['seo_robots_index']??'1')==='1'?'checked':'' ?>><label class="form-check-label fw-semibold" for="seoIndex">Allow Search Engine Indexing</label></div>
                            </div>
                            <div class="col-12"><label class="form-label fw-semibold">Custom robots.txt content</label><textarea name="seo_robots_txt" class="form-control font-monospace small" rows="5"><?= e($s['seo_robots_txt']??"User-agent: *\nAllow: /") ?></textarea></div>
                        </div>
                        <div class="mt-4"><button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i>Save</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
