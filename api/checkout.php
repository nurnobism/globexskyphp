<?php
/**
 * api/checkout.php — Checkout API (PR #6 rewrite)
 *
 * All endpoints require authentication.
 *
 * GET  ?action=validate           — Validate cart for checkout readiness
 * GET  ?action=get_addresses      — Get saved shipping addresses
 * POST ?action=add_address        — Add new shipping address
 * POST ?action=set_default_address — Set default address
 * POST ?action=calculate_totals   — Calculate order totals
 * POST ?action=create_payment_intent — Create order(s) + Stripe/COD/BankTransfer
 * POST ?action=confirm_order      — Confirm order after Stripe payment
 * GET  ?action=get_order_summary  — Get order details for confirmation page
 */

require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/checkout.php';
require_once __DIR__ . '/../includes/stripe-handler.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../config/stripe.php';

$action = $_GET['action'] ?? post('action', '');
$method = $_SERVER['REQUEST_METHOD'];

// All checkout actions require login
requireAuth();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

switch ($action) {

    // -----------------------------------------------------------------------
    // GET ?action=validate
    // -----------------------------------------------------------------------
    case 'validate':
        $result    = validateCheckout($userId);
        $cartItems = getCart($userId);
        $totals    = [];

        if (!empty($cartItems)) {
            $addresses = getShippingAddresses($userId);
            $default   = array_values(array_filter($addresses, fn($a) => (int)$a['is_default'] === 1));
            $addrId    = $default ? (int)$default[0]['id'] : (int)($addresses[0]['id'] ?? 0);
            if ($addrId > 0) {
                $totals = calculateOrderTotals($cartItems, $addrId);
            }
        }

        jsonResponse(array_merge($result, [
            'cart_count' => count($cartItems),
            'totals'     => $totals,
        ]));
        break;

    // -----------------------------------------------------------------------
    // GET ?action=get_addresses
    // -----------------------------------------------------------------------
    case 'get_addresses':
        $addresses = getShippingAddresses($userId);
        jsonResponse(['addresses' => $addresses]);
        break;

    // -----------------------------------------------------------------------
    // POST ?action=add_address
    // -----------------------------------------------------------------------
    case 'add_address':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);

        $data = [
            'full_name'     => trim(post('full_name', '')),
            'phone'         => trim(post('phone', '')),
            'address_line1' => trim(post('address_line1', '')),
            'address_line2' => trim(post('address_line2', '')),
            'city'          => trim(post('city', '')),
            'state'         => trim(post('state', '')),
            'postal_code'   => trim(post('postal_code', '')),
            'country'       => trim(post('country', 'US')),
            'is_default'    => (int)(bool)post('is_default', 0),
        ];

        $required = ['full_name', 'phone', 'address_line1', 'city', 'country'];
        $errors   = [];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[$field] = ucwords(str_replace('_', ' ', $field)) . ' is required.';
            }
        }
        if ($errors) {
            jsonResponse(['error' => 'Validation failed', 'errors' => $errors], 422);
        }

        try {
            $addressId = addShippingAddress($userId, $data);
            $addresses = getShippingAddresses($userId);
            jsonResponse(['success' => true, 'address_id' => $addressId, 'addresses' => $addresses]);
        } catch (InvalidArgumentException $e) {
            jsonResponse(['error' => $e->getMessage()], 422);
        }
        break;

    // -----------------------------------------------------------------------
    // POST ?action=set_default_address
    // -----------------------------------------------------------------------
    case 'set_default_address':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);

        $addressId = (int)post('address_id', 0);
        if ($addressId <= 0) jsonResponse(['error' => 'address_id required'], 400);

        $ok = setDefaultAddress($userId, $addressId);
        jsonResponse(['success' => $ok]);
        break;

    // -----------------------------------------------------------------------
    // POST ?action=calculate_totals
    // -----------------------------------------------------------------------
    case 'calculate_totals':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);

        $addressId = (int)post('address_id', 0);
        if ($addressId <= 0) jsonResponse(['error' => 'address_id is required'], 400);

        $cartItems = getCart($userId);
        if (empty($cartItems)) jsonResponse(['error' => 'Cart is empty'], 400);

        $addrCheck = $db->prepare('SELECT id FROM addresses WHERE id = ? AND user_id = ?');
        $addrCheck->execute([$addressId, $userId]);
        if (!$addrCheck->fetch()) jsonResponse(['error' => 'Address not found'], 404);

        $totals = calculateOrderTotals($cartItems, $addressId);
        jsonResponse($totals);
        break;

    // -----------------------------------------------------------------------
    // POST ?action=create_payment_intent
    // -----------------------------------------------------------------------
    case 'create_payment_intent':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);

        $addressId     = (int)post('address_id', 0);
        $paymentMethod = trim(post('payment_method', 'stripe'));

        if ($addressId <= 0) jsonResponse(['error' => 'address_id is required'], 400);

        // Feature toggle checks
        $allowedPM = ['stripe'];
        if (isFeatureEnabled('cod_payment'))           $allowedPM[] = 'cod';
        if (isFeatureEnabled('bank_transfer_payment')) $allowedPM[] = 'bank_transfer';
        if (!in_array($paymentMethod, $allowedPM, true)) {
            jsonResponse(['error' => 'Payment method not available'], 400);
        }

        $validation = validateCheckout($userId);
        if (!$validation['valid']) {
            jsonResponse(['error' => 'Checkout validation failed', 'errors' => $validation['errors']], 422);
        }

        $addrCheck = $db->prepare('SELECT id FROM addresses WHERE id = ? AND user_id = ?');
        $addrCheck->execute([$addressId, $userId]);
        if (!$addrCheck->fetch()) jsonResponse(['error' => 'Address not found'], 404);

        $cartItems = getCart($userId);
        if (empty($cartItems)) jsonResponse(['error' => 'Cart is empty'], 400);

        // Server-side total calculation (never trust client)
        $totals = calculateOrderTotals($cartItems, $addressId);

        try {
            $orderIds = createOrder($userId, $addressId, $paymentMethod, $cartItems);
        } catch (Throwable $e) {
            error_log('createOrder error: ' . $e->getMessage());
            jsonResponse(['error' => 'Failed to create order. Please try again.'], 500);
        }

        if (empty($orderIds)) {
            jsonResponse(['error' => 'Order creation failed'], 500);
        }

        $primaryOrderId = $orderIds[0];

        if ($paymentMethod === 'stripe') {
            $amountCents = (int)round($totals['total'] * 100);
            try {
                $stripeKeys = getStripeKeys();
                if (empty($stripeKeys['secret_key'])) {
                    jsonResponse(['error' => 'Stripe is not configured. Please contact support.'], 503);
                }
                $intent = createPaymentIntent($primaryOrderId, $amountCents);
                jsonResponse([
                    'success'               => true,
                    'order_ids'             => $orderIds,
                    'order_id'              => $primaryOrderId,
                    'client_secret'         => $intent['client_secret'],
                    'payment_intent_id'     => $intent['id'],
                    'publishable_key'       => $stripeKeys['publishable_key'],
                    'totals'                => $totals,
                ]);
            } catch (RuntimeException $e) {
                error_log('createPaymentIntent error: ' . $e->getMessage());
                jsonResponse(['error' => 'Payment setup failed: ' . $e->getMessage()], 500);
            }
        }

        if ($paymentMethod === 'cod') {
            $orderSummary = getOrderSummary($primaryOrderId);
            _checkoutSendPlacedNotification($db, $userId, $primaryOrderId);
            jsonResponse([
                'success'      => true,
                'order_ids'    => $orderIds,
                'order_id'     => $primaryOrderId,
                'order_number' => $orderSummary['order_number'] ?? '',
                'status'       => 'pending',
                'message'      => 'Order placed. Pay on delivery.',
                'totals'       => $totals,
            ]);
        }

        if ($paymentMethod === 'bank_transfer') {
            $bankDetails  = _checkoutGetBankDetails($db);
            $orderSummary = getOrderSummary($primaryOrderId);
            _checkoutSendPlacedNotification($db, $userId, $primaryOrderId);
            jsonResponse([
                'success'      => true,
                'order_ids'    => $orderIds,
                'order_id'     => $primaryOrderId,
                'order_number' => $orderSummary['order_number'] ?? '',
                'status'       => 'pending',
                'bank_details' => $bankDetails,
                'totals'       => $totals,
            ]);
        }

        jsonResponse(['error' => 'Unhandled payment method'], 400);
        break;

    // -----------------------------------------------------------------------
    // POST ?action=confirm_order
    // -----------------------------------------------------------------------
    case 'confirm_order':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);

        $orderId         = (int)post('order_id', 0);
        $paymentIntentId = trim(post('payment_intent_id', ''));

        if ($orderId <= 0 || empty($paymentIntentId)) {
            jsonResponse(['error' => 'order_id and payment_intent_id are required'], 400);
        }

        $oStmt = $db->prepare('SELECT * FROM orders WHERE id = ? AND buyer_id = ?');
        $oStmt->execute([$orderId, $userId]);
        $order = $oStmt->fetch();
        if (!$order) jsonResponse(['error' => 'Order not found'], 404);

        // Prevent double-processing
        if (in_array($order['payment_status'] ?? '', ['paid'], true)) {
            $summary = getOrderSummary($orderId);
            jsonResponse([
                'success'      => true,
                'order_id'     => $orderId,
                'order_number' => $summary['order_number'] ?? '',
                'status'       => 'confirmed',
                'already_paid' => true,
            ]);
        }

        // Verify with Stripe
        try {
            $intent = confirmPayment($paymentIntentId);
        } catch (RuntimeException $e) {
            jsonResponse(['error' => 'Payment verification failed: ' . $e->getMessage()], 502);
        }

        if (($intent['status'] ?? '') !== 'succeeded') {
            jsonResponse([
                'error'          => 'Payment not completed.',
                'payment_status' => $intent['status'] ?? 'unknown',
            ], 422);
        }

        // Update order status
        updateOrderStatus($orderId, 'confirmed');
        $db->prepare(
            "UPDATE orders SET payment_status='paid', confirmed_at=NOW(), updated_at=NOW() WHERE id = ?"
        )->execute([$orderId]);

        _stripeRecordPayment($db, $orderId, $paymentIntentId,
            (int)($intent['amount'] ?? 0), $intent['currency'] ?? 'usd', 'success');

        _notifyOrderPaid($db, $orderId);

        try {
            require_once __DIR__ . '/../includes/mailer.php';
            if (function_exists('sendOrderConfirmationEmail')) {
                sendOrderConfirmationEmail($orderId);
            }
        } catch (Throwable $e) {
            error_log('confirm_order mailer: ' . $e->getMessage());
        }

        $summary = getOrderSummary($orderId);
        jsonResponse([
            'success'      => true,
            'order_id'     => $orderId,
            'order_number' => $summary['order_number'] ?? '',
            'status'       => 'confirmed',
        ]);
        break;

    // -----------------------------------------------------------------------
    // GET ?action=get_order_summary
    // -----------------------------------------------------------------------
    case 'get_order_summary':
        $orderId = (int)($_GET['order_id'] ?? 0);
        if ($orderId <= 0) jsonResponse(['error' => 'order_id is required'], 400);

        $oCheck = $db->prepare('SELECT id FROM orders WHERE id = ? AND buyer_id = ?');
        $oCheck->execute([$orderId, $userId]);
        if (!$oCheck->fetch()) jsonResponse(['error' => 'Order not found'], 404);

        $summary = getOrderSummary($orderId);
        jsonResponse(['order' => $summary]);
        break;

    // -----------------------------------------------------------------------
    // Legacy compatibility
    // -----------------------------------------------------------------------
    case 'validate_address':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        $errors = [];
        foreach (['full_name' => 'Full name', 'address_line1' => 'Address', 'city' => 'City', 'country' => 'Country'] as $field => $label) {
            if (empty(trim(post($field, '')))) {
                $errors[$field] = "$label is required.";
            }
        }
        if ($errors) jsonResponse(['valid' => false, 'errors' => $errors], 422);
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
        $cpStmt = $db->prepare(
            'SELECT * FROM coupons WHERE code = ? AND is_active = 1
             AND (expires_at IS NULL OR expires_at > NOW())
             AND (usage_limit IS NULL OR used_count < usage_limit) LIMIT 1'
        );
        $cpStmt->execute([$couponCode]);
        $coupon = $cpStmt->fetch();
        if (!$coupon) jsonResponse(['error' => 'Invalid or expired coupon'], 422);
        $discount = ($coupon['type'] === 'percent')
            ? min($subtotal * $coupon['value'] / 100, $coupon['max_discount'] ?? PHP_INT_MAX)
            : min((float)$coupon['value'], $subtotal);
        jsonResponse(['valid' => true, 'discount' => round($discount, 2),
            'coupon' => ['code' => $coupon['code'], 'type' => $coupon['type'], 'value' => $coupon['value']]]);
        break;

    case 'place_order':
        // Legacy place_order — redirect to create_payment_intent logic
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);

        $cartItems = getCart($userId);
        if (empty($cartItems)) jsonResponse(['error' => 'Cart is empty'], 400);

        // Build address from post data
        $addrData = [
            'full_name'     => trim(post('full_name', '')),
            'phone'         => trim(post('phone', '')),
            'address_line1' => trim(post('address_line1', '')),
            'address_line2' => trim(post('address_line2', '')),
            'city'          => trim(post('city', '')),
            'state'         => trim(post('state', '')),
            'postal_code'   => trim(post('postal_code', '')),
            'country'       => trim(post('country', 'US')),
        ];
        foreach (['full_name', 'address_line1', 'city', 'country'] as $f) {
            if (empty($addrData[$f])) jsonResponse(['error' => 'Shipping address is incomplete'], 422);
        }

        // Ensure phone is set
        if (empty($addrData['phone'])) {
            $addrData['phone'] = 'N/A';
        }

        try {
            $addrId = addShippingAddress($userId, $addrData);
        } catch (Throwable $e) {
            // Address may already exist — get existing default
            $addresses = getShippingAddresses($userId);
            $addrId = !empty($addresses) ? (int)$addresses[0]['id'] : 0;
            if ($addrId === 0) jsonResponse(['error' => 'Could not save address'], 500);
        }

        $payMethod = trim(post('payment_method', 'cod'));
        $totals    = calculateOrderTotals($cartItems, $addrId);

        try {
            $orderIds = createOrder($userId, $addrId, $payMethod, $cartItems);
        } catch (Throwable $e) {
            jsonResponse(['error' => 'Failed to create order: ' . $e->getMessage()], 500);
        }

        $primaryOrderId = $orderIds[0];
        $orderSummary   = getOrderSummary($primaryOrderId);

        if ($payMethod === 'stripe') {
            try {
                $stripeKeys = getStripeKeys();
                if (!empty($stripeKeys['secret_key'])) {
                    $amountCents = (int)round($totals['total'] * 100);
                    $intent = createPaymentIntent($primaryOrderId, $amountCents);
                    jsonResponse([
                        'success'       => true,
                        'order_id'      => $primaryOrderId,
                        'order_number'  => $orderSummary['order_number'] ?? '',
                        'client_secret' => $intent['client_secret'],
                    ]);
                }
            } catch (RuntimeException $e) {
                error_log('place_order stripe error: ' . $e->getMessage());
            }
        }

        jsonResponse([
            'success'      => true,
            'order_id'     => $primaryOrderId,
            'order_number' => $orderSummary['order_number'] ?? '',
        ]);
        break;

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}

// ---------------------------------------------------------------------------
// Local helpers (avoid naming collision with stripe-handler functions)
// ---------------------------------------------------------------------------

function _checkoutGetBankDetails(PDO $db): array
{
    $details = [];
    try {
        $stmt = $db->query(
            "SELECT setting_key, setting_value FROM system_settings
             WHERE setting_key IN ('bank_name','bank_account_name','bank_account_number',
                                   'bank_routing_number','bank_swift_code')"
        );
        foreach ($stmt->fetchAll() as $row) {
            $details[$row['setting_key']] = $row['setting_value'];
        }
    } catch (PDOException $e) {
        error_log('_checkoutGetBankDetails: ' . $e->getMessage());
    }
    return $details;
}

function _checkoutSendPlacedNotification(PDO $db, int $userId, int $orderId): void
{
    try {
        $stmt = $db->prepare('SELECT order_number, supplier_id FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) return;

        createNotification($db, $userId, 'order_placed',
            'Order Placed',
            'Your order #' . $order['order_number'] . ' has been placed successfully.',
            ['order_id' => $orderId], 'normal',
            '/pages/checkout/confirmation.php?order_id=' . $orderId);

        if (!empty($order['supplier_id'])) {
            createNotification($db, (int)$order['supplier_id'], 'order_placed',
                'New Order Received',
                'New order #' . $order['order_number'] . ' received.',
                ['order_id' => $orderId], 'normal', '/pages/supplier/orders.php');
        }
    } catch (Throwable $e) {
        error_log('_checkoutSendPlacedNotification: ' . $e->getMessage());
    }
}
