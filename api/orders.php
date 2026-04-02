<?php
/**
 * api/orders.php — Orders API
 */

require_once __DIR__ . '/../includes/middleware.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

switch ($action) {

    case 'list':
        $page   = max(1, (int)get('page', 1));
        $status = get('status', '');
        $where  = ['o.buyer_id = ?'];
        $params = [$_SESSION['user_id']];
        if ($status) { $where[] = 'o.status = ?'; $params[] = $status; }
        $sql = 'SELECT o.*, COUNT(oi.id) item_count FROM orders o
            LEFT JOIN order_items oi ON oi.order_id = o.id
            WHERE ' . implode(' AND ', $where) . ' GROUP BY o.id ORDER BY o.placed_at DESC';
        jsonResponse(paginate($db, $sql, $params, $page));
        break;

    case 'detail':
        $id = (int)get('id', 0);
        if (!$id) jsonResponse(['error' => 'Order ID required'], 400);

        $stmt = $db->prepare('SELECT * FROM orders WHERE id = ? AND buyer_id = ?');
        $stmt->execute([$id, $_SESSION['user_id']]);
        $order = $stmt->fetch();
        if (!$order) jsonResponse(['error' => 'Order not found'], 404);

        $iStmt = $db->prepare('SELECT * FROM order_items WHERE order_id = ?');
        $iStmt->execute([$id]);
        $order['items'] = $iStmt->fetchAll();

        $sStmt = $db->prepare('SELECT * FROM shipments WHERE order_id = ? ORDER BY created_at DESC LIMIT 1');
        $sStmt->execute([$id]);
        $order['shipment'] = $sStmt->fetch() ?: null;

        jsonResponse(['data' => $order]);
        break;

    case 'place':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);

        // Get cart items
        $cStmt = $db->prepare('SELECT ci.*, p.price, p.name, p.sku, p.stock_qty, p.supplier_id
            FROM cart_items ci JOIN products p ON p.id = ci.product_id
            WHERE ci.user_id = ?');
        $cStmt->execute([$_SESSION['user_id']]);
        $cartItems = $cStmt->fetchAll();

        if (empty($cartItems)) jsonResponse(['error' => 'Cart is empty'], 400);

        $shippingAddress = [
            'full_name'    => post('full_name', ''),
            'phone'        => post('phone', ''),
            'address_line1'=> post('address_line1', ''),
            'address_line2'=> post('address_line2', ''),
            'city'         => post('city', ''),
            'state'        => post('state', ''),
            'postal_code'  => post('postal_code', ''),
            'country'      => post('country', 'US'),
        ];

        if (empty($shippingAddress['full_name']) || empty($shippingAddress['address_line1']) || empty($shippingAddress['city'])) {
            jsonResponse(['error' => 'Shipping address is incomplete'], 422);
        }

        // Apply coupon
        $couponCode = trim(post('coupon_code', ''));
        $discount   = 0.00;
        if ($couponCode) {
            $cpStmt = $db->prepare('SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) AND (usage_limit IS NULL OR used_count < usage_limit) LIMIT 1');
            $cpStmt->execute([$couponCode]);
            $coupon = $cpStmt->fetch();
            if ($coupon) {
                $subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cartItems));
                if ($coupon['type'] === 'percent') {
                    $discount = min($subtotal * $coupon['value'] / 100, $coupon['max_discount'] ?? PHP_INT_MAX);
                } else {
                    $discount = min($coupon['value'], $subtotal);
                }
                $db->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?')->execute([$coupon['id']]);
            }
        }

        $subtotal    = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cartItems));
        $shippingFee = (float)post('shipping_fee', 0);
        $tax         = round($subtotal * 0.05, 2); // 5% tax
        $total       = max(0, $subtotal + $shippingFee + $tax - $discount);

        $orderNumber = 'GS-' . strtoupper(substr(md5(uniqid()), 0, 8));

        $db->prepare('INSERT INTO orders (order_number, buyer_id, status, subtotal, shipping_fee, tax, discount, total, payment_method, shipping_address, coupon_code)
            VALUES (?, ?, "pending", ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$orderNumber, $_SESSION['user_id'], $subtotal, $shippingFee, $tax, $discount, $total,
                post('payment_method', 'bank_transfer'), json_encode($shippingAddress), $couponCode ?: null]);

        $orderId = (int)$db->lastInsertId();

        // Insert order items
        foreach ($cartItems as $item) {
            $db->prepare('INSERT INTO order_items (order_id, product_id, variant_id, product_name, product_sku, quantity, unit_price, total_price)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$orderId, $item['product_id'], $item['variant_id'] ?? null,
                    $item['name'], $item['sku'] ?? '', $item['quantity'],
                    $item['price'], $item['price'] * $item['quantity']]);
        }

        // Clear cart
        $db->prepare('DELETE FROM cart_items WHERE user_id = ?')->execute([$_SESSION['user_id']]);

        // Notify user
        $db->prepare('INSERT INTO notifications (user_id, type, title, message) VALUES (?, "order_placed", ?, ?)')
           ->execute([$_SESSION['user_id'], 'Order Placed', 'Your order ' . $orderNumber . ' has been placed successfully.']);

        jsonResponse(['success' => true, 'order_id' => $orderId, 'order_number' => $orderNumber]);
        break;

    case 'cancel':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);

        $id = (int)post('order_id', 0);
        $stmt = $db->prepare('SELECT id, status FROM orders WHERE id = ? AND buyer_id = ?');
        $stmt->execute([$id, $_SESSION['user_id']]);
        $order = $stmt->fetch();

        if (!$order) jsonResponse(['error' => 'Order not found'], 404);
        if (!in_array($order['status'], ['pending', 'confirmed'])) {
            jsonResponse(['error' => 'Order cannot be cancelled at this stage'], 400);
        }

        $db->prepare('UPDATE orders SET status = "cancelled", cancelled_at = NOW() WHERE id = ?')->execute([$id]);
        jsonResponse(['success' => true]);
        break;

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}
