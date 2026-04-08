<?php
/**
 * includes/addresses.php — Address Management Library (PR #17)
 *
 * Address CRUD, validation, country/state data, auto-complete and checkout
 * address integration for the user_addresses table.
 *
 * Max addresses per user: 10
 */

require_once __DIR__ . '/feature_toggles.php';
require_once __DIR__ . '/countries_data.php';

const ADDRESS_MAX_PER_USER = 10;

// ─────────────────────────────────────────────────────────────────────────────
// Address CRUD
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Create a new address for a user.
 *
 * @param  int   $userId
 * @param  array $data  Keys: label, full_name, phone, address_line_1, address_line_2,
 *                      city, state_province, state_code, postal_code, country_code,
 *                      is_default_shipping, is_default_billing
 * @return int New address ID
 * @throws RuntimeException on validation failure or limit exceeded
 */
function createAddress(int $userId, array $data): int
{
    $errors = validateAddress($data);
    if (!empty($errors)) {
        throw new InvalidArgumentException(implode(' ', $errors));
    }

    $db = getDB();

    $countStmt = $db->prepare(
        'SELECT COUNT(*) FROM user_addresses WHERE user_id = ? AND deleted_at IS NULL'
    );
    $countStmt->execute([$userId]);
    $count = (int)$countStmt->fetchColumn();

    if ($count >= ADDRESS_MAX_PER_USER) {
        throw new RuntimeException('Maximum of ' . ADDRESS_MAX_PER_USER . ' addresses allowed per user.');
    }

    $isDefaultShipping = !empty($data['is_default_shipping']) ? 1 : ($count === 0 ? 1 : 0);
    $isDefaultBilling  = !empty($data['is_default_billing'])  ? 1 : ($count === 0 ? 1 : 0);

    // Clear existing defaults if setting new ones
    if ($isDefaultShipping) {
        $db->prepare('UPDATE user_addresses SET is_default_shipping = 0 WHERE user_id = ? AND deleted_at IS NULL')
           ->execute([$userId]);
    }
    if ($isDefaultBilling) {
        $db->prepare('UPDATE user_addresses SET is_default_billing = 0 WHERE user_id = ? AND deleted_at IS NULL')
           ->execute([$userId]);
    }

    $countryName = getCountryName($data['country_code'] ?? 'US');

    // Insert into both legacy (address_line1/2) and new (address_line_1/2) columns
    // to maintain backwards compatibility with existing checkout.php and orders queries.
    $stmt = $db->prepare(
        'INSERT INTO user_addresses
            (user_id, label, full_name, phone, address_line1, address_line2,
             address_line_1, address_line_2, city, state_province, state_code,
             postal_code, country, country_code, country_name,
             is_default, is_default_shipping, is_default_billing, is_active,
             created_at, updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,NOW(),NOW())'
    );

    $addrLine1 = sanitizeText($data['address_line_1'] ?? $data['address_line1'] ?? '');
    $addrLine2 = sanitizeText($data['address_line_2'] ?? $data['address_line2'] ?? '');

    $stmt->execute([
        $userId,
        sanitizeText($data['label'] ?? 'Home'),
        sanitizeText($data['full_name'] ?? ''),
        sanitizeText($data['phone'] ?? ''),
        $addrLine1,
        $addrLine2,
        $addrLine1,
        $addrLine2,
        sanitizeText($data['city'] ?? ''),
        sanitizeText($data['state_province'] ?? $data['state'] ?? ''),
        sanitizeText($data['state_code'] ?? ''),
        sanitizeText($data['postal_code'] ?? ''),
        $countryName,
        strtoupper(sanitizeText($data['country_code'] ?? 'US')),
        $countryName,
        $isDefaultShipping,
        $isDefaultShipping,
        $isDefaultBilling,
    ]);

    return (int)$db->lastInsertId();
}

/**
 * Update an existing address.
 *
 * @param  int   $addressId
 * @param  int   $userId     Ownership check
 * @param  array $data
 * @return bool
 * @throws InvalidArgumentException|RuntimeException
 */
function updateAddress(int $addressId, int $userId, array $data): bool
{
    $errors = validateAddress($data);
    if (!empty($errors)) {
        throw new InvalidArgumentException(implode(' ', $errors));
    }

    $db = getDB();

    // Ownership check
    $checkStmt = $db->prepare(
        'SELECT id FROM user_addresses WHERE id = ? AND user_id = ? AND deleted_at IS NULL'
    );
    $checkStmt->execute([$addressId, $userId]);
    if (!$checkStmt->fetch()) {
        throw new RuntimeException('Address not found or access denied.');
    }

    if (!empty($data['is_default_shipping'])) {
        $db->prepare('UPDATE user_addresses SET is_default_shipping = 0 WHERE user_id = ? AND deleted_at IS NULL')
           ->execute([$userId]);
    }
    if (!empty($data['is_default_billing'])) {
        $db->prepare('UPDATE user_addresses SET is_default_billing = 0 WHERE user_id = ? AND deleted_at IS NULL')
           ->execute([$userId]);
    }

    $countryName = getCountryName($data['country_code'] ?? 'US');
    $addrLine1   = sanitizeText($data['address_line_1'] ?? $data['address_line1'] ?? '');
    $addrLine2   = sanitizeText($data['address_line_2'] ?? $data['address_line2'] ?? '');

    $stmt = $db->prepare(
        'UPDATE user_addresses SET
            label = ?, full_name = ?, phone = ?,
            address_line1 = ?, address_line2 = ?,
            address_line_1 = ?, address_line_2 = ?,
            city = ?, state_province = ?, state = ?, state_code = ?,
            postal_code = ?, country = ?, country_code = ?, country_name = ?,
            is_default = ?, is_default_shipping = ?, is_default_billing = ?,
            updated_at = NOW()
         WHERE id = ? AND user_id = ?'
    );

    $isDefaultShipping = isset($data['is_default_shipping']) ? (int)(bool)$data['is_default_shipping'] : 0;
    $isDefaultBilling  = isset($data['is_default_billing'])  ? (int)(bool)$data['is_default_billing']  : 0;

    $stmt->execute([
        sanitizeText($data['label'] ?? 'Home'),
        sanitizeText($data['full_name'] ?? ''),
        sanitizeText($data['phone'] ?? ''),
        $addrLine1,
        $addrLine2,
        $addrLine1,
        $addrLine2,
        sanitizeText($data['city'] ?? ''),
        sanitizeText($data['state_province'] ?? $data['state'] ?? ''),
        sanitizeText($data['state_province'] ?? $data['state'] ?? ''),
        sanitizeText($data['state_code'] ?? ''),
        sanitizeText($data['postal_code'] ?? ''),
        $countryName,
        strtoupper(sanitizeText($data['country_code'] ?? 'US')),
        $countryName,
        $isDefaultShipping,
        $isDefaultShipping,
        $isDefaultBilling,
        $addressId,
        $userId,
    ]);

    return $stmt->rowCount() > 0;
}

/**
 * Soft-delete an address (sets deleted_at).
 *
 * @param  int $addressId
 * @param  int $userId    Ownership check
 * @return bool
 */
function deleteAddress(int $addressId, int $userId): bool
{
    $db   = getDB();
    $stmt = $db->prepare(
        'UPDATE user_addresses SET deleted_at = NOW(), is_active = 0
         WHERE id = ? AND user_id = ? AND deleted_at IS NULL'
    );
    $stmt->execute([$addressId, $userId]);
    return $stmt->rowCount() > 0;
}

/**
 * Get a single address (must belong to user and not be deleted).
 *
 * @param  int $addressId
 * @param  int $userId
 * @return array|null
 */
function getAddress(int $addressId, int $userId): ?array
{
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT * FROM user_addresses
         WHERE id = ? AND user_id = ? AND deleted_at IS NULL'
    );
    $stmt->execute([$addressId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Get all active addresses for a user.
 *
 * @param  int         $userId
 * @param  string|null $type  'shipping'|'billing'|null (null = all)
 * @return array[]
 */
function getUserAddresses(int $userId, ?string $type = null): array
{
    $db  = getDB();
    $sql = 'SELECT * FROM user_addresses WHERE user_id = ? AND deleted_at IS NULL';
    $params = [$userId];

    if ($type === 'shipping') {
        $sql .= ' AND is_default_shipping = 1';
    } elseif ($type === 'billing') {
        $sql .= ' AND is_default_billing = 1';
    }

    $sql .= ' ORDER BY is_default_shipping DESC, is_default_billing DESC, created_at DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get the default address of a given type for a user.
 *
 * @param  int    $userId
 * @param  string $type  'shipping'|'billing'
 * @return array|null
 */
function getDefaultAddress(int $userId, string $type = 'shipping'): ?array
{
    $db     = getDB();
    $column = ($type === 'billing') ? 'is_default_billing' : 'is_default_shipping';
    $stmt   = $db->prepare(
        "SELECT * FROM user_addresses
         WHERE user_id = ? AND $column = 1 AND deleted_at IS NULL
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Set an address as default shipping or billing.
 *
 * @param  int    $addressId
 * @param  int    $userId
 * @param  string $type  'shipping'|'billing'
 * @return bool
 */
function setDefaultAddress(int $addressId, int $userId, string $type = 'shipping'): bool
{
    $db     = getDB();
    $column = ($type === 'billing') ? 'is_default_billing' : 'is_default_shipping';

    // Ownership check
    $check = $db->prepare(
        'SELECT id FROM user_addresses WHERE id = ? AND user_id = ? AND deleted_at IS NULL'
    );
    $check->execute([$addressId, $userId]);
    if (!$check->fetch()) {
        return false;
    }

    // Clear current default
    $db->prepare("UPDATE user_addresses SET $column = 0 WHERE user_id = ? AND deleted_at IS NULL")
       ->execute([$userId]);

    // Set new default
    $stmt = $db->prepare("UPDATE user_addresses SET $column = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$addressId, $userId]);

    // Also keep the legacy is_default column in sync for shipping
    if ($type === 'shipping') {
        $db->prepare('UPDATE user_addresses SET is_default = 0 WHERE user_id = ? AND deleted_at IS NULL')
           ->execute([$userId]);
        $db->prepare('UPDATE user_addresses SET is_default = 1 WHERE id = ? AND user_id = ?')
           ->execute([$addressId, $userId]);
    }

    return $stmt->rowCount() > 0;
}

// ─────────────────────────────────────────────────────────────────────────────
// Validation
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Validate an address data array.
 *
 * @param  array $data
 * @return string[]  Array of error messages (empty = valid)
 */
function validateAddress(array $data): array
{
    $errors = [];

    // Required fields
    $required = ['full_name', 'city', 'country_code'];
    foreach ($required as $field) {
        $value = trim($data[$field] ?? '');
        if ($value === '') {
            $label = ucwords(str_replace('_', ' ', $field));
            $errors[] = "$label is required.";
        }
    }

    // address_line_1 — allow both naming conventions
    $line1 = trim($data['address_line_1'] ?? $data['address_line1'] ?? '');
    if ($line1 === '') {
        $errors[] = 'Address line 1 is required.';
    }

    $countryCode = strtoupper(trim($data['country_code'] ?? ''));

    // State required for US, CA, AU, IN
    if (in_array($countryCode, ['US', 'CA', 'AU', 'IN'], true)) {
        $state = trim($data['state_province'] ?? $data['state'] ?? $data['state_code'] ?? '');
        if ($state === '') {
            $errors[] = 'State/Province is required for the selected country.';
        }
    }

    // Postal code format validation
    $postalCode = trim($data['postal_code'] ?? '');
    if ($postalCode !== '' && $countryCode !== '') {
        $postalErrors = validatePostalCode($postalCode, $countryCode);
        if ($postalErrors !== null) {
            $errors[] = $postalErrors;
        }
    }

    // Phone format validation (if provided)
    $phone = trim($data['phone'] ?? '');
    if ($phone !== '' && $countryCode !== '') {
        $phoneError = validatePhoneNumber($phone, $countryCode);
        if ($phoneError !== null) {
            $errors[] = $phoneError;
        }
    }

    // full_name length
    if (strlen(trim($data['full_name'] ?? '')) > 200) {
        $errors[] = 'Full name must be 200 characters or less.';
    }

    return $errors;
}

/**
 * Validate postal code format for a country.
 *
 * @param  string $postalCode
 * @param  string $countryCode  ISO 3166-1 alpha-2
 * @return string|null  Error message or null if valid
 */
function validatePostalCode(string $postalCode, string $countryCode): ?string
{
    $countries = getCountriesData();
    $postalCode = trim($postalCode);
    $countryCode = strtoupper(trim($countryCode));

    foreach ($countries as $country) {
        if ($country['code'] === $countryCode) {
            $regex = $country['postal_regex'];
            if ($regex === '' || $regex === null) {
                return null; // No validation for this country
            }
            if (!preg_match($regex, $postalCode)) {
                return "Invalid postal code format for $countryCode.";
            }
            return null;
        }
    }

    return null; // Unknown country — skip validation
}

/**
 * Basic phone number format validation.
 *
 * @param  string $phone
 * @param  string $countryCode
 * @return string|null  Error message or null if valid
 */
function validatePhoneNumber(string $phone, string $countryCode): ?string
{
    $phone = preg_replace('/[\s\-\.\(\)]/', '', $phone);

    // Allow optional leading + for international format
    if (!preg_match('/^\+?\d{6,15}$/', $phone)) {
        return 'Invalid phone number format.';
    }

    return null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Formatting
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Format an address for display.
 *
 * @param  array  $address  Row from user_addresses
 * @param  string $format   'display'|'label'|'shipping_label'
 * @return string
 */
function formatAddress(array $address, string $format = 'display'): string
{
    $name    = $address['full_name'] ?? '';
    $line1   = $address['address_line_1'] ?? $address['address_line1'] ?? '';
    $line2   = $address['address_line_2'] ?? $address['address_line2'] ?? '';
    $city    = $address['city'] ?? '';
    $state   = $address['state_province'] ?? $address['state'] ?? '';
    $postal  = $address['postal_code'] ?? '';
    $country = $address['country_name'] ?? $address['country'] ?? ($address['country_code'] ?? '');
    $phone   = $address['phone'] ?? '';

    switch ($format) {
        case 'label':
            // Single line for checkout dropdown
            $parts = array_filter([$city, $state, $postal, $country]);
            return e($name) . ' — ' . e(implode(', ', $parts));

        case 'shipping_label':
            // Formatted for parcel label
            $lines = array_filter([
                $name,
                $line1,
                $line2,
                trim("$city" . ($state ? ", $state" : '') . ($postal ? " $postal" : '')),
                strtoupper($country),
                $phone ? "Tel: $phone" : '',
            ]);
            return implode("\n", $lines);

        case 'display':
        default:
            // Multiline HTML formatted
            $html = '<address class="mb-0">';
            if ($name)   $html .= '<strong>' . e($name) . '</strong><br>';
            if ($line1)  $html .= e($line1) . '<br>';
            if ($line2)  $html .= e($line2) . '<br>';
            $cityLine = trim($city . ($state ? ", $state" : '') . ($postal ? " $postal" : ''));
            if ($cityLine) $html .= e($cityLine) . '<br>';
            if ($country) $html .= e($country);
            if ($phone)  $html .= '<br><small class="text-muted"><i class="bi bi-telephone me-1"></i>' . e($phone) . '</small>';
            $html .= '</address>';
            return $html;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Country & State Data
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Get all countries.
 *
 * @return array[]  Each: code, name, phone_code, flag, postal_regex, has_states
 */
function getCountries(): array
{
    return getCountriesData();
}

/**
 * Get single country details by code.
 *
 * @param  string $code  ISO 3166-1 alpha-2
 * @return array|null
 */
function getCountry(string $code): ?array
{
    $code = strtoupper(trim($code));
    foreach (getCountriesData() as $country) {
        if ($country['code'] === $code) {
            return $country;
        }
    }
    return null;
}

/**
 * Get country name by code.
 */
function getCountryName(string $code): string
{
    $country = getCountry($code);
    return $country ? $country['name'] : $code;
}

/**
 * Get states/provinces for a country.
 *
 * @param  string $countryCode
 * @return array[]  Each: code, name
 */
function getStates(string $countryCode): array
{
    $statesData  = getStatesData();
    $countryCode = strtoupper(trim($countryCode));
    return $statesData[$countryCode] ?? [];
}

/**
 * Get a single state by country code + state code.
 *
 * @param  string $countryCode
 * @param  string $stateCode
 * @return array|null
 */
function getState(string $countryCode, string $stateCode): ?array
{
    $states = getStates($countryCode);
    $stateCode = strtoupper(trim($stateCode));
    foreach ($states as $state) {
        if (strtoupper($state['code']) === $stateCode) {
            return $state;
        }
    }
    return null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Auto-Complete / Suggestion
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Suggest cities for a country matching a query prefix.
 *
 * @param  string $countryCode
 * @param  string $query
 * @return string[]
 */
function suggestCities(string $countryCode, string $query): array
{
    $all   = getCitySuggestionsData();
    $code  = strtoupper(trim($countryCode));
    $query = strtolower(trim($query));
    $cities = $all[$code] ?? [];

    if ($query === '') {
        return array_slice($cities, 0, 10);
    }

    $matches = [];
    foreach ($cities as $city) {
        if (strpos(strtolower($city), $query) === 0) {
            $matches[] = $city;
        }
    }
    // Also include substring matches if prefix matches are few
    if (count($matches) < 5) {
        foreach ($cities as $city) {
            if (strpos(strtolower($city), $query) !== false && !in_array($city, $matches, true)) {
                $matches[] = $city;
            }
        }
    }

    return array_slice($matches, 0, 10);
}

/**
 * Suggest postal codes for a country/city combination.
 * Returns an array of known postal codes matching the city (basic built-in data).
 *
 * @param  string $countryCode
 * @param  string $city
 * @return string[]
 */
function suggestPostalCode(string $countryCode, string $city): array
{
    $lookupData  = getPostalLookupData();
    $code        = strtoupper(trim($countryCode));
    $cityLower   = strtolower(trim($city));
    $results     = [];

    if (!isset($lookupData[$code])) {
        return [];
    }

    foreach ($lookupData[$code] as $postal => $info) {
        if (strpos(strtolower($info['city']), $cityLower) !== false) {
            $results[] = $postal;
        }
    }

    return array_slice($results, 0, 5);
}

/**
 * Reverse-lookup city/state from postal code.
 *
 * @param  string $countryCode
 * @param  string $postalCode
 * @return array|null  ['city', 'state_code', 'state_name'] or null if not found
 */
function getAddressFromPostalCode(string $countryCode, string $postalCode): ?array
{
    $lookupData  = getPostalLookupData();
    $code        = strtoupper(trim($countryCode));
    $postalCode  = strtoupper(trim($postalCode));

    if (!isset($lookupData[$code])) {
        return null;
    }

    // Exact match first
    if (isset($lookupData[$code][$postalCode])) {
        return $lookupData[$code][$postalCode];
    }

    // For US: try without ZIP+4 extension
    if ($code === 'US') {
        $base = substr(preg_replace('/[^0-9]/', '', $postalCode), 0, 5);
        if (isset($lookupData[$code][$base])) {
            return $lookupData[$code][$base];
        }
    }

    return null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Checkout Address Integration
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Get formatted addresses for checkout dropdown.
 *
 * @param  int $userId
 * @return array[]  Each: id, label, formatted_label, full_name, address_line_1,
 *                  city, state, postal_code, country_code, is_default_shipping, is_default_billing
 */
function getCheckoutAddresses(int $userId): array
{
    $addresses = getUserAddresses($userId);
    $result    = [];

    foreach ($addresses as $addr) {
        $result[] = [
            'id'                  => (int)$addr['id'],
            'label'               => $addr['label'] ?? 'Home',
            'formatted_label'     => formatAddress($addr, 'label'),
            'full_name'           => $addr['full_name'] ?? '',
            'address_line_1'      => $addr['address_line_1'] ?? $addr['address_line1'] ?? '',
            'address_line_2'      => $addr['address_line_2'] ?? $addr['address_line2'] ?? '',
            'city'                => $addr['city'] ?? '',
            'state_province'      => $addr['state_province'] ?? $addr['state'] ?? '',
            'state_code'          => $addr['state_code'] ?? '',
            'postal_code'         => $addr['postal_code'] ?? '',
            'country_code'        => $addr['country_code'] ?? 'US',
            'country_name'        => $addr['country_name'] ?? $addr['country'] ?? '',
            'phone'               => $addr['phone'] ?? '',
            'is_default_shipping' => (int)($addr['is_default_shipping'] ?? 0),
            'is_default_billing'  => (int)($addr['is_default_billing']  ?? 0),
        ];
    }

    return $result;
}

/**
 * Set the checkout address in session.
 *
 * @param  int    $userId
 * @param  int    $addressId
 * @param  string $type  'shipping'|'billing'
 * @return bool
 */
function selectCheckoutAddress(int $userId, int $addressId, string $type = 'shipping'): bool
{
    $address = getAddress($addressId, $userId);
    if (!$address) {
        return false;
    }

    $key = ($type === 'billing') ? '_checkout_billing_address_id' : '_checkout_shipping_address_id';
    $_SESSION[$key] = $addressId;
    return true;
}

/**
 * Get the currently selected checkout address from session.
 *
 * @param  string $type  'shipping'|'billing'
 * @return int|null  Address ID or null
 */
function getSelectedCheckoutAddress(string $type = 'shipping'): ?int
{
    $key = ($type === 'billing') ? '_checkout_billing_address_id' : '_checkout_shipping_address_id';
    return isset($_SESSION[$key]) ? (int)$_SESSION[$key] : null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Sanitize a plain text input value (XSS prevention).
 */
function sanitizeText(string $value): string
{
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Get the count of active addresses for a user.
 */
function getAddressCount(int $userId): int
{
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM user_addresses WHERE user_id = ? AND deleted_at IS NULL'
    );
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}
