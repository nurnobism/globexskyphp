<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();

$db = getDB();
$rules = $db->query("SELECT * FROM pricing_rules WHERE category = 'flash_sale' ORDER BY sort_order ASC, created_at DESC")->fetchAll();

$pageTitle = 'Flash Sale Fees';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-lightning-fill text-danger me-2"></i>Flash Sale Fees</h3>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRuleModal">
                <i class="bi bi-plus-circle me-1"></i> Add Rule
            </button>
            <a href="/pages/admin/pricing/index.php" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Name</th><th>Type</th><th>Value</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php if (empty($rules)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No rules defined yet.</td></tr>
                <?php else: ?>
                <?php foreach ($rules as $r): ?>
                <tr>
                    <td><strong><?= e($r['name']) ?></strong><br><small class="text-muted"><?= e($r['description'] ?? '') ?></small></td>
                    <td><span class="badge bg-info"><?= e($r['value_type']) ?></span></td>
                    <td><?= $r['value_type'] === 'percentage' ? $r['value'] . '%' : '$' . number_format($r['value'], 2) ?></td>
                    <td><span class="badge bg-<?= $r['is_active'] ? 'success' : 'secondary' ?>"><?= $r['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                    <td>
                        <div class="d-flex gap-1">
                            <form method="POST" action="/api/pricing.php?action=toggle_rule">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-<?= $r['is_active'] ? 'secondary' : 'success' ?>"><?= $r['is_active'] ? 'Disable' : 'Enable' ?></button>
                            </form>
                            <form method="POST" action="/api/pricing.php?action=delete_rule" onsubmit="return confirm('Delete this rule?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="modal fade" id="addRuleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Rule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/api/pricing.php?action=create_rule">
                <?= csrfField() ?>
                <input type="hidden" name="category" value="flash_sale">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label fw-semibold">Name *</label><input type="text" name="name" class="form-control" required></div>
                        <div class="col-12"><label class="form-label fw-semibold">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Value Type *</label><select name="value_type" class="form-select" required><option value="percentage">Percentage</option><option value="fixed">Fixed Amount</option></select></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Value *</label><input type="number" name="value" class="form-control" required min="0" step="0.01"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
