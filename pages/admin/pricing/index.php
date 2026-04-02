<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();

$db = getDB();

// Count pricing rules per category
$categories = ['commission', 'supplier_plan', 'inspection', 'dropship_markup', 'carry', 'api_platform', 'flash_sale', 'advertising'];
$counts = [];
foreach ($categories as $cat) {
    $s = $db->prepare("SELECT COUNT(*) FROM pricing_rules WHERE category = ? AND is_active = 1");
    $s->execute([$cat]);
    $counts[$cat] = (int)$s->fetchColumn();
}

// Recent pricing changes
$recent = $db->query("SELECT pr.*, u.first_name, u.last_name FROM pricing_history pr LEFT JOIN users u ON pr.changed_by = u.id ORDER BY pr.changed_at DESC LIMIT 10")->fetchAll();

$pageTitle = 'Pricing Management';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-currency-dollar text-primary me-2"></i>Pricing Management</h3>
        <a href="/pages/admin/dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Admin Dashboard
        </a>
    </div>

    <!-- Pricing Module Cards -->
    <div class="row g-3 mb-4">
        <?php $modules = [
            ['commission',      'commissions.php',        'percent',          'primary',   'Commissions',         'Seller & supplier commission rates'],
            ['supplier_plan',   'supplier-plans.php',     'building-fill',    'success',   'Supplier Plans',      'Subscription plan pricing'],
            ['inspection',      'inspection-pricing.php', 'clipboard-check',  'warning',   'Inspection Pricing',  'Inspection service fees'],
            ['dropship_markup', 'dropship-markup.php',    'shop',             'info',      'Dropship Markup',     'Dropshipping markup rules'],
            ['carry',           'carry-pricing.php',      'airplane-fill',    'secondary', 'Carry Pricing',       'Carry service rates'],
            ['api_platform',    'api-pricing.php',        'plug-fill',        'dark',      'API Pricing',         'API platform tier pricing'],
            ['flash_sale',      'flash-sale-fees.php',    'lightning-fill',   'danger',    'Flash Sale Fees',     'Flash sale participation fees'],
            ['advertising',     'ad-pricing.php',         'megaphone-fill',   'primary',   'Ad Pricing',          'Advertising rates and packages'],
        ]; ?>
        <?php foreach ($modules as [$cat, $url, $icon, $color, $title, $desc]): ?>
        <div class="col-6 col-md-3">
            <a href="/pages/admin/pricing/<?= $url ?>" class="card border-0 shadow-sm text-decoration-none h-100">
                <div class="card-body d-flex align-items-start gap-3 p-3">
                    <div class="rounded-circle bg-<?= $color ?> bg-opacity-10 p-2 flex-shrink-0">
                        <i class="bi bi-<?= $icon ?> text-<?= $color ?> fs-5"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1 text-dark"><?= $title ?></h6>
                        <p class="text-muted small mb-1"><?= $desc ?></p>
                        <span class="badge bg-light text-dark border"><?= $counts[$cat] ?> active rules</span>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Recent Changes -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2"></i>Recent Pricing Changes</h6>
        </div>
        <?php if (empty($recent)): ?>
        <div class="card-body text-center text-muted py-4">No pricing changes yet.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr><th>Category</th><th>Rule</th><th>Old Value</th><th>New Value</th><th>Changed By</th><th>Date</th></tr>
                </thead>
                <tbody>
                <?php foreach ($recent as $r): ?>
                <tr>
                    <td><span class="badge bg-secondary"><?= e(str_replace('_',' ',ucfirst($r['category']))) ?></span></td>
                    <td><?= e($r['rule_name'] ?? '—') ?></td>
                    <td class="text-muted"><?= e($r['old_value'] ?? '—') ?></td>
                    <td class="fw-semibold"><?= e($r['new_value'] ?? '—') ?></td>
                    <td><?= e(($r['first_name'] ?? 'System') . ' ' . ($r['last_name'] ?? '')) ?></td>
                    <td><?= formatDate($r['changed_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
