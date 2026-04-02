<?php
/**
 * api/auth.php — Authentication API
 * Actions: login, register, logout, forgot_password, reset_password
 */

require_once __DIR__ . '/../includes/middleware.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

/**
 * Validate password strength: min 8 chars, uppercase, number, special char
 */
function validatePasswordStrength(string $password): ?string {
    if (strlen($password) < 8) return 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Z]/', $password)) return 'Password must contain at least one uppercase letter.';
    if (!preg_match('/[0-9]/', $password)) return 'Password must contain at least one number.';
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) return 'Password must contain at least one special character.';
    return null;
}

/**
 * Get redirect URL based on user role (PHP 8.0+ match expression)
 */
function getRoleRedirect(string $role): string {
    return match($role) {
        'super_admin', 'admin' => '/pages/admin/index.php',
        'supplier'             => '/pages/supplier/index.php',
        'carrier'              => '/pages/shipment/carrier/',
        default                => '/pages/account/',
    };
}

/**
 * Record a session in user_sessions table (best-effort)
 */
function recordUserSession(int $userId): void {
    try {
        $token     = bin2hex(random_bytes(32));
        $expires   = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
        $ip        = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua        = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        getDB()->prepare(
            'INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$userId, $token, $ip, $ua, $expires]);
    } catch (PDOException $e) { /* best-effort — table may not exist yet */ }
}

switch ($action) {

    // -------------------------------------------------------
    case 'login':
        if ($method !== 'POST') { jsonResponse(['error' => 'Method not allowed'], 405); }
        if (!verifyCsrf())      { jsonResponse(['error' => 'Invalid CSRF token'], 403); }

        $email    = trim(post('email', ''));
        $password = post('password', '');

        if (!$email || !$password) {
            if (isset($_POST['_redirect'])) { flashMessage('danger', 'Email and password are required.'); redirect($_POST['_redirect']); }
            jsonResponse(['error' => 'Email and password are required.'], 422);
        }

        $stmt = getDB()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            if (isset($_POST['_redirect'])) { flashMessage('danger', 'Invalid email or password.'); redirect($_POST['_redirect']); }
            jsonResponse(['error' => 'Invalid email or password.'], 401);
        }

        // Check account status
        $userStatus = $user['status'] ?? ($user['is_active'] ? 'active' : 'suspended');
        if ($userStatus === 'suspended') {
            if (isset($_POST['_redirect'])) { flashMessage('danger', 'Your account has been suspended. Please contact support.'); redirect($_POST['_redirect']); }
            jsonResponse(['error' => 'Account suspended.'], 403);
        }
        if ($userStatus === 'banned') {
            if (isset($_POST['_redirect'])) { flashMessage('danger', 'Your account has been banned.'); redirect($_POST['_redirect']); }
            jsonResponse(['error' => 'Account banned.'], 403);
        }
        if (!$user['is_active']) {
            if (isset($_POST['_redirect'])) { flashMessage('danger', 'Your account is inactive. Please contact support.'); redirect($_POST['_redirect']); }
            jsonResponse(['error' => 'Account inactive.'], 403);
        }

        loginUser($user);
        recordUserSession((int)$user['id']);
        getDB()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);

        $roleRedirect = getRoleRedirect($user['role']);
        $redirect     = $_POST['redirect'] ?? $roleRedirect;

        if (isset($_POST['_redirect'])) {
            $userEmail   = $user['email'] ?? '';
            $displayName = $user['first_name'] ?? (!empty($userEmail) ? explode('@', $userEmail)[0] : 'User');
            flashMessage('success', 'Welcome back, ' . $displayName . '!');
            redirect($redirect);
        }
        jsonResponse(['success' => true, 'user' => ['id' => $user['id'], 'email' => $user['email'], 'role' => $user['role']], 'redirect' => $redirect]);
        break;

    // -------------------------------------------------------
    case 'register':
        if ($method !== 'POST') { jsonResponse(['error' => 'Method not allowed'], 405); }
        if (!verifyCsrf())      { jsonResponse(['error' => 'Invalid CSRF token'], 403); }

        $first_name = trim(post('first_name', ''));
        $last_name  = trim(post('last_name', ''));
        $email      = trim(post('email', ''));
        $password   = post('password', '');
        $password2  = post('password_confirm', '');
        $allowedRoles = ['buyer', 'supplier', 'carrier'];
        $role       = in_array(post('role'), $allowedRoles) ? post('role') : 'buyer';

        $errors = [];
        if (empty($first_name))              $errors[] = 'First name is required.';
        if (empty($email) || !isValidEmail($email)) $errors[] = 'Valid email is required.';

        $pwdError = validatePasswordStrength($password);
        if ($pwdError)                       $errors[] = $pwdError;
        if ($password !== $password2)        $errors[] = 'Passwords do not match.';

        // Role-specific validation
        if ($role === 'supplier') {
            if (empty(trim(post('company_name', '')))) $errors[] = 'Company name is required for suppliers.';
        }
        if ($role === 'carrier') {
            if (empty(trim(post('passport_number', '')))) $errors[] = 'Passport number is required for carriers.';
        }

        if ($errors) {
            if (isset($_POST['_redirect'])) { flashMessage('danger', implode(' ', $errors)); redirect($_POST['_redirect']); }
            jsonResponse(['errors' => $errors], 422);
        }

        $db = getDB();
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            if (isset($_POST['_redirect'])) { flashMessage('danger', 'Email already registered.'); redirect($_POST['_redirect']); }
            jsonResponse(['error' => 'Email already registered.'], 409);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $fullName = trim($first_name . ' ' . $last_name);
        $stmt = $db->prepare('INSERT INTO users (email, password_hash, name, first_name, last_name, role, status, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
        $stmt->execute([$email, $hash, $fullName, $first_name, $last_name, $role, 'active']);
        $userId = (int)$db->lastInsertId();

        // Create supplier profile if role=supplier
        if ($role === 'supplier') {
            $companyName  = trim(post('company_name', $first_name . "'s Company"));
            $businessType = trim(post('business_type', ''));
            $country      = trim(post('country', ''));
            $slug = slugify($companyName) . '-' . $userId;
            $db->prepare('INSERT INTO suppliers (user_id, company_name, slug, business_type, country) VALUES (?, ?, ?, ?, ?)')
               ->execute([$userId, $companyName, $slug, $businessType, $country]);
        }

        // Create carrier profile if role=carrier
        if ($role === 'carrier') {
            $passportNumber = trim(post('passport_number', ''));
            $nationality    = trim(post('nationality', ''));
            $db->prepare('INSERT INTO carriers (user_id, full_name, passport_number, nationality) VALUES (?, ?, ?, ?)')
               ->execute([$userId, $fullName, $passportNumber, $nationality]);
        }

        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        loginUser($stmt->fetch());
        recordUserSession($userId);

        $roleRedirect = getRoleRedirect($role);
        if (isset($_POST['_redirect'])) { flashMessage('success', 'Welcome to GlobexSky!'); redirect($roleRedirect); }
        jsonResponse(['success' => true, 'user_id' => $userId, 'redirect' => $roleRedirect]);
        break;

    // -------------------------------------------------------
    case 'logout':
        // Delete session record (best-effort)
        try {
            if (isLoggedIn()) {
                getDB()->prepare('DELETE FROM user_sessions WHERE user_id = ?')->execute([$_SESSION['user_id']]);
            }
        } catch (PDOException $e) { /* best-effort */ }
        logoutUser();
        redirect('/');
        break;

    // -------------------------------------------------------
    case 'forgot_password':
        if ($method !== 'POST') { jsonResponse(['error' => 'Method not allowed'], 405); }
        if (!verifyCsrf())      { jsonResponse(['error' => 'Invalid CSRF token'], 403); }

        $email = trim(post('email', ''));
        if (!$email || !isValidEmail($email)) {
            if (isset($_POST['_redirect'])) { flashMessage('danger', 'Valid email is required.'); redirect($_POST['_redirect']); }
            jsonResponse(['error' => 'Valid email is required.'], 422);
        }

        $stmt = getDB()->prepare('SELECT id FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            getDB()->prepare(
                'INSERT INTO password_resets (user_id, email, token, expires_at) VALUES (?, ?, ?, ?)'
            )->execute([$user['id'], $email, $token, $expires]);

            $resetUrl = APP_URL . '/pages/auth/reset-password.php?token=' . $token;
            $html = '<p>Click the link below to reset your password (expires in 1 hour):</p><p><a href="' . $resetUrl . '">' . $resetUrl . '</a></p>';
            sendMail($email, APP_NAME . ' — Password Reset', $html);
        }

        $msg = 'If an account exists for that email, a reset link has been sent.';
        if (isset($_POST['_redirect'])) { flashMessage('info', $msg); redirect($_POST['_redirect']); }
        jsonResponse(['success' => true, 'message' => $msg]);
        break;

    // -------------------------------------------------------
    case 'reset_password':
        if ($method !== 'POST') { jsonResponse(['error' => 'Method not allowed'], 405); }
        if (!verifyCsrf())      { jsonResponse(['error' => 'Invalid CSRF token'], 403); }

        $token     = post('token', '');
        $password  = post('password', '');
        $password2 = post('password_confirm', '');

        $pwdError = validatePasswordStrength($password);
        if ($pwdError || $password !== $password2) {
            $errMsg = $pwdError ?? 'Passwords do not match.';
            if (isset($_POST['_redirect'])) { flashMessage('danger', $errMsg); redirect($_POST['_redirect']); }
            jsonResponse(['error' => $errMsg], 422);
        }

        $stmt = getDB()->prepare('SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1');
        $stmt->execute([$token]);
        $reset = $stmt->fetch();

        if (!$reset) {
            if (isset($_POST['_redirect'])) { flashMessage('danger', 'Invalid or expired reset token.'); redirect($_POST['_redirect']); }
            jsonResponse(['error' => 'Invalid or expired token.'], 400);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        getDB()->prepare('UPDATE users SET password_hash = ? WHERE email = ?')->execute([$hash, $reset['email']]);
        getDB()->prepare('UPDATE password_resets SET used = 1 WHERE id = ?')->execute([$reset['id']]);

        if (isset($_POST['_redirect'])) { flashMessage('success', 'Password reset successfully. Please login.'); redirect('/pages/auth/login.php'); }
        jsonResponse(['success' => true]);
        break;

    // -------------------------------------------------------
    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}

