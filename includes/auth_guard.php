<?php
/**
 * includes/auth_guard.php — Role-Based Access Control (RBAC) Guards
 *
 * Provides additional RBAC helpers.
 * Core auth functions (isLoggedIn, requireLogin, requireRole, hasRole, etc.)
 * are defined in includes/auth.php which is loaded first via middleware.php.
 *
 * This file adds:
 *   requireAuth()             — alias for requireLogin() with URI redirect support
 *   generateCSRFToken()       — alias for csrfToken() defined in functions.php
 *   validateCSRFToken($token) — alias for verifyCsrf() with explicit token arg
 */

if (!function_exists('requireAuth')) {
    /**
     * Redirect to login page if the user is not authenticated.
     * Alias for requireLogin() that explicitly passes the current URI as redirect.
     */
    function requireAuth(string $redirectBack = ''): void
    {
        if (!isLoggedIn()) {
            $back = $redirectBack ?: $_SERVER['REQUEST_URI'];
            redirect('/pages/auth/login.php?redirect=' . urlencode($back));
        }
    }
}

if (!function_exists('generateCSRFToken')) {
    /**
     * Generate a CSRF token, store it in the session, and return it.
     * Alias for csrfToken() defined in functions.php.
     */
    function generateCSRFToken(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }
}

if (!function_exists('validateCSRFToken')) {
    /**
     * Validate a submitted CSRF token against the one stored in the session.
     */
    function validateCSRFToken(string $token): bool
    {
        return isset($_SESSION['_csrf_token'])
            && hash_equals($_SESSION['_csrf_token'], $token);
    }
}

