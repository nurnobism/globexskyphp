<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];

$stmt = $db->prepare("SELECT * FROM carry_trips WHERE user_id = ? AND status = 'active' ORDER BY flight_date ASC");
$stmt->execute([$userId]);
$activeTrips = $stmt->fetchAll();

$pageTitle = 'Active Carry Jobs';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-broadcast text-success me-2"></i>Active Carry Jobs</h3>
        <a href="/pages/shipment/carry/create-request.php" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i> Post New Trip
        </a>
    </div>

    <?php if (empty($activeTrips)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-inbox text-muted display-3"></i>
            <h5 class="mt-3 text-muted">No active carry jobs</h5>
            <p class="text-muted">Post a trip to start accepting carry requests.</p>
            <a href="/pages/shipment/carry/create-request.php" class="btn btn-primary">Post a Trip</a>
        </div>
    </div>
    <?php else: ?>
    <div class="row g-3">
        <?php foreach ($activeTrips as $trip): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="fw-bold mb-0">
                            <i class="bi bi-airplane me-1 text-primary"></i>
                            <?= e($trip['from_city']) ?> → <?= e($trip['to_city']) ?>
                        </h6>
                        <span class="badge bg-success">Active</span>
                    </div>
                    <p class="text-muted small mb-2">
                        <i class="bi bi-calendar me-1"></i><?= formatDate($trip['flight_date']) ?>
                    </p>
                    <div class="row g-2 text-center mb-3">
                        <div class="col-6">
                            <div class="bg-light rounded p-2">
                                <div class="fw-bold"><?= number_format($trip['available_weight'], 1) ?> kg</div>
                                <small class="text-muted">Available</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-light rounded p-2">
                                <div class="fw-bold">$<?= number_format($trip['rate_per_kg'], 2) ?></div>
                                <small class="text-muted">Per kg</small>
                            </div>
                        </div>
                    </div>
                    <?php if ($trip['item_types']): ?>
                    <p class="small text-muted mb-2"><strong>Accepts:</strong> <?= e($trip['item_types']) ?></p>
                    <?php endif; ?>
                    <form method="POST" action="/api/carry.php?action=complete" class="d-inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="delivery_id" value="<?= $trip['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-secondary"
                                onclick="return confirm('Mark this trip as completed?')">
                            <i class="bi bi-check-circle me-1"></i> Mark Complete
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
