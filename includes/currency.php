<?php
/**
 * includes/currency.php — Multi-Currency Engine (Phase 10)
 */

/** Get the user's selected currency (default: USD) */
function getSelectedCurrency(): string {
    $currency = $_COOKIE['currency'] ?? ($_SESSION['currency'] ?? (getenv('DEFAULT_CURRENCY') ?: 'USD'));
    return preg_replace('/[^A-Z]/', '', strtoupper((string)$currency));
}

/** Set the selected currency */
function setSelectedCurrency(string $code): void {
    $code = preg_replace('/[^A-Z]/', '', strtoupper($code));
    $_SESSION['currency'] = $code;
    setcookie('currency', $code, time() + 31536000, '/', '', isset($_SERVER['HTTPS']), true);
}

/** Get exchange rate for a currency against USD */
function getExchangeRate(string $code): float {
    static $rates = null;
    if ($rates === null) {
        $rates = loadExchangeRates();
    }
    return $rates[$code] ?? 1.0;
}

/** Load exchange rates from DB cache */
function loadExchangeRates(): array {
    $rates = ['USD' => 1.0];
    try {
        $db   = getDB();
        $stmt = $db->query('SELECT code, exchange_rate FROM currencies WHERE is_active=1');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rates[$row['code']] = (float)$row['exchange_rate'];
        }
    } catch (PDOException $e) {
        // Return defaults on failure
    }
    return $rates;
}

/** Convert amount from USD to target currency */
function convertCurrency(float $amount, string $to = '', string $from = 'USD'): float {
    if ($to === '') {
        $to = getSelectedCurrency();
    }
    if ($from === $to) return $amount;
    // Convert to USD first, then to target
    $fromRate = getExchangeRate($from);
    $toRate   = getExchangeRate($to);
    if ($fromRate <= 0) $fromRate = 1.0;
    return ($amount / $fromRate) * $toRate;
}

/** Format amount with currency symbol */
function formatCurrency(float $amount, string $currency = ''): string {
    if ($currency === '') {
        $currency = getSelectedCurrency();
    }
    $symbols = [
        'USD'=>'$','EUR'=>'€','GBP'=>'£','CNY'=>'¥','JPY'=>'¥','KRW'=>'₩',
        'INR'=>'₹','BDT'=>'৳','AED'=>'د.إ','SAR'=>'﷼','TRY'=>'₺','BRL'=>'R$',
        'RUB'=>'₽','THB'=>'฿','VND'=>'₫','IDR'=>'Rp','MYR'=>'RM','PHP'=>'₱',
        'SGD'=>'S$','AUD'=>'A$','CAD'=>'C$','CHF'=>'CHF','SEK'=>'kr','NOK'=>'kr',
        'DKK'=>'kr','PLN'=>'zł','CZK'=>'Kč','HUF'=>'Ft','ZAR'=>'R','NGN'=>'₦',
    ];
    $symbol = $symbols[$currency] ?? $currency . ' ';
    // No decimal for JPY, KRW, IDR, VND, HUF
    $noDecimal = in_array($currency, ['JPY','KRW','IDR','VND','HUF'], true);
    $formatted = $noDecimal ? number_format($amount, 0) : number_format($amount, 2);
    return $symbol . $formatted;
}

/** Get all active currencies from DB */
function getActiveCurrencies(): array {
    try {
        $db   = getDB();
        $stmt = $db->query('SELECT * FROM currencies WHERE is_active=1 ORDER BY code ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [
            ['code'=>'USD','name'=>'US Dollar','symbol'=>'$'],
            ['code'=>'EUR','name'=>'Euro','symbol'=>'€'],
            ['code'=>'GBP','name'=>'British Pound','symbol'=>'£'],
        ];
    }
}

/** Refresh exchange rates from external API and cache in DB */
function refreshExchangeRates(): bool {
    $apiKey = getenv('EXCHANGE_RATE_API_KEY');
    $apiUrl = rtrim(getenv('EXCHANGE_RATE_API_URL') ?: 'https://v6.exchangerate-api.com/v6/', '/');
    if (!$apiKey) return false;

    $url = $apiUrl . '/' . $apiKey . '/latest/USD';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) return false;
    $data = json_decode($response, true);
    if (empty($data['conversion_rates'])) return false;

    $db = getDB();
    // Update rates in currencies table
    $stmt = $db->prepare('UPDATE currencies SET exchange_rate=?, last_updated=NOW() WHERE code=?');
    foreach ($data['conversion_rates'] as $code => $rate) {
        try {
            $stmt->execute([(float)$rate, $code]);
        } catch (PDOException $e) { /* unknown currency, skip */ }
    }

    // Cache raw JSON
    $db->prepare('INSERT INTO exchange_rate_cache (base_currency, rates_json, fetched_at) VALUES (?,?,NOW())')
       ->execute(['USD', json_encode($data['conversion_rates'])]);

    return true;
}
