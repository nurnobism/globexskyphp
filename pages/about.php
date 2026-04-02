<?php
require_once __DIR__ . '/../includes/middleware.php';
$pageTitle = 'About Us';
include __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <!-- Hero -->
    <div class="text-center py-5 mb-5" style="background:linear-gradient(135deg,#0d6efd15,#6610f215);border-radius:1rem">
        <h1 class="fw-bold display-4">About <?= e(APP_NAME) ?></h1>
        <p class="lead text-muted">Connecting the world through trade</p>
    </div>

    <div class="row g-5 align-items-center mb-5">
        <div class="col-lg-6">
            <h2 class="fw-bold mb-3">Our Mission</h2>
            <p class="text-muted lead">GlobexSky is a global B2B trade platform designed to connect buyers and suppliers worldwide. We streamline international commerce by providing a secure, efficient, and transparent marketplace for businesses of all sizes.</p>
            <p class="text-muted">Founded with the vision of making global trade accessible to everyone, we provide the tools, infrastructure, and support that businesses need to thrive in the global market.</p>
        </div>
        <div class="col-lg-6">
            <div class="row g-3">
                <?php foreach ([
                    ['10K+', 'Verified Suppliers', 'building-fill', 'primary'],
                    ['500K+', 'Products Listed', 'box-seam-fill', 'success'],
                    ['50+', 'Countries Served', 'globe2', 'warning'],
                    ['98%', 'Customer Satisfaction', 'star-fill', 'info'],
                ] as [$num, $label, $icon, $color]): ?>
                <div class="col-6">
                    <div class="card border-0 shadow-sm text-center p-4">
                        <i class="bi bi-<?= $icon ?> text-<?= $color ?> fs-1 mb-2"></i>
                        <h3 class="fw-bold text-<?= $color ?>"><?= $num ?></h3>
                        <p class="text-muted mb-0"><?= $label ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-12 text-center mb-3"><h2 class="fw-bold">Why Choose GlobexSky?</h2></div>
        <?php foreach ([
            ['bi-shield-check', 'Trade Assurance', 'Our escrow and trade assurance program protects both buyers and suppliers throughout every transaction.'],
            ['bi-search', 'Verified Suppliers', 'All suppliers on our platform go through a rigorous verification process to ensure quality and reliability.'],
            ['bi-lightning-charge', 'Fast RFQ', 'Submit a Request for Quotation and receive competitive quotes from multiple suppliers within 24 hours.'],
            ['bi-headset', '24/7 Support', 'Our dedicated support team is available around the clock to assist you with any trade-related questions.'],
            ['bi-translate', 'Multi-Language', 'Platform available in multiple languages to serve our global community of buyers and suppliers.'],
            ['bi-graph-up', 'Insights & Analytics', 'Access market insights, price trends, and analytics to make informed purchasing decisions.'],
        ] as [$icon, $title, $desc]): ?>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 p-4">
                <i class="bi <?= $icon ?> text-primary fs-1 mb-3"></i>
                <h5 class="fw-bold"><?= $title ?></h5>
                <p class="text-muted mb-0"><?= $desc ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- CTA -->
    <div class="text-center py-5 bg-primary rounded text-white">
        <h2 class="fw-bold mb-3">Ready to Start Trading?</h2>
        <p class="lead mb-4">Join thousands of businesses already using GlobexSky</p>
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <a href="/pages/auth/register.php" class="btn btn-light btn-lg px-5">Get Started Free</a>
            <a href="/pages/contact.php" class="btn btn-outline-light btn-lg px-5">Contact Us</a>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
