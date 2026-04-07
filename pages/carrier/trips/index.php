<?php
/**
 * pages/carrier/trips/index.php — Carrier Trip Management — PR #16
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/carry.php';
requireAuth();

$userId  = (int)$_SESSION['user_id'];
$action  = trim($_GET['action'] ?? $_POST['action'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$message = '';
$msgType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if ($action === 'create') {
        $result = createTrip($userId, $_POST);
        if ($result['success']) {
            flashMessage('success', 'Trip posted successfully!');
            redirect('/pages/carrier/trips/index.php');
        } else {
            $message = $result['error'] ?? 'Failed to create trip';
            $msgType = 'danger';
        }
    } elseif ($action === 'update') {
        $tripId = (int)($_POST['trip_id'] ?? 0);
        $result = updateTrip($tripId, $userId, $_POST);
        if ($result['success']) {
            flashMessage('success', 'Trip updated successfully!');
            redirect('/pages/carrier/trips/index.php');
        } else {
            $message = $result['error'] ?? 'Failed to update trip';
            $msgType = 'danger';
        }
    } elseif ($action === 'delete') {
        $tripId = (int)($_POST['trip_id'] ?? 0);
        $result = deleteTrip($tripId, $userId);
        if ($result['success']) {
            flashMessage('success', 'Trip cancelled.');
            redirect('/pages/carrier/trips/index.php');
        } else {
            flashMessage('danger', $result['error'] ?? 'Failed to cancel trip.');
            redirect('/pages/carrier/trips/index.php');
        }
    }
}

// Load trip for editing
$editTrip = null;
if ($action === 'edit' && isset($_GET['trip_id'])) {
    $editTrip = getTrip((int)$_GET['trip_id']);
    if (!$editTrip || (int)$editTrip['carrier_id'] !== $userId) {
        $editTrip = null;
    }
}

$result = getCarrierTrips($userId, [], $page, $perPage);
$trips  = $result['trips'];
$total  = $result['total'];
$pages  = $result['pages'];

$statusColors = ['active'=>'success','inactive'=>'secondary','cancelled'=>'danger','completed'=>'primary'];
$pageTitle    = 'My Trips';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-airplane text-primary me-2"></i>My Trips</h3>
        <button class="btn btn-primary" onclick="showForm()" id="postTripBtn">
            <i class="bi bi-plus-circle me-1"></i> Post New Trip
        </button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= htmlspecialchars($msgType) ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php $flash = getFlashMessage(); if ($flash): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
    <?php endif; ?>

    <!-- Trip Form (collapsible) -->
    <div class="card border-0 shadow-sm mb-4 <?= ($action !== 'new' && !$editTrip) ? 'd-none' : '' ?>" id="tripFormCard">
        <div class="card-header bg-white fw-bold">
            <i class="bi bi-calendar-plus text-primary me-1"></i>
            <?= $editTrip ? 'Edit Trip' : 'Post New Trip' ?>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                <input type="hidden" name="action" value="<?= $editTrip ? 'update' : 'create' ?>">
                <?php if ($editTrip): ?>
                    <input type="hidden" name="trip_id" value="<?= (int)$editTrip['id'] ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Origin City <span class="text-danger">*</span></label>
                        <input type="text" name="origin_city" class="form-control" required
                               value="<?= htmlspecialchars($editTrip['origin_city'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Origin Country <span class="text-danger">*</span></label>
                        <input type="text" name="origin_country" class="form-control" required
                               value="<?= htmlspecialchars($editTrip['origin_country'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Destination City <span class="text-danger">*</span></label>
                        <input type="text" name="destination_city" class="form-control" required
                               value="<?= htmlspecialchars($editTrip['destination_city'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Destination Country <span class="text-danger">*</span></label>
                        <input type="text" name="destination_country" class="form-control" required
                               value="<?= htmlspecialchars($editTrip['destination_country'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Departure Date <span class="text-danger">*</span></label>
                        <input type="date" name="departure_date" class="form-control" required
                               value="<?= htmlspecialchars($editTrip['departure_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Arrival Date <span class="text-danger">*</span></label>
                        <input type="date" name="arrival_date" class="form-control" required
                               value="<?= htmlspecialchars($editTrip['arrival_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Max Weight (kg) <span class="text-danger">*</span></label>
                        <input type="number" name="max_weight_kg" class="form-control" min="0.1" step="0.1" required
                               value="<?= htmlspecialchars($editTrip['max_weight_kg'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Max Dimensions</label>
                        <input type="text" name="max_dimensions" class="form-control" placeholder="e.g. 50x40x30 cm"
                               value="<?= htmlspecialchars($editTrip['max_dimensions'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Price per kg ($)</label>
                        <input type="number" name="price_per_kg" class="form-control" min="0" step="0.01"
                               value="<?= htmlspecialchars($editTrip['price_per_kg'] ?? '0') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Flat Rate ($, optional)</label>
                        <input type="number" name="flat_rate" class="form-control" min="0" step="0.01"
                               placeholder="Leave empty to use per-kg pricing"
                               value="<?= htmlspecialchars($editTrip['flat_rate'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Active</label>
                        <div class="form-check form-switch mt-2">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input"
                                   <?= (!$editTrip || $editTrip['is_active']) ? 'checked' : '' ?>>
                            <label class="form-check-label">List trip publicly</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Available Space Description</label>
                        <textarea name="available_space_description" class="form-control" rows="2" placeholder="e.g. Carry-on luggage space, checked bag, small parcels only..."><?= htmlspecialchars($editTrip['available_space_description'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Carrier Notes</label>
                        <textarea name="carrier_notes" class="form-control" rows="2" placeholder="Any special requirements or instructions..."><?= htmlspecialchars($editTrip['carrier_notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> <?= $editTrip ? 'Update Trip' : 'Post Trip' ?>
                    </button>
                    <button type="button" class="btn btn-outline-secondary ms-2" onclick="hideForm()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Trips Table -->
    <?php if (!empty($trips)): ?>
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Route</th>
                            <th>Departure</th>
                            <th>Capacity</th>
                            <th>Pricing</th>
                            <th>Requests</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trips as $trip): ?>
                            <tr>
                                <td class="fw-semibold">
                                    <?= htmlspecialchars("{$trip['origin_city']}, {$trip['origin_country']}") ?>
                                    <i class="bi bi-arrow-right text-muted mx-1"></i>
                                    <?= htmlspecialchars("{$trip['destination_city']}, {$trip['destination_country']}") ?>
                                </td>
                                <td><?= date('M d, Y', strtotime($trip['departure_date'])) ?></td>
                                <td><?= number_format($trip['max_weight_kg'], 1) ?> kg</td>
                                <td>
                                    <?php if ($trip['flat_rate']): ?>
                                        $<?= number_format($trip['flat_rate'], 2) ?> flat
                                    <?php else: ?>
                                        $<?= number_format($trip['price_per_kg'], 2) ?>/kg
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="/pages/carrier/requests/index.php?trip_id=<?= (int)$trip['id'] ?>" class="badge bg-primary text-decoration-none">
                                        <?= (int)($trip['request_count'] ?? 0) ?> request(s)
                                    </a>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $statusColors[$trip['status']] ?? 'secondary' ?>">
                                        <?= ucfirst($trip['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!in_array($trip['status'], ['cancelled', 'completed'], true)): ?>
                                        <a href="?action=edit&trip_id=<?= (int)$trip['id'] ?>" class="btn btn-sm btn-outline-primary me-1">Edit</a>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Cancel this trip?')">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="trip_id" value="<?= (int)$trip['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($p = 1; $p <= $pages; $p++): ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>

    <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-airplane display-1 text-muted"></i>
            <h5 class="mt-3 text-muted">No trips yet</h5>
            <button class="btn btn-primary mt-2" onclick="showForm()">Post Your First Trip</button>
        </div>
    <?php endif; ?>
</div>

<script>
function showForm() {
    document.getElementById('tripFormCard').classList.remove('d-none');
    document.getElementById('postTripBtn').classList.add('d-none');
    document.getElementById('tripFormCard').scrollIntoView({ behavior: 'smooth' });
}
function hideForm() {
    document.getElementById('tripFormCard').classList.add('d-none');
    document.getElementById('postTripBtn').classList.remove('d-none');
}
<?php if ($action === 'new'): ?>
showForm();
<?php endif; ?>
</script>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
