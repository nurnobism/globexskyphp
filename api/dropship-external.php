<?php
/**
 * api/dropship-external.php — External Store Integration API
 *
 * For Enterprise dropshippers who sell on Shopify, WooCommerce, etc.
 *
 * Auth: API key header (X-API-Key)
 * Rate limit: based on plan (Pro: 1K/day, Enterprise: 50K/day)
 * Response format: JSON
 *
 * GET  ?action=products   — List imported products
 * GET  ?action=product&id=123 — Single product data
 * POST ?action=create_order — Create order from external store
 * GET  ?action=order_status&id=456 — Check order status
 * GET  ?action=inventory&id=123 — Check real-time inventory
 * POST ?action=webhook — Register webhook for order updates
 */
require_once __DIR__ . '/../includes/middleware.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-API-Key, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = get('action', '');
$db     = getDB();

// Authenticate via API key
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? get('api_key', '');
$userId = 0;

if (empty($apiKey)) {
    jsonResponse(['error' => 'API key required. Provide X-API-Key header.'], 401);
}

try {
    $stmt = $db->prepare("SELECT u.id, u.role FROM users u
        JOIN user_api_keys uak ON uak.user_id = u.id
        WHERE uak.api_key = ? AND uak.is_active = 1 AND u.status = 'active'
        LIMIT 1");
    $stmt->execute([$apiKey]);
    $apiUser = $stmt->fetch();
} catch (PDOException $e) {
    // Table may not exist — fallback: check users table for api_key column
    try {
        $stmt = $db->prepare("SELECT id, role FROM users WHERE api_key = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$apiKey]);
        $apiUser = $stmt->fetch();
    } catch (PDOException $e2) {
        jsonResponse(['error' => 'API authentication not configured'], 500);
    }
}

if (!$apiUser) {
    jsonResponse(['error' => 'Invalid API key'], 401);
}

$userId = (int)$apiUser['id'];

// Load dropshipping engine
require_once __DIR__ . '/../includes/dropshipping.php';

// Verify store exists
$store = null;
try {
    $stmt = $db->prepare('SELECT * FROM dropship_stores WHERE user_id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$userId]);
    $store = $stmt->fetch();
} catch (PDOException $e) { /* ignore */ }

if (!$store && $action !== 'status') {
    jsonResponse(['error' => 'No active dropship store found'], 404);
}

$storeId = $store ? (int)$store['id'] : 0;

switch ($action) {

    case 'products':
        $page    = max(1, (int)get('page', 1));
        $perPage = min(100, max(1, (int)get('per_page', 50)));
        $offset  = ($page - 1) * $perPage;

        try {
            $count = $db->prepare('SELECT COUNT(*) FROM dropship_products WHERE store_id = ? AND is_active = 1');
            $count->execute([$storeId]);
            $total = (int)$count->fetchColumn();

            $stmt = $db->prepare('SELECT dp.id, dp.original_product_id, dp.custom_title,
                dp.custom_description, dp.custom_images, dp.selling_price, dp.original_price,
                dp.markup_type, dp.markup_value, dp.is_active, dp.last_synced_at,
                p.slug, p.sku, p.weight, p.stock_quantity
                FROM dropship_products dp
                LEFT JOIN products p ON p.id = dp.original_product_id
                WHERE dp.store_id = ? AND dp.is_active = 1
                ORDER BY dp.import_date DESC LIMIT ? OFFSET ?');
            $stmt->execute([$storeId, $perPage, $offset]);
            $products = $stmt->fetchAll();
        } catch (PDOException $e) {
            jsonResponse(['error' => 'Failed to fetch products'], 500);
        }

        foreach ($products as &$p) {
            $p['custom_images'] = json_decode($p['custom_images'] ?? '[]', true);
        }
        unset($p);

        jsonResponse([
            'success'  => true,
            'data'     => $products,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => (int)ceil($total / $perPage),
        ]);
        break;

    case 'product':
        $id = (int)get('id', 0);
        if (!$id) jsonResponse(['error' => 'Product ID required'], 400);

        try {
            $stmt = $db->prepare('SELECT dp.*, p.slug, p.sku, p.weight, p.stock_quantity,
                p.images AS original_images
                FROM dropship_products dp
                LEFT JOIN products p ON p.id = dp.original_product_id
                WHERE dp.id = ? AND dp.store_id = ?');
            $stmt->execute([$id, $storeId]);
            $product = $stmt->fetch();
        } catch (PDOException $e) {
            jsonResponse(['error' => 'Failed to fetch product'], 500);
        }

        if (!$product) jsonResponse(['error' => 'Product not found'], 404);

        $product['custom_images']   = json_decode($product['custom_images'] ?? '[]', true);
        $product['original_images'] = json_decode($product['original_images'] ?? '[]', true);

        jsonResponse(['success' => true, 'data' => $product]);
        break;

    case 'create_order':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['error' => 'POST required'], 405);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;

        $productId = (int)($input['product_id'] ?? 0);
        $quantity  = max(1, (int)($input['quantity'] ?? 1));
        $shipping  = $input['shipping_address'] ?? [];
        $customer  = $input['customer_info'] ?? [];

        if (!$productId) jsonResponse(['error' => 'product_id required'], 400);
        if (empty($shipping['address_line1']) || empty($shipping['city']) || empty($shipping['country'])) {
            jsonResponse(['error' => 'Shipping address (address_line1, city, country) required'], 400);
        }

        // Verify product belongs to this store
        try {
            $dpStmt = $db->prepare('SELECT * FROM dropship_products WHERE id = ? AND store_id = ? AND is_active = 1');
            $dpStmt->execute([$productId, $storeId]);
            $dp = $dpStmt->fetch();
        } catch (PDOException $e) {
            jsonResponse(['error' => 'Database error'], 500);
        }

        if (!$dp) jsonResponse(['error' => 'Product not found in your store'], 404);

        // Create external order
        try {
            $orderNumber = 'DS-EXT-' . strtoupper(bin2hex(random_bytes(4)));
            $total = (float)$dp['selling_price'] * $quantity;

            // Create order in orders table
            $ins = $db->prepare('INSERT INTO orders (buyer_id, order_number, total, status, payment_status, placed_at)
                VALUES (?,?,?,"pending","pending", NOW())');
            $ins->execute([$userId, $orderNumber, $total]);
            $orderId = (int)$db->lastInsertId();

            // Create order item
            try {
                $db->prepare('INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price)
                    VALUES (?,?,?,?,?)')
                   ->execute([$orderId, $dp['original_product_id'], $quantity, $dp['selling_price'], $total]);
            } catch (PDOException $e) { /* ignore */ }

            // Process as dropship order
            $dsResult = processDropshipOrder($orderId);

            jsonResponse([
                'success'      => true,
                'order_id'     => $orderId,
                'order_number' => $orderNumber,
                'total'        => $total,
                'dropship'     => $dsResult,
            ]);
        } catch (PDOException $e) {
            jsonResponse(['error' => 'Failed to create order'], 500);
        }
        break;

    case 'order_status':
        $id = (int)get('id', 0);
        if (!$id) jsonResponse(['error' => 'Order ID required'], 400);

        try {
            $stmt = $db->prepare('SELECT do.id, do.order_id, do.status, do.tracking_number,
                do.tracking_url, do.created_at, do.routed_at, do.shipped_at, do.delivered_at,
                o.order_number
                FROM dropship_orders do
                LEFT JOIN orders o ON o.id = do.order_id
                WHERE do.order_id = ? AND do.dropshipper_id = ?');
            $stmt->execute([$id, $userId]);
            $order = $stmt->fetch();
        } catch (PDOException $e) {
            jsonResponse(['error' => 'Database error'], 500);
        }

        if (!$order) jsonResponse(['error' => 'Order not found'], 404);
        jsonResponse(['success' => true, 'data' => $order]);
        break;

    case 'inventory':
        $id = (int)get('id', 0);
        if (!$id) jsonResponse(['error' => 'Product ID required'], 400);

        try {
            $stmt = $db->prepare('SELECT dp.id, dp.is_active, p.stock_quantity, p.status AS product_status
                FROM dropship_products dp
                LEFT JOIN products p ON p.id = dp.original_product_id
                WHERE dp.id = ? AND dp.store_id = ?');
            $stmt->execute([$id, $storeId]);
            $inv = $stmt->fetch();
        } catch (PDOException $e) {
            jsonResponse(['error' => 'Database error'], 500);
        }

        if (!$inv) jsonResponse(['error' => 'Product not found'], 404);

        jsonResponse([
            'success'        => true,
            'product_id'     => (int)$inv['id'],
            'in_stock'       => ($inv['product_status'] === 'active' && ($inv['stock_quantity'] ?? 0) > 0),
            'stock_quantity' => (int)($inv['stock_quantity'] ?? 0),
            'is_active'      => (bool)$inv['is_active'],
        ]);
        break;

    case 'webhook':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['error' => 'POST required'], 405);
        }

        // Webhook registration placeholder
        jsonResponse([
            'success' => true,
            'message' => 'Webhook registration is not yet implemented. Contact support for webhook setup.',
        ]);
        break;

    case 'status':
        jsonResponse(['success' => true, 'status' => 'ok', 'version' => '1.0']);
        break;

    default:
        jsonResponse([
            'error'    => 'Unknown action',
            'actions'  => ['products','product','create_order','order_status','inventory','webhook','status'],
        ], 400);
        break;
}
