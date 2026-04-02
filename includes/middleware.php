<?php
// includes/middleware.php — Auth Check, Admin Check, CSRF, Rate Limiting

require_once __DIR__ . '/functions.php';

function authMiddleware(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isLoggedIn()) {
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            json_response(['error' => 'Unauthorized', 'code' => 401], 401);
        }
        redirect(APP_URL . '/pages/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    }
}

function adminMiddleware(): void {
    authMiddleware();
    if (!isAdmin()) {
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            json_response(['error' => 'Forbidden', 'code' => 403], 403);
        }
        redirect(APP_URL . '/?error=forbidden');
    }
}

function csrfMiddleware(): void {
    if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'PATCH', 'DELETE'])) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!verifyCsrf($token)) {
            if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
                json_response(['error' => 'CSRF token mismatch', 'code' => 419], 419);
            }
            http_response_code(419);
            die('CSRF token mismatch. Please go back and try again.');
        }
    }
}

function rateLimitMiddleware(string $key, int $limit = 60, int $window = 60): void {
    $ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $cacheKey = 'rate_' . $key . '_' . md5($ip);
    $file    = sys_get_temp_dir() . '/' . $cacheKey;
    $now     = time();
    $count   = 1;
    $windowStart = $now;

    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if ($data && ($now - $data['start']) < $window) {
            $count = $data['count'] + 1;
            $windowStart = $data['start'];
        }
    }

    file_put_contents($file, json_encode(['count' => $count, 'start' => $windowStart]));

    if ($count > $limit) {
        http_response_code(429);
        header('Retry-After: ' . ($window - ($now - $windowStart)));
        json_response(['error' => 'Too many requests. Please slow down.', 'code' => 429], 429);
    }
}
