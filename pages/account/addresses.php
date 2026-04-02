<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$db    = getDB();
$stmt  = $db->prepare('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id');
$stmt->execute([$_SESSION['user_id']]);
$addresses = $stmt->fetchAll();

$pageTitle = 'My Addresses';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold"><i class="bi bi-geo-alt-fill text-primary me-2"></i>My Addresses</h3>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAddressModal">
                    <i class="bi bi-plus-circle me-1"></i> Add Address
                </button>
            </div>

            <?php if (empty($addresses)): ?>
                <div class="alert alert-info">No addresses saved yet. Add one below.</div>
            <?php else: ?>
            <div class="row g-3">
                <?php foreach ($addresses as $addr): ?>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm <?= $addr['is_default'] ? 'border-primary border-2' : '' ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="badge bg-secondary mb-1"><?= e($addr['label']) ?></span>
                                    <?php if ($addr['is_default']): ?><span class="badge bg-primary mb-1">Default</span><?php endif; ?>
                                    <h6 class="mb-1"><?= e($addr['full_name']) ?></h6>
                                    <p class="mb-0 small text-muted">
                                        <?= e($addr['address_line1']) ?><?= $addr['address_line2'] ? ', ' . e($addr['address_line2']) : '' ?><br>
                                        <?= e($addr['city']) ?><?= $addr['state'] ? ', ' . e($addr['state']) : '' ?> <?= e($addr['postal_code']) ?><br>
                                        <?= e($addr['country']) ?>
                                        <?php if ($addr['phone']): ?><br><i class="bi bi-phone"></i> <?= e($addr['phone']) ?><?php endif; ?>
                                    </p>
                                </div>
                                <div>
                                    <form method="POST" action="/api/users.php?action=delete_address" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="address_id" value="<?= $addr['id'] ?>">
                                        <input type="hidden" name="_redirect" value="/pages/account/addresses.php">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this address?')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Address Modal -->
<div class="modal fade" id="addAddressModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-geo-alt-fill me-2"></i>Add New Address</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/api/users.php?action=add_address">
                <?= csrfField() ?>
                <input type="hidden" name="_redirect" value="/pages/account/addresses.php">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Label</label>
                            <select name="label" class="form-select">
                                <option>Home</option><option>Office</option><option>Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Country *</label>
                            <input type="text" name="country" class="form-control" value="US" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address Line 1 *</label>
                            <input type="text" name="address_line1" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address Line 2</label>
                            <input type="text" name="address_line2" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">City *</label>
                            <input type="text" name="city" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">State/Province</label>
                            <input type="text" name="state" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Postal Code</label>
                            <input type="text" name="postal_code" class="form-control">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="is_default" value="1" class="form-check-input" id="setDefault">
                                <label class="form-check-label" for="setDefault">Set as default address</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Address</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
