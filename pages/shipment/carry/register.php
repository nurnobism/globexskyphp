<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];

// Check if already registered
$stmt = $db->prepare("SELECT * FROM carriers WHERE user_id = ?");
$stmt->execute([$userId]);
$existing = $stmt->fetch();

$pageTitle = 'Register as Carry Provider';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <?php if ($existing): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <?php if ($existing['status'] === 'active'): ?>
                        <i class="bi bi-check-circle-fill text-success display-3"></i>
                        <h4 class="mt-3">You are an active Carry Provider!</h4>
                        <p class="text-muted">Your carry provider account is verified and active.</p>
                        <a href="/pages/shipment/carry/dashboard.php" class="btn btn-primary mt-2">Go to Dashboard</a>
                    <?php elseif ($existing['status'] === 'pending'): ?>
                        <i class="bi bi-clock-fill text-warning display-3"></i>
                        <h4 class="mt-3">Registration Under Review</h4>
                        <p class="text-muted">Your application is being reviewed. We'll notify you within 2-3 business days.</p>
                    <?php else: ?>
                        <i class="bi bi-x-circle-fill text-danger display-3"></i>
                        <h4 class="mt-3">Registration Not Approved</h4>
                        <p class="text-muted">Your previous application was not approved. Please re-apply with correct information.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-person-badge-fill me-2"></i>Register as Carry Service Provider</h5>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-info mb-4">
                        <i class="bi bi-info-circle me-2"></i>
                        As a carry provider, you can offer to carry items for others during your travels and earn money for each delivery.
                    </div>
                    <form method="POST" action="/api/carry.php?action=register" enctype="multipart/form-data">
                        <?= csrfField() ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Full Name *</label>
                                <input type="text" name="full_name" class="form-control" required
                                       value="<?= e(getCurrentUser()['first_name'] . ' ' . getCurrentUser()['last_name']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Phone Number *</label>
                                <input type="tel" name="phone" class="form-control" required placeholder="+1 234 567 8900">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Passport Number *</label>
                                <input type="text" name="passport_number" class="form-control" required placeholder="e.g., AB1234567">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Nationality</label>
                                <input type="text" name="nationality" class="form-control" placeholder="e.g., United States">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Travel Frequency</label>
                                <select name="travel_frequency" class="form-select">
                                    <option value="">Select...</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="biweekly">Bi-weekly</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="occasional">Occasional</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">ID Document Upload</label>
                                <input type="file" name="id_upload" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                                <div class="form-text">Passport or government-issued ID (max 5MB)</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Bio / Experience</label>
                                <textarea name="bio" class="form-control" rows="3"
                                    placeholder="Tell us about your travel experience and routes you frequently travel..."></textarea>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="agree_terms" id="agreeTerms" required>
                                    <label class="form-check-label" for="agreeTerms">
                                        I agree to the <a href="/pages/terms.php" target="_blank">Terms of Service</a> and
                                        <a href="/pages/privacy.php" target="_blank">Privacy Policy</a>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-send me-1"></i> Submit Application
                            </button>
                            <a href="/pages/shipment/index.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
