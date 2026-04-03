<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();

$db     = getDB();
$status = get('status', '');
$page   = max(1, (int)get('page', 1));
$limit  = 50;
$offset = ($page - 1) * $limit;

$shipments = [];
$total     = 0;

try {
    $where  = ['1=1'];
    $params = [];
    if ($status) {
        $where[] = 'ps.status = ?';
        $params[] = $status;
    }
    $whereStr = implode(' AND ', $where);

    $countStmt = $db->prepare(
        "SELECT COUNT(*) FROM parcel_shipments ps WHERE $whereStr"
    );
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare(
        "SELECT ps.*, u.first_name, u.last_name, u.email
         FROM parcel_shipments ps
         LEFT JOIN users u ON u.id = ps.user_id
         WHERE $whereStr
         ORDER BY ps.created_at DESC
         LIMIT $limit OFFSET $offset"
    );
    $stmt->execute($params);
    $shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Tables may not exist yet
}

$statusColors = [
    'pending'          => 'warning',
    'processing'       => 'info',
    'picked_up'        => 'info',
    'in_transit'       => 'primary',
    'out_for_delivery' => 'primary',
    'delivered'        => 'success',
    'failed'           => 'danger',
    'cancelled'        => 'secondary',
    'returned'         => 'dark',
];

$allStatuses = ['pending', 'processing', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'failed', 'cancelled', 'returned'];
$pages       = $limit > 0 && $total > 0 ? (int)ceil($total / $limit) : 1;

$pageTitle = 'Parcel Shipments';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-box-seam text-primary me-2"></i>Parcel Shipments</h3>
        <div class="d-flex gap-2">
            <a href="/pages/admin/logistics/parcels.php?export=csv" class="btn btn-outline-success btn-sm">
                <i class="bi bi-download me-1"></i>Export CSV
            </a>
            <a href="/pages/admin/logistics/index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Overview
            </a>
        </div>
    </div>

    <!-- Quick Nav -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="/pages/admin/logistics/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Overview</a>
        <a href="/pages/admin/logistics/parcels.php" class="btn btn-primary btn-sm"><i class="bi bi-box-seam me-1"></i>Parcels</a>
        <a href="/pages/admin/logistics/carriers.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-person-badge me-1"></i>Carriers</a>
        <a href="/pages/admin/logistics/carry-requests.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left-right me-1"></i>Carry Requests</a>
        <a href="/pages/admin/logistics/rates.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-currency-dollar me-1"></i>Rates</a>
    </div>

    <!-- Status Filters -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="?" class="btn btn-sm <?= $status === '' ? 'btn-primary' : 'btn-outline-secondary' ?>">All</a>
        <?php foreach ($allStatuses as $s): ?>
        <a href="?status=<?= $s ?>" class="btn btn-sm <?= $status === $s ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <?= ucfirst(str_replace('_', ' ', $s)) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
            <span class="small text-muted"><?= $total ?> shipment<?= $total !== 1 ? 's' : '' ?> found</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Tracking #</th>
                        <th>Sender → Receiver</th>
                        <th>Route</th>
                        <th>Method</th>
                        <th>Weight</th>
                        <th>Cost</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($shipments)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No shipments found.</td></tr>
                <?php endif; ?>
                <?php foreach ($shipments as $s): ?>
                <tr>
                    <td><strong><?= e($s['tracking_number']) ?></strong></td>
                    <td>
                        <div class="fw-semibold"><?= e($s['sender_name']) ?></div>
                        <i class="bi bi-arrow-down text-muted"></i>
                        <div><?= e($s['receiver_name']) ?></div>
                        <?php if (!empty($s['email'])): ?>
                        <small class="text-muted"><?= e($s['email']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="small text-nowrap">
                        <?= e($s['sender_city'] ?? '?') ?> (<?= e($s['sender_country'] ?? '') ?>)
                        <i class="bi bi-arrow-right"></i>
                        <?= e($s['receiver_city'] ?? '?') ?> (<?= e($s['receiver_country'] ?? '') ?>)
                    </td>
                    <td><?= e(ucfirst($s['shipping_method'] ?? '—')) ?></td>
                    <td><?= isset($s['weight']) ? e($s['weight']) . ' kg' : '—' ?></td>
                    <td><?= isset($s['shipping_cost']) ? formatMoney($s['shipping_cost']) : '—' ?></td>
                    <td>
                        <span class="badge bg-<?= $statusColors[$s['status']] ?? 'secondary' ?>">
                            <?= e(ucfirst(str_replace('_', ' ', $s['status']))) ?>
                        </span>
                    </td>
                    <td class="text-nowrap"><?= formatDate($s['created_at']) ?></td>
                    <td>
                        <a href="/pages/shipment/tracking.php?number=<?= urlencode($s['tracking_number']) ?>"
                           class="btn btn-sm btn-outline-primary" title="Track">
                            <i class="bi bi-geo-alt"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($pages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>&status=<?= e($status) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
