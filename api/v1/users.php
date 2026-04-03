<?php
/**
 * api/v1/users.php — Users API Resource
 *
 * Actions: profile, update_profile, public_profile, register, login, refresh_token
 */
if (!defined('API_RESOURCE')) {
    require_once __DIR__ . '/gateway.php';
    exit;
}

require_once __DIR__ . '/../../includes/api-response.php';

$db     = getDB();
$action = API_ACTION ?: ($_GET['action'] ?? 'profile');
$apiKey = API_KEY_ROW;

$elapsed = fn() => (int)((microtime(true) - API_START_TIME) * 1000);

switch ($action) {
    // ── GET profile ───────────────────────────────────────────
    case 'profile':
        $userId = (int)$apiKey['user_id'];
        $stmt   = $db->prepare(
            'SELECT id, email, first_name, last_name, role, phone, avatar_url, created_at FROM users WHERE id = ?'
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            apiNotFound('User');
        }
        logApiRequest((int)$apiKey['id'], $userId, 'GET', 'users/profile', 200, $elapsed());
        apiSuccess($user, null, 200, getRateLimit($apiKey));
        break;

    // ── PUT update_profile ────────────────────────────────────
    case 'update_profile':
        $userId = (int)$apiKey['user_id'];
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $allowed = ['first_name', 'last_name', 'phone'];
        $sets    = [];
        $vals    = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $body)) {
                $sets[] = "$field = ?";
                $vals[] = htmlspecialchars(strip_tags((string)$body[$field]), ENT_QUOTES, 'UTF-8');
            }
        }
        if (!$sets) {
            apiError('No updatable fields provided.', 400);
        }
        $vals[] = $userId;
        $db->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
        logApiRequest((int)$apiKey['id'], $userId, 'PUT', 'users/update_profile', 200, $elapsed());
        apiSuccess(['message' => 'Profile updated.'], null, 200, getRateLimit($apiKey));
        break;

    // ── GET public_profile ────────────────────────────────────
    case 'public_profile':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            apiError('User ID required.', 400);
        }
        $stmt = $db->prepare(
            'SELECT id, first_name, last_name, role, avatar_url, created_at FROM users WHERE id = ?'
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            apiNotFound('User');
        }
        if ($apiKey) {
            logApiRequest((int)$apiKey['id'], (int)$apiKey['user_id'], 'GET', 'users/public_profile', 200, $elapsed());
        }
        apiSuccess($user, null, 200, $apiKey ? getRateLimit($apiKey) : null);
        break;

    // ── POST register ─────────────────────────────────────────
    case 'register':
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $errors = [];
        if (empty($body['email'])) {
            $errors['email']    = 'Email is required.';
        }
        if (empty($body['password'])) {
            $errors['password'] = 'Password is required.';
        }
        if (strlen($body['password'] ?? '') < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }
        if ($errors) {
            apiValidationError($errors);
        }
        $email = strtolower(trim($body['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            apiValidationError(['email' => 'Invalid email address.']);
        }
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetchColumn()) {
            apiError([['code' => 'EMAIL_EXISTS', 'message' => 'Email already registered.']], 409);
        }
        $hash = password_hash($body['password'], PASSWORD_DEFAULT);
        $db->prepare(
            'INSERT INTO users (email, password, first_name, last_name, role, created_at)
             VALUES (?, ?, ?, ?, "buyer", NOW())'
        )->execute([$email, $hash, $body['first_name'] ?? '', $body['last_name'] ?? '']);
        $newId = (int)$db->lastInsertId();
        apiSuccess(['id' => $newId, 'message' => 'Registration successful.'], null, 201);
        break;

    // ── POST login ────────────────────────────────────────────
    case 'login':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($body['email']) || empty($body['password'])) {
            apiValidationError(['email' => 'Email and password are required.']);
        }
        $stmt = $db->prepare('SELECT id, email, password, role, first_name FROM users WHERE email = ?');
        $stmt->execute([strtolower(trim($body['email']))]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !password_verify($body['password'], $user['password'])) {
            apiUnauthorized('Invalid email or password.');
        }
        // Return a temporary API key token (simplified JWT-like approach)
        $token = bin2hex(random_bytes(32));
        // Store as a test-environment API key
        $db->prepare(
            'INSERT INTO api_keys (user_id, name, api_key, key_prefix, environment, rate_limit_per_day)
             VALUES (?, "Login Token", ?, ?, "live", 1000)
             ON DUPLICATE KEY UPDATE last_used_at = NOW()'
        )->execute([$user['id'], $token, 'gsk_live_' . substr($token, 0, 8) . '...']);
        apiSuccess([
            'token'      => $token,
            'user_id'    => $user['id'],
            'role'       => $user['role'],
            'first_name' => $user['first_name'],
        ], null, 200);
        break;

    // ── POST refresh_token ────────────────────────────────────
    case 'refresh_token':
        // Simplified: validate existing token, issue new one
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $token = $body['token'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? '');
        if (!$token) {
            apiUnauthorized('Token required.');
        }
        $stmt = $db->prepare('SELECT * FROM api_keys WHERE api_key = ? AND is_active = 1');
        $stmt->execute([$token]);
        $keyRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$keyRow) {
            apiUnauthorized('Invalid or expired token.');
        }
        $newToken = bin2hex(random_bytes(32));
        $db->prepare('UPDATE api_keys SET api_key = ?, last_used_at = NOW() WHERE id = ?')->execute([$newToken, $keyRow['id']]);
        apiSuccess(['token' => $newToken], null, 200);
        break;

    default:
        if ($apiKey) {
            logApiRequest((int)$apiKey['id'], (int)$apiKey['user_id'], $_SERVER['REQUEST_METHOD'], "users/$action", 404, $elapsed());
        }
        apiNotFound("Action '$action'");
}
