<?php
/**
 * pages/admin/commission/categories.php — Category Rate Overrides (PR #8)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();

$db      = getDB();
$message = '';
$msgType = '';

// Handle inline save via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $catIds        = $_POST['category_id']   ?? [];
    $overrideRates = $_POST['override_rate'] ?? [];
    $isActives     = $_POST['is_active']     ?? [];
    $saved = 0;

    foreach ($catIds as $i => $rawCatId) {
        $categoryId   = (int)$rawCatId;
        $overrideRate = isset($overrideRates[$i]) ? (float)$overrideRates[$i] : -1;
        $isActive     = isset($isActives[$i]) ? 1 : 0;

        if ($categoryId <= 0 || $overrideRate < 0) {
            continue;
        }
        // Accept percent (8) or fraction (0.08); store as fraction
        $rateStored = $overrideRate > 1 ? round($overrideRate / 100, 6) : round($overrideRate, 6);

        try {
            $check = $db->prepare('SELECT id FROM category_commission_rates WHERE category_id = ?');
            $check->execute([$categoryId]);
            if ($check->fetchColumn()) {
                $db->prepare(
                    'UPDATE category_commission_rates
                     SET override_rate = ?, is_active = ?, updated_at = NOW()
                     WHERE category_id = ?'
                )->execute([$rateStored, $isActive, $categoryId]);
            } else {
                $db->prepare(
                    'INSERT INTO category_commission_rates (category_id, override_rate, is_active)
                     VALUES (?, ?, ?)'
                )->execute([$categoryId, $rateStored, $isActive]);
            }
            $saved++;
        } catch (PDOException $e) {
            // Try old schema column
            try {
                $check = $db->prepare('SELECT id FROM category_commission_rates WHERE category_id = ?');
                $check->execute([$categoryId]);
                if ($check->fetchColumn()) {
                    $db->prepare(
                        'UPDATE category_commission_rates SET rate = ?, is_active = ?, updated_at = NOW() WHERE category_id = ?'
                    )->execute([$rateStored, $isActive, $categoryId]);
                } else {
                    $db->prepare(
                        'INSERT INTO category_commission_rates (category_id, rate, is_active) VALUES (?, ?, ?)'
                    )->execute([$categoryId, $rateStored, $isActive]);
                }
                $saved++;
            } catch (PDOException $e2) { /* ignore */ }
        }
    }

    $message = "Saved $saved category rate(s).";
    $msgType = 'success';
}

// Load all root categories + overrides
$categories = [];
try {
    $stmt = $db->query(
        'SELECT c.id, c.name, c.slug,
                ccr.id AS ccr_id,
                COALESCE(ccr.override_rate, ccr.rate, NULL) AS override_rate,
                ccr.is_active AS ccr_active
         FROM categories c
         LEFT JOIN category_commission_rates ccr ON ccr.category_id = c.id
         ORDER BY c.name ASC'
    );
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    // categories table may not exist in all test environments
}

// Default tier rates for reference (to show "default if no override")
$defaultRate = 0.10; // Growth tier as reference

$pageTitle = 'Category Commission Rate Overrides';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-tags text-primary me-2"></i>Category Rate Overrides</h3>
        <a href="/pages/admin/commission/index.php" class="btn btn-outline-secondary btn-sm">← Dashboard</a>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $msgType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
        <?= e($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Legend -->
    <div class="d-flex gap-3 mb-3">
        <span class="badge bg-success">Override Active</span>
        <span class="badge bg-secondary">Default (tier rate)</span>
        <span class="badge bg-danger">Override Disabled</span>
    </div>

    <div class="card border-0 shadow-sm">
        <form method="POST">
            <?= csrfField() ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Override Rate (%)</th>
                            <th>Active</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">
                            No categories found. Add categories first.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                    <?php
                        $hasOverride   = $cat['override_rate'] !== null;
                        $isActive      = (int)($cat['ccr_active'] ?? 0);
                        $displayRate   = $hasOverride ? round((float)$cat['override_rate'] * 100, 2) : '';
                    ?>
                    <tr>
                        <input type="hidden" name="category_id[]" value="<?= (int)$cat['id'] ?>">
                        <td>
                            <strong><?= e($cat['name']) ?></strong>
                        </td>
                        <td>
                            <?php if ($hasOverride && $isActive): ?>
                                <span class="badge bg-success">Override Active</span>
                            <?php elseif ($hasOverride && !$isActive): ?>
                                <span class="badge bg-danger">Override Disabled</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Default (tier rate)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="input-group input-group-sm" style="max-width:160px">
                                <input type="number" name="override_rate[]"
                                       class="form-control form-control-sm"
                                       value="<?= $displayRate !== '' ? $displayRate : '' ?>"
                                       placeholder="e.g. 8.00"
                                       min="0" max="100" step="0.01">
                                <span class="input-group-text">%</span>
                            </div>
                        </td>
                        <td>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox"
                                       name="is_active[]"
                                       value="1"
                                       <?= ($hasOverride && $isActive) ? 'checked' : '' ?>>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($categories)): ?>
            <div class="card-footer bg-light text-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Save Category Rates
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Reference table -->
    <div class="alert alert-info mt-4 d-flex gap-2">
        <i class="bi bi-info-circle-fill fs-5 flex-shrink-0 mt-1"></i>
        <div>
            <strong>How category overrides work:</strong> When an override rate is set and active,
            it replaces the tier base rate for all orders in that category.
            The supplier's plan discount is still applied on top.
            Leave blank to use the supplier's GMV tier base rate.
            <br><strong>Common overrides:</strong> Electronics 8% · Fashion 15% · Food 10% · Industrial 7%
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
