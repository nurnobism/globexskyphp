<?php
/**
 * pages/supplier/shipping/index.php — Supplier Shipping Templates (PR #14)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/shipping.php';
requireLogin();

$userId     = (int)$_SESSION['user_id'];
$supplierId = (int)($_SESSION['supplier_id'] ?? $userId);

$templates = getSupplierShippingTemplates($supplierId);
$limit     = getShippingTemplatePlanLimit($supplierId);
$used      = count($templates);
$zones     = getShippingZones();

$pageTitle = 'My Shipping Templates';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-truck text-success me-2"></i>Shipping Templates</h3>
        <?php if ($used < $limit): ?>
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#templateModal">
            <i class="bi bi-plus-lg me-1"></i>Create Template
        </button>
        <?php else: ?>
        <span class="btn btn-outline-secondary btn-sm disabled" title="Plan limit reached">
            <i class="bi bi-lock me-1"></i>Limit Reached
        </span>
        <?php endif; ?>
    </div>

    <!-- Plan limit indicator -->
    <div class="alert alert-light border mb-4 d-flex align-items-center gap-3">
        <i class="bi bi-bar-chart-steps fs-4 text-primary"></i>
        <div>
            <div class="fw-semibold"><?= $used ?>/<?= $limit >= 999 ? '∞' : $limit ?> templates used</div>
            <div class="text-muted small">
                <?php
                $planName = 'Free';
                if (function_exists('getSupplierActivePlan')) {
                    $plan = getSupplierActivePlan($supplierId);
                    $planName = ucfirst($plan['plan_name'] ?? 'Free');
                }
                echo htmlspecialchars($planName) . ' plan — ';
                if ($limit >= 999) echo 'Unlimited templates';
                elseif ($limit === 5) echo 'Up to 5 templates';
                else echo '1 template';
                ?>
            </div>
        </div>
        <?php if ($limit < 999): ?>
        <a href="/pages/supplier/plan-upgrade.php" class="btn btn-sm btn-outline-primary ms-auto">Upgrade Plan</a>
        <?php endif; ?>
    </div>

    <?php if (empty($templates)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-truck fs-1 d-block mb-2 opacity-25"></i>
            <p>No shipping templates yet.</p>
            <p class="small">Create a template to define your shipping rates for different zones.</p>
        </div>
    </div>
    <?php else: ?>
    <div class="row g-3">
        <?php foreach ($templates as $tpl): ?>
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="fw-bold mb-0"><?= htmlspecialchars($tpl['name']) ?></h6>
                        <?php if ($tpl['is_default']): ?>
                        <span class="badge bg-primary">Default</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted small mb-3">
                        <span class="me-3"><i class="bi bi-clock me-1"></i>Ships in <?= (int)$tpl['handling_time_days'] ?> business day<?= $tpl['handling_time_days'] != 1 ? 's' : '' ?></span>
                        <span class="me-3"><i class="bi bi-globe2 me-1"></i><?= (int)$tpl['zones_count'] ?> zone<?= $tpl['zones_count'] != 1 ? 's' : '' ?></span>
                        <span><i class="bi bi-box-seam me-1"></i><?= (int)$tpl['products_count'] ?> product<?= $tpl['products_count'] != 1 ? 's' : '' ?></span>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-secondary btn-edit-template flex-grow-1"
                                data-id="<?= (int)$tpl['id'] ?>"
                                data-name="<?= htmlspecialchars($tpl['name']) ?>"
                                data-handling="<?= (int)$tpl['handling_time_days'] ?>"
                                data-default="<?= (int)$tpl['is_default'] ?>">
                            <i class="bi bi-pencil me-1"></i>Edit
                        </button>
                        <button class="btn btn-sm btn-outline-danger btn-delete-template"
                                data-id="<?= (int)$tpl['id'] ?>"
                                data-name="<?= htmlspecialchars($tpl['name']) ?>">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Template Create/Edit Modal -->
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <form id="templateForm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="templateModalTitle">Create Shipping Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="template_id" id="templateId">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($_SESSION['_csrf_token'] ?? '') ?>">

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Template Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="templateName" required placeholder="e.g. Standard Shipping">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Handling Time (days)</label>
                            <input type="number" class="form-control" name="handling_time_days" id="templateHandling" value="1" min="0" max="30">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="is_default" id="templateIsDefault">
                                <label class="form-check-label fw-semibold" for="templateIsDefault">Set as Default</label>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-semibold mb-3">Zone Rates</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle" id="zoneRatesTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Zone</th>
                                    <th>Method Name</th>
                                    <th>Cost ($)</th>
                                    <th>Free Above ($)</th>
                                    <th>Active</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($zones as $z): ?>
                                <tr>
                                    <td class="fw-semibold small"><?= htmlspecialchars($z['name']) ?></td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm zone-method-name"
                                               data-zone-id="<?= (int)$z['id'] ?>"
                                               value="Standard" placeholder="Standard">
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm zone-cost"
                                               data-zone-id="<?= (int)$z['id'] ?>"
                                               value="0" min="0" step="0.01">
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm zone-free-above"
                                               data-zone-id="<?= (int)$z['id'] ?>"
                                               value="0" min="0" step="0.01" placeholder="0 = N/A">
                                    </td>
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check-input zone-active"
                                               data-zone-id="<?= (int)$z['id'] ?>" checked>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="templateSubmitBtn">Save Template</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Delete template modal -->
<div class="modal fade" id="deleteTemplateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Delete template "<strong id="deleteTemplateName"></strong>"? Products using this template will lose their shipping configuration.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteTemplateForm">
                    <input type="hidden" name="template_id" id="deleteTemplateId">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($_SESSION['_csrf_token'] ?? '') ?>">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function collectZoneRates() {
    const zones = [];
    document.querySelectorAll('#zoneRatesTable tbody tr').forEach(row => {
        const zoneId = parseInt(row.querySelector('.zone-method-name')?.dataset.zoneId || 0);
        if (!zoneId) return;
        zones.push({
            zone_id:     zoneId,
            method_name: row.querySelector('.zone-method-name')?.value || 'Standard',
            cost:        parseFloat(row.querySelector('.zone-cost')?.value) || 0,
            free_above:  parseFloat(row.querySelector('.zone-free-above')?.value) || 0,
            is_active:   row.querySelector('.zone-active')?.checked ? 1 : 0,
        });
    });
    return zones;
}

document.getElementById('templateForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd         = new FormData(e.target);
    const templateId = fd.get('template_id');
    const action     = templateId ? 'update_template' : 'create_template';
    if (!e.target.querySelector('#templateIsDefault').checked) fd.set('is_default', '0');
    fd.set('zones', JSON.stringify(collectZoneRates()));

    const res  = await fetch(`/api/shipping.php?action=${action}`, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) location.reload();
    else alert(data.error || 'Failed to save template.');
});

// Reset on create
document.querySelector('[data-bs-target="#templateModal"]')?.addEventListener('click', () => {
    document.getElementById('templateModalTitle').textContent = 'Create Shipping Template';
    document.getElementById('templateId').value       = '';
    document.getElementById('templateName').value      = '';
    document.getElementById('templateHandling').value  = '1';
    document.getElementById('templateIsDefault').checked = false;
    document.getElementById('templateSubmitBtn').textContent = 'Save Template';
});

// Edit
document.querySelectorAll('.btn-edit-template').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('templateModalTitle').textContent = 'Edit Shipping Template';
        document.getElementById('templateId').value       = btn.dataset.id;
        document.getElementById('templateName').value      = btn.dataset.name;
        document.getElementById('templateHandling').value  = btn.dataset.handling;
        document.getElementById('templateIsDefault').checked = !!+btn.dataset.default;
        document.getElementById('templateSubmitBtn').textContent = 'Update Template';
        new bootstrap.Modal(document.getElementById('templateModal')).show();
    });
});

// Delete
document.querySelectorAll('.btn-delete-template').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('deleteTemplateId').value         = btn.dataset.id;
        document.getElementById('deleteTemplateName').textContent  = btn.dataset.name;
        new bootstrap.Modal(document.getElementById('deleteTemplateModal')).show();
    });
});

document.getElementById('deleteTemplateForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd  = new FormData(e.target);
    const res = await fetch('/api/shipping.php?action=delete_template', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) location.reload();
    else alert(data.error || 'Failed to delete template.');
});
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
