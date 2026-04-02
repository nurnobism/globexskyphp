<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db = getDB();

$shipmentCount = $db->query("SELECT COUNT(*) AS cnt FROM shipments")->fetch();
$activeShipments = $db->query("SELECT COUNT(*) AS cnt FROM shipments WHERE status NOT IN ('delivered','cancelled')")->fetch();

$avgDelivery = $db->query("
    SELECT AVG(DATEDIFF(estimated_delivery, created_at)) AS avg_days
    FROM shipments WHERE estimated_delivery IS NOT NULL
")->fetch();

$recentShipments = $db->query("
    SELECT s.*, o.order_number
    FROM shipments s
    JOIN orders o ON s.order_id = o.id
    ORDER BY s.created_at DESC
    LIMIT 10
")->fetchAll();

$pageTitle = 'Logistics Dashboard';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-truck me-2"></i><?= e($pageTitle) ?></h1>
            <p class="text-muted mb-0">Manage shipments, routes, and warehouses</p>
        </div>
        <div>
            <a href="/pages/logistics/routes.php" class="btn btn-outline-primary me-2"><i class="bi bi-signpost-split me-1"></i>Routes</a>
            <a href="/pages/logistics/warehouses.php" class="btn btn-outline-secondary"><i class="bi bi-building me-1"></i>Warehouses</a>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex p-3 mb-2">
                        <i class="bi bi-box-seam fs-3 text-primary"></i>
                    </div>
                    <h4 class="mb-0"><?= number_format($shipmentCount['cnt']) ?></h4>
                    <small class="text-muted">Total Shipments</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex p-3 mb-2">
                        <i class="bi bi-building fs-3 text-success"></i>
                    </div>
                    <h4 class="mb-0"><?= number_format($activeShipments['cnt']) ?></h4>
                    <small class="text-muted">Active Shipments</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="bg-warning bg-opacity-10 rounded-circle d-inline-flex p-3 mb-2">
                        <i class="bi bi-signpost-split fs-3 text-warning"></i>
                    </div>
                    <h4 class="mb-0">—</h4>
                    <small class="text-muted">Active Routes</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="bg-info bg-opacity-10 rounded-circle d-inline-flex p-3 mb-2">
                        <i class="bi bi-clock-history fs-3 text-info"></i>
                    </div>
                    <h4 class="mb-0"><?= $avgDelivery['avg_days'] ? number_format($avgDelivery['avg_days'], 1) : '—' ?></h4>
                    <small class="text-muted">Avg Delivery Days</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title mb-3"><i class="bi bi-lightning me-2"></i>Quick Actions</h6>
                    <a href="/pages/logistics/routes.php" class="btn btn-sm btn-outline-primary me-2"><i class="bi bi-plus-circle me-1"></i>View Routes</a>
                    <a href="/pages/logistics/warehouses.php" class="btn btn-sm btn-outline-success me-2"><i class="bi bi-building me-1"></i>View Warehouses</a>
                    <a href="/pages/order/index.php" class="btn btn-sm btn-outline-warning me-2"><i class="bi bi-box me-1"></i>Manage Orders</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Shipments Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0"><i class="bi bi-clock-history me-2"></i>Recent Shipments</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Order #</th>
                            <th>Tracking Number</th>
                            <th>Carrier</th>
                            <th>Status</th>
                            <th>Est. Delivery</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentShipments)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No shipments found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentShipments as $ship):
                                $statusMap = [
                                    'pending'    => 'secondary',
                                    'shipped'    => 'primary',
                                    'in_transit' => 'info',
                                    'delivered'  => 'success',
                                    'cancelled'  => 'danger',
                                ];
                                $badgeClass = $statusMap[$ship['status']] ?? 'secondary';
                            ?>
                                <tr>
                                    <td class="fw-semibold"><?= e($ship['order_number']) ?></td>
                                    <td><code><?= e($ship['tracking_number'] ?? '—') ?></code></td>
                                    <td><?= e($ship['carrier'] ?? '—') ?></td>
                                    <td><span class="badge bg-<?= $badgeClass ?>"><?= e(ucfirst(str_replace('_', ' ', $ship['status']))) ?></span></td>
                                    <td><?= $ship['estimated_delivery'] ? formatDate($ship['estimated_delivery']) : '—' ?></td>
                                    <td><small class="text-muted"><?= formatDate($ship['created_at']) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
