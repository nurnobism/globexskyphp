<?php
/**
 * includes/checkout.php — Checkout Helper Library (PR #6)
 *
 * Functions:
 *   validateCheckout($userId)
 *   createOrder($userId, $addressId, $paymentMethod, $cartItems)
 *   calculateOrderTotals($cartItems, $addressId)
 *   getOrderSummary($orderId)
 *   updateOrderStatus($orderId, $status)
 *   getShippingAddresses($userId)
 *   addShippingAddress($userId, $data)
 *   setDefaultAddress($userId, $addressId)
 */

require_once __DIR__ . '/feature_toggles.php';
require_once __DIR__ . '/cart.php';
require_once __DIR__ . '/commission.php';
if (file_exists(__DIR__ . '/shipping.php')) {
    require_once __DIR__ . '/shipping.php';
}

// ---------------------------------------------------------------------------
// Validation
// ---------------------------------------------------------------------------

/**
 * Validate that a user's cart is ready for checkout.
 * Returns ['valid' => true] or ['valid' => false, 'errors' => [...]]
 */
function validateCheckout(int $userId): array
{
    $errors = [];

    if (!isFeatureEnabled('cart_checkout')) {
        $errors[] = 'Checkout is currently disabled.';
        return ['valid' => false, 'errors' => $errors];
    }

    $cartItems = getCart($userId);
    if (empty($cartItems)) {
        $errors[] = 'Your cart is empty.';
    }

    $stockIssues = validateCartStock($userId);
    foreach ($stockIssues as $issue) {
        if ($issue['issue'] === 'out_of_stock') {
            $errors[] = htmlspecialchars($issue['product_name'], ENT_QUOTES) . ' is out of stock.';
        }
    }

    $addresses = getShippingAddresses($userId);
    if (empty($addresses)) {
        $errors[] = 'Please add a shipping address before checkout.';
    }

    if (!empty($errors)) {
        return ['valid' => false, 'errors' => $errors];
    }

    return ['valid' => true];
}

// ---------------------------------------------------------------------------
// Order Creation
// ---------------------------------------------------------------------------

/**
 * Create orders from cart items, split per supplier.
 * Returns array of order IDs, or throws on failure.
 *
 * @param  int    $userId
 * @param  int    $addressId   ID from addresses table
 * @param  string $paymentMethod  stripe|cod|bank_transfer
 * @param  array  $cartItems   from getCart()
 * @return int[]  order IDs
 */
function createOrder(int $userId, int $addressId, string $paymentMethod, array $cartItems): array
{
    $db = getDB();

    // Snapshot shipping address
    $addrStmt = $db->prepare('SELECT * FROM addresses WHERE id = ? AND user_id = ?');
    $addrStmt->execute([$addressId, $userId]);
    $address = $addrStmt->fetch();
    if (!$address) {
        throw new RuntimeException('Invalid shipping address.');
    }

    // Allowed payment methods
    $allowedMethods = ['stripe', 'cod', 'bank_transfer'];
    if (!in_array($paymentMethod, $allowedMethods, true)) {
        $paymentMethod = 'stripe';
    }

    // Initial order status per payment method
    $initialStatus = match ($paymentMethod) {
        'cod'           => 'pending',
        'bank_transfer' => 'pending',
        default         => 'pending',   // stripe: pending until PaymentIntent succeeds
    };

    // Group cart items by supplier
    $groups = [];
    foreach ($cartItems as $item) {
        $sid = (int)($item['supplier_id'] ?? 0);
        if (!isset($groups[$sid])) {
            $groups[$sid] = [];
        }
        $groups[$sid][] = $item;
    }

    $orderIds = [];
    $date     = date('Ymd');

    $db->beginTransaction();
    try {
        foreach ($groups as $supplierId => $items) {
            $totals      = calculateOrderTotals($items, $addressId);
            $orderNumber = 'GS-' . $date . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

            // Insert order row
            $stmt = $db->prepare(
                'INSERT INTO orders
                    (order_number, buyer_id, supplier_id, status,
                     subtotal, shipping_fee, tax, total,
                     payment_method, shipping_address, placed_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $orderNumber,
                $userId,
                $supplierId > 0 ? $supplierId : null,
                $initialStatus,
                $totals['subtotal'],
                $totals['shipping'],
                $totals['tax'],
                $totals['total'],
                $paymentMethod,
                json_encode([
                    'full_name'     => $address['full_name'] ?? '',
                    'phone'         => $address['phone'] ?? '',
                    'address_line1' => $address['address_line1'],
                    'address_line2' => $address['address_line2'] ?? '',
                    'city'          => $address['city'],
                    'state'         => $address['state'] ?? '',
                    'postal_code'   => $address['postal_code'] ?? '',
                    'country'       => $address['country'],
                ]),
            ]);
            $orderId = (int)$db->lastInsertId();

            // Insert order items
            foreach ($items as $item) {
                $db->prepare(
                    'INSERT INTO order_items
                        (order_id, product_id, variant_id, product_name, product_image,
                         variation_info, product_sku, quantity, unit_price, total_price, attributes)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $orderId,
                    $item['product_id'],
                    $item['variant_id'] ?? null,
                    $item['product_name'],
                    $item['image'] ?? null,
                    $item['sku_info'] ?? null,
                    $item['slug'] ?? null,
                    $item['quantity'],
                    $item['unit_price'],
                    $item['subtotal'],
                    $item['sku_info'] ? json_encode(['variation' => $item['sku_info']]) : null,
                ]);
            }

            // Deduct stock atomically
            foreach ($items as $item) {
                if ($item['variant_id']) {
                    $db->prepare(
                        'UPDATE product_variants SET stock_qty = GREATEST(0, stock_qty - ?) WHERE id = ?'
                    )->execute([$item['quantity'], $item['variant_id']]);
                } else {
                    $db->prepare(
                        'UPDATE products SET stock_qty = GREATEST(0, stock_qty - ?) WHERE id = ?'
                    )->execute([$item['quantity'], $item['product_id']]);
                }
            }

            // Calculate and store commission
            try {
                $commResult = calculateCommission($orderId);
                if ($commResult !== false) {
                    $db->prepare(
                        'UPDATE orders SET commission_amount = ? WHERE id = ?'
                    )->execute([$commResult['commission_amount'], $orderId]);
                }
            } catch (Throwable $e) {
                error_log('checkout createOrder commission error: ' . $e->getMessage());
            }

            $orderIds[] = $orderId;
        }

        // Clear cart after successful order creation
        clearCart($userId);

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    return $orderIds;
}

// ---------------------------------------------------------------------------
// Totals
// ---------------------------------------------------------------------------

/**
 * Calculate order totals for a set of cart items and address.
 *
 * Uses zone-based shipping when the shipping_calculator feature is enabled
 * and a valid address is provided; otherwise falls back to a flat rate.
 *
 * @param  array $cartItems  items from getCart() for one supplier group
 * @param  int   $addressId  User address ID for zone-based rate lookup
 * @param  int   $methodId   Specific shipping method ID (0 = cheapest available)
 * @return array{subtotal:float, shipping:float, tax:float, total:float, shipping_method_id:int|null}
 */
function calculateOrderTotals(array $cartItems, int $addressId = 0, int $methodId = 0): array
{
    $subtotal = 0.0;
    foreach ($cartItems as $item) {
        $subtotal += (float)($item['unit_price'] ?? $item['price'] ?? 0) * (int)$item['quantity'];
    }
    $subtotal = round($subtotal, 2);

    // Zone-based shipping when feature is enabled and address provided
    $shipping         = null;
    $chosenMethodId   = null;
    if ($addressId > 0
        && function_exists('isFeatureEnabled') && isFeatureEnabled('shipping_calculator')
        && function_exists('getShippingRates')
    ) {
        try {
            $rates = getShippingRates($cartItems, $addressId);
            if (!empty($rates['methods'])) {
                $selectedMethod = null;
                if ($methodId > 0) {
                    foreach ($rates['methods'] as $m) {
                        if ((int)$m['id'] === $methodId) { $selectedMethod = $m; break; }
                    }
                }
                // Default: cheapest available method
                if (!$selectedMethod) {
                    usort($rates['methods'], fn($a, $b) => $a['cost'] <=> $b['cost']);
                    $selectedMethod = $rates['methods'][0];
                }
                $shipping       = (float)$selectedMethod['cost'];
                $chosenMethodId = (int)$selectedMethod['id'];
            }
        } catch (Throwable $e) {
            $shipping = null;
        }
    }

    // Fallback: flat-rate shipping (free over $100, else $9.99)
    if ($shipping === null) {
        $shipping = ($subtotal >= 100.0) ? 0.0 : 9.99;
    }

    // Tax: try tax engine, fall back to 0
    $tax = 0.0;
    if (function_exists('calculateTax')) {
        try {
            $tax = calculateTax($subtotal);
        } catch (Throwable $e) {
            $tax = 0.0;
        }
    }
    $tax = round($tax, 2);

    $total = round($subtotal + $shipping + $tax, 2);

    return [
        'subtotal'           => $subtotal,
        'shipping'           => $shipping,
        'tax'                => $tax,
        'total'              => $total,
        'shipping_method_id' => $chosenMethodId,
    ];
}

// ---------------------------------------------------------------------------
// Order Summary & Status
// ---------------------------------------------------------------------------

/**
 * Get a full order summary including all items.
 * Returns order array with 'items' key, or null if not found.
 */
function getOrderSummary(int $orderId): ?array
{
    $db = getDB();

    $stmt = $db->prepare(
        'SELECT o.*, u.full_name buyer_name, u.email buyer_email
         FROM orders o
         JOIN users u ON u.id = o.buyer_id
         WHERE o.id = ?'
    );
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) return null;

    $iStmt = $db->prepare(
        'SELECT oi.*, p.slug product_slug
         FROM order_items oi
         LEFT JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id = ?
         ORDER BY oi.id ASC'
    );
    $iStmt->execute([$orderId]);
    $order['items'] = $iStmt->fetchAll();

    // Decode shipping address snapshot
    if (!empty($order['shipping_address']) && is_string($order['shipping_address'])) {
        $order['shipping_address'] = json_decode($order['shipping_address'], true);
    }

    return $order;
}

/**
 * Update order status with transition validation.
 * Returns true on success, false if invalid transition.
 */
function updateOrderStatus(int $orderId, string $newStatus): bool
{
    $validStatuses = [
        'pending', 'confirmed', 'processing', 'shipped', 'delivered',
        'cancelled', 'refunded',
    ];

    if (!in_array($newStatus, $validStatuses, true)) {
        return false;
    }

    $db   = getDB();
    $stmt = $db->prepare('UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$newStatus, $orderId]);
    return $stmt->rowCount() > 0;
}

// ---------------------------------------------------------------------------
// Shipping Addresses  (uses existing `addresses` table)
// ---------------------------------------------------------------------------

/**
 * Get all shipping addresses for a user (default first).
 */
function getShippingAddresses(int $userId): array
{
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Add a new shipping address for a user.
 * Returns the new address ID, or throws on validation failure.
 */
function addShippingAddress(int $userId, array $data): int
{
    $required = ['full_name', 'phone', 'address_line1', 'city', 'country'];
    foreach ($required as $field) {
        if (empty(trim($data[$field] ?? ''))) {
            throw new InvalidArgumentException("Field '$field' is required.");
        }
    }

    $db = getDB();

    // If this is the user's first address, make it default
    $countStmt = $db->prepare('SELECT COUNT(*) FROM addresses WHERE user_id = ?');
    $countStmt->execute([$userId]);
    $count     = (int)$countStmt->fetchColumn();
    $isDefault = ($count === 0) ? 1 : (int)!empty($data['is_default']);

    if ($isDefault) {
        $db->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = ?')->execute([$userId]);
    }

    $stmt = $db->prepare(
        'INSERT INTO addresses
            (user_id, full_name, phone, address_line1, address_line2, city, state, postal_code, country, is_default)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $userId,
        trim($data['full_name']),
        trim($data['phone'] ?? ''),
        trim($data['address_line1']),
        trim($data['address_line2'] ?? ''),
        trim($data['city']),
        trim($data['state'] ?? ''),
        trim($data['postal_code'] ?? ''),
        trim($data['country']),
        $isDefault,
    ]);

    return (int)$db->lastInsertId();
}

/**
 * Set a specific address as the user's default shipping address.
 */
function setDefaultAddress(int $userId, int $addressId): bool
{
    $db = getDB();
    $db->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = ?')->execute([$userId]);
    $stmt = $db->prepare('UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?');
    $stmt->execute([$addressId, $userId]);
    return $stmt->rowCount() > 0;
}
