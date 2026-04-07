<?php
/**
 * pages/checkout/payment-failed.php — Payment Failed Page (PR #6)
 */

require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/checkout.php';

requireLogin();

$orderId = (int)($_GET['order_id'] ?? 0);
$reason  = htmlspecialchars(strip_tags($_GET['reason'] ?? ''), ENT_QUOTES, 'UTF-8');

$order = null;
if ($orderId > 0) {
    $db     = getDB();
    $userId = (int)$_SESSION['user_id'];
    $stmt   = $db->prepare('SELECT id, order_number, total, payment_method FROM orders WHERE id = ? AND buyer_id = ?');
    $stmt->execute([$orderId, $userId]);
    $order  = $stmt->fetch();
}

$pageTitle = 'Payment Failed';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 text-center">

            <div class="display-1 text-danger mb-3">
                <i class="bi bi-x-circle-fill"></i>
            </div>
            <h2 class="fw-bold mb-2">Payment Failed</h2>
            <p class="text-muted fs-5 mb-4">
                <?php if ($reason): ?>
                <?= $reason ?>
                <?php else: ?>
                We were unable to process your payment. Your order has been saved and you can try again.
                <?php endif; ?>
            </p>

            <?php if ($order): ?>
            <div class="card border-0 shadow-sm mb-4 text-start">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5 text-muted">Order Number</dt>
                        <dd class="col-7 fw-semibold"><?= e($order['order_number']) ?></dd>
                        <dt class="col-5 text-muted">Amount Due</dt>
                        <dd class="col-7 fw-bold text-primary"><?= formatMoney($order['total']) ?></dd>
                        <dt class="col-5 text-muted">Payment Method</dt>
                        <dd class="col-7"><?= e(ucwords(str_replace('_',' ',$order['payment_method'] ?? ''))) ?></dd>
                    </dl>
                </div>
            </div>
            <?php endif; ?>

            <div class="d-flex flex-wrap gap-3 justify-content-center">
                <a href="/pages/checkout/index.php" class="btn btn-primary btn-lg">
                    <i class="bi bi-arrow-repeat me-1"></i> Try Again
                </a>
                <a href="/pages/support/index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-headset me-1"></i> Contact Support
                </a>
                <a href="/" class="btn btn-outline-secondary">
                    <i class="bi bi-house me-1"></i> Go Home
                </a>
            </div>

        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
