<?php
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (isLoggedIn()) { header('Location: /dashboard.php'); exit; }

$pageTitle = 'Login — Globex Sky';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $result = loginUser($_POST['email'] ?? '', $_POST['password'] ?? '', !empty($_POST['remember']));
        if ($result['success']) {
            $redirect = $_SESSION['intended'] ?? '/dashboard.php';
            unset($_SESSION['intended']);
            header('Location: ' . $redirect); exit;
        } else {
            $error = $result['message'] ?? 'Invalid credentials.';
        }
    }
}

require_once '../../includes/header.php';
?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card shadow border-0">
        <div class="card-body p-5">
          <div class="text-center mb-4">
            <h2 class="fw-bold">Welcome Back</h2>
            <p class="text-muted">Sign in to your Globex Sky account</p>
          </div>
          <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>
          <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <div class="mb-3">
              <label class="form-label fw-semibold">Email Address</label>
              <input type="email" name="email" class="form-control form-control-lg" placeholder="you@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold d-flex justify-content-between">
                Password
                <a href="/pages/auth/forgot-password.php" class="text-warning small">Forgot password?</a>
              </label>
              <div class="input-group">
                <input type="password" name="password" id="passwordField" class="form-control form-control-lg" placeholder="••••••••" required>
                <button type="button" class="btn btn-outline-secondary" onclick="togglePassword()"><i class="fas fa-eye" id="eyeIcon"></i></button>
              </div>
            </div>
            <div class="mb-4 form-check">
              <input type="checkbox" name="remember" class="form-check-input" id="rememberMe">
              <label class="form-check-label" for="rememberMe">Remember me for 30 days</label>
            </div>
            <button type="submit" class="btn btn-warning btn-lg w-100 fw-bold">Sign In</button>
          </form>
          <hr class="my-4">
          <div class="d-grid gap-2">
            <button class="btn btn-outline-danger" type="button"><i class="fab fa-google me-2"></i>Continue with Google</button>
            <button class="btn btn-outline-primary" type="button"><i class="fab fa-facebook me-2"></i>Continue with Facebook</button>
          </div>
          <p class="text-center mt-4 mb-0 text-muted">Don't have an account? <a href="/pages/auth/register.php" class="text-warning fw-semibold">Create one</a></p>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
function togglePassword() {
  const f = document.getElementById('passwordField');
  const i = document.getElementById('eyeIcon');
  if (f.type === 'password') { f.type = 'text'; i.classList.replace('fa-eye','fa-eye-slash'); }
  else { f.type = 'password'; i.classList.replace('fa-eye-slash','fa-eye'); }
}
</script>
<?php require_once '../../includes/footer.php'; ?>
