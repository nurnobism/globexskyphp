<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];

// Check verified carrier
try {
    $stmt = $db->prepare("SELECT * FROM carriers WHERE user_id = ? AND status = 'active'");
    $stmt->execute([$userId]);
    $carrier = $stmt->fetch();
} catch (Exception $e) {
    $carrier = null;
}

if (!$carrier) {
    header('Location: /pages/shipment/carry/register.php');
    exit;
}

$carrierId = (int)$carrier['id'];

// Fetch matches with request and sender details
$matches = [];
try {
    $stmt = $db->prepare(
        "SELECT cm.*,
                cr.title        AS request_title,
                cr.from_city,
                cr.from_country_name,
                cr.from_country,
                cr.to_city,
                cr.to_country_name,
                cr.to_country,
                cr.weight_kg,
                u.first_name    AS sender_first,
                u.last_name     AS sender_last
         FROM carry_matches cm
         JOIN carry_requests cr ON cr.id = cm.request_id
         JOIN users u           ON u.id  = cr.sender_id
         WHERE cm.carrier_id = ?
         ORDER BY cm.created_at DESC"
    );
    $stmt->execute([$carrierId]);
    $matches = $stmt->fetchAll();
} catch (Exception $e) {
    $matches = [];
}

$statusColors = [
    'pending'    => 'warning',
    'accepted'   => 'info',
    'in_transit' => 'primary',
    'delivered'  => 'success',
    'cancelled'  => 'danger',
    'disputed'   => 'dark',
];

$statusTransitions = [
    'pending'    => ['accepted', 'cancelled'],
    'accepted'   => ['in_transit', 'cancelled'],
    'in_transit' => ['delivered'],
    'delivered'  => [],
    'cancelled'  => [],
    'disputed'   => ['delivered', 'cancelled'],
];

$pageTitle = 'My Matches';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-link-45deg text-primary me-2"></i>My Matches</h3>
        <div class="d-flex gap-2">
            <a href="/pages/shipment/carry/requests.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-search me-1"></i> Browse Requests
            </a>
            <a href="/pages/shipment/carry/dashboard.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <?php if (empty($matches)): ?>
    <div class="text-center py-5">
        <i class="bi bi-link text-muted display-3"></i>
        <h5 class="mt-3 text-muted">No matches yet</h5>
        <p class="text-muted">Accept carry requests to see them here.</p>
        <a href="/pages/shipment/carry/requests.php" class="btn btn-primary mt-1">
            <i class="bi bi-search me-1"></i> Browse Requests
        </a>
    </div>
    <?php else: ?>
    <div class="row g-4">
        <?php foreach ($matches as $match): ?>
        <?php
            $statusColor  = $statusColors[$match['status']] ?? 'secondary';
            $nextStatuses = $statusTransitions[$match['status']] ?? [];
        ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="row align-items-start g-3">

                        <!-- Request Info -->
                        <div class="col-md-5">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <h6 class="fw-bold mb-0"><?= e($match['request_title']) ?></h6>
                                <span class="badge bg-<?= $statusColor ?> text-capitalize ms-2">
                                    <?= ucfirst(str_replace('_', ' ', e($match['status']))) ?>
                                </span>
                            </div>
                            <p class="small text-muted mb-1">
                                <i class="bi bi-person me-1"></i>
                                Sender: <strong><?= e($match['sender_first'] . ' ' . $match['sender_last']) ?></strong>
                            </p>
                            <p class="small mb-1">
                                <i class="bi bi-geo-alt text-primary me-1"></i>
                                <strong><?= e($match['from_city']) ?></strong>, <?= e($match['from_country_name'] ?? $match['from_country']) ?>
                                <i class="bi bi-arrow-right text-muted mx-1"></i>
                                <strong><?= e($match['to_city']) ?></strong>, <?= e($match['to_country_name'] ?? $match['to_country']) ?>
                            </p>
                            <p class="small text-muted mb-0">
                                <i class="bi bi-box me-1"></i><?= number_format((float)$match['weight_kg'], 1) ?> kg
                                &nbsp;·&nbsp;
                                <i class="bi bi-currency-dollar me-1"></i>Agreed: <strong class="text-success"><?= formatMoney((float)$match['agreed_price']) ?></strong>
                            </p>
                            <p class="small text-muted mt-1 mb-0">
                                <i class="bi bi-clock me-1"></i>Matched <?= formatDate($match['created_at']) ?>
                            </p>
                        </div>

                        <!-- Status Update -->
                        <div class="col-md-4">
                            <?php if (!empty($nextStatuses)): ?>
                            <form method="POST" action="/api/carry.php?action=update_match_status">
                                <?= csrfField() ?>
                                <input type="hidden" name="match_id" value="<?= (int)$match['id'] ?>">
                                <label class="form-label fw-semibold small mb-1">Update Status</label>
                                <div class="d-flex gap-2">
                                    <select name="status" class="form-select form-select-sm">
                                        <?php foreach ($nextStatuses as $next): ?>
                                        <option value="<?= e($next) ?>"><?= ucfirst(str_replace('_', ' ', $next)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-primary btn-sm text-nowrap">
                                        <i class="bi bi-arrow-clockwise me-1"></i> Update
                                    </button>
                                </div>
                            </form>
                            <?php else: ?>
                            <p class="small text-muted mb-0 mt-2">
                                <i class="bi bi-check-circle text-success me-1"></i>
                                No further status updates available.
                            </p>
                            <?php endif; ?>
                        </div>

                        <!-- Proof of Delivery -->
                        <div class="col-md-3">
                            <?php if ($match['status'] === 'delivered'): ?>
                            <?php if (!empty($match['proof_of_delivery_url'])): ?>
                            <div class="text-center">
                                <i class="bi bi-file-earmark-check-fill text-success fs-3"></i>
                                <p class="small text-success mb-0">Proof uploaded</p>
                                <a href="/<?= e($match['proof_of_delivery_url']) ?>" target="_blank"
                                   class="btn btn-outline-success btn-sm mt-1">
                                    <i class="bi bi-eye me-1"></i> View
                                </a>
                            </div>
                            <?php else: ?>
                            <form method="POST" action="/api/carry.php?action=upload_proof"
                                  enctype="multipart/form-data">
                                <?= csrfField() ?>
                                <input type="hidden" name="match_id" value="<?= (int)$match['id'] ?>">
                                <label class="form-label fw-semibold small mb-1">Upload Proof of Delivery</label>
                                <input type="file" name="proof_file" class="form-control form-control-sm mb-2"
                                       accept=".jpg,.jpeg,.png,.pdf" required>
                                <button type="submit" class="btn btn-success btn-sm w-100">
                                    <i class="bi bi-upload me-1"></i> Upload
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php else: ?>
                            <p class="small text-muted mb-0">
                                <i class="bi bi-info-circle me-1"></i>
                                Proof of delivery available once status is <em>Delivered</em>.
                            </p>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
