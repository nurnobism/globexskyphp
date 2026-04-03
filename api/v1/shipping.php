<?php
/**
 * api/v1/shipping.php — Shipping API Resource
 *
 * Actions: rates, countries, tracking, create_shipment
 */
if (!defined('API_RESOURCE')) {
    require_once __DIR__ . '/gateway.php';
    exit;
}

require_once __DIR__ . '/../../includes/api-response.php';

$db     = getDB();
$action = API_ACTION ?: ($_GET['action'] ?? 'rates');
$apiKey = API_KEY_ROW;

$elapsed = fn() => (int)((microtime(true) - API_START_TIME) * 1000);

switch ($action) {
    // ── GET rates ─────────────────────────────────────────────
    case 'rates':
        $from   = trim($_GET['from_country'] ?? '');
        $to     = trim($_GET['to_country'] ?? '');
        $weight = (float)($_GET['weight_kg'] ?? 1);

        if (!$from || !$to) {
            apiError('from_country and to_country are required.', 400);
        }

        // Return static rate estimates (real implementation would query carrier APIs)
        $rates = [
            [
                'carrier'          => 'Standard',
                'service'          => 'Economy',
                'price'            => round($weight * 2.5, 2),
                'currency'         => 'USD',
                'estimated_days'   => '14-21',
            ],
            [
                'carrier'          => 'Express',
                'service'          => 'Priority',
                'price'            => round($weight * 8.0, 2),
                'currency'         => 'USD',
                'estimated_days'   => '5-7',
            ],
            [
                'carrier'          => 'Premium',
                'service'          => 'Express',
                'price'            => round($weight * 15.0, 2),
                'currency'         => 'USD',
                'estimated_days'   => '2-3',
            ],
        ];

        if ($apiKey) {
            logApiRequest((int)$apiKey['id'], (int)$apiKey['user_id'], 'GET', 'shipping/rates', 200, $elapsed());
        }
        apiSuccess($rates, null, 200, $apiKey ? getRateLimit($apiKey) : null);
        break;

    // ── GET countries ─────────────────────────────────────────
    case 'countries':
        // Return list of supported shipping countries
        $countries = [
            ['code' => 'US', 'name' => 'United States'],
            ['code' => 'GB', 'name' => 'United Kingdom'],
            ['code' => 'DE', 'name' => 'Germany'],
            ['code' => 'FR', 'name' => 'France'],
            ['code' => 'CA', 'name' => 'Canada'],
            ['code' => 'AU', 'name' => 'Australia'],
            ['code' => 'CN', 'name' => 'China'],
            ['code' => 'JP', 'name' => 'Japan'],
            ['code' => 'IN', 'name' => 'India'],
            ['code' => 'BR', 'name' => 'Brazil'],
            ['code' => 'MX', 'name' => 'Mexico'],
            ['code' => 'SG', 'name' => 'Singapore'],
            ['code' => 'AE', 'name' => 'United Arab Emirates'],
        ];
        if ($apiKey) {
            logApiRequest((int)$apiKey['id'], (int)$apiKey['user_id'], 'GET', 'shipping/countries', 200, $elapsed());
        }
        apiSuccess($countries, null, 200, $apiKey ? getRateLimit($apiKey) : null);
        break;

    // ── GET tracking ──────────────────────────────────────────
    case 'tracking':
        $trackingNumber = trim($_GET['tracking_number'] ?? '');
        if (!$trackingNumber) {
            apiError('tracking_number is required.', 400);
        }
        $stmt = $db->prepare(
            'SELECT o.order_number, o.status, o.tracking_number, o.shipping_carrier
             FROM orders o
             WHERE o.tracking_number = ?'
        );
        $stmt->execute([$trackingNumber]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            apiNotFound('Tracking information');
        }
        if ($apiKey) {
            logApiRequest((int)$apiKey['id'], (int)$apiKey['user_id'], 'GET', 'shipping/tracking', 200, $elapsed());
        }
        apiSuccess($result, null, 200, $apiKey ? getRateLimit($apiKey) : null);
        break;

    // ── POST create_shipment ──────────────────────────────────
    case 'create_shipment':
        if (!$apiKey) {
            apiUnauthorized();
        }
        if (!in_array($apiKey['user_role'], ['supplier', 'admin', 'super_admin'], true)) {
            apiForbidden('Only suppliers can create shipments.');
        }
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $errors = [];
        if (empty($body['order_id'])) {
            $errors['order_id'] = 'Order ID required.';
        }
        if (empty($body['carrier'])) {
            $errors['carrier'] = 'Carrier required.';
        }
        if ($errors) {
            apiValidationError($errors);
        }
        $trackingNumber = strtoupper(bin2hex(random_bytes(8)));
        $db->prepare(
            'UPDATE orders SET tracking_number = ?, shipping_carrier = ?, status = "shipped" WHERE id = ?'
        )->execute([$trackingNumber, $body['carrier'], (int)$body['order_id']]);

        logApiRequest((int)$apiKey['id'], (int)$apiKey['user_id'], 'POST', 'shipping/create_shipment', 201, $elapsed());
        apiSuccess(['tracking_number' => $trackingNumber, 'message' => 'Shipment created.'], null, 201, getRateLimit($apiKey));
        break;

    default:
        if ($apiKey) {
            logApiRequest((int)$apiKey['id'], (int)$apiKey['user_id'], $_SERVER['REQUEST_METHOD'], "shipping/$action", 404, $elapsed());
        }
        apiNotFound("Action '$action'");
}
