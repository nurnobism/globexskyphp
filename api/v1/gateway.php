<?php
/**
 * GlobexSky REST API v1 Gateway
 *
 * Central entry point for all v1 API requests.
 *
 * URL: /api/v1/gateway.php?resource=products&action=list
 * Or with .htaccess rewrite: /api/v1/products/list
 *
 * Auth Methods:
 *   1. API Key  — Header: X-API-Key: {key}
 *   2. Bearer   — Header: Authorization: Bearer {jwt_token}
 *
 * Features:
 *   - Rate limiting per API key (tracked in DB)
 *   - Request logging
 *   - CORS headers
 *   - JSON-only responses
 *   - Proper HTTP status codes
 */

require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/api-response.php';
require_once __DIR__ . '/../../includes/api-auth.php';

setApiHeaders();

$resource = strtolower(trim($_GET['resource'] ?? ''));
$action   = strtolower(trim($_GET['action'] ?? ''));

if (!$resource) {
    apiError([
        'code'    => 'MISSING_RESOURCE',
        'message' => 'Specify ?resource=<name>&action=<name> or use /api/v1/{resource}/{action}.',
    ], 400);
}

// Resources that do NOT require authentication
$publicResources = ['products', 'reviews', 'shipping'];
$publicActions   = [
    'products' => ['list', 'detail', 'search', 'categories'],
    'reviews'  => ['list'],
    'shipping' => ['rates', 'countries', 'tracking'],
    'users'    => ['register', 'login', 'refresh_token'],
];

$requiresAuth = true;
if (in_array($resource, $publicResources, true)) {
    if (isset($publicActions[$resource]) && in_array($action, $publicActions[$resource], true)) {
        $requiresAuth = false;
    }
}
if ($resource === 'users' && in_array($action, ['register', 'login', 'refresh_token'], true)) {
    $requiresAuth = false;
}

$startTime = microtime(true);
$apiKey    = null;

if ($requiresAuth) {
    $apiKey = authenticateApiRequest();
}

// Dispatch to resource handler
$resourceMap = [
    'products' => __DIR__ . '/products.php',
    'orders'   => __DIR__ . '/orders.php',
    'users'    => __DIR__ . '/users.php',
    'cart'     => __DIR__ . '/cart.php',
    'reviews'  => __DIR__ . '/reviews.php',
    'shipping' => __DIR__ . '/shipping.php',
    'dropship' => __DIR__ . '/dropship.php',
    'webhooks' => __DIR__ . '/webhooks.php',
];

if (!array_key_exists($resource, $resourceMap)) {
    $elapsed = (int)((microtime(true) - $startTime) * 1000);
    if ($apiKey) {
        logApiRequest((int)$apiKey['id'], (int)$apiKey['user_id'], $_SERVER['REQUEST_METHOD'], "$resource/$action", 404, $elapsed);
    }
    apiNotFound("Resource '$resource'");
}

// Pass context to resource handlers
define('API_RESOURCE', $resource);
define('API_ACTION', $action);
define('API_KEY_ROW', $apiKey);
define('API_START_TIME', $startTime);

require $resourceMap[$resource];
