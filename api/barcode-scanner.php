<?php
/**
 * api/barcode-scanner.php — Barcode Scanner / Product Lookup API
 */
require_once __DIR__ . '/../includes/middleware.php';

$action = $_GET['action'] ?? 'lookup';
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

switch ($action) {

    case 'lookup':
        $barcode = trim(get('barcode', ''));
        if (!$barcode) jsonResponse(['error' => 'Barcode is required'], 400);

        // Search by SKU, UPC, EAN, or barcode field
        $stmt = $db->prepare(
            "SELECT p.id, p.name, p.slug, p.sku, p.price, p.currency, p.stock_qty,
                    p.thumbnail, p.short_desc, p.unit, p.status,
                    s.company_name supplier_name, c.name category_name
             FROM products p
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE (p.sku = ? OR p.barcode = ? OR p.upc = ?)
               AND p.status = 'active'
             LIMIT 1"
        );
        $stmt->execute([$barcode, $barcode, $barcode]);
        $product = $stmt->fetch();

        if (!$product) {
            jsonResponse(['error' => 'Product not found for barcode: ' . $barcode], 404);
        }

        jsonResponse(['data' => $product]);
        break;

    case 'search':
        $query = trim(get('q', ''));
        if (!$query) jsonResponse(['error' => 'Search query required'], 400);

        $like = '%' . $query . '%';
        $stmt = $db->prepare(
            "SELECT p.id, p.name, p.slug, p.sku, p.price, p.currency, p.stock_qty, p.thumbnail
             FROM products p
             WHERE p.status = 'active'
               AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ? OR p.upc LIKE ?)
             ORDER BY p.name
             LIMIT 20"
        );
        $stmt->execute([$like, $like, $like, $like]);
        jsonResponse(['data' => $stmt->fetchAll()]);
        break;

    case 'history':
        requireLogin();
        $uid  = $_SESSION['user_id'];
        $page = max(1, (int)get('page', 1));
        $sql  = 'SELECT sh.*, p.name product_name, p.slug product_slug
                 FROM scan_history sh
                 LEFT JOIN products p ON p.id = sh.product_id
                 WHERE sh.user_id = ?
                 ORDER BY sh.scanned_at DESC';
        jsonResponse(paginate($db, $sql, [$uid], $page, 20));
        break;

    case 'save_scan':
        requireLogin();
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);

        $barcode   = trim(post('barcode', ''));
        $productId = (int)post('product_id', 0);
        if (!$barcode) jsonResponse(['error' => 'Barcode is required'], 400);

        $db->prepare(
            'INSERT INTO scan_history (user_id, barcode, product_id, scanned_at) VALUES (?, ?, ?, NOW())'
        )->execute([$_SESSION['user_id'], $barcode, $productId ?: null]);

        jsonResponse(['success' => true]);
        break;

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}
