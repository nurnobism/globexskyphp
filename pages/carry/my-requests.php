<?php
/**
 * pages/carry/my-requests.php — Buyer's Carry Requests — PR #16
 */
require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/carry.php';
requireAuth();

$userId     = (int)$_SESSION['user_id'];
$statusTab  = trim($_GET['status'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 15;

$filters = [];
if ($statusTab !== '') {
    $filters['status'] = $statusTab;
}

$result   = getBuyerRequests($userId, $filters, $page, $perPage);
$requests = $result['requests'];
$total    = $result['total'];
$pages    = $result['pages'];

$statusTabs = [
    ''           => 'All',
    'pending'    => 'Pending',
    'accepted'   => 'Accepted',
    'picked_up'  => 'Picked Up',
    'in_transit' => 'In Transit',
    'delivered'  => 'Delivered',
    'completed'  => 'Completed',
];

$statusColors = [
    'pending'    => 'warning',
    'accepted'   => 'primary',
    'picked_up'  => 'info',
    'in_transit' => 'info',
    'delivered'  => 'success',
    'completed'  => 'success',
    'declined'   => 'danger',
    'cancelled'  => 'secondary',
    'disputed'   => 'danger',
];

$pageTitle = 'My Carry Requests';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-box-seam text-primary me-2"></i>My Carry Requests</h3>
        <a href="/pages/carry/index.php" class="btn btn-outline-primary">
            <i class="bi bi-search me-1"></i> Browse Trips
        </a>
    </div>

    <!-- Status Tabs -->
    <ul class="nav nav-tabs mb-4">
        <?php foreach ($statusTabs as $status => $label): ?>
            <li class="nav-item">
                <a class="nav-link <?= $statusTab === $status ? 'active' : '' ?>"
                   href="?status=<?= urlencode($status) ?>">
                    <?= htmlspecialchars($label) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if (!empty($requests)): ?>
        <div class="row g-3">
            <?php foreach ($requests as $req): ?>
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="row align-items-center g-3">
                                <!-- Carrier -->
                                <div class="col-md-2 text-center">
                                    <?php if (!empty($req['carrier_avatar'])): ?>
                                        <img src="<?= htmlspecialchars($req['carrier_avatar']) ?>" class="rounded-circle mb-1" width="48" height="48" alt="Carrier" style="object-fit:cover">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-1" style="width:48px;height:48px">
                                            <?= strtoupper(substr($req['carrier_first_name'] ?? 'C', 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="small fw-semibold">
                                        <?= htmlspecialchars(($req['carrier_first_name'] ?? '') . ' ' . ($req['carrier_last_name'] ?? '')) ?>
                                        <?php if ($req['carrier_verified']): ?>
                                            <i class="bi bi-patch-check-fill text-success" title="Verified"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Route & Details -->
                                <div class="col-md-4">
                                    <div class="fw-semibold">
                                        <?= htmlspecialchars("{$req['origin_city']}, {$req['origin_country']}") ?>
                                        <i class="bi bi-arrow-right text-muted mx-1"></i>
                                        <?= htmlspecialchars("{$req['destination_city']}, {$req['destination_country']}") ?>
                                    </div>
                                    <div class="text-muted small">
                                        <i class="bi bi-calendar3 me-1"></i> <?= date('M d, Y', strtotime($req['departure_date'])) ?>
                                    </div>
                                    <div class="text-muted small mt-1">
                                        <?= htmlspecialchars(mb_strimwidth($req['package_description'], 0, 60, '…')) ?>
                                        &mdash; <?= number_format($req['weight_kg'], 1) ?> kg
                                    </div>
                                </div>

                                <!-- Price -->
                                <div class="col-md-2 text-center">
                                    <div class="fw-bold text-success">$<?= number_format($req['offered_price'], 2) ?></div>
                                    <div class="text-muted small">Offered Price</div>
                                </div>

                                <!-- Status -->
                                <div class="col-md-2 text-center">
                                    <span class="badge bg-<?= $statusColors[$req['status']] ?? 'secondary' ?> fs-6">
                                        <?= ucfirst(str_replace('_', ' ', $req['status'])) ?>
                                    </span>
                                    <div class="text-muted small mt-1"><?= date('M d', strtotime($req['created_at'])) ?></div>
                                </div>

                                <!-- Actions -->
                                <div class="col-md-2 text-end">
                                    <div class="btn-group-vertical btn-group-sm w-100" role="group">
                                        <?php if (in_array($req['status'], ['picked_up', 'in_transit'], true)): ?>
                                            <a href="/pages/carry/trip-detail.php?id=<?= (int)$req['trip_id'] ?>" class="btn btn-outline-info btn-sm">
                                                <i class="bi bi-geo-alt me-1"></i> Track
                                            </a>
                                        <?php endif; ?>

                                        <?php if (in_array($req['status'], ['delivered', 'completed'], true)): ?>
                                            <button class="btn btn-outline-warning btn-sm"
                                                    onclick="openRating(<?= (int)$req['id'] ?>)">
                                                <i class="bi bi-star me-1"></i> Rate
                                            </button>
                                        <?php endif; ?>

                                        <?php if (in_array($req['status'], ['pending', 'accepted'], true)): ?>
                                            <button class="btn btn-outline-danger btn-sm"
                                                    onclick="cancelRequest(<?= (int)$req['id'] ?>)">
                                                <i class="bi bi-x-circle me-1"></i> Cancel
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($p = 1; $p <= $pages; $p++): ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>

    <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-inbox display-1 text-muted"></i>
            <h5 class="mt-3 text-muted">No carry requests yet</h5>
            <a href="/pages/carry/index.php" class="btn btn-primary mt-2">Browse Trips</a>
        </div>
    <?php endif; ?>
</div>

<!-- Rating Modal -->
<div class="modal fade" id="ratingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rate Carrier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="ratingForm">
                    <input type="hidden" name="action" value="rate">
                    <input type="hidden" name="request_id" id="rateRequestId">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                    <div class="mb-3 text-center">
                        <label class="form-label fw-semibold">Your Rating</label>
                        <div class="fs-2" id="starRow">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi bi-star text-warning" style="cursor:pointer" data-val="<?= $i ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="rating" id="ratingVal" value="5">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Review (optional)</label>
                        <textarea name="review" class="form-control" rows="3" placeholder="Share your experience..."></textarea>
                    </div>
                    <div id="ratingMsg" class="d-none"></div>
                    <button type="submit" class="btn btn-primary w-100">Submit Rating</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function cancelRequest(requestId) {
    if (!confirm('Cancel this carry request?')) return;
    const reason = prompt('Reason for cancellation (optional):') || '';
    const fd = new FormData();
    fd.append('action', 'cancel_request');
    fd.append('request_id', requestId);
    fd.append('reason', reason);
    fd.append('csrf_token', '<?= htmlspecialchars(generateCsrfToken()) ?>');
    fetch('/api/carry.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) location.reload();
            else alert(d.error || 'Failed to cancel.');
        });
}

function openRating(requestId) {
    document.getElementById('rateRequestId').value = requestId;
    const modal = new bootstrap.Modal(document.getElementById('ratingModal'));
    modal.show();
}

(function() {
    const stars  = document.querySelectorAll('#starRow i');
    const rVal   = document.getElementById('ratingVal');
    let selected = 5;
    stars.forEach(s => {
        s.addEventListener('mouseover', () => {
            stars.forEach((x, i) => x.className = 'bi bi-star' + (i < parseInt(s.dataset.val) ? '-fill' : '') + ' text-warning');
        });
        s.addEventListener('click', () => {
            selected = parseInt(s.dataset.val);
            rVal.value = selected;
        });
    });
    document.getElementById('starRow').addEventListener('mouseleave', () => {
        stars.forEach((x, i) => x.className = 'bi bi-star' + (i < selected ? '-fill' : '') + ' text-warning');
    });

    const rf = document.getElementById('ratingForm');
    if (rf) {
        rf.addEventListener('submit', function(e) {
            e.preventDefault();
            const msg = document.getElementById('ratingMsg');
            fetch('/api/carry.php', { method: 'POST', body: new FormData(rf) })
                .then(r => r.json())
                .then(d => {
                    msg.classList.remove('d-none');
                    if (d.success) {
                        msg.className = 'alert alert-success';
                        msg.textContent = 'Rating submitted! Thank you.';
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        msg.className = 'alert alert-danger';
                        msg.textContent = d.error || 'Failed to submit rating.';
                    }
                });
        });
    }
})();
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
