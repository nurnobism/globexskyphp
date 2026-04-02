<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$user = getCurrentUser();

$pageTitle = 'Delete Account';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item"><a href="/pages/gdpr/">GDPR</a></li>
            <li class="breadcrumb-item active">Delete Account</li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-lg-6">
            <h1 class="h2 mb-4 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Your Account</h1>

            <div id="alertContainer"></div>

            <!-- Warning Card -->
            <div class="card border-danger shadow-sm mb-4">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Warning: This Action Is Irreversible</h5>
                </div>
                <div class="card-body">
                    <p class="fw-bold">Deleting your account will permanently remove the following:</p>
                    <ul class="mb-3">
                        <li>Your profile information and account settings</li>
                        <li>Order history and transaction records</li>
                        <li>Saved addresses and payment methods</li>
                        <li>Product reviews and ratings</li>
                        <li>Messages and communication history</li>
                        <li>Wishlist and saved products</li>
                        <li>All customization settings</li>
                    </ul>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Note:</strong> Some data may be retained for legal compliance (e.g., financial records for 7 years).
                        You may want to <a href="/pages/gdpr/data-request.php">export your data</a> before deleting your account.
                    </div>
                </div>
            </div>

            <!-- Deletion Form -->
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Confirm Account Deletion</h5>
                </div>
                <div class="card-body">
                    <form id="deleteForm" method="post" action="/api/gdpr.php?action=request_delete">
                        <?= csrfField() ?>

                        <div class="mb-3">
                            <label for="password" class="form-label">
                                Current Password <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password"
                                       required placeholder="Enter your password to verify">
                                <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">Enter your password to confirm your identity.</div>
                        </div>

                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="confirmDelete" name="confirm" required>
                                <label class="form-check-label" for="confirmDelete">
                                    I understand that this action is <strong class="text-danger">permanent and irreversible</strong>.
                                    All my data will be deleted and cannot be recovered.
                                </label>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="/pages/gdpr/" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-danger" id="deleteBtn" disabled>
                                <i class="bi bi-trash me-2"></i>Delete My Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="text-center mt-3">
                <small class="text-muted">
                    Need help? <a href="/pages/contact.php">Contact support</a> or email
                    <a href="mailto:dpo@globexsky.com">dpo@globexsky.com</a>
                </small>
            </div>
        </div>
    </div>
</div>

<script>
// Enable delete button only when checkbox is checked
document.getElementById('confirmDelete')?.addEventListener('change', function() {
    document.getElementById('deleteBtn').disabled = !this.checked;
});

// Password toggle
document.getElementById('togglePassword')?.addEventListener('click', function() {
    const pwd = document.getElementById('password');
    const icon = this.querySelector('i');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        pwd.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
});

document.getElementById('deleteForm')?.addEventListener('submit', function(e) {
    e.preventDefault();

    if (!confirm('FINAL WARNING: Are you absolutely sure you want to delete your account? This cannot be undone.')) {
        return;
    }

    const alert = document.getElementById('alertContainer');
    const btn = document.getElementById('deleteBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';

    fetch(this.action, { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-trash me-2"></i>Delete My Account';
            if (data.success || data.status === 'success') {
                alert.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Account deletion request submitted. You will be logged out shortly.</div>';
                setTimeout(() => window.location.href = '/', 3000);
            } else {
                alert.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>' + (data.message || data.error || 'Failed to process deletion request') + '</div>';
                document.getElementById('confirmDelete').checked = false;
                btn.disabled = true;
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-trash me-2"></i>Delete My Account';
            alert.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>Network error. Please try again.</div>';
        });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
