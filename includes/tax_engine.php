<?php
/**
 * includes/tax_engine.php — Tax Calculation Engine (PR #12)
 *
 * Supports three tax modes:
 *   1. fixed       — Single rate applied to all orders
 *   2. per_country — Different rates per country/state (DB-driven)
 *   3. vat         — EU-style VAT with reverse-charge for B2B
 *
 * Feature toggle: isFeatureEnabled('tax_calculation')
 */

// ─────────────────────────────────────────────────────────────────────────────
// Tax Mode & Config helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Get the current tax mode from admin config.
 * Returns one of: 'fixed', 'per_country', 'vat'
 */
function getTaxMode(): string
{
    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'tax_mode' LIMIT 1");
        $stmt->execute();
        $val  = $stmt->fetchColumn();
        if ($val && in_array($val, ['fixed', 'per_country', 'vat'], true)) return $val;
    } catch (PDOException $e) { /* ignore */ }
    return 'fixed';
}

/**
 * Get a single tax config setting value.
 */
function getTaxSetting(string $key, string $default = ''): string
{
    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $val  = $stmt->fetchColumn();
        if ($val !== false) return (string)$val;
    } catch (PDOException $e) { /* ignore */ }
    return $default;
}

/**
 * Get the global default/fallback tax rate.
 */
function getDefaultTaxRate(): float
{
    return (float)getTaxSetting('tax_default_rate', '10.00');
}

// ─────────────────────────────────────────────────────────────────────────────
// Country / State Rate helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Get the tax rate for a given country (and optional state).
 * Checks state rate first, then country rate, then default.
 *
 * @param string $countryCode  ISO 3166-1 alpha-2
 * @param string $stateCode    Two-letter state/province code (optional)
 * @return float               Tax rate as percentage (e.g. 20.00)
 */
function getCountryTaxRate(string $countryCode, string $stateCode = ''): float
{
    $countryCode = strtoupper(trim($countryCode));
    $stateCode   = strtoupper(trim($stateCode));
    if ($countryCode === '') return getDefaultTaxRate();

    $db = getDB();
    try {
        // State-level rate takes priority
        if ($stateCode !== '') {
            $stmt = $db->prepare(
                "SELECT rate FROM tax_rates
                  WHERE country_code = ? AND state_code = ? AND is_active = 1 LIMIT 1"
            );
            $stmt->execute([$countryCode, $stateCode]);
            $rate = $stmt->fetchColumn();
            if ($rate !== false) return (float)$rate;
        }

        // Country-level rate (no state)
        $stmt = $db->prepare(
            "SELECT rate FROM tax_rates
              WHERE country_code = ? AND state_code = '' AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([$countryCode]);
        $rate = $stmt->fetchColumn();
        if ($rate !== false) return (float)$rate;
    } catch (PDOException $e) { /* ignore */ }

    return getDefaultTaxRate();
}

/**
 * Get the tax rate for a country (backward-compatible alias).
 */
function getTaxRate(string $countryCode, string $stateCode = ''): float
{
    return getCountryTaxRate($countryCode, $stateCode);
}

/**
 * Set (upsert) a country/state tax rate.
 */
function setCountryTaxRate(
    string $countryCode,
    float  $rate,
    string $taxName    = 'Tax',
    string $stateCode  = '',
    string $stateName  = '',
    string $countryName = ''
): bool {
    $db = getDB();
    try {
        if ($countryName === '') {
            if (function_exists('getCountryName')) {
                $countryName = getCountryName($countryCode);
            } else {
                $countryName = strtoupper($countryCode);
            }
        }
        $db->prepare(
            "INSERT INTO tax_rates
                (country_code, country_name, state_code, state_name, rate, tax_name, is_active)
             VALUES (?, ?, ?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE
                rate = VALUES(rate),
                tax_name = VALUES(tax_name),
                country_name = VALUES(country_name),
                state_name = VALUES(state_name),
                is_active = 1,
                updated_at = NOW()"
        )->execute([
            strtoupper($countryCode), $countryName,
            strtoupper($stateCode),   $stateName,
            $rate, $taxName
        ]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get all configured tax rates (paginated or full list).
 */
function getAllTaxRates(int $page = 0, int $perPage = 0): array
{
    $db = getDB();
    try {
        $sql = 'SELECT * FROM tax_rates ORDER BY country_name ASC, state_name ASC';
        if ($page > 0 && $perPage > 0) {
            $offset = ($page - 1) * $perPage;
            $sql   .= " LIMIT $perPage OFFSET $offset";
        }
        $stmt = $db->query($sql);
        return $stmt->fetchAll();
    } catch (PDOException $e) { /* ignore */ }
    return [];
}

/**
 * Delete a tax rate by its ID.
 */
function deleteCountryTaxRate(int $rateId): bool
{
    $db = getDB();
    try {
        $db->prepare('DELETE FROM tax_rates WHERE id = ?')->execute([$rateId]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Core calculation functions
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Calculate tax using fixed rate mode.
 *
 * @param float $subtotal
 * @return array{tax_rate:float,tax_amount:float}
 */
function calculateFixedTax(float $subtotal): array
{
    $rate   = (float)getTaxSetting('tax_fixed_rate', '10.00');
    $amount = $subtotal > 0 ? round($subtotal * $rate / 100, 2) : 0.0;
    return ['tax_rate' => $rate, 'tax_amount' => $amount];
}

/**
 * Calculate tax using per-country rate mode.
 *
 * @param float  $subtotal
 * @param string $countryCode
 * @param string $stateCode
 * @return array{tax_rate:float,tax_amount:float}
 */
function calculateCountryTax(float $subtotal, string $countryCode, string $stateCode = ''): array
{
    $rate   = getCountryTaxRate($countryCode, $stateCode);
    $amount = $subtotal > 0 ? round($subtotal * $rate / 100, 2) : 0.0;
    return ['tax_rate' => $rate, 'tax_amount' => $amount];
}

/**
 * Calculate EU VAT with reverse-charge support.
 *
 * Rules:
 *   - Same country: apply local VAT rate
 *   - Cross-border B2C (no valid VAT number): destination country VAT
 *   - Cross-border B2B (valid EU VAT number): reverse charge (0%)
 *   - Buyer outside EU: no VAT (0%)
 *
 * @param float  $subtotal
 * @param string $buyerCountry   Buyer's country code
 * @param string $sellerCountry  Seller/platform country code
 * @param string $buyerVatNumber Buyer's VAT number (optional)
 * @return array{tax_rate:float,tax_amount:float,is_reverse_charge:bool,vat_note:string}
 */
function calculateVat(
    float  $subtotal,
    string $buyerCountry,
    string $sellerCountry  = 'DE',
    string $buyerVatNumber = ''
): array {
    $buyerCountry  = strtoupper(trim($buyerCountry));
    $sellerCountry = strtoupper(trim($sellerCountry));

    // Buyer outside EU: no VAT
    if (!function_exists('isEuCountry')) {
        require_once __DIR__ . '/countries.php';
    }
    if (!isEuCountry($buyerCountry)) {
        return ['tax_rate' => 0.0, 'tax_amount' => 0.0, 'is_reverse_charge' => false, 'vat_note' => 'Outside EU — no VAT'];
    }

    // B2B cross-border with valid VAT number → reverse charge
    if ($buyerVatNumber !== '' && $buyerCountry !== $sellerCountry) {
        $valid = validateVatNumber($buyerVatNumber, $buyerCountry);
        if ($valid) {
            return ['tax_rate' => 0.0, 'tax_amount' => 0.0, 'is_reverse_charge' => true, 'vat_note' => 'VAT reverse charge (B2B)'];
        }
    }

    // Same country or B2C: apply destination country VAT rate
    $rate   = getCountryTaxRate($buyerCountry);
    $amount = $subtotal > 0 ? round($subtotal * $rate / 100, 2) : 0.0;
    $note   = $buyerCountry === $sellerCountry ? 'Local VAT' : 'Destination country VAT (B2C)';
    return ['tax_rate' => $rate, 'tax_amount' => $amount, 'is_reverse_charge' => false, 'vat_note' => $note];
}

/**
 * Validate an EU VAT number format (regex-based, no API call by default).
 *
 * Optionally calls the VIES API if vies_validation_enabled = 1.
 *
 * @param string $vatNumber   Full VAT number including country prefix (e.g. DE123456789)
 * @param string $countryCode ISO country code
 * @return bool
 */
function validateVatNumber(string $vatNumber, string $countryCode): bool
{
    $vatNumber   = strtoupper(trim(str_replace([' ', '-', '.'], '', $vatNumber)));
    $countryCode = strtoupper(trim($countryCode));

    // VAT number regex patterns per EU country
    $patterns = [
        'AT' => '/^ATU\d{8}$/',
        'BE' => '/^BE0?\d{9,10}$/',
        'BG' => '/^BG\d{9,10}$/',
        'CY' => '/^CY\d{8}[A-Z]$/',
        'CZ' => '/^CZ\d{8,10}$/',
        'DE' => '/^DE\d{9}$/',
        'DK' => '/^DK\d{8}$/',
        'EE' => '/^EE\d{9}$/',
        'ES' => '/^ES[A-Z0-9]\d{7}[A-Z0-9]$/',
        'FI' => '/^FI\d{8}$/',
        'FR' => '/^FR[A-HJ-NP-Z0-9]{2}\d{9}$/',
        'GB' => '/^GB(\d{9}|\d{12}|GD\d{3}|HA\d{3})$/',
        'GR' => '/^EL\d{9}$/',
        'HR' => '/^HR\d{11}$/',
        'HU' => '/^HU\d{8}$/',
        'IE' => '/^IE(\d[A-Z0-9+*]\d{5}[A-Z]{1,2}|\d{7}[A-WY][A-I])$/',
        'IT' => '/^IT\d{11}$/',
        'LT' => '/^LT(\d{9}|\d{12})$/',
        'LU' => '/^LU\d{8}$/',
        'LV' => '/^LV\d{11}$/',
        'MT' => '/^MT\d{8}$/',
        'NL' => '/^NL\d{9}B\d{2}$/',
        'PL' => '/^PL\d{10}$/',
        'PT' => '/^PT\d{9}$/',
        'RO' => '/^RO\d{2,10}$/',
        'SE' => '/^SE\d{12}$/',
        'SI' => '/^SI\d{8}$/',
        'SK' => '/^SK\d{10}$/',
    ];

    // Strip country prefix if present and re-add for matching
    if (str_starts_with($vatNumber, $countryCode)) {
        $toMatch = $vatNumber;
    } else {
        $toMatch = $countryCode . $vatNumber;
    }

    $pattern = $patterns[$countryCode] ?? null;
    if ($pattern === null) {
        return strlen($vatNumber) >= 8 && strlen($vatNumber) <= 15;
    }

    if (!preg_match($pattern, $toMatch)) {
        return false;
    }

    // Optional VIES live validation
    if (getTaxSetting('vies_validation_enabled', '0') === '1') {
        return _viesValidate($countryCode, ltrim(substr($toMatch, strlen($countryCode)), '0') ?: $vatNumber);
    }

    return true;
}

/**
 * Perform a VIES SOAP lookup (internal helper).
 * Returns true if valid, false otherwise.
 */
function _viesValidate(string $countryCode, string $vatNumber): bool
{
    $url = 'https://ec.europa.eu/taxation_customs/vies/services/checkVatService';
    $xml = '<?xml version="1.0" encoding="UTF-8"?><soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:v1="urn:ec.europa.eu:taxud:vies:services:checkVat:types"><soapenv:Body><v1:checkVat><v1:countryCode>' . htmlspecialchars($countryCode) . '</v1:countryCode><v1:vatNumber>' . htmlspecialchars($vatNumber) . '</v1:vatNumber></v1:checkVat></soapenv:Body></soapenv:Envelope>';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $xml,
        CURLOPT_HTTPHEADER     => ['Content-Type: text/xml;charset=UTF-8', 'SOAPAction: ""'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
    ]);
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err || !$response) return false;

    return str_contains($response, '<valid>true</valid>');
}

// ─────────────────────────────────────────────────────────────────────────────
// Main calculateTax — routes to appropriate engine
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Calculate tax for an order.
 *
 * @param float  $orderSubtotal    Pre-tax order subtotal
 * @param array  $shippingAddress  Address array with 'country' and optionally 'state'
 * @param array  $items            Cart/order items (unused currently, for future per-item rates)
 * @param int    $userId           Buyer user ID (for exemption check)
 * @param string $vatNumber        Buyer VAT number (for VAT mode B2B)
 * @return array{tax_mode,tax_rate,tax_amount,taxable_amount,breakdown,is_tax_exempt}
 */
function calculateTax(
    float  $orderSubtotal,
    array  $shippingAddress = [],
    array  $items           = [],
    int    $userId          = 0,
    string $vatNumber       = ''
): array {
    // Feature toggle
    if (function_exists('isFeatureEnabled') && !isFeatureEnabled('tax_calculation')) {
        return _taxZeroResult('feature_disabled', $orderSubtotal);
    }

    $taxInclusive = getTaxSetting('tax_inclusive', '0') === '1';
    $taxableAmount = $taxInclusive
        ? round($orderSubtotal / (1 + getDefaultTaxRate() / 100), 2)
        : $orderSubtotal;

    if ($taxableAmount <= 0) {
        return _taxZeroResult(getTaxMode(), $orderSubtotal);
    }

    // Check user exemption
    if ($userId > 0 && isTaxExempt($userId)) {
        $result          = _taxZeroResult(getTaxMode(), $taxableAmount);
        $result['is_tax_exempt'] = true;
        return $result;
    }

    $countryCode = strtoupper(trim($shippingAddress['country'] ?? ''));
    $stateCode   = strtoupper(trim($shippingAddress['state']   ?? ''));
    $taxMode     = getTaxMode();

    switch ($taxMode) {
        case 'per_country':
            $calc = calculateCountryTax($taxableAmount, $countryCode, $stateCode);
            break;

        case 'vat':
            $sellerCountry = getTaxSetting('seller_country', 'DE');
            $vatCalc       = calculateVat($taxableAmount, $countryCode, $sellerCountry, $vatNumber);
            $calc = [
                'tax_rate'        => $vatCalc['tax_rate'],
                'tax_amount'      => $vatCalc['tax_amount'],
                'is_reverse_charge' => $vatCalc['is_reverse_charge'],
                'vat_note'        => $vatCalc['vat_note'],
            ];
            break;

        case 'fixed':
        default:
            $calc = calculateFixedTax($taxableAmount);
            break;
    }

    return [
        'tax_mode'       => $taxMode,
        'tax_rate'       => $calc['tax_rate'],
        'tax_amount'     => $calc['tax_amount'],
        'taxable_amount' => $taxableAmount,
        'is_tax_exempt'  => false,
        'is_reverse_charge' => $calc['is_reverse_charge'] ?? false,
        'vat_note'       => $calc['vat_note'] ?? '',
        'tax_label'      => getTaxSetting('tax_label', 'Tax'),
        'tax_inclusive'  => $taxInclusive,
        'breakdown'      => [],
    ];
}

/** Return a zero-tax result array. */
function _taxZeroResult(string $taxMode, float $taxableAmount): array
{
    return [
        'tax_mode'          => $taxMode,
        'tax_rate'          => 0.0,
        'tax_amount'        => 0.0,
        'taxable_amount'    => $taxableAmount,
        'is_tax_exempt'     => false,
        'is_reverse_charge' => false,
        'vat_note'          => '',
        'tax_label'         => getTaxSetting('tax_label', 'Tax'),
        'tax_inclusive'     => false,
        'breakdown'         => [],
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// Tax Exemptions
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Check if a user has an active tax exemption.
 */
function isTaxExempt(int $userId): bool
{
    if ($userId <= 0) return false;
    $db = getDB();
    try {
        // Check dedicated tax_exemptions table first
        $stmt = $db->prepare(
            "SELECT id FROM tax_exemptions
              WHERE user_id = ? AND is_active = 1
                AND (expiry_date IS NULL OR expiry_date >= CURDATE())
              LIMIT 1"
        );
        $stmt->execute([$userId]);
        if ($stmt->fetchColumn() !== false) return true;

        // Legacy: users table tax_exempt flag
        $stmt = $db->prepare('SELECT tax_exempt FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetchColumn();
        if ($row !== false) return (bool)$row;
    } catch (PDOException $e) { /* ignore */ }
    return false;
}

/**
 * Grant a tax exemption to a user.
 */
function setTaxExemption(
    int    $userId,
    string $exemptionType,
    string $certificateNumber,
    string $expiryDate,
    int    $grantedBy = 0
): bool {
    $db = getDB();
    try {
        $db->prepare(
            "INSERT INTO tax_exemptions
                (user_id, exemption_type, certificate_number, expiry_date, granted_by, is_active)
             VALUES (?, ?, ?, ?, ?, 1)"
        )->execute([
            $userId,
            $exemptionType,
            $certificateNumber,
            $expiryDate ?: null,
            $grantedBy,
        ]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * List all tax exemptions (admin, paginated).
 */
function getTaxExemptions(int $page = 1, int $perPage = 20): array
{
    $db     = getDB();
    $offset = ($page - 1) * $perPage;
    try {
        $total = (int)$db->query('SELECT COUNT(*) FROM tax_exemptions WHERE is_active = 1')->fetchColumn();
        $stmt  = $db->prepare(
            "SELECT te.*, u.name AS user_name, u.email AS user_email,
                    a.name AS granted_by_name
             FROM tax_exemptions te
             LEFT JOIN users u  ON u.id = te.user_id
             LEFT JOIN users a  ON a.id = te.granted_by
             WHERE te.is_active = 1
             ORDER BY te.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$perPage, $offset]);
        return [
            'items'      => $stmt->fetchAll(),
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $perPage,
            'last_page'  => (int)ceil($total / $perPage),
        ];
    } catch (PDOException $e) {
        return ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'last_page' => 1];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Tax Reporting
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Get tax collected report grouped by country, month, or category.
 *
 * @param string $dateFrom   Y-m-d
 * @param string $dateTo     Y-m-d
 * @param string $groupBy    'country' | 'month' | 'category'
 * @return array[]
 */
function getTaxReport(string $dateFrom, string $dateTo, string $groupBy = 'country'): array
{
    $db = getDB();
    try {
        switch ($groupBy) {
            case 'month':
                $sql = "SELECT DATE_FORMAT(otd.created_at, '%Y-%m') AS grp,
                               COUNT(DISTINCT otd.order_id)         AS orders_count,
                               SUM(otd.taxable_amount)              AS taxable_amount,
                               SUM(otd.tax_amount)                  AS tax_collected
                        FROM order_tax_details otd
                        WHERE otd.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                        GROUP BY grp
                        ORDER BY grp ASC";
                break;

            case 'category':
                $sql = "SELECT COALESCE(c.name, 'Uncategorized')   AS grp,
                               COUNT(DISTINCT otd.order_id)        AS orders_count,
                               SUM(otd.taxable_amount)             AS taxable_amount,
                               SUM(otd.tax_amount)                 AS tax_collected
                        FROM order_tax_details otd
                        JOIN orders o       ON o.id  = otd.order_id
                        JOIN order_items oi ON oi.order_id = o.id
                        JOIN products p     ON p.id  = oi.product_id
                        LEFT JOIN categories c ON c.id = p.category_id
                        WHERE otd.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                        GROUP BY grp
                        ORDER BY tax_collected DESC";
                break;

            default: // country
                $sql = "SELECT COALESCE(otd.country_code, 'N/A')  AS grp,
                               COUNT(DISTINCT otd.order_id)       AS orders_count,
                               SUM(otd.taxable_amount)            AS taxable_amount,
                               SUM(otd.tax_amount)                AS tax_collected
                        FROM order_tax_details otd
                        WHERE otd.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                        GROUP BY grp
                        ORDER BY tax_collected DESC";
                break;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$dateFrom, $dateTo]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Quick tax summary for a period.
 *
 * @param string $period  'month' | 'quarter' | 'year'
 * @return array{total_tax:float,orders_count:int,taxable_amount:float}
 */
function getTaxSummary(string $period = 'month'): array
{
    $db = getDB();
    $interval = match($period) {
        'quarter' => '3 MONTH',
        'year'    => '1 YEAR',
        default   => '1 MONTH',
    };

    $empty = ['total_tax' => 0.0, 'orders_count' => 0, 'taxable_amount' => 0.0];
    try {
        $stmt = $db->prepare(
            "SELECT COALESCE(SUM(tax_amount),0)    AS total_tax,
                    COUNT(*)                       AS orders_count,
                    COALESCE(SUM(taxable_amount),0) AS taxable_amount
             FROM order_tax_details
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL $interval)"
        );
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? [
            'total_tax'      => (float)$row['total_tax'],
            'orders_count'   => (int)$row['orders_count'],
            'taxable_amount' => (float)$row['taxable_amount'],
        ] : $empty;
    } catch (PDOException $e) {
        return $empty;
    }
}

/**
 * Export tax report as CSV string.
 *
 * @param string $dateFrom
 * @param string $dateTo
 * @param string $format   'csv' (only supported value)
 * @return string           CSV content
 */
function exportTaxReport(string $dateFrom, string $dateTo, string $format = 'csv'): string
{
    $rows = getTaxReport($dateFrom, $dateTo, 'country');

    $out = "Group,Orders Count,Taxable Amount,Tax Collected\n";
    foreach ($rows as $row) {
        $out .= sprintf(
            "%s,%d,%.2f,%.2f\n",
            addslashes($row['grp'] ?? ''),
            (int)($row['orders_count'] ?? 0),
            (float)($row['taxable_amount'] ?? 0),
            (float)($row['tax_collected'] ?? 0)
        );
    }
    return $out;
}

/**
 * Get full tax breakdown for a placed order (from order_tax_details).
 *
 * @param int $orderId
 * @return array
 */
function getTaxBreakdown(int $orderId): array
{
    $db = getDB();
    try {
        // Try order_tax_details first
        $stmt = $db->prepare('SELECT * FROM order_tax_details WHERE order_id = ? LIMIT 1');
        $stmt->execute([$orderId]);
        $detail = $stmt->fetch();
        if ($detail) return $detail;

        // Fallback: derive from orders + shipping address
        $stmt = $db->prepare(
            'SELECT o.*, a.country, a.state FROM orders o
             LEFT JOIN addresses a ON a.id = o.shipping_address_id
             WHERE o.id = ?'
        );
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) return [];

        $countryCode = strtoupper($order['country'] ?? '');
        $stateCode   = strtoupper($order['state']   ?? '');
        $subtotal    = (float)($order['subtotal'] ?? $order['total'] ?? 0);
        $taxRate     = getCountryTaxRate($countryCode, $stateCode);
        $taxAmount   = round($subtotal * $taxRate / 100, 2);

        return [
            'order_id'      => $orderId,
            'country_code'  => $countryCode,
            'taxable_amount'=> $subtotal,
            'tax_rate'      => $taxRate,
            'tax_amount'    => $taxAmount,
            'tax_mode'      => getTaxMode(),
        ];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Save tax details for an order (called after order creation).
 */
function saveOrderTaxDetails(
    int    $orderId,
    float  $taxableAmount,
    float  $taxAmount,
    float  $taxRate,
    string $taxMode,
    string $countryCode   = '',
    string $stateCode     = '',
    string $vatNumber     = '',
    bool   $isReverseCharge = false
): bool {
    $db = getDB();
    try {
        $db->prepare(
            "INSERT INTO order_tax_details
                (order_id, tax_mode, tax_rate, taxable_amount, tax_amount,
                 country_code, state_code, vat_number, is_reverse_charge)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                tax_mode = VALUES(tax_mode), tax_rate = VALUES(tax_rate),
                taxable_amount = VALUES(taxable_amount), tax_amount = VALUES(tax_amount),
                country_code = VALUES(country_code), state_code = VALUES(state_code),
                vat_number = VALUES(vat_number), is_reverse_charge = VALUES(is_reverse_charge)"
        )->execute([
            $orderId, $taxMode, $taxRate, $taxableAmount, $taxAmount,
            $countryCode, $stateCode, $vatNumber, (int)$isReverseCharge,
        ]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}
