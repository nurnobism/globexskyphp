<?php
/**
 * api/trade-shows.php — Trade Shows API
 */
require_once __DIR__ . '/../includes/middleware.php';

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

switch ($action) {

    case 'list':
        $page     = max(1, (int)get('page', 1));
        $status   = get('status', '');
        $upcoming = get('upcoming', '');

        $where  = ['1=1'];
        $params = [];

        if ($status) {
            $where[]  = 'ts.status = ?';
            $params[] = $status;
        }
        if ($upcoming) {
            $where[]  = 'ts.start_date >= CURDATE()';
        }

        $sql = 'SELECT ts.*, COUNT(tsb.id) booth_count
                FROM trade_shows ts
                LEFT JOIN trade_show_booths tsb ON tsb.show_id = ts.id
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY ts.id
                ORDER BY ts.start_date ASC';
        jsonResponse(paginate($db, $sql, $params, $page, 12));
        break;

    case 'detail':
        $id = (int)get('id', 0);
        if (!$id) jsonResponse(['error' => 'Trade show ID required'], 400);

        $stmt = $db->prepare(
            'SELECT ts.*, COUNT(tsb.id) booth_count
             FROM trade_shows ts
             LEFT JOIN trade_show_booths tsb ON tsb.show_id = ts.id
             WHERE ts.id = ?
             GROUP BY ts.id'
        );
        $stmt->execute([$id]);
        $show = $stmt->fetch();
        if (!$show) jsonResponse(['error' => 'Trade show not found'], 404);

        // Fetch booths
        $bStmt = $db->prepare(
            'SELECT tsb.*, s.company_name, s.logo
             FROM trade_show_booths tsb
             JOIN suppliers s ON s.id = tsb.supplier_id
             WHERE tsb.show_id = ?
             ORDER BY tsb.booth_number'
        );
        $bStmt->execute([$id]);
        $show['booths'] = $bStmt->fetchAll();

        jsonResponse(['data' => $show]);
        break;

    case 'register_booth':
        requireLogin();
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);

        $showId      = (int)post('show_id', 0);
        $boothNumber = trim(post('booth_number', ''));
        $notes       = trim(post('notes', ''));

        if (!$showId) jsonResponse(['error' => 'show_id is required'], 400);

        // Verify supplier status
        $sStmt = $db->prepare('SELECT id FROM suppliers WHERE user_id = ? AND status = "active"');
        $sStmt->execute([$_SESSION['user_id']]);
        $supplier = $sStmt->fetch();
        if (!$supplier) jsonResponse(['error' => 'Active supplier account required'], 403);

        // Check show exists and is open for registration
        $tsStmt = $db->prepare('SELECT id, status, registration_deadline FROM trade_shows WHERE id = ?');
        $tsStmt->execute([$showId]);
        $tradeShow = $tsStmt->fetch();
        if (!$tradeShow) jsonResponse(['error' => 'Trade show not found'], 404);
        if ($tradeShow['status'] !== 'open') jsonResponse(['error' => 'Registration is not open for this show'], 400);
        if (!empty($tradeShow['registration_deadline'])) {
            $deadline = new DateTime($tradeShow['registration_deadline']);
            $today    = new DateTime('today');
            if ($deadline < $today) {
                jsonResponse(['error' => 'Registration deadline has passed'], 400);
            }
        }

        // Prevent duplicate registration
        $dup = $db->prepare('SELECT id FROM trade_show_booths WHERE show_id = ? AND supplier_id = ?');
        $dup->execute([$showId, $supplier['id']]);
        if ($dup->fetch()) jsonResponse(['error' => 'Already registered for this trade show'], 409);

        $db->prepare(
            'INSERT INTO trade_show_booths (show_id, supplier_id, booth_number, notes, status)
             VALUES (?, ?, ?, ?, "pending")'
        )->execute([$showId, $supplier['id'], $boothNumber ?: null, $notes ?: null]);

        jsonResponse(['success' => true, 'booth_id' => (int)$db->lastInsertId()]);
        break;

    case 'my_registrations':
        requireLogin();
        $sStmt = $db->prepare('SELECT id FROM suppliers WHERE user_id = ?');
        $sStmt->execute([$_SESSION['user_id']]);
        $supplier = $sStmt->fetch();
        if (!$supplier) jsonResponse(['data' => []]);

        $page = max(1, (int)get('page', 1));
        $sql  = 'SELECT tsb.*, ts.name show_name, ts.start_date, ts.end_date, ts.location
                 FROM trade_show_booths tsb
                 JOIN trade_shows ts ON ts.id = tsb.show_id
                 WHERE tsb.supplier_id = ?
                 ORDER BY ts.start_date DESC';
        jsonResponse(paginate($db, $sql, [$supplier['id']], $page, 10));
        break;

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}
