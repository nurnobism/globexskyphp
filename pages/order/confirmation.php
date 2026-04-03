<?php
/**
 * pages/order/confirmation.php — Order Confirmation / Thank You
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$db = getDB();

$orderId     = (int)get('id', 0);
$orderNumber = trim(get('order_number', ''));

if (!$orderId && !$orderNumber) {
    flashMessage('danger', 'Order not found.');
    redirect('/pages/order/index.php');
}

if ($orderId) {
    $stmt = $db->prepare('SELECT o.*, u.first_name, u.last_name, u.email FROM orders o JOIN users u ON u.id = o.buyer_id WHERE o.id = ? AND o.buyer_id = ?');
    $stmt->execute([$orderId, $_SESSION['user_id']]);
} else {
    $stmt = $db->prepare('SELECT o.*, u.first_name, u.last_name, u.email FROM orders o JOIN users u ON u.id = o.buyer_id WHERE o.order_number = ? AND o.buyer_id = ?');
    $stmt->execute([$orderNumber, $_SESSION['user_id']]);
}
$order = $stmt->fetch();

if (!$order) {
    flashMessage('danger', 'Order not found.');
    redirect('/pages/order/index.php');
}

$iStmt = $db->prepare('SELECT oi.*, p.slug product_slug FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?');
$iStmt->execute([$order['id']]);
$items = $iStmt->fetchAll();

$shippingAddr = json_decode($order['shipping_address'] ?? '{}', true);

$pageTitle = 'Order Confirmed — ' . $order['order_number'];
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <!-- Success Banner -->
    <div class="text-center mb-5">
        <div class="display-1 text-success mb-3"><i class="bi bi-check-circle-fill"></i></div>
        <h2 class="fw-bold">Thank You for Your Order!</h2>
        <p class="text-muted fs-5">Your order <strong><?= e($order['order_number']) ?></strong> has been placed successfully.</p>
        <p class="text-muted">A confirmation will be sent to <strong><?= e($order['email']) ?></strong>.</p>
    </div>

    <div class="row g-4 justify-content-center">
        <div class="col-lg-8">

            <!-- Order Items -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3"><h6 class="fw-bold mb-0"><i class="bi bi-bag me-2 text-primary"></i>Order Items</h6></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light"><tr><th>Product</th><th>Qty</th><th>Price</th><th class="text-end">Total</th></tr></thead>
                            <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <?php if ($item['product_slug']): ?>
                                    <a href="/pages/product/detail.php?slug=<?= urlencode($item['product_slug']) ?>" class="text-decoration-none fw-semibold"><?= e($item['product_name']) ?></a>
                                    <?php else: ?>
                                    <span class="fw-semibold"><?= e($item['product_name']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($item['product_sku']): ?><br><small class="text-muted">SKU: <?= e($item['product_sku']) ?></small><?php endif; ?>
                                </td>
                                <td><?= $item['quantity'] ?></td>
                                <td><?= formatMoney($item['unit_price']) ?></td>
                                <td class="text-end fw-semibold"><?= formatMoney($item['total_price']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 border-top">
                        <dl class="row mb-0 small">
                            <dt class="col-6">Subtotal</dt><dd class="col-6 text-end"><?= formatMoney($order['subtotal']) ?></dd>
                            <dt class="col-6">Shipping (<?= e(ucfirst($order['shipping_method'] ?? 'standard')) ?>)</dt>
                            <dd class="col-6 text-end"><?= $order['shipping_fee'] > 0 ? formatMoney($order['shipping_fee']) : 'Free' ?></dd>
                            <dt class="col-6">Tax (5%)</dt><dd class="col-6 text-end"><?= formatMoney($order['tax']) ?></dd>
                            <?php if ($order['discount'] > 0): ?>
                            <dt class="col-6 text-success">Discount</dt><dd class="col-6 text-end text-success">-<?= formatMoney($order['discount']) ?></dd>
                            <?php endif; ?>
                        </dl>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between fw-bold fs-5">
                            <span>Total</span>
                            <span class="text-primary"><?= formatMoney($order['total']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Order Info -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white py-3"><h6 class="fw-bold mb-0"><i class="bi bi-receipt me-2 text-primary"></i>Order Details</h6></div>
                        <div class="card-body">
                            <?php
                            $statusBadge = ['pending'=>'warning','confirmed'=>'info','processing'=>'info','shipped'=>'primary','delivered'=>'success','cancelled'=>'danger','refunded'=>'secondary'];
                            $payBadge    = ['paid'=>'success','pending'=>'warning','failed'=>'danger','refunded'=>'secondary'];
                            ?>
                            <dl class="row small mb-0">
                                <dt class="col-5 text-muted">Order #</dt><dd class="col-7 fw-semibold"><?= e($order['order_number']) ?></dd>
                                <dt class="col-5 text-muted">Status</dt><dd class="col-7"><span class="badge bg-<?= $statusBadge[$order['status']] ?? 'secondary' ?>"><?= ucfirst($order['status']) ?></span></dd>
                                <dt class="col-5 text-muted">Payment</dt><dd class="col-7"><span class="badge bg-<?= $payBadge[$order['payment_status']] ?? 'secondary' ?>"><?= ucfirst($order['payment_status']) ?></span></dd>
                                <dt class="col-5 text-muted">Method</dt><dd class="col-7"><?= e(str_replace('_', ' ', ucfirst($order['payment_method'] ?? '—'))) ?></dd>
                                <dt class="col-5 text-muted">Shipping</dt><dd class="col-7"><?= e(ucfirst($order['shipping_method'] ?? 'standard')) ?></dd>
                                <dt class="col-5 text-muted">Placed</dt><dd class="col-7"><?= formatDateTime($order['placed_at']) ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Shipping Address -->
                <?php if ($shippingAddr): ?>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white py-3"><h6 class="fw-bold mb-0"><i class="bi bi-geo-alt me-2 text-primary"></i>Shipping Address</h6></div>
                        <div class="card-body small text-muted">
                            <strong class="text-dark"><?= e($shippingAddr['full_name'] ?? '') ?></strong><br>
                            <?php if (!empty($shippingAddr['phone'])): ?><i class="bi bi-telephone me-1"></i><?= e($shippingAddr['phone']) ?><br><?php endif; ?>
                            <?= e($shippingAddr['address_line1'] ?? '') ?>
                            <?php if (!empty($shippingAddr['address_line2'])): ?><br><?= e($shippingAddr['address_line2']) ?><?php endif; ?><br>
                            <?= e($shippingAddr['city'] ?? '') ?><?= !empty($shippingAddr['state']) ? ', ' . e($shippingAddr['state']) : '' ?>
                            <?= e($shippingAddr['postal_code'] ?? '') ?><br>
                            <?= e($shippingAddr['country'] ?? '') ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="d-flex flex-wrap gap-3 mt-4">
                <a href="/pages/order/detail.php?id=<?= $order['id'] ?>" class="btn btn-outline-primary">
                    <i class="bi bi-receipt me-1"></i> View Order Details
                </a>
                <a href="/pages/order/index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-list-ul me-1"></i> All Orders
                </a>
                <a href="/" class="btn btn-primary">
                    <i class="bi bi-shop me-1"></i> Continue Shopping
                </a>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
