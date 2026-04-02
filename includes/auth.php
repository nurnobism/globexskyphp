<?php
/**
 * Authentication & Session Functions
 */

/**
 * Start session with secure settings
 */
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        // Regenerate session ID periodically to prevent fixation
        if (!isset($_SESSION['_last_regen']) || time() - $_SESSION['_last_regen'] > 300) {
            session_regenerate_id(true);
            $_SESSION['_last_regen'] = time();
        }
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Check if logged-in user is an admin
 */
function isAdmin(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Check if logged-in user is a supplier
 */
function isSupplier(): bool {
    return isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['supplier', 'admin']);
}

/**
 * Get current logged-in user data
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    try {
        $db   = getDB();
        $stmt = $db->prepare('SELECT id, email, first_name, last_name, role, avatar, is_verified FROM users WHERE id = ? AND is_active = 1');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Login a user (set session)
 */
function loginUser(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name']  = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    $_SESSION['user_role']  = $user['role'] ?? 'buyer';
    $_SESSION['_last_regen'] = time();
}

/**
 * Logout the current user
 */
function logoutUser(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

/**
 * Redirect to login if not authenticated
 */
function requireLogin(string $redirect = ''): void {
    if (!isLoggedIn()) {
        $back = $redirect ?: $_SERVER['REQUEST_URI'];
        header('Location: /pages/auth/login.php?redirect=' . urlencode($back));
        exit;
    }
}

/**
 * Redirect to homepage if not admin
 */
function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        header('Location: /?error=forbidden');
        exit;
    }
}
