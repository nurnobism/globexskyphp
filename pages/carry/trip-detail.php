<?php
/**
 * pages/carry/trip-detail.php — Trip Detail & Request Form — PR #16
 */
require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/carry.php';

$tripId = (int)($_GET['id'] ?? 0);
if (!$tripId) {
    redirect('/pages/carry/index.php');
}

$trip = getTrip($tripId);
if (!$trip || $trip['status'] !== 'active' || !$trip['is_active']) {
    flashMessage('warning', 'This trip is no longer available.');
    redirect('/pages/carry/index.php');
}

// Similar trips on same route
$similarFilters = [
    'origin'      => $trip['origin_country'],
    'destination' => $trip['destination_country'],
];
$similar = getTrips($similarFilters, 1, 4);
$similar['trips'] = array_filter($similar['trips'], fn($t) => (int)$t['id'] !== $tripId);

// Suggested fee for 1 kg
$suggestedFee = calculateCarryFee($tripId, 1.0);

$pageTitle = htmlspecialchars("{$trip['origin_city']} → {$trip['destination_city']} Trip");
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/pages/carry/index.php">Carry Trips</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars("{$trip['origin_city']} → {$trip['destination_city']}") ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <!-- Trip Info -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h4 class="fw-bold mb-3">
                        <i class="bi bi-airplane text-primary me-2"></i>
                        <?= htmlspecialchars("{$trip['origin_city']}, {$trip['origin_country']}") ?>
                        &nbsp;<i class="bi bi-arrow-right text-muted"></i>&nbsp;
                        <?= htmlspecialchars("{$trip['destination_city']}, {$trip['destination_country']}") ?>
                    </h4>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-calendar-event text-primary me-2"></i>
                                <div>
                                    <div class="small text-muted">Departure</div>
                                    <div class="fw-semibold"><?= date('D, M j, Y', strtotime($trip['departure_date'])) ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-calendar-check text-success me-2"></i>
                                <div>
                                    <div class="small text-muted">Arrival</div>
                                    <div class="fw-semibold"><?= date('D, M j, Y', strtotime($trip['arrival_date'])) ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-box-seam text-warning me-2"></i>
                                <div>
                                    <div class="small text-muted">Max Weight</div>
                                    <div class="fw-semibold"><?= number_format($trip['max_weight_kg'], 1) ?> kg</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-cash text-success me-2"></i>
                                <div>
                                    <div class="small text-muted">Pricing</div>
                                    <div class="fw-semibold">
                                        <?php if ($trip['flat_rate']): ?>
                                            $<?= number_format($trip['flat_rate'], 2) ?> flat rate
                                        <?php else: ?>
                                            $<?= number_format($trip['price_per_kg'], 2) ?>/kg
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($trip['available_space_description'])): ?>
                        <div class="mb-3">
                            <h6 class="fw-semibold">Available Space</h6>
                            <p class="text-muted mb-0"><?= nl2br(htmlspecialchars($trip['available_space_description'])) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($trip['max_dimensions'])): ?>
                        <div class="mb-3">
                            <h6 class="fw-semibold">Max Dimensions</h6>
                            <p class="text-muted mb-0"><?= htmlspecialchars($trip['max_dimensions']) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($trip['carrier_notes'])): ?>
                        <div class="alert alert-light border">
                            <i class="bi bi-info-circle me-1 text-primary"></i>
                            <strong>Carrier Notes:</strong> <?= nl2br(htmlspecialchars($trip['carrier_notes'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Request Form -->
            <?php if (isLoggedIn() && (int)$_SESSION['user_id'] !== (int)$trip['carrier_id']): ?>
                <div class="card border-0 shadow-sm" id="requestForm">
                    <div class="card-header bg-white fw-bold">
                        <i class="bi bi-send text-primary me-1"></i> Send Carry Request
                    </div>
                    <div class="card-body">
                        <form id="carryRequestForm">
                            <input type="hidden" name="action" value="request_carry">
                            <input type="hidden" name="trip_id" value="<?= (int)$tripId ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">

                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Package Description <span class="text-danger">*</span></label>
                                    <textarea name="package_description" class="form-control" rows="2" placeholder="Briefly describe your package contents..." required></textarea>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label fw-semibold">Weight (kg) <span class="text-danger">*</span></label>
                                    <input type="number" name="weight_kg" class="form-control" min="0.1" max="<?= (float)$trip['max_weight_kg'] ?>" step="0.1" required
                                           placeholder="e.g. 2.5" id="weightInput">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label fw-semibold">Dimensions (optional)</label>
                                    <input type="text" name="dimensions" class="form-control" placeholder="e.g. 30x20x15 cm">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label fw-semibold">Pickup Address <span class="text-danger">*</span></label>
                                    <input type="text" name="pickup_address" class="form-control" placeholder="Package pickup address" required>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label fw-semibold">Delivery Address <span class="text-danger">*</span></label>
                                    <input type="text" name="delivery_address" class="form-control" placeholder="Package delivery address" required>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label fw-semibold">Your Offer ($)</label>
                                    <input type="number" name="offered_price" class="form-control" min="0" step="0.01" id="priceInput"
                                           placeholder="Suggested: $<?= number_format($suggestedFee['fee'], 2) ?>">
                                    <div class="form-text">Suggested: $<?= number_format($trip['price_per_kg'], 2) ?>/kg × weight</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Special Instructions (optional)</label>
                                    <textarea name="special_instructions" class="form-control" rows="2" placeholder="Fragile, urgent delivery, etc."></textarea>
                                </div>
                            </div>

                            <div id="feePreview" class="alert alert-info mt-3 d-none">
                                <i class="bi bi-calculator me-1"></i>
                                Estimated fee: <strong id="feePreviewAmt"></strong>
                            </div>

                            <div id="requestMsg" class="mt-3 d-none"></div>

                            <button type="submit" class="btn btn-primary mt-3">
                                <i class="bi bi-send me-1"></i> Send Carry Request
                            </button>
                        </form>
                    </div>
                </div>
            <?php elseif (!isLoggedIn()): ?>
                <div class="alert alert-info">
                    <a href="/pages/auth/login.php">Log in</a> to send a carry request.
                </div>
            <?php endif; ?>
        </div>

        <!-- Carrier Profile Card -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">
                    <i class="bi bi-person-badge text-primary me-1"></i> Carrier Profile
                </div>
                <div class="card-body text-center">
                    <?php if (!empty($trip['avatar'])): ?>
                        <img src="<?= htmlspecialchars($trip['avatar']) ?>" class="rounded-circle mb-3" width="72" height="72" alt="Carrier" style="object-fit:cover">
                    <?php else: ?>
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-3" style="width:72px;height:72px;font-size:1.8rem">
                            <?= strtoupper(substr($trip['first_name'] ?? 'C', 0, 1)) ?>
                        </div>
                    <?php endif; ?>

                    <h5 class="fw-bold mb-1">
                        <?= htmlspecialchars(($trip['first_name'] ?? '') . ' ' . ($trip['last_name'] ?? '')) ?>
                        <?php if ($trip['carrier_verified']): ?>
                            <i class="bi bi-patch-check-fill text-success" title="Verified Carrier"></i>
                        <?php endif; ?>
                    </h5>

                    <?php if ($trip['carrier_rating'] > 0): ?>
                        <div class="text-warning mb-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi bi-star<?= $i <= round($trip['carrier_rating']) ? '-fill' : '' ?>"></i>
                            <?php endfor; ?>
                            <span class="text-muted small"><?= number_format($trip['carrier_rating'], 1) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="row g-2 mt-2 text-center">
                        <div class="col-6">
                            <div class="fw-bold"><?= (int)$trip['trips_completed'] ?></div>
                            <div class="text-muted small">Trips Completed</div>
                        </div>
                        <div class="col-6">
                            <div class="fw-bold"><?= $trip['carrier_verified'] ? '<span class="text-success">✓ Verified</span>' : '<span class="text-muted">Unverified</span>' ?></div>
                            <div class="text-muted small">KYC Status</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pricing Summary -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">
                    <i class="bi bi-cash-coin text-success me-1"></i> Pricing
                </div>
                <div class="card-body">
                    <?php if ($trip['flat_rate']): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Flat Rate</span>
                            <strong>$<?= number_format($trip['flat_rate'], 2) ?></strong>
                        </div>
                    <?php else: ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Price per kg</span>
                            <strong>$<?= number_format($trip['price_per_kg'], 2) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Max capacity</span>
                            <strong><?= number_format($trip['max_weight_kg'], 1) ?> kg</strong>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between">
                            <span>Max total</span>
                            <strong>$<?= number_format($trip['price_per_kg'] * $trip['max_weight_kg'], 2) ?></strong>
                        </div>
                    <?php endif; ?>
                    <div class="mt-3 small text-muted">
                        <i class="bi bi-shield-lock me-1 text-success"></i> Payment held in escrow until delivery confirmed
                    </div>
                </div>
            </div>

            <?php if (!isLoggedIn()): ?>
                <a href="/pages/auth/login.php" class="btn btn-primary w-100 mb-4">Log in to Request Carry</a>
            <?php elseif ((int)$_SESSION['user_id'] !== (int)$trip['carrier_id']): ?>
                <a href="#requestForm" class="btn btn-primary w-100 mb-4">
                    <i class="bi bi-send me-1"></i> Request Carry
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Similar Trips -->
    <?php if (!empty($similar['trips'])): ?>
        <h5 class="fw-bold mt-4 mb-3"><i class="bi bi-grid text-primary me-1"></i> Similar Trips</h5>
        <div class="row g-3">
            <?php foreach (array_slice($similar['trips'], 0, 3) as $st): ?>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="fw-semibold mb-1">
                                <?= htmlspecialchars("{$st['origin_city']} → {$st['destination_city']}") ?>
                            </div>
                            <div class="text-muted small mb-2"><?= date('M d, Y', strtotime($st['departure_date'])) ?></div>
                            <?php if ($st['flat_rate']): ?>
                                <span class="badge bg-primary">$<?= number_format($st['flat_rate'], 2) ?> flat</span>
                            <?php else: ?>
                                <span class="badge bg-primary">$<?= number_format($st['price_per_kg'], 2) ?>/kg</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-white border-0 pt-0">
                            <a href="/pages/carry/trip-detail.php?id=<?= (int)$st['id'] ?>" class="btn btn-outline-primary btn-sm w-100">View Trip</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
(function() {
    const weightInput = document.getElementById('weightInput');
    const priceInput  = document.getElementById('priceInput');
    const feePreview  = document.getElementById('feePreview');
    const feeAmt      = document.getElementById('feePreviewAmt');
    const tripId      = <?= (int)$tripId ?>;

    if (weightInput) {
        weightInput.addEventListener('input', function() {
            const w = parseFloat(this.value);
            if (!w || w <= 0) { feePreview.classList.add('d-none'); return; }
            fetch(`/api/carry.php?action=calculate_fee&trip_id=${tripId}&weight_kg=${w}`)
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        feeAmt.textContent = '$' + parseFloat(d.fee).toFixed(2);
                        if (priceInput && !priceInput.value) priceInput.value = d.fee;
                        feePreview.classList.remove('d-none');
                    }
                });
        });
    }

    const form = document.getElementById('carryRequestForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const msg = document.getElementById('requestMsg');
            const btn = form.querySelector('button[type=submit]');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sending...';
            fetch('/api/carry.php', { method: 'POST', body: new FormData(form) })
                .then(r => r.json())
                .then(d => {
                    msg.classList.remove('d-none');
                    if (d.success) {
                        msg.className = 'alert alert-success mt-3';
                        msg.innerHTML = '<i class="bi bi-check-circle me-1"></i> Carry request sent! The carrier will be notified. <a href="/pages/carry/my-requests.php">View My Requests</a>';
                        form.reset();
                    } else {
                        msg.className = 'alert alert-danger mt-3';
                        msg.textContent = d.error || 'Failed to send request.';
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bi bi-send me-1"></i> Send Carry Request';
                    }
                })
                .catch(() => {
                    msg.classList.remove('d-none');
                    msg.className = 'alert alert-danger mt-3';
                    msg.textContent = 'Network error. Please try again.';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-send me-1"></i> Send Carry Request';
                });
        });
    }
})();
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
