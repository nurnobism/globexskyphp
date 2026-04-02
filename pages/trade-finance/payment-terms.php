<?php
require_once __DIR__ . '/../../includes/middleware.php';

$pageTitle = 'Payment Terms';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <h3 class="fw-bold mb-4"><i class="bi bi-calendar-check text-success me-2"></i>Payment Terms</h3>

    <div class="row g-4 mb-5">
        <?php $terms = [
            ['Net 30', 'net_30', 'success', 'Payment due within 30 days of invoice date. Standard terms for established relationships.', ['Min order $5,000', 'Credit check required', 'Available to verified buyers']],
            ['Net 60', 'net_60', 'primary', 'Extended 60-day payment window for larger orders. Ideal for bulk purchasing.', ['Min order $10,000', 'Bank guarantee or LC required', 'Available to premium suppliers']],
            ['Net 90', 'net_90', 'warning', '90-day deferred payment for enterprise buyers with established track record.', ['Min order $25,000', 'Strong credit history required', 'Enterprise accounts only']],
            ['LC (Letter of Credit)', 'lc', 'info', 'Payment guaranteed via Letter of Credit, ensuring security for both parties.', ['Any order size', 'Bank-backed guarantee', 'International transactions']],
        ]; ?>
        <?php foreach ($terms as [$name, $id, $color, $desc, $features]): ?>
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-<?= $color ?> text-white text-center py-3">
                    <h5 class="mb-0 fw-bold"><?= $name ?></h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3"><?= $desc ?></p>
                    <ul class="list-unstyled small">
                        <?php foreach ($features as $f): ?>
                        <li class="mb-1"><i class="bi bi-check-circle-fill text-<?= $color ?> me-2"></i><?= $f ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="card-footer bg-white border-0">
                    <?php if (isLoggedIn()): ?>
                    <a href="/pages/trade-finance/credit-application.php?type=<?= $id ?>" class="btn btn-<?= $color ?> w-100 btn-sm">Apply Now</a>
                    <?php else: ?>
                    <a href="/pages/auth/login.php" class="btn btn-outline-<?= $color ?> w-100 btn-sm">Login to Apply</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- FAQ -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-question-circle me-2 text-primary"></i>Frequently Asked Questions</h5>
        </div>
        <div class="card-body p-0">
            <div class="accordion accordion-flush" id="paymentFAQ">
                <?php $faqs = [
                    ['How do I qualify for Net 30 terms?', 'To qualify for Net 30 terms, you need to be a verified buyer with a minimum order history of $5,000 and pass our credit check process. Apply through the Trade Finance section.'],
                    ['What happens if I miss a payment?', 'Late payments incur a 1.5% monthly fee. Consistent late payments may result in reverting to upfront payment terms. Please contact our support team if you face difficulties.'],
                    ['Is a Letter of Credit required for international orders?', 'For orders over $50,000 or from new international suppliers, we recommend using an LC for maximum security. It protects both buyer and seller.'],
                    ['How long does credit application take?', 'Standard credit applications are processed within 3-5 business days. Express processing (2 business days) is available for premium members.'],
                ]; ?>
                <?php foreach ($faqs as $i => [$q, $a]): ?>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button <?= $i > 0 ? 'collapsed' : '' ?>" type="button"
                                data-bs-toggle="collapse" data-bs-target="#faq<?= $i ?>">
                            <?= $q ?>
                        </button>
                    </h2>
                    <div id="faq<?= $i ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>">
                        <div class="accordion-body text-muted"><?= $a ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
