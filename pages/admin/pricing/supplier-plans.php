<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();

$db = getDB();
$plans = $db->query("SELECT * FROM pricing_rules WHERE category = 'supplier_plan' ORDER BY value ASC")->fetchAll();

$pageTitle = 'Supplier Subscription Plans';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-building-fill text-success me-2"></i>Supplier Subscription Plans</h3>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPlanModal">
                <i class="bi bi-plus-circle me-1"></i> Add Plan
            </button>
            <a href="/pages/admin/pricing/index.php" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
    </div>
    <div class="row g-3">
        <?php if (empty($plans)): ?>
        <div class="col-12"><div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">No plans defined yet.</div></div></div>
        <?php else: ?>
        <?php foreach ($plans as $p): ?>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 <?= !$p['is_active'] ? 'opacity-50' : '' ?>">
                <div class="card-header bg-success text-white text-center py-3">
                    <h5 class="mb-0 fw-bold"><?= e($p['name']) ?></h5>
                    <h3 class="mt-2 mb-0">$<?= number_format($p['value'], 2) ?><small class="fs-6 fw-normal">/mo</small></h3>
                </div>
                <div class="card-body">
                    <p class="text-muted"><?= e($p['description'] ?? '') ?></p>
                    <?php if ($p['metadata']): ?>
                    <?php $meta = json_decode($p['metadata'], true) ?? []; ?>
                    <ul class="list-unstyled small">
                        <?php foreach ($meta as $feature): ?>
                        <li class="mb-1"><i class="bi bi-check-circle-fill text-success me-2"></i><?= e($feature) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                    <div class="d-flex gap-2 mt-3">
                        <form method="POST" action="/api/pricing.php?action=toggle_rule">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button class="btn btn-sm btn-outline-<?= $p['is_active'] ? 'secondary' : 'success' ?>">
                                <?= $p['is_active'] ? 'Disable' : 'Enable' ?>
                            </button>
                        </form>
                        <form method="POST" action="/api/pricing.php?action=delete_rule" onsubmit="return confirm('Delete this plan?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="addPlanModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Supplier Plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/api/pricing.php?action=create_rule">
                <?= csrfField() ?>
                <input type="hidden" name="category" value="supplier_plan">
                <input type="hidden" name="value_type" value="fixed">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label fw-semibold">Plan Name *</label><input type="text" name="name" class="form-control" required placeholder="e.g., Starter, Professional, Enterprise"></div>
                        <div class="col-12"><label class="form-label fw-semibold">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Monthly Price ($) *</label><input type="number" name="value" class="form-control" required min="0" step="0.01"></div>
                        <div class="col-12"><label class="form-label fw-semibold">Features (JSON array)</label><textarea name="metadata" class="form-control font-monospace small" rows="4" placeholder='["Unlimited products","Priority support","Analytics dashboard"]'></textarea></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
