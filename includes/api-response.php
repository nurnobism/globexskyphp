<?php
/**
 * includes/api-response.php — Standardized API Response Helpers
 *
 * All API responses follow this format:
 * {
 *   "success": true|false,
 *   "data": { ... },
 *   "meta": { "page": 1, "per_page": 25, "total": 150, "total_pages": 6 },
 *   "errors": [ { "code": "INVALID_PARAM", "message": "...", "field": "price" } ],
 *   "rate_limit": { "limit": 5000, "remaining": 4987, "reset": 1700000000 }
 * }
 */

/**
 * Set standard API response headers (JSON + CORS).
 */
function setApiHeaders(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization, X-CSRF-Token');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

/**
 * Send a successful API response.
 *
 * @param mixed       $data
 * @param array|null  $meta       Pagination metadata
 * @param int         $statusCode HTTP status code (default 200)
 * @param array|null  $rateLimit  Rate limit info
 */
function apiSuccess($data, ?array $meta = null, int $statusCode = 200, ?array $rateLimit = null): void
{
    http_response_code($statusCode);
    $response = ['success' => true, 'data' => $data];
    if ($meta !== null) {
        $response['meta'] = $meta;
    }
    if ($rateLimit !== null) {
        $response['rate_limit'] = $rateLimit;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Send an error API response.
 *
 * @param array $errors    Array of error objects or a single message string
 * @param int   $statusCode
 */
function apiError($errors, int $statusCode = 400): void
{
    http_response_code($statusCode);
    if (is_string($errors)) {
        $errors = [['code' => 'ERROR', 'message' => $errors]];
    }
    echo json_encode([
        'success' => false,
        'errors'  => $errors,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Send a paginated API response.
 *
 * @param array $data
 * @param int   $page
 * @param int   $perPage
 * @param int   $total
 * @param array|null $rateLimit
 */
function apiPaginated(array $data, int $page, int $perPage, int $total, ?array $rateLimit = null): void
{
    $totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;
    apiSuccess($data, [
        'page'        => $page,
        'per_page'    => $perPage,
        'total'       => $total,
        'total_pages' => $totalPages,
    ], 200, $rateLimit);
}

/**
 * Send a validation error response (422 Unprocessable Entity).
 *
 * @param array $errors  ['field' => 'message'] or array of error objects
 */
function apiValidationError(array $errors): void
{
    $formatted = [];
    foreach ($errors as $field => $message) {
        if (is_string($field)) {
            $formatted[] = ['code' => 'VALIDATION_ERROR', 'message' => $message, 'field' => $field];
        } else {
            $formatted[] = $message;
        }
    }
    apiError($formatted, 422);
}

/**
 * Send a 401 Unauthorized response.
 */
function apiUnauthorized(string $message = 'Authentication required.'): void
{
    apiError([['code' => 'UNAUTHORIZED', 'message' => $message]], 401);
}

/**
 * Send a 403 Forbidden response.
 */
function apiForbidden(string $message = 'Access forbidden.'): void
{
    apiError([['code' => 'FORBIDDEN', 'message' => $message]], 403);
}

/**
 * Send a 404 Not Found response.
 */
function apiNotFound(string $resource = 'Resource'): void
{
    apiError([['code' => 'NOT_FOUND', 'message' => "$resource not found."]], 404);
}

/**
 * Send a 429 Rate Limited response.
 *
 * @param int $retryAfter  Seconds until rate limit resets
 */
function apiRateLimited(int $retryAfter = 60): void
{
    header("Retry-After: $retryAfter");
    apiError([['code' => 'RATE_LIMITED', 'message' => 'Too many requests. Please slow down.']], 429);
}

/**
 * Send a 500 Internal Server Error response.
 */
function apiServerError(string $message = 'An unexpected server error occurred.'): void
{
    apiError([['code' => 'SERVER_ERROR', 'message' => $message]], 500);
}

/**
 * Parse pagination parameters from the query string.
 *
 * @return array ['page' => int, 'per_page' => int, 'offset' => int]
 */
function getPaginationParams(int $defaultPerPage = 25, int $maxPerPage = 100): array
{
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(1, min($maxPerPage, (int)($_GET['per_page'] ?? $defaultPerPage)));
    return [
        'page'     => $page,
        'per_page' => $perPage,
        'offset'   => ($page - 1) * $perPage,
    ];
}
