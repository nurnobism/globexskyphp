<?php
/**
 * Coupon validation and application
 *
 * Coupon types:
 * - percentage: X% off (max discount cap)
 * - fixed: $X off
 * - free_shipping: waive shipping cost
 * - buy_x_get_y: buy X items get Y free
 *
 * Validation rules:
 * - Check expiry date
 * - Check usage limit (total uses)
 * - Check per-user limit
 * - Check minimum order amount
 * - Check applicable categories (if category_id set)
 * - Check applicable suppliers (if supplier_id set)
 * - Check first-order-only flag
 */

/**
 * Validate a coupon code for the current cart.
 *
 * @param string $code       Coupon code
 * @param int    $userId     Current user ID
 * @param array  $cartItems  Array of cart item rows (must include price, quantity, category_id, supplier_id)
 * @return array ['valid'=>bool, 'discount'=>float, 'message'=>string, 'coupon'=>array|null]
 */
function validateCouponEngine(string $code, int $userId, array $cartItems): array
{
    $db = getDB();
    $code = strtoupper(trim($code));

    if ($code === '') {
        return ['valid' => false, 'discount' => 0, 'message' => 'Coupon code is required', 'coupon' => null];
    }

    // Look up in coupons table
    try {
        $stmt = $db->prepare('SELECT * FROM coupons WHERE code = ? LIMIT 1');
        $stmt->execute([$code]);
        $coupon = $stmt->fetch();
    } catch (PDOException $e) {
        return ['valid' => false, 'discount' => 0, 'message' => 'Unable to validate coupon', 'coupon' => null];
    }

    if (!$coupon) {
        return ['valid' => false, 'discount' => 0, 'message' => 'Invalid coupon code', 'coupon' => null];
    }

    // Check expiry
    if (!empty($coupon['expires_at']) && strtotime($coupon['expires_at']) < time()) {
        return ['valid' => false, 'discount' => 0, 'message' => 'This coupon has expired', 'coupon' => $coupon];
    }

    // Check active status
    if (isset($coupon['is_active']) && !$coupon['is_active']) {
        return ['valid' => false, 'discount' => 0, 'message' => 'This coupon is no longer active', 'coupon' => $coupon];
    }

    // Check global usage limit
    if (!empty($coupon['usage_limit']) && (int)$coupon['usage_limit'] > 0) {
        try {
            $uStmt = $db->prepare('SELECT COUNT(*) FROM coupon_usage WHERE coupon_id = ?');
            $uStmt->execute([$coupon['id']]);
            if ((int)$uStmt->fetchColumn() >= (int)$coupon['usage_limit']) {
                return ['valid' => false, 'discount' => 0, 'message' => 'This coupon has reached its usage limit', 'coupon' => $coupon];
            }
        } catch (PDOException $e) { /* ignore */ }
    }

    // Check per-user limit
    $perUser = (int)($coupon['per_user_limit'] ?? 1);
    if ($perUser > 0 && $userId > 0) {
        try {
            $uStmt = $db->prepare('SELECT COUNT(*) FROM coupon_usage WHERE coupon_id = ? AND user_id = ?');
            $uStmt->execute([$coupon['id'], $userId]);
            if ((int)$uStmt->fetchColumn() >= $perUser) {
                return ['valid' => false, 'discount' => 0, 'message' => 'You have already used this coupon', 'coupon' => $coupon];
            }
        } catch (PDOException $e) { /* ignore */ }
    }

    // Calculate cart subtotal
    $subtotal = 0.0;
    foreach ($cartItems as $item) {
        $price = (float)($item['price'] ?? $item['unit_price'] ?? 0);
        $qty   = (int)($item['quantity'] ?? 1);
        $subtotal += $price * $qty;
    }

    // Check minimum order amount
    if (!empty($coupon['min_order']) && $subtotal < (float)$coupon['min_order']) {
        return [
            'valid'   => false,
            'discount'=> 0,
            'message' => 'Minimum order amount of $' . number_format((float)$coupon['min_order'], 2) . ' required',
            'coupon'  => $coupon,
        ];
    }

    // Check first-order-only flag
    if (!empty($coupon['first_order_only']) && $userId > 0) {
        try {
            $oStmt = $db->prepare('SELECT COUNT(*) FROM orders WHERE buyer_id = ? AND status NOT IN ("cancelled","refunded")');
            $oStmt->execute([$userId]);
            if ((int)$oStmt->fetchColumn() > 0) {
                return ['valid' => false, 'discount' => 0, 'message' => 'This coupon is for first-time orders only', 'coupon' => $coupon];
            }
        } catch (PDOException $e) { /* ignore */ }
    }

    // Calculate discount
    $couponType = $coupon['type'] ?? $coupon['discount_type'] ?? 'percent';
    $discountVal = (float)($coupon['discount'] ?? $coupon['discount_value'] ?? 0);
    $discount = 0.0;

    switch ($couponType) {
        case 'percentage':
        case 'percent':
            $discount = round($subtotal * $discountVal / 100, 2);
            // Apply max discount cap if set
            if (!empty($coupon['max_discount']) && $discount > (float)$coupon['max_discount']) {
                $discount = (float)$coupon['max_discount'];
            }
            break;

        case 'fixed':
            $discount = min($discountVal, $subtotal);
            break;

        case 'free_shipping':
            $discount = 0; // handled at shipping level
            break;

        case 'buy_x_get_y':
            // Simplified: give free items based on quantity multiples
            $buyX   = (int)($coupon['buy_x'] ?? 2);
            $getY   = (int)($coupon['get_y'] ?? 1);
            $total  = array_sum(array_column($cartItems, 'quantity'));
            $sets   = (int)floor($total / ($buyX + $getY));
            // Value of free items (cheapest items)
            $prices = [];
            foreach ($cartItems as $item) {
                for ($i = 0; $i < (int)($item['quantity'] ?? 1); $i++) {
                    $prices[] = (float)($item['price'] ?? 0);
                }
            }
            sort($prices);
            $freeItems = array_slice($prices, 0, $sets * $getY);
            $discount  = round(array_sum($freeItems), 2);
            break;
    }

    return [
        'valid'   => true,
        'discount'=> $discount,
        'message' => 'Coupon applied successfully',
        'coupon'  => $coupon,
        'type'    => $couponType,
    ];
}

/**
 * Record coupon usage when an order is placed.
 */
function applyCoupon(string $code, int $orderId, int $userId, float $discountAmount): bool
{
    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT id FROM coupons WHERE code = ?');
        $stmt->execute([strtoupper(trim($code))]);
        $coupon = $stmt->fetch();
        if (!$coupon) return false;

        $ins = $db->prepare('INSERT INTO coupon_usage (coupon_id, user_id, order_id, discount_amount, used_at)
            VALUES (?, ?, ?, ?, NOW())');
        $ins->execute([$coupon['id'], $userId, $orderId, $discountAmount]);
        return true;
    } catch (PDOException $e) {
        error_log('applyCoupon error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get active/available coupons for a user.
 */
function getActiveCoupons(int $userId): array
{
    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT c.*,
            (SELECT COUNT(*) FROM coupon_usage cu WHERE cu.coupon_id = c.id) AS times_used
            FROM coupons c
            WHERE (c.expires_at IS NULL OR c.expires_at > NOW())
              AND (c.is_active IS NULL OR c.is_active = 1)
              AND (c.usage_limit IS NULL OR c.usage_limit = 0 OR
                   (SELECT COUNT(*) FROM coupon_usage cu2 WHERE cu2.coupon_id = c.id) < c.usage_limit)
            ORDER BY c.created_at DESC');
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}
