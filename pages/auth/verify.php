<?php
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pageTitle = 'Verify Email — Globex Sky';
$status = 'pending'; // pending | success | error | already_verified
$message = '';

$token = trim($_GET['token'] ?? '');

if (empty($token)) {
    $status = 'error';
    $message = 'No verification token provided.';
} else {
    if (function_exists('verifyEmail')) {
        $result = verifyEmail($token);
        if ($result['success']) {
            $status = 'success';
            $message = $result['message'] ?? 'Your email has been verified successfully!';
        } elseif (isset($result['already_verified']) && $result['already_verified']) {
            $status = 'already_verified';
            $message = 'This email address has already been verified.';
        } else {
            $status = 'error';
            $message = $result['message'] ?? 'Invalid or expired verification link.';
        }
    } else {
        // Fallback: manual DB verification
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT id, email_verified_at FROM users WHERE email_verify_token = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([hash('sha256', $token)]);
            $user = $stmt->fetch();
            if (!$user) {
                $status = 'error';
                $message = 'Invalid or expired verification link. Please request a new one.';
            } elseif (!empty($user['email_verified_at'])) {
                $status = 'already_verified';
                $message = 'This email address has already been verified.';
            } else {
                $pdo->prepare("UPDATE users SET email_verified_at = NOW(), email_verify_token = NULL WHERE id = ?")->execute([$user['id']]);
                $status = 'success';
                $message = 'Your email has been verified successfully! You can now log in.';
            }
        } catch (Exception $e) {
            $status = 'error';
            $message = 'A server error occurred. Please try again or contact support.';
        }
    }
}

require_once '../../includes/header.php';
?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-5 text-center">
      <div class="card shadow border-0">
        <div class="card-body p-5">
          <?php if ($status === 'success'): ?>
            <div class="bg-success rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width:80px;height:80px;">
              <i class="fas fa-check fa-2x text-white"></i>
            </div>
            <h2 class="fw-bold text-success">Email Verified!</h2>
            <p class="text-muted"><?= htmlspecialchars($message) ?></p>
            <a href="/pages/auth/login.php" class="btn btn-warning btn-lg px-5 mt-2">Sign In Now</a>
          <?php elseif ($status === 'already_verified'): ?>
            <div class="bg-info rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width:80px;height:80px;">
              <i class="fas fa-info fa-2x text-white"></i>
            </div>
            <h2 class="fw-bold text-info">Already Verified</h2>
            <p class="text-muted"><?= htmlspecialchars($message) ?></p>
            <a href="/pages/auth/login.php" class="btn btn-warning btn-lg px-5 mt-2">Go to Login</a>
          <?php else: ?>
            <div class="bg-danger rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width:80px;height:80px;">
              <i class="fas fa-times fa-2x text-white"></i>
            </div>
            <h2 class="fw-bold text-danger">Verification Failed</h2>
            <p class="text-muted"><?= htmlspecialchars($message) ?></p>
            <a href="/pages/auth/login.php" class="btn btn-warning btn-lg px-4 mt-2 me-2">Login</a>
            <a href="/pages/auth/register.php" class="btn btn-outline-secondary btn-lg px-4 mt-2">Register</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
