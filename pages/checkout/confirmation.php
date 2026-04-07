<?php
/**
 * pages/checkout/confirmation.php — Order Confirmation Page (PR #6)
 */

require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/checkout.php';

requireLogin();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

$orderId = (int)($_GET['order_id'] ?? 0);
if ($orderId <= 0) {
    redirect('/pages/cart/index.php');
}

// Verify order belongs to logged-in user
$oStmt = $db->prepare('SELECT id FROM orders WHERE id = ? AND buyer_id = ?');
$oStmt->execute([$orderId, $userId]);
if (!$oStmt->fetch()) {
    flashMessage('error', 'Order not found.');
    redirect('/');
}

$order = getOrderSummary($orderId);
if (!$order) {
    flashMessage('error', 'Order not found.');
    redirect('/');
}

$shipping = is_array($order['shipping_address']) ? $order['shipping_address'] : [];

$pageTitle = 'Order Confirmation — #' . e($order['order_number']);
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <!-- Success Banner -->
            <div class="text-center mb-5">
                <div class="display-1 text-success mb-3">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <h2 class="fw-bold">Thank You for Your Order!</h2>
                <p class="text-muted fs-5">
                    Your order has been placed successfully.
                    <?php if (!empty($order['buyer_email'])): ?>
                    A confirmation has been sent to <strong><?= e($order['buyer_email']) ?></strong>.
                    <?php endif; ?>
                </p>
                <div class="d-inline-block bg-light border rounded px-4 py-2 mt-2">
                    <span class="text-muted small">Order Number</span><br>
                    <strong class="fs-4 text-primary"><?= e($order['order_number']) ?></strong>
                </div>
            </div>

            <!-- Order Details Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-bag-check me-2 text-primary"></i>Order Details</h6>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($order['items'])): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($order['items'] as $item): ?>
                        <div class="list-group-item d-flex align-items-center gap-3 px-4 py-3">
                            <?php if (!empty($item['product_image'])): ?>
                            <img src="<?= e($item['product_image']) ?>" alt="" class="rounded" style="width:56px;height:56px;object-fit:cover">
                            <?php else: ?>
                            <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width:56px;height:56px">
                                <i class="bi bi-image text-muted"></i>
                            </div>
                            <?php endif; ?>
                            <div class="flex-grow-1">
                                <div class="fw-semibold"><?= e($item['product_name']) ?></div>
                                <?php if (!empty($item['variation_info'])): ?>
                                <small class="text-muted"><?= e($item['variation_info']) ?></small>
                                <?php endif; ?>
                                <div class="text-muted small">Qty: <?= (int)$item['quantity'] ?> × <?= formatMoney($item['unit_price']) ?></div>
                            </div>
                            <span class="fw-bold"><?= formatMoney($item['total_price']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="p-4">
                        <dl class="row mb-0">
                            <dt class="col-6">Subtotal</dt>
                            <dd class="col-6 text-end"><?= formatMoney($order['subtotal']) ?></dd>
                            <dt class="col-6">Shipping</dt>
                            <dd class="col-6 text-end">
                                <?= (float)$order['shipping_fee'] > 0 ? formatMoney($order['shipping_fee']) : '<span class="text-success">Free</span>' ?>
                            </dd>
                            <dt class="col-6">Tax</dt>
                            <dd class="col-6 text-end"><?= formatMoney($order['tax']) ?></dd>
                        </dl>
                        <hr>
                        <div class="d-flex justify-content-between fw-bold fs-5">
                            <span>Total Paid</span>
                            <span class="text-primary"><?= formatMoney($order['total']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Shipping Address & Payment Info -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white py-3">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-geo-alt me-2 text-primary"></i>Shipping Address</h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($shipping)): ?>
                            <address class="mb-0">
                                <strong><?= e($shipping['full_name'] ?? '') ?></strong><br>
                                <?= e($shipping['address_line1'] ?? '') ?>
                                <?php if (!empty($shipping['address_line2'])): ?><br><?= e($shipping['address_line2']) ?><?php endif; ?><br>
                                <?= e($shipping['city'] ?? '') ?>
                                <?php if (!empty($shipping['state'])): ?>, <?= e($shipping['state']) ?><?php endif; ?>
                                <?= e($shipping['postal_code'] ?? '') ?><br>
                                <?= e($shipping['country'] ?? '') ?>
                                <?php if (!empty($shipping['phone'])): ?><br><i class="bi bi-telephone me-1"></i><?= e($shipping['phone']) ?><?php endif; ?>
                            </address>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white py-3">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-wallet2 me-2 text-primary"></i>Payment Info</h6>
                        </div>
                        <div class="card-body">
                            <dl class="mb-0">
                                <dt class="text-muted small">Method</dt>
                                <dd class="fw-semibold"><?= e(ucwords(str_replace('_', ' ', $order['payment_method'] ?? 'N/A'))) ?></dd>
                                <dt class="text-muted small">Status</dt>
                                <dd>
                                    <?php
                                    $ps = $order['payment_status'] ?? 'pending';
                                    $badge = match($ps) {
                                        'paid'     => 'success',
                                        'failed'   => 'danger',
                                        'refunded' => 'warning',
                                        default    => 'secondary',
                                    };
                                    ?>
                                    <span class="badge bg-<?= $badge ?>"><?= ucfirst($ps) ?></span>
                                </dd>
                                <dt class="text-muted small">Order Status</dt>
                                <dd>
                                    <?php
                                    $os = $order['status'] ?? 'pending';
                                    $obadge = match($os) {
                                        'confirmed','processing' => 'primary',
                                        'shipped'               => 'info',
                                        'delivered'             => 'success',
                                        'cancelled'             => 'danger',
                                        'refunded'              => 'warning',
                                        default                  => 'secondary',
                                    };
                                    ?>
                                    <span class="badge bg-<?= $obadge ?>"><?= ucfirst($os) ?></span>
                                </dd>
                                <dt class="text-muted small">Estimated Delivery</dt>
                                <dd class="text-muted">5–10 business days</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex flex-wrap gap-3 justify-content-center">
                <a href="/pages/order/track.php?id=<?= $orderId ?>" class="btn btn-outline-primary">
                    <i class="bi bi-geo-alt-fill me-1"></i> Track Order
                </a>
                <a href="/pages/product/index.php" class="btn btn-primary">
                    <i class="bi bi-cart me-1"></i> Continue Shopping
                </a>
            </div>

        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
