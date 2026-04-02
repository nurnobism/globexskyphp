<?php
require_once __DIR__ . '/../../includes/middleware.php';

$tracking = trim(get('tracking', ''));

$pageTitle = 'Track Shipment';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="text-center mb-5">
                <i class="bi bi-truck display-3 text-primary"></i>
                <h2 class="fw-bold mt-3">Track Your Shipment</h2>
                <p class="text-muted">Enter your tracking number to get real-time updates</p>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="GET" class="d-flex gap-2 mb-4">
                        <input type="text" name="tracking" class="form-control form-control-lg" placeholder="Enter tracking number..." value="<?= e($tracking) ?>" required>
                        <button type="submit" class="btn btn-primary btn-lg px-4"><i class="bi bi-search"></i></button>
                    </form>

                    <?php if ($tracking): ?>
                    <?php
                    try {
                        $db   = getDB();
                        $stmt = $db->prepare('SELECT s.*, o.order_number FROM shipments s LEFT JOIN orders o ON o.id=s.order_id WHERE s.tracking_number=?');
                        $stmt->execute([$tracking]);
                        $shipment = $stmt->fetch();
                    } catch (PDOException $e) { $shipment = null; }
                    ?>
                    <?php if (!$shipment): ?>
                    <div class="alert alert-warning d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>No shipment found</strong> for tracking number <em><?= e($tracking) ?></em>. Please check and try again.
                    </div>
                    <?php else: ?>
                    <?php
                    $statusMap = [
                        'pending'           => ['info','clock','Pending'],
                        'processing'        => ['primary','gear','Processing'],
                        'in_transit'        => ['warning','truck','In Transit'],
                        'out_for_delivery'  => ['primary','truck-front','Out for Delivery'],
                        'delivered'         => ['success','check-circle-fill','Delivered'],
                        'failed'            => ['danger','x-circle','Delivery Failed'],
                        'returned'          => ['secondary','arrow-return-left','Returned'],
                    ];
                    $st = $statusMap[$shipment['status']] ?? ['secondary','circle','Unknown'];
                    $events = json_decode($shipment['events'] ?? '[]', true) ?: [];
                    ?>
                    <div class="card border-0 bg-light mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Tracking: <strong><?= e($shipment['tracking_number']) ?></strong></h6>
                                    <?php if ($shipment['order_number']): ?><small class="text-muted">Order: <?= e($shipment['order_number']) ?></small><?php endif; ?>
                                </div>
                                <span class="badge bg-<?= $st[0] ?> py-2 px-3 fs-6">
                                    <i class="bi bi-<?= $st[1] ?> me-1"></i><?= $st[2] ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <?php if ($shipment['carrier']): ?>
                        <div class="col-sm-6"><small class="text-muted d-block">Carrier</small><strong><?= e($shipment['carrier']) ?></strong></div>
                        <?php endif; ?>
                        <?php if ($shipment['estimated_delivery']): ?>
                        <div class="col-sm-6"><small class="text-muted d-block">Est. Delivery</small><strong><?= formatDate($shipment['estimated_delivery']) ?></strong></div>
                        <?php endif; ?>
                        <?php if ($shipment['origin']): ?>
                        <div class="col-sm-6"><small class="text-muted d-block">Origin</small><strong><?= e($shipment['origin']) ?></strong></div>
                        <?php endif; ?>
                        <?php if ($shipment['destination']): ?>
                        <div class="col-sm-6"><small class="text-muted d-block">Destination</small><strong><?= e($shipment['destination']) ?></strong></div>
                        <?php endif; ?>
                        <?php if ($shipment['shipped_at']): ?>
                        <div class="col-sm-6"><small class="text-muted d-block">Shipped</small><strong><?= formatDateTime($shipment['shipped_at']) ?></strong></div>
                        <?php endif; ?>
                        <?php if ($shipment['delivered_at']): ?>
                        <div class="col-sm-6"><small class="text-muted d-block">Delivered</small><strong><?= formatDateTime($shipment['delivered_at']) ?></strong></div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($events)): ?>
                    <h6 class="fw-bold mb-3">Tracking History</h6>
                    <div class="timeline">
                        <?php foreach (array_reverse($events) as $event): ?>
                        <div class="d-flex gap-3 mb-3">
                            <div class="flex-shrink-0 mt-1"><span class="badge rounded-pill bg-primary">&nbsp;</span></div>
                            <div>
                                <strong class="d-block"><?= e($event['status'] ?? '') ?></strong>
                                <small class="text-muted"><?= e($event['location'] ?? '') ?> — <?= e($event['timestamp'] ?? '') ?></small>
                                <?php if (!empty($event['description'])): ?><p class="mb-0 small"><?= e($event['description']) ?></p><?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
