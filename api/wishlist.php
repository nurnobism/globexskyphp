<?php
/**
 * api/wishlist.php — Wishlist API
 */
require_once __DIR__ . '/../includes/middleware.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();
$uid    = $_SESSION['user_id'];

switch ($action) {

    case 'list':
        $stmt = $db->prepare(
            'SELECT w.id, w.added_at created_at, p.id product_id, p.name, p.slug, p.price, p.images, p.stock_qty, p.currency
             FROM wishlist_items w JOIN products p ON p.id = w.product_id
             WHERE w.user_id = ?
             ORDER BY w.added_at DESC'
        );
        $stmt->execute([$uid]);
        jsonResponse(['data' => $stmt->fetchAll()]);
        break;

    case 'add':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);

        $productId = (int)post('product_id', 0);
        if (!$productId) jsonResponse(['error' => 'Product ID required'], 400);

        // Verify product exists
        $pStmt = $db->prepare('SELECT id FROM products WHERE id = ? AND status = "active"');
        $pStmt->execute([$productId]);
        if (!$pStmt->fetch()) jsonResponse(['error' => 'Product not found'], 404);

        // Check duplicate
        $existingItem = $db->prepare('SELECT id FROM wishlist_items WHERE user_id = ? AND product_id = ?');
        $existingItem->execute([$uid, $productId]);
        if ($existingItem->fetch()) {
            if (isset($_POST['_redirect'])) { flashMessage('info', 'Already in wishlist.'); redirect($_POST['_redirect']); }
            jsonResponse(['success' => true, 'message' => 'Already in wishlist']);
        }

        $db->prepare('INSERT INTO wishlist_items (user_id, product_id) VALUES (?, ?)')->execute([$uid, $productId]);

        if (isset($_POST['_redirect'])) { flashMessage('success', 'Added to wishlist!'); redirect($_POST['_redirect']); }
        jsonResponse(['success' => true]);
        break;

    case 'remove':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);

        $wishlistId = (int)post('wishlist_id', 0);
        $productId  = (int)post('product_id', 0);

        if ($wishlistId) {
            $db->prepare('DELETE FROM wishlist_items WHERE id = ? AND user_id = ?')->execute([$wishlistId, $uid]);
        } elseif ($productId) {
            $db->prepare('DELETE FROM wishlist_items WHERE product_id = ? AND user_id = ?')->execute([$productId, $uid]);
        } else {
            jsonResponse(['error' => 'wishlist_id or product_id required'], 400);
        }

        if (isset($_POST['_redirect'])) { flashMessage('success', 'Removed from wishlist.'); redirect($_POST['_redirect']); }
        jsonResponse(['success' => true]);
        break;

    case 'check':
        $productId = (int)get('product_id', 0);
        if (!$productId) jsonResponse(['error' => 'Product ID required'], 400);
        $stmt = $db->prepare('SELECT id FROM wishlist_items WHERE user_id = ? AND product_id = ?');
        $stmt->execute([$uid, $productId]);
        $row = $stmt->fetch();
        jsonResponse(['in_wishlist' => (bool)$row, 'wishlist_id' => $row ? (int)$row['id'] : null]);
        break;

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}
