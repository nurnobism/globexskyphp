<?php
/**
 * api/wishlist.php — Wishlist API
 *
 * Actions (GET):  get, count, check
 * Actions (POST): add, remove, move_to_cart
 *
 * All operations require authentication (wishlist is user-only).
 */
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/wishlist.php';
require_once __DIR__ . '/../includes/cart.php';

requireLogin();

$action = $_GET['action'] ?? ($_POST['action'] ?? 'get');
$method = $_SERVER['REQUEST_METHOD'];
$uid    = (int)$_SESSION['user_id'];

switch ($action) {

    // ── GET: wishlist items (paginated) ──────────────────────
    case 'get':
    case 'list':
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 20)));
        $sort    = $_GET['sort'] ?? 'date_added';
        jsonResponse(['success' => true, 'data' => getWishlist($uid, $page, $perPage, $sort)]);
        break;

    // ── GET: wishlist item count ─────────────────────────────
    case 'count':
        jsonResponse(['success' => true, 'count' => getWishlistCount($uid)]);
        break;

    // ── GET: check if product is in wishlist ─────────────────
    case 'check':
        $productId = (int)($_GET['product_id'] ?? 0);
        if (!$productId) {
            jsonResponse(['success' => false, 'message' => 'Product ID required.'], 400);
        }
        jsonResponse(array_merge(['success' => true], isInWishlist($uid, $productId)));
        break;

    // ── POST: add to wishlist ────────────────────────────────
    case 'add':
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
        }
        if (!verifyCsrf()) {
            jsonResponse(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
        }

        $productId = (int)($_POST['product_id'] ?? 0);
        if (!$productId) {
            jsonResponse(['success' => false, 'message' => 'Product ID required.'], 400);
        }

        $result = addToWishlist($uid, $productId);
        if (!empty($result['error'])) {
            jsonResponse(['success' => false, 'message' => $result['error']], 400);
        }

        if (isset($_POST['_redirect'])) {
            flashMessage('success', 'Added to wishlist!');
            redirect($_POST['_redirect']);
        }

        jsonResponse([
            'success' => true,
            'message' => $result['message'] ?? 'Added to wishlist.',
            'count'   => $result['count'] ?? getWishlistCount($uid),
        ]);
        break;

    // ── POST: remove from wishlist ───────────────────────────
    case 'remove':
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
        }
        if (!verifyCsrf()) {
            jsonResponse(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
        }

        $productId = (int)($_POST['product_id'] ?? 0);
        if (!$productId) {
            jsonResponse(['success' => false, 'message' => 'Product ID required.'], 400);
        }

        $result = removeFromWishlist($uid, $productId);

        if (isset($_POST['_redirect'])) {
            flashMessage('success', 'Removed from wishlist.');
            redirect($_POST['_redirect']);
        }

        jsonResponse([
            'success' => true,
            'count'   => $result['count'] ?? getWishlistCount($uid),
        ]);
        break;

    // ── POST: move item from wishlist to cart ────────────────
    case 'move_to_cart':
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

        $result = moveToCart($uid, $productId, $variantId, $quantity);
        if (!empty($result['error'])) {
            jsonResponse(['success' => false, 'message' => $result['error']], 400);
        }

        if (isset($_POST['_redirect'])) {
            flashMessage('success', 'Item moved to cart!');
            redirect($_POST['_redirect']);
        }

        jsonResponse([
            'success'       => true,
            'message'       => 'Item moved to cart.',
            'cart_count'    => getCartCount($uid),
            'wishlist_count' => getWishlistCount($uid),
        ]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action.'], 400);
}
