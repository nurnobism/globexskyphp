<?php
/**
 * pages/checkout/payment-success.php — Stripe Redirect Success Handler (PR #6)
 *
 * Landing page for 3D Secure / redirect-based Stripe payments.
 * Verifies payment status and redirects to confirmation page.
 */

require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/checkout.php';
require_once __DIR__ . '/../../includes/stripe-handler.php';
require_once __DIR__ . '/../../config/stripe.php';

requireLogin();

$paymentIntentId     = trim($_GET['payment_intent'] ?? '');
$paymentIntentStatus = trim($_GET['payment_intent_client_secret'] ?? '');
$orderId             = (int)($_GET['order_id'] ?? 0);
$redirectStatus      = trim($_GET['redirect_status'] ?? '');

// If Stripe redirected with success, verify and confirm
if ($redirectStatus === 'succeeded' && $paymentIntentId && $orderId > 0) {
    try {
        $intent = confirmPayment($paymentIntentId);
        if (($intent['status'] ?? '') === 'succeeded') {
            $db     = getDB();
            $userId = (int)$_SESSION['user_id'];

            // Verify order ownership
            $oStmt = $db->prepare('SELECT id, payment_status FROM orders WHERE id = ? AND buyer_id = ?');
            $oStmt->execute([$orderId, $userId]);
            $order = $oStmt->fetch();

            if ($order && ($order['payment_status'] ?? '') !== 'paid') {
                updateOrderStatus($orderId, 'confirmed');
                $db->prepare(
                    "UPDATE orders SET payment_status='paid', confirmed_at=NOW(), updated_at=NOW() WHERE id = ?"
                )->execute([$orderId]);

                require_once __DIR__ . '/../../includes/notifications.php';
                _notifyOrderPaid($db, $orderId);
            }

            redirect('/pages/checkout/confirmation.php?order_id=' . $orderId);
        }
    } catch (Throwable $e) {
        error_log('payment-success handler error: ' . $e->getMessage());
    }
}

// If status is failed or unknown
if ($redirectStatus === 'requires_payment_method' || $redirectStatus === 'failed') {
    $qs = $orderId > 0 ? '?order_id=' . $orderId : '';
    redirect('/pages/checkout/payment-failed.php' . $qs);
}

// Fallback: show a simple message with redirect
$pageTitle = 'Payment Processing';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5 text-center">
    <div class="spinner-border text-primary mb-3" role="status"></div>
    <h4>Verifying your payment…</h4>
    <p class="text-muted">Please wait while we confirm your payment.</p>
    <?php if ($orderId > 0): ?>
    <a href="/pages/checkout/confirmation.php?order_id=<?= $orderId ?>" class="btn btn-primary mt-3">
        View Order Confirmation
    </a>
    <?php else: ?>
    <a href="/" class="btn btn-outline-secondary mt-3">Return to Home</a>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
