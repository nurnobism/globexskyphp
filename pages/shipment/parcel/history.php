<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];

$stmt = $db->prepare("SELECT p.*, sa.city AS from_city, ra.city AS to_city, ra.country AS to_country
    FROM parcels p
    LEFT JOIN addresses sa ON p.sender_address_id = sa.id
    LEFT JOIN addresses ra ON p.receiver_address_id = ra.id
    WHERE p.user_id = ? ORDER BY p.created_at DESC");
$stmt->execute([$userId]);
$parcels = $stmt->fetchAll();

$pageTitle = 'Parcel History';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-clock-history text-secondary me-2"></i>Parcel History</h3>
        <a href="/pages/shipment/parcel/create.php" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i> New Shipment
        </a>
    </div>

    <?php if (empty($parcels)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-inbox text-muted display-3"></i>
            <h5 class="mt-3 text-muted">No shipments yet</h5>
            <p class="text-muted">Your parcel shipment history will appear here.</p>
            <a href="/pages/shipment/parcel/create.php" class="btn btn-primary">Create First Shipment</a>
        </div>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tracking #</th>
                        <th>Route</th>
                        <th>Weight</th>
                        <th>Speed</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($parcels as $p): ?>
                <?php $colors = ['pending'=>'warning','processing'=>'info','in_transit'=>'primary','delivered'=>'success','cancelled'=>'danger']; ?>
                <tr>
                    <td><code><?= e($p['tracking_number']) ?></code></td>
                    <td>
                        <?= e($p['from_city'] ?? '—') ?>
                        <i class="bi bi-arrow-right text-muted mx-1"></i>
                        <?= e($p['to_city'] ?? '—') ?>, <?= e($p['to_country'] ?? '') ?>
                    </td>
                    <td><?= number_format($p['weight'], 1) ?> kg</td>
                    <td><span class="badge bg-light text-dark border"><?= ucfirst($p['speed'] ?? 'standard') ?></span></td>
                    <td><span class="badge bg-<?= $colors[$p['status']] ?? 'secondary' ?>"><?= ucfirst(str_replace('_',' ',$p['status'])) ?></span></td>
                    <td><?= formatDate($p['created_at']) ?></td>
                    <td>
                        <a href="/pages/shipment/parcel/tracking.php?tracking=<?= urlencode($p['tracking_number']) ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-geo-alt me-1"></i>Track
                        </a>
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
