<?php
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/commission.php';
requireAdmin();

$db = getDB();

// Load commission tiers
$tiers = [];
try {
    $stmt  = $db->query('SELECT * FROM commission_tiers ORDER BY min_monthly_sales ASC');
    $tiers = $stmt->fetchAll();
} catch (PDOException $e) {
    $tiers = getCommissionTiers();
}

// Load category commission rates
$catRates = [];
try {
    $stmt     = $db->query('SELECT ccr.*, c.name AS category_name FROM category_commission_rates ccr
        LEFT JOIN categories c ON c.id = ccr.category_id WHERE ccr.is_active = 1 ORDER BY c.name');
    $catRates = $stmt->fetchAll();
} catch (PDOException $e) { /* ignore */ }

// Load categories for override form
$categories = [];
try {
    $stmt       = $db->query('SELECT id, name FROM categories ORDER BY name');
    $categories = $stmt->fetchAll();
} catch (PDOException $e) { /* ignore */ }

// Handle tier save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_tiers'])) {
    if (!verifyCsrf()) {
        $flash = ['type' => 'danger', 'msg' => 'Invalid CSRF token'];
    } else {
        try {
            foreach ($_POST['tier'] ?? [] as $id => $data) {
                $id   = (int)$id;
                $rate = (float)($data['rate'] ?? 0);
                if ($id > 0 && $rate > 0) {
                    $db->prepare('UPDATE commission_tiers SET rate = ? WHERE id = ?')->execute([$rate, $id]);
                }
            }
            $flash = ['type' => 'success', 'msg' => 'Commission tiers updated successfully'];
        } catch (PDOException $e) {
            $flash = ['type' => 'danger', 'msg' => 'Error saving tiers: ' . $e->getMessage()];
        }
    }
}

// Handle category rate save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category_rate'])) {
    if (!verifyCsrf()) {
        $flash = ['type' => 'danger', 'msg' => 'Invalid CSRF token'];
    } else {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $rate       = (float)($_POST['rate'] ?? 0);
        if ($categoryId > 0 && $rate >= 0) {
            try {
                $check = $db->prepare('SELECT id FROM category_commission_rates WHERE category_id = ?');
                $check->execute([$categoryId]);
                if ($check->fetch()) {
                    $db->prepare('UPDATE category_commission_rates SET rate = ?, updated_at = NOW() WHERE category_id = ?')
                       ->execute([$rate, $categoryId]);
                } else {
                    $db->prepare('INSERT INTO category_commission_rates (category_id, rate) VALUES (?, ?)')->execute([$categoryId, $rate]);
                }
                $flash = ['type' => 'success', 'msg' => 'Category rate saved'];
            } catch (PDOException $e) {
                $flash = ['type' => 'danger', 'msg' => 'Error: ' . $e->getMessage()];
            }
        }
    }
}

// Handle category rate delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category_rate'])) {
    if (verifyCsrf()) {
        $catId = (int)($_POST['category_id'] ?? 0);
        try {
            $db->prepare('DELETE FROM category_commission_rates WHERE category_id = ?')->execute([$catId]);
            $flash = ['type' => 'success', 'msg' => 'Category rate removed'];
        } catch (PDOException $e) {
            $flash = ['type' => 'danger', 'msg' => 'Error: ' . $e->getMessage()];
        }
    }
}

// Reload after save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt  = $db->query('SELECT * FROM commission_tiers ORDER BY min_monthly_sales ASC');
        $tiers = $stmt->fetchAll();
    } catch (PDOException $e) { /* ignore */ }
    try {
        $stmt     = $db->query('SELECT ccr.*, c.name AS category_name FROM category_commission_rates ccr
            LEFT JOIN categories c ON c.id = ccr.category_id WHERE ccr.is_active = 1 ORDER BY c.name');
        $catRates = $stmt->fetchAll();
    } catch (PDOException $e) { /* ignore */ }
}

$pageTitle = 'Commission Management';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-percent text-primary me-2"></i>Commission Management</h3>
        <a href="/pages/admin/pricing/index.php" class="btn btn-outline-secondary btn-sm">← Back</a>
    </div>

    <?php if (isset($flash)): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
        <?= e($flash['msg']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Tier Rates -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light fw-semibold">
                    <i class="bi bi-layers me-2"></i>Commission Tiers
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Rates applied based on supplier's monthly sales volume.</p>
                    <form method="POST">
                        <?= csrfField() ?>
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr><th>Tier</th><th>Monthly Sales Range</th><th>Rate (%)</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($tiers as $t): ?>
                            <tr>
                                <td><strong><?= e($t['tier_name'] ?? 'Tier') ?></strong></td>
                                <td class="small">
                                    $<?= number_format((float)$t['min_monthly_sales']) ?>
                                    — <?= $t['max_monthly_sales'] ? '$' . number_format((float)$t['max_monthly_sales']) : 'Unlimited' ?>
                                </td>
                                <td style="width:130px">
                                    <div class="input-group input-group-sm">
                                        <input type="number" name="tier[<?= (int)$t['id'] ?>][rate]"
                                               class="form-control" value="<?= (float)$t['rate'] ?>"
                                               min="0" max="100" step="0.01" required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="submit" name="save_tiers" class="btn btn-primary btn-sm">
                            <i class="bi bi-save me-1"></i> Save Tiers
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Category Overrides -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light fw-semibold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-tag me-2"></i>Category-Specific Rates</span>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCatModal">
                        <i class="bi bi-plus"></i> Add Override
                    </button>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($catRates)): ?>
                    <p class="text-muted text-center py-4 small">No category overrides. Tier rates apply to all categories.</p>
                    <?php else: ?>
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Category</th><th>Rate</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($catRates as $cr): ?>
                        <tr>
                            <td><?= e($cr['category_name'] ?? 'Category #' . $cr['category_id']) ?></td>
                            <td><span class="badge bg-primary"><?= number_format((float)$cr['rate'], 1) ?>%</span></td>
                            <td>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Remove this override?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="category_id" value="<?= (int)$cr['category_id'] ?>">
                                    <button type="submit" name="delete_category_rate" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Effective Rates Preview -->
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light fw-semibold">
                    <i class="bi bi-eye me-2"></i>Effective Rate Summary
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ($tiers as $t): ?>
                        <div class="col-md-3">
                            <div class="card bg-light border-0 text-center p-3">
                                <div class="fw-bold"><?= e($t['tier_name']) ?></div>
                                <div class="display-6 fw-bold text-primary"><?= (float)$t['rate'] ?>%</div>
                                <div class="small text-muted">
                                    $<?= number_format((float)$t['min_monthly_sales']) ?>+/mo
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Category Rate Modal -->
<div class="modal fade" id="addCatModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Category Rate Override</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrfField() ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category *</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select category...</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Commission Rate (%) *</label>
                        <div class="input-group">
                            <input type="number" name="rate" class="form-control" required min="0" max="100" step="0.01">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_category_rate" class="btn btn-primary">Save Override</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
