<?php
/**
 * api/admin.php — Admin Operations API
 */

require_once __DIR__ . '/../includes/middleware.php';
requireAdmin();

$action = $_GET['action'] ?? 'stats';
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

switch ($action) {

    case 'stats':
        $stats = [
            'total_users'     => (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'total_products'  => (int)$db->query('SELECT COUNT(*) FROM products WHERE status="active"')->fetchColumn(),
            'total_orders'    => (int)$db->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
            'total_suppliers' => (int)$db->query('SELECT COUNT(*) FROM suppliers')->fetchColumn(),
            'revenue_total'   => (float)$db->query('SELECT COALESCE(SUM(total),0) FROM orders WHERE payment_status="paid"')->fetchColumn(),
            'pending_orders'  => (int)$db->query('SELECT COUNT(*) FROM orders WHERE status="pending"')->fetchColumn(),
            'open_rfqs'       => (int)$db->query('SELECT COUNT(*) FROM rfqs WHERE status="open"')->fetchColumn(),
            'new_contacts'    => (int)$db->query('SELECT COUNT(*) FROM contact_inquiries WHERE status="new"')->fetchColumn(),
        ];
        jsonResponse(['data' => $stats]);
        break;

    case 'users':
        $page   = max(1, (int)get('page', 1));
        $role   = get('role', '');
        $q      = get('q', '');
        $where  = ['1=1'];
        $params = [];
        if ($role)  { $where[] = 'role=?'; $params[] = $role; }
        if ($q)     { $where[] = '(email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
        $sql = 'SELECT id,email,first_name,last_name,role,is_active,is_verified,created_at FROM users WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC';
        jsonResponse(paginate($db, $sql, $params, $page));
        break;

    case 'toggle_user':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);
        $id = (int)post('user_id', 0);
        if (!$id) jsonResponse(['error' => 'User ID required'], 400);
        $db->prepare('UPDATE users SET is_active = NOT is_active WHERE id = ?')->execute([$id]);
        jsonResponse(['success' => true]);
        break;

    case 'orders':
        $page   = max(1, (int)get('page', 1));
        $status = get('status', '');
        $where  = ['1=1'];
        $params = [];
        if ($status) { $where[] = 'o.status=?'; $params[] = $status; }
        $sql = 'SELECT o.*, u.email buyer_email, u.first_name, u.last_name FROM orders o JOIN users u ON u.id=o.buyer_id WHERE ' . implode(' AND ', $where) . ' ORDER BY o.placed_at DESC';
        jsonResponse(paginate($db, $sql, $params, $page));
        break;

    case 'update_order_status':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);
        $id     = (int)post('order_id', 0);
        $status = post('status', '');
        $allowed = ['pending','confirmed','processing','shipped','delivered','cancelled','refunded'];
        if (!$id || !in_array($status, $allowed)) jsonResponse(['error' => 'Invalid data'], 422);
        $db->prepare('UPDATE orders SET status=?, updated_at=NOW() WHERE id=?')->execute([$status, $id]);
        // Notify buyer
        $stmt = $db->prepare('SELECT buyer_id, order_number FROM orders WHERE id=?');
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        if ($order) {
            $db->prepare('INSERT INTO notifications (user_id, type, title, message) VALUES (?,?,?,?)')
               ->execute([$order['buyer_id'], 'order_status', 'Order Updated',
                   'Your order ' . $order['order_number'] . ' status changed to ' . $status . '.']);
        }
        jsonResponse(['success' => true]);
        break;

    case 'products':
        $page   = max(1, (int)get('page', 1));
        $status = get('status', '');
        $q      = get('q', '');
        $where  = ['1=1'];
        $params = [];
        if ($status) { $where[] = 'p.status=?'; $params[] = $status; }
        if ($q)      { $where[] = 'p.name LIKE ?'; $params[] = "%$q%"; }
        $sql = 'SELECT p.*, s.company_name supplier_name FROM products p LEFT JOIN suppliers s ON s.id=p.supplier_id WHERE ' . implode(' AND ', $where) . ' ORDER BY p.created_at DESC';
        jsonResponse(paginate($db, $sql, $params, $page));
        break;

    case 'verify_supplier':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);
        $id       = (int)post('supplier_id', 0);
        $verified = post('verified', '1') === '1' ? 1 : 0;
        $db->prepare('UPDATE suppliers SET verified=? WHERE id=?')->execute([$verified, $id]);
        jsonResponse(['success' => true]);
        break;

    case 'reviews':
        $page   = max(1, (int)get('page', 1));
        $status = get('status', 'pending');
        $sql    = 'SELECT r.*, p.name product_name, u.first_name, u.last_name, u.email FROM reviews r JOIN products p ON p.id=r.product_id JOIN users u ON u.id=r.user_id WHERE r.status=? ORDER BY r.created_at DESC';
        jsonResponse(paginate($db, $sql, [$status], $page));
        break;

    case 'approve_review':
    case 'reject_review':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);
        $id     = (int)post('review_id', 0);
        $status = $action === 'approve_review' ? 'approved' : 'rejected';
        $db->prepare('UPDATE reviews SET status=? WHERE id=?')->execute([$status, $id]);
        jsonResponse(['success' => true]);
        break;

    case 'settings':
        if ($method === 'POST') {
            if (!verifyCsrf()) jsonResponse(['error' => 'Invalid CSRF token'], 403);
            foreach ($_POST as $key => $value) {
                if (strpos($key, '_') === 0) continue; // skip _csrf etc
                $db->prepare('INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?')->execute([$key, $value, $value]);
            }
            if (isset($_POST['_redirect'])) { flashMessage('success', 'Settings saved.'); redirect($_POST['_redirect']); }
            jsonResponse(['success' => true]);
        }
        $stmt = $db->query('SELECT `key`,`value`,group_name FROM settings ORDER BY group_name,`key`');
        $settings = [];
        foreach ($stmt->fetchAll() as $row) $settings[$row['key']] = $row['value'];
        jsonResponse(['data' => $settings]);
        break;

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}
