<?php
require_once __DIR__ . '/../../includes/middleware.php';

$pageTitle = 'GDPR & Data Privacy';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item active">GDPR & Data Privacy</li>
        </ol>
    </nav>

    <div class="text-center mb-5">
        <i class="bi bi-shield-lock display-3 text-primary"></i>
        <h1 class="display-6 fw-bold mt-3">GDPR & Data Privacy</h1>
        <p class="lead text-muted">Your privacy matters. Learn how we protect and manage your personal data.</p>
    </div>

    <div class="row g-4">
        <!-- Your Data Rights -->
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-person-check me-2"></i>Your Data Rights</h4>
                </div>
                <div class="card-body">
                    <p>Under the General Data Protection Regulation (GDPR), you have the following rights:</p>
                    <ul class="list-unstyled">
                        <li class="mb-3">
                            <i class="bi bi-eye text-primary me-2 fs-5"></i>
                            <strong>Right to Access</strong>
                            <p class="text-muted small ms-4 mb-0">Request a copy of all personal data we hold about you.</p>
                        </li>
                        <li class="mb-3">
                            <i class="bi bi-pencil-square text-primary me-2 fs-5"></i>
                            <strong>Right to Rectification</strong>
                            <p class="text-muted small ms-4 mb-0">Request correction of inaccurate or incomplete data.</p>
                        </li>
                        <li class="mb-3">
                            <i class="bi bi-trash text-primary me-2 fs-5"></i>
                            <strong>Right to Erasure</strong>
                            <p class="text-muted small ms-4 mb-0">Request deletion of your personal data ("right to be forgotten").</p>
                        </li>
                        <li class="mb-3">
                            <i class="bi bi-download text-primary me-2 fs-5"></i>
                            <strong>Right to Data Portability</strong>
                            <p class="text-muted small ms-4 mb-0">Receive your data in a portable, machine-readable format.</p>
                        </li>
                        <li class="mb-3">
                            <i class="bi bi-hand-thumbs-down text-primary me-2 fs-5"></i>
                            <strong>Right to Object</strong>
                            <p class="text-muted small ms-4 mb-0">Object to processing of your data for specific purposes.</p>
                        </li>
                        <li class="mb-0">
                            <i class="bi bi-pause-circle text-primary me-2 fs-5"></i>
                            <strong>Right to Restrict Processing</strong>
                            <p class="text-muted small ms-4 mb-0">Request limitation of how we process your data.</p>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- How We Use Data -->
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0"><i class="bi bi-database-check me-2"></i>How We Use Your Data</h4>
                </div>
                <div class="card-body">
                    <p>We collect and process personal data for the following purposes:</p>
                    <div class="mb-3">
                        <h6><i class="bi bi-cart me-2 text-success"></i>Order Processing</h6>
                        <p class="text-muted small">Name, address, email, and payment information to fulfill your orders and provide customer support.</p>
                    </div>
                    <div class="mb-3">
                        <h6><i class="bi bi-graph-up me-2 text-success"></i>Service Improvement</h6>
                        <p class="text-muted small">Usage analytics and browsing behavior to improve our platform, personalize recommendations, and enhance user experience.</p>
                    </div>
                    <div class="mb-3">
                        <h6><i class="bi bi-envelope me-2 text-success"></i>Communications</h6>
                        <p class="text-muted small">Email address for order confirmations, shipping updates, and (with your consent) marketing newsletters.</p>
                    </div>
                    <div class="mb-3">
                        <h6><i class="bi bi-shield me-2 text-success"></i>Security & Fraud Prevention</h6>
                        <p class="text-muted small">IP addresses, device information, and transaction patterns to protect against fraud and maintain platform security.</p>
                    </div>
                    <div class="mb-0">
                        <h6><i class="bi bi-building me-2 text-success"></i>Legal Compliance</h6>
                        <p class="text-muted small mb-0">Financial and transaction records as required by tax, trade, and regulatory obligations.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Retention -->
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0"><i class="bi bi-clock-history me-2"></i>Data Retention</h4>
                </div>
                <div class="card-body">
                    <p>We retain personal data only as long as necessary:</p>
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr><th>Data Category</th><th>Retention Period</th></tr>
                        </thead>
                        <tbody>
                            <tr><td>Account Information</td><td>Duration of account + 30 days</td></tr>
                            <tr><td>Order Records</td><td>7 years (legal requirement)</td></tr>
                            <tr><td>Payment Data</td><td>As required by payment processor</td></tr>
                            <tr><td>Communication Logs</td><td>2 years</td></tr>
                            <tr><td>Analytics Data</td><td>26 months</td></tr>
                            <tr><td>Marketing Preferences</td><td>Until consent withdrawn</td></tr>
                            <tr><td>Session Data</td><td>24 hours</td></tr>
                        </tbody>
                    </table>
                    <p class="text-muted small mb-0">After the retention period, data is securely deleted or anonymized.</p>
                </div>
            </div>
        </div>

        <!-- Your Choices -->
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-info text-white">
                    <h4 class="mb-0"><i class="bi bi-toggles me-2"></i>Your Choices</h4>
                </div>
                <div class="card-body">
                    <p>You have control over your personal data:</p>
                    <div class="list-group list-group-flush">
                        <div class="list-group-item px-0">
                            <i class="bi bi-cookie text-info me-2"></i>
                            <strong>Cookie Preferences</strong>
                            <p class="text-muted small mb-0">Manage cookie settings through your browser or our <a href="/pages/cookie-policy.php">cookie policy</a> page.</p>
                        </div>
                        <div class="list-group-item px-0">
                            <i class="bi bi-envelope-x text-info me-2"></i>
                            <strong>Marketing Emails</strong>
                            <p class="text-muted small mb-0">Unsubscribe from marketing emails using the link in any email or through your account settings.</p>
                        </div>
                        <div class="list-group-item px-0">
                            <i class="bi bi-person-gear text-info me-2"></i>
                            <strong>Account Settings</strong>
                            <p class="text-muted small mb-0">Update or correct your personal information in your <a href="/pages/account/profile.php">account profile</a>.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons for Logged-in Users -->
    <?php if (isLoggedIn()): ?>
        <div class="card mt-4 border-primary">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-person-lock me-2"></i>Manage Your Data</h5>
                <p class="text-muted">Exercise your data rights by using the options below:</p>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="/pages/gdpr/data-request.php" class="btn btn-primary">
                        <i class="bi bi-download me-2"></i>Request Data Export
                    </a>
                    <a href="/pages/gdpr/delete-account.php" class="btn btn-outline-danger">
                        <i class="bi bi-trash me-2"></i>Delete My Account
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card mt-4">
            <div class="card-body text-center">
                <p class="mb-2">Log in to manage your personal data and exercise your data rights.</p>
                <a href="/pages/auth/login.php?redirect=/pages/gdpr/" class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Log In
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Contact -->
    <div class="text-center mt-4">
        <p class="text-muted">
            For any data privacy questions, contact our Data Protection Officer at
            <a href="mailto:dpo@globexsky.com">dpo@globexsky.com</a>
        </p>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
