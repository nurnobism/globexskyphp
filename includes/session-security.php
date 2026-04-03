<?php
/**
 * Secure Session Manager
 *
 * Enhanced session management with fingerprinting, lifetime checks,
 * flash messages and hardened cookie settings.
 */

class SecureSession
{
    private const SESSION_NAME    = 'GLOBEXSKY_SID';
    private const MAX_LIFETIME    = 3600;  // seconds
    private const REGEN_INTERVAL  = 300;   // regenerate ID every 5 min

    // -----------------------------------------------------------------------
    // Core lifecycle
    // -----------------------------------------------------------------------

    /**
     * Start the session with hardened settings.
     *
     * @param array $options Override defaults:
     *   'name'      string  Session name
     *   'lifetime'  int     Cookie lifetime in seconds (0 = browser session)
     *   'secure'    bool    Restrict cookie to HTTPS
     *   'samesite'  string  SameSite policy ('Strict'|'Lax'|'None')
     */
    public static function start(array $options = []): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $name     = $options['name']    ?? self::SESSION_NAME;
        $lifetime = $options['lifetime'] ?? 0;
        $secure   = $options['secure']  ?? (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $samesite = $options['samesite'] ?? 'Strict';

        session_name($name);

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => $samesite,
        ]);

        ini_set('session.gc_maxlifetime', (string) self::MAX_LIFETIME);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');

        session_start();

        // Periodic ID regeneration to prevent session fixation
        $now = time();
        if (!isset($_SESSION['_last_regen']) || ($now - $_SESSION['_last_regen']) > self::REGEN_INTERVAL) {
            session_regenerate_id(true);
            $_SESSION['_last_regen'] = $now;
        }

        // Record last activity
        $_SESSION['_last_activity'] = $now;
    }

    /**
     * Regenerate the session ID immediately.
     * Call after login or any privilege elevation.
     */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
        $_SESSION['_last_regen'] = time();
    }

    /**
     * Validate the session: checks fingerprint and expiry.
     */
    public static function validate(): bool
    {
        if (!self::checkFingerprint()) {
            self::destroy();
            return false;
        }
        if (self::isExpired()) {
            self::destroy();
            return false;
        }
        // Refresh last-activity on every valid request
        $_SESSION['_last_activity'] = time();
        return true;
    }

    /**
     * Completely destroy the session and clear the cookie.
     */
    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            $params   = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 3600,
                    'path'     => $params['path'],
                    'domain'   => $params['domain'],
                    'secure'   => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Strict',
                ]
            );
            session_destroy();
        }
    }

    // -----------------------------------------------------------------------
    // Fingerprinting
    // -----------------------------------------------------------------------

    /**
     * Store a browser fingerprint in the session.
     * Call once, immediately after session_start on login.
     */
    public static function setFingerprint(): void
    {
        $_SESSION['_fingerprint'] = self::buildFingerprint();
    }

    /**
     * Return true if the stored fingerprint matches the current request.
     * Returns true if no fingerprint is stored yet (first request after login).
     */
    public static function checkFingerprint(): bool
    {
        if (!isset($_SESSION['_fingerprint'])) {
            return true;
        }
        return hash_equals($_SESSION['_fingerprint'], self::buildFingerprint());
    }

    // -----------------------------------------------------------------------
    // Expiry
    // -----------------------------------------------------------------------

    /**
     * Check whether the session has exceeded its maximum idle lifetime.
     */
    public static function isExpired(int $maxLifetime = self::MAX_LIFETIME): bool
    {
        $last = $_SESSION['_last_activity'] ?? 0;
        return (time() - $last) > $maxLifetime;
    }

    /**
     * Return the UNIX timestamp of the last recorded activity.
     */
    public static function getLastActivity(): int
    {
        return $_SESSION['_last_activity'] ?? 0;
    }

    // -----------------------------------------------------------------------
    // Flash messages
    // -----------------------------------------------------------------------

    /**
     * Store a flash message (available only for the next request).
     */
    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * Retrieve and remove a flash message.
     * Returns null if the key does not exist.
     */
    public static function getFlash(string $key): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Build a fingerprint string from stable browser characteristics.
     * Handles both IPv4 and IPv6 addresses.
     */
    private static function buildFingerprint(): string
    {
        $ua  = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $raw = $_SERVER['REMOTE_ADDR']     ?? '';

        if (str_contains($raw, ':')) {
            // IPv6 — keep the first 4 groups (64-bit prefix) for NAT tolerance
            $groups = explode(':', $raw);
            $ip     = implode(':', array_slice($groups, 0, 4));
        } else {
            // IPv4 — keep the first 3 octets (allows last-octet churn in NAT pools)
            $ip = implode('.', array_slice(explode('.', $raw), 0, 3));
        }

        return hash('sha256', $ua . '|' . $ip);
    }
}
