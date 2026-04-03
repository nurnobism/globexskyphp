<?php
/**
 * Security Headers Middleware
 * Sets comprehensive HTTP security headers on every response.
 */

/**
 * Generate a cryptographically secure CSP nonce for this request.
 * The nonce is stored in $_SERVER so it can be used in templates.
 */
function generateCspNonce(): string
{
    if (!isset($_SERVER['CSP_NONCE'])) {
        $_SERVER['CSP_NONCE'] = base64_encode(random_bytes(16));
    }
    return $_SERVER['CSP_NONCE'];
}

/**
 * Apply all security headers.
 * Call this from header.php before any output.
 *
 * @param array $options Optional overrides:
 *   - 'csp_extra'         string  Additional CSP directives to append
 *   - 'frame_options'     string  X-Frame-Options value (default 'DENY')
 *   - 'hsts'              bool    Whether to send HSTS header (default true)
 *   - 'permissions_policy' string  Full Permissions-Policy value override
 */
function applySecurityHeaders(array $options = []): void
{
    $nonce = generateCspNonce();

    // Content-Security-Policy
    $cspDirectives = [
        "default-src 'self'",
        "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
        "style-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://fonts.googleapis.com https://cdnjs.cloudflare.com",
        "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com",
        "img-src 'self' data: https:",
        "connect-src 'self'",
        "frame-src 'none'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "upgrade-insecure-requests",
    ];
    if (!empty($options['csp_extra'])) {
        $cspDirectives[] = $options['csp_extra'];
    }
    header("Content-Security-Policy: " . implode('; ', $cspDirectives));

    // Prevent MIME-type sniffing
    header('X-Content-Type-Options: nosniff');

    // Clickjacking protection
    $frameOptions = $options['frame_options'] ?? 'DENY';
    header("X-Frame-Options: {$frameOptions}");

    // Legacy XSS filter (kept for older browsers)
    header('X-XSS-Protection: 1; mode=block');

    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Permissions Policy — disable sensitive APIs by default
    $permissionsPolicy = $options['permissions_policy']
        ?? 'camera=(), microphone=(), geolocation=(), payment=(), usb=()';
    header("Permissions-Policy: {$permissionsPolicy}");

    // HSTS — only send over HTTPS
    $sendHsts = $options['hsts'] ?? true;
    if ($sendHsts && (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }

    // Prevent caching of sensitive pages
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}
