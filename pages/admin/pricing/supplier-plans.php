<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();

$db = getDB();

// Load supplier plans from new table
$plans = [];
try {
    $stmt  = $db->query('SELECT sp.*,
        (SELECT COUNT(*) FROM plan_subscriptions ps WHERE ps.plan_id = sp.id AND ps.status = "active") AS subscriber_count
        FROM supplier_plans sp ORDER BY sp.sort_order ASC');
    $plans = $stmt->fetchAll();
    foreach ($plans as &$p) {
        $p['features_decoded'] = json_decode($p['features'] ?? '{}', true) ?: [];
        $p['limits_decoded']   = json_decode($p['limits'] ?? '{}', true) ?: [];
    }
    unset($p);
} catch (PDOException $e) {
    // Fallback to pricing_rules
    try {
        $plans = $db->query("SELECT * FROM pricing_rules WHERE category = 'supplier_plan' ORDER BY value ASC")->fetchAll();
    } catch (PDOException $e2) { /* ignore */ }
}

// Handle plan update
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_plan'])) {
    if (!verifyCsrf()) {
        $flash = ['type' => 'danger', 'msg' => 'Invalid CSRF token'];
    } else {
        $id       = (int)($_POST['plan_id'] ?? 0);
        $price    = (float)($_POST['price'] ?? 0);
        $isActive = (int)($_POST['is_active'] ?? 1);
        $commDisc = (float)($_POST['commission_discount'] ?? 0);
        try {
            $db->prepare('UPDATE supplier_plans SET price = ?, commission_discount = ?, is_active = ?, updated_at = NOW() WHERE id = ?')
               ->execute([$price, $commDisc, $isActive, $id]);
            $flash = ['type' => 'success', 'msg' => 'Plan updated successfully'];
        } catch (PDOException $e) {
            $flash = ['type' => 'danger', 'msg' => 'Error: ' . $e->getMessage()];
        }
    }
}

$pageTitle = 'Supplier Plans';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-building-fill text-success me-2"></i>Supplier Plans</h3>
        <a href="/pages/admin/pricing/index.php" class="btn btn-outline-secondary btn-sm">← Back</a>
    </div>

    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
        <?= e($flash['msg']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <?php if (empty($plans)): ?>
        <div class="col-12">
            <div class="alert alert-info">
                No plans found. Run <code>database/schema_v3.sql</code> to seed the default plans.
            </div>
        </div>
        <?php else: ?>
        <?php foreach ($plans as $p): ?>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 <?= !($p['is_active'] ?? 1) ? 'opacity-50' : '' ?>">
                <div class="card-header bg-<?= match($p['slug'] ?? ''){'pro'=>'primary','enterprise'=>'warning',default=>'success'} ?> text-<?= ($p['slug'] ?? '') === 'enterprise' ? 'dark' : 'white' ?> text-center py-3">
                    <h5 class="mb-0 fw-bold"><?= e($p['name']) ?></h5>
                    <h3 class="mt-1 mb-0">
                        <?= (float)$p['price'] > 0 ? '$' . number_format((float)$p['price']) . '<small class="fs-6 fw-normal">/mo</small>' : '<span class="fs-5">Free</span>' ?>
                    </h3>
                    <small><?= (int)($p['subscriber_count'] ?? 0) ?> active subscribers</small>
                </div>
                <div class="card-body">
                    <?php
                    $lim  = $p['limits_decoded'] ?? [];
                    $feat = $p['features_decoded'] ?? [];
                    ?>
                    <ul class="list-unstyled small mb-3">
                        <li><strong>Products:</strong> <?= ($lim['products'] ?? 10) === -1 ? 'Unlimited' : ($lim['products'] ?? 10) ?></li>
                        <li><strong>Images/product:</strong> <?= $lim['images_per_product'] ?? 3 ?></li>
                        <li><strong>Commission Discount:</strong> <?= (float)($p['commission_discount'] ?? 0) ?>%</li>
                        <li><strong>Dropshipping:</strong> <?= empty($lim['dropshipping']) ? '❌' : '✅' ?></li>
                        <li><strong>API Access:</strong> <?= empty($lim['api_access']) ? '❌' : '✅ ' . ucfirst((string)$lim['api_access']) ?></li>
                    </ul>

                    <button class="btn btn-sm btn-outline-primary w-100"
                            data-bs-toggle="modal"
                            data-bs-target="#editPlanModal"
                            data-id="<?= (int)$p['id'] ?>"
                            data-name="<?= e($p['name']) ?>"
                            data-price="<?= (float)$p['price'] ?>"
                            data-discount="<?= (float)($p['commission_discount'] ?? 0) ?>"
                            data-active="<?= (int)($p['is_active'] ?? 1) ?>">
                        <i class="bi bi-pencil me-1"></i> Edit Plan
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Plan Modal -->
<div class="modal fade" id="editPlanModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Plan: <span id="editPlanName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="plan_id" id="editPlanId">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Monthly Price ($)</label>
                            <input type="number" name="price" id="editPlanPrice" class="form-control" min="0" step="0.01">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Commission Discount (%)</label>
                            <input type="number" name="commission_discount" id="editPlanDiscount" class="form-control" min="0" max="100" step="0.01">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="is_active" id="editPlanActive" class="form-select">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_plan" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('editPlanModal')?.addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('editPlanName').textContent    = btn.dataset.name;
    document.getElementById('editPlanId').value            = btn.dataset.id;
    document.getElementById('editPlanPrice').value         = btn.dataset.price;
    document.getElementById('editPlanDiscount').value      = btn.dataset.discount;
    document.getElementById('editPlanActive').value        = btn.dataset.active;
});
</script>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
