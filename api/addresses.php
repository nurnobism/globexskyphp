<?php
/**
 * api/addresses.php — Address Management API (PR #17)
 *
 * Actions:
 *   list          GET   Get user's addresses (type: shipping|billing|all)
 *   get           GET   Get single address (address_id)
 *   create        POST  Add new address
 *   update        POST  Update address (address_id + fields)
 *   delete        POST  Soft-delete address (address_id)
 *   set_default   POST  Set as default (address_id, type: shipping|billing)
 *   validate      POST  Validate address without saving
 *   countries     GET   Get country list (public)
 *   states        GET   Get states for a country (country_code)
 *   suggest_city  GET   City auto-complete (country_code, query)
 *   postal_lookup GET   Reverse postal lookup (country_code, postal_code)
 */

require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/addresses.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$method = $_SERVER['REQUEST_METHOD'];

function addrJson(mixed $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function addrCsrf(): void
{
    if (!verifyCsrf()) {
        addrJson(['success' => false, 'message' => 'Invalid CSRF token'], 403);
    }
}

function addrGetParam(string $key, string $default = ''): string
{
    return trim(htmlspecialchars($_GET[$key] ?? $default, ENT_QUOTES, 'UTF-8'));
}

function addrPostParam(string $key, string $default = ''): string
{
    return trim(htmlspecialchars($_POST[$key] ?? $default, ENT_QUOTES, 'UTF-8'));
}

switch ($action) {

    // ── GET: list addresses ──────────────────────────────────────────────────
    case 'list':
        requireLogin();
        $userId = (int)$_SESSION['user_id'];
        $type   = addrGetParam('type') ?: null;
        if ($type !== null && !in_array($type, ['shipping', 'billing', 'all'], true)) {
            $type = null;
        }
        $addresses = getUserAddresses($userId, $type === 'all' ? null : $type);
        $count     = getAddressCount($userId);
        addrJson([
            'success'   => true,
            'addresses' => $addresses,
            'count'     => $count,
            'max'       => ADDRESS_MAX_PER_USER,
        ]);
        break;

    // ── GET: single address ──────────────────────────────────────────────────
    case 'get':
        requireLogin();
        $userId    = (int)$_SESSION['user_id'];
        $addressId = (int)addrGetParam('address_id');
        if ($addressId <= 0) {
            addrJson(['success' => false, 'message' => 'address_id required'], 400);
        }
        $addr = getAddress($addressId, $userId);
        if (!$addr) {
            addrJson(['success' => false, 'message' => 'Address not found'], 404);
        }
        addrJson(['success' => true, 'address' => $addr]);
        break;

    // ── POST: create address ─────────────────────────────────────────────────
    case 'create':
        if ($method !== 'POST') addrJson(['success' => false, 'message' => 'Method not allowed'], 405);
        requireLogin();
        addrCsrf();
        $userId = (int)$_SESSION['user_id'];

        $data = [
            'label'              => addrPostParam('label', 'Home'),
            'full_name'          => addrPostParam('full_name'),
            'phone'              => addrPostParam('phone'),
            'address_line_1'     => addrPostParam('address_line_1') ?: addrPostParam('address_line1'),
            'address_line_2'     => addrPostParam('address_line_2') ?: addrPostParam('address_line2'),
            'city'               => addrPostParam('city'),
            'state_province'     => addrPostParam('state_province') ?: addrPostParam('state'),
            'state_code'         => addrPostParam('state_code'),
            'postal_code'        => addrPostParam('postal_code'),
            'country_code'       => strtoupper(addrPostParam('country_code', 'US')),
            'is_default_shipping'=> isset($_POST['is_default_shipping']) ? (bool)$_POST['is_default_shipping'] : false,
            'is_default_billing' => isset($_POST['is_default_billing'])  ? (bool)$_POST['is_default_billing']  : false,
        ];

        try {
            $id = createAddress($userId, $data);
            addrJson(['success' => true, 'address_id' => $id, 'message' => 'Address created successfully.']);
        } catch (InvalidArgumentException $e) {
            addrJson(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            addrJson(['success' => false, 'message' => $e->getMessage()], 400);
        }
        break;

    // ── POST: update address ─────────────────────────────────────────────────
    case 'update':
        if ($method !== 'POST') addrJson(['success' => false, 'message' => 'Method not allowed'], 405);
        requireLogin();
        addrCsrf();
        $userId    = (int)$_SESSION['user_id'];
        $addressId = (int)addrPostParam('address_id');
        if ($addressId <= 0) {
            addrJson(['success' => false, 'message' => 'address_id required'], 400);
        }

        $data = [
            'label'              => addrPostParam('label', 'Home'),
            'full_name'          => addrPostParam('full_name'),
            'phone'              => addrPostParam('phone'),
            'address_line_1'     => addrPostParam('address_line_1') ?: addrPostParam('address_line1'),
            'address_line_2'     => addrPostParam('address_line_2') ?: addrPostParam('address_line2'),
            'city'               => addrPostParam('city'),
            'state_province'     => addrPostParam('state_province') ?: addrPostParam('state'),
            'state_code'         => addrPostParam('state_code'),
            'postal_code'        => addrPostParam('postal_code'),
            'country_code'       => strtoupper(addrPostParam('country_code', 'US')),
            'is_default_shipping'=> isset($_POST['is_default_shipping']) ? (bool)$_POST['is_default_shipping'] : false,
            'is_default_billing' => isset($_POST['is_default_billing'])  ? (bool)$_POST['is_default_billing']  : false,
        ];

        try {
            $updated = updateAddress($addressId, $userId, $data);
            addrJson(['success' => true, 'updated' => $updated, 'message' => 'Address updated successfully.']);
        } catch (InvalidArgumentException $e) {
            addrJson(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            addrJson(['success' => false, 'message' => $e->getMessage()], 400);
        }
        break;

    // ── POST: delete address ─────────────────────────────────────────────────
    case 'delete':
        if ($method !== 'POST') addrJson(['success' => false, 'message' => 'Method not allowed'], 405);
        requireLogin();
        addrCsrf();
        $userId    = (int)$_SESSION['user_id'];
        $addressId = (int)addrPostParam('address_id');
        if ($addressId <= 0) {
            addrJson(['success' => false, 'message' => 'address_id required'], 400);
        }
        $deleted = deleteAddress($addressId, $userId);
        if (!$deleted) {
            addrJson(['success' => false, 'message' => 'Address not found or already deleted.'], 404);
        }
        addrJson(['success' => true, 'message' => 'Address deleted successfully.']);
        break;

    // ── POST: set default ────────────────────────────────────────────────────
    case 'set_default':
        if ($method !== 'POST') addrJson(['success' => false, 'message' => 'Method not allowed'], 405);
        requireLogin();
        addrCsrf();
        $userId    = (int)$_SESSION['user_id'];
        $addressId = (int)addrPostParam('address_id');
        $type      = addrPostParam('type', 'shipping');
        if (!in_array($type, ['shipping', 'billing'], true)) {
            $type = 'shipping';
        }
        if ($addressId <= 0) {
            addrJson(['success' => false, 'message' => 'address_id required'], 400);
        }
        $result = setDefaultAddress($addressId, $userId, $type);
        if (!$result) {
            addrJson(['success' => false, 'message' => 'Address not found or access denied.'], 404);
        }
        addrJson(['success' => true, 'message' => ucfirst($type) . ' default address updated.']);
        break;

    // ── POST: validate address without saving ────────────────────────────────
    case 'validate':
        if ($method !== 'POST') addrJson(['success' => false, 'message' => 'Method not allowed'], 405);
        $data = [
            'full_name'      => addrPostParam('full_name'),
            'address_line_1' => addrPostParam('address_line_1') ?: addrPostParam('address_line1'),
            'city'           => addrPostParam('city'),
            'state_province' => addrPostParam('state_province') ?: addrPostParam('state'),
            'postal_code'    => addrPostParam('postal_code'),
            'country_code'   => strtoupper(addrPostParam('country_code', 'US')),
            'phone'          => addrPostParam('phone'),
        ];
        $errors = validateAddress($data);
        if (!empty($errors)) {
            addrJson(['success' => false, 'valid' => false, 'errors' => $errors], 422);
        }
        // Return preview
        $preview = formatAddress($data, 'display');
        addrJson(['success' => true, 'valid' => true, 'preview' => $preview]);
        break;

    // ── GET: countries (public, no auth) ─────────────────────────────────────
    case 'countries':
        $countries = getCountries();
        // Strip regex from public output for security
        $safe = array_map(static function (array $c): array {
            return [
                'code'       => $c['code'],
                'name'       => $c['name'],
                'phone_code' => $c['phone_code'],
                'flag'       => $c['flag'],
                'has_states' => $c['has_states'],
            ];
        }, $countries);
        addrJson(['success' => true, 'countries' => $safe]);
        break;

    // ── GET: states for a country ────────────────────────────────────────────
    case 'states':
        $countryCode = strtoupper(addrGetParam('country_code', 'US'));
        $states      = getStates($countryCode);
        addrJson(['success' => true, 'country_code' => $countryCode, 'states' => $states]);
        break;

    // ── GET: city auto-complete ──────────────────────────────────────────────
    case 'suggest_city':
        $countryCode = strtoupper(addrGetParam('country_code', 'US'));
        $query       = addrGetParam('query');
        if (strlen($query) < 1) {
            addrJson(['success' => false, 'message' => 'query parameter required'], 400);
        }
        $cities = suggestCities($countryCode, $query);
        addrJson(['success' => true, 'cities' => $cities]);
        break;

    // ── GET: postal code reverse lookup ─────────────────────────────────────
    case 'postal_lookup':
        $countryCode = strtoupper(addrGetParam('country_code', 'US'));
        $postalCode  = addrGetParam('postal_code');
        if ($postalCode === '') {
            addrJson(['success' => false, 'message' => 'postal_code parameter required'], 400);
        }
        $result = getAddressFromPostalCode($countryCode, $postalCode);
        if ($result === null) {
            addrJson(['success' => false, 'message' => 'Postal code not found'], 404);
        }
        addrJson(['success' => true, 'data' => $result]);
        break;

    default:
        addrJson(['success' => false, 'message' => 'Unknown action'], 400);
}
