<?php
/**
 * api/rfq.php — Request for Quotation API
 */

require_once __DIR__ . '/../includes/middleware.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

switch ($action) {

    case 'list':
        $page = max(1, (int)get('page', 1));
        if (isAdmin()) {
            $sql = 'SELECT r.*, u.first_name, u.last_name, u.email, c.name category_name,
                    (SELECT COUNT(*) FROM rfq_quotes WHERE rfq_id=r.id) quote_count
                    FROM rfqs r JOIN users u ON u.id=r.buyer_id
                    LEFT JOIN categories c ON c.id=r.category_id
                    ORDER BY r.created_at DESC';
            jsonResponse(paginate($db, $sql, [], $page));
        }
        $sql = 'SELECT r.*, c.name category_name,
                (SELECT COUNT(*) FROM rfq_quotes WHERE rfq_id=r.id) quote_count
                FROM rfqs r LEFT JOIN categories c ON c.id=r.category_id
                WHERE r.buyer_id = ? ORDER BY r.created_at DESC';
        jsonResponse(paginate($db, $sql, [$_SESSION['user_id']], $page));
        break;

    case 'open':
        // Public RFQ listing (for suppliers to browse)
        $page = max(1, (int)get('page', 1));
        $sql  = 'SELECT r.*, u.company_name buyer_company, c.name category_name,
                 (SELECT COUNT(*) FROM rfq_quotes WHERE rfq_id=r.id) quote_count
                 FROM rfqs r JOIN users u ON u.id=r.buyer_id
                 LEFT JOIN categories c ON c.id=r.category_id
                 WHERE r.status="open" AND (r.deadline IS NULL OR r.deadline >= CURDATE())
                 ORDER BY r.created_at DESC';
        jsonResponse(paginate($db, $sql, [], $page));
        break;

    case 'detail':
        $id = (int)get('id', 0);
        $stmt = $db->prepare('SELECT r.*, c.name category_name FROM rfqs r
            LEFT JOIN categories c ON c.id=r.category_id WHERE r.id=?');
        $stmt->execute([$id]);
        $rfq = $stmt->fetch();
        if (!$rfq) jsonResponse(['error' => 'RFQ not found'], 404);
        // Only owner or admin can see details
        if ($rfq['buyer_id'] != $_SESSION['user_id'] && !isAdmin() && !isSupplier()) {
            jsonResponse(['error' => 'Forbidden'], 403);
        }
        $qStmt = $db->prepare('SELECT q.*, s.company_name FROM rfq_quotes q
            JOIN suppliers s ON s.id=q.supplier_id WHERE q.rfq_id=? ORDER BY q.created_at');
        $qStmt->execute([$id]);
        $rfq['quotes'] = $qStmt->fetchAll();
        jsonResponse(['data' => $rfq]);
        break;

    case 'create':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);

        $title    = trim(post('title', ''));
        $desc     = trim(post('description', ''));
        $category = (int)post('category_id', 0) ?: null;
        $qty      = (int)post('quantity', 0) ?: null;
        $unit     = trim(post('unit', ''));
        $price    = (float)post('target_price', 0) ?: null;
        $dest     = trim(post('destination_country', ''));
        $deadline = post('deadline', '') ?: null;

        if (empty($title)) jsonResponse(['error' => 'Title is required'], 422);

        $rfqNumber = 'RFQ-' . strtoupper(substr(md5(uniqid()), 0, 8));

        $db->prepare('INSERT INTO rfqs (rfq_number, buyer_id, title, description, category_id, quantity, unit, target_price, destination_country, deadline)
            VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([$rfqNumber, $_SESSION['user_id'], $title, $desc, $category, $qty, $unit, $price, $dest, $deadline]);

        $id = $db->lastInsertId();
        if (isset($_POST['_redirect'])) { flashMessage('success', 'RFQ submitted successfully!'); redirect('/pages/rfq/index.php'); }
        jsonResponse(['success' => true, 'id' => $id, 'rfq_number' => $rfqNumber]);
        break;

    case 'submit_quote':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);
        if (!isSupplier())      jsonResponse(['error' => 'Supplier account required'], 403);

        $rfqId     = (int)post('rfq_id', 0);
        $unitPrice = (float)post('unit_price', 0);
        $leadTime  = trim(post('lead_time', ''));
        $message   = trim(post('message', ''));
        $validUntil = post('valid_until', '') ?: null;

        if (!$rfqId || $unitPrice <= 0) jsonResponse(['error' => 'RFQ ID and unit price required'], 422);

        $suppStmt = $db->prepare('SELECT id FROM suppliers WHERE user_id = ?');
        $suppStmt->execute([$_SESSION['user_id']]);
        $supplier = $suppStmt->fetch();
        if (!$supplier) jsonResponse(['error' => 'Supplier profile not found'], 404);

        $rfqStmt = $db->prepare('SELECT id FROM rfqs WHERE id = ? AND status = "open"');
        $rfqStmt->execute([$rfqId]);
        if (!$rfqStmt->fetch()) jsonResponse(['error' => 'RFQ not found or closed'], 404);

        $db->prepare('INSERT INTO rfq_quotes (rfq_id, supplier_id, unit_price, lead_time, message, valid_until) VALUES (?,?,?,?,?,?)')
           ->execute([$rfqId, $supplier['id'], $unitPrice, $leadTime, $message, $validUntil]);

        jsonResponse(['success' => true]);
        break;

    case 'close':
    case 'cancel':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);
        $id = (int)post('rfq_id', 0);
        $stmt = $db->prepare('SELECT id FROM rfqs WHERE id = ? AND buyer_id = ?');
        $stmt->execute([$id, $_SESSION['user_id']]);
        if (!$stmt->fetch() && !isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        $newStatus = $action === 'close' ? 'closed' : 'cancelled';
        $db->prepare('UPDATE rfqs SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
        jsonResponse(['success' => true]);
        break;

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}
