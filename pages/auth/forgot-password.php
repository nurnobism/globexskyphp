<?php
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (isLoggedIn()) { header('Location: /dashboard.php'); exit; }

$pageTitle = 'Forgot Password — Globex Sky';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            try {
                $pdo = getDB();
                $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                if ($user) {
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
                    $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)")->execute([$email, hash('sha256', $token), $expires]);
                    $resetLink = APP_URL . '/pages/auth/reset-password.php?token=' . urlencode($token) . '&email=' . urlencode($email);
                    if (function_exists('sendEmail')) {
                        sendEmail($email, $user['name'], 'Password Reset — Globex Sky',
                            "<p>Hi {$user['name']},</p><p>Click the link below to reset your password. This link expires in 1 hour.</p><p><a href='{$resetLink}'>{$resetLink}</a></p>"
                        );
                    }
                }
                // Always show success to avoid email enumeration
                $success = 'If that email is registered, you will receive a password reset link shortly.';
            } catch (Exception $e) {
                $error = 'An error occurred. Please try again later.';
            }
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
            <div class="bg-warning rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;">
              <i class="fas fa-lock fa-2x text-dark"></i>
            </div>
            <h2 class="fw-bold">Forgot Password?</h2>
            <p class="text-muted">Enter your email and we'll send you a reset link.</p>
          </div>
          <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>
          <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?></div>
            <div class="text-center mt-3"><a href="/pages/auth/login.php" class="btn btn-warning px-4">Back to Login</a></div>
          <?php else: ?>
          <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <div class="mb-4">
              <label class="form-label fw-semibold">Email Address</label>
              <input type="email" name="email" class="form-control form-control-lg" placeholder="you@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
            </div>
            <button type="submit" class="btn btn-warning btn-lg w-100 fw-bold">Send Reset Link</button>
          </form>
          <p class="text-center mt-4 mb-0 text-muted"><a href="/pages/auth/login.php" class="text-warning"><i class="fas fa-arrow-left me-1"></i>Back to Login</a></p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
