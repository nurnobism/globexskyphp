<?php
/**
 * pages/admin/logistics/carry.php — Admin Carry Dashboard — PR #16
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/carry.php';
requireAdmin();

$db = getDB();

// Stats
$stats = [
    'active_trips'        => 0,
    'total_requests'      => 0,
    'completed_deliveries'=> 0,
    'pending_requests'    => 0,
    'disputed'            => 0,
    'total_revenue'       => 0.0,
];

$allTrips    = [];
$allRequests = [];
$recentRatings = [];

try {
    $stmt = $db->query("SELECT COUNT(*) FROM carry_trips WHERE is_active = 1 AND status = 'active'");
    $stats['active_trips'] = (int)$stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM carry_requests");
    $stats['total_requests'] = (int)$stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM carry_requests WHERE status IN ('delivered','completed')");
    $stats['completed_deliveries'] = (int)$stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM carry_requests WHERE status = 'pending'");
    $stats['pending_requests'] = (int)$stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM carry_requests WHERE status = 'disputed'");
    $stats['disputed'] = (int)$stmt->fetchColumn();

    $stmt = $db->query("SELECT COALESCE(SUM(carrier_fee), 0) FROM carry_requests WHERE status IN ('delivered','completed')");
    $stats['total_revenue'] = (float)$stmt->fetchColumn();

    // Recent trips
    $stmt = $db->query(
        "SELECT ct.*,
                u.first_name, u.last_name, u.email,
                (SELECT COUNT(*) FROM carry_requests cr WHERE cr.trip_id = ct.id) AS request_count
         FROM carry_trips ct
         LEFT JOIN users u ON u.id = ct.carrier_id
         ORDER BY ct.created_at DESC
         LIMIT 20"
    );
    $allTrips = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent requests
    $stmt = $db->query(
        "SELECT cr.*,
                ct.origin_city, ct.origin_country, ct.destination_city, ct.destination_country,
                ct.carrier_id,
                bu.first_name AS buyer_first, bu.last_name AS buyer_last, bu.email AS buyer_email,
                cu.first_name AS carrier_first, cu.last_name AS carrier_last
         FROM carry_requests cr
         JOIN carry_trips ct ON ct.id = cr.trip_id
         LEFT JOIN users bu ON bu.id = cr.buyer_id
         LEFT JOIN users cu ON cu.id = ct.carrier_id
         ORDER BY cr.updated_at DESC
         LIMIT 20"
    );
    $allRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent ratings
    $stmt = $db->query(
        "SELECT cr_r.*, bu.first_name AS buyer_first, bu.last_name AS buyer_last,
                cu.first_name AS carrier_first, cu.last_name AS carrier_last
         FROM carry_ratings cr_r
         LEFT JOIN users bu ON bu.id = cr_r.buyer_id
         LEFT JOIN users cu ON cu.id = cr_r.carrier_id
         ORDER BY cr_r.created_at DESC
         LIMIT 10"
    );
    $recentRatings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Tables may not exist yet — graceful degradation
}

$statusColors = ['active'=>'success','inactive'=>'secondary','cancelled'=>'danger','completed'=>'primary'];
$reqColors    = ['pending'=>'warning','accepted'=>'primary','picked_up'=>'info','in_transit'=>'info','delivered'=>'success','completed'=>'success','declined'=>'danger','cancelled'=>'secondary','disputed'=>'danger'];
$pageTitle    = 'Admin — Carry Management';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <h3 class="fw-bold mb-4"><i class="bi bi-airplane text-primary me-2"></i>Carry Service Management</h3>

    <!-- Stats -->
    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-2">
            <div class="card border-0 shadow-sm text-center p-3">
                <i class="bi bi-airplane display-5 text-primary mb-2"></i>
                <h3 class="fw-bold"><?= $stats['active_trips'] ?></h3>
                <p class="text-muted mb-0 small">Active Trips</p>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="card border-0 shadow-sm text-center p-3">
                <i class="bi bi-inbox display-5 text-warning mb-2"></i>
                <h3 class="fw-bold"><?= $stats['total_requests'] ?></h3>
                <p class="text-muted mb-0 small">Total Requests</p>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="card border-0 shadow-sm text-center p-3">
                <i class="bi bi-clock display-5 text-info mb-2"></i>
                <h3 class="fw-bold"><?= $stats['pending_requests'] ?></h3>
                <p class="text-muted mb-0 small">Pending</p>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="card border-0 shadow-sm text-center p-3">
                <i class="bi bi-check-circle display-5 text-success mb-2"></i>
                <h3 class="fw-bold"><?= $stats['completed_deliveries'] ?></h3>
                <p class="text-muted mb-0 small">Completed</p>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="card border-0 shadow-sm text-center p-3">
                <i class="bi bi-exclamation-triangle display-5 text-danger mb-2"></i>
                <h3 class="fw-bold"><?= $stats['disputed'] ?></h3>
                <p class="text-muted mb-0 small">Disputed</p>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="card border-0 shadow-sm text-center p-3">
                <i class="bi bi-cash-stack display-5 text-success mb-2"></i>
                <h3 class="fw-bold">$<?= number_format($stats['total_revenue'], 0) ?></h3>
                <p class="text-muted mb-0 small">Revenue</p>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- All Trips -->
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">
                    <i class="bi bi-list-ul text-primary me-1"></i> Recent Trips
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Carrier</th>
                                <th>Route</th>
                                <th>Date</th>
                                <th>Reqs</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allTrips as $t): ?>
                                <tr>
                                    <td class="small"><?= htmlspecialchars(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? '')) ?></td>
                                    <td class="small"><?= htmlspecialchars("{$t['origin_city']} → {$t['destination_city']}") ?></td>
                                    <td class="small"><?= date('M d', strtotime($t['departure_date'])) ?></td>
                                    <td><?= (int)$t['request_count'] ?></td>
                                    <td><span class="badge bg-<?= $statusColors[$t['status']] ?? 'secondary' ?>"><?= ucfirst($t['status']) ?></span></td>
                                    <td>
                                        <?php if ($t['status'] === 'active'): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Suspend this trip?')">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                                                <input type="hidden" name="action" value="admin_suspend_trip">
                                                <input type="hidden" name="trip_id" value="<?= (int)$t['id'] ?>">
                                                <button type="submit" class="btn btn-xs btn-outline-danger" style="font-size:.75rem;padding:2px 6px">Suspend</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($allTrips)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-3">No trips yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- All Requests -->
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">
                    <i class="bi bi-envelope text-warning me-1"></i> Recent Requests
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Buyer</th>
                                <th>Route</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allRequests as $r): ?>
                                <tr>
                                    <td class="small"><?= htmlspecialchars(($r['buyer_first'] ?? '') . ' ' . ($r['buyer_last'] ?? '')) ?></td>
                                    <td class="small"><?= htmlspecialchars("{$r['origin_city']} → {$r['destination_city']}") ?></td>
                                    <td class="small">$<?= number_format($r['offered_price'], 2) ?></td>
                                    <td><span class="badge bg-<?= $reqColors[$r['status']] ?? 'secondary' ?>"><?= ucfirst(str_replace('_', ' ', $r['status'])) ?></span></td>
                                    <td>
                                        <?php if ($r['status'] === 'disputed'): ?>
                                            <button class="btn btn-xs btn-outline-primary" style="font-size:.75rem;padding:2px 6px"
                                                    onclick="resolveDispute(<?= (int)$r['id'] ?>)">Resolve</button>
                                        <?php endif; ?>
                                        <?php if (in_array($r['status'], ['delivered'], true) && !$r['payment_released']): ?>
                                            <button class="btn btn-xs btn-outline-success" style="font-size:.75rem;padding:2px 6px"
                                                    onclick="releasePayment(<?= (int)$r['id'] ?>)">Release Pay</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($allRequests)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-3">No requests yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Ratings -->
    <?php if (!empty($recentRatings)): ?>
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-star-fill text-warning me-1"></i> Recent Carrier Ratings
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Buyer</th><th>Carrier</th><th>Rating</th><th>Review</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentRatings as $rr): ?>
                            <tr>
                                <td class="small"><?= htmlspecialchars(($rr['buyer_first'] ?? '') . ' ' . ($rr['buyer_last'] ?? '')) ?></td>
                                <td class="small"><?= htmlspecialchars(($rr['carrier_first'] ?? '') . ' ' . ($rr['carrier_last'] ?? '')) ?></td>
                                <td>
                                    <span class="text-warning">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi bi-star<?= $i <= $rr['rating'] ? '-fill' : '' ?>"></i>
                                        <?php endfor; ?>
                                    </span>
                                </td>
                                <td class="small"><?= !empty($rr['review']) ? htmlspecialchars(mb_strimwidth($rr['review'], 0, 60, '…')) : '<span class="text-muted">—</span>' ?></td>
                                <td class="small text-muted"><?= date('M d, Y', strtotime($rr['created_at'])) ?></td>
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

function resolveDispute(requestId) {
    const resolution = prompt('Resolution action (completed / cancelled):');
    if (!resolution) return;
    const fd = new FormData();
    fd.append('action', 'update_status');
    fd.append('request_id', requestId);
    fd.append('status', resolution);
    fd.append('note', 'Resolved by admin');
    fd.append('csrf_token', csrfToken);
    fetch('/api/carry.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.success) location.reload(); else alert(d.error || 'Failed'); });
}

function releasePayment(requestId) {
    if (!confirm('Release payment to carrier?')) return;
    const fd = new FormData();
    fd.append('action', 'release_payment');
    fd.append('request_id', requestId);
    fd.append('csrf_token', csrfToken);
    fetch('/api/carry.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.success) location.reload(); else alert(d.error || 'Failed'); });
}
</script>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
