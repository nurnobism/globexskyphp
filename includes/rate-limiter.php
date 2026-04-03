<?php
/**
 * Rate Limiter Middleware
 *
 * File-based OR database-based rate limiter.
 * Works on shared hosting without Redis / Memcached.
 *
 * SQL migration (run once):
 * ------------------------------------------------------------
 * CREATE TABLE IF NOT EXISTS `rate_limits` (
 *   `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 *   `rate_key`        VARCHAR(255) NOT NULL,
 *   `attempts`        INT UNSIGNED NOT NULL DEFAULT 0,
 *   `last_attempt_at` DATETIME     NOT NULL,
 *   `expires_at`      DATETIME     NOT NULL,
 *   UNIQUE KEY `uq_rate_key` (`rate_key`),
 *   INDEX `idx_expires_at` (`expires_at`)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 * ------------------------------------------------------------
 *
 * Default limits:
 *   login          : 5 attempts  / 15 min  per IP
 *   api            : 60 requests / 1 min   per API key
 *   password_reset : 3 attempts  / 60 min  per email
 *   registration   : 3 attempts  / 60 min  per IP
 *   page           : 120 requests/ 1 min   per IP
 */

class RateLimiter
{
    private PDO $db;

    /** Default limits: [maxAttempts, decayMinutes] */
    public const LIMITS = [
        'login'          => [5,   15],
        'api'            => [60,  1],
        'password_reset' => [3,   60],
        'registration'   => [3,   60],
        'page'           => [120, 1],
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Check whether the key is currently allowed (not over limit).
     *
     * @param string $key          Unique key, e.g. "login:127.0.0.1"
     * @param int    $maxAttempts  Maximum number of attempts
     * @param int    $decayMinutes Window length in minutes
     */
    public function check(string $key, int $maxAttempts, int $decayMinutes): bool
    {
        return !$this->tooManyAttempts($key, $maxAttempts, $decayMinutes);
    }

    /**
     * Record one attempt for the key.
     */
    public function hit(string $key, int $decayMinutes = 15): void
    {
        $now       = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$decayMinutes} minutes"));

        $stmt = $this->db->prepare(
            "INSERT INTO rate_limits (rate_key, attempts, last_attempt_at, expires_at)
             VALUES (:key, 1, :now, :expires)
             ON DUPLICATE KEY UPDATE
                 attempts        = attempts + 1,
                 last_attempt_at = :now2,
                 expires_at      = :expires2"
        );
        $stmt->execute([
            ':key'     => $key,
            ':now'     => $now,
            ':expires' => $expiresAt,
            ':now2'    => $now,
            ':expires2'=> $expiresAt,
        ]);
    }

    /**
     * How many attempts remain before the key is throttled.
     */
    public function remaining(string $key, int $maxAttempts, int $decayMinutes): int
    {
        $row = $this->getRecord($key, $decayMinutes);
        if ($row === null) {
            return $maxAttempts;
        }
        return max(0, $maxAttempts - (int) $row['attempts']);
    }

    /**
     * Clear all attempts for a key (e.g. after successful login).
     */
    public function clear(string $key): void
    {
        $stmt = $this->db->prepare("DELETE FROM rate_limits WHERE rate_key = :key");
        $stmt->execute([':key' => $key]);
    }

    /**
     * Return true if the key has exceeded the limit within the decay window.
     */
    public function tooManyAttempts(string $key, int $maxAttempts, int $decayMinutes): bool
    {
        $row = $this->getRecord($key, $decayMinutes);
        if ($row === null) {
            return false;
        }
        return (int) $row['attempts'] >= $maxAttempts;
    }

    /**
     * Delete expired records older than the decay window (maintenance helper).
     */
    public function cleanExpired(): void
    {
        $this->db->exec("DELETE FROM rate_limits WHERE expires_at < NOW()");
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Fetch the current record for a key if it is still within the window.
     * Returns null if no record exists or the window has passed.
     */
    private function getRecord(string $key, int $decayMinutes): ?array
    {
        $windowStart = date('Y-m-d H:i:s', strtotime("-{$decayMinutes} minutes"));

        $stmt = $this->db->prepare(
            "SELECT attempts, last_attempt_at
               FROM rate_limits
              WHERE rate_key = :key
                AND last_attempt_at >= :window_start"
        );
        $stmt->execute([':key' => $key, ':window_start' => $windowStart]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
