<?php
/**
 * pages/carrier/dashboard.php — Carrier Dashboard — PR #16
 */
require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/carry.php';
requireAuth();

$userId = (int)$_SESSION['user_id'];
$stats  = getCarrierStats($userId);

// Active trips
$myTrips    = getCarrierTrips($userId, ['status' => 'active'], 1, 5);
$activeTrips = $myTrips['trips'];

// Pending requests across all trips
$pendingRequests = [];
try {
    $db = getDB();
    $stmt = $db->prepare(
        'SELECT cr.*, ct.origin_city, ct.destination_city, ct.departure_date,
                u.first_name, u.last_name, u.avatar
         FROM carry_requests cr
         JOIN carry_trips ct ON ct.id = cr.trip_id
         JOIN users u ON u.id = cr.buyer_id
         WHERE ct.carrier_id = ? AND cr.status = "pending"
         ORDER BY cr.created_at DESC
         LIMIT 10'
    );
    $stmt->execute([$userId]);
    $pendingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Tables may not exist yet
}

// Recent activity
$recentActivity = [];
try {
    $db = getDB();
    $stmt = $db->prepare(
        'SELECT cr.*, ct.origin_city, ct.destination_city
         FROM carry_requests cr
         JOIN carry_trips ct ON ct.id = cr.trip_id
         WHERE ct.carrier_id = ?
         ORDER BY cr.updated_at DESC
         LIMIT 5'
    );
    $stmt->execute([$userId]);
    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Tables may not exist yet
}

$isVerified = isCarrierVerified($userId);
$pageTitle  = 'Carrier Dashboard';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-airplane text-primary me-2"></i>Carrier Dashboard</h3>
        <div>
            <?php if (!$isVerified): ?>
                <span class="badge bg-warning text-dark me-2">
                    <i class="bi bi-exclamation-triangle me-1"></i> Verification Pending
                </span>
            <?php else: ?>
                <span class="badge bg-success me-2">
                    <i class="bi bi-patch-check-fill me-1"></i> Verified Carrier
                </span>
            <?php endif; ?>
            <a href="/pages/carrier/trips/index.php?action=new" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Post New Trip
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-2-4">
            <div class="col">
                <div class="card border-0 shadow-sm text-center p-3">
                    <i class="bi bi-airplane display-5 text-primary mb-2"></i>
                    <h2 class="fw-bold"><?= count($activeTrips) ?></h2>
                    <p class="text-muted mb-0">Active Trips</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <i class="bi bi-clock-history display-5 text-warning mb-2"></i>
                <h2 class="fw-bold"><?= count($pendingRequests) ?></h2>
                <p class="text-muted mb-0">Pending Requests</p>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <i class="bi bi-check-circle display-5 text-success mb-2"></i>
                <h2 class="fw-bold"><?= $stats['packages_delivered'] ?></h2>
                <p class="text-muted mb-0">Packages Delivered</p>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <i class="bi bi-cash-stack display-5 text-success mb-2"></i>
                <h2 class="fw-bold">$<?= number_format($stats['total_earnings'], 0) ?></h2>
                <p class="text-muted mb-0">Total Earnings</p>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <i class="bi bi-star-fill display-5 text-warning mb-2"></i>
                <h2 class="fw-bold"><?= $stats['rating'] > 0 ? number_format($stats['rating'], 1) : 'N/A' ?></h2>
                <p class="text-muted mb-0">Avg Rating</p>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Upcoming Trips -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-calendar3 text-primary me-1"></i> Upcoming Trips</span>
                    <a href="/pages/carrier/trips/index.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($activeTrips)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($activeTrips as $t): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-semibold">
                                                <?= htmlspecialchars("{$t['origin_city']} → {$t['destination_city']}") ?>
                                            </div>
                                            <div class="text-muted small">
                                                <i class="bi bi-calendar3 me-1"></i> <?= date('M d, Y', strtotime($t['departure_date'])) ?>
                                                &nbsp;|&nbsp;
                                                <i class="bi bi-people me-1"></i> <?= (int)($t['request_count'] ?? 0) ?> request(s)
                                            </div>
                                        </div>
                                        <a href="/pages/carrier/requests/index.php?trip_id=<?= (int)$t['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            View
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-calendar-x fs-3 mb-2"></i>
                            <p class="mb-0">No active trips. <a href="/pages/carrier/trips/index.php?action=new">Post one!</a></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-activity text-success me-1"></i> Recent Activity</span>
                    <a href="/pages/carrier/requests/index.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($recentActivity)): ?>
                        <div class="list-group list-group-flush">
                            <?php
                            $actColors = ['pending'=>'warning','accepted'=>'primary','picked_up'=>'info','in_transit'=>'info','delivered'=>'success','completed'=>'success','declined'=>'danger','cancelled'=>'secondary'];
                            foreach ($recentActivity as $ra):
                            ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <span class="badge bg-<?= $actColors[$ra['status']] ?? 'secondary' ?> me-2">
                                                <?= ucfirst(str_replace('_', ' ', $ra['status'])) ?>
                                            </span>
                                            <?= htmlspecialchars("{$ra['origin_city']} → {$ra['destination_city']}") ?>
                                        </div>
                                        <div class="text-muted small"><?= date('M d', strtotime($ra['updated_at'])) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-clock-history fs-3 mb-2"></i>
                            <p class="mb-0">No recent activity yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Requests Quick View -->
    <?php if (!empty($pendingRequests)): ?>
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-warning bg-opacity-10 d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-exclamation-circle text-warning me-1"></i> Pending Requests (<?= count($pendingRequests) ?>)</span>
                <a href="/pages/carrier/requests/index.php" class="btn btn-sm btn-warning">Manage</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Buyer</th>
                            <th>Trip</th>
                            <th>Package</th>
                            <th>Offered</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingRequests as $pr): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars(($pr['first_name'] ?? '') . ' ' . ($pr['last_name'] ?? '')) ?>
                                </td>
                                <td><?= htmlspecialchars("{$pr['origin_city']} → {$pr['destination_city']}") ?></td>
                                <td><?= htmlspecialchars(mb_strimwidth($pr['package_description'], 0, 40, '…')) ?></td>
                                <td>$<?= number_format($pr['offered_price'], 2) ?></td>
                                <td><?= date('M d', strtotime($pr['created_at'])) ?></td>
                                <td>
                                    <button class="btn btn-success btn-sm" onclick="quickAccept(<?= (int)$pr['id'] ?>)">
                                        <i class="bi bi-check"></i> Accept
                                    </button>
                                    <button class="btn btn-danger btn-sm ms-1" onclick="quickDecline(<?= (int)$pr['id'] ?>)">
                                        <i class="bi bi-x"></i> Decline
                                    </button>
                                </td>
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

function quickAccept(requestId) {
    if (!confirm('Accept this carry request?')) return;
    const fd = new FormData();
    fd.append('action', 'accept_request');
    fd.append('request_id', requestId);
    fd.append('csrf_token', csrfToken);
    fetch('/api/carry.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.success) location.reload(); else alert(d.error || 'Failed'); });
}

function quickDecline(requestId) {
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
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
