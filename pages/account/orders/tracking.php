<?php
/**
 * pages/account/orders/tracking.php — Buyer: Parcel Tracking Page (PR #15)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/tracking.php';
requireLogin();

$db         = getDB();
$userId     = (int)$_SESSION['user_id'];
$shipmentId = (int)get('shipment_id', 0);
$orderId    = (int)get('order_id', 0);

$shipment = null;
$shipments = [];

if ($shipmentId) {
    $shipment = getShipment($shipmentId);
    // Verify buyer owns this shipment
    if ($shipment && (int)($shipment['buyer_id'] ?? 0) !== $userId) {
        $shipment = null;
    }
} elseif ($orderId) {
    // Get all shipments for an order — verify buyer owns order
    try {
        $stmt = $db->prepare('SELECT id FROM orders WHERE id = ? AND buyer_id = ? LIMIT 1');
        $stmt->execute([$orderId, $userId]);
        if ($stmt->fetchColumn()) {
            $shipments = getOrderShipments($orderId);
            if (count($shipments) === 1) {
                $shipment = getShipment((int)$shipments[0]['id']);
            }
        }
    } catch (PDOException $e) {
        // Ignore
    }
}

$pageTitle = $shipment ? 'Tracking — ' . e($shipment['tracking_number']) : 'My Shipments';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container py-5">
    <!-- Back -->
    <a href="/pages/account/orders/index.php" class="btn btn-outline-secondary btn-sm mb-3">
        <i class="bi bi-arrow-left me-1"></i>My Orders
    </a>

<?php if (!$shipment && empty($shipments)): ?>
    <!-- No shipment found -->
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        Shipment not found or you do not have access to view it.
    </div>
<?php elseif (!$shipment && count($shipments) > 1): ?>
    <!-- Multiple shipments for an order -->
    <h4 class="fw-bold mb-4">Shipments for Order</h4>
    <div class="row g-3">
        <?php foreach ($shipments as $s): ?>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <strong><?= e($s['carrier_name'] ?: $s['carrier_code']) ?></strong>
                            <div class="text-muted small font-monospace"><?= e($s['tracking_number']) ?></div>
                        </div>
                        <span class="badge bg-<?= getStatusColor($s['status']) ?>"><?= e(getStatusLabel($s['status'])) ?></span>
                    </div>
                    <?php if ($s['estimated_delivery']): ?>
                    <div class="small text-muted">Est. delivery: <?= e(date('M j, Y', strtotime($s['estimated_delivery']))) ?></div>
                    <?php endif; ?>
                    <a href="?shipment_id=<?= (int)$s['id'] ?>" class="btn btn-primary btn-sm mt-3">
                        <i class="bi bi-search me-1"></i>Track
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <?php
    $events  = $shipment['events'] ?? [];
    $status  = $shipment['status'] ?? 'unknown';
    $carrier = $shipment['carrier_name'] ?: ($shipment['carrier_code'] ?? '');
    $trackUrl = getCarrierTrackingUrl($shipment['carrier_code'] ?? 'generic', $shipment['tracking_number'] ?? '', $shipment['tracking_url'] ?? '');

    // Progress steps
    $progressSteps = [
        TRACKING_STATUS_LABEL_CREATED,
        TRACKING_STATUS_PICKED_UP,
        TRACKING_STATUS_IN_TRANSIT,
        TRACKING_STATUS_OUT_FOR_DELIVERY,
        TRACKING_STATUS_DELIVERED,
    ];
    $currentStep = array_search($status, $progressSteps);
    if ($currentStep === false) { $currentStep = -1; }
    ?>

    <!-- Shipment Header -->
    <div class="card border-0 shadow-sm mb-4" id="shipment-card" data-shipment-id="<?= (int)$shipment['id'] ?>">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <?php if ($shipment['order_number']): ?>
                    <div class="text-muted small mb-1">Order #<?= e($shipment['order_number']) ?></div>
                    <?php endif; ?>
                    <h5 class="fw-bold mb-1"><?= e($carrier) ?></h5>
                    <div class="d-flex align-items-center gap-2">
                        <span class="font-monospace fs-6"><?= e($shipment['tracking_number']) ?></span>
                        <button class="btn btn-link btn-sm p-0 text-muted copy-tracking"
                                data-value="<?= e($shipment['tracking_number']) ?>"
                                title="Copy tracking number">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                </div>
                <div class="text-end">
                    <span class="badge bg-<?= getStatusColor($status) ?> fs-5 px-3 py-2" id="status-badge">
                        <?= e(getStatusLabel($status)) ?>
                    </span>
                    <?php if ($trackUrl): ?>
                    <div class="mt-2">
                        <a href="<?= e($trackUrl) ?>" target="_blank" rel="noopener noreferrer"
                           class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Track on <?= e($carrier) ?> website
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Bar -->
    <?php if (!in_array($status, [TRACKING_STATUS_EXCEPTION, TRACKING_STATUS_RETURNED, TRACKING_STATUS_UNKNOWN], true)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h6 class="fw-bold mb-3">Shipping Progress</h6>
            <div class="d-flex justify-content-between align-items-center position-relative">
                <div class="position-absolute top-50 start-0 end-0 translate-middle-y"
                     style="height:3px;background:#dee2e6;z-index:0"></div>
                <?php
                $stepIcons = [
                    TRACKING_STATUS_LABEL_CREATED    => 'tag',
                    TRACKING_STATUS_PICKED_UP        => 'box-seam',
                    TRACKING_STATUS_IN_TRANSIT       => 'truck',
                    TRACKING_STATUS_OUT_FOR_DELIVERY => 'truck-front',
                    TRACKING_STATUS_DELIVERED        => 'house-check',
                ];
                foreach ($progressSteps as $i => $step):
                    $done    = $i <= $currentStep;
                    $current = $i === $currentStep;
                ?>
                <div class="text-center position-relative" style="z-index:1;flex:1">
                    <div class="mx-auto rounded-circle d-flex align-items-center justify-content-center"
                         style="width:40px;height:40px;background:<?= $done ? '#0d6efd' : '#dee2e6' ?>;color:<?= $done ? '#fff' : '#6c757d' ?>">
                        <i class="bi bi-<?= $stepIcons[$step] ?? 'circle' ?>"></i>
                    </div>
                    <div class="mt-1 small <?= $current ? 'fw-bold text-primary' : ($done ? 'text-muted' : 'text-muted') ?>">
                        <?= e(getStatusLabel($step)) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Tracking Timeline -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Tracking History</h6>
                    <?php if (empty($events)): ?>
                    <div class="text-center py-4 text-muted" id="events-container">
                        <i class="bi bi-clock-history display-6 mb-2"></i>
                        <p class="mb-0">No tracking events yet. Check back later.</p>
                    </div>
                    <?php else: ?>
                    <div class="timeline" id="events-container">
                        <?php foreach ($events as $i => $ev): ?>
                        <div class="d-flex gap-3 mb-3 <?= $i === 0 ? 'latest-event' : '' ?>">
                            <div class="flex-shrink-0 text-center" style="width:40px">
                                <div class="rounded-circle d-inline-flex align-items-center justify-content-center"
                                     style="width:36px;height:36px;background:<?= $i === 0 ? '#0d6efd' : '#f8f9fa' ?>;color:<?= $i === 0 ? '#fff' : '#6c757d' ?>">
                                    <i class="bi bi-geo-alt-fill"></i>
                                </div>
                                <?php if ($i < count($events) - 1): ?>
                                <div style="width:2px;height:100%;min-height:24px;background:#dee2e6;margin:4px auto 0"></div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1 pb-3">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-1">
                                    <div>
                                        <span class="badge bg-<?= getStatusColor($ev['status']) ?> me-2">
                                            <?= e(getStatusLabel($ev['status'])) ?>
                                        </span>
                                        <?php if ($ev['location']): ?>
                                        <span class="text-muted small"><i class="bi bi-pin-map me-1"></i><?= e($ev['location']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-muted small"><?= e($ev['event_date']) ?></span>
                                </div>
                                <?php if ($ev['description']): ?>
                                <p class="mb-0 mt-1 small"><?= e($ev['description']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Shipment Details -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Shipment Details</h6>
                    <dl class="row mb-0 small">
                        <dt class="col-6 text-muted">Carrier</dt>
                        <dd class="col-6"><?= e($carrier) ?></dd>
                        <?php if ($shipment['shipped_date']): ?>
                        <dt class="col-6 text-muted">Shipped</dt>
                        <dd class="col-6"><?= e(date('M j, Y', strtotime($shipment['shipped_date']))) ?></dd>
                        <?php endif; ?>
                        <?php if ($shipment['estimated_delivery']): ?>
                        <dt class="col-6 text-muted">Est. Delivery</dt>
                        <dd class="col-6"><?= e(date('M j, Y', strtotime($shipment['estimated_delivery']))) ?></dd>
                        <?php endif; ?>
                        <?php if ($shipment['actual_delivery']): ?>
                        <dt class="col-6 text-muted">Delivered</dt>
                        <dd class="col-6 text-success fw-bold"><?= e(date('M j, Y', strtotime($shipment['actual_delivery']))) ?></dd>
                        <?php endif; ?>
                        <?php if ($shipment['weight_kg']): ?>
                        <dt class="col-6 text-muted">Weight</dt>
                        <dd class="col-6"><?= e(number_format((float)$shipment['weight_kg'], 2)) ?> kg</dd>
                        <?php endif; ?>
                        <?php if ($shipment['package_dimensions']): ?>
                        <dt class="col-6 text-muted">Dimensions</dt>
                        <dd class="col-6"><?= e($shipment['package_dimensions']) ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>

            <!-- Location placeholder -->
            <?php
            $latestEvent = $events[0] ?? null;
            $currentLocation = $latestEvent['location'] ?? '';
            ?>
            <?php if ($currentLocation): ?>
            <div class="card border-0 shadow-sm bg-light">
                <div class="card-body">
                    <p class="mb-0">
                        <i class="bi bi-geo-alt-fill text-primary me-2"></i>
                        Package is currently in <strong><?= e($currentLocation) ?></strong>
                    </p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
</div>

<script>
// ── Copy tracking number to clipboard ─────────────────────────────────────
document.querySelectorAll('.copy-tracking').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var val = btn.getAttribute('data-value');
        if (navigator.clipboard) {
            navigator.clipboard.writeText(val).then(function() {
                btn.innerHTML = '<i class="bi bi-check2"></i>';
                setTimeout(function() { btn.innerHTML = '<i class="bi bi-clipboard"></i>'; }, 1500);
            });
        }
    });
});

<?php if ($shipment): ?>
// ── Auto-refresh every 60 seconds ─────────────────────────────────────────
(function() {
    var shipmentId = <?= (int)$shipment['id'] ?>;
    var lastStatus = <?= json_encode($status) ?>;

    function refresh() {
        fetch('/api/tracking.php?action=order_shipments&order_id=<?= (int)($shipment['order_id'] ?? 0) ?>')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success || !data.data.length) return;
                var s = data.data.find(function(x) { return x.id == shipmentId; });
                if (!s) return;
                if (s.status !== lastStatus) {
                    lastStatus = s.status;
                    location.reload();
                }
            })
            .catch(function() {});
    }

    setInterval(refresh, 60000);
})();
<?php endif; ?>
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
