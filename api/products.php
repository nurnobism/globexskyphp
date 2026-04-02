<?php
/**
 * api/products.php — Products API
 * GET  ?action=list|detail|search|featured|categories
 * POST ?action=create|update|delete (supplier/admin only)
 */

require_once __DIR__ . '/../includes/middleware.php';

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

$db = getDB();

switch ($action) {

    case 'list':
        $page     = max(1, (int)get('page', 1));
        $category = get('category', '');
        $q        = get('q', '');
        $supplier = get('supplier_id', '');
        $sort     = get('sort', 'created_at');
        $dir      = get('dir', 'DESC');

        $allowedSorts = ['name', 'price', 'created_at', 'rating', 'view_count'];
        if (!in_array($sort, $allowedSorts)) $sort = 'created_at';
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $where  = ['p.status = "active"'];
        $params = [];

        if ($category) {
            $where[]  = 'p.category_id = ?';
            $params[] = $category;
        }
        if ($q) {
            $where[]  = 'MATCH(p.name, p.short_desc) AGAINST(? IN BOOLEAN MODE)';
            $params[] = $q . '*';
        }
        if ($supplier) {
            $where[]  = 'p.supplier_id = ?';
            $params[] = $supplier;
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT p.*, s.company_name supplier_name, c.name category_name
                FROM products p
                LEFT JOIN suppliers s ON s.id = p.supplier_id
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE $whereClause
                ORDER BY p.$sort $dir";

        $result = paginate($db, $sql, $params, $page);
        jsonResponse($result);
        break;

    case 'detail':
        $id   = (int)get('id', 0);
        $slug = get('slug', '');
        if (!$id && !$slug) jsonResponse(['error' => 'Product ID or slug required'], 400);

        $stmt = $db->prepare('SELECT p.*, s.company_name supplier_name, s.slug supplier_slug, c.name category_name
            FROM products p
            LEFT JOIN suppliers s ON s.id = p.supplier_id
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE (' . ($id ? 'p.id = ?' : 'p.slug = ?') . ') AND p.status = "active"');
        $stmt->execute([$id ?: $slug]);
        $product = $stmt->fetch();
        if (!$product) jsonResponse(['error' => 'Product not found'], 404);

        // Increment view count
        $db->prepare('UPDATE products SET view_count = view_count + 1 WHERE id = ?')->execute([$product['id']]);

        // Get variants
        $vStmt = $db->prepare('SELECT * FROM product_variants WHERE product_id = ?');
        $vStmt->execute([$product['id']]);
        $product['variants'] = $vStmt->fetchAll();

        // Get reviews (latest 5)
        $rStmt = $db->prepare('SELECT r.*, u.first_name, u.last_name, u.avatar FROM reviews r
            JOIN users u ON u.id = r.user_id
            WHERE r.product_id = ? AND r.status = "approved"
            ORDER BY r.created_at DESC LIMIT 5');
        $rStmt->execute([$product['id']]);
        $product['reviews'] = $rStmt->fetchAll();

        jsonResponse(['data' => $product]);
        break;

    case 'featured':
        $limit = min(20, (int)get('limit', 8));
        $stmt  = $db->prepare('SELECT p.*, s.company_name supplier_name FROM products p
            LEFT JOIN suppliers s ON s.id = p.supplier_id
            WHERE p.status = "active" AND p.is_featured = 1
            ORDER BY p.created_at DESC LIMIT ?');
        $stmt->execute([$limit]);
        jsonResponse(['data' => $stmt->fetchAll()]);
        break;

    case 'categories':
        $stmt = $db->query('SELECT c.*, COUNT(p.id) product_count FROM categories c
            LEFT JOIN products p ON p.category_id = c.id AND p.status = "active"
            WHERE c.is_active = 1 GROUP BY c.id ORDER BY c.sort_order');
        jsonResponse(['data' => $stmt->fetchAll()]);
        break;

    case 'create':
    case 'update':
        if (!isLoggedIn())   jsonResponse(['error' => 'Unauthorized'], 401);
        if (!verifyCsrf())   jsonResponse(['error' => 'Invalid CSRF token'], 403);
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);

        // Supplier or admin
        $suppStmt = $db->prepare('SELECT id FROM suppliers WHERE user_id = ?');
        $suppStmt->execute([$_SESSION['user_id']]);
        $supplier = $suppStmt->fetch();
        if (!$supplier && !isAdmin()) jsonResponse(['error' => 'Supplier account required'], 403);

        $name     = trim(post('name', ''));
        $price    = (float)post('price', 0);
        $category = (int)post('category_id', 0);
        $desc     = post('description', '');
        $shortDesc= post('short_desc', '');
        $status   = in_array(post('status'), ['active','draft','inactive']) ? post('status') : 'draft';
        $minQty   = max(1, (int)post('min_order_qty', 1));
        $stock    = max(0, (int)post('stock_qty', 0));

        if (empty($name) || $price < 0) jsonResponse(['error' => 'Name and valid price required'], 422);

        $slug = slugify($name);

        if ($action === 'create') {
            // Ensure unique slug
            $i = 1;
            $baseSlug = $slug;
            while (true) {
                $s = $db->prepare('SELECT id FROM products WHERE slug = ? LIMIT 1');
                $s->execute([$slug]);
                if (!$s->fetch()) break;
                $slug = $baseSlug . '-' . $i++;
            }
            $db->prepare('INSERT INTO products (supplier_id, category_id, name, slug, short_desc, description, price, min_order_qty, stock_qty, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$supplier['id'] ?? 0, $category ?: null, $name, $slug, $shortDesc, $desc, $price, $minQty, $stock, $status]);
            $id = $db->lastInsertId();
            jsonResponse(['success' => true, 'id' => $id, 'slug' => $slug]);
        } else {
            $id = (int)post('id', 0);
            if (!$id) jsonResponse(['error' => 'Product ID required'], 400);
            $db->prepare('UPDATE products SET category_id=?, name=?, short_desc=?, description=?, price=?, min_order_qty=?, stock_qty=?, status=?, updated_at=NOW() WHERE id=?')
               ->execute([$category ?: null, $name, $shortDesc, $desc, $price, $minQty, $stock, $status, $id]);
            jsonResponse(['success' => true]);
        }
        break;

    case 'delete':
        if (!isLoggedIn()) jsonResponse(['error' => 'Unauthorized'], 401);
        if (!isAdmin())    jsonResponse(['error' => 'Forbidden'], 403);
        if (!verifyCsrf()) jsonResponse(['error' => 'Invalid CSRF token'], 403);

        $id = (int)post('id', 0);
        if (!$id) jsonResponse(['error' => 'Product ID required'], 400);
        $db->prepare('UPDATE products SET status = "archived" WHERE id = ?')->execute([$id]);
        jsonResponse(['success' => true]);
        break;

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}
