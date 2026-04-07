<?php
/**
 * pages/supplier/orders/detail.php — Supplier: Order Detail (PR #7)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/orders.php';
requireRole(['supplier', 'admin', 'super_admin']);

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

// Resolve supplier
$suppStmt = $db->prepare('SELECT id, business_name, commission_rate FROM suppliers WHERE user_id = ?');
$suppStmt->execute([$userId]);
$supplier = $suppStmt->fetch();

if (!$supplier && !isAdmin()) {
    flashMessage('warning', 'Supplier account not found.');
    redirect('/pages/supplier/dashboard.php');
}

$supplierId = $supplier ? (int)$supplier['id'] : 0;
$commRate   = (float)($supplier['commission_rate'] ?? 0);

$orderId = (int)get('order_id', 0);
if (!$orderId) {
    redirect('/pages/supplier/orders/index.php');
}

// Verify access
if (isAdmin()) {
    $order = getOrder($db, $orderId);
} else {
    $order = getOrder($db, $orderId, $supplierId, 'supplier');
}

if (!$order) {
    flashMessage('danger', 'Order not found or access denied.');
    redirect('/pages/supplier/orders/index.php');
}

$statusHistory  = getStatusHistory($db, $orderId);
$notes          = getOrderNotes($db, $orderId, true);
$tracking       = $order['tracking'];
$validNextSteps = getValidStatusTransitions($order['status'], isAdmin() ? 'admin' : 'supplier');

// Financial calc: sum only supplier's items
$supplierTotal = 0.0;
foreach ($order['items'] as $item) {
    if (isAdmin() || (int)($item['supplier_id'] ?? 0) === $supplierId) {
        $supplierTotal += (float)$item['total_price'];
    }
}
$commission  = $supplierTotal * $commRate / 100;
$netEarnings = $supplierTotal - $commission;

$pageTitle = 'Order ' . $order['order_number'];
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <a href="/pages/supplier/orders/index.php" class="btn btn-outline-secondary btn-sm me-2">
                <i class="bi bi-arrow-left me-1"></i>Orders
            </a>
            <span class="fw-bold fs-5"><?= e($order['order_number']) ?></span>
            <span class="badge bg-<?= getOrderStatusBadgeClass($order['status']) ?> ms-2">
                <?= e(ucfirst($order['status'])) ?>
            </span>
        </div>
        <div class="text-muted small">Placed <?= formatDateTime($order['placed_at']) ?></div>
    </div>

    <div class="row g-4">
        <!-- Left -->
        <div class="col-lg-8">
            <!-- Order Items -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-box me-2"></i>Order Items</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Line Total</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($order['items'] as $item): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($item['product_name']) ?></div>
                                <?php if (!empty($item['attributes']) && is_array($item['attributes'])): ?>
                                <small class="text-muted">
                                    <?= e(implode(', ', array_map(fn($k, $v) => "$k: $v", array_keys($item['attributes']), $item['attributes']))) ?>
                                </small>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?= e($item['product_sku'] ?? '') ?></td>
                            <td class="text-center"><?= (int)$item['quantity'] ?></td>
                            <td class="text-end"><?= formatMoney((float)$item['unit_price']) ?></td>
                            <td class="text-end fw-semibold"><?= formatMoney((float)$item['total_price']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Shipping Form (if processing) -->
            <?php if ($order['status'] === 'processing' || (isAdmin() && in_array($order['status'], ['confirmed', 'processing'], true))): ?>
            <?php if (!$tracking): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-truck me-2"></i>Add Tracking Info</div>
                <div class="card-body">
                    <form id="trackingForm" method="POST" action="/api/orders.php?action=add_tracking">
                        <?= csrfField() ?>
                        <input type="hidden" name="order_id" value="<?= $orderId ?>">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Carrier</label>
                                <input type="text" name="carrier" class="form-control" placeholder="FedEx, UPS, DHL…">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tracking # <span class="text-danger">*</span></label>
                                <input type="text" name="tracking_number" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tracking URL</label>
                                <input type="url" name="tracking_url" class="form-control" placeholder="https://…">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3">
                            <i class="bi bi-truck me-1"></i>Mark as Shipped
                        </button>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <div class="card border-0 shadow-sm mb-4 border-start border-4 border-primary">
                <div class="card-body">
                    <h6 class="fw-bold"><i class="bi bi-truck me-2 text-primary"></i>Tracking Submitted</h6>
                    <div><strong>Carrier:</strong> <?= e($tracking['carrier'] ?? '—') ?></div>
                    <div><strong>Tracking #:</strong> <?= e($tracking['tracking_number']) ?></div>
                    <?php if (!empty($tracking['tracking_url'])): ?>
                    <a href="<?= e($tracking['tracking_url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary mt-2">Track</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php elseif ($tracking): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-truck me-2"></i>Tracking</div>
                <div class="card-body small">
                    <div><strong>Carrier:</strong> <?= e($tracking['carrier'] ?? '—') ?></div>
                    <div><strong>Tracking #:</strong> <?= e($tracking['tracking_number']) ?></div>
                    <?php if (!empty($tracking['tracking_url'])): ?>
                    <a href="<?= e($tracking['tracking_url']) ?>" target="_blank" rel="noopener">Track Package</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Notes -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-chat-left-text me-2"></i>Notes</div>
                <div class="card-body">
                    <?php foreach ($notes as $note): ?>
                    <div class="mb-3 p-2 rounded <?= $note['is_internal'] ? 'bg-warning bg-opacity-10 border border-warning' : 'bg-light' ?>">
                        <div class="d-flex justify-content-between mb-1">
                            <strong class="small">
                                <?= e(trim(($note['first_name'] ?? '') . ' ' . ($note['last_name'] ?? '')) ?: 'System') ?>
                                <?php if ($note['is_internal']): ?>
                                <span class="badge bg-warning text-dark ms-1">Internal</span>
                                <?php endif; ?>
                            </strong>
                            <span class="text-muted small"><?= formatDateTime($note['created_at']) ?></span>
                        </div>
                        <p class="mb-0 small"><?= nl2br(e($note['note'])) ?></p>
                    </div>
                    <?php endforeach; ?>

                    <form id="addNoteForm" class="mt-3">
                        <?= csrfField() ?>
                        <input type="hidden" name="order_id" value="<?= $orderId ?>">
                        <div class="mb-2">
                            <textarea name="note" class="form-control form-control-sm" rows="2" placeholder="Add a note…"></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <label class="form-check-label small me-2">
                                <input type="checkbox" name="is_internal" value="1" class="form-check-input me-1">Internal only
                            </label>
                            <button type="submit" class="btn btn-sm btn-outline-primary">Add Note</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Status History -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-clock-history me-2"></i>Status History</div>
                <div class="card-body p-0">
                    <?php if (empty($statusHistory)): ?>
                    <p class="p-3 text-muted mb-0">No history yet.</p>
                    <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach (array_reverse($statusHistory) as $h): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span>
                                    <?php if ($h['old_status']): ?>
                                    <span class="badge bg-secondary"><?= e(ucfirst($h['old_status'])) ?></span>
                                    <i class="bi bi-arrow-right mx-1 text-muted"></i>
                                    <?php endif; ?>
                                    <span class="badge bg-<?= getOrderStatusBadgeClass($h['new_status']) ?>"><?= e(ucfirst($h['new_status'])) ?></span>
                                </span>
                                <span class="text-muted small"><?= formatDateTime($h['created_at']) ?></span>
                            </div>
                            <?php if (!empty($h['note'])): ?>
                            <small class="text-muted"><?= e($h['note']) ?></small>
                            <?php endif; ?>
                            <?php if (!empty($h['first_name'])): ?>
                            <div class="text-muted small">by <?= e(trim($h['first_name'] . ' ' . $h['last_name'])) ?></div>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right -->
        <div class="col-lg-4">
            <!-- Buyer Info -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-person me-2"></i>Buyer</div>
                <div class="card-body small">
                    <div><?= e(trim($order['first_name'] . ' ' . $order['last_name'])) ?></div>
                    <div class="text-muted"><?= e(maskEmail($order['buyer_email'])) ?></div>
                    <?php if (!empty($order['buyer_phone'])): ?>
                    <div class="text-muted"><?= e($order['buyer_phone']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Shipping Address -->
            <?php if (!empty($order['shipping_address']) && is_array($order['shipping_address'])): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-geo-alt me-2"></i>Ship To</div>
                <div class="card-body small">
                    <?php $addr = $order['shipping_address']; ?>
                    <div><?= e($addr['full_name'] ?? '') ?></div>
                    <div><?= e($addr['address_line1'] ?? '') ?></div>
                    <?php if (!empty($addr['address_line2'])): ?><div><?= e($addr['address_line2']) ?></div><?php endif; ?>
                    <div><?= e(trim(($addr['city'] ?? '') . ', ' . ($addr['state'] ?? '') . ' ' . ($addr['postal_code'] ?? ''))) ?></div>
                    <div><?= e($addr['country'] ?? '') ?></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Financial Breakdown -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-cash-stack me-2"></i>Financials</div>
                <div class="card-body small">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Your Items Subtotal</span>
                        <span><?= formatMoney($supplierTotal) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1 text-danger">
                        <span>Commission (<?= number_format($commRate, 1) ?>%)</span>
                        <span>-<?= formatMoney($commission) ?></span>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between fw-bold">
                        <span>Net Earnings</span>
                        <span class="text-success"><?= formatMoney($netEarnings) ?></span>
                    </div>
                    <div class="mt-2">
                        <span class="badge bg-<?= $order['payment_status'] === 'paid' ? 'success' : ($order['payment_status'] === 'failed' ? 'danger' : 'warning') ?>">
                            Payment: <?= ucfirst($order['payment_status']) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <?php if (!empty($validNextSteps)): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Update Status</div>
                <div class="card-body">
                    <?php if (in_array('processing', $validNextSteps, true)): ?>
                    <button class="btn btn-success w-100 mb-2" onclick="updateStatus('processing')">
                        <i class="bi bi-check-circle me-1"></i>Accept Order
                    </button>
                    <?php endif; ?>
                    <?php if (in_array('shipped', $validNextSteps, true)): ?>
                    <button class="btn btn-primary w-100 mb-2" onclick="document.getElementById('trackingForm')?.scrollIntoView({behavior:'smooth'}) || updateStatus('shipped')">
                        <i class="bi bi-truck me-1"></i>Mark as Shipped
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const csrfToken = document.querySelector('[name=_csrf_token]').value;

function updateStatus(status) {
    if (!confirm('Update order status to ' + status + '?')) return;
    const fd = new FormData();
    fd.append('order_id', <?= $orderId ?>);
    fd.append('status', status);
    fd.append('_csrf_token', csrfToken);
    fetch('/api/orders.php?action=update_status', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.success) location.reload(); else alert(d.message); });
}

const trackingForm = document.getElementById('trackingForm');
if (trackingForm) {
    trackingForm.addEventListener('submit', function(e) {
        e.preventDefault();
        fetch(this.action, { method: 'POST', body: new FormData(this) })
            .then(r => r.json())
            .then(d => { if (d.success) location.reload(); else alert(d.message); });
    });
}

document.getElementById('addNoteForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    if (!fd.has('is_internal')) fd.append('is_internal', '0');
    fetch('/api/orders.php?action=add_note', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.success) location.reload(); else alert(d.message); });
});
</script>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
