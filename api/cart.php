<?php
/**
 * api/cart.php — Shopping Cart API
 */

require_once __DIR__ . '/../includes/middleware.php';

$action = $_GET['action'] ?? post('action', 'list');
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

switch ($action) {

    case 'list':
        if (!isLoggedIn()) {
            // Return session cart for guests
            $cart = [];
            foreach ($_SESSION['cart'] ?? [] as $key => $item) {
                $cart[] = $item;
            }
            jsonResponse(['data' => $cart]);
        }
        $stmt = $db->prepare('SELECT ci.*, p.name, p.price, p.images, p.slug, p.stock_qty,
                s.company_name supplier_name
            FROM cart_items ci
            JOIN products p ON p.id = ci.product_id
            LEFT JOIN suppliers s ON s.id = p.supplier_id
            WHERE ci.user_id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $items = $stmt->fetchAll();
        $subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));
        jsonResponse(['data' => $items, 'subtotal' => $subtotal, 'count' => count($items)]);
        break;

    case 'add':
        if (!verifyCsrf()) jsonResponse(['error' => 'Invalid CSRF token'], 403);
        $productId = (int)post('product_id', 0);
        $quantity  = max(1, (int)post('quantity', 1));
        $variantId = (int)post('variant_id', 0) ?: null;

        if (!$productId) jsonResponse(['error' => 'Product ID required'], 400);

        // Validate product exists and is active
        $pStmt = $db->prepare('SELECT id, stock_qty, min_order_qty FROM products WHERE id = ? AND status = "active"');
        $pStmt->execute([$productId]);
        $product = $pStmt->fetch();
        if (!$product) jsonResponse(['error' => 'Product not found'], 404);

        if (!isLoggedIn()) {
            // Session cart for guests
            $key = $productId . '_' . ($variantId ?? 0);
            if (isset($_SESSION['cart'][$key])) {
                $_SESSION['cart'][$key]['quantity'] += $quantity;
            } else {
                $pInfo = $db->prepare('SELECT id, name, price, images, slug FROM products WHERE id = ?');
                $pInfo->execute([$productId]);
                $info = $pInfo->fetch();
                $_SESSION['cart'][$key] = array_merge($info, ['quantity' => $quantity, 'variant_id' => $variantId]);
            }
            jsonResponse(['success' => true]);
        }

        // Logged-in: use DB cart
        $existing = $db->prepare('SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ? AND ' . ($variantId ? 'variant_id = ?' : 'variant_id IS NULL'));
        $existing->execute($variantId ? [$_SESSION['user_id'], $productId, $variantId] : [$_SESSION['user_id'], $productId]);
        $row = $existing->fetch();

        if ($row) {
            $db->prepare('UPDATE cart_items SET quantity = quantity + ? WHERE id = ?')->execute([$quantity, $row['id']]);
        } else {
            $db->prepare('INSERT INTO cart_items (user_id, product_id, variant_id, quantity) VALUES (?, ?, ?, ?)')->execute([$_SESSION['user_id'], $productId, $variantId, $quantity]);
        }
        jsonResponse(['success' => true]);
        break;

    case 'update':
        if (!verifyCsrf()) jsonResponse(['error' => 'Invalid CSRF token'], 403);
        $itemId   = (int)post('item_id', 0);
        $quantity = max(0, (int)post('quantity', 1));

        if (!isLoggedIn()) {
            // Session cart
            foreach ($_SESSION['cart'] ?? [] as $key => $item) {
                if ($item['id'] == $itemId) {
                    if ($quantity === 0) {
                        unset($_SESSION['cart'][$key]);
                    } else {
                        $_SESSION['cart'][$key]['quantity'] = $quantity;
                    }
                    break;
                }
            }
            jsonResponse(['success' => true]);
        }

        if ($quantity === 0) {
            $db->prepare('DELETE FROM cart_items WHERE id = ? AND user_id = ?')->execute([$itemId, $_SESSION['user_id']]);
        } else {
            $db->prepare('UPDATE cart_items SET quantity = ? WHERE id = ? AND user_id = ?')->execute([$quantity, $itemId, $_SESSION['user_id']]);
        }
        jsonResponse(['success' => true]);
        break;

    case 'remove':
        if (!verifyCsrf()) jsonResponse(['error' => 'Invalid CSRF token'], 403);
        $itemId = (int)post('item_id', 0);

        if (!isLoggedIn()) {
            foreach ($_SESSION['cart'] ?? [] as $key => $item) {
                if ($item['id'] == $itemId) { unset($_SESSION['cart'][$key]); break; }
            }
            jsonResponse(['success' => true]);
        }
        $db->prepare('DELETE FROM cart_items WHERE id = ? AND user_id = ?')->execute([$itemId, $_SESSION['user_id']]);
        jsonResponse(['success' => true]);
        break;

    case 'clear':
        if (!verifyCsrf()) jsonResponse(['error' => 'Invalid CSRF token'], 403);
        if (!isLoggedIn()) { $_SESSION['cart'] = []; jsonResponse(['success' => true]); }
        $db->prepare('DELETE FROM cart_items WHERE user_id = ?')->execute([$_SESSION['user_id']]);
        jsonResponse(['success' => true]);
        break;

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}
