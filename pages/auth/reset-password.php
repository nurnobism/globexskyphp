<?php
require_once __DIR__ . '/../../includes/middleware.php';

$token = get('token', '');
if (!$token) redirect('/pages/auth/forgot-password.php');

$pageTitle = 'Reset Password';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <h3 class="fw-bold"><i class="bi bi-shield-lock-fill text-primary"></i> Reset Password</h3>
                        <p class="text-muted small">Enter your new password</p>
                    </div>
                    <form method="POST" action="/api/auth.php?action=reset_password">
                        <?= csrfField() ?>
                        <input type="hidden" name="token" value="<?= e($token) ?>">
                        <input type="hidden" name="_redirect" value="<?= e($_SERVER['REQUEST_URI']) ?>">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">New Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Min 8 characters" required minlength="8">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Confirm Password</label>
                            <input type="password" name="password_confirm" class="form-control" placeholder="Repeat password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">Reset Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
