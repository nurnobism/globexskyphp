<?php
/**
 * pages/admin/shipping/zones.php — Shipping Zone Management (PR #14)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/shipping.php';
requireAdmin();

$zones = getShippingZones();

// Attach method count to each zone
$db = getDB();
foreach ($zones as &$zone) {
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM shipping_methods WHERE zone_id = ? AND is_active = 1');
        $stmt->execute([(int)$zone['id']]);
        $zone['methods_count'] = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        $zone['methods_count'] = 0;
    }
}
unset($zone);

$pageTitle = 'Shipping Zones';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-globe2 text-primary me-2"></i>Shipping Zones</h3>
        <div class="d-flex gap-2">
            <a href="/pages/admin/shipping/settings.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-gear me-1"></i>Settings
            </a>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#zoneModal">
                <i class="bi bi-plus-lg me-1"></i>Add Zone
            </button>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= htmlspecialchars($_GET['success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($_GET['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <?php if (empty($zones)): ?>
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-globe2 fs-1 d-block mb-2 opacity-25"></i>
            No shipping zones configured. Add your first zone to get started.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Zone Name</th>
                        <th>Countries</th>
                        <th>Methods</th>
                        <th>Default</th>
                        <th>Status</th>
                        <th>Sort</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($zones as $z): ?>
                    <?php
                        $countries = json_decode($z['countries_json'] ?? '[]', true) ?: [];
                    ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($z['name']) ?></td>
                        <td>
                            <?php if (empty($countries)): ?>
                                <span class="badge bg-secondary">All others</span>
                            <?php else: ?>
                                <?php foreach (array_slice($countries, 0, 5) as $cc): ?>
                                <span class="badge bg-light text-dark border me-1"><?= htmlspecialchars($cc) ?></span>
                                <?php endforeach; ?>
                                <?php if (count($countries) > 5): ?>
                                <span class="text-muted small">+<?= count($countries) - 5 ?> more</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="/pages/admin/shipping/methods.php?zone_id=<?= (int)$z['id'] ?>" class="text-decoration-none">
                                <span class="badge bg-primary"><?= (int)$z['methods_count'] ?> method<?= $z['methods_count'] !== 1 ? 's' : '' ?></span>
                            </a>
                        </td>
                        <td>
                            <?php if ($z['is_default']): ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Default</span>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($z['is_active']): ?>
                            <span class="badge bg-success">Active</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= (int)$z['sort_order'] ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/pages/admin/shipping/methods.php?zone_id=<?= (int)$z['id'] ?>"
                                   class="btn btn-sm btn-outline-primary" title="Manage Methods">
                                    <i class="bi bi-list-ul"></i>
                                </a>
                                <button class="btn btn-sm btn-outline-secondary btn-edit-zone"
                                        data-zone='<?= htmlspecialchars(json_encode($z), ENT_QUOTES) ?>'
                                        title="Edit Zone">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger btn-delete-zone"
                                        data-id="<?= (int)$z['id'] ?>"
                                        data-name="<?= htmlspecialchars($z['name']) ?>"
                                        title="Delete Zone">
                                    <i class="bi bi-trash"></i>
                                </button>
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

<!-- Zone Create/Edit Modal -->
<div class="modal fade" id="zoneModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form id="zoneForm" method="POST">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="zoneModalTitle">Add Shipping Zone</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="zone_id" id="zoneId">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($_SESSION['_csrf_token'] ?? '') ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Zone Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="zoneName" required placeholder="e.g. North America">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Country Codes (comma-separated)</label>
                        <input type="text" class="form-control" name="countries_raw" id="zoneCountries"
                               placeholder="US, CA, MX">
                        <div class="form-text">ISO 3166-1 alpha-2 codes. Leave empty for "Rest of World" catch-all.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">State/Province Codes (comma-separated)</label>
                        <input type="text" class="form-control" name="states_raw" id="zoneStates"
                               placeholder="US-CA, US-NY, CA-ON">
                        <div class="form-text">Format: CC-ST (e.g. US-CA). Overrides country match for these regions.</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Sort Order</label>
                            <input type="number" class="form-control" name="sort_order" id="zoneSortOrder" value="0" min="0">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="is_default" id="zoneIsDefault">
                                <label class="form-check-label" for="zoneIsDefault">Default Zone</label>
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="zoneIsActive" checked>
                                <label class="form-check-label" for="zoneIsActive">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="zoneSubmitBtn">Save Zone</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Delete confirmation modal -->
<div class="modal fade" id="deleteZoneModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Zone</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Delete zone "<strong id="deleteZoneName"></strong>"? All methods in this zone will also be removed.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" id="deleteZoneForm">
                    <input type="hidden" name="zone_id" id="deleteZoneId">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($_SESSION['_csrf_token'] ?? '') ?>">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Edit zone
document.querySelectorAll('.btn-edit-zone').forEach(btn => {
    btn.addEventListener('click', () => {
        const z = JSON.parse(btn.dataset.zone);
        document.getElementById('zoneModalTitle').textContent = 'Edit Shipping Zone';
        document.getElementById('zoneId').value         = z.id;
        document.getElementById('zoneName').value        = z.name;
        document.getElementById('zoneCountries').value   = (JSON.parse(z.countries_json || '[]') || []).join(', ');
        document.getElementById('zoneStates').value      = (JSON.parse(z.states_json    || '[]') || []).join(', ');
        document.getElementById('zoneSortOrder').value   = z.sort_order;
        document.getElementById('zoneIsDefault').checked = !!+z.is_default;
        document.getElementById('zoneIsActive').checked  = !!+z.is_active;
        document.getElementById('zoneSubmitBtn').textContent = 'Update Zone';
        new bootstrap.Modal(document.getElementById('zoneModal')).show();
    });
});

// Create form clears data
document.querySelector('[data-bs-target="#zoneModal"]')?.addEventListener('click', () => {
    document.getElementById('zoneModalTitle').textContent = 'Add Shipping Zone';
    document.getElementById('zoneId').value         = '';
    document.getElementById('zoneName').value        = '';
    document.getElementById('zoneCountries').value   = '';
    document.getElementById('zoneStates').value      = '';
    document.getElementById('zoneSortOrder').value   = '0';
    document.getElementById('zoneIsDefault').checked = false;
    document.getElementById('zoneIsActive').checked  = true;
    document.getElementById('zoneSubmitBtn').textContent = 'Save Zone';
});

// Zone form submit
document.getElementById('zoneForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd        = new FormData(e.target);
    const zoneId    = fd.get('zone_id');
    const action    = zoneId ? 'update_zone' : 'create_zone';

    // Convert comma-separated country/state to JSON arrays
    const countries = (fd.get('countries_raw') || '').split(',').map(s => s.trim().toUpperCase()).filter(Boolean);
    const states    = (fd.get('states_raw')    || '').split(',').map(s => s.trim().toUpperCase()).filter(Boolean);
    fd.set('countries', JSON.stringify(countries));
    fd.set('states',    JSON.stringify(states));

    const res  = await fetch(`/api/shipping.php?action=${action}`, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
        location.reload();
    } else {
        alert(data.error || 'Failed to save zone.');
    }
});

// Delete zone
document.querySelectorAll('.btn-delete-zone').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('deleteZoneId').value  = btn.dataset.id;
        document.getElementById('deleteZoneName').textContent = btn.dataset.name;
        new bootstrap.Modal(document.getElementById('deleteZoneModal')).show();
    });
});

document.getElementById('deleteZoneForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd  = new FormData(e.target);
    const res = await fetch('/api/shipping.php?action=delete_zone', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) location.reload();
    else alert(data.error || 'Failed to delete zone.');
});
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
