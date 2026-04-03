<?php
/**
 * api/checkout.php — Checkout API
 * GET  ?action=calculate_shipping
 * POST ?action=validate_address|apply_coupon|place_order
 */

require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../config/stripe.php';

$action = $_GET['action'] ?? post('action', '');
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

switch ($action) {

    case 'validate_address':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        $errors = [];
        $required = ['full_name' => 'Full name', 'address_line1' => 'Address', 'city' => 'City', 'country' => 'Country'];
        foreach ($required as $field => $label) {
            if (empty(trim(post($field, '')))) {
                $errors[$field] = "$label is required.";
            }
        }
        if ($errors) {
            jsonResponse(['valid' => false, 'errors' => $errors], 422);
        }
        jsonResponse(['valid' => true]);
        break;

    case 'calculate_shipping':
        $shippingMethod = get('method', 'standard');
        $subtotal       = (float)get('subtotal', 0);

        $fee = match ($shippingMethod) {
            'express'  => 19.99,
            'priority' => 29.99,
            default    => ($subtotal >= 100 ? 0.00 : 9.99),
        };

        jsonResponse(['method' => $shippingMethod, 'fee' => $fee, 'subtotal' => $subtotal]);
        break;

    case 'apply_coupon':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        $couponCode = trim(post('coupon_code', ''));
        $subtotal   = (float)post('subtotal', 0);

        if (empty($couponCode)) jsonResponse(['error' => 'Coupon code required'], 400);

        $cpStmt = $db->prepare('SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) AND (usage_limit IS NULL OR used_count < usage_limit) LIMIT 1');
        $cpStmt->execute([$couponCode]);
        $coupon = $cpStmt->fetch();

        if (!$coupon) jsonResponse(['error' => 'Invalid or expired coupon'], 422);

        $discount = 0.00;
        if ($coupon['type'] === 'percent') {
            $discount = min($subtotal * $coupon['value'] / 100, $coupon['max_discount'] ?? PHP_INT_MAX);
        } else {
            $discount = min((float)$coupon['value'], $subtotal);
        }

        jsonResponse(['valid' => true, 'discount' => round($discount, 2), 'coupon' => ['code' => $coupon['code'], 'type' => $coupon['type'], 'value' => $coupon['value']]]);
        break;

    case 'place_order':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!isLoggedIn())      jsonResponse(['error' => 'Login required'], 401);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);

        // Load cart items
        $cStmt = $db->prepare('SELECT ci.*, p.price, p.name, p.sku, p.stock_qty, p.supplier_id
            FROM cart_items ci JOIN products p ON p.id = ci.product_id
            WHERE ci.user_id = ?');
        $cStmt->execute([$_SESSION['user_id']]);
        $cartItems = $cStmt->fetchAll();

        if (empty($cartItems)) jsonResponse(['error' => 'Cart is empty'], 400);

        // Validate address
        $shippingAddress = [
            'full_name'     => trim(post('full_name', '')),
            'phone'         => trim(post('phone', '')),
            'address_line1' => trim(post('address_line1', '')),
            'address_line2' => trim(post('address_line2', '')),
            'city'          => trim(post('city', '')),
            'state'         => trim(post('state', '')),
            'postal_code'   => trim(post('postal_code', '')),
            'country'       => trim(post('country', 'US')),
        ];

        if (empty($shippingAddress['full_name']) || empty($shippingAddress['address_line1']) || empty($shippingAddress['city']) || empty($shippingAddress['country'])) {
            jsonResponse(['error' => 'Shipping address is incomplete'], 422);
        }

        // Validate stock
        foreach ($cartItems as $item) {
            if ($item['stock_qty'] < $item['quantity']) {
                jsonResponse(['error' => 'Insufficient stock for: ' . $item['name']], 422);
            }
        }

        // Shipping method & fee
        $shippingMethod = post('shipping_method', 'standard');
        if (!in_array($shippingMethod, ['standard', 'express', 'priority'])) {
            $shippingMethod = 'standard';
        }

        $subtotal    = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cartItems));
        $shippingFee = match ($shippingMethod) {
            'express'  => 19.99,
            'priority' => 29.99,
            default    => ($subtotal >= 100 ? 0.00 : 9.99),
        };

        // Apply coupon
        $couponCode = trim(post('coupon_code', ''));
        $discount   = 0.00;
        if ($couponCode) {
            $cpStmt = $db->prepare('SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) AND (usage_limit IS NULL OR used_count < usage_limit) LIMIT 1');
            $cpStmt->execute([$couponCode]);
            $coupon = $cpStmt->fetch();
            if ($coupon) {
                if ($coupon['type'] === 'percent') {
                    $discount = min($subtotal * $coupon['value'] / 100, $coupon['max_discount'] ?? PHP_INT_MAX);
                } else {
                    $discount = min((float)$coupon['value'], $subtotal);
                }
                $db->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?')->execute([$coupon['id']]);
            }
        }

        $tax   = round($subtotal * 0.05, 2);
        $total = max(0, round($subtotal + $shippingFee + $tax - $discount, 2));

        $orderNumber   = 'GS-' . strtoupper(substr(md5(uniqid()), 0, 8));
        $paymentMethod = post('payment_method', 'cod');

        // Insert order
        $db->prepare('INSERT INTO orders (order_number, buyer_id, status, subtotal, shipping_fee, tax, discount, total, payment_method, shipping_method, shipping_address, coupon_code)
            VALUES (?, ?, "pending", ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$orderNumber, $_SESSION['user_id'], $subtotal, $shippingFee, $tax, $discount, $total,
                $paymentMethod, $shippingMethod, json_encode($shippingAddress), $couponCode ?: null]);

        $orderId = (int)$db->lastInsertId();

        // Insert order items
        foreach ($cartItems as $item) {
            $db->prepare('INSERT INTO order_items (order_id, product_id, variant_id, product_name, product_sku, quantity, unit_price, total_price)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$orderId, $item['product_id'], $item['variant_id'] ?? null,
                    $item['name'], $item['sku'] ?? '', $item['quantity'],
                    $item['price'], $item['price'] * $item['quantity']]);
        }

        // Reduce stock
        foreach ($cartItems as $item) {
            $db->prepare('UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?')
               ->execute([$item['quantity'], $item['product_id']]);
        }

        // Clear cart
        $db->prepare('DELETE FROM cart_items WHERE user_id = ?')->execute([$_SESSION['user_id']]);

        // Notification
        $db->prepare('INSERT INTO notifications (user_id, type, title, message) VALUES (?, "order_placed", ?, ?)')
           ->execute([$_SESSION['user_id'], 'Order Placed', 'Your order ' . $orderNumber . ' has been placed successfully.']);

        // Stripe payment
        if ($paymentMethod === 'stripe') {
            if (class_exists('\Stripe\Stripe')) {
                try {
                    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
                    $intent = \Stripe\PaymentIntent::create([
                        'amount'   => (int)round($total * 100),
                        'currency' => 'usd',
                        'metadata' => ['order_id' => $orderId, 'order_number' => $orderNumber],
                    ]);
                    $db->prepare('UPDATE orders SET stripe_payment_intent_id = ? WHERE id = ?')
                       ->execute([$intent->id, $orderId]);
                    jsonResponse(['success' => true, 'order_id' => $orderId, 'order_number' => $orderNumber, 'client_secret' => $intent->client_secret]);
                } catch (\Exception $e) {
                    jsonResponse(['error' => 'Payment setup failed: ' . $e->getMessage()], 500);
                }
            }
            // Stripe not available — fall through to pending
        }

        jsonResponse(['success' => true, 'order_id' => $orderId, 'order_number' => $orderNumber]);
        break;

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}
