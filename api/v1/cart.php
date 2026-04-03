<?php
/**
 * api/v1/cart.php — Cart API Resource
 *
 * Actions: list, add, update, remove, clear, summary
 */
if (!defined('API_RESOURCE')) {
    require_once __DIR__ . '/gateway.php';
    exit;
}

require_once __DIR__ . '/../../includes/api-response.php';

$db     = getDB();
$action = API_ACTION ?: ($_GET['action'] ?? 'list');
$apiKey = API_KEY_ROW;
$userId = (int)$apiKey['user_id'];

$elapsed = fn() => (int)((microtime(true) - API_START_TIME) * 1000);

switch ($action) {
    // ── GET list ──────────────────────────────────────────────
    case 'list':
        $stmt = $db->prepare(
            'SELECT ci.id, ci.product_id, ci.quantity, ci.added_at,
                    p.name, p.price, p.sale_price, p.thumbnail_url, p.stock_qty
             FROM cart_items ci
             LEFT JOIN products p ON p.id = ci.product_id
             WHERE ci.user_id = ?'
        );
        $stmt->execute([$userId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        logApiRequest((int)$apiKey['id'], $userId, 'GET', 'cart/list', 200, $elapsed());
        apiSuccess($items, null, 200, getRateLimit($apiKey));
        break;

    // ── POST add ──────────────────────────────────────────────
    case 'add':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($body['product_id'])) {
            apiValidationError(['product_id' => 'Product ID required.']);
        }
        $productId = (int)$body['product_id'];
        $qty       = max(1, (int)($body['quantity'] ?? 1));

        // Check product exists
        $pStmt = $db->prepare('SELECT id, stock_qty FROM products WHERE id = ? AND status = "active"');
        $pStmt->execute([$productId]);
        $product = $pStmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            apiNotFound('Product');
        }

        // Upsert
        $stmt = $db->prepare('SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?');
        $stmt->execute([$userId, $productId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $newQty = (int)$existing['quantity'] + $qty;
            $db->prepare('UPDATE cart_items SET quantity = ?, added_at = NOW() WHERE id = ?')->execute([$newQty, $existing['id']]);
        } else {
            $db->prepare('INSERT INTO cart_items (user_id, product_id, quantity, added_at) VALUES (?, ?, ?, NOW())')->execute([$userId, $productId, $qty]);
        }
        logApiRequest((int)$apiKey['id'], $userId, 'POST', 'cart/add', 200, $elapsed());
        apiSuccess(['message' => 'Item added to cart.'], null, 200, getRateLimit($apiKey));
        break;

    // ── PUT update ────────────────────────────────────────────
    case 'update':
        $id   = (int)($_GET['id'] ?? 0);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $qty  = (int)($body['quantity'] ?? 0);
        if (!$id || $qty < 1) {
            apiError('Cart item ID and quantity (>= 1) required.', 400);
        }
        $stmt = $db->prepare('UPDATE cart_items SET quantity = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$qty, $id, $userId]);
        if (!$stmt->rowCount()) {
            apiNotFound('Cart item');
        }
        logApiRequest((int)$apiKey['id'], $userId, 'PUT', 'cart/update', 200, $elapsed());
        apiSuccess(['message' => 'Cart updated.'], null, 200, getRateLimit($apiKey));
        break;

    // ── DELETE remove ─────────────────────────────────────────
    case 'remove':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            apiError('Cart item ID required.', 400);
        }
        $stmt = $db->prepare('DELETE FROM cart_items WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        if (!$stmt->rowCount()) {
            apiNotFound('Cart item');
        }
        logApiRequest((int)$apiKey['id'], $userId, 'DELETE', 'cart/remove', 200, $elapsed());
        apiSuccess(['message' => 'Item removed from cart.'], null, 200, getRateLimit($apiKey));
        break;

    // ── DELETE clear ──────────────────────────────────────────
    case 'clear':
        $db->prepare('DELETE FROM cart_items WHERE user_id = ?')->execute([$userId]);
        logApiRequest((int)$apiKey['id'], $userId, 'DELETE', 'cart/clear', 200, $elapsed());
        apiSuccess(['message' => 'Cart cleared.'], null, 200, getRateLimit($apiKey));
        break;

    // ── GET summary ───────────────────────────────────────────
    case 'summary':
        $stmt = $db->prepare(
            'SELECT COUNT(*) AS item_count,
                    SUM(ci.quantity) AS total_qty,
                    SUM(ci.quantity * COALESCE(p.sale_price, p.price)) AS subtotal
             FROM cart_items ci
             LEFT JOIN products p ON p.id = ci.product_id
             WHERE ci.user_id = ?'
        );
        $stmt->execute([$userId]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        logApiRequest((int)$apiKey['id'], $userId, 'GET', 'cart/summary', 200, $elapsed());
        apiSuccess($summary, null, 200, getRateLimit($apiKey));
        break;

    default:
        logApiRequest((int)$apiKey['id'], $userId, $_SERVER['REQUEST_METHOD'], "cart/$action", 404, $elapsed());
        apiNotFound("Action '$action'");
}
