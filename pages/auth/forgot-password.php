<?php
require_once __DIR__ . '/../../includes/middleware.php';

if (isLoggedIn()) redirect('/');

$pageTitle = 'Forgot Password';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <h3 class="fw-bold"><i class="bi bi-lock-fill text-primary"></i> Forgot Password</h3>
                        <p class="text-muted small">Enter your email to receive a reset link</p>
                    </div>
                    <form method="POST" action="/api/auth.php?action=forgot_password">
                        <?= csrfField() ?>
                        <input type="hidden" name="_redirect" value="<?= e($_SERVER['REQUEST_URI']) ?>">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="you@example.com" required autofocus>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">Send Reset Link</button>
                    </form>
                    <p class="text-center mt-3 small mb-0">
                        <a href="/pages/auth/login.php" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Back to Login</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
