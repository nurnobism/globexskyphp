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

// Fetch trips
$trips = [];
try {
    $stmt = $db->prepare("SELECT * FROM carrier_trips WHERE carrier_id = ? ORDER BY created_at DESC");
    $stmt->execute([$carrierId]);
    $trips = $stmt->fetchAll();
} catch (Exception $e) {
    $trips = [];
}

$countries = [
    'United States', 'United Kingdom', 'Canada', 'Australia', 'Germany',
    'France', 'Italy', 'Spain', 'China', 'Japan', 'India', 'Brazil',
    'Mexico', 'South Africa', 'Nigeria', 'Kenya', 'UAE', 'Saudi Arabia',
    'Singapore', 'Malaysia', 'Indonesia', 'Pakistan', 'Bangladesh',
    'Egypt', 'Turkey', 'Argentina', 'Colombia', 'Chile', 'Ghana', 'Ethiopia',
];

$statusColors = [
    'active'    => 'success',
    'completed' => 'secondary',
    'cancelled' => 'danger',
    'expired'   => 'warning',
];

$pageTitle = 'My Trips';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-map-fill text-primary me-2"></i>My Trips</h3>
        <div class="d-flex gap-2">
            <a href="/pages/shipment/carry/dashboard.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTripModal">
                <i class="bi bi-plus-circle me-1"></i> Add New Trip
            </button>
        </div>
    </div>

    <!-- Trips Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold">All Trips</h6>
        </div>
        <?php if (empty($trips)): ?>
        <div class="card-body text-center py-5">
            <i class="bi bi-airplane text-muted display-3"></i>
            <h5 class="mt-3 text-muted">No trips yet</h5>
            <p class="text-muted">Post your first trip to start earning.</p>
            <button class="btn btn-primary mt-1" data-bs-toggle="modal" data-bs-target="#addTripModal">
                <i class="bi bi-plus-circle me-1"></i> Add New Trip
            </button>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Route</th>
                        <th>Travel Date</th>
                        <th>Capacity (kg)</th>
                        <th>Price/kg</th>
                        <th>Mode</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($trips as $trip): ?>
                <?php $color = $statusColors[$trip['status']] ?? 'secondary'; ?>
                <tr>
                    <td>
                        <strong><?= e($trip['departure_city']) ?></strong>, <?= e($trip['departure_country_name'] ?? $trip['departure_country']) ?>
                        <i class="bi bi-arrow-right text-muted mx-1"></i>
                        <strong><?= e($trip['arrival_city']) ?></strong>, <?= e($trip['arrival_country_name'] ?? $trip['arrival_country']) ?>
                    </td>
                    <td><?= formatDate($trip['travel_date']) ?></td>
                    <td><?= number_format((float)$trip['available_capacity_kg'], 1) ?></td>
                    <td><?= formatMoney((float)$trip['price_per_kg']) ?></td>
                    <td><span class="text-capitalize"><?= e($trip['transport_mode']) ?></span></td>
                    <td><span class="badge bg-<?= $color ?>"><?= ucfirst(e($trip['status'])) ?></span></td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn btn-outline-primary btn-sm"
                                data-bs-toggle="modal"
                                data-bs-target="#editTripModal"
                                data-id="<?= (int)$trip['id'] ?>"
                                data-departure_city="<?= e($trip['departure_city']) ?>"
                                data-departure_country="<?= e($trip['departure_country']) ?>"
                                data-arrival_city="<?= e($trip['arrival_city']) ?>"
                                data-arrival_country="<?= e($trip['arrival_country']) ?>"
                                data-travel_date="<?= e($trip['travel_date']) ?>"
                                data-available_capacity_kg="<?= e($trip['available_capacity_kg']) ?>"
                                data-price_per_kg="<?= e($trip['price_per_kg']) ?>"
                                data-transport_mode="<?= e($trip['transport_mode']) ?>"
                                data-notes="<?= e($trip['notes'] ?? '') ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php if ($trip['status'] !== 'cancelled' && $trip['status'] !== 'completed'): ?>
                            <form method="POST" action="/api/carry.php?action=cancel_trip" class="d-inline"
                                  onsubmit="return confirm('Cancel this trip?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="trip_id" value="<?= (int)$trip['id'] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Trip Modal -->
<div class="modal fade" id="addTripModal" tabindex="-1" aria-labelledby="addTripModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addTripModalLabel">
                    <i class="bi bi-plus-circle me-2 text-primary"></i>Add New Trip
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="/api/carry.php?action=add_trip">
                <?= csrfField() ?>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Departure City *</label>
                            <input type="text" name="departure_city" class="form-control" required placeholder="e.g., London">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Departure Country *</label>
                            <select name="departure_country" class="form-select" required>
                                <option value="">Select country...</option>
                                <?php foreach ($countries as $country): ?>
                                <option value="<?= e($country) ?>"><?= e($country) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Arrival City *</label>
                            <input type="text" name="arrival_city" class="form-control" required placeholder="e.g., New York">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Arrival Country *</label>
                            <select name="arrival_country" class="form-select" required>
                                <option value="">Select country...</option>
                                <?php foreach ($countries as $country): ?>
                                <option value="<?= e($country) ?>"><?= e($country) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Travel Date *</label>
                            <input type="date" name="travel_date" class="form-control" required
                                   min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Transport Mode *</label>
                            <select name="transport_mode" class="form-select" required>
                                <option value="">Select mode...</option>
                                <option value="flight">✈ Flight</option>
                                <option value="bus">🚌 Bus</option>
                                <option value="train">🚆 Train</option>
                                <option value="car">🚗 Car</option>
                                <option value="ship">🚢 Ship</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Available Capacity (kg) *</label>
                            <input type="number" name="available_capacity_kg" class="form-control" required
                                   min="0.1" max="1000" step="0.1" placeholder="e.g., 10">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Price per kg (USD) *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="price_per_kg" class="form-control" required
                                       min="0.01" step="0.01" placeholder="e.g., 5.00">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea name="notes" class="form-control" rows="3"
                                placeholder="Any special instructions or details about your trip..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i> Post Trip
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Trip Modal -->
<div class="modal fade" id="editTripModal" tabindex="-1" aria-labelledby="editTripModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editTripModalLabel">
                    <i class="bi bi-pencil me-2 text-primary"></i>Edit Trip
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="/api/carry.php?action=edit_trip">
                <?= csrfField() ?>
                <input type="hidden" name="trip_id" id="editTripId">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Departure City *</label>
                            <input type="text" name="departure_city" id="editDepartureCity" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Departure Country *</label>
                            <select name="departure_country" id="editDepartureCountry" class="form-select" required>
                                <option value="">Select country...</option>
                                <?php foreach ($countries as $country): ?>
                                <option value="<?= e($country) ?>"><?= e($country) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Arrival City *</label>
                            <input type="text" name="arrival_city" id="editArrivalCity" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Arrival Country *</label>
                            <select name="arrival_country" id="editArrivalCountry" class="form-select" required>
                                <option value="">Select country...</option>
                                <?php foreach ($countries as $country): ?>
                                <option value="<?= e($country) ?>"><?= e($country) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Travel Date *</label>
                            <input type="date" name="travel_date" id="editTravelDate" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Transport Mode *</label>
                            <select name="transport_mode" id="editTransportMode" class="form-select" required>
                                <option value="">Select mode...</option>
                                <option value="flight">✈ Flight</option>
                                <option value="bus">🚌 Bus</option>
                                <option value="train">🚆 Train</option>
                                <option value="car">🚗 Car</option>
                                <option value="ship">🚢 Ship</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Available Capacity (kg) *</label>
                            <input type="number" name="available_capacity_kg" id="editCapacity" class="form-control" required min="0.1" step="0.1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Price per kg (USD) *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="price_per_kg" id="editPrice" class="form-control" required min="0.01" step="0.01">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea name="notes" id="editNotes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var editModal = document.getElementById('editTripModal');
    if (!editModal) return;
    editModal.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        document.getElementById('editTripId').value            = btn.dataset.id;
        document.getElementById('editDepartureCity').value     = btn.dataset.departure_city;
        document.getElementById('editArrivalCity').value       = btn.dataset.arrival_city;
        document.getElementById('editTravelDate').value        = btn.dataset.travel_date;
        document.getElementById('editCapacity').value          = btn.dataset.available_capacity_kg;
        document.getElementById('editPrice').value             = btn.dataset.price_per_kg;
        document.getElementById('editNotes').value             = btn.dataset.notes;

        var depCountry  = document.getElementById('editDepartureCountry');
        var arrCountry  = document.getElementById('editArrivalCountry');
        var transMode   = document.getElementById('editTransportMode');

        for (var i = 0; i < depCountry.options.length; i++) {
            if (depCountry.options[i].value === btn.dataset.departure_country) {
                depCountry.selectedIndex = i; break;
            }
        }
        for (var j = 0; j < arrCountry.options.length; j++) {
            if (arrCountry.options[j].value === btn.dataset.arrival_country) {
                arrCountry.selectedIndex = j; break;
            }
        }
        for (var k = 0; k < transMode.options.length; k++) {
            if (transMode.options[k].value === btn.dataset.transport_mode) {
                transMode.selectedIndex = k; break;
            }
        }
    });
}());
</script>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
