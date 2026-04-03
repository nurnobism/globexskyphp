<?php
/**
 * Central pricing engine — enforces ALL pricing rules at checkout
 *
 * calculateOrderTotals($cartItems, $shippingMethod, $couponCode, $address):
 *   1. Calculate item subtotal (with any sale prices)
 *   2. Apply dropship markup if applicable
 *   3. Calculate shipping cost
 *   4. Apply coupon discount
 *   5. Calculate tax
 *   6. Calculate platform commission (for internal tracking)
 *   7. Return full breakdown:
 *      - items_subtotal
 *      - shipping_cost
 *      - coupon_discount
 *      - tax_amount
 *      - total
 *      - commission_amount (internal, not shown to buyer)
 *      - supplier_earning (internal)
 */

require_once __DIR__ . '/coupon_engine.php';
require_once __DIR__ . '/tax_engine.php';
require_once __DIR__ . '/commission.php';

/**
 * Calculate all order totals through a single engine.
 *
 * @param array  $cartItems      Array of cart item rows. Each must have: price, quantity, supplier_id, category_id
 * @param string $shippingMethod Shipping method key (standard|express|overnight)
 * @param string $couponCode     Coupon code (empty string for none)
 * @param array  $address        Shipping address (must include country)
 * @param int    $userId         Buyer user ID
 * @return array                 Full price breakdown
 */
function calculateOrderTotals(
    array $cartItems,
    string $shippingMethod = 'standard',
    string $couponCode = '',
    array $address = [],
    int $userId = 0
): array {
    // 1. Item subtotal
    $itemsSubtotal = 0.0;
    foreach ($cartItems as $item) {
        $price = (float)($item['sale_price'] ?? $item['price'] ?? $item['unit_price'] ?? 0);
        $qty   = (int)($item['quantity'] ?? 1);
        $itemsSubtotal += $price * $qty;
    }
    $itemsSubtotal = round($itemsSubtotal, 2);

    // 2. Dropship markup (if any item is dropshipped)
    $dropshipMarkup = 0.0;
    foreach ($cartItems as $item) {
        if (!empty($item['is_dropship'])) {
            $markupRate = (float)($item['dropship_markup_rate'] ?? 0);
            $price      = (float)($item['price'] ?? 0);
            $qty        = (int)($item['quantity'] ?? 1);
            $dropshipMarkup += round($price * $qty * $markupRate / 100, 2);
        }
    }

    $subtotalAfterMarkup = round($itemsSubtotal + $dropshipMarkup, 2);

    // 3. Shipping cost
    $shippingCost = calculateShippingCost($shippingMethod, $subtotalAfterMarkup);

    // 4. Coupon discount
    $couponDiscount = 0.0;
    $couponResult   = null;
    if ($couponCode !== '') {
        $couponResult   = validateCouponEngine($couponCode, $userId, $cartItems);
        if ($couponResult['valid']) {
            $couponDiscount = (float)$couponResult['discount'];
            // Free shipping coupon
            if (($couponResult['coupon']['type'] ?? '') === 'free_shipping') {
                $shippingCost = 0.0;
            }
        }
    }

    $afterCoupon = max(0, round($subtotalAfterMarkup - $couponDiscount, 2));

    // 5. Tax
    $countryCode = strtoupper($address['country'] ?? 'US');
    $taxExempt   = $userId > 0 && isTaxExempt($userId);
    $taxAmount   = $taxExempt ? 0.0 : calculateTax($afterCoupon, $countryCode);

    // 6. Grand total
    $total = round($afterCoupon + $shippingCost + $taxAmount, 2);

    // 7. Commission (internal tracking — first supplier found; real impl per order_item)
    $avgRate = 0.0;
    $supplierId = 0;
    if (!empty($cartItems)) {
        $supplierId = (int)($cartItems[0]['supplier_id'] ?? 0);
        $categoryId = (int)($cartItems[0]['category_id'] ?? 0);
        $avgRate    = getEffectiveRate($supplierId, $categoryId);
    }
    $commissionAmount = round($afterCoupon * $avgRate / 100, 2);
    $supplierEarning  = round($afterCoupon - $commissionAmount, 2);

    return [
        'items_subtotal'    => $itemsSubtotal,
        'dropship_markup'   => $dropshipMarkup,
        'subtotal'          => $subtotalAfterMarkup,
        'shipping_cost'     => $shippingCost,
        'coupon_discount'   => $couponDiscount,
        'coupon_valid'      => $couponResult['valid'] ?? false,
        'coupon_message'    => $couponResult['message'] ?? '',
        'tax_rate'          => getTaxRate($countryCode),
        'tax_amount'        => $taxAmount,
        'total'             => $total,
        'commission_rate'   => $avgRate,
        'commission_amount' => $commissionAmount,
        'supplier_earning'  => $supplierEarning,
        'currency'          => 'USD',
    ];
}

/**
 * Calculate shipping cost based on method and subtotal.
 */
function calculateShippingCost(string $method, float $subtotal): float
{
    // Free shipping over $500
    if ($subtotal >= 500) return 0.0;

    $rates = [
        'standard'  => 9.99,
        'express'   => 24.99,
        'overnight' => 49.99,
        'freight'   => 99.99,
        'free'      => 0.0,
    ];

    // Check DB for custom shipping rules
    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT value FROM pricing_rules WHERE category = "shipping" AND name = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$method]);
        $dbRate = $stmt->fetchColumn();
        if ($dbRate !== false) {
            return (float)$dbRate;
        }
    } catch (PDOException $e) { /* ignore */ }

    return (float)($rates[$method] ?? $rates['standard']);
}
