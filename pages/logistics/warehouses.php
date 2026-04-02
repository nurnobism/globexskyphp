<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db = getDB();

$warehouses = [];
try {
    $warehouses = $db->query("
        SELECT * FROM warehouses ORDER BY name ASC
    ")->fetchAll();
} catch (\Exception $e) {
    $warehouses = [];
}

$pageTitle = 'Warehouses';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-building me-2"></i><?= e($pageTitle) ?></h1>
            <p class="text-muted mb-0">Warehouse locations, capacity, and status</p>
        </div>
        <div>
            <a href="/pages/logistics/index.php" class="btn btn-outline-secondary me-2"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
            <?php if (isAdmin()): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addWarehouseModal"><i class="bi bi-plus-circle me-1"></i>Add Warehouse</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Warehouses Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Location</th>
                            <th>Capacity</th>
                            <th class="text-center">Current Stock</th>
                            <th>Manager</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($warehouses)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">
                                <i class="bi bi-building display-6 d-block mb-2"></i>
                                No warehouses found.
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($warehouses as $wh):
                                $capacity = (int)($wh['capacity'] ?? 0);
                                $currentStock = (int)($wh['current_stock'] ?? 0);
                                $usagePct = $capacity > 0 ? round(($currentStock / $capacity) * 100) : 0;

                                if ($usagePct >= 90) {
                                    $barClass = 'danger';
                                } elseif ($usagePct >= 70) {
                                    $barClass = 'warning';
                                } else {
                                    $barClass = 'success';
                                }

                                $statusMap = [
                                    'active'      => 'success',
                                    'inactive'    => 'secondary',
                                    'maintenance' => 'warning',
                                ];
                                $statusBadge = $statusMap[$wh['status'] ?? ''] ?? 'secondary';
                            ?>
                                <tr>
                                    <td>
                                        <i class="bi bi-building text-primary me-1"></i>
                                        <span class="fw-semibold"><?= e($wh['name']) ?></span>
                                    </td>
                                    <td>
                                        <i class="bi bi-geo-alt me-1 text-muted"></i>
                                        <?= e(($wh['city'] ?? '') . ($wh['country'] ? ', ' . $wh['country'] : '')) ?>
                                    </td>
                                    <td style="min-width: 180px;">
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1 me-2" style="height: 10px;">
                                                <div class="progress-bar bg-<?= $barClass ?>" style="width: <?= $usagePct ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?= $usagePct ?>%</small>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark"><?= number_format($currentStock) ?> / <?= number_format($capacity) ?></span>
                                    </td>
                                    <td><?= e($wh['manager'] ?? '—') ?></td>
                                    <td><span class="badge bg-<?= $statusBadge ?>"><?= e(ucfirst($wh['status'] ?? 'unknown')) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if (isAdmin()): ?>
<!-- Add Warehouse Modal -->
<div class="modal fade" id="addWarehouseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Warehouse</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="/api/logistics.php?action=add_warehouse">
                <?= csrfField() ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" name="city" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Country</label>
                            <input type="text" class="form-control" name="country" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Capacity</label>
                        <input type="number" class="form-control" name="capacity" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Manager</label>
                        <input type="text" class="form-control" name="manager">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Add Warehouse</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
