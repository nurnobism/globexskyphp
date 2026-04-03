<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$id = (int)get('id', 0);
if (!$id) redirect('/pages/order/index.php');

$db   = getDB();
$stmt = $db->prepare('SELECT o.*, u.first_name, u.last_name, u.email FROM orders o JOIN users u ON u.id=o.buyer_id WHERE o.id=? AND o.buyer_id=?');
$stmt->execute([$id, $_SESSION['user_id']]);
$order = $stmt->fetch();
if (!$order) { flashMessage('danger', 'Order not found.'); redirect('/pages/order/index.php'); }

$iStmt = $db->prepare('SELECT * FROM order_items WHERE order_id=?');
$iStmt->execute([$id]);
$items = $iStmt->fetchAll();

$sStmt = $db->prepare('SELECT * FROM shipments WHERE order_id=? ORDER BY created_at DESC LIMIT 1');
$sStmt->execute([$id]);
$shipment = $sStmt->fetch() ?: null;

$shippingAddr = json_decode($order['shipping_address'] ?? '{}', true);

$pageTitle = 'Order ' . $order['order_number'];
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-receipt text-primary me-2"></i>Order <?= e($order['order_number']) ?></h3>
        <a href="/pages/order/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Back</a>
    </div>

    <div class="row g-4">
        <!-- Order Items -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold">Order Items</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>Product</th><th>Qty</th><th>Price</th><th>Total</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($item['product_name']) ?></div>
                                    <?php if ($item['product_sku']): ?><small class="text-muted">SKU: <?= e($item['product_sku']) ?></small><?php endif; ?>
                                </td>
                                <td><?= $item['quantity'] ?></td>
                                <td><?= formatMoney($item['unit_price']) ?></td>
                                <td class="fw-semibold"><?= formatMoney($item['total_price']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Shipment Tracking -->
            <?php if ($shipment): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-truck me-2 text-primary"></i>Shipment Tracking</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Tracking Number</dt>
                        <dd class="col-sm-8"><a href="/pages/shipment/tracking.php?tracking=<?= urlencode($shipment['tracking_number']) ?>"><?= e($shipment['tracking_number']) ?></a></dd>
                        <dt class="col-sm-4">Carrier</dt>
                        <dd class="col-sm-8"><?= e($shipment['carrier'] ?? '—') ?></dd>
                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8"><span class="badge bg-info"><?= ucfirst(str_replace('_',' ',$shipment['status'])) ?></span></dd>
                        <?php if ($shipment['estimated_delivery']): ?>
                        <dt class="col-sm-4">Est. Delivery</dt>
                        <dd class="col-sm-8"><?= formatDate($shipment['estimated_delivery']) ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
            <?php endif; ?>

            <!-- Cancel Button -->
            <?php if (in_array($order['status'], ['pending', 'confirmed'])): ?>
            <form method="POST" action="/api/orders.php?action=cancel" onsubmit="return confirm('Cancel this order?')" class="d-inline">
                <?= csrfField() ?>
                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                <button type="submit" class="btn btn-outline-danger"><i class="bi bi-x-circle me-1"></i>Cancel Order</button>
            </form>
            <?php endif; ?>

            <!-- Reorder Button -->
            <a href="/pages/cart/index.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-repeat me-1"></i>Reorder
            </a>
        </div>

        <!-- Order Summary -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold">Order Summary</h6></div>
                <div class="card-body">
                    <?php
                    $statusBadge = ['pending'=>'warning','confirmed'=>'info','processing'=>'info','shipped'=>'primary','delivered'=>'success','cancelled'=>'danger','refunded'=>'secondary'];
                    $payBadge    = ['paid'=>'success','pending'=>'warning','failed'=>'danger','refunded'=>'secondary'];
                    ?>
                    <dl class="row mb-0">
                        <dt class="col-6 text-muted">Status</dt>
                        <dd class="col-6"><span class="badge bg-<?= $statusBadge[$order['status']] ?? 'secondary' ?>"><?= ucfirst($order['status']) ?></span></dd>
                        <dt class="col-6 text-muted">Payment</dt>
                        <dd class="col-6"><span class="badge bg-<?= $payBadge[$order['payment_status']] ?? 'secondary' ?>"><?= ucfirst($order['payment_status']) ?></span></dd>
                        <dt class="col-6 text-muted">Method</dt>
                        <dd class="col-6"><?= e(str_replace('_', ' ', ucfirst($order['payment_method'] ?? '—'))) ?></dd>
                        <?php if (!empty($order['stripe_payment_intent_id'])): ?>
                        <dt class="col-6 text-muted">Payment Ref</dt>
                        <dd class="col-6 small text-break"><?= e($order['stripe_payment_intent_id']) ?></dd>
                        <?php endif; ?>
                        <dt class="col-6 text-muted">Placed</dt>
                        <dd class="col-6"><?= formatDate($order['placed_at']) ?></dd>
                    </dl>
                    <hr>
                    <dl class="row mb-0">
                        <dt class="col-6">Subtotal</dt><dd class="col-6"><?= formatMoney($order['subtotal']) ?></dd>
                        <dt class="col-6">Shipping</dt><dd class="col-6"><?= formatMoney($order['shipping_fee']) ?></dd>
                        <dt class="col-6">Tax</dt><dd class="col-6"><?= formatMoney($order['tax']) ?></dd>
                        <?php if ($order['discount'] > 0): ?>
                        <dt class="col-6 text-success">Discount</dt><dd class="col-6 text-success">-<?= formatMoney($order['discount']) ?></dd>
                        <?php endif; ?>
                        <dt class="col-6 fw-bold fs-6 border-top pt-2">Total</dt>
                        <dd class="col-6 fw-bold fs-6 border-top pt-2"><?= formatMoney($order['total']) ?></dd>
                    </dl>
                </div>
            </div>

            <?php if ($shippingAddr): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold">Shipping Address</h6></div>
                <div class="card-body small text-muted">
                    <strong><?= e($shippingAddr['full_name'] ?? '') ?></strong><br>
                    <?= e($shippingAddr['address_line1'] ?? '') ?>
                    <?php if (!empty($shippingAddr['address_line2'])): ?><br><?= e($shippingAddr['address_line2']) ?><?php endif; ?><br>
                    <?= e($shippingAddr['city'] ?? '') ?><?= !empty($shippingAddr['state']) ? ', ' . e($shippingAddr['state']) : '' ?>
                    <?= e($shippingAddr['postal_code'] ?? '') ?><br>
                    <?= e($shippingAddr['country'] ?? '') ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
