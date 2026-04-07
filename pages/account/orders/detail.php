<?php
/**
 * pages/account/orders/detail.php — Buyer: Order Detail (PR #7)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/orders.php';
requireLogin();

$db      = getDB();
$userId  = (int)$_SESSION['user_id'];
$orderId = (int)get('order_id', 0);

if (!$orderId) {
    redirect('/pages/account/orders/index.php');
}

$order = getOrder($db, $orderId, $userId, 'buyer');
if (!$order) {
    flashMessage('danger', 'Order not found.');
    redirect('/pages/account/orders/index.php');
}

$statusHistory = getStatusHistory($db, $orderId);
$notes         = getOrderNotes($db, $orderId, false);
$tracking      = $order['tracking'];

// Stepper config
$steps = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
$currentStep = array_search($order['status'], $steps);
if ($currentStep === false) $currentStep = -1;

$pageTitle = 'Order ' . $order['order_number'];
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container py-5">
    <!-- Back -->
    <a href="/pages/account/orders/index.php" class="btn btn-outline-secondary btn-sm mb-3">
        <i class="bi bi-arrow-left me-1"></i>My Orders
    </a>

    <!-- Order Header -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <h4 class="fw-bold mb-1"><?= e($order['order_number']) ?></h4>
                    <div class="text-muted small">Placed on <?= formatDateTime($order['placed_at']) ?></div>
                </div>
                <span class="badge bg-<?= getOrderStatusBadgeClass($order['status']) ?> fs-6">
                    <?= e(ucfirst($order['status'])) ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Status Stepper -->
    <?php if (!in_array($order['status'], ['cancelled', 'refunded'], true)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h6 class="fw-bold mb-3">Order Progress</h6>
            <div class="d-flex justify-content-between align-items-center position-relative">
                <div class="position-absolute top-50 start-0 end-0 translate-middle-y"
                     style="height:3px;background:#dee2e6;z-index:0"></div>
                <?php foreach ($steps as $i => $step): ?>
                <?php
                    $done    = $i <= $currentStep;
                    $current = $i === $currentStep;
                    $iconMap = ['pending' => 'clock', 'confirmed' => 'check-circle', 'processing' => 'gear', 'shipped' => 'truck', 'delivered' => 'house-check'];
                ?>
                <div class="text-center position-relative" style="z-index:1;flex:1">
                    <div class="mx-auto rounded-circle d-flex align-items-center justify-content-center fw-bold"
                         style="width:40px;height:40px;background:<?= $done ? '#0d6efd' : '#dee2e6' ?>;color:<?= $done ? '#fff' : '#6c757d' ?>">
                        <i class="bi bi-<?= $iconMap[$step] ?? 'circle' ?>"></i>
                    </div>
                    <div class="mt-1 small <?= $current ? 'fw-bold text-primary' : ($done ? 'text-muted' : 'text-muted') ?>">
                        <?= ucfirst($step) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left: Items + Notes -->
        <div class="col-lg-8">
            <!-- Order Items -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">
                    <i class="bi bi-box me-2"></i>Order Items
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th>Product SKU</th>
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
                            <td class="text-muted small"><?= e($item['product_sku'] ?? '') ?></td>
                            <td class="text-center"><?= (int)$item['quantity'] ?></td>
                            <td class="text-end"><?= formatMoney((float)$item['unit_price']) ?></td>
                            <td class="text-end fw-semibold"><?= formatMoney((float)$item['total_price']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tracking Info -->
            <?php if ($tracking): ?>
            <div class="card border-0 shadow-sm mb-4 border-start border-4 border-primary">
                <div class="card-body">
                    <h6 class="fw-bold"><i class="bi bi-truck me-2 text-primary"></i>Shipping & Tracking</h6>
                    <?php if (!empty($tracking['carrier'])): ?>
                    <div><strong>Carrier:</strong> <?= e($tracking['carrier']) ?></div>
                    <?php endif; ?>
                    <div><strong>Tracking #:</strong> <?= e($tracking['tracking_number']) ?></div>
                    <?php if (!empty($tracking['tracking_url'])): ?>
                    <a href="<?= e($tracking['tracking_url']) ?>" target="_blank" rel="noopener"
                       class="btn btn-sm btn-outline-primary mt-2">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Track Package
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Notes / Messages -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">
                    <i class="bi bi-chat-left-text me-2"></i>Messages
                </div>
                <div class="card-body">
                    <?php if (empty($notes)): ?>
                    <p class="text-muted mb-0">No messages yet.</p>
                    <?php else: ?>
                    <?php foreach ($notes as $note): ?>
                    <div class="mb-3 p-3 rounded bg-light">
                        <div class="d-flex justify-content-between">
                            <strong class="small"><?= e(trim(($note['first_name'] ?? '') . ' ' . ($note['last_name'] ?? '')) ?: 'System') ?></strong>
                            <span class="text-muted small"><?= formatDateTime($note['created_at']) ?></span>
                        </div>
                        <p class="mb-0 mt-1"><?= nl2br(e($note['note'])) ?></p>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>

                    <form id="addNoteForm" class="mt-3">
                        <?= csrfField() ?>
                        <input type="hidden" name="order_id" value="<?= $orderId ?>">
                        <input type="hidden" name="is_internal" value="0">
                        <div class="input-group">
                            <textarea name="note" class="form-control" rows="2" placeholder="Send a message…"></textarea>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right: Summary + Actions -->
        <div class="col-lg-4">
            <!-- Order Totals -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Order Summary</div>
                <div class="card-body">
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
                    <hr>
                    <div class="d-flex justify-content-between fw-bold fs-5">
                        <span>Total</span>
                        <span><?= formatMoney((float)$order['total']) ?></span>
                    </div>
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

            <!-- Actions -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body d-grid gap-2">
                    <?php if (in_array($order['status'], ['pending', 'confirmed'], true)): ?>
                    <button class="btn btn-danger" onclick="cancelOrder(<?= $orderId ?>)">
                        <i class="bi bi-x-circle me-1"></i>Cancel Order
                    </button>
                    <?php endif; ?>
                    <?php if ($order['status'] === 'shipped'): ?>
                    <button class="btn btn-success" onclick="confirmDelivery(<?= $orderId ?>)">
                        <i class="bi bi-house-check me-1"></i>Confirm Delivery
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="cancelForm" method="POST" action="/api/orders.php?action=cancel">
                <?= csrfField() ?>
                <input type="hidden" name="order_id" value="<?= $orderId ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Cancel Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Reason (optional)</label>
                    <textarea name="reason" class="form-control" rows="3" placeholder="Why are you cancelling?"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Order</button>
                    <button type="submit" class="btn btn-danger">Cancel Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function cancelOrder(id) {
    new bootstrap.Modal(document.getElementById('cancelModal')).show();
}
function confirmDelivery(id) {
    if (!confirm('Confirm that you have received this order?')) return;
    const fd = new FormData();
    fd.append('order_id', id);
    fd.append('_csrf_token', document.querySelector('[name=_csrf_token]').value);
    fetch('/api/orders.php?action=confirm_delivery', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.success) location.reload(); else alert(d.message); });
}
document.getElementById('cancelForm').addEventListener('submit', function(e) {
    e.preventDefault();
    fetch(this.action, { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(d => { if (d.success) location.href = '/pages/account/orders/index.php'; else alert(d.message); });
});
document.getElementById('addNoteForm').addEventListener('submit', function(e) {
    e.preventDefault();
    fetch('/api/orders.php?action=add_note', { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(d => { if (d.success) location.reload(); else alert(d.message); });
});
</script>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
