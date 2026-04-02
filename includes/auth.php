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
 * Check if logged-in user is an admin or super_admin
 */
function isAdmin(): bool {
    return isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'super_admin']);
}

/**
 * Check if logged-in user is a supplier
 */
function isSupplier(): bool {
    return isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['supplier', 'admin', 'super_admin']);
}

/**
 * Check if the current user has a specific role (or one of several roles)
 * @param string|string[] $role
 */
function hasRole(string|array $role): bool {
    $current = $_SESSION['user_role'] ?? '';
    $roles = is_array($role) ? $role : [$role];
    return in_array($current, $roles, true);
}

/**
 * Require user to have one of the given roles; redirect/403 otherwise
 * @param string[] $roles
 */
function requireRole(array $roles): void {
    if (!isLoggedIn()) {
        redirect('/pages/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
    if (!hasRole($roles)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><title>Access Denied</title>'
           . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"></head>'
           . '<body><div class="container py-5 text-center">'
           . '<h2 class="text-danger">403 — Access Denied</h2>'
           . '<p>You do not have permission to view this page.</p>'
           . '<a href="/" class="btn btn-primary">Go Home</a>'
           . '</div></body></html>';
        exit;
    }
}

/**
 * Shortcut: require super_admin role
 */
function requireSuperAdmin(): void {
    requireRole(['super_admin']);
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
