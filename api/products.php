<?php
/**
 * api/products.php — Products API
 *
 * Public (GET):
 *   action=list          — Browse/filter products
 *   action=get           — Single product detail (alias: detail)
 *   action=featured      — Featured products
 *   action=categories    — Category list with product counts
 *
 * Supplier (POST, auth required):
 *   action=create        — Create product (plan limit checked)
 *   action=update        — Update own product
 *   action=delete        — Soft-delete own product (status → archived)
 *   action=my_products   — Supplier's own product list (alias: list_mine)
 *   action=upload_image  — Upload product image (plan limit checked)
 *   action=delete_image  — Delete product image
 *
 * Admin (POST, admin auth required):
 *   action=update_status — Approve / reject / suspend products
 *                          (alias: toggle_status)
 */

require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/plan_limits.php';
require_once __DIR__ . '/../includes/feature_toggles.php';
require_once __DIR__ . '/../includes/products.php';

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

$db = getDB();

switch ($action) {

    // ── public: list products ───────────────────────────────────────────
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

    // ── public: single product (alias: get) ────────────────────────────
    case 'get':
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

        // Feature toggle check
        if (!isFeatureEnabled('product_listing')) {
            jsonResponse(['error' => 'Product listing is currently disabled by platform administrators.'], 503);
        }

        // Supplier or admin
        $suppStmt = $db->prepare('SELECT id FROM suppliers WHERE user_id = ?');
        $suppStmt->execute([$_SESSION['user_id']]);
        $supplier = $suppStmt->fetch();
        if (!$supplier && !isAdmin()) jsonResponse(['error' => 'Supplier account required'], 403);

        $supplierId = (int)($supplier['id'] ?? 0);

        // Plan limit check (create only)
        if ($action === 'create' && !canAddProduct($supplierId)) {
            $plan = getSupplierPlan($supplierId);
            $limit = (int)($plan['limits_decoded']['products'] ?? 10);
            jsonResponse(['error' => "Product limit reached ({$limit} products on {$plan['name']} plan). Please upgrade your plan."], 403);
        }

        $name      = trim(post('name', ''));
        $price     = (float)post('price', 0);
        $category  = (int)post('category_id', 0);
        $desc      = post('description', '');
        $shortDesc = post('short_desc', '');
        $status    = in_array(post('status'), ['active','draft','inactive']) ? post('status') : 'draft';
        $minQty    = max(1, (int)post('min_order_qty', 1));
        $stock     = max(0, (int)post('stock_qty', 0));
        $weight    = post('weight', null);
        $weight    = ($weight !== null && $weight !== '') ? (float)$weight : null;

        // Extra fields from multi-step form
        $tagsJson       = post('tags', null);
        $tags           = null;
        if ($tagsJson !== null) {
            $parsed = json_decode($tagsJson, true);
            $tags   = is_array($parsed) ? json_encode(array_slice($parsed, 0, 10)) : null;
        }
        $variationsJson = post('variations', null);
        $skusJson       = post('skus', null);

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
            $db->prepare(
                'INSERT INTO products (supplier_id, category_id, name, slug, short_desc, description, price, min_order_qty, stock_qty, weight, tags, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$supplierId, $category ?: null, $name, $slug, $shortDesc, $desc, $price, $minQty, $stock, $weight, $tags, $status]);
            $id = (int)$db->lastInsertId();

            // Save variations and SKUs
            if ($variationsJson) {
                $variations = json_decode($variationsJson, true);
                if (is_array($variations)) {
                    foreach ($variations as $vi => $varType) {
                        $vName = trim($varType['name'] ?? '');
                        if (!$vName) continue;
                        $db->prepare('INSERT INTO product_variations (product_id, name, sort_order) VALUES (?, ?, ?)')
                           ->execute([$id, $vName, $vi]);
                        $varId = (int)$db->lastInsertId();
                        foreach (($varType['values'] ?? []) as $vj => $val) {
                            $val = trim($val);
                            if (!$val) continue;
                            $db->prepare('INSERT INTO product_variation_options (variation_id, value, sort_order) VALUES (?, ?, ?)')
                               ->execute([$varId, $val, $vj]);
                        }
                    }
                }
            }

            if ($skusJson) {
                $skus = json_decode($skusJson, true);
                if (is_array($skus)) {
                    foreach ($skus as $sku) {
                        $skuCode  = trim($sku['sku_code'] ?? '');
                        $skuPrice = (isset($sku['price']) && $sku['price'] !== null && $sku['price'] !== '') ? (float)$sku['price'] : $price;
                        $skuStock = max(0, (int)($sku['stock'] ?? 0));
                        $attrs    = isset($sku['attributes']) ? json_encode($sku['attributes']) : null;
                        $db->prepare('INSERT INTO product_variants (product_id, sku, attributes, price, stock_qty) VALUES (?, ?, ?, ?, ?)')
                           ->execute([$id, $skuCode ?: null, $attrs, $skuPrice, $skuStock]);
                    }
                }
            }

            jsonResponse(['success' => true, 'id' => $id, 'slug' => $slug]);
        } else {
            $id = (int)post('id', 0);
            if (!$id) jsonResponse(['error' => 'Product ID required'], 400);
            // Ownership check — supplier may only edit own products
            if (!isAdmin()) {
                $ownerCheck = $db->prepare('SELECT id FROM products WHERE id = ? AND supplier_id = ?');
                $ownerCheck->execute([$id, $supplierId]);
                if (!$ownerCheck->fetch()) {
                    jsonResponse(['error' => 'Product not found or access denied'], 403);
                }
            }
            $db->prepare(
                'UPDATE products SET category_id=?, name=?, short_desc=?, description=?, price=?, min_order_qty=?, stock_qty=?, weight=?, tags=?, status=?, updated_at=NOW() WHERE id=?'
            )->execute([$category ?: null, $name, $shortDesc, $desc, $price, $minQty, $stock, $weight, $tags, $status, $id]);
            jsonResponse(['success' => true]);
        }
        break;

    // ── supplier/admin: soft-delete a product ──────────────────────────
    case 'delete':
        if (!isLoggedIn()) jsonResponse(['error' => 'Unauthorized'], 401);
        if (!verifyCsrf()) jsonResponse(['error' => 'Invalid CSRF token'], 403);
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);

        $id = (int)post('id', 0);
        if (!$id) jsonResponse(['error' => 'Product ID required'], 400);

        if (isAdmin()) {
            // Admin can delete any product
            $db->prepare('UPDATE products SET status = "archived", updated_at = NOW() WHERE id = ?')->execute([$id]);
        } else {
            // Supplier can only delete their own product
            $suppStmt = $db->prepare('SELECT id FROM suppliers WHERE user_id = ?');
            $suppStmt->execute([$_SESSION['user_id']]);
            $supplier = $suppStmt->fetch();
            if (!$supplier) jsonResponse(['error' => 'Supplier account required'], 403);

            $ownerCheck = $db->prepare('SELECT id FROM products WHERE id = ? AND supplier_id = ?');
            $ownerCheck->execute([$id, $supplier['id']]);
            if (!$ownerCheck->fetch()) jsonResponse(['error' => 'Product not found or access denied'], 403);

            $db->prepare('UPDATE products SET status = "archived", updated_at = NOW() WHERE id = ?')->execute([$id]);
        }
        jsonResponse(['success' => true]);
        break;

    // ── supplier: own product list (alias: my_products) ────────────────
    case 'my_products':
    case 'list_mine':
        if (!isLoggedIn()) jsonResponse(['error' => 'Unauthorized'], 401);
        $suppStmt = $db->prepare('SELECT id FROM suppliers WHERE user_id = ?');
        $suppStmt->execute([$_SESSION['user_id']]);
        $supplier = $suppStmt->fetch();
        if (!$supplier && !isAdmin()) jsonResponse(['error' => 'Supplier account required'], 403);

        $page  = max(1, (int)get('page', 1));
        $q     = get('q', '');
        $pStatus = get('status', '');

        $where  = ['p.supplier_id = ?'];
        $params = [$supplier['id'] ?? 0];
        if ($q)       { $where[] = 'p.name LIKE ?';   $params[] = "%$q%"; }
        if ($pStatus) { $where[] = 'p.status = ?'; $params[] = $pStatus; }

        $sql = 'SELECT p.*, c.name category_name FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE ' . implode(' AND ', $where) . ' ORDER BY p.created_at DESC';
        jsonResponse(paginate($db, $sql, $params, $page));
        break;

    case 'upload_image':
        if (!isLoggedIn()) jsonResponse(['error' => 'Unauthorized'], 401);
        if (!verifyCsrf()) jsonResponse(['error' => 'Invalid CSRF token'], 403);
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);

        $suppStmt = $db->prepare('SELECT id FROM suppliers WHERE user_id = ?');
        $suppStmt->execute([$_SESSION['user_id']]);
        $supplier = $suppStmt->fetch();
        if (!$supplier && !isAdmin()) jsonResponse(['error' => 'Supplier account required'], 403);

        if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['error' => 'Image file required'], 400);
        }

        $path = uploadFile($_FILES['image'], 'uploads/products');
        if (!$path) jsonResponse(['error' => 'Upload failed or invalid file type'], 422);

        $productId = (int)post('product_id', 0);
        $isPrimary = (int)post('is_primary', 0);
        if ($productId) {
            $db->prepare('INSERT INTO product_images (product_id, image_url, is_primary) VALUES (?, ?, ?)')
               ->execute([$productId, $path, $isPrimary]);
        }

        jsonResponse(['success' => true, 'url' => APP_URL . '/' . $path, 'path' => $path]);
        break;

    // ── admin: update product status ────────────────────────────────────
    case 'update_status':
    case 'toggle_status':
        if (!isLoggedIn()) jsonResponse(['error' => 'Unauthorized'], 401);
        if (!isAdmin())    jsonResponse(['error' => 'Forbidden'], 403);
        if (!verifyCsrf()) jsonResponse(['error' => 'Invalid CSRF token'], 403);
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);

        $id        = (int)post('id', 0);
        $newStatus = post('status', '');
        if (!$id) jsonResponse(['error' => 'Product ID required'], 400);
        if (!in_array($newStatus, ['active', 'rejected', 'draft', 'inactive', 'archived'])) {
            jsonResponse(['error' => 'Invalid status'], 422);
        }

        $db->prepare('UPDATE products SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$newStatus, $id]);
        jsonResponse(['success' => true, 'id' => $id, 'status' => $newStatus]);
        break;

    // ── supplier/admin: delete a product image ──────────────────────────
    case 'delete_image':
        if (!isLoggedIn()) jsonResponse(['error' => 'Unauthorized'], 401);
        if (!verifyCsrf()) jsonResponse(['error' => 'Invalid CSRF token'], 403);
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);

        $imageId = (int)post('image_id', 0);
        if (!$imageId) jsonResponse(['error' => 'Image ID required'], 400);

        $supplierId = 0; // admin context by default
        if (!isAdmin()) {
            $suppStmt = $db->prepare('SELECT id FROM suppliers WHERE user_id = ?');
            $suppStmt->execute([$_SESSION['user_id']]);
            $supplier = $suppStmt->fetch();
            if (!$supplier) jsonResponse(['error' => 'Supplier account required'], 403);
            $supplierId = (int)$supplier['id'];
        }

        try {
            deleteProductImage($imageId, $supplierId);
            jsonResponse(['success' => true]);
        } catch (RuntimeException $e) {
            jsonResponse(['error' => $e->getMessage()], 403);
        }
        break;

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}
