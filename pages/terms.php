<?php
require_once __DIR__ . '/../includes/middleware.php';
$pageTitle = 'Terms of Service';
include __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <h1 class="fw-bold mb-2">Terms of Service</h1>
            <p class="text-muted">Last updated: <?= date('F j, Y') ?></p>
            <hr>
            <p class="lead">Please read these Terms of Service carefully before using <?= e(APP_NAME) ?>. By accessing or using our platform, you agree to be bound by these Terms.</p>
            <?php foreach ([
                ['1. Acceptance of Terms', 'By accessing and using GlobexSky, you accept and agree to be bound by these Terms of Service and our Privacy Policy. If you do not agree to these terms, please do not use our platform.'],
                ['2. User Accounts', 'You must create an account to access certain features. You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account. You must provide accurate and complete information when creating your account.'],
                ['3. Platform Use', 'GlobexSky provides a marketplace connecting buyers and suppliers. We do not own, sell, resell, furnish, provide, or supply any products listed by suppliers. We are not a party to any transaction between buyers and suppliers.'],
                ['4. Supplier Obligations', 'Suppliers must ensure all product listings are accurate, legal, and comply with all applicable laws and regulations. Suppliers are responsible for fulfilling orders, maintaining product quality, and handling any disputes in good faith.'],
                ['5. Buyer Obligations', 'Buyers agree to pay for orders placed, provide accurate shipping information, and act in good faith in all transactions. Buyers should review product descriptions carefully before purchasing.'],
                ['6. Payments & Fees', 'Payment terms are agreed between buyers and suppliers. GlobexSky may charge fees for certain services. All fees are non-refundable unless otherwise stated. Transaction security is provided through our Trade Assurance program.'],
                ['7. Dispute Resolution', 'In case of disputes, parties should first attempt to resolve them directly. GlobexSky offers mediation services for unresolved disputes. Our Trade Assurance program provides additional protection for qualifying transactions.'],
                ['8. Prohibited Activities', 'You may not: post false or misleading information; engage in fraudulent activities; violate any laws or regulations; spam or send unsolicited communications; attempt to bypass platform security measures.'],
                ['9. Limitation of Liability', 'GlobexSky is not liable for any indirect, incidental, special, consequential, or punitive damages resulting from your use of the platform. Our maximum liability is limited to fees paid by you in the past 12 months.'],
                ['10. Modifications', 'We reserve the right to modify these Terms at any time. Continued use of the platform after changes constitutes acceptance of the new Terms. We will notify users of significant changes via email or platform notification.'],
            ] as [$heading, $content]): ?>
            <h4 class="fw-bold mt-4 mb-2"><?= $heading ?></h4>
            <p class="text-muted"><?= $content ?></p>
            <?php endforeach; ?>
            <div class="alert alert-info mt-4">
                <strong>Questions?</strong> Contact us at <a href="mailto:legal@globexsky.com">legal@globexsky.com</a> or visit our <a href="/pages/contact.php">Contact Page</a>.
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
