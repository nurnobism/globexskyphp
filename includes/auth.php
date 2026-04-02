<?php
// includes/auth.php — Authentication Functions

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

function loginUser(string $email, string $password, bool $remember = false): array {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT id, name, email, password, role, is_active, is_verified, avatar FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([strtolower(trim($email))]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return ['success' => false, 'error' => 'Invalid email or password'];
    }

    if (!$user['is_verified']) {
        return ['success' => false, 'error' => 'Please verify your email before logging in'];
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'     => $user['id'],
        'name'   => $user['name'],
        'email'  => $user['email'],
        'role'   => $user['role'],
        'avatar' => $user['avatar'],
    ];

    // Update last login
    $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
    logActivity($user['id'], 'login', 'User logged in', $_SERVER['REMOTE_ADDR'] ?? '');

    if ($remember) {
        $token  = generateToken(64);
        $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
        $pdo->prepare("INSERT INTO user_sessions (user_id, token, expires_at) VALUES (?, ?, ?)")->execute([$user['id'], hash('sha256', $token), $expiry]);
        setcookie('remember_token', $token, strtotime('+30 days'), '/', '', true, true);
    }

    return ['success' => true, 'user' => $_SESSION['user']];
}

function registerUser(array $data): array {
    $pdo = getDB();
    $email = strtolower(trim($data['email']));

    // Check duplicate
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Email already registered'];
    }

    $password   = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
    $verifyToken = generateToken(32);
    $role        = $data['role'] ?? 'buyer';

    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, verify_token, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
    $stmt->execute([
        sanitize($data['name']),
        $email,
        $password,
        $role,
        $verifyToken,
    ]);

    $userId = (int)$pdo->lastInsertId();
    logActivity($userId, 'register', 'New user registered');

    return ['success' => true, 'user_id' => $userId, 'verify_token' => $verifyToken];
}

function logoutUser(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (isset($_SESSION['user'])) {
        logActivity($_SESSION['user']['id'], 'logout', 'User logged out');
    }
    if (isset($_COOKIE['remember_token'])) {
        $pdo = getDB();
        $hash = hash('sha256', $_COOKIE['remember_token']);
        $pdo->prepare("DELETE FROM user_sessions WHERE token = ?")->execute([$hash]);
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }
    session_destroy();
}

function checkRememberToken(): bool {
    if (isLoggedIn()) return true;
    if (!isset($_COOKIE['remember_token'])) return false;

    $pdo  = getDB();
    $hash = hash('sha256', $_COOKIE['remember_token']);
    $stmt = $pdo->prepare("SELECT us.user_id, u.name, u.email, u.role, u.avatar FROM user_sessions us JOIN users u ON us.user_id = u.id WHERE us.token = ? AND us.expires_at > NOW() AND u.is_active = 1");
    $stmt->execute([$hash]);
    $user = $stmt->fetch();

    if ($user) {
        session_start();
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'     => $user['user_id'],
            'name'   => $user['name'],
            'email'  => $user['email'],
            'role'   => $user['role'],
            'avatar' => $user['avatar'],
        ];
        return true;
    }
    return false;
}

function verifyEmail(string $token): bool {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT id FROM users WHERE verify_token = ? AND is_verified = 0");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) return false;

    $pdo->prepare("UPDATE users SET is_verified = 1, verify_token = NULL WHERE id = ?")->execute([$user['id']]);
    return true;
}
