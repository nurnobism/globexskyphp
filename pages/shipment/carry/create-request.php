<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];

// Check registration
$stmt = $db->prepare("SELECT * FROM carriers WHERE user_id = ? AND status = 'active'");
$stmt->execute([$userId]);
if (!$stmt->fetch()) {
    header('Location: /pages/shipment/carry/register.php');
    exit;
}

$pageTitle = 'Post New Carry Trip';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-airplane-fill me-2"></i>Post a New Carry Trip</h5>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-info mb-4">
                        <i class="bi bi-info-circle me-2"></i>
                        Post your upcoming flight details to let senders know you can carry items for them.
                    </div>
                    <form method="POST" action="/api/carry.php?action=create_request" enctype="multipart/form-data">
                        <?= csrfField() ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">From City / Airport *</label>
                                <input type="text" name="from_city" class="form-control" required placeholder="e.g., New York (JFK)">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">To City / Airport *</label>
                                <input type="text" name="to_city" class="form-control" required placeholder="e.g., London (LHR)">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Flight Date *</label>
                                <input type="date" name="flight_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Available Weight (kg) *</label>
                                <input type="number" name="available_weight" class="form-control" required min="0.5" max="50" step="0.5" placeholder="e.g., 5">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Rate per kg (USD) *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="rate_per_kg" class="form-control" required min="1" step="0.5" placeholder="e.g., 15">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Flight Ticket (optional)</label>
                                <input type="file" name="flight_ticket" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                                <div class="form-text">Upload for verification (max 5MB)</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Accepted Item Types</label>
                                <div class="row g-2">
                                    <?php foreach (['Documents', 'Electronics', 'Clothing', 'Cosmetics', 'Food (sealed)', 'Gifts', 'Accessories', 'Other'] as $type): ?>
                                    <div class="col-6 col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="item_types[]"
                                                   value="<?= $type ?>" id="type_<?= md5($type) ?>">
                                            <label class="form-check-label" for="type_<?= md5($type) ?>"><?= $type ?></label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-check-circle me-1"></i> Post Trip
                            </button>
                            <a href="/pages/shipment/carry/dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
