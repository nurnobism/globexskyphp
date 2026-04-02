<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];

// Get user's saved addresses
$stmt = $db->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, label ASC");
$stmt->execute([$userId]);
$addresses = $stmt->fetchAll();

$pageTitle = 'Create Parcel Shipment';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-box-seam me-2"></i>Create New Parcel Shipment</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="/api/parcels.php?action=create" id="parcelForm">
                        <?= csrfField() ?>
                        <h6 class="fw-bold mb-3 border-bottom pb-2">
                            <i class="bi bi-geo-alt me-2 text-primary"></i>Addresses
                        </h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Sender Address *</label>
                                <select name="sender_address_id" class="form-select" required>
                                    <option value="">Select sender address...</option>
                                    <?php foreach ($addresses as $addr): ?>
                                    <option value="<?= $addr['id'] ?>" <?= $addr['is_default'] ? 'selected' : '' ?>>
                                        <?= e($addr['label'] . ' — ' . $addr['city'] . ', ' . $addr['country']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <a href="/pages/shipment/parcel/addresses.php" class="form-text text-primary">+ Add new address</a>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Receiver Address *</label>
                                <select name="receiver_address_id" class="form-select" required>
                                    <option value="">Select receiver address...</option>
                                    <?php foreach ($addresses as $addr): ?>
                                    <option value="<?= $addr['id'] ?>">
                                        <?= e($addr['label'] . ' — ' . $addr['city'] . ', ' . $addr['country']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <a href="/pages/shipment/parcel/addresses.php" class="form-text text-primary">+ Add new address</a>
                            </div>
                        </div>

                        <h6 class="fw-bold mb-3 border-bottom pb-2">
                            <i class="bi bi-box me-2 text-primary"></i>Package Details
                        </h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Weight (kg) *</label>
                                <input type="number" name="weight" class="form-control" required min="0.1" max="100" step="0.1" placeholder="e.g., 2.5">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Length (cm)</label>
                                <input type="number" name="length" class="form-control" min="1" step="0.1" placeholder="cm">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Width (cm)</label>
                                <input type="number" name="width" class="form-control" min="1" step="0.1" placeholder="cm">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Height (cm)</label>
                                <input type="number" name="height" class="form-control" min="1" step="0.1" placeholder="cm">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Contents Description *</label>
                                <textarea name="contents" class="form-control" rows="2" required
                                    placeholder="Describe the package contents (e.g., clothing, electronics, documents)..."></textarea>
                            </div>
                        </div>

                        <h6 class="fw-bold mb-3 border-bottom pb-2">
                            <i class="bi bi-lightning me-2 text-primary"></i>Delivery Options
                        </h6>
                        <div class="row g-3 mb-4">
                            <?php $speeds = [
                                ['standard', 'Standard', '5-7 days', '1.0x', 'secondary'],
                                ['express',  'Express',  '2-3 days', '1.6x', 'warning'],
                                ['priority', 'Priority', '1-2 days', '2.2x', 'danger'],
                            ]; ?>
                            <?php foreach ($speeds as [$val, $label, $days, $mult, $color]): ?>
                            <div class="col-md-4">
                                <input type="radio" class="btn-check" name="speed" id="speed_<?= $val ?>"
                                       value="<?= $val ?>" <?= $val === 'standard' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-<?= $color ?> w-100 d-flex flex-column align-items-center py-3" for="speed_<?= $val ?>">
                                    <i class="bi bi-<?= $val === 'priority' ? 'rocket' : ($val === 'express' ? 'lightning' : 'truck') ?> fs-4 mb-1"></i>
                                    <strong><?= $label ?></strong>
                                    <small><?= $days ?></small>
                                    <small class="text-muted"><?= $mult ?> rate</small>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" name="insurance" id="insurance">
                            <label class="form-check-label" for="insurance">
                                <strong>Add Shipping Insurance</strong>
                                <span class="text-muted ms-1">(Recommended for valuable items)</span>
                            </label>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-send me-1"></i> Create Shipment
                            </button>
                            <a href="/pages/shipment/parcel/calculator.php" class="btn btn-outline-secondary">
                                <i class="bi bi-calculator me-1"></i> Calculate Cost First
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
