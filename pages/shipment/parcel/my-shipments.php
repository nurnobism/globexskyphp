<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireLogin();

$db     = getDB();
$userId = $_SESSION['user_id'];

$allowedStatuses = ['all', 'in_transit', 'delivered', 'cancelled', 'pending'];
$statusFilter    = in_array($_GET['status'] ?? '', $allowedStatuses) ? $_GET['status'] : 'all';

$shipments = [];
try {
    if ($statusFilter === 'all') {
        $stmt = $db->prepare(
            "SELECT * FROM parcel_shipments WHERE user_id = ? ORDER BY created_at DESC"
        );
        $stmt->execute([$userId]);
    } else {
        $stmt = $db->prepare(
            "SELECT * FROM parcel_shipments WHERE user_id = ? AND status = ? ORDER BY created_at DESC"
        );
        $stmt->execute([$userId, $statusFilter]);
    }
    $shipments = $stmt->fetchAll();
} catch (Exception $e) {
    $shipments = [];
}

$statusBadges = [
    'pending'          => 'warning',
    'processing'       => 'info',
    'picked_up'        => 'info',
    'in_transit'       => 'primary',
    'out_for_delivery' => 'primary',
    'delivered'        => 'success',
    'failed'           => 'danger',
    'cancelled'        => 'danger',
    'returned'         => 'secondary',
];

$pageTitle = 'My Shipments';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-box-seam text-primary me-2"></i>My Shipments</h3>
        <a href="/pages/shipment/parcel/create.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> New Shipment
        </a>
    </div>

    <!-- Status filter tabs -->
    <ul class="nav nav-pills mb-4">
        <?php foreach ([
            'all'        => 'All',
            'pending'    => 'Pending',
            'in_transit' => 'In Transit',
            'delivered'  => 'Delivered',
            'cancelled'  => 'Cancelled',
        ] as $val => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $statusFilter === $val ? 'active' : '' ?>"
               href="?status=<?= $val ?>">
                <?= $label ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <?php if (empty($shipments)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-box text-muted display-3"></i>
            <h5 class="mt-3 text-muted">No shipments found</h5>
            <p class="text-muted">
                <?= $statusFilter === 'all'
                    ? "You haven't created any shipments yet."
                    : "No shipments with status <strong>" . e(str_replace('_', ' ', $statusFilter)) . "</strong>." ?>
            </p>
            <a href="/pages/shipment/parcel/create.php" class="btn btn-primary mt-2">
                <i class="bi bi-plus-circle me-1"></i> Create First Shipment
            </a>
        </div>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tracking #</th>
                        <th>Date</th>
                        <th>Route</th>
                        <th>Method</th>
                        <th>Weight</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shipments as $s): ?>
                    <tr>
                        <td>
                            <span class="font-monospace fw-semibold"><?= e($s['tracking_number']) ?></span>
                        </td>
                        <td class="text-muted small">
                            <?= e(date('M j, Y', strtotime($s['created_at']))) ?>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                <?= e(strtoupper($s['sender_country'] ?? '—')) ?>
                            </span>
                            <i class="bi bi-arrow-right text-muted mx-1"></i>
                            <span class="badge bg-light text-dark border">
                                <?= e(strtoupper($s['receiver_country'] ?? '—')) ?>
                            </span>
                        </td>
                        <td class="text-capitalize"><?= e($s['shipping_method'] ?? '—') ?></td>
                        <td><?= $s['weight'] !== null ? e($s['weight']) . ' kg' : '—' ?></td>
                        <td>
                            <?php $badge = $statusBadges[$s['status']] ?? 'secondary'; ?>
                            <span class="badge bg-<?= $badge ?>">
                                <?= e(ucwords(str_replace('_', ' ', $s['status']))) ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="d-flex gap-2 justify-content-end">
                                <a href="/pages/shipment/parcel/tracking.php?tracking=<?= urlencode($s['tracking_number']) ?>"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-geo-alt me-1"></i>Track
                                </a>
                                <?php if ($s['status'] === 'pending'): ?>
                                <form method="POST" action="/api/parcels.php?action=cancel"
                                      onsubmit="return confirm('Cancel this shipment?')"
                                      class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="shipment_id" value="<?= (int)$s['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-x-circle me-1"></i>Cancel
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
