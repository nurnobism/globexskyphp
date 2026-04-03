<?php
/**
 * api/v1/products.php — Products API Resource
 *
 * Actions: list, detail, search, categories, create, update, delete
 */
if (!defined('API_RESOURCE')) {
    require_once __DIR__ . '/gateway.php';
    exit;
}

require_once __DIR__ . '/../../includes/api-response.php';

$db     = getDB();
$action = API_ACTION ?: ($_GET['action'] ?? 'list');
$apiKey = API_KEY_ROW;

$elapsed = fn() => (int)((microtime(true) - API_START_TIME) * 1000);

switch ($action) {
    // ── GET list ──────────────────────────────────────────────
    case 'list':
        $pag      = getPaginationParams();
        $where    = ['p.status = "active"'];
        $bindings = [];

        if (!empty($_GET['category'])) {
            $where[]    = 'p.category_id = ?';
            $bindings[] = (int)$_GET['category'];
        }
        if (!empty($_GET['supplier'])) {
            $where[]    = 'p.supplier_id = ?';
            $bindings[] = (int)$_GET['supplier'];
        }
        if (!empty($_GET['min_price'])) {
            $where[]    = 'p.price >= ?';
            $bindings[] = (float)$_GET['min_price'];
        }
        if (!empty($_GET['max_price'])) {
            $where[]    = 'p.price <= ?';
            $bindings[] = (float)$_GET['max_price'];
        }
        if (!empty($_GET['search'])) {
            $where[]    = '(p.name LIKE ? OR p.description LIKE ?)';
            $term       = '%' . $_GET['search'] . '%';
            $bindings[] = $term;
            $bindings[] = $term;
        }

        $allowedSort = ['price', 'name', 'created_at'];
        $sort        = in_array($_GET['sort'] ?? '', $allowedSort, true) ? $_GET['sort'] : 'created_at';
        $dir         = (($_GET['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';
        $whereStr    = implode(' AND ', $where);

        $countStmt = $db->prepare("SELECT COUNT(*) FROM products p WHERE $whereStr");
        $countStmt->execute($bindings);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $db->prepare(
            "SELECT p.id, p.name, p.slug, p.price, p.sale_price, p.sku, p.stock_qty,
                    p.thumbnail_url, p.category_id, p.supplier_id, p.created_at
             FROM products p
             WHERE $whereStr
             ORDER BY p.$sort $dir
             LIMIT ? OFFSET ?"
        );
        $stmt->execute(array_merge($bindings, [$pag['per_page'], $pag['offset']]));
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($apiKey) {
            logApiRequest((int)$apiKey['id'], (int)$apiKey['user_id'], 'GET', 'products/list', 200, $elapsed());
        }
        $rl = $apiKey ? getRateLimit($apiKey) : null;
        apiPaginated($products, $pag['page'], $pag['per_page'], $total, $rl);
        break;

    // ── GET detail ────────────────────────────────────────────
    case 'detail':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            apiError('Product ID required.', 400);
        }
        $stmt = $db->prepare(
            'SELECT p.*, s.name AS supplier_name
             FROM products p
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             WHERE p.id = ? AND p.status = "active"'
        );
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            apiNotFound('Product');
        }
        if ($apiKey) {
            logApiRequest((int)$apiKey['id'], (int)$apiKey['user_id'], 'GET', 'products/detail', 200, $elapsed());
        }
        apiSuccess($product, null, 200, $apiKey ? getRateLimit($apiKey) : null);
        break;

    // ── GET search ────────────────────────────────────────────
    case 'search':
        $q = trim($_GET['q'] ?? $_GET['search'] ?? '');
        if (!$q) {
            apiError('Search query required.', 400);
        }
        $pag  = getPaginationParams();
        $term = '%' . $q . '%';
        $stmt = $db->prepare(
            'SELECT p.id, p.name, p.slug, p.price, p.sale_price, p.thumbnail_url
             FROM products p
             WHERE p.status = "active" AND (p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)
             ORDER BY p.created_at DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->execute([$term, $term, $term, $pag['per_page'], $pag['offset']]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countStmt = $db->prepare('SELECT COUNT(*) FROM products p WHERE p.status = "active" AND (p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)');
        $countStmt->execute([$term, $term, $term]);
        $total = (int)$countStmt->fetchColumn();

        if ($apiKey) {
            logApiRequest((int)$apiKey['id'], (int)$apiKey['user_id'], 'GET', 'products/search', 200, $elapsed());
        }
        apiPaginated($products, $pag['page'], $pag['per_page'], $total, $apiKey ? getRateLimit($apiKey) : null);
        break;

    // ── GET categories ────────────────────────────────────────
    case 'categories':
        $stmt = $db->query(
            'SELECT id, name, slug, parent_id, description FROM product_categories WHERE is_active = 1 ORDER BY sort_order, name'
        );
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($apiKey) {
            logApiRequest((int)$apiKey['id'], (int)$apiKey['user_id'], 'GET', 'products/categories', 200, $elapsed());
        }
        apiSuccess($categories, null, 200, $apiKey ? getRateLimit($apiKey) : null);
        break;

    // ── POST create ───────────────────────────────────────────
    case 'create':
        if (!$apiKey) {
            apiUnauthorized();
        }
        if (!in_array($apiKey['user_role'], ['supplier', 'admin', 'super_admin'], true)) {
            apiForbidden('Only suppliers can create products.');
        }
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $errors = [];
        if (empty($body['name']))  {
            $errors['name']  = 'Product name is required.';
        }
        if (empty($body['price'])) {
            $errors['price'] = 'Price is required.';
        }
        if ($errors) {
            if ($apiKey) {
                logApiRequest((int)$apiKey['id'], (int)$apiKey['user_id'], 'POST', 'products/create', 422, $elapsed());
            }
            apiValidationError($errors);
        }

        // Resolve supplier_id
        $suppStmt = $db->prepare('SELECT id FROM suppliers WHERE user_id = ?');
        $suppStmt->execute([$apiKey['user_id']]);
        $supplierId = $suppStmt->fetchColumn();
        if (!$supplierId && !in_array($apiKey['user_role'], ['admin', 'super_admin'], true)) {
            apiForbidden('Supplier account not found.');
        }

        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($body['name'])) . '-' . time();
        $stmt = $db->prepare(
            'INSERT INTO products (name, slug, description, price, sale_price, sku, stock_qty, category_id, supplier_id, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "active", NOW())'
        );
        $stmt->execute([
            $body['name'],
            $slug,
            $body['description'] ?? '',
            (float)$body['price'],
            !empty($body['sale_price']) ? (float)$body['sale_price'] : null,
            $body['sku'] ?? null,
            (int)($body['stock_qty'] ?? 0),
            !empty($body['category_id']) ? (int)$body['category_id'] : null,
            $supplierId ?: ($body['supplier_id'] ?? null),
        ]);
        $newId = (int)$db->lastInsertId();
        logApiRequest((int)$apiKey['id'], (int)$apiKey['user_id'], 'POST', 'products/create', 201, $elapsed());
        apiSuccess(['id' => $newId, 'message' => 'Product created.'], null, 201, getRateLimit($apiKey));
        break;

    // ── PUT update ────────────────────────────────────────────
    case 'update':
        if (!$apiKey) {
            apiUnauthorized();
        }
        $id   = (int)($_GET['id'] ?? 0);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!$id) {
            apiError('Product ID required.', 400);
        }
        // Ownership check
        $stmt = $db->prepare('SELECT p.*, s.user_id AS owner_user_id FROM products p LEFT JOIN suppliers s ON s.id = p.supplier_id WHERE p.id = ?');
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            apiNotFound('Product');
        }
        if (!in_array($apiKey['user_role'], ['admin', 'super_admin'], true) && (int)$product['owner_user_id'] !== (int)$apiKey['user_id']) {
            apiForbidden('You do not own this product.');
        }
        $allowed = ['name', 'description', 'price', 'sale_price', 'stock_qty', 'status'];
        $sets    = [];
        $vals    = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $body)) {
                $sets[] = "$field = ?";
                $vals[] = $body[$field];
            }
        }
        if ($sets) {
            $vals[] = $id;
            $db->prepare('UPDATE products SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
        }
        logApiRequest((int)$apiKey['id'], (int)$apiKey['user_id'], 'PUT', 'products/update', 200, $elapsed());
        apiSuccess(['message' => 'Product updated.'], null, 200, getRateLimit($apiKey));
        break;

    // ── DELETE delete ─────────────────────────────────────────
    case 'delete':
        if (!$apiKey) {
            apiUnauthorized();
        }
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            apiError('Product ID required.', 400);
        }
        $stmt = $db->prepare('SELECT p.*, s.user_id AS owner_user_id FROM products p LEFT JOIN suppliers s ON s.id = p.supplier_id WHERE p.id = ?');
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            apiNotFound('Product');
        }
        if (!in_array($apiKey['user_role'], ['admin', 'super_admin'], true) && (int)$product['owner_user_id'] !== (int)$apiKey['user_id']) {
            apiForbidden('You do not own this product.');
        }
        $db->prepare('UPDATE products SET status = "archived" WHERE id = ?')->execute([$id]);
        logApiRequest((int)$apiKey['id'], (int)$apiKey['user_id'], 'DELETE', 'products/delete', 200, $elapsed());
        apiSuccess(['message' => 'Product deleted.'], null, 200, getRateLimit($apiKey));
        break;

    default:
        if ($apiKey) {
            logApiRequest((int)$apiKey['id'], (int)$apiKey['user_id'], $_SERVER['REQUEST_METHOD'], "products/$action", 404, $elapsed());
        }
        apiNotFound("Action '$action'");
}
