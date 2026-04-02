<?php
require_once __DIR__ . '/../includes/middleware.php';
$pageTitle = 'Help Center';
include __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <div class="text-center mb-5">
        <h2 class="fw-bold"><i class="bi bi-question-circle-fill text-primary me-2"></i>Help Center</h2>
        <p class="text-muted lead">Find answers to common questions</p>
        <form class="d-flex justify-content-center mt-3">
            <div class="input-group" style="max-width:500px">
                <input type="search" class="form-control form-control-lg" placeholder="Search help articles...">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
            </div>
        </form>
    </div>
    <div class="row g-4 mb-5">
        <?php foreach ([
            ['bi-person-plus', 'Getting Started', '#getting-started', 'Account creation, verification, and first steps on GlobexSky'],
            ['bi-cart3', 'Buying', '#buying', 'How to find products, place orders, and make payments'],
            ['bi-shop', 'Selling', '#selling', 'List products, manage inventory, and receive payments as a supplier'],
            ['bi-truck', 'Shipping', '#shipping', 'Shipping methods, tracking, and delivery policies'],
            ['bi-shield-check', 'Trade Assurance', '#trade', 'How our buyer protection and escrow services work'],
            ['bi-headset', 'Contact Support', '/pages/contact.php', 'Get direct help from our support team'],
        ] as [$icon, $title, $link, $desc]): ?>
        <div class="col-md-4">
            <a href="<?= $link ?>" class="card border-0 shadow-sm h-100 text-decoration-none text-dark">
                <div class="card-body p-4 d-flex flex-column">
                    <i class="bi <?= $icon ?> text-primary fs-1 mb-3"></i>
                    <h5 class="fw-bold"><?= $title ?></h5>
                    <p class="text-muted mb-0"><?= $desc ?></p>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- FAQ -->
    <h4 class="fw-bold mb-4" id="getting-started">Frequently Asked Questions</h4>
    <div class="accordion" id="faqAccordion">
        <?php $faqs = [
            ['How do I create an account?', 'Click the "Register" button at the top of the page. Fill in your name, email, and password. Choose whether you are a buyer or supplier. Complete the registration form and you will be logged in immediately.'],
            ['How can I verify my supplier account?', 'After registering as a supplier, go to your profile settings and submit your business documents. Our team reviews and verifies supplier accounts within 2-3 business days.'],
            ['How do I place an order?', 'Browse products, add items to your cart, proceed to checkout, enter your shipping address, select a payment method, and confirm your order.'],
            ['What payment methods are accepted?', 'We accept Bank Transfer, Wire Transfer, PayPal, and our Escrow (Trade Assurance) service for larger orders.'],
            ['How can I track my shipment?', 'Go to My Orders or visit the Shipment Tracking page and enter your tracking number.'],
            ['How does the RFQ system work?', 'Submit a Request for Quotation with your product requirements. Verified suppliers will respond with their best quotes. You can compare and accept the best offer.'],
            ['Is my payment secure?', 'Yes. We use escrow-based payments for large orders where funds are only released to the supplier after you confirm receipt and satisfaction.'],
            ['How do I contact a supplier?', 'Visit the supplier\'s profile page and use the "Send Message" or "Request Quote" buttons to start a conversation.'],
        ]; ?>
        <?php foreach ($faqs as $i => [$q, $a]): ?>
        <div class="accordion-item border-0 mb-2 shadow-sm">
            <h2 class="accordion-header">
                <button class="accordion-button <?= $i > 0 ? 'collapsed' : '' ?> fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= $i ?>">
                    <?= e($q) ?>
                </button>
            </h2>
            <div id="faq<?= $i ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>" data-bs-parent="#faqAccordion">
                <div class="accordion-body text-muted"><?= e($a) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="text-center mt-5 p-4 bg-light rounded">
        <h5 class="fw-bold">Still need help?</h5>
        <p class="text-muted">Our support team is available Monday through Friday, 9am–6pm UTC</p>
        <a href="/pages/contact.php" class="btn btn-primary"><i class="bi bi-headset me-1"></i>Contact Support</a>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
