<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];

// Handle form submission (non-API direct handling)
$error = '';
$success = '';

$stmt = $db->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, label ASC");
$stmt->execute([$userId]);
$addresses = $stmt->fetchAll();

$pageTitle = 'Manage Shipping Addresses';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-geo-alt-fill text-primary me-2"></i>Shipping Addresses</h3>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAddressModal">
            <i class="bi bi-plus-circle me-1"></i> Add Address
        </button>
    </div>

    <?php if (empty($addresses)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-geo-alt text-muted display-3"></i>
            <h5 class="mt-3 text-muted">No addresses saved</h5>
            <p class="text-muted">Add a shipping address to get started.</p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAddressModal">
                Add First Address
            </button>
        </div>
    </div>
    <?php else: ?>
    <div class="row g-3">
        <?php foreach ($addresses as $addr): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100 <?= $addr['is_default'] ? 'border-primary border-2' : '' ?>">
                <?php if ($addr['is_default']): ?>
                <div class="card-header bg-primary text-white py-2 text-center small fw-bold">
                    <i class="bi bi-star-fill me-1"></i> Default Address
                </div>
                <?php endif; ?>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <h6 class="fw-bold mb-0"><?= e($addr['label'] ?: 'Address') ?></h6>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-outline-secondary"
                                    onclick="editAddress(<?= htmlspecialchars(json_encode($addr)) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" action="/api/parcels.php?action=addresses" class="d-inline"
                                  onsubmit="return confirm('Delete this address?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="sub_action" value="delete">
                                <input type="hidden" name="id" value="<?= $addr['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </div>
                    <address class="text-muted small mb-0">
                        <strong><?= e($addr['full_name']) ?></strong><br>
                        <?= e($addr['address_line1']) ?><br>
                        <?php if ($addr['address_line2']): ?><?= e($addr['address_line2']) ?><br><?php endif; ?>
                        <?= e($addr['city']) ?>, <?= e($addr['state'] ?? '') ?> <?= e($addr['postal_code'] ?? '') ?><br>
                        <?= e($addr['country']) ?><br>
                        <?php if ($addr['phone']): ?><i class="bi bi-telephone me-1"></i><?= e($addr['phone']) ?><?php endif; ?>
                    </address>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Address Modal -->
<div class="modal fade" id="addAddressModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addressModalTitle">Add New Address</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/api/parcels.php?action=addresses" id="addressForm">
                <?= csrfField() ?>
                <input type="hidden" name="sub_action" value="add" id="subAction">
                <input type="hidden" name="id" id="editAddressId">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Label *</label>
                            <input type="text" name="label" id="addrLabel" class="form-control" required placeholder="e.g., Home, Office, Warehouse">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name *</label>
                            <input type="text" name="full_name" id="addrFullName" class="form-control" required placeholder="Recipient's full name">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Address Line 1 *</label>
                            <input type="text" name="address_line1" id="addrLine1" class="form-control" required placeholder="Street address">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Address Line 2</label>
                            <input type="text" name="address_line2" id="addrLine2" class="form-control" placeholder="Apartment, suite, unit (optional)">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">City *</label>
                            <input type="text" name="city" id="addrCity" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">State / Province</label>
                            <input type="text" name="state" id="addrState" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Postal Code</label>
                            <input type="text" name="postal_code" id="addrPostal" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Country *</label>
                            <input type="text" name="country" id="addrCountry" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="tel" name="phone" id="addrPhone" class="form-control" placeholder="+1 234 567 8900">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_default" id="addrDefault">
                                <label class="form-check-label" for="addrDefault">Set as default address</label>
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

<script>
function editAddress(addr) {
    document.getElementById('addressModalTitle').textContent = 'Edit Address';
    document.getElementById('subAction').value = 'edit';
    document.getElementById('editAddressId').value = addr.id;
    document.getElementById('addrLabel').value = addr.label ?? '';
    document.getElementById('addrFullName').value = addr.full_name ?? '';
    document.getElementById('addrLine1').value = addr.address_line1 ?? '';
    document.getElementById('addrLine2').value = addr.address_line2 ?? '';
    document.getElementById('addrCity').value = addr.city ?? '';
    document.getElementById('addrState').value = addr.state ?? '';
    document.getElementById('addrPostal').value = addr.postal_code ?? '';
    document.getElementById('addrCountry').value = addr.country ?? '';
    document.getElementById('addrPhone').value = addr.phone ?? '';
    document.getElementById('addrDefault').checked = !!parseInt(addr.is_default);
    new bootstrap.Modal(document.getElementById('addAddressModal')).show();
}
</script>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
