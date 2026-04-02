<?php
require_once __DIR__ . '/../includes/middleware.php';
header('Content-Type: application/json');
$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOut($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

switch ($action) {
    case 'list':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $page   = (int) get('page', 1);
        $result = paginate(
            $db,
            'SELECT * FROM sourcing_requests WHERE user_id = ? ORDER BY created_at DESC',
            [$userId],
            $page
        );
        jsonOut(['success' => true, 'data' => $result]);
        break;

    case 'detail':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $id     = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            jsonOut(['success' => false, 'message' => 'ID is required'], 400);
        }
        $stmt = $db->prepare('SELECT * FROM sourcing_requests WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $request = $stmt->fetch();
        if (!$request) {
            jsonOut(['success' => false, 'message' => 'Sourcing request not found'], 404);
        }
        $countStmt = $db->prepare('SELECT COUNT(*) FROM sourcing_quotes WHERE request_id = ?');
        $countStmt->execute([$id]);
        $request['quotes_count'] = (int) $countStmt->fetchColumn();
        jsonOut(['success' => true, 'data' => $request]);
        break;

    case 'create':
        requireAuth();
        validateCsrf();
        $userId      = $_SESSION['user_id'];
        $title       = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $category    = sanitize($_POST['category'] ?? '');
        $quantity    = (int) ($_POST['quantity'] ?? 0);
        $budget      = sanitize($_POST['budget'] ?? '');
        $deadline    = sanitize($_POST['deadline'] ?? '');
        if (!$title || !$description) {
            jsonOut(['success' => false, 'message' => 'title and description are required'], 400);
        }
        $stmt = $db->prepare(
            'INSERT INTO sourcing_requests (user_id, title, description, category, quantity, budget, deadline, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, \'open\', NOW())'
        );
        $stmt->execute([
            $userId, $title, $description, $category,
            $quantity ?: null, $budget ?: null, $deadline ?: null,
        ]);
        jsonOut(['success' => true, 'id' => $db->lastInsertId()]);
        break;

    case 'update':
        requireAuth();
        validateCsrf();
        $userId      = $_SESSION['user_id'];
        $id          = (int) ($_POST['id'] ?? 0);
        $title       = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $category    = sanitize($_POST['category'] ?? '');
        $quantity    = isset($_POST['quantity']) ? (int) $_POST['quantity'] : null;
        $budget      = sanitize($_POST['budget'] ?? '');
        $deadline    = sanitize($_POST['deadline'] ?? '');
        if (!$id) {
            jsonOut(['success' => false, 'message' => 'ID is required'], 400);
        }
        $checkStmt = $db->prepare('SELECT id FROM sourcing_requests WHERE id = ? AND user_id = ?');
        $checkStmt->execute([$id, $userId]);
        if (!$checkStmt->fetch()) {
            jsonOut(['success' => false, 'message' => 'Sourcing request not found'], 404);
        }
        $stmt = $db->prepare(
            'UPDATE sourcing_requests
             SET title = ?, description = ?, category = ?, quantity = ?, budget = ?, deadline = ?, updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([
            $title, $description, $category,
            $quantity, $budget ?: null, $deadline ?: null,
            $id, $userId,
        ]);
        jsonOut(['success' => true, 'message' => 'Sourcing request updated']);
        break;

    case 'delete':
        requireAuth();
        validateCsrf();
        $userId = $_SESSION['user_id'];
        $id     = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            jsonOut(['success' => false, 'message' => 'ID is required'], 400);
        }
        $stmt = $db->prepare('DELETE FROM sourcing_requests WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        if (!$stmt->rowCount()) {
            jsonOut(['success' => false, 'message' => 'Sourcing request not found'], 404);
        }
        jsonOut(['success' => true, 'message' => 'Sourcing request deleted']);
        break;

    case 'add_quote':
        requireAuth();
        validateCsrf();
        $userId    = $_SESSION['user_id'];
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $price     = sanitize($_POST['price'] ?? '');
        $leadTime  = sanitize($_POST['lead_time'] ?? '');
        $notes     = sanitize($_POST['notes'] ?? '');
        if (!$requestId || !$price) {
            jsonOut(['success' => false, 'message' => 'request_id and price are required'], 400);
        }
        $requestStmt = $db->prepare('SELECT id FROM sourcing_requests WHERE id = ?');
        $requestStmt->execute([$requestId]);
        if (!$requestStmt->fetch()) {
            jsonOut(['success' => false, 'message' => 'Sourcing request not found'], 404);
        }
        $stmt = $db->prepare(
            'INSERT INTO sourcing_quotes (request_id, supplier_id, price, lead_time, notes, status, created_at)
             VALUES (?, ?, ?, ?, ?, \'pending\', NOW())'
        );
        $stmt->execute([$requestId, $userId, $price, $leadTime ?: null, $notes ?: null]);
        jsonOut(['success' => true, 'id' => $db->lastInsertId()]);
        break;

    case 'update_quote':
        requireAuth();
        validateCsrf();
        $userId   = $_SESSION['user_id'];
        $id       = (int) ($_POST['id'] ?? 0);
        $price    = sanitize($_POST['price'] ?? '');
        $leadTime = sanitize($_POST['lead_time'] ?? '');
        $notes    = sanitize($_POST['notes'] ?? '');
        if (!$id) {
            jsonOut(['success' => false, 'message' => 'Quote ID is required'], 400);
        }
        $checkStmt = $db->prepare('SELECT id FROM sourcing_quotes WHERE id = ? AND supplier_id = ?');
        $checkStmt->execute([$id, $userId]);
        if (!$checkStmt->fetch()) {
            jsonOut(['success' => false, 'message' => 'Quote not found'], 404);
        }
        $stmt = $db->prepare(
            'UPDATE sourcing_quotes
             SET price = ?, lead_time = ?, notes = ?, updated_at = NOW()
             WHERE id = ? AND supplier_id = ?'
        );
        $stmt->execute([$price, $leadTime ?: null, $notes ?: null, $id, $userId]);
        jsonOut(['success' => true, 'message' => 'Quote updated']);
        break;

    case 'list_quotes':
        requireAuth();
        $userId    = $_SESSION['user_id'];
        $requestId = (int) ($_GET['request_id'] ?? 0);
        if (!$requestId) {
            jsonOut(['success' => false, 'message' => 'request_id is required'], 400);
        }
        $ownerStmt = $db->prepare('SELECT user_id FROM sourcing_requests WHERE id = ?');
        $ownerStmt->execute([$requestId]);
        $request = $ownerStmt->fetch();
        if (!$request) {
            jsonOut(['success' => false, 'message' => 'Sourcing request not found'], 404);
        }
        $supplierStmt = $db->prepare(
            'SELECT id FROM sourcing_quotes WHERE request_id = ? AND supplier_id = ?'
        );
        $supplierStmt->execute([$requestId, $userId]);
        $isOwner    = (int) $request['user_id'] === (int) $userId;
        $isSupplier = (bool) $supplierStmt->fetch();
        if (!$isOwner && !$isSupplier) {
            jsonOut(['success' => false, 'message' => 'Access denied'], 403);
        }
        $stmt = $db->prepare(
            'SELECT sq.*, u.name AS supplier_name
             FROM sourcing_quotes sq
             JOIN users u ON u.id = sq.supplier_id
             WHERE sq.request_id = ?
             ORDER BY sq.created_at ASC'
        );
        $stmt->execute([$requestId]);
        jsonOut(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    default:
        jsonOut(['success' => false, 'message' => 'Invalid action'], 400);
}
