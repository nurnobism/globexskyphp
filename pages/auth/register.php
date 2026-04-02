<?php
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (isLoggedIn()) { header('Location: /dashboard.php'); exit; }

$pageTitle = 'Create Account — Globex Sky';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } elseif (empty($_POST['terms'])) {
        $error = 'You must accept the Terms of Service.';
    } elseif (($_POST['password'] ?? '') !== ($_POST['password_confirm'] ?? '')) {
        $error = 'Passwords do not match.';
    } else {
        $result = registerUser([
            'name'         => $_POST['name'] ?? '',
            'email'        => $_POST['email'] ?? '',
            'password'     => $_POST['password'] ?? '',
            'phone'        => $_POST['phone'] ?? '',
            'account_type' => $_POST['account_type'] ?? 'buyer',
        ]);
        if ($result['success']) {
            $success = 'Account created! Please check your email to verify your account.';
        } else {
            $error = $result['message'] ?? 'Registration failed. Please try again.';
        }
    }
}

$accountType = $_GET['type'] ?? $_POST['account_type'] ?? 'buyer';
require_once '../../includes/header.php';
?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card shadow border-0">
        <div class="card-body p-5">
          <div class="text-center mb-4">
            <h2 class="fw-bold">Create Your Account</h2>
            <p class="text-muted">Join the Globex Sky global marketplace</p>
          </div>
          <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>
          <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
          <?php endif; ?>
          <?php if (!$success): ?>
          <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <div class="mb-3">
              <label class="form-label fw-semibold">I am a</label>
              <div class="d-flex gap-3">
                <?php foreach (['buyer'=>['fas fa-shopping-cart','Buyer'],'supplier'=>['fas fa-store','Supplier'],'carrier'=>['fas fa-plane','Carrier']] as $val => [$icon,$label]): ?>
                  <div class="flex-fill">
                    <input type="radio" class="btn-check" name="account_type" id="type_<?= $val ?>" value="<?= $val ?>" <?= ($accountType === $val) ? 'checked' : '' ?>>
                    <label class="btn btn-outline-warning w-100" for="type_<?= $val ?>"><i class="<?= $icon ?> me-1"></i><?= $label ?></label>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Full Name</label>
              <input type="text" name="name" class="form-control form-control-lg" placeholder="John Doe" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Email Address</label>
              <input type="email" name="email" class="form-control form-control-lg" placeholder="you@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Phone Number</label>
              <input type="tel" name="phone" class="form-control form-control-lg" placeholder="+1 555 000 0000" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>
            <div class="row g-3 mb-3">
              <div class="col">
                <label class="form-label fw-semibold">Password</label>
                <input type="password" name="password" class="form-control form-control-lg" placeholder="Min 8 characters" required minlength="8">
              </div>
              <div class="col">
                <label class="form-label fw-semibold">Confirm Password</label>
                <input type="password" name="password_confirm" class="form-control form-control-lg" placeholder="Repeat password" required>
              </div>
            </div>
            <div class="mb-4 form-check">
              <input type="checkbox" name="terms" class="form-check-input" id="terms" required>
              <label class="form-check-label" for="terms">I agree to the <a href="/pages/terms.php" class="text-warning">Terms of Service</a> and <a href="/pages/privacy.php" class="text-warning">Privacy Policy</a></label>
            </div>
            <button type="submit" class="btn btn-warning btn-lg w-100 fw-bold">Create Account</button>
          </form>
          <?php endif; ?>
          <p class="text-center mt-4 mb-0 text-muted">Already have an account? <a href="/pages/auth/login.php" class="text-warning fw-semibold">Sign in</a></p>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
