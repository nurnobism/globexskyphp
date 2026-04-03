<?php
/**
 * Tax calculation engine
 *
 * - VAT rates by country (configurable in admin)
 * - GST support
 * - Tax-inclusive vs tax-exclusive pricing toggle
 * - Tax exemption for certain categories
 * - B2B tax exemption with valid tax ID
 */

/**
 * Default tax rates (admin-configurable via tax_rates table).
 */
const DEFAULT_TAX_RATES = [
    'US' => ['rate' => 0.00,  'type' => 'sales_tax', 'name' => 'United States'],
    'GB' => ['rate' => 20.00, 'type' => 'vat',       'name' => 'United Kingdom'],
    'DE' => ['rate' => 19.00, 'type' => 'vat',       'name' => 'Germany'],
    'FR' => ['rate' => 20.00, 'type' => 'vat',       'name' => 'France'],
    'CN' => ['rate' => 13.00, 'type' => 'vat',       'name' => 'China'],
    'BD' => ['rate' => 15.00, 'type' => 'vat',       'name' => 'Bangladesh'],
    'IN' => ['rate' => 18.00, 'type' => 'gst',       'name' => 'India'],
    'JP' => ['rate' => 10.00, 'type' => 'vat',       'name' => 'Japan'],
    'AE' => ['rate' => 5.00,  'type' => 'vat',       'name' => 'UAE'],
    'SA' => ['rate' => 15.00, 'type' => 'vat',       'name' => 'Saudi Arabia'],
];

/**
 * Categories exempt from tax (category slugs or IDs).
 */
const TAX_EXEMPT_CATEGORIES = ['documents', 'digital_goods'];

/**
 * Get the tax rate for a country (checks DB first, then fallback).
 *
 * @param string $countryCode  ISO 3166-1 alpha-2 country code
 * @return float               Tax rate as a percentage (e.g., 20 for 20%)
 */
function getTaxRate(string $countryCode): float
{
    $countryCode = strtoupper(trim($countryCode));
    if ($countryCode === '') return 0.0;

    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT rate FROM tax_rates WHERE country_code = ? AND is_active = 1');
        $stmt->execute([$countryCode]);
        $rate = $stmt->fetchColumn();
        if ($rate !== false) return (float)$rate;
    } catch (PDOException $e) { /* table may not exist */ }

    return (float)(DEFAULT_TAX_RATES[$countryCode]['rate'] ?? 0);
}

/**
 * Check if a user is tax-exempt (B2B with valid tax ID).
 */
function isTaxExempt(int $userId): bool
{
    if ($userId <= 0) return false;
    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT tax_exempt, tax_id FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if ($user && !empty($user['tax_id']) && !empty($user['tax_exempt'])) {
            return (bool)$user['tax_exempt'];
        }
    } catch (PDOException $e) { /* ignore */ }
    return false;
}

/**
 * Calculate tax on a subtotal.
 *
 * @param float  $subtotal     Pre-tax subtotal
 * @param string $countryCode  ISO 3166-1 alpha-2 country code
 * @param int    $categoryId   Product category ID (for exemptions)
 * @return float               Tax amount
 */
function calculateTax(float $subtotal, string $countryCode, int $categoryId = 0): float
{
    if ($subtotal <= 0) return 0.0;

    // Check category exemption
    if ($categoryId > 0) {
        $db = getDB();
        try {
            $stmt = $db->prepare('SELECT slug FROM categories WHERE id = ?');
            $stmt->execute([$categoryId]);
            $slug = $stmt->fetchColumn();
            if ($slug && in_array(strtolower($slug), TAX_EXEMPT_CATEGORIES)) {
                return 0.0;
            }
        } catch (PDOException $e) { /* ignore */ }
    }

    $rate = getTaxRate($countryCode);
    if ($rate <= 0) return 0.0;

    return round($subtotal * $rate / 100, 2);
}

/**
 * Get full tax breakdown for a placed order.
 *
 * @param int $orderId
 * @return array
 */
function getTaxBreakdown(int $orderId): array
{
    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT o.*, a.country FROM orders o
            LEFT JOIN addresses a ON a.id = o.shipping_address_id
            WHERE o.id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) return [];

        $countryCode = strtoupper($order['country'] ?? 'US');
        $subtotal    = (float)($order['subtotal'] ?? $order['total'] ?? 0);
        $taxRate     = getTaxRate($countryCode);
        $taxAmount   = round($subtotal * $taxRate / 100, 2);

        return [
            'order_id'     => $orderId,
            'country_code' => $countryCode,
            'subtotal'     => $subtotal,
            'tax_rate'     => $taxRate,
            'tax_amount'   => $taxAmount,
            'tax_type'     => DEFAULT_TAX_RATES[$countryCode]['type'] ?? 'vat',
            'total'        => round($subtotal + $taxAmount, 2),
        ];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get all available tax rates for display.
 */
function getAllTaxRates(): array
{
    $db = getDB();
    try {
        $stmt = $db->query('SELECT * FROM tax_rates ORDER BY country_name ASC');
        $rows = $stmt->fetchAll();
        if ($rows) return $rows;
    } catch (PDOException $e) { /* ignore */ }

    // Return hard-coded defaults as array
    $result = [];
    foreach (DEFAULT_TAX_RATES as $code => $data) {
        $result[] = array_merge(['country_code' => $code, 'is_active' => 1], $data);
    }
    return $result;
}
