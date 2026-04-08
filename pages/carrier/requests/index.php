<?php
/**
 * pages/carrier/requests/index.php — Carrier Request Management — PR #16
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/carry.php';
requireAuth();

$userId   = (int)$_SESSION['user_id'];
$tripId   = (int)($_GET['trip_id'] ?? 0);
$statusTab = trim($_GET['status'] ?? 'pending');

// Load requests depending on context
$pendingRequests   = [];
$acceptedRequests  = [];
$historyRequests   = [];

try {
    $db = getDB();

    $baseJoin = 'FROM carry_requests cr
                 JOIN carry_trips ct ON ct.id = cr.trip_id
                 JOIN users u ON u.id = cr.buyer_id
                 WHERE ct.carrier_id = ?';
    $params = [$userId];

    if ($tripId) {
        $baseJoin .= ' AND cr.trip_id = ?';
        $params[] = $tripId;
    }

    // Pending
    $stmt = $db->prepare("SELECT cr.*, ct.origin_city, ct.origin_country, ct.destination_city, ct.destination_country,
                                  ct.departure_date, u.first_name, u.last_name, u.email, u.avatar
                          $baseJoin AND cr.status = 'pending'
                          ORDER BY cr.created_at ASC");
    $stmt->execute($params);
    $pendingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Accepted (in-progress)
    $stmt = $db->prepare("SELECT cr.*, ct.origin_city, ct.origin_country, ct.destination_city, ct.destination_country,
                                  ct.departure_date, u.first_name, u.last_name, u.email, u.avatar
                          $baseJoin AND cr.status IN ('accepted','picked_up','in_transit')
                          ORDER BY cr.updated_at ASC");
    $stmt->execute($params);
    $acceptedRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // History (completed/declined/cancelled/delivered)
    $stmt = $db->prepare("SELECT cr.*, ct.origin_city, ct.origin_country, ct.destination_city, ct.destination_country,
                                  ct.departure_date, u.first_name, u.last_name, u.email, u.avatar,
                                  COALESCE(cr2.rating, NULL) AS buyer_rating, COALESCE(cr2.review, NULL) AS buyer_review
                          $baseJoin AND cr.status IN ('delivered','completed','declined','cancelled','disputed')
                          LEFT JOIN carry_ratings cr2 ON cr2.request_id = cr.id
                          ORDER BY cr.updated_at DESC
                          LIMIT 50");
    $stmt->execute($params);
    $historyRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Tables may not exist yet
}

// Get trip name for context header
$contextTrip = $tripId ? getTrip($tripId) : null;

$statusColors = ['pending'=>'warning','accepted'=>'primary','picked_up'=>'info','in_transit'=>'info','delivered'=>'success','completed'=>'success','declined'=>'danger','cancelled'=>'secondary'];
$pageTitle    = 'Carry Requests';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-0"><i class="bi bi-inbox text-primary me-2"></i>Carry Requests</h3>
            <?php if ($contextTrip): ?>
                <small class="text-muted">
                    Filtering for: <?= htmlspecialchars("{$contextTrip['origin_city']} → {$contextTrip['destination_city']}") ?>
                    — <a href="/pages/carrier/requests/index.php">Show all trips</a>
                </small>
            <?php endif; ?>
        </div>
        <a href="/pages/carrier/dashboard.php" class="btn btn-outline-primary">
            <i class="bi bi-speedometer2 me-1"></i> Dashboard
        </a>
    </div>

    <!-- Summary badges -->
    <div class="d-flex gap-3 mb-4">
        <span class="badge bg-warning text-dark fs-6 px-3 py-2">
            <i class="bi bi-clock me-1"></i> <?= count($pendingRequests) ?> Pending
        </span>
        <span class="badge bg-primary fs-6 px-3 py-2">
            <i class="bi bi-arrow-right-circle me-1"></i> <?= count($acceptedRequests) ?> In Progress
        </span>
        <span class="badge bg-secondary fs-6 px-3 py-2">
            <i class="bi bi-archive me-1"></i> <?= count($historyRequests) ?> Historical
        </span>
    </div>

    <!-- Pending Requests -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-warning bg-opacity-10 fw-bold">
            <i class="bi bi-clock text-warning me-1"></i> Incoming Requests (<?= count($pendingRequests) ?>)
        </div>
        <?php if (!empty($pendingRequests)): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Buyer</th>
                            <th>Trip</th>
                            <th>Package Details</th>
                            <th>Offered Price</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingRequests as $pr): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($pr['avatar'])): ?>
                                            <img src="<?= htmlspecialchars($pr['avatar']) ?>" class="rounded-circle me-2" width="32" height="32" alt="Buyer" style="object-fit:cover">
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-semibold small"><?= htmlspecialchars(($pr['first_name'] ?? '') . ' ' . ($pr['last_name'] ?? '')) ?></div>
                                            <div class="text-muted" style="font-size:.75rem"><?= htmlspecialchars($pr['email'] ?? '') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="small">
                                    <?= htmlspecialchars("{$pr['origin_city']} → {$pr['destination_city']}") ?>
                                    <div class="text-muted"><?= date('M d, Y', strtotime($pr['departure_date'])) ?></div>
                                </td>
                                <td class="small">
                                    <?= htmlspecialchars(mb_strimwidth($pr['package_description'], 0, 50, '…')) ?>
                                    <div class="text-muted"><?= number_format($pr['weight_kg'], 1) ?> kg <?= !empty($pr['dimensions']) ? '— ' . htmlspecialchars($pr['dimensions']) : '' ?></div>
                                </td>
                                <td class="fw-bold text-success">$<?= number_format($pr['offered_price'], 2) ?></td>
                                <td class="small text-muted"><?= date('M d, H:i', strtotime($pr['created_at'])) ?></td>
                                <td>
                                    <button class="btn btn-success btn-sm" onclick="acceptReq(<?= (int)$pr['id'] ?>)">
                                        <i class="bi bi-check2"></i> Accept
                                    </button>
                                    <button class="btn btn-danger btn-sm ms-1" onclick="declineReq(<?= (int)$pr['id'] ?>)">
                                        <i class="bi bi-x"></i> Decline
                                    </button>
                                    <?php if (!empty($pr['special_instructions'])): ?>
                                        <button class="btn btn-outline-info btn-sm ms-1" title="<?= htmlspecialchars($pr['special_instructions']) ?>">
                                            <i class="bi bi-info-circle"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="card-body text-center text-muted py-4">
                <i class="bi bi-check-all fs-3 mb-2 d-block"></i>
                No pending requests.
            </div>
        <?php endif; ?>
    </div>

    <!-- Accepted / In Progress -->
    <?php if (!empty($acceptedRequests)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-primary bg-opacity-10 fw-bold">
                <i class="bi bi-arrow-right-circle text-primary me-1"></i> In Progress (<?= count($acceptedRequests) ?>)
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Buyer</th>
                            <th>Trip</th>
                            <th>Package</th>
                            <th>Fee</th>
                            <th>Status</th>
                            <th>Update Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($acceptedRequests as $ar): ?>
                            <tr>
                                <td><?= htmlspecialchars(($ar['first_name'] ?? '') . ' ' . ($ar['last_name'] ?? '')) ?></td>
                                <td class="small"><?= htmlspecialchars("{$ar['origin_city']} → {$ar['destination_city']}") ?></td>
                                <td class="small"><?= htmlspecialchars(mb_strimwidth($ar['package_description'], 0, 40, '…')) ?></td>
                                <td class="fw-bold text-success">$<?= number_format($ar['carrier_fee'] ?? $ar['offered_price'], 2) ?></td>
                                <td>
                                    <span class="badge bg-<?= $statusColors[$ar['status']] ?? 'secondary' ?>">
                                        <?= ucfirst(str_replace('_', ' ', $ar['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php $allowed = CARRY_STATUS_TRANSITIONS[$ar['status']] ?? []; ?>
                                    <?php foreach ($allowed as $next):
                                        if (in_array($next, ['declined', 'cancelled'], true)) continue;
                                        $btnClass = match($next) {
                                            'picked_up'  => 'btn-info',
                                            'in_transit' => 'btn-primary',
                                            'delivered'  => 'btn-success',
                                            default      => 'btn-secondary',
                                        };
                                    ?>
                                        <button class="btn btn-sm <?= $btnClass ?> me-1"
                                                onclick="updateStatus(<?= (int)$ar['id'] ?>, '<?= htmlspecialchars($next) ?>')">
                                            <?= ucfirst(str_replace('_', ' ', $next)) ?>
                                        </button>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- History -->
    <?php if (!empty($historyRequests)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-archive text-secondary me-1"></i> History (<?= count($historyRequests) ?>)
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Buyer</th>
                            <th>Route</th>
                            <th>Status</th>
                            <th>Fee</th>
                            <th>Rating</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historyRequests as $hr): ?>
                            <tr>
                                <td><?= htmlspecialchars(($hr['first_name'] ?? '') . ' ' . ($hr['last_name'] ?? '')) ?></td>
                                <td class="small"><?= htmlspecialchars("{$hr['origin_city']} → {$hr['destination_city']}") ?></td>
                                <td>
                                    <span class="badge bg-<?= $statusColors[$hr['status']] ?? 'secondary' ?>">
                                        <?= ucfirst(str_replace('_', ' ', $hr['status'])) ?>
                                    </span>
                                </td>
                                <td>$<?= number_format($hr['carrier_fee'] ?? $hr['offered_price'], 2) ?></td>
                                <td>
                                    <?php if ($hr['buyer_rating']): ?>
                                        <span class="text-warning">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi bi-star<?= $i <= $hr['buyer_rating'] ? '-fill' : '' ?>"></i>
                                            <?php endfor; ?>
                                        </span>
                                        <?php if ($hr['buyer_review']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars(mb_strimwidth($hr['buyer_review'], 0, 60, '…')) ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">Not rated</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small"><?= date('M d, Y', strtotime($hr['updated_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
const csrfToken = '<?= htmlspecialchars(generateCsrfToken()) ?>';

function acceptReq(requestId) {
    if (!confirm('Accept this carry request?')) return;
    const fd = new FormData();
    fd.append('action', 'accept_request');
    fd.append('request_id', requestId);
    fd.append('csrf_token', csrfToken);
    fetch('/api/carry.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.success) location.reload(); else alert(d.error || 'Failed'); });
}

function declineReq(requestId) {
    const reason = prompt('Reason for declining (optional):') || '';
    const fd = new FormData();
    fd.append('action', 'decline_request');
    fd.append('request_id', requestId);
    fd.append('reason', reason);
    fd.append('csrf_token', csrfToken);
    fetch('/api/carry.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.success) location.reload(); else alert(d.error || 'Failed'); });
}

function updateStatus(requestId, status) {
    const labels = { picked_up: 'Picked Up', in_transit: 'In Transit', delivered: 'Delivered' };
    if (!confirm('Mark as ' + (labels[status] || status) + '?')) return;
    const note = (status === 'delivered') ? (prompt('Delivery note (optional):') || '') : '';
    const fd = new FormData();
    fd.append('action', 'update_status');
    fd.append('request_id', requestId);
    fd.append('status', status);
    fd.append('note', note);
    fd.append('csrf_token', csrfToken);
    fetch('/api/carry.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.success) location.reload(); else alert(d.error || 'Failed'); });
}
</script>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
