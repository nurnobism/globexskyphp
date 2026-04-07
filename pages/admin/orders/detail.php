<?php
/**
 * pages/admin/orders/detail.php — Admin: Order Detail (PR #7)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/orders.php';
requireAdmin();

$db      = getDB();
$userId  = (int)$_SESSION['user_id'];
$orderId = (int)get('order_id', 0);

if (!$orderId) {
    redirect('/pages/admin/orders/index.php');
}

$order = getOrder($db, $orderId);
if (!$order) {
    flashMessage('danger', 'Order not found.');
    redirect('/pages/admin/orders/index.php');
}

$statusHistory = getStatusHistory($db, $orderId);
$notes         = getOrderNotes($db, $orderId, true);
$tracking      = $order['tracking'];

// Supplier info via first item
$suppInfo = null;
if (!empty($order['items'])) {
    $firstSupplierId = (int)($order['items'][0]['supplier_id'] ?? 0);
    if ($firstSupplierId) {
        $sStmt = $db->prepare(
            'SELECT s.id, s.business_name, s.commission_rate, u.email AS supp_email
             FROM suppliers s JOIN users u ON u.id = s.user_id
             WHERE s.id = ?'
        );
        $sStmt->execute([$firstSupplierId]);
        $suppInfo = $sStmt->fetch() ?: null;
    }
}

$commRate   = (float)($suppInfo['commission_rate'] ?? 0);
$orderTotal = (float)$order['total'];
$commission = $orderTotal * $commRate / 100;
$netPayout  = $orderTotal - $commission;

$allStatuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];

$pageTitle = 'Order ' . $order['order_number'] . ' — Admin';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <a href="/pages/admin/orders/index.php" class="btn btn-outline-secondary btn-sm me-2">
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
                                <th class="text-end">Total</th>
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

            <!-- Tracking -->
            <?php if ($tracking): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-truck me-2"></i>Tracking</div>
                <div class="card-body small">
                    <div><strong>Carrier:</strong> <?= e($tracking['carrier'] ?? '—') ?></div>
                    <div><strong>Tracking #:</strong> <?= e($tracking['tracking_number']) ?></div>
                    <?php if (!empty($tracking['tracking_url'])): ?>
                    <a href="<?= e($tracking['tracking_url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary mt-2">Track Package</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Notes (all including internal) -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-chat-left-text me-2"></i>Notes</div>
                <div class="card-body">
                    <?php if (empty($notes)): ?>
                    <p class="text-muted mb-2">No notes yet.</p>
                    <?php else: ?>
                    <?php foreach ($notes as $note): ?>
                    <div class="mb-3 p-2 rounded <?= $note['is_internal'] ? 'bg-warning bg-opacity-10 border border-warning' : 'bg-light' ?>">
                        <div class="d-flex justify-content-between mb-1">
                            <strong class="small">
                                <?= e(trim(($note['first_name'] ?? '') . ' ' . ($note['last_name'] ?? '')) ?: 'System') ?>
                                <?php if ($note['is_internal']): ?>
                                <span class="badge bg-warning text-dark ms-1">Internal</span>
                                <?php endif; ?>
                                <?php if (!empty($note['user_role'])): ?>
                                <span class="badge bg-secondary ms-1"><?= e($note['user_role']) ?></span>
                                <?php endif; ?>
                            </strong>
                            <span class="text-muted small"><?= formatDateTime($note['created_at']) ?></span>
                        </div>
                        <p class="mb-0 small"><?= nl2br(e($note['note'])) ?></p>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>

                    <form id="addNoteForm" class="mt-3">
                        <?= csrfField() ?>
                        <input type="hidden" name="order_id" value="<?= $orderId ?>">
                        <div class="mb-2">
                            <textarea name="note" class="form-control form-control-sm" rows="2" placeholder="Add a note…"></textarea>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <label class="form-check-label small">
                                <input type="checkbox" name="is_internal" value="1" class="form-check-input me-1" checked>Internal only
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
                        <li class="list-group-item small">
                            <div class="d-flex justify-content-between">
                                <span>
                                    <?php if ($h['old_status']): ?>
                                    <span class="badge bg-secondary"><?= e(ucfirst($h['old_status'])) ?></span>
                                    <i class="bi bi-arrow-right mx-1 text-muted"></i>
                                    <?php endif; ?>
                                    <span class="badge bg-<?= getOrderStatusBadgeClass($h['new_status']) ?>"><?= e(ucfirst($h['new_status'])) ?></span>
                                </span>
                                <span class="text-muted"><?= formatDateTime($h['created_at']) ?></span>
                            </div>
                            <?php if (!empty($h['note'])): ?>
                            <div class="text-muted mt-1"><?= e($h['note']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($h['first_name'])): ?>
                            <div class="text-muted">by <?= e(trim($h['first_name'] . ' ' . $h['last_name'])) ?></div>
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
            <!-- Buyer -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-person me-2"></i>Buyer</div>
                <div class="card-body small">
                    <div class="fw-semibold"><?= e(trim($order['first_name'] . ' ' . $order['last_name'])) ?></div>
                    <div><?= e($order['buyer_email']) ?></div>
                    <?php if (!empty($order['buyer_phone'])): ?>
                    <div><?= e($order['buyer_phone']) ?></div>
                    <?php endif; ?>
                    <a href="/pages/admin/users.php?search=<?= urlencode($order['buyer_email']) ?>"
                       class="btn btn-sm btn-outline-secondary mt-2">View Profile</a>
                </div>
            </div>

            <!-- Supplier -->
            <?php if ($suppInfo): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-shop me-2"></i>Supplier</div>
                <div class="card-body small">
                    <div class="fw-semibold"><?= e($suppInfo['business_name']) ?></div>
                    <div><?= e($suppInfo['supp_email']) ?></div>
                    <div class="text-muted">Commission: <?= number_format($commRate, 1) ?>%</div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Shipping Address -->
            <?php if (!empty($order['shipping_address']) && is_array($order['shipping_address'])): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-geo-alt me-2"></i>Shipping Address</div>
                <div class="card-body small">
                    <?php $addr = $order['shipping_address']; ?>
                    <div><?= e($addr['full_name'] ?? '') ?></div>
                    <?php if (!empty($addr['phone'])): ?><div><?= e($addr['phone']) ?></div><?php endif; ?>
                    <div><?= e($addr['address_line1'] ?? '') ?></div>
                    <?php if (!empty($addr['address_line2'])): ?><div><?= e($addr['address_line2']) ?></div><?php endif; ?>
                    <div><?= e(trim(($addr['city'] ?? '') . ', ' . ($addr['state'] ?? '') . ' ' . ($addr['postal_code'] ?? ''))) ?></div>
                    <div><?= e($addr['country'] ?? '') ?></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Financials -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-cash-stack me-2"></i>Financials</div>
                <div class="card-body small">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Subtotal</span>
                        <span><?= formatMoney((float)$order['subtotal']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Shipping</span>
                        <span><?= formatMoney((float)$order['shipping_fee']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Tax</span>
                        <span><?= formatMoney((float)$order['tax']) ?></span>
                    </div>
                    <?php if ((float)$order['discount'] > 0): ?>
                    <div class="d-flex justify-content-between mb-1 text-success">
                        <span>Discount</span>
                        <span>-<?= formatMoney((float)$order['discount']) ?></span>
                    </div>
                    <?php endif; ?>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between fw-bold">
                        <span>Order Total</span>
                        <span><?= formatMoney($orderTotal) ?></span>
                    </div>
                    <?php if ($commRate > 0): ?>
                    <div class="d-flex justify-content-between text-danger mt-1">
                        <span>Commission (<?= number_format($commRate, 1) ?>%)</span>
                        <span>-<?= formatMoney($commission) ?></span>
                    </div>
                    <div class="d-flex justify-content-between text-success mt-1 fw-semibold">
                        <span>Supplier Payout</span>
                        <span><?= formatMoney($netPayout) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="mt-2">
                        <span class="badge bg-<?= $order['payment_status'] === 'paid' ? 'success' : ($order['payment_status'] === 'failed' ? 'danger' : 'warning') ?>">
                            Payment: <?= ucfirst($order['payment_status']) ?>
                        </span>
                        <?php if (!empty($order['payment_method'])): ?>
                        <small class="text-muted ms-2"><?= e(ucfirst(str_replace('_', ' ', $order['payment_method']))) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Status Override -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-pencil-square me-2"></i>Status Override</div>
                <div class="card-body">
                    <form id="statusForm" class="d-flex gap-2">
                        <?= csrfField() ?>
                        <input type="hidden" name="order_id" value="<?= $orderId ?>">
                        <select name="status" class="form-select form-select-sm">
                            <?php foreach ($allStatuses as $s): ?>
                            <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-warning btn-sm">Update</button>
                    </form>
                    <div class="mt-2">
                        <input type="text" id="statusNote" class="form-control form-control-sm" placeholder="Note (optional)">
                    </div>
                </div>
            </div>

            <!-- Refund -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold text-danger"><i class="bi bi-arrow-counterclockwise me-2"></i>Refund</div>
                <div class="card-body">
                    <button class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#refundModal">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Issue Refund
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Refund Modal (stub) -->
<div class="modal fade" id="refundModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-arrow-counterclockwise me-2"></i>Issue Refund</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Refund processing is handled via the payment gateway. Please process the refund in your Stripe or payment provider dashboard, then update the order status to <strong>refunded</strong>.
                </div>
                <p>Order: <strong><?= e($order['order_number']) ?></strong></p>
                <p>Amount: <strong><?= formatMoney($orderTotal) ?></strong></p>
                <p>Payment: <strong><?= e(ucfirst(str_replace('_', ' ', $order['payment_method'] ?? ''))) ?></strong></p>
                <?php if (!empty($order['stripe_payment_intent_id'])): ?>
                <p class="mb-0">Stripe Intent: <code><?= e($order['stripe_payment_intent_id']) ?></code></p>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger"
                        onclick="quickStatus('refunded')"
                        data-bs-dismiss="modal">
                    Mark as Refunded
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const csrfToken = document.querySelector('[name=_csrf_token]').value;

function quickStatus(status) {
    const note = document.getElementById('statusNote')?.value || '';
    const fd = new FormData();
    fd.append('order_id', <?= $orderId ?>);
    fd.append('status', status);
    fd.append('note', note);
    fd.append('_csrf_token', csrfToken);
    fetch('/api/orders.php?action=update_status', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.success) location.reload(); else alert(d.message); });
}

document.getElementById('statusForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('note', document.getElementById('statusNote').value);
    fetch('/api/orders.php?action=update_status', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.success) location.reload(); else alert(d.message); });
});

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
