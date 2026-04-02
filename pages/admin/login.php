<?php
/**
 * pages/admin/login.php — Dedicated admin login page
 *
 * Features:
 * - Separate form for admin/super_admin accounts only
 * - CSRF token protection
 * - Rate limiting: max 5 failed attempts per 15 minutes (session-based)
 * - On success → redirect to /pages/admin/index.php
 */

require_once __DIR__ . '/../../includes/middleware.php';

// Already logged-in admins go straight to the dashboard
if (isLoggedIn() && isAdmin()) {
    redirect('/pages/admin/index.php');
}

// Rate-limit tracking stored in session
const ADMIN_MAX_ATTEMPTS = 5;
const ADMIN_LOCKOUT_SECS = 900; // 15 minutes

$attempts  = (int)($_SESSION['admin_login_attempts'] ?? 0);
$lockedAt  = (int)($_SESSION['admin_login_locked_at'] ?? 0);
$lockout   = false;
$lockMsg   = '';
$errorMsg  = '';
$infoMsg   = '';

// Check whether we are still in a lockout window
if ($lockedAt && (time() - $lockedAt) < ADMIN_LOCKOUT_SECS) {
    $lockout     = true;
    $remaining   = ADMIN_LOCKOUT_SECS - (time() - $lockedAt);
    $lockMsg     = 'Too many failed attempts. Please wait ' . ceil($remaining / 60) . ' minute(s) before trying again.';
} elseif ($lockedAt && (time() - $lockedAt) >= ADMIN_LOCKOUT_SECS) {
    // Lockout period has expired — reset counters
    $_SESSION['admin_login_attempts']  = 0;
    $_SESSION['admin_login_locked_at'] = 0;
    $attempts = 0;
    $lockedAt = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$lockout) {

    if (!verifyCsrf()) {
        $errorMsg = 'Invalid security token. Please refresh the page and try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $errorMsg = 'Email and password are required.';
        } else {
            try {
                $stmt = getDB()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                $validCredentials = $user && password_verify($password, $user['password_hash']);
                $isAdminRole      = $user && in_array($user['role'], ['admin', 'super_admin'], true);

                if ($validCredentials && $isAdminRole && $user['is_active']) {
                    // Successful admin login — reset counters
                    $_SESSION['admin_login_attempts']  = 0;
                    $_SESSION['admin_login_locked_at'] = 0;

                    loginUser($user);

                    // Record session
                    try {
                        $token   = bin2hex(random_bytes(32));
                        $expires = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
                        $ip      = $_SERVER['REMOTE_ADDR'] ?? '';
                        $ua      = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
                        getDB()->prepare(
                            'INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at)
                             VALUES (?, ?, ?, ?, ?)'
                        )->execute([$user['id'], $token, $ip, $ua, $expires]);
                    } catch (PDOException $e) { /* best-effort */ }

                    // Log admin login activity
                    try {
                        getDB()->prepare(
                            'INSERT INTO admin_activity_logs (admin_id, action, target_type, details, ip_address)
                             VALUES (?, ?, ?, ?, ?)'
                        )->execute([
                            $user['id'],
                            'admin_login',
                            'user',
                            json_encode(['email' => $user['email'], 'role' => $user['role']]),
                            $_SERVER['REMOTE_ADDR'] ?? '',
                        ]);
                    } catch (PDOException $e) { /* best-effort */ }

                    getDB()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);

                    redirect('/pages/admin/index.php');

                } else {
                    // Failed attempt — increment counter
                    $attempts++;
                    $_SESSION['admin_login_attempts'] = $attempts;

                    if ($attempts >= ADMIN_MAX_ATTEMPTS) {
                        $_SESSION['admin_login_locked_at'] = time();
                        $lockout = true;
                        $lockMsg = 'Too many failed attempts. Please wait 15 minutes before trying again.';
                    } else {
                        $remaining = ADMIN_MAX_ATTEMPTS - $attempts;
                        if ($validCredentials && !$isAdminRole) {
                            $errorMsg = 'This login page is for admin accounts only.';
                        } else {
                            $errorMsg = 'Invalid email or password. ' . $remaining . ' attempt(s) remaining.';
                        }
                    }
                }
            } catch (PDOException $e) {
                $errorMsg = 'A database error occurred. Please try again later.';
            }
        }
    }
}

$pageTitle = 'Admin Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f0f2f5; }
        .login-card { max-width: 420px; margin: 80px auto; }
    </style>
</head>
<body>
<div class="login-card px-3">
    <div class="text-center mb-4">
        <a href="/" class="text-decoration-none">
            <h3 class="fw-bold text-primary"><i class="bi bi-globe2"></i> <?= e(APP_NAME) ?></h3>
        </a>
        <p class="text-muted small mb-0">Administration Panel</p>
    </div>

    <div class="card border-0 shadow">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-4 text-center"><i class="bi bi-shield-lock text-primary me-2"></i>Admin Sign In</h5>

            <?php if ($lockout): ?>
                <div class="alert alert-danger d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= e($lockMsg) ?>
                </div>
            <?php elseif ($errorMsg): ?>
                <div class="alert alert-danger d-flex align-items-center">
                    <i class="bi bi-x-circle me-2"></i>
                    <?= e($errorMsg) ?>
                </div>
            <?php elseif ($infoMsg): ?>
                <div class="alert alert-info">
                    <?= e($infoMsg) ?>
                </div>
            <?php endif; ?>

            <?php if (!$lockout): ?>
            <form method="POST" action="" autocomplete="off">
                <?= csrfField() ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control"
                               placeholder="admin@globexsky.com"
                               value="<?= e($_POST['email'] ?? '') ?>"
                               required autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" id="adminPwd" class="form-control"
                               placeholder="Your admin password" required>
                        <button type="button" class="btn btn-outline-secondary"
                                onclick="togglePwd('adminPwd', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                </button>
            </form>
            <?php endif; ?>

            <hr class="my-3">
            <div class="text-center small text-muted">
                <a href="/pages/auth/forgot-password.php" class="text-decoration-none">Forgot your password?</a>
                &nbsp;|&nbsp;
                <a href="/" class="text-decoration-none">← Back to site</a>
            </div>
        </div>
    </div>

    <p class="text-center text-muted small mt-3">
        <i class="bi bi-shield-check text-success me-1"></i>
        Secure admin access — <?= e(APP_NAME) ?>
    </p>
</div>

<script>
function togglePwd(id, btn) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.innerHTML = input.type === 'password'
        ? '<i class="bi bi-eye"></i>'
        : '<i class="bi bi-eye-slash"></i>';
}
</script>
</body>
</html>
