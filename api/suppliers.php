<?php
/**
 * api/suppliers.php — Suppliers API
 */

require_once __DIR__ . '/../includes/middleware.php';

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

switch ($action) {

    case 'list':
        $page    = max(1, (int)get('page', 1));
        $country = get('country', '');
        $q       = get('q', '');
        $where   = ['s.verified = 1'];
        $params  = [];
        if ($country) { $where[] = 's.country = ?'; $params[] = $country; }
        if ($q) { $where[] = '(s.company_name LIKE ? OR s.description LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
        $sql = 'SELECT s.*, u.email FROM suppliers s JOIN users u ON u.id=s.user_id WHERE ' . implode(' AND ', $where) . ' ORDER BY s.rating DESC, s.total_orders DESC';
        jsonResponse(paginate($db, $sql, $params, $page));
        break;

    case 'profile':
        $id   = (int)get('id', 0);
        $slug = get('slug', '');
        if (!$id && !$slug) jsonResponse(['error' => 'Supplier ID or slug required'], 400);
        $stmt = $db->prepare('SELECT s.*, u.email, u.first_name, u.last_name FROM suppliers s JOIN users u ON u.id=s.user_id WHERE ' . ($id ? 's.id=?' : 's.slug=?'));
        $stmt->execute([$id ?: $slug]);
        $supplier = $stmt->fetch();
        if (!$supplier) jsonResponse(['error' => 'Supplier not found'], 404);

        // Products
        $pStmt = $db->prepare('SELECT * FROM products WHERE supplier_id=? AND status="active" ORDER BY created_at DESC LIMIT 12');
        $pStmt->execute([$supplier['id']]);
        $supplier['products'] = $pStmt->fetchAll();

        jsonResponse(['data' => $supplier]);
        break;

    case 'update_profile':
        if ($method !== 'POST')  jsonResponse(['error' => 'Method not allowed'], 405);
        if (!isLoggedIn())       jsonResponse(['error' => 'Unauthorized'], 401);
        if (!verifyCsrf())       jsonResponse(['error' => 'Invalid CSRF token'], 403);

        $stmt = $db->prepare('SELECT id FROM suppliers WHERE user_id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $supplier = $stmt->fetch();
        if (!$supplier && !isAdmin()) jsonResponse(['error' => 'Supplier profile not found'], 403);

        $companyName  = trim(post('company_name', ''));
        $description  = trim(post('description', ''));
        $country      = trim(post('country', ''));
        $city         = trim(post('city', ''));
        $website      = trim(post('website', ''));
        $responseTime = trim(post('response_time', ''));
        $established  = (int)post('established_year', 0) ?: null;
        $employees    = trim(post('employee_count', ''));
        $revenue      = trim(post('annual_revenue', ''));

        $logo = null;
        if (!empty($_FILES['logo']['name'])) $logo = uploadFile($_FILES['logo'], 'supplier_logos');

        $sql = 'UPDATE suppliers SET company_name=?,description=?,country=?,city=?,website=?,response_time=?,established_year=?,employee_count=?,annual_revenue=?';
        $params = [$companyName, $description, $country, $city, $website, $responseTime, $established, $employees, $revenue];
        if ($logo) { $sql .= ',logo=?'; $params[] = $logo; }
        $sql .= ' WHERE id=?';
        $params[] = $supplier['id'];
        $db->prepare($sql)->execute($params);

        if (isset($_POST['_redirect'])) { flashMessage('success', 'Profile updated.'); redirect($_POST['_redirect']); }
        jsonResponse(['success' => true]);
        break;

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}
