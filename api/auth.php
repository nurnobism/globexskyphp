<?php
/**
 * api/auth.php — Authentication API
 * Actions: login, register, logout, forgot_password, reset_password
 */

require_once __DIR__ . '/../includes/middleware.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

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

        $stmt = getDB()->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            if (isset($_POST['_redirect'])) { flashMessage('danger', 'Invalid email or password.'); redirect($_POST['_redirect']); }
            jsonResponse(['error' => 'Invalid email or password.'], 401);
        }

        loginUser($user);
        getDB()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);

        $redirect = $_POST['redirect'] ?? '/';
        if (isset($_POST['_redirect'])) { flashMessage('success', 'Welcome back, ' . $user['first_name'] . '!'); redirect($redirect); }
        jsonResponse(['success' => true, 'user' => ['id' => $user['id'], 'email' => $user['email'], 'role' => $user['role']]]);
        break;

    // -------------------------------------------------------
    case 'register':
        if ($method !== 'POST') { jsonResponse(['error' => 'Method not allowed'], 405); }
        if (!verifyCsrf())      { jsonResponse(['error' => 'Invalid CSRF token'], 403); }

        $first_name   = trim(post('first_name', ''));
        $last_name    = trim(post('last_name', ''));
        $email        = trim(post('email', ''));
        $password     = post('password', '');
        $password2    = post('password_confirm', '');
        $role         = in_array(post('role'), ['buyer', 'supplier']) ? post('role') : 'buyer';

        $errors = [];
        if (empty($first_name)) $errors[] = 'First name is required.';
        if (empty($email) || !isValidEmail($email)) $errors[] = 'Valid email is required.';
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($password !== $password2) $errors[] = 'Passwords do not match.';

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
        $stmt = $db->prepare('INSERT INTO users (email, password_hash, first_name, last_name, role) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$email, $hash, $first_name, $last_name, $role]);
        $userId = (int)$db->lastInsertId();

        // Create supplier profile if role=supplier
        if ($role === 'supplier') {
            $companyName = trim(post('company_name', $first_name . "'s Company"));
            $slug = slugify($companyName) . '-' . $userId;
            $db->prepare('INSERT INTO suppliers (user_id, company_name, slug) VALUES (?, ?, ?)')->execute([$userId, $companyName, $slug]);
        }

        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        loginUser($stmt->fetch());

        if (isset($_POST['_redirect'])) { flashMessage('success', 'Welcome to GlobexSky!'); redirect('/'); }
        jsonResponse(['success' => true, 'user_id' => $userId]);
        break;

    // -------------------------------------------------------
    case 'logout':
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
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            getDB()->prepare('INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)')->execute([$email, $token, $expires]);

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

        if (strlen($password) < 8 || $password !== $password2) {
            if (isset($_POST['_redirect'])) { flashMessage('danger', 'Passwords do not match or are too short.'); redirect($_POST['_redirect']); }
            jsonResponse(['error' => 'Passwords do not match or are too short.'], 422);
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
