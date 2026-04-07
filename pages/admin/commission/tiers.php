<?php
/**
 * pages/admin/commission/tiers.php — GMV Tier Configuration (PR #8)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();

$db      = getDB();
$message = '';
$msgType = '';

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $tierIds   = $_POST['tier_id']   ?? [];
    $baseRates = $_POST['base_rate'] ?? [];
    $tierNames = $_POST['tier_name'] ?? [];
    $minGmvs   = $_POST['min_gmv']   ?? [];
    $maxGmvs   = $_POST['max_gmv']   ?? [];
    $updated   = 0;

    foreach ($tierIds as $i => $rawId) {
        $id       = (int)$rawId;
        $rate     = isset($baseRates[$i]) ? round((float)$baseRates[$i] / 100, 6) : null;
        $name     = $tierNames[$i] ?? '';
        $minGmv   = isset($minGmvs[$i]) ? (float)$minGmvs[$i] : null;
        $maxGmvRaw = $maxGmvs[$i] ?? '';
        $maxGmv   = ($maxGmvRaw !== '' && $maxGmvRaw !== null) ? (float)$maxGmvRaw : null;

        if ($id > 0 && $rate !== null && $rate > 0 && $name !== '') {
            try {
                $db->prepare(
                    'UPDATE commission_tier_config
                     SET tier_name = ?, min_gmv = ?, max_gmv = ?, base_rate = ?, updated_at = NOW()
                     WHERE id = ?'
                )->execute([$name, $minGmv, $maxGmv, $rate, $id]);
                $updated++;
            } catch (PDOException $e) {
                // Fallback: old commission_tiers table
                try {
                    $pct = round($rate * 100, 4);
                    $db->prepare('UPDATE commission_tiers SET rate = ? WHERE id = ?')->execute([$pct, $id]);
                    $updated++;
                } catch (PDOException $e2) { /* ignore */ }
            }
        }
    }

    $message = "Saved $updated tier(s) successfully.";
    $msgType = 'success';
}

// Load tiers
$tiers = [];
try {
    $stmt = $db->query(
        'SELECT * FROM commission_tier_config WHERE is_active = 1 ORDER BY sort_order ASC, min_gmv ASC'
    );
    $tiers = $stmt->fetchAll();
} catch (PDOException $e) {
    // Fall back to commission_tiers
    try {
        $stmt = $db->query('SELECT *, rate/100 AS base_rate FROM commission_tiers WHERE is_active = 1 ORDER BY min_monthly_sales ASC');
        $rows = $stmt->fetchAll();
        foreach ($rows as $r) {
            $tiers[] = [
                'id'        => $r['id'],
                'tier_name' => $r['tier_name'],
                'min_gmv'   => $r['min_monthly_sales'],
                'max_gmv'   => $r['max_monthly_sales'],
                'base_rate' => $r['base_rate'],
            ];
        }
    } catch (PDOException $e2) { /* ignore */ }
}

// Hard-coded defaults if DB empty
if (empty($tiers)) {
    $tiers = [
        ['id' => 0, 'tier_name' => 'Starter',    'min_gmv' =>      0, 'max_gmv' =>   9999.99, 'base_rate' => 0.12],
        ['id' => 0, 'tier_name' => 'Growth',     'min_gmv' =>  10000, 'max_gmv' =>  49999.99, 'base_rate' => 0.10],
        ['id' => 0, 'tier_name' => 'Scale',      'min_gmv' =>  50000, 'max_gmv' => 199999.99, 'base_rate' => 0.08],
        ['id' => 0, 'tier_name' => 'Enterprise', 'min_gmv' => 200000, 'max_gmv' =>       null, 'base_rate' => 0.06],
    ];
}

$pageTitle = 'Commission Tier Configuration';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-layers text-primary me-2"></i>GMV Tier Configuration</h3>
        <a href="/pages/admin/commission/index.php" class="btn btn-outline-secondary btn-sm">← Dashboard</a>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $msgType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
        <?= e($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent">
            <strong>Commission Tiers</strong>
            <small class="text-muted ms-2">— Based on supplier's 90-day rolling GMV</small>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tier Name</th>
                            <th>Min GMV (90-day $)</th>
                            <th>Max GMV (90-day $)</th>
                            <th>Base Commission Rate (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tiers as $i => $tier): ?>
                    <tr>
                        <input type="hidden" name="tier_id[]" value="<?= (int)$tier['id'] ?>">
                        <td>
                            <input type="text" name="tier_name[]" class="form-control form-control-sm fw-semibold"
                                   value="<?= e($tier['tier_name']) ?>" required>
                        </td>
                        <td>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" name="min_gmv[]" class="form-control form-control-sm"
                                       value="<?= (float)$tier['min_gmv'] ?>"
                                       min="0" step="0.01" required>
                            </div>
                        </td>
                        <td>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" name="max_gmv[]" class="form-control form-control-sm"
                                       value="<?= $tier['max_gmv'] !== null ? (float)$tier['max_gmv'] : '' ?>"
                                       min="0" step="0.01"
                                       placeholder="∞ (no limit)">
                            </div>
                        </td>
                        <td>
                            <div class="input-group input-group-sm" style="max-width:160px">
                                <input type="number" name="base_rate[]" class="form-control form-control-sm"
                                       value="<?= round((float)$tier['base_rate'] * 100, 2) ?>"
                                       min="0" max="100" step="0.01" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-light text-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Save Tier Rates
                </button>
            </div>
        </form>
    </div>

    <!-- Info box -->
    <div class="alert alert-info mt-4 d-flex gap-2">
        <i class="bi bi-info-circle-fill fs-5 flex-shrink-0 mt-1"></i>
        <div>
            <strong>How tiers work:</strong> The supplier's 90-day rolling GMV (Gross Merchandise Value)
            determines their tier. A higher GMV earns a lower commission rate, incentivising volume.
            Category overrides and plan discounts are applied on top of the tier base rate.
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
