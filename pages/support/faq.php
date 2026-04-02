<?php
require_once __DIR__ . '/../../includes/middleware.php';

$faqs = [
    'general' => [
        'title' => 'General',
        'icon' => 'bi-info-circle',
        'items' => [
            ['q' => 'What is GlobexSky?', 'a' => 'GlobexSky is a global B2B marketplace connecting buyers with verified suppliers worldwide. We facilitate international trade by providing sourcing tools, secure payments, and logistics support.'],
            ['q' => 'How do I create an account?', 'a' => 'Click the "Sign Up" button on the top right corner. Fill in your business details, verify your email address, and complete your company profile to start trading.'],
            ['q' => 'Is GlobexSky free to use?', 'a' => 'Basic buyer accounts are free. Supplier accounts have various subscription tiers depending on the features and visibility required. Contact our sales team for enterprise pricing.'],
            ['q' => 'How do I contact support?', 'a' => 'You can reach us through the Support Center by creating a ticket, emailing support@globexsky.com, or using our live chat during business hours (Mon-Fri, 9AM-6PM EST).'],
        ],
    ],
    'orders' => [
        'title' => 'Orders',
        'icon' => 'bi-box-seam',
        'items' => [
            ['q' => 'How do I place an order?', 'a' => 'Browse products, add items to your cart, and proceed to checkout. You can negotiate terms directly with suppliers before confirming your order.'],
            ['q' => 'Can I modify an order after placing it?', 'a' => 'Orders can be modified before the supplier confirms them. Once confirmed, contact the supplier directly or create a support ticket for assistance.'],
            ['q' => 'What is the minimum order quantity?', 'a' => 'Minimum order quantities vary by product and supplier. Each product listing displays the MOQ set by the supplier. You can negotiate MOQs through our messaging system.'],
            ['q' => 'How do I track my order?', 'a' => 'Go to your Orders page to see all active orders. Each order has a tracking section with real-time status updates and shipping information once dispatched.'],
        ],
    ],
    'shipping' => [
        'title' => 'Shipping',
        'icon' => 'bi-truck',
        'items' => [
            ['q' => 'What shipping methods are available?', 'a' => 'We support air freight, sea freight, rail, and express courier services. Shipping options and costs depend on the origin, destination, and shipment size.'],
            ['q' => 'How long does shipping take?', 'a' => 'Delivery times vary: express courier (3-7 days), air freight (5-10 days), rail (15-25 days), and sea freight (20-45 days). Times depend on origin and destination.'],
            ['q' => 'Do you handle customs clearance?', 'a' => 'We provide customs documentation support and can connect you with licensed customs brokers. Import duties and taxes are the responsibility of the buyer.'],
        ],
    ],
    'payments' => [
        'title' => 'Payments',
        'icon' => 'bi-credit-card',
        'items' => [
            ['q' => 'What payment methods are accepted?', 'a' => 'We accept credit cards (Visa, Mastercard, Amex), bank transfers, PayPal, and trade assurance escrow. Large orders may qualify for letter of credit (L/C) terms.'],
            ['q' => 'Is my payment secure?', 'a' => 'All transactions are protected by 256-bit SSL encryption. Our Trade Assurance program holds funds in escrow until you confirm receipt of goods meeting your specifications.'],
            ['q' => 'What currencies do you support?', 'a' => 'We support USD, EUR, GBP, CNY, and JPY. Currency conversion is handled automatically at competitive exchange rates.'],
        ],
    ],
    'returns' => [
        'title' => 'Returns',
        'icon' => 'bi-arrow-return-left',
        'items' => [
            ['q' => 'What is the return policy?', 'a' => 'Return policies vary by supplier. Generally, defective or misrepresented goods can be returned within 30 days. Check each supplier\'s specific return policy before ordering.'],
            ['q' => 'How do I initiate a return?', 'a' => 'Go to your order details, click "Request Return," and provide the reason with supporting photos. The supplier has 5 business days to respond to your request.'],
            ['q' => 'How long do refunds take?', 'a' => 'Once a return is approved, refunds are processed within 5-10 business days. The refund goes back to the original payment method. Trade Assurance claims may take up to 15 days.'],
        ],
    ],
    'account' => [
        'title' => 'Account',
        'icon' => 'bi-person-circle',
        'items' => [
            ['q' => 'How do I reset my password?', 'a' => 'Click "Forgot Password" on the login page. Enter your email and follow the instructions in the reset email. For security, password reset links expire after 1 hour.'],
            ['q' => 'How do I verify my company?', 'a' => 'Go to Account Settings and submit your business license, tax registration, and company registration documents. Verification typically takes 2-3 business days.'],
            ['q' => 'Can I have multiple users on one account?', 'a' => 'Yes, our Teams feature allows you to invite team members with different roles and permissions. Go to the Teams section to manage your team.'],
        ],
    ],
];

$pageTitle = 'Frequently Asked Questions';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="text-center mb-4">
        <h1 class="h3"><i class="bi bi-question-circle me-2"></i>Frequently Asked Questions</h1>
        <p class="text-muted">Find answers to common questions about GlobexSky.</p>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-9">
            <?php foreach ($faqs as $key => $category): ?>
                <h5 class="mt-4 mb-3" id="<?= e($key) ?>">
                    <i class="bi <?= $category['icon'] ?> me-2 text-primary"></i><?= e($category['title']) ?>
                </h5>
                <div class="accordion mb-3" id="faq-<?= e($key) ?>">
                    <?php foreach ($category['items'] as $i => $item): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading-<?= e($key) ?>-<?= $i ?>">
                                <button class="accordion-button collapsed" type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#collapse-<?= e($key) ?>-<?= $i ?>">
                                    <?= e($item['q']) ?>
                                </button>
                            </h2>
                            <div id="collapse-<?= e($key) ?>-<?= $i ?>"
                                 class="accordion-collapse collapse"
                                 data-bs-parent="#faq-<?= e($key) ?>">
                                <div class="accordion-body text-muted">
                                    <?= e($item['a']) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div class="text-center py-4">
                <p class="text-muted">Can't find what you're looking for?</p>
                <a href="ticket.php" class="btn btn-primary">
                    <i class="bi bi-headset me-1"></i>Contact Support
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
