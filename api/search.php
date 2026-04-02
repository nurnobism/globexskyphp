<?php
require_once __DIR__ . '/../includes/middleware.php';
header('Content-Type: application/json');
$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOut($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

switch ($action) {
    case 'search':
        $q         = sanitize($_GET['q'] ?? '');
        $type      = sanitize($_GET['type'] ?? 'all');
        $category  = sanitize($_GET['category'] ?? '');
        $minPrice  = isset($_GET['min_price']) ? (float) $_GET['min_price'] : null;
        $maxPrice  = isset($_GET['max_price']) ? (float) $_GET['max_price'] : null;
        $country   = sanitize($_GET['country'] ?? '');
        $minRating = isset($_GET['min_rating']) ? (float) $_GET['min_rating'] : null;
        $inStock   = isset($_GET['in_stock']) ? (bool) $_GET['in_stock'] : null;
        $page      = (int) get('page', 1);
        if (!$q) {
            jsonOut(['success' => false, 'message' => 'Query parameter q is required'], 400);
        }
        $searchTerm = '%' . $q . '%';
        $results = [];

        if ($type === 'products' || $type === 'all') {
            $sql    = 'SELECT p.*, s.company_name AS supplier_name
                       FROM products p
                       LEFT JOIN suppliers s ON s.id = p.supplier_id
                       WHERE p.name LIKE ?';
            $params = [$searchTerm];
            if ($category) {
                $sql .= ' AND p.category = ?';
                $params[] = $category;
            }
            if ($minPrice !== null) {
                $sql .= ' AND p.price >= ?';
                $params[] = $minPrice;
            }
            if ($maxPrice !== null) {
                $sql .= ' AND p.price <= ?';
                $params[] = $maxPrice;
            }
            if ($inStock !== null && $inStock) {
                $sql .= ' AND p.stock > 0';
            }
            if ($minRating !== null) {
                $sql .= ' AND p.rating >= ?';
                $params[] = $minRating;
            }
            $sql .= ' ORDER BY p.name ASC';
            if ($type === 'products') {
                $results = paginate($db, $sql, $params, $page);
            } else {
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $results['products'] = $stmt->fetchAll();
            }
        }

        if ($type === 'suppliers' || $type === 'all') {
            $sql    = 'SELECT * FROM suppliers WHERE company_name LIKE ?';
            $params = [$searchTerm];
            if ($country) {
                $sql .= ' AND country = ?';
                $params[] = $country;
            }
            if ($minRating !== null) {
                $sql .= ' AND rating >= ?';
                $params[] = $minRating;
            }
            $sql .= ' ORDER BY company_name ASC';
            if ($type === 'suppliers') {
                $results = paginate($db, $sql, $params, $page);
            } else {
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $results['suppliers'] = $stmt->fetchAll();
            }
        }

        jsonOut(['success' => true, 'data' => $results]);
        break;

    case 'suggestions':
        $q = sanitize($_GET['q'] ?? '');
        if (!$q) {
            jsonOut(['success' => true, 'data' => []]);
        }
        $searchTerm = '%' . $q . '%';
        $productStmt = $db->prepare('SELECT name FROM products WHERE name LIKE ? LIMIT 5');
        $productStmt->execute([$searchTerm]);
        $productNames = $productStmt->fetchAll(\PDO::FETCH_COLUMN);
        $supplierStmt = $db->prepare('SELECT company_name FROM suppliers WHERE company_name LIKE ? LIMIT 5');
        $supplierStmt->execute([$searchTerm]);
        $supplierNames = $supplierStmt->fetchAll(\PDO::FETCH_COLUMN);
        $suggestions = array_slice(array_unique(array_merge($productNames, $supplierNames)), 0, 10);
        jsonOut(['success' => true, 'data' => $suggestions]);
        break;

    case 'history':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $stmt = $db->prepare(
            'SELECT * FROM search_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 20'
        );
        $stmt->execute([$userId]);
        jsonOut(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'save_history':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $query  = sanitize($_POST['query'] ?? '');
        $type   = sanitize($_POST['type'] ?? 'all');
        if (!$query) {
            jsonOut(['success' => false, 'message' => 'Query is required'], 400);
        }
        $stmt = $db->prepare(
            'INSERT INTO search_history (user_id, query, type, created_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE created_at = NOW()'
        );
        $stmt->execute([$userId, $query, $type]);
        jsonOut(['success' => true, 'message' => 'Search history saved']);
        break;

    default:
        jsonOut(['success' => false, 'message' => 'Invalid action'], 400);
}
