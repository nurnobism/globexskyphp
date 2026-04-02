<?php
require_once __DIR__ . '/../includes/middleware.php';
$pageTitle = 'Privacy Policy';
include __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <h1 class="fw-bold mb-2">Privacy Policy</h1>
            <p class="text-muted">Last updated: <?= date('F j, Y') ?></p>
            <hr>
            <?php foreach ([
                ['Information We Collect', 'We collect information you provide directly to us, such as when you create an account, make a purchase, submit an RFQ, or contact us for support. This includes name, email address, phone number, company name, billing and shipping addresses, and payment information.'],
                ['How We Use Your Information', 'We use the information we collect to: process transactions and send related information; create and manage your account; send promotional communications (with your consent); respond to your comments and questions; and improve our services.'],
                ['Information Sharing', 'We do not sell, trade, or otherwise transfer your personal information to third parties without your consent, except as described in this policy. We may share your information with trusted service providers who assist us in operating our website and conducting our business.'],
                ['Data Security', 'We implement appropriate technical and organizational security measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction. All data transmission is encrypted using SSL/TLS.'],
                ['Cookies', 'We use cookies and similar tracking technologies to track activity on our platform and hold certain information. You can instruct your browser to refuse all cookies or to indicate when a cookie is being sent.'],
                ['Your Rights', 'You have the right to access, correct, or delete your personal information. You may also object to or restrict the processing of your data. To exercise these rights, please contact us at privacy@globexsky.com.'],
                ['Changes to This Policy', 'We may update our Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page and updating the "Last updated" date.'],
                ['Contact Us', 'If you have any questions about this Privacy Policy, please contact us at privacy@globexsky.com or through our <a href="/pages/contact.php">Contact Page</a>.'],
            ] as [$heading, $content]): ?>
            <h4 class="fw-bold mt-5 mb-3"><?= $heading ?></h4>
            <p class="text-muted"><?= $content ?></p>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
