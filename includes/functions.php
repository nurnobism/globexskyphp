<?php
/**
 * Helper Functions
 */

/**
 * Sanitize output for HTML display
 */
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Generate a CSRF token
 */
function csrfToken(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Output a hidden CSRF input field
 */
function csrfField(): string {
    return '<input type="hidden" name="_csrf_token" value="' . e(csrfToken()) . '">';
}

/**
 * Verify CSRF token (returns true on valid, false on invalid)
 */
function verifyCsrf(): bool {
    $token = $_POST['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return isset($_SESSION['_csrf_token']) && hash_equals($_SESSION['_csrf_token'], $token);
}

/**
 * Format money amount
 */
function formatMoney(float $amount, string $currency = 'USD'): string {
    return '$' . number_format($amount, 2);
}

/**
 * Format datetime for display
 */
function formatDate(string $datetime, string $format = 'M j, Y'): string {
    if (empty($datetime)) return '—';
    return date($format, strtotime($datetime));
}

/**
 * Format datetime with time
 */
function formatDateTime(string $datetime): string {
    return formatDate($datetime, 'M j, Y g:i A');
}

/**
 * Get base URL path for assets
 */
function asset(string $path): string {
    return APP_URL . '/assets/' . ltrim($path, '/');
}

/**
 * Redirect to a URL
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * Return JSON response and exit
 */
function jsonResponse(mixed $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get value from $_GET with optional default
 */
function get(string $key, mixed $default = null): mixed {
    return $_GET[$key] ?? $default;
}

/**
 * Get value from $_POST with optional default
 */
function post(string $key, mixed $default = null): mixed {
    return $_POST[$key] ?? $default;
}

/**
 * Validate required fields in an array
 * Returns array of error messages
 */
function validateRequired(array $data, array $fields): array {
    $errors = [];
    foreach ($fields as $field => $label) {
        if (empty(trim($data[$field] ?? ''))) {
            $errors[$field] = "$label is required.";
        }
    }
    return $errors;
}

/**
 * Validate email format
 */
function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate a UUID v4
 */
function generateUuid(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Create a slug from a string
 */
function slugify(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    return strtolower($text);
}

/**
 * Paginate a query result
 * Returns ['data' => [...], 'total' => n, 'pages' => n, 'current' => n]
 */
function paginate(PDO $db, string $sql, array $params, int $page = 1, int $perPage = ITEMS_PER_PAGE): array {
    $countSql = 'SELECT COUNT(*) FROM (' . $sql . ') AS _count_query';
    $stmt = $db->prepare($countSql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $pages  = max(1, (int)ceil($total / $perPage));
    $offset = ($page - 1) * $perPage;

    $stmt = $db->prepare($sql . " LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $data = $stmt->fetchAll();

    return ['data' => $data, 'total' => $total, 'pages' => $pages, 'current' => $page];
}

/**
 * Flash message: set
 */
function flashMessage(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Flash message: get & clear
 */
function getFlashMessages(): array {
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/**
 * Handle file upload
 * Returns relative path on success, null on failure
 */
function uploadFile(array $file, string $subfolder = 'uploads'): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > UPLOAD_MAX_SIZE) return null;
    if (!in_array($file['type'], ALLOWED_IMAGE_TYPES)) return null;

    $dir = UPLOAD_DIR . $subfolder . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = generateUuid() . '.' . strtolower($ext);
    $dest     = $dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) return null;

    return 'assets/uploads/' . $subfolder . '/' . $filename;
}

/**
 * Calculate star rating display (returns integer 1-5)
 */
function starRating(float $rating): int {
    return max(1, min(5, (int)round($rating)));
}
