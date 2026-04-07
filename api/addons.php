<?php
/**
 * api/addons.php — Add-On Store API (PR #10)
 *
 * Actions:
 *   catalog    — GET:  Get all available add-ons with pricing (public)
 *   purchase   — POST: Purchase an add-on (supplier only)
 *   my_addons  — GET:  Get supplier's active add-ons and credits
 *   history    — GET:  Purchase history with pagination
 *   check      — GET:  Check if add-on is active for a target
 */

require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/addons.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

function addonsJson(mixed $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

switch ($action) {

    // ── Get all add-ons (public) ──────────────────────────────────────
    case 'catalog':
        $addons = getAddons();
        addonsJson(['success' => true, 'addons' => $addons]);

    // ── Purchase an add-on ────────────────────────────────────────────
    case 'purchase':
        requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') addonsJson(['error' => 'POST required'], 405);
        if (!verifyCsrf()) addonsJson(['error' => 'Invalid CSRF token'], 403);

        $addonId         = (int)($_POST['addon_id'] ?? 0);
        $quantity        = max(1, (int)($_POST['quantity'] ?? 1));
        $targetProductId = (int)($_POST['target_product_id'] ?? 0);
        $stripePaymentId = trim($_POST['stripe_payment_id'] ?? '');

        if ($addonId <= 0) addonsJson(['error' => 'addon_id required'], 400);

        $supplierId = (int)$_SESSION['user_id'];

        $options = [];
        if ($targetProductId > 0) $options['target_product_id'] = $targetProductId;
        if ($stripePaymentId)     $options['stripe_payment_id']  = $stripePaymentId;

        $result = purchaseAddon($supplierId, $addonId, $quantity, $options);
        if (!$result['success']) addonsJson(['error' => $result['error'] ?? 'Purchase failed'], 400);
        addonsJson($result);

    // ── Get supplier's active add-ons ─────────────────────────────────
    case 'my_addons':
        requireLogin();
        $supplierId = (int)$_SESSION['user_id'];

        $activeAddons = getSupplierAddons($supplierId);

        // Credits summary
        $creditTypes = ['api_calls_pack', 'translation_credit', 'livestream_session'];
        $credits = [];
        foreach ($creditTypes as $ct) {
            $credits[$ct] = getRemainingCredits($supplierId, $ct);
        }

        // Effective limits
        $limits = [
            'products'           => getEffectiveLimit($supplierId, 'products'),
            'images_per_product' => getEffectiveLimit($supplierId, 'images_per_product'),
        ];

        addonsJson([
            'success'      => true,
            'active_addons' => $activeAddons,
            'credits'      => $credits,
            'limits'       => $limits,
        ]);

    // ── Purchase history ──────────────────────────────────────────────
    case 'history':
        requireLogin();
        $supplierId = (int)$_SESSION['user_id'];
        $page       = max(1, (int)($_GET['page'] ?? 1));
        $perPage    = min(100, max(1, (int)($_GET['per_page'] ?? 20)));

        $history = getAddonPurchaseHistory($supplierId, $page, $perPage);
        addonsJson(['success' => true] + $history);

    // ── Check if add-on is active for a target ────────────────────────
    case 'check':
        requireLogin();
        $supplierId = (int)$_SESSION['user_id'];
        $addonType  = trim($_GET['addon_type'] ?? '');
        $targetId   = (int)($_GET['target_id'] ?? 0);

        if (!$addonType) addonsJson(['error' => 'addon_type required'], 400);

        $active = isAddonActive($supplierId, $addonType, $targetId);
        addonsJson(['success' => true, 'active' => $active]);

    default:
        addonsJson(['error' => 'Unknown action'], 400);
}
