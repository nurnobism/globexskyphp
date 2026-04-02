<?php
require_once __DIR__ . '/../../includes/middleware.php';

if (isLoggedIn()) {
    redirect('/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Forward to auth API
    $_POST['_redirect'] = $_POST['redirect'] ?? '/';
    include __DIR__ . '/../../api/auth.php';
    exit;
}

$pageTitle = 'Login';
$redirect  = get('redirect', '/');
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <h3 class="fw-bold"><i class="bi bi-person-circle text-primary"></i> Sign In</h3>
                        <p class="text-muted small">Welcome back to <?= e(APP_NAME) ?></p>
                    </div>
                    <form method="POST" action="/api/auth.php?action=login">
                        <?= csrfField() ?>
                        <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
                        <input type="hidden" name="_redirect" value="<?= e($_SERVER['REQUEST_URI']) ?>">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="you@example.com" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold d-flex justify-content-between">
                                Password
                                <a href="/pages/auth/forgot-password.php" class="small text-decoration-none">Forgot?</a>
                            </label>
                            <div class="input-group">
                                <input type="password" name="password" id="loginPassword" class="form-control" placeholder="Your password" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('loginPassword',this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="rememberMe">
                            <label class="form-check-label" for="rememberMe">Remember me</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">Sign In</button>
                    </form>
                    <hr>
                    <p class="text-center small mb-0">
                        Don't have an account?
                        <a href="/pages/auth/register.php" class="fw-semibold text-decoration-none">Create one</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function togglePwd(id, btn) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.innerHTML = input.type === 'password' ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
}
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
