<?php
// includes/functions.php — Helper Functions

function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function redirect(string $url, int $code = 302): never {
    http_response_code($code);
    header("Location: $url");
    exit;
}

function json_response(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function formatCurrency(float $amount, string $currency = 'USD'): string {
    $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'BDT' => '৳', 'CNY' => '¥', 'INR' => '₹', 'AED' => 'د.إ'];
    $symbol = $symbols[$currency] ?? $currency . ' ';
    return $symbol . number_format($amount, 2);
}

function formatDate(string $date, string $format = 'M d, Y'): string {
    return date($format, strtotime($date));
}

function formatDateTime(string $datetime): string {
    return date('M d, Y H:i', strtotime($datetime));
}

function timeAgo(string $datetime): string {
    $now  = new DateTime();
    $then = new DateTime($datetime);
    $diff = $now->diff($then);
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'just now';
}

function generateToken(int $length = 32): string {
    return bin2hex(random_bytes($length / 2));
}

function generateSlug(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\-]/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

function truncate(string $text, int $length = 100, string $suffix = '...'): string {
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length) . $suffix;
}

function uploadFile(array $file, string $folder = 'general', array $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp']): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload error: ' . $file['error']];
    }
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return ['success' => false, 'error' => 'File too large (max 10MB)'];
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'error' => 'File type not allowed'];
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = generateToken(16) . '.' . strtolower($ext);
    $uploadDir = UPLOAD_PATH . $folder . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $destination = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'error' => 'Failed to move uploaded file'];
    }
    return ['success' => true, 'filename' => $filename, 'url' => UPLOAD_URL . $folder . '/' . $filename];
}

function paginate(int $total, int $perPage, int $currentPage): array {
    $totalPages = (int)ceil($total / $perPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    return [
        'total'        => $total,
        'per_page'     => $perPage,
        'current_page' => $currentPage,
        'total_pages'  => $totalPages,
        'offset'       => ($currentPage - 1) * $perPage,
        'has_prev'     => $currentPage > 1,
        'has_next'     => $currentPage < $totalPages,
    ];
}

function getCurrentUser(): ?array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return $_SESSION['user'] ?? null;
}

function isLoggedIn(): bool {
    return getCurrentUser() !== null;
}

function isAdmin(): bool {
    $user = getCurrentUser();
    return $user && in_array($user['role'], ['admin', 'superadmin']);
}

function isSupplier(): bool {
    $user = getCurrentUser();
    return $user && in_array($user['role'], ['supplier', 'verified_supplier']);
}

function requireLogin(string $redirectTo = '/pages/auth/login.php'): void {
    if (!isLoggedIn()) {
        redirect(APP_URL . $redirectTo . '?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        redirect(APP_URL . '/?error=unauthorized');
    }
}

function csrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken(CSRF_TOKEN_LENGTH);
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

function getSettings(): array {
    static $settings = null;
    if ($settings === null) {
        try {
            $pdo = getDB();
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM platform_settings");
            $settings = [];
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            $settings = [];
        }
    }
    return $settings;
}

function getSetting(string $key, $default = null) {
    $settings = getSettings();
    return $settings[$key] ?? $default;
}

function isFeatureEnabled(string $feature): bool {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT is_enabled FROM feature_toggles WHERE feature_key = ?");
        $stmt->execute([$feature]);
        $row = $stmt->fetch();
        return $row ? (bool)$row['is_enabled'] : false;
    } catch (Exception $e) {
        return false;
    }
}

function getCartCount(): int {
    if (!isLoggedIn()) {
        return isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
    }
    try {
        $pdo = getDB();
        $user = getCurrentUser();
        $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart_items ci JOIN cart c ON ci.cart_id = c.id WHERE c.user_id = ?");
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();
        return (int)($row['total'] ?? 0);
    } catch (Exception $e) {
        return 0;
    }
}

function getNotificationCount(): int {
    if (!isLoggedIn()) return 0;
    try {
        $pdo = getDB();
        $user = getCurrentUser();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user['id']]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function t(string $key, string $lang = ''): string {
    static $translations = [];
    if (!$lang) {
        $lang = $_SESSION['lang'] ?? APP_LOCALE;
    }
    if (!isset($translations[$lang])) {
        $file = __DIR__ . '/../locales/' . $lang . '.json';
        if (file_exists($file)) {
            $translations[$lang] = json_decode(file_get_contents($file), true) ?? [];
        } else {
            $translations[$lang] = [];
        }
    }
    return $translations[$lang][$key] ?? $key;
}

function flashMessage(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlashMessage(): ?array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function renderFlash(): string {
    $flash = getFlashMessage();
    if (!$flash) return '';
    $type = $flash['type'] === 'error' ? 'danger' : htmlspecialchars($flash['type']);
    $msg  = htmlspecialchars($flash['message']);
    return "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
        {$msg}
        <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
    </div>";
}

function ratePerKg(string $category): float {
    $rates = [
        'smartphones'    => 50.0,
        'laptops'        => 60.0,
        'clothes'        => 25.0,
        'jewelry'        => 80.0,
        'electronics'    => 55.0,
        'accessories'    => 35.0,
        'home'           => 30.0,
        'default'        => 40.0,
    ];
    return $rates[strtolower($category)] ?? $rates['default'];
}

function calculateCommission(float $orderValue, string $category = 'general'): float {
    $categoryRates = ['electronics' => 0.07, 'fashion' => 0.10, 'general' => 0.05];
    $tierRates     = [10000 => 0.02, 5000 => 0.03, 1000 => 0.04, 0 => $categoryRates[$category] ?? 0.05];

    foreach ($tierRates as $threshold => $rate) {
        if ($orderValue >= $threshold) {
            $commission = $orderValue * $rate;
            return max(5.0, $commission); // minimum $5
        }
    }
    return max(5.0, $orderValue * 0.05);
}

function logActivity(int $userId, string $action, string $description, string $ipAddress = ''): void {
    try {
        $pdo = getDB();
        $ip  = $ipAddress ?: ($_SERVER['REMOTE_ADDR'] ?? '');
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $action, $description, $ip]);
    } catch (Exception $e) {
        // Silently fail
    }
}
