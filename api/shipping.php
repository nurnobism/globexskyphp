<?php
/**
 * api/shipping.php — Shipping Calculator API (PR #14)
 *
 * Actions:
 *   calculate       POST  Calculate shipping rates for cart + address
 *   methods         GET   Get available shipping methods for an address
 *   zones           GET   Get all shipping zones (admin)
 *   create_zone     POST  Create shipping zone (admin)
 *   update_zone     POST  Update zone (admin)
 *   delete_zone     POST  Delete zone (admin)
 *   zone_methods    GET   Get methods for a zone (admin)
 *   create_method   POST  Create shipping method (admin)
 *   update_method   POST  Update method (admin)
 *   delete_method   POST  Delete method (admin)
 *   templates       GET   Get supplier shipping templates (supplier)
 *   create_template POST  Create template (supplier)
 *   update_template POST  Update template (supplier)
 *   delete_template POST  Delete template (supplier)
 *   estimate        GET   Quick shipping estimate (product page)
 */

require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/shipping.php';

header('Content-Type: application/json');

if (!isFeatureEnabled('shipping_calculator')) {
    http_response_code(503);
    echo json_encode(['error' => 'Shipping calculator is disabled']);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

/** @return never */
function shippingJson(mixed $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

switch ($action) {

    // ── calculate ──────────────────────────────────────────────────────────
    case 'calculate':
        if ($method !== 'POST') shippingJson(['error' => 'POST required'], 405);

        requireLogin();

        $userId    = (int)$_SESSION['user_id'];
        $addressId = (int)($_POST['address_id'] ?? 0);
        $methodId  = isset($_POST['method_id']) ? (int)$_POST['method_id'] : null;

        // Accept either address_id or country_code/state_code directly
        if ($addressId > 0) {
            $rates = getShippingRates(getCart($userId), $addressId);
        } else {
            $countryCode = strtoupper(trim($_POST['country_code'] ?? ''));
            $stateCode   = strtoupper(trim($_POST['state_code']   ?? ''));
            if ($countryCode === '') shippingJson(['error' => 'address_id or country_code required'], 400);

            $cartItems = function_exists('getCart') ? getCart($userId) : [];
            $rates     = calculateShipping($cartItems, ['country' => $countryCode, 'state' => $stateCode], $methodId);
        }

        shippingJson(['success' => true, 'data' => $rates]);

    // ── methods ────────────────────────────────────────────────────────────
    case 'methods':
        requireLogin();

        $countryCode = strtoupper(trim($_GET['country_code'] ?? ''));
        $stateCode   = strtoupper(trim($_GET['state_code']   ?? ''));

        if ($countryCode === '') shippingJson(['error' => 'country_code required'], 400);

        $zone    = getZoneForAddress($countryCode, $stateCode);
        $methods = $zone ? getShippingMethods((int)$zone['id']) : [];

        shippingJson(['success' => true, 'data' => ['zone' => $zone, 'methods' => $methods]]);

    // ── zones (admin) ──────────────────────────────────────────────────────
    case 'zones':
        requireAdmin();
        shippingJson(['success' => true, 'data' => getShippingZones()]);

    // ── create_zone (admin) ────────────────────────────────────────────────
    case 'create_zone':
        requireAdmin();
        if ($method !== 'POST') shippingJson(['error' => 'POST required'], 405);
        verifyCsrf();

        $name = trim($_POST['name'] ?? '');
        if ($name === '') shippingJson(['error' => 'name is required'], 400);

        $countries = json_decode($_POST['countries'] ?? '[]', true) ?: [];
        $states    = json_decode($_POST['states']    ?? '[]', true) ?: [];

        $zoneId = createShippingZone([
            'name'       => $name,
            'countries'  => $countries,
            'states'     => $states,
            'is_default' => !empty($_POST['is_default']),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active'  => !isset($_POST['is_active']) || !empty($_POST['is_active']),
        ]);

        if ($zoneId === false) shippingJson(['error' => 'Failed to create zone'], 500);
        shippingJson(['success' => true, 'zone_id' => $zoneId], 201);

    // ── update_zone (admin) ────────────────────────────────────────────────
    case 'update_zone':
        requireAdmin();
        if ($method !== 'POST') shippingJson(['error' => 'POST required'], 405);
        verifyCsrf();

        $zoneId = (int)($_POST['zone_id'] ?? 0);
        if ($zoneId <= 0) shippingJson(['error' => 'zone_id required'], 400);

        $data = [];
        foreach (['name', 'sort_order', 'is_default', 'is_active'] as $k) {
            if (isset($_POST[$k])) $data[$k] = $_POST[$k];
        }
        if (isset($_POST['countries'])) $data['countries'] = json_decode($_POST['countries'], true) ?: [];
        if (isset($_POST['states']))    $data['states']    = json_decode($_POST['states'],    true) ?: [];

        $ok = updateShippingZone($zoneId, $data);
        shippingJson(['success' => $ok]);

    // ── delete_zone (admin) ────────────────────────────────────────────────
    case 'delete_zone':
        requireAdmin();
        if ($method !== 'POST') shippingJson(['error' => 'POST required'], 405);
        verifyCsrf();

        $zoneId = (int)($_POST['zone_id'] ?? 0);
        if ($zoneId <= 0) shippingJson(['error' => 'zone_id required'], 400);

        shippingJson(['success' => deleteShippingZone($zoneId)]);

    // ── zone_methods (admin) ───────────────────────────────────────────────
    case 'zone_methods':
        requireAdmin();

        $zoneId = (int)($_GET['zone_id'] ?? 0);
        if ($zoneId <= 0) shippingJson(['error' => 'zone_id required'], 400);

        // Admin sees all methods (including inactive)
        try {
            $db   = getDB();
            $stmt = $db->prepare(
                'SELECT * FROM shipping_methods WHERE zone_id = ? ORDER BY sort_order ASC, id ASC'
            );
            $stmt->execute([$zoneId]);
            $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $methods = [];
        }

        shippingJson(['success' => true, 'data' => $methods]);

    // ── create_method (admin) ──────────────────────────────────────────────
    case 'create_method':
        requireAdmin();
        if ($method !== 'POST') shippingJson(['error' => 'POST required'], 405);
        verifyCsrf();

        $zoneId = (int)($_POST['zone_id'] ?? 0);
        if ($zoneId <= 0) shippingJson(['error' => 'zone_id required'], 400);

        $name = trim($_POST['name'] ?? '');
        if ($name === '') shippingJson(['error' => 'name is required'], 400);

        $methodId = createShippingMethod($zoneId, [
            'name'               => $name,
            'type'               => $_POST['type']               ?? 'flat_rate',
            'base_cost'          => $_POST['base_cost']          ?? 0,
            'per_kg_cost'        => $_POST['per_kg_cost']        ?? 0,
            'per_item_cost'      => $_POST['per_item_cost']      ?? 0,
            'free_above_amount'  => $_POST['free_above_amount']  ?? 0,
            'estimated_days_min' => $_POST['estimated_days_min'] ?? 1,
            'estimated_days_max' => $_POST['estimated_days_max'] ?? 7,
            'is_active'          => !isset($_POST['is_active']) || !empty($_POST['is_active']),
            'sort_order'         => $_POST['sort_order']         ?? 0,
        ]);

        if ($methodId === false) shippingJson(['error' => 'Failed to create method'], 500);
        shippingJson(['success' => true, 'method_id' => $methodId], 201);

    // ── update_method (admin) ──────────────────────────────────────────────
    case 'update_method':
        requireAdmin();
        if ($method !== 'POST') shippingJson(['error' => 'POST required'], 405);
        verifyCsrf();

        $methodId = (int)($_POST['method_id'] ?? 0);
        if ($methodId <= 0) shippingJson(['error' => 'method_id required'], 400);

        $data = [];
        foreach (['name','type','base_cost','per_kg_cost','per_item_cost',
                  'free_above_amount','estimated_days_min','estimated_days_max',
                  'is_active','sort_order'] as $k) {
            if (isset($_POST[$k])) $data[$k] = $_POST[$k];
        }

        shippingJson(['success' => updateShippingMethod($methodId, $data)]);

    // ── delete_method (admin) ──────────────────────────────────────────────
    case 'delete_method':
        requireAdmin();
        if ($method !== 'POST') shippingJson(['error' => 'POST required'], 405);
        verifyCsrf();

        $methodId = (int)($_POST['method_id'] ?? 0);
        if ($methodId <= 0) shippingJson(['error' => 'method_id required'], 400);

        shippingJson(['success' => deleteShippingMethod($methodId)]);

    // ── templates (supplier) ───────────────────────────────────────────────
    case 'templates':
        requireLogin();
        $supplierId = (int)($_SESSION['supplier_id'] ?? $_SESSION['user_id'] ?? 0);
        if ($supplierId <= 0) shippingJson(['error' => 'Supplier ID required'], 400);

        $templates = getSupplierShippingTemplates($supplierId);
        $limit     = getShippingTemplatePlanLimit($supplierId);

        shippingJson(['success' => true, 'data' => $templates, 'limit' => $limit, 'used' => count($templates)]);

    // ── create_template (supplier) ─────────────────────────────────────────
    case 'create_template':
        requireLogin();
        if ($method !== 'POST') shippingJson(['error' => 'POST required'], 405);
        verifyCsrf();

        $supplierId = (int)($_SESSION['supplier_id'] ?? $_SESSION['user_id'] ?? 0);
        if ($supplierId <= 0) shippingJson(['error' => 'Supplier ID required'], 400);

        $name = trim($_POST['name'] ?? '');
        if ($name === '') shippingJson(['error' => 'name is required'], 400);

        $zones = json_decode($_POST['zones'] ?? '[]', true) ?: [];

        $templateId = createShippingTemplate($supplierId, [
            'name'               => $name,
            'handling_time_days' => (int)($_POST['handling_time_days'] ?? 1),
            'is_default'         => !empty($_POST['is_default']),
            'zones'              => $zones,
        ]);

        if ($templateId === false) {
            shippingJson(['error' => 'Failed to create template. Plan limit may have been reached.'], 422);
        }
        shippingJson(['success' => true, 'template_id' => $templateId], 201);

    // ── update_template (supplier) ─────────────────────────────────────────
    case 'update_template':
        requireLogin();
        if ($method !== 'POST') shippingJson(['error' => 'POST required'], 405);
        verifyCsrf();

        $supplierId = (int)($_SESSION['supplier_id'] ?? $_SESSION['user_id'] ?? 0);
        $templateId = (int)($_POST['template_id'] ?? 0);
        if ($templateId <= 0) shippingJson(['error' => 'template_id required'], 400);

        // Ownership check
        $tpl = getShippingTemplate($templateId);
        if (!$tpl || (int)$tpl['supplier_id'] !== $supplierId) {
            shippingJson(['error' => 'Template not found or access denied'], 403);
        }

        $data = [];
        foreach (['name', 'handling_time_days', 'is_default'] as $k) {
            if (isset($_POST[$k])) $data[$k] = $_POST[$k];
        }
        if (isset($_POST['zones'])) $data['zones'] = json_decode($_POST['zones'], true) ?: [];

        shippingJson(['success' => updateShippingTemplate($templateId, $data)]);

    // ── delete_template (supplier) ─────────────────────────────────────────
    case 'delete_template':
        requireLogin();
        if ($method !== 'POST') shippingJson(['error' => 'POST required'], 405);
        verifyCsrf();

        $supplierId = (int)($_SESSION['supplier_id'] ?? $_SESSION['user_id'] ?? 0);
        $templateId = (int)($_POST['template_id'] ?? 0);
        if ($templateId <= 0) shippingJson(['error' => 'template_id required'], 400);

        $tpl = getShippingTemplate($templateId);
        if (!$tpl || (int)$tpl['supplier_id'] !== $supplierId) {
            shippingJson(['error' => 'Template not found or access denied'], 403);
        }

        shippingJson(['success' => deleteShippingTemplate($templateId)]);

    // ── estimate (product page) ────────────────────────────────────────────
    case 'estimate':
        $productId   = (int)($_GET['product_id']   ?? 0);
        $countryCode = strtoupper(trim($_GET['country_code'] ?? ''));

        if ($countryCode === '') shippingJson(['error' => 'country_code required'], 400);

        $zone    = getZoneForAddress($countryCode);
        $methods = $zone ? getShippingMethods((int)$zone['id']) : [];

        if (empty($methods)) {
            shippingJson(['success' => true, 'data' => ['available' => false, 'message' => 'No shipping available to this location']]);
        }

        // Determine product weight (from product_shipping if available)
        $weightKg = 0.0;
        if ($productId > 0) {
            try {
                $db   = getDB();
                $stmt = $db->prepare('SELECT weight_kg FROM product_shipping WHERE product_id = ? LIMIT 1');
                $stmt->execute([$productId]);
                $ps = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($ps) $weightKg = (float)$ps['weight_kg'];
            } catch (PDOException $e) { /* ignore */ }
        }

        $subtotal = 0.0; // no cart context for estimate
        $options  = [];
        foreach ($methods as $m) {
            $cost = calculateMethodCost($m, $weightKg, $subtotal, 1);
            if ($cost < 0) continue;
            $options[] = [
                'id'                 => (int)$m['id'],
                'name'               => $m['name'],
                'cost'               => round($cost, 2),
                'estimated_days_min' => (int)$m['estimated_days_min'],
                'delivery_estimate'  => getEstimatedDeliveryLabel((int)$m['estimated_days_min'], (int)$m['estimated_days_max']),
            ];
        }

        // Cheapest and fastest
        $cheapest = null;
        $fastest  = null;
        foreach ($options as $opt) {
            if ($cheapest === null || $opt['cost'] < $cheapest['cost']) $cheapest = $opt;
        }
        foreach ($options as $opt) {
            if ($fastest === null || $opt['estimated_days_min'] < $fastest['estimated_days_min']) $fastest = $opt;
        }

        // Handling time
        $handlingDays = 1;
        if ($productId > 0) {
            try {
                $db   = getDB();
                $stmt = $db->prepare(
                    'SELECT st.handling_time_days FROM product_shipping ps
                     JOIN shipping_templates st ON st.id = ps.template_id
                     WHERE ps.product_id = ? LIMIT 1'
                );
                $stmt->execute([$productId]);
                $ht = $stmt->fetchColumn();
                if ($ht !== false) $handlingDays = (int)$ht;
            } catch (PDOException $e) { /* ignore */ }
        }

        shippingJson([
            'success' => true,
            'data'    => [
                'available'     => !empty($options),
                'zone'          => $zone ? $zone['name'] : null,
                'options'       => $options,
                'cheapest'      => $cheapest,
                'fastest'       => $fastest,
                'handling_days' => $handlingDays,
            ],
        ]);

    default:
        shippingJson(['error' => 'Unknown action'], 400);
}
