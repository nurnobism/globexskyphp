<?php
/**
 * api/v1/dropship.php — Dropshipping API Resource
 *
 * Actions: catalog, import, my_products, update_product, orders, create_order, inventory
 */
if (!defined('API_RESOURCE')) {
    require_once __DIR__ . '/gateway.php';
    exit;
}

require_once __DIR__ . '/../../includes/api-response.php';

$db     = getDB();
$action = API_ACTION ?: ($_GET['action'] ?? 'catalog');
$apiKey = API_KEY_ROW;
$userId = (int)$apiKey['user_id'];

$elapsed = fn() => (int)((microtime(true) - API_START_TIME) * 1000);

switch ($action) {
    // ── GET catalog ───────────────────────────────────────────
    case 'catalog':
        $pag   = getPaginationParams();
        $where = ['p.status = "active"', 'p.is_dropshippable = 1'];
        $binds = [];

        if (!empty($_GET['search'])) {
            $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
            $term    = '%' . $_GET['search'] . '%';
            $binds[] = $term;
            $binds[] = $term;
        }
        if (!empty($_GET['category'])) {
            $where[] = 'p.category_id = ?';
            $binds[] = (int)$_GET['category'];
        }

        $whereStr  = implode(' AND ', $where);
        $countStmt = $db->prepare("SELECT COUNT(*) FROM products p WHERE $whereStr");
        $countStmt->execute($binds);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $db->prepare(
            "SELECT p.id, p.name, p.slug, p.price, p.sale_price, p.thumbnail_url,
                    p.stock_qty, p.sku, p.category_id, s.name AS supplier_name
             FROM products p
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             WHERE $whereStr ORDER BY p.created_at DESC LIMIT ? OFFSET ?"
        );
        $stmt->execute(array_merge($binds, [$pag['per_page'], $pag['offset']]));
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        logApiRequest((int)$apiKey['id'], $userId, 'GET', 'dropship/catalog', 200, $elapsed());
        apiPaginated($items, $pag['page'], $pag['per_page'], $total, getRateLimit($apiKey));
        break;

    // ── POST import ───────────────────────────────────────────
    case 'import':
        $body      = json_decode(file_get_contents('php://input'), true) ?? [];
        $productId = (int)($body['product_id'] ?? 0);
        if (!$productId) {
            apiValidationError(['product_id' => 'Product ID required.']);
        }
        $pStmt = $db->prepare('SELECT * FROM products WHERE id = ? AND is_dropshippable = 1 AND status = "active"');
        $pStmt->execute([$productId]);
        $product = $pStmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            apiNotFound('Dropshippable product');
        }

        // Create a dropship product link
        $stmt = $db->prepare(
            'INSERT INTO dropship_products (dropshipper_id, source_product_id, custom_price, status, created_at)
             VALUES (?, ?, ?, "active", NOW())'
        );
        $stmt->execute([
            $userId,
            $productId,
            !empty($body['custom_price']) ? (float)$body['custom_price'] : null,
        ]);

        logApiRequest((int)$apiKey['id'], $userId, 'POST', 'dropship/import', 201, $elapsed());
        apiSuccess(['message' => 'Product imported to your store.', 'id' => (int)$db->lastInsertId()], null, 201, getRateLimit($apiKey));
        break;

    // ── GET my_products ───────────────────────────────────────
    case 'my_products':
        $pag   = getPaginationParams();
        $count = $db->prepare('SELECT COUNT(*) FROM dropship_products WHERE dropshipper_id = ?');
        $count->execute([$userId]);
        $total = (int)$count->fetchColumn();

        $stmt = $db->prepare(
            'SELECT dp.id, dp.custom_price, dp.status, dp.created_at,
                    p.name, p.price, p.thumbnail_url, p.stock_qty
             FROM dropship_products dp
             LEFT JOIN products p ON p.id = dp.source_product_id
             WHERE dp.dropshipper_id = ?
             ORDER BY dp.created_at DESC LIMIT ? OFFSET ?'
        );
        $stmt->execute([$userId, $pag['per_page'], $pag['offset']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        logApiRequest((int)$apiKey['id'], $userId, 'GET', 'dropship/my_products', 200, $elapsed());
        apiPaginated($items, $pag['page'], $pag['per_page'], $total, getRateLimit($apiKey));
        break;

    // ── PUT update_product ────────────────────────────────────
    case 'update_product':
        $id   = (int)($_GET['id'] ?? 0);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!$id) {
            apiError('Dropship product ID required.', 400);
        }
        $stmt = $db->prepare('SELECT id FROM dropship_products WHERE id = ? AND dropshipper_id = ?');
        $stmt->execute([$id, $userId]);
        if (!$stmt->fetchColumn()) {
            apiNotFound('Dropship product');
        }
        if (array_key_exists('custom_price', $body)) {
            $db->prepare('UPDATE dropship_products SET custom_price = ? WHERE id = ?')->execute([(float)$body['custom_price'], $id]);
        }
        logApiRequest((int)$apiKey['id'], $userId, 'PUT', 'dropship/update_product', 200, $elapsed());
        apiSuccess(['message' => 'Product updated.'], null, 200, getRateLimit($apiKey));
        break;

    // ── GET orders ────────────────────────────────────────────
    case 'orders':
        $pag   = getPaginationParams();
        $count = $db->prepare('SELECT COUNT(*) FROM dropship_orders WHERE dropshipper_id = ?');
        $count->execute([$userId]);
        $total = (int)$count->fetchColumn();

        $stmt = $db->prepare(
            'SELECT * FROM dropship_orders WHERE dropshipper_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?'
        );
        $stmt->execute([$userId, $pag['per_page'], $pag['offset']]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        logApiRequest((int)$apiKey['id'], $userId, 'GET', 'dropship/orders', 200, $elapsed());
        apiPaginated($orders, $pag['page'], $pag['per_page'], $total, getRateLimit($apiKey));
        break;

    // ── POST create_order ─────────────────────────────────────
    case 'create_order':
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $errors = [];
        if (empty($body['product_id'])) {
            $errors['product_id'] = 'Product ID required.';
        }
        if (empty($body['customer_name'])) {
            $errors['customer_name'] = 'Customer name required.';
        }
        if (empty($body['shipping_address'])) {
            $errors['shipping_address'] = 'Shipping address required.';
        }
        if ($errors) {
            apiValidationError($errors);
        }
        $stmt = $db->prepare(
            'INSERT INTO dropship_orders (dropshipper_id, product_id, quantity, customer_name, shipping_address, status, created_at)
             VALUES (?, ?, ?, ?, ?, "pending", NOW())'
        );
        $stmt->execute([
            $userId,
            (int)$body['product_id'],
            max(1, (int)($body['quantity'] ?? 1)),
            $body['customer_name'],
            $body['shipping_address'],
        ]);
        logApiRequest((int)$apiKey['id'], $userId, 'POST', 'dropship/create_order', 201, $elapsed());
        apiSuccess(['id' => (int)$db->lastInsertId(), 'message' => 'Dropship order created.'], null, 201, getRateLimit($apiKey));
        break;

    // ── GET inventory ─────────────────────────────────────────
    case 'inventory':
        $productId = (int)($_GET['product_id'] ?? 0);
        if (!$productId) {
            apiError('product_id is required.', 400);
        }
        $stmt = $db->prepare('SELECT id, name, stock_qty, sku FROM products WHERE id = ? AND status = "active"');
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            apiNotFound('Product');
        }
        logApiRequest((int)$apiKey['id'], $userId, 'GET', 'dropship/inventory', 200, $elapsed());
        apiSuccess($product, null, 200, getRateLimit($apiKey));
        break;

    default:
        logApiRequest((int)$apiKey['id'], $userId, $_SERVER['REQUEST_METHOD'], "dropship/$action", 404, $elapsed());
        apiNotFound("Action '$action'");
}
