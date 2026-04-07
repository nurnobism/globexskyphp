<?php
/**
 * pages/admin/billing/addons.php — Admin Add-On Configuration (PR #10)
 *
 * View/edit add-on catalog, view revenue stats.
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/addons.php';

requireAdmin();

$db = getDB();

// Handle activate/deactivate toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_addon'])) {
    if (verifyCsrf()) {
        $toggleId     = (int)($_POST['toggle_addon']);
        $toggleActive = (int)($_POST['active'] ?? 0);
        try {
            $db->prepare('UPDATE addons SET is_active = ? WHERE id = ?')->execute([$toggleActive, $toggleId]);
        } catch (PDOException $e) { /* ignore */ }
    }
    header('Location: /pages/admin/billing/addons.php?saved=1');
    exit;
}

// Load catalog (include inactive for admin)
$allAddons = [];
try {
    $stmt      = $db->query('SELECT * FROM addons ORDER BY sort_order ASC');
    $allAddons = $stmt->fetchAll() ?: [];
} catch (PDOException $e) { /* ignore */ }

// Revenue per add-on
$revenue = [];
try {
    $rStmt = $db->query('SELECT a.slug, a.name,
        COUNT(ap.id) AS purchase_count,
        COALESCE(SUM(ap.total_price), 0) AS total_revenue
        FROM addons a
        LEFT JOIN addon_purchases ap ON ap.addon_id = a.id AND ap.status != "cancelled"
        GROUP BY a.id
        ORDER BY total_revenue DESC');
    $revenue = $rStmt->fetchAll() ?: [];
} catch (PDOException $e) { /* ignore */ }

// Total add-on revenue
$totalRevenue = array_sum(array_column($revenue, 'total_revenue'));

$csrfToken = generateCsrf();
$pageTitle = 'Admin — Add-On Management';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-bag-plus text-primary me-2"></i>Add-On Management</h3>
        <a href="/pages/admin/billing/invoices.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-receipt me-1"></i>All Invoices
        </a>
    </div>

    <?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        Changes saved. <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Revenue Summary -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 bg-success text-white">
                <div class="card-body py-3 text-center">
                    <div class="fs-3 fw-bold">$<?= number_format($totalRevenue, 2) ?></div>
                    <div class="small opacity-75">Total Add-On Revenue</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 bg-primary text-white">
                <div class="card-body py-3 text-center">
                    <div class="fs-3 fw-bold"><?= count($allAddons) ?></div>
                    <div class="small opacity-75">Add-On Types</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 bg-info text-white">
                <div class="card-body py-3 text-center">
                    <div class="fs-3 fw-bold"><?= array_sum(array_column($revenue, 'purchase_count')) ?></div>
                    <div class="small opacity-75">Total Purchases</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add-On Catalog Table -->
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-bold">Add-On Catalog</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Icon</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Price</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allAddons as $addon): ?>
                        <tr>
                            <td><i class="bi <?= htmlspecialchars($addon['icon'], ENT_QUOTES) ?> fs-4 text-primary"></i></td>
                            <td>
                                <strong><?= htmlspecialchars($addon['name'], ENT_QUOTES) ?></strong>
                                <div class="text-muted small"><?= htmlspecialchars($addon['description'], ENT_QUOTES) ?></div>
                            </td>
                            <td><code class="small"><?= htmlspecialchars($addon['type'], ENT_QUOTES) ?></code></td>
                            <td class="fw-bold">$<?= number_format((float)$addon['price'], 2) ?></td>
                            <td><?= $addon['duration_days'] ? $addon['duration_days'] . ' days' : 'Permanent' ?></td>
                            <td>
                                <?php if ($addon['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                                    <input type="hidden" name="toggle_addon" value="<?= (int)$addon['id'] ?>">
                                    <input type="hidden" name="active" value="<?= $addon['is_active'] ? '0' : '1' ?>">
                                    <button type="submit" class="btn btn-sm <?= $addon['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                        <?= $addon['is_active'] ? 'Disable' : 'Enable' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($allAddons)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-3">No add-ons configured.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Revenue per Add-On -->
    <div class="card shadow-sm">
        <div class="card-header fw-bold">Revenue by Add-On</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Add-On</th>
                            <th>Purchases</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($revenue as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['name'], ENT_QUOTES) ?></td>
                            <td><?= (int)$r['purchase_count'] ?></td>
                            <td class="fw-bold text-success">$<?= number_format((float)$r['total_revenue'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($revenue)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">No revenue data yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
