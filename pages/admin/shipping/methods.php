<?php
/**
 * pages/admin/shipping/methods.php — Shipping Method Management per Zone (PR #14)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/shipping.php';
requireAdmin();

$zoneId = (int)($_GET['zone_id'] ?? 0);

$zone    = $zoneId ? getShippingZone($zoneId) : null;
$zones   = getShippingZones();

// All methods for selected zone (including inactive)
$methods = [];
if ($zone) {
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT * FROM shipping_methods WHERE zone_id = ? ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([$zoneId]);
        $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $methods = [];
    }
}

$typeLabels = [
    'flat_rate'    => 'Flat Rate',
    'weight_based' => 'Weight-Based',
    'price_based'  => 'Price-Based',
    'free'         => 'Free Above Threshold',
];

$pageTitle = $zone ? 'Methods — ' . htmlspecialchars($zone['name']) : 'Shipping Methods';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-0">
                <i class="bi bi-truck text-success me-2"></i>Shipping Methods
                <?php if ($zone): ?>
                — <span class="text-success"><?= htmlspecialchars($zone['name']) ?></span>
                <?php endif; ?>
            </h3>
            <?php if ($zone): ?>
            <div class="text-muted small mt-1">
                <?php
                $countries = json_decode($zone['countries_json'] ?? '[]', true) ?: [];
                echo $countries ? implode(', ', array_slice($countries, 0, 10)) . (count($countries) > 10 ? '…' : '') : 'All other countries';
                ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2">
            <a href="/pages/admin/shipping/zones.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Zones
            </a>
            <?php if ($zone): ?>
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#methodModal">
                <i class="bi bi-plus-lg me-1"></i>Add Method
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$zone): ?>
    <!-- Zone picker -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <p class="text-muted mb-3">Select a zone to manage its shipping methods:</p>
            <div class="list-group">
                <?php foreach ($zones as $z): ?>
                <a href="?zone_id=<?= (int)$z['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><?= htmlspecialchars($z['name']) ?></span>
                    <?php if ($z['is_default']): ?>
                    <span class="badge bg-success">Default</span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php else: ?>

    <div class="card border-0 shadow-sm">
        <?php if (empty($methods)): ?>
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-truck fs-1 d-block mb-2 opacity-25"></i>
            No methods for this zone yet. Add a method to enable shipping.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Base Cost</th>
                        <th>Per KG</th>
                        <th>Free Above</th>
                        <th>Est. Days</th>
                        <th>Sort</th>
                        <th>Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($methods as $m): ?>
                    <tr class="<?= !$m['is_active'] ? 'text-muted' : '' ?>">
                        <td class="fw-semibold"><?= htmlspecialchars($m['name']) ?></td>
                        <td>
                            <span class="badge bg-info text-dark"><?= htmlspecialchars($typeLabels[$m['type']] ?? $m['type']) ?></span>
                        </td>
                        <td>$<?= number_format((float)$m['base_cost'], 2) ?></td>
                        <td><?= (float)$m['per_kg_cost'] > 0 ? '$' . number_format((float)$m['per_kg_cost'], 2) . '/kg' : '—' ?></td>
                        <td><?= (float)$m['free_above_amount'] > 0 ? '$' . number_format((float)$m['free_above_amount'], 2) : '—' ?></td>
                        <td class="text-nowrap"><?= (int)$m['estimated_days_min'] ?>–<?= (int)$m['estimated_days_max'] ?> days</td>
                        <td><?= (int)$m['sort_order'] ?></td>
                        <td>
                            <?php if ($m['is_active']): ?>
                            <span class="badge bg-success">Yes</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">No</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-outline-secondary btn-edit-method"
                                        data-method='<?= htmlspecialchars(json_encode($m), ENT_QUOTES) ?>'
                                        title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger btn-delete-method"
                                        data-id="<?= (int)$m['id'] ?>"
                                        data-name="<?= htmlspecialchars($m['name']) ?>"
                                        title="Delete">
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
    <?php endif; ?>
</div>

<?php if ($zone): ?>
<!-- Method Create/Edit Modal -->
<div class="modal fade" id="methodModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form id="methodForm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="methodModalTitle">Add Shipping Method</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="method_id" id="methodId">
                    <input type="hidden" name="zone_id"   value="<?= $zoneId ?>">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($_SESSION['_csrf_token'] ?? '') ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Method Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="methodName" required placeholder="Standard Shipping">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="type" id="methodType">
                                <?php foreach ($typeLabels as $val => $label): ?>
                                <option value="<?= $val ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3" id="costFields">
                        <div class="col-md-4" id="baseCostWrap">
                            <label class="form-label fw-semibold" id="baseCostLabel">Base Cost ($)</label>
                            <input type="number" class="form-control" name="base_cost" id="methodBaseCost" value="0" min="0" step="0.01">
                        </div>
                        <div class="col-md-4" id="perKgWrap">
                            <label class="form-label fw-semibold">Per KG Cost ($)</label>
                            <input type="number" class="form-control" name="per_kg_cost" id="methodPerKg" value="0" min="0" step="0.0001">
                        </div>
                        <div class="col-md-4" id="freeAboveWrap">
                            <label class="form-label fw-semibold">Free Above ($)</label>
                            <input type="number" class="form-control" name="free_above_amount" id="methodFreeAbove" value="0" min="0" step="0.01">
                            <div class="form-text">0 = not applicable</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Min Days</label>
                            <input type="number" class="form-control" name="estimated_days_min" id="methodMinDays" value="1" min="1">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Max Days</label>
                            <input type="number" class="form-control" name="estimated_days_max" id="methodMaxDays" value="7" min="1">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Sort Order</label>
                            <input type="number" class="form-control" name="sort_order" id="methodSortOrder" value="0" min="0">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="methodIsActive" checked>
                                <label class="form-check-label" for="methodIsActive">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="methodSubmitBtn">Save Method</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Delete method modal -->
<div class="modal fade" id="deleteMethodModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Method</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Delete method "<strong id="deleteMethodName"></strong>"?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteMethodForm">
                    <input type="hidden" name="method_id" id="deleteMethodId">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($_SESSION['_csrf_token'] ?? '') ?>">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const TYPE_META = {
    flat_rate:    { baseCostLabel: 'Base Cost ($)', showPerKg: false, showFreeAbove: true },
    weight_based: { baseCostLabel: 'Base Cost ($)', showPerKg: true,  showFreeAbove: true },
    price_based:  { baseCostLabel: '% of Subtotal', showPerKg: false, showFreeAbove: true },
    free:         { baseCostLabel: 'Cost ($)',       showPerKg: false, showFreeAbove: true },
};

function updateCostFields() {
    const type = document.getElementById('methodType').value;
    const meta = TYPE_META[type] || TYPE_META.flat_rate;
    document.getElementById('baseCostLabel').textContent = meta.baseCostLabel;
    document.getElementById('perKgWrap').style.display   = meta.showPerKg    ? '' : 'none';
    document.getElementById('freeAboveWrap').style.display = meta.showFreeAbove ? '' : 'none';
    // For 'free' type, base cost should be 0 and disabled
    const baseCostInput = document.getElementById('methodBaseCost');
    if (type === 'free') { baseCostInput.value = '0'; baseCostInput.disabled = true; }
    else baseCostInput.disabled = false;
}
document.getElementById('methodType').addEventListener('change', updateCostFields);
updateCostFields();

// Edit method
document.querySelectorAll('.btn-edit-method').forEach(btn => {
    btn.addEventListener('click', () => {
        const m = JSON.parse(btn.dataset.method);
        document.getElementById('methodModalTitle').textContent = 'Edit Shipping Method';
        document.getElementById('methodId').value         = m.id;
        document.getElementById('methodName').value        = m.name;
        document.getElementById('methodType').value        = m.type;
        document.getElementById('methodBaseCost').value    = m.base_cost;
        document.getElementById('methodPerKg').value       = m.per_kg_cost;
        document.getElementById('methodFreeAbove').value   = m.free_above_amount;
        document.getElementById('methodMinDays').value     = m.estimated_days_min;
        document.getElementById('methodMaxDays').value     = m.estimated_days_max;
        document.getElementById('methodSortOrder').value   = m.sort_order;
        document.getElementById('methodIsActive').checked  = !!+m.is_active;
        document.getElementById('methodSubmitBtn').textContent = 'Update Method';
        updateCostFields();
        new bootstrap.Modal(document.getElementById('methodModal')).show();
    });
});

document.querySelector('[data-bs-target="#methodModal"]')?.addEventListener('click', () => {
    document.getElementById('methodModalTitle').textContent = 'Add Shipping Method';
    document.getElementById('methodId').value         = '';
    document.getElementById('methodName').value        = '';
    document.getElementById('methodType').value        = 'flat_rate';
    document.getElementById('methodBaseCost').value    = '0';
    document.getElementById('methodPerKg').value       = '0';
    document.getElementById('methodFreeAbove').value   = '0';
    document.getElementById('methodMinDays').value     = '1';
    document.getElementById('methodMaxDays').value     = '7';
    document.getElementById('methodSortOrder').value   = '0';
    document.getElementById('methodIsActive').checked  = true;
    document.getElementById('methodSubmitBtn').textContent = 'Save Method';
    updateCostFields();
});

document.getElementById('methodForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd       = new FormData(e.target);
    const methodId = fd.get('method_id');
    const action   = methodId ? 'update_method' : 'create_method';
    // Checkbox fix
    if (!e.target.querySelector('#methodIsActive').checked) fd.set('is_active', '0');
    const baseCostInput = document.getElementById('methodBaseCost');
    if (baseCostInput.disabled) fd.set('base_cost', '0');

    const res  = await fetch(`/api/shipping.php?action=${action}`, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) location.reload();
    else alert(data.error || 'Failed to save method.');
});

document.querySelectorAll('.btn-delete-method').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('deleteMethodId').value            = btn.dataset.id;
        document.getElementById('deleteMethodName').textContent    = btn.dataset.name;
        new bootstrap.Modal(document.getElementById('deleteMethodModal')).show();
    });
});

document.getElementById('deleteMethodForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd  = new FormData(e.target);
    const res = await fetch('/api/shipping.php?action=delete_method', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) location.reload();
    else alert(data.error || 'Failed to delete method.');
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
