<?php
/**
 * Admin — Email Template Preview
 * Lists all email templates and renders a preview with sample data.
 */
require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();

$pageTitle = 'Email Templates';

// All available templates with sample data for preview
$templates = [
    'welcome' => [
        'label'  => 'Welcome',
        'desc'   => 'Sent after user registration',
        'render' => function () {
            require_once __DIR__ . '/../../../templates/emails/welcome.php';
            return emailWelcome('Jane Smith', 'https://example.com/login');
        },
    ],
    'email-verification' => [
        'label'  => 'Email Verification',
        'desc'   => 'Verify new email address with link + OTP',
        'render' => function () {
            require_once __DIR__ . '/../../../templates/emails/email-verification.php';
            return emailEmailVerification('Jane Smith', 'https://example.com/verify?token=abc123', '847291');
        },
    ],
    'password-reset' => [
        'label'  => 'Password Reset',
        'desc'   => 'Password reset link',
        'render' => function () {
            require_once __DIR__ . '/../../../templates/emails/password-reset.php';
            return emailPasswordReset('Jane Smith', 'https://example.com/reset?token=xyz');
        },
    ],
    'password-changed' => [
        'label'  => 'Password Changed',
        'desc'   => 'Security alert when password is changed',
        'render' => function () {
            require_once __DIR__ . '/../../../templates/emails/password-changed.php';
            return emailPasswordChanged('Jane Smith', date('D, d M Y H:i'), 'https://example.com/support');
        },
    ],
    'order-placed' => [
        'label'  => 'Order Placed',
        'desc'   => 'Order confirmation for buyer',
        'render' => function () {
            require_once __DIR__ . '/../../../templates/emails/order-placed.php';
            return emailOrderPlaced([
                'id'           => 1042,
                'buyer_name'   => 'Jane Smith',
                'total_amount' => 248.50,
                'currency'     => 'USD',
                'order_url'    => 'https://example.com/orders/1042',
                'items'        => [
                    ['product_name' => 'Wireless Headphones Pro', 'quantity' => 2, 'unit_price' => 89.99],
                    ['product_name' => 'USB-C Charging Cable',    'quantity' => 1, 'unit_price' => 12.99],
                ],
            ]);
        },
    ],
    'order-confirmed' => [
        'label'  => 'Order Confirmed',
        'desc'   => 'Supplier confirmed the order',
        'render' => function () {
            require_once __DIR__ . '/../../../templates/emails/order-confirmed.php';
            return emailOrderConfirmed('Jane Smith', 1042, 'https://example.com/orders/1042', 'Within 3 business days');
        },
    ],
    'order-shipped' => [
        'label'  => 'Order Shipped',
        'desc'   => 'Tracking details sent to buyer',
        'render' => function () {
            require_once __DIR__ . '/../../../templates/emails/order-shipped.php';
            return emailOrderShipped('Jane Smith', 1042, 'DHL Express', '1234567890', 'https://dhl.com/track?id=1234567890', 'Dec 15, 2025');
        },
    ],
    'order-delivered' => [
        'label'  => 'Order Delivered',
        'desc'   => 'Delivery confirmed + review prompt',
        'render' => function () {
            require_once __DIR__ . '/../../../templates/emails/order-delivered.php';
            return emailOrderDelivered('Jane Smith', 1042, 'https://example.com/orders/1042/review');
        },
    ],
    'order-cancelled' => [
        'label'  => 'Order Cancelled',
        'desc'   => 'Order cancellation notification',
        'render' => function () {
            require_once __DIR__ . '/../../../templates/emails/order-cancelled.php';
            return emailOrderCancelled('Jane Smith', 1042, 'Item is out of stock.', 'https://example.com/orders/1042');
        },
    ],
    'new-order' => [
        'label'  => 'New Order (Supplier)',
        'desc'   => 'New order notification for supplier',
        'render' => function () {
            require_once __DIR__ . '/../../../templates/emails/new-order.php';
            return emailNewOrder(
                'Acme Supplies',
                1042,
                'Jane Smith',
                [['product_name' => 'Widget Pro', 'quantity' => 5, 'unit_price' => 29.99]],
                'USD',
                149.95,
                'https://example.com/supplier/orders'
            );
        },
    ],
    'payout-processed' => [
        'label'  => 'Payout Processed',
        'desc'   => 'Payout sent to supplier/carrier',
        'render' => function () {
            require_once __DIR__ . '/../../../templates/emails/payout-processed.php';
            return emailPayoutProcessed('Acme Supplies', '$1,250.00', 'Bank Transfer', 'TXN-20251201-7842', 'https://example.com/payouts');
        },
    ],
    'payout-rejected' => [
        'label'  => 'Payout Rejected',
        'desc'   => 'Payout request rejected with reason',
        'render' => function () {
            require_once __DIR__ . '/../../../templates/emails/payout-rejected.php';
            return emailPayoutRejected('Acme Supplies', '$500.00', 'Bank account details are incomplete.', 'https://example.com/payouts');
        },
    ],
    'plan-expires-soon' => [
        'label'  => 'Plan Expires Soon',
        'desc'   => 'Subscription expiry reminder',
        'render' => function () {
            require_once __DIR__ . '/../../../templates/emails/plan-expires-soon.php';
            return emailPlanExpiresSoon('Jane Smith', 'Pro Plan', 'December 31, 2025', 'https://example.com/billing');
        },
    ],
    'plan-expired' => [
        'label'  => 'Plan Expired',
        'desc'   => 'Plan has expired notification',
        'render' => function () {
            require_once __DIR__ . '/../../../templates/emails/plan-expired.php';
            return emailPlanExpired('Jane Smith', 'Pro Plan', 'https://example.com/billing');
        },
    ],
    'kyc-approved' => [
        'label'  => 'KYC Approved',
        'desc'   => 'KYC verification approved',
        'render' => function () {
            require_once __DIR__ . '/../../../templates/emails/kyc-approved.php';
            return emailKycApproved('Jane Smith', 'https://example.com/dashboard');
        },
    ],
    'kyc-rejected' => [
        'label'  => 'KYC Rejected',
        'desc'   => 'KYC rejected with reason',
        'render' => function () {
            require_once __DIR__ . '/../../../templates/emails/kyc-rejected.php';
            return emailKycRejected('Jane Smith', 'Uploaded ID document is blurry and unreadable.', 'https://example.com/kyc');
        },
    ],
    'new-message' => [
        'label'  => 'New Message',
        'desc'   => 'New chat message digest',
        'render' => function () {
            require_once __DIR__ . '/../../../templates/emails/new-message.php';
            return emailNewMessage('Jane Smith', 'Bob Trader', 'Can you send me a quote for 500 units?', 'https://example.com/messages');
        },
    ],
    'dispute-opened' => [
        'label'  => 'Dispute Opened',
        'desc'   => 'Dispute notification for both parties',
        'render' => function () {
            require_once __DIR__ . '/../../../templates/emails/dispute-opened.php';
            return emailDisputeOpened('Acme Supplies', 1042, 17, 'Items received were damaged.', 'https://example.com/disputes/17');
        },
    ],
    'invoice' => [
        'label'  => 'Invoice',
        'desc'   => 'Invoice notification / download link',
        'render' => function () {
            require_once __DIR__ . '/../../../templates/emails/invoice.php';
            return emailInvoice('Jane Smith', 'INV-2025-0042', 'Dec 1, 2025', 'Dec 15, 2025', 1499.00, 'USD', 'https://example.com/invoices/42');
        },
    ],
];

$preview  = $_GET['template'] ?? '';
$previewHtml = null;

if ($preview && isset($templates[$preview])) {
    $previewHtml = ($templates[$preview]['render'])();
}

include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-envelope-paper-fill text-primary me-2"></i>Email Templates</h3>
        <a href="/pages/admin/settings/email.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-gear me-1"></i>SMTP Settings
        </a>
    </div>

    <?php if ($previewHtml): ?>
    <!-- Preview panel -->
    <div class="mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h5 class="mb-0">
                Previewing: <strong><?= htmlspecialchars($templates[$preview]['label'], ENT_QUOTES, 'UTF-8') ?></strong>
            </h5>
            <a href="?" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to list</a>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <iframe srcdoc="<?= htmlspecialchars($previewHtml, ENT_QUOTES, 'UTF-8') ?>"
                        style="width:100%;height:700px;border:none;border-radius:0.375rem;"
                        title="Email preview"></iframe>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Template list -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3">Template</th>
                            <th class="py-3">Description</th>
                            <th class="py-3 text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($templates as $key => $tpl): ?>
                        <tr>
                            <td class="ps-4 py-3">
                                <span class="badge bg-light text-secondary border font-monospace small"><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>.php</span>
                                <div class="fw-semibold mt-1"><?= htmlspecialchars($tpl['label'], ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td class="py-3 text-muted small"><?= htmlspecialchars($tpl['desc'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-3 text-end pe-4">
                                <a href="?template=<?= urlencode($key) ?>"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye me-1"></i>Preview
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
