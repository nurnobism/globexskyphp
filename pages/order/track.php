<?php
require_once __DIR__ . '/../../includes/middleware.php';

$tracking = trim($_GET['tracking'] ?? '');

$shipment = null;
$timeline = [];
$orderResult = null;
$notFound = false;

if ($tracking !== '') {
    // Try parcel_shipments by tracking_number first
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            "SELECT * FROM parcel_shipments WHERE tracking_number = ? LIMIT 1"
        );
        $stmt->execute([$tracking]);
        $shipment = $stmt->fetch();
    } catch (Exception $e) {
        $shipment = null;
    }

    // If found, load tracking events
    if ($shipment) {
        try {
            $db   = getDB();
            $stmt = $db->prepare(
                "SELECT * FROM parcel_tracking_events WHERE shipment_id = ? ORDER BY event_time DESC"
            );
            $stmt->execute([$shipment['id']]);
            $timeline = $stmt->fetchAll();
        } catch (Exception $e) {
            $timeline = [];
        }
    }

    // If not found as parcel, try orders table by order_number
    if (!$shipment) {
        try {
            $db   = getDB();
            $stmt = $db->prepare(
                "SELECT * FROM orders WHERE order_number = ? LIMIT 1"
            );
            $stmt->execute([$tracking]);
            $orderResult = $stmt->fetch();
        } catch (Exception $e) {
            $orderResult = null;
        }

        // If order found, check for a linked parcel shipment
        if ($orderResult) {
            try {
                $db   = getDB();
                $stmt = $db->prepare(
                    "SELECT * FROM parcel_shipments WHERE order_id = ? LIMIT 1"
                );
                $stmt->execute([$orderResult['id']]);
                $shipment = $stmt->fetch() ?: null;
            } catch (Exception $e) {
                $shipment = null;
            }

            if ($shipment) {
                try {
                    $db   = getDB();
                    $stmt = $db->prepare(
                        "SELECT * FROM parcel_tracking_events WHERE shipment_id = ? ORDER BY event_time DESC"
                    );
                    $stmt->execute([$shipment['id']]);
                    $timeline = $stmt->fetchAll();
                } catch (Exception $e) {
                    $timeline = [];
                }
            }
        }

        if (!$shipment && !$orderResult) {
            $notFound = true;
        }
    }
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

$pageTitle = 'Track Your Shipment';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <!-- Search form -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-search me-2"></i>Track Your Shipment</h5>
                </div>
                <div class="card-body p-4">
                    <form method="GET" class="d-flex gap-2">
                        <input type="text" name="tracking" class="form-control form-control-lg"
                               placeholder="Enter tracking number or order number"
                               value="<?= e($tracking) ?>" required>
                        <button type="submit" class="btn btn-primary btn-lg px-4">
                            <i class="bi bi-search me-1"></i> Track
                        </button>
                    </form>
                    <p class="form-text text-muted mt-2 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Enter a parcel tracking number (e.g. GS260401ABCDEF) or an order number.
                    </p>
                </div>
            </div>

            <?php if ($tracking !== ''): ?>

            <?php if ($notFound): ?>
            <!-- Not found -->
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-search text-muted display-3"></i>
                    <h5 class="mt-3 text-muted">No shipment found</h5>
                    <p class="text-muted mb-0">
                        We couldn't find any shipment matching
                        <strong><?= e($tracking) ?></strong>.<br>
                        Please check the tracking number and try again.
                    </p>
                </div>
            </div>

            <?php elseif ($shipment): ?>
            <!-- Shipment details -->
            <?php $badge = $statusBadges[$shipment['status']] ?? 'secondary'; ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="row align-items-center mb-3">
                        <div class="col-md-6">
                            <p class="text-muted small mb-1 text-uppercase fw-semibold">Tracking Number</p>
                            <h5 class="fw-bold font-monospace mb-0"><?= e($shipment['tracking_number']) ?></h5>
                        </div>
                        <div class="col-md-3 text-md-center mt-3 mt-md-0">
                            <span class="badge bg-<?= $badge ?> fs-6 px-3 py-2">
                                <?= e(ucwords(str_replace('_', ' ', $shipment['status']))) ?>
                            </span>
                        </div>
                        <div class="col-md-3 text-md-end mt-3 mt-md-0">
                            <small class="text-muted text-uppercase fw-semibold">
                                <?= e($shipment['shipping_method'] ?? '') ?> Shipping
                            </small>
                        </div>
                    </div>
                    <hr>
                    <!-- Route -->
                    <div class="row g-3 align-items-center mb-3">
                        <div class="col-md-5">
                            <p class="text-muted small mb-1">FROM</p>
                            <p class="fw-semibold mb-0">
                                <?= e($shipment['sender_name'] ?? '') ?><br>
                                <span class="text-muted small">
                                    <?= e($shipment['sender_city'] ?? '') ?>
                                    <?= $shipment['sender_city'] && $shipment['sender_country'] ? ', ' : '' ?>
                                    <?= e(strtoupper($shipment['sender_country'] ?? '')) ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-2 text-center">
                            <i class="bi bi-arrow-right fs-3 text-primary"></i>
                        </div>
                        <div class="col-md-5 text-md-end">
                            <p class="text-muted small mb-1">TO</p>
                            <p class="fw-semibold mb-0">
                                <?= e($shipment['receiver_name'] ?? '') ?><br>
                                <span class="text-muted small">
                                    <?= e($shipment['receiver_city'] ?? '') ?>
                                    <?= $shipment['receiver_city'] && $shipment['receiver_country'] ? ', ' : '' ?>
                                    <?= e(strtoupper($shipment['receiver_country'] ?? '')) ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    <!-- Package info -->
                    <div class="row g-3 text-center border-top pt-3">
                        <div class="col-4">
                            <small class="text-muted d-block">Weight</small>
                            <strong><?= $shipment['weight'] !== null ? e($shipment['weight']) . ' kg' : '—' ?></strong>
                        </div>
                        <div class="col-4">
                            <small class="text-muted d-block">Insurance</small>
                            <strong><?= $shipment['has_insurance'] ? '✅ Yes' : 'No' ?></strong>
                        </div>
                        <div class="col-4">
                            <small class="text-muted d-block">Created</small>
                            <?php $createdTs = strtotime($shipment['created_at'] ?? ''); ?>
                            <strong><?= $createdTs ? e(date('M j, Y', $createdTs)) : '—' ?></strong>
                        </div>
                    </div>
                    <?php if ($shipment['estimated_delivery']): ?>
                    <?php $estTs = strtotime($shipment['estimated_delivery']); ?>
                    <?php if ($estTs): ?>
                    <div class="mt-3 alert alert-light mb-0">
                        <i class="bi bi-calendar-check me-2 text-primary"></i>
                        <strong>Estimated Delivery:</strong>
                        <?= e(date('M j, Y', $estTs)) ?>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Timeline -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2"></i>Tracking Timeline</h6>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($timeline)): ?>
                    <p class="text-muted mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        No tracking events yet. Check back soon.
                    </p>
                    <?php else: ?>
                    <?php foreach ($timeline as $i => $ev): ?>
                    <div class="d-flex gap-3 <?= $i < count($timeline) - 1 ? 'mb-3' : '' ?>">
                        <div class="d-flex flex-column align-items-center">
                            <div class="rounded-circle bg-primary"
                                 style="width:12px;height:12px;margin-top:4px;flex-shrink:0"></div>
                            <?php if ($i < count($timeline) - 1): ?>
                            <div class="flex-grow-1"
                                 style="width:2px;background:#dee2e6;margin:2px auto"></div>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1 pb-2">
                            <div class="fw-semibold"><?= e(ucwords(str_replace('_', ' ', $ev['status']))) ?></div>
                            <?php if ($ev['description']): ?>
                            <div class="text-muted small"><?= e($ev['description']) ?></div>
                            <?php endif; ?>
                            <div class="text-muted small">
                                <?php if ($ev['location']): ?>
                                <i class="bi bi-geo-alt me-1"></i><?= e($ev['location']) ?> &middot;
                                <?php endif; ?>
                                <?= e(date('M j, Y g:i A', strtotime($ev['event_time']))) ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php elseif ($orderResult): ?>
            <!-- Order found but no linked parcel -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-bag me-2"></i>Order Found</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <p class="text-muted small mb-1">Order Number</p>
                            <p class="fw-bold font-monospace mb-0"><?= e($orderResult['order_number']) ?></p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <?php $orderBadge = [
                                'pending'    => 'warning',
                                'confirmed'  => 'info',
                                'processing' => 'info',
                                'shipped'    => 'primary',
                                'delivered'  => 'success',
                                'cancelled'  => 'danger',
                                'refunded'   => 'secondary',
                            ][$orderResult['status']] ?? 'secondary'; ?>
                            <span class="badge bg-<?= $orderBadge ?> fs-6 px-3 py-2">
                                <?= e(ucfirst($orderResult['status'])) ?>
                            </span>
                        </div>
                    </div>
                    <hr>
                    <p class="text-muted mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        No parcel tracking is linked to this order yet. Please contact support for details.
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <?php endif; ?>

        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
