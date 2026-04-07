<?php
/**
 * api/cart.php — Shopping Cart API
 *
 * Actions (GET): get, count, validate
 * Actions (POST): add, update, remove, clear, merge
 *
 * Guest users: session cart. Authenticated users: DB cart.
 * Feature toggle: cart_checkout must be enabled.
 */

require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/cart.php';

if (!isFeatureEnabled('cart_checkout')) {
    jsonResponse(['success' => false, 'message' => 'Cart & checkout is currently disabled.'], 503);
}

$action = $_GET['action'] ?? ($_POST['action'] ?? 'get');
$method = $_SERVER['REQUEST_METHOD'];
$userId = isLoggedIn() ? (int)$_SESSION['user_id'] : 0;

switch ($action) {

    // ── GET: full cart contents ──────────────────────────────
    case 'get':
    case 'list':
        $grouped = getCartGroupedBySupplier($userId);
        $total   = getCartTotal($userId);
        $count   = getCartCount($userId);
        jsonResponse([
            'success' => true,
            'data'    => [
                'groups' => $grouped,
                'total'  => $total,
                'count'  => $count,
            ],
        ]);
        break;

    // ── GET: cart item count (badge) ─────────────────────────
    case 'count':
        jsonResponse(['success' => true, 'count' => getCartCount($userId)]);
        break;

    // ── GET: validate cart stock before checkout ─────────────
    case 'validate':
        $issues = validateCartStock($userId);
        jsonResponse([
            'success' => true,
            'all_clear' => empty($issues),
            'issues'  => $issues,
        ]);
        break;

    // ── POST: add item to cart ───────────────────────────────
    case 'add':
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
        }
        if (!verifyCsrf()) {
            jsonResponse(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
        }

        $productId = (int)($_POST['product_id'] ?? 0);
        $variantId = (int)($_POST['variant_id'] ?? 0) ?: null;
        $quantity  = max(1, (int)($_POST['quantity'] ?? 1));

        if (!$productId) {
            jsonResponse(['success' => false, 'message' => 'Product ID required.'], 400);
        }

        $result = addToCart($userId, $productId, $variantId, $quantity);
        if (!empty($result['error'])) {
            jsonResponse(['success' => false, 'message' => $result['error']], 400);
        }

        if (isset($_POST['_redirect'])) {
            flashMessage('success', 'Item added to cart!');
            redirect($_POST['_redirect']);
        }

        jsonResponse([
            'success' => true,
            'message' => 'Item added to cart.',
            'count'   => $result['count'] ?? getCartCount($userId),
        ]);
        break;

    // ── POST: update cart item quantity ──────────────────────
    case 'update':
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
        }
        if (!verifyCsrf()) {
            jsonResponse(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
        }

        $itemId   = (int)($_POST['cart_item_id'] ?? $_POST['item_id'] ?? 0);
        $quantity = max(0, (int)($_POST['quantity'] ?? 1));

        if (!$itemId) {
            jsonResponse(['success' => false, 'message' => 'Cart item ID required.'], 400);
        }

        $result = updateCartItem($userId, $itemId, $quantity);
        if (!empty($result['error'])) {
            jsonResponse(['success' => false, 'message' => $result['error']], 400);
        }

        if (isset($_POST['_redirect'])) {
            redirect($_POST['_redirect']);
        }

        $total = getCartTotal($userId);
        jsonResponse([
            'success' => true,
            'total'   => $total,
            'count'   => getCartCount($userId),
        ]);
        break;

    // ── POST: remove cart item ───────────────────────────────
    case 'remove':
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
        }
        if (!verifyCsrf()) {
            jsonResponse(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
        }

        $itemId = (int)($_POST['cart_item_id'] ?? $_POST['item_id'] ?? 0);

        if (!$itemId) {
            jsonResponse(['success' => false, 'message' => 'Cart item ID required.'], 400);
        }

        $result = removeFromCart($userId, $itemId);
        if (!empty($result['error'])) {
            jsonResponse(['success' => false, 'message' => $result['error']], 400);
        }

        if (isset($_POST['_redirect'])) {
            redirect($_POST['_redirect']);
        }

        jsonResponse([
            'success' => true,
            'count'   => getCartCount($userId),
            'total'   => getCartTotal($userId),
        ]);
        break;

    // ── POST: clear entire cart ──────────────────────────────
    case 'clear':
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
        }
        if (!verifyCsrf()) {
            jsonResponse(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
        }

        $result = clearCart($userId);
        if (!empty($result['error'])) {
            jsonResponse(['success' => false, 'message' => $result['error']], 400);
        }

        if (isset($_POST['_redirect'])) {
            redirect($_POST['_redirect']);
        }

        jsonResponse(['success' => true, 'count' => 0, 'total' => 0]);
        break;

    // ── POST: merge session cart to DB (called on login) ─────
    case 'merge':
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
        }
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'message' => 'Login required.'], 401);
        }
        if (!verifyCsrf()) {
            jsonResponse(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
        }

        mergeSessionCartToDb($userId);
        jsonResponse([
            'success' => true,
            'count'   => getCartCount($userId),
        ]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action.'], 400);
}
