<?php
/**
 * api/inspections.php — Inspection CRUD API
 */

require_once __DIR__ . '/../includes/middleware.php';
requireLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();
$userId = $_SESSION['user_id'];
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

switch ($action) {

    case 'list':
        $page   = max(1, (int)get('page', 1));
        $status = get('status', '');
        $where  = $isAdmin ? ['1=1'] : ['i.buyer_id = ?'];
        $params = $isAdmin ? [] : [$userId];
        if ($status) { $where[] = 'i.status = ?'; $params[] = $status; }
        $sql = 'SELECT i.*, u.name buyer_name FROM inspections i
                LEFT JOIN users u ON u.id = i.buyer_id
                WHERE ' . implode(' AND ', $where) . ' ORDER BY i.created_at DESC';
        jsonResponse(paginate($db, $sql, $params, $page));
        break;

    case 'detail':
        $id = (int)get('id', 0);
        if (!$id) jsonResponse(['error' => 'Inspection ID required'], 400);
        $cond = $isAdmin ? 'i.id = ?' : 'i.id = ? AND i.buyer_id = ?';
        $params = $isAdmin ? [$id] : [$id, $userId];
        $stmt = $db->prepare("SELECT i.*, u.name buyer_name FROM inspections i
            LEFT JOIN users u ON u.id = i.buyer_id WHERE $cond");
        $stmt->execute($params);
        $insp = $stmt->fetch();
        if (!$insp) jsonResponse(['error' => 'Not found'], 404);
        $tStmt = $db->prepare('SELECT * FROM inspection_timeline WHERE inspection_id = ? ORDER BY created_at ASC');
        $tStmt->execute([$id]);
        $insp['timeline'] = $tStmt->fetchAll();
        jsonResponse(['data' => $insp]);
        break;

    case 'request':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);
        $prices = ['pre_production' => 150, 'during_production' => 200, 'pre_shipment' => 180, 'full_audit' => 500];
        $type   = post('inspection_type', '');
        if (!array_key_exists($type, $prices)) jsonResponse(['error' => 'Invalid inspection type'], 422);
        $supplier = trim(post('supplier_name', ''));
        $product  = trim(post('product_name', ''));
        $qty      = (int)post('quantity', 0);
        $date     = post('inspection_date', '');
        $address  = trim(post('factory_address', ''));
        if (!$supplier || !$product || !$qty || !$date || !$address) {
            jsonResponse(['error' => 'All fields are required'], 422);
        }
        $refNo = 'INS-' . strtoupper(substr(md5(uniqid()), 0, 8));
        $db->prepare('INSERT INTO inspections (reference_no, buyer_id, supplier_name, product_name, quantity,
            inspection_date, factory_address, inspection_type, price, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "requested", NOW())')
            ->execute([$refNo, $userId, $supplier, $product, $qty, $date, $address, $type, $prices[$type]]);
        $inspId = (int)$db->lastInsertId();
        $db->prepare('INSERT INTO inspection_timeline (inspection_id, status, notes, created_at) VALUES (?, "requested", "Inspection request submitted.", NOW())')
            ->execute([$inspId]);
        jsonResponse(['success' => true, 'inspection_id' => $inspId, 'reference_no' => $refNo, 'price' => $prices[$type]]);
        break;

    case 'update_status':
        if (!$isAdmin)          jsonResponse(['error' => 'Forbidden'], 403);
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);
        $allowed = ['requested', 'scheduled', 'inspector_assigned', 'in_progress', 'report_ready', 'completed', 'cancelled'];
        $id      = (int)post('inspection_id', 0);
        $status  = post('status', '');
        $notes   = trim(post('notes', ''));
        if (!$id || !in_array($status, $allowed)) jsonResponse(['error' => 'Invalid data'], 422);
        $db->prepare('UPDATE inspections SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$status, $id]);
        $db->prepare('INSERT INTO inspection_timeline (inspection_id, status, notes, created_at) VALUES (?, ?, ?, NOW())')
            ->execute([$id, $status, $notes ?: ucwords(str_replace('_', ' ', $status))]);
        jsonResponse(['success' => true]);
        break;

    case 'report':
        $id = (int)get('id', (int)post('inspection_id', 0));
        if (!$id) jsonResponse(['error' => 'Inspection ID required'], 400);
        if ($method === 'GET') {
            $cond   = $isAdmin ? 'id = ?' : 'id = ? AND buyer_id = ?';
            $params = $isAdmin ? [$id] : [$id, $userId];
            $stmt   = $db->prepare("SELECT * FROM inspections WHERE $cond");
            $stmt->execute($params);
            $insp = $stmt->fetch();
            if (!$insp) jsonResponse(['error' => 'Not found'], 404);
            $rStmt = $db->prepare('SELECT * FROM inspection_reports WHERE inspection_id = ?');
            $rStmt->execute([$id]);
            jsonResponse(['data' => ['inspection' => $insp, 'report' => $rStmt->fetch() ?: null]]);
        }
        if (!$isAdmin)     jsonResponse(['error' => 'Forbidden'], 403);
        if (!verifyCsrf()) jsonResponse(['error' => 'Invalid CSRF token'], 403);
        $score        = (int)post('overall_score', 0);
        $result       = post('result', 'fail');
        $checklist    = post('checklist', '[]');
        $recommendations = trim(post('recommendations', ''));
        $db->prepare('INSERT INTO inspection_reports (inspection_id, overall_score, result, checklist, recommendations, created_at)
            VALUES (?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE overall_score=VALUES(overall_score),
            result=VALUES(result), checklist=VALUES(checklist), recommendations=VALUES(recommendations), updated_at=NOW()')
            ->execute([$id, $score, $result, $checklist, $recommendations]);
        $db->prepare('UPDATE inspections SET status = "report_ready", updated_at = NOW() WHERE id = ?')->execute([$id]);
        jsonResponse(['success' => true]);
        break;

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}
