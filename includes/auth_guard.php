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

if (!function_exists('requireKycApproved')) {
    /**
     * Require KYC approval for the current user.
     * Loads kyc.php if needed, then redirects if status != approved.
     */
    function requireKycApproved(): void {
        if (!isLoggedIn()) {
            redirect('/pages/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        }
        if (!function_exists('getKycStatus')) {
            $f = __DIR__ . '/kyc.php';
            if (file_exists($f)) require_once $f;
        }
        if (function_exists('getKycStatus')) {
            $status = getKycStatus((int)$_SESSION['user_id']);
            if ($status !== 'approved') {
                redirect('/pages/account/kyc.php?required=1');
            }
        }
    }
}

if (!function_exists('requireKycForSellers')) {
    /**
     * Require KYC for supplier/carrier roles when the system setting is enabled.
     */
    function requireKycForSellers(): void {
        if (!isLoggedIn()) return;
        $role = $_SESSION['user_role'] ?? '';
        if (!in_array($role, ['supplier', 'carrier'], true)) return;
        if (!function_exists('getSystemSetting')) {
            $f = __DIR__ . '/admin_permissions.php';
            if (file_exists($f)) require_once $f;
        }
        if (function_exists('getSystemSetting') && getSystemSetting('kyc_required_for_sellers') !== '1') return;
        if (!function_exists('getKycStatus')) {
            $f = __DIR__ . '/kyc.php';
            if (file_exists($f)) require_once $f;
        }
        if (function_exists('getKycStatus')) {
            $status = getKycStatus((int)$_SESSION['user_id']);
            if ($status !== 'approved') {
                redirect('/pages/account/kyc.php?required=1&reason=seller_kyc');
            }
        }
    }
}

