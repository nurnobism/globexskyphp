<?php
/**
 * api/v1/orders.php — Orders API Resource
 *
 * Actions: list, detail, create, update_status, cancel, tracking
 */
if (!defined('API_RESOURCE')) {
    require_once __DIR__ . '/gateway.php';
    exit;
}

require_once __DIR__ . '/../../includes/api-response.php';

$db     = getDB();
$action = API_ACTION ?: ($_GET['action'] ?? 'list');
$apiKey = API_KEY_ROW;
$userId = (int)$apiKey['user_id'];

$elapsed = fn() => (int)((microtime(true) - API_START_TIME) * 1000);

switch ($action) {
    // ── GET list ──────────────────────────────────────────────
    case 'list':
        $pag      = getPaginationParams();
        $where    = [];
        $bindings = [];

        // Non-admin users see only their own orders
        if (!in_array($apiKey['user_role'], ['admin', 'super_admin'], true)) {
            $where[]    = 'o.buyer_id = ?';
            $bindings[] = $userId;
        }

        if (!empty($_GET['status'])) {
            $where[]    = 'o.status = ?';
            $bindings[] = $_GET['status'];
        }
        if (!empty($_GET['from'])) {
            $where[]    = 'o.placed_at >= ?';
            $bindings[] = $_GET['from'];
        }
        if (!empty($_GET['to'])) {
            $where[]    = 'o.placed_at <= ?';
            $bindings[] = $_GET['to'];
        }

        $whereStr  = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $countStmt = $db->prepare("SELECT COUNT(*) FROM orders o $whereStr");
        $countStmt->execute($bindings);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $db->prepare(
            "SELECT o.id, o.order_number, o.status, o.payment_status, o.total,
                    o.placed_at, o.buyer_id
             FROM orders o $whereStr ORDER BY o.placed_at DESC LIMIT ? OFFSET ?"
        );
        $stmt->execute(array_merge($bindings, [$pag['per_page'], $pag['offset']]));
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        logApiRequest((int)$apiKey['id'], $userId, 'GET', 'orders/list', 200, $elapsed());
        apiPaginated($orders, $pag['page'], $pag['per_page'], $total, getRateLimit($apiKey));
        break;

    // ── GET detail ────────────────────────────────────────────
    case 'detail':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            apiError('Order ID required.', 400);
        }
        $stmt = $db->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            apiNotFound('Order');
        }
        // Access control
        if (!in_array($apiKey['user_role'], ['admin', 'super_admin'], true) && (int)$order['buyer_id'] !== $userId) {
            apiForbidden('You do not have access to this order.');
        }
        // Get items
        $itemStmt = $db->prepare('SELECT oi.*, p.name AS product_name FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?');
        $itemStmt->execute([$id]);
        $order['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        logApiRequest((int)$apiKey['id'], $userId, 'GET', 'orders/detail', 200, $elapsed());
        apiSuccess($order, null, 200, getRateLimit($apiKey));
        break;

    // ── POST create ───────────────────────────────────────────
    case 'create':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($body['items'])) {
            apiValidationError(['items' => 'Order items are required.']);
        }
        // Simple order creation stub
        $orderNumber = 'ORD-' . strtoupper(bin2hex(random_bytes(4)));
        $total       = 0;
        foreach ($body['items'] as $item) {
            $total += (float)($item['price'] ?? 0) * (int)($item['quantity'] ?? 1);
        }
        $stmt = $db->prepare(
            'INSERT INTO orders (order_number, buyer_id, status, payment_status, total, placed_at)
             VALUES (?, ?, "pending", "unpaid", ?, NOW())'
        );
        $stmt->execute([$orderNumber, $userId, $total]);
        $orderId = (int)$db->lastInsertId();

        foreach ($body['items'] as $item) {
            $db->prepare(
                'INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([
                $orderId,
                (int)$item['product_id'],
                (int)($item['quantity'] ?? 1),
                (float)($item['price'] ?? 0),
                (float)($item['price'] ?? 0) * (int)($item['quantity'] ?? 1),
            ]);
        }

        logApiRequest((int)$apiKey['id'], $userId, 'POST', 'orders/create', 201, $elapsed());
        apiSuccess(['id' => $orderId, 'order_number' => $orderNumber, 'total' => $total], null, 201, getRateLimit($apiKey));
        break;

    // ── PUT update_status ─────────────────────────────────────
    case 'update_status':
        if (!in_array($apiKey['user_role'], ['supplier', 'admin', 'super_admin'], true)) {
            apiForbidden('Insufficient permissions.');
        }
        $id   = (int)($_GET['id'] ?? 0);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!$id || empty($body['status'])) {
            apiError('Order ID and status required.', 400);
        }
        $allowed = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
        if (!in_array($body['status'], $allowed, true)) {
            apiValidationError(['status' => 'Invalid status value.']);
        }
        $db->prepare('UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$body['status'], $id]);
        logApiRequest((int)$apiKey['id'], $userId, 'PUT', 'orders/update_status', 200, $elapsed());
        apiSuccess(['message' => 'Order status updated.'], null, 200, getRateLimit($apiKey));
        break;

    // ── POST cancel ───────────────────────────────────────────
    case 'cancel':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            apiError('Order ID required.', 400);
        }
        $stmt = $db->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            apiNotFound('Order');
        }
        if ((int)$order['buyer_id'] !== $userId && !in_array($apiKey['user_role'], ['admin', 'super_admin'], true)) {
            apiForbidden('You do not own this order.');
        }
        if ($order['status'] !== 'pending') {
            apiError('Only pending orders can be cancelled.', 422);
        }
        $db->prepare('UPDATE orders SET status = "cancelled", updated_at = NOW() WHERE id = ?')->execute([$id]);
        logApiRequest((int)$apiKey['id'], $userId, 'POST', 'orders/cancel', 200, $elapsed());
        apiSuccess(['message' => 'Order cancelled.'], null, 200, getRateLimit($apiKey));
        break;

    // ── GET tracking ──────────────────────────────────────────
    case 'tracking':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            apiError('Order ID required.', 400);
        }
        $stmt = $db->prepare('SELECT tracking_number, shipping_carrier, status FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            apiNotFound('Order');
        }
        logApiRequest((int)$apiKey['id'], $userId, 'GET', 'orders/tracking', 200, $elapsed());
        apiSuccess($order, null, 200, getRateLimit($apiKey));
        break;

    default:
        logApiRequest((int)$apiKey['id'], $userId, $_SERVER['REQUEST_METHOD'], "orders/$action", 404, $elapsed());
        apiNotFound("Action '$action'");
}
