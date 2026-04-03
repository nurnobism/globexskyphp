<?php
/**
 * includes/api-auth.php — API Authentication & Authorization
 *
 * Handles API key and JWT Bearer token validation for the REST API.
 * Rate limits by plan tier (Free / Pro / Enterprise / Admin).
 *
 * API Key Format: gsk_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx (32-char hex)
 * Test Key Format: gsk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
 */

/**
 * Authenticate an incoming API request.
 *
 * Checks for:
 *  1. X-API-Key header
 *  2. Authorization: Bearer <token> header
 *
 * Returns array with api_key row + user row on success, or sends a 401 JSON response.
 */
function authenticateApiRequest(): array
{
    $db = getDB();

    // 1. Try API key header
    $apiKeyValue = $_SERVER['HTTP_X_API_KEY'] ?? '';

    // 2. Try Bearer token header
    if (!$apiKeyValue) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            $apiKeyValue = trim(substr($authHeader, 7));
        }
    }

    if (!$apiKeyValue) {
        apiUnauthorized('API key required. Provide X-API-Key header or Authorization: Bearer <key>.');
    }

    // Look up the key (store the raw value, compare directly — key is hashed on storage but
    // we compare the hash stored in DB; if plain-text storage, compare directly)
    $stmt = $db->prepare(
        'SELECT ak.*, u.role AS user_role, u.email AS user_email
         FROM api_keys ak
         JOIN users u ON u.id = ak.user_id
         WHERE ak.api_key = ? AND ak.is_active = 1 AND ak.revoked_at IS NULL'
    );
    $stmt->execute([$apiKeyValue]);
    $keyRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$keyRow) {
        apiUnauthorized('Invalid or revoked API key.');
    }

    // IP whitelist check
    if (!empty($keyRow['ip_whitelist'])) {
        $allowed = array_map('trim', explode(',', $keyRow['ip_whitelist']));
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!in_array($clientIp, $allowed, true)) {
            apiForbidden('Your IP address is not whitelisted for this API key.');
        }
    }

    // Rate limit check
    checkRateLimit($keyRow);

    // Update last_used_at and increment counters
    $db->prepare(
        'UPDATE api_keys SET last_used_at = NOW(),
         requests_today = requests_today + 1,
         requests_month = requests_month + 1
         WHERE id = ?'
    )->execute([$keyRow['id']]);

    return $keyRow;
}

/**
 * Check rate limit for the given API key row.
 * Sends 429 JSON response if limit exceeded.
 */
function checkRateLimit(array $keyRow): void
{
    $limit = (int)$keyRow['rate_limit_per_day'];
    // Admin keys are unlimited
    if ($keyRow['user_role'] === 'super_admin' || $keyRow['user_role'] === 'admin') {
        return;
    }
    if ($limit > 0 && (int)$keyRow['requests_today'] >= $limit) {
        // Reset time: midnight UTC
        $resetTime = strtotime('tomorrow midnight');
        apiRateLimited($resetTime - time());
    }
}

/**
 * Get remaining rate limit for an API key.
 */
function getRateLimit(array $keyRow): array
{
    $limit     = (int)$keyRow['rate_limit_per_day'];
    $used      = (int)$keyRow['requests_today'];
    $remaining = max(0, $limit - $used);
    $reset     = strtotime('tomorrow midnight');

    return [
        'limit'     => $limit,
        'remaining' => $remaining,
        'reset'     => $reset,
    ];
}

/**
 * Generate a new API key for a user.
 *
 * @param int    $userId
 * @param string $name         Human-readable label
 * @param string $environment  'live' or 'test'
 * @param array  $permissions  Resource permission list
 * @param string $ipWhitelist  Comma-separated IPs (optional)
 * @return array               ['key' => '...full key...', 'prefix' => '...', 'id' => ...]
 */
function generateApiKey(int $userId, string $name, string $environment = 'live', array $permissions = [], string $ipWhitelist = ''): array
{
    $db        = getDB();
    $hex       = bin2hex(random_bytes(16));           // 32 hex chars
    $prefix    = 'gsk_' . $environment . '_';
    $fullKey   = $prefix . $hex;
    $keyPrefix = $prefix . substr($hex, 0, 8) . '...';

    // Default rate limit based on role
    $stmt = $db->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $role     = $stmt->fetchColumn();
    $rateLimit = match ($role) {
        'super_admin', 'admin' => 0,        // unlimited (0 = no limit enforced)
        'supplier'             => 5000,
        default                => 100,
    };

    $stmt = $db->prepare(
        'INSERT INTO api_keys (user_id, name, api_key, key_prefix, environment, permissions, ip_whitelist, rate_limit_per_day)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $userId,
        $name,
        $fullKey,
        $keyPrefix,
        $environment,
        json_encode($permissions),
        $ipWhitelist,
        $rateLimit,
    ]);

    return [
        'id'     => (int)$db->lastInsertId(),
        'key'    => $fullKey,     // shown ONCE
        'prefix' => $keyPrefix,
    ];
}

/**
 * Revoke an API key by ID (sets revoked_at and is_active = 0).
 */
function revokeApiKey(int $keyId, int $userId): bool
{
    $db   = getDB();
    $stmt = $db->prepare(
        'UPDATE api_keys SET is_active = 0, revoked_at = NOW()
         WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$keyId, $userId]);
    return $stmt->rowCount() > 0;
}

/**
 * Rotate an API key — generate new key, schedule old key for deactivation after 24h.
 *
 * @return array New key details
 */
function rotateApiKey(int $keyId, int $userId): array
{
    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM api_keys WHERE id = ? AND user_id = ?');
    $stmt->execute([$keyId, $userId]);
    $old = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$old) {
        return [];
    }

    $newKey = generateApiKey(
        $userId,
        $old['name'] . ' (rotated)',
        $old['environment'],
        json_decode($old['permissions'] ?? '[]', true),
        $old['ip_whitelist'] ?? ''
    );

    // Revoke old key after 24h grace period (we just set revoked_at to +24h)
    $db->prepare(
        'UPDATE api_keys SET revoked_at = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id = ?'
    )->execute([$keyId]);

    return $newKey;
}

/**
 * Check whether an API key has a specific permission.
 */
function checkApiPermission(int $keyId, string $resource, string $action): bool
{
    $db   = getDB();
    $stmt = $db->prepare('SELECT permissions FROM api_keys WHERE id = ? AND is_active = 1');
    $stmt->execute([$keyId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return false;
    }
    $permissions = json_decode($row['permissions'] ?? '[]', true);
    // Empty = all allowed; otherwise check resource list
    if (empty($permissions)) {
        return true;
    }
    return in_array($resource, $permissions, true) || in_array("$resource.$action", $permissions, true);
}

/**
 * Log an API request.
 */
function logApiRequest(int $apiKeyId, int $userId, string $method, string $endpoint, int $statusCode, int $responseTimeMs, ?string $params = null, ?string $requestBody = null): void
{
    try {
        $db = getDB();
        $db->prepare(
            'INSERT INTO api_request_logs
             (api_key_id, user_id, method, endpoint, params, request_body, response_code, response_time_ms, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $apiKeyId,
            $userId,
            $method,
            $endpoint,
            $params,
            $requestBody,
            $statusCode,
            $responseTimeMs,
            $_SERVER['REMOTE_ADDR'] ?? '',
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    } catch (PDOException $e) {
        // Logging must not break API responses
    }
}

/**
 * Get API usage stats for a key over a period.
 *
 * @param int    $apiKeyId
 * @param string $period  'today' | 'week' | 'month'
 */
function getApiUsage(int $apiKeyId, string $period = 'today'): array
{
    $db   = getDB();
    $since = match ($period) {
        'week'  => 'DATE_SUB(NOW(), INTERVAL 7 DAY)',
        'month' => 'DATE_SUB(NOW(), INTERVAL 30 DAY)',
        default => 'CURDATE()',
    };

    $stmt = $db->prepare(
        "SELECT COUNT(*) AS total_requests,
                SUM(CASE WHEN response_code < 400 THEN 1 ELSE 0 END) AS success_requests,
                AVG(response_time_ms) AS avg_response_ms
         FROM api_request_logs
         WHERE api_key_id = ? AND created_at >= $since"
    );
    $stmt->execute([$apiKeyId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}
