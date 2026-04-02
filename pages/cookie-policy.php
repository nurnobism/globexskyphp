<?php
require_once __DIR__ . '/../includes/middleware.php';
$pageTitle = 'Cookie Policy';
include __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <h1 class="fw-bold mb-2">Cookie Policy</h1>
            <p class="text-muted">Last updated: <?= date('F j, Y') ?></p>
            <hr>
            <p class="lead">This Cookie Policy explains how <?= e(APP_NAME) ?> uses cookies and similar technologies when you visit our platform.</p>
            <h4 class="fw-bold mt-4 mb-2">What Are Cookies?</h4>
            <p class="text-muted">Cookies are small text files placed on your device when you visit a website. They help the website remember your actions and preferences over time.</p>
            <h4 class="fw-bold mt-4 mb-2">Types of Cookies We Use</h4>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light"><tr><th>Type</th><th>Purpose</th><th>Duration</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Essential</strong></td><td>Session management, authentication, CSRF protection</td><td>Session / 24 hours</td></tr>
                        <tr><td><strong>Functional</strong></td><td>Remember language preference, cart contents for guests</td><td>30 days</td></tr>
                        <tr><td><strong>Analytics</strong></td><td>Understand platform usage to improve features</td><td>90 days</td></tr>
                    </tbody>
                </table>
            </div>
            <h4 class="fw-bold mt-4 mb-2">Managing Cookies</h4>
            <p class="text-muted">You can control cookies through your browser settings. Note that disabling essential cookies may affect platform functionality. Most browsers allow you to block or delete cookies through their settings menu.</p>
            <h4 class="fw-bold mt-4 mb-2">Contact</h4>
            <p class="text-muted">For cookie-related questions, contact us at <a href="mailto:privacy@globexsky.com">privacy@globexsky.com</a>.</p>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
