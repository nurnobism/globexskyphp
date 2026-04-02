<?php
/**
 * api/returns.php — Returns & Refunds API
 */
require_once __DIR__ . '/../includes/middleware.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();
$uid    = $_SESSION['user_id'];

switch ($action) {

    case 'list':
        $page = max(1, (int)get('page', 1));
        $sql  = 'SELECT r.*, o.order_number
                 FROM return_requests r
                 LEFT JOIN orders o ON o.id = r.order_id
                 WHERE r.user_id = ?
                 ORDER BY r.created_at DESC';
        jsonResponse(paginate($db, $sql, [$uid], $page, 10));
        break;

    case 'detail':
        $id   = (int)get('id', 0);
        if (!$id) jsonResponse(['error' => 'Return request ID required'], 400);
        $stmt = $db->prepare(
            'SELECT r.*, o.order_number, o.total order_total
             FROM return_requests r
             LEFT JOIN orders o ON o.id = r.order_id
             WHERE r.id = ? AND r.user_id = ?'
        );
        $stmt->execute([$id, $uid]);
        $row = $stmt->fetch();
        if (!$row) jsonResponse(['error' => 'Return request not found'], 404);
        jsonResponse(['data' => $row]);
        break;

    case 'create':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);

        $orderId        = (int)post('order_id', 0);
        $reason         = trim(post('reason', ''));
        $reasonCode     = trim(post('reason_code', 'other'));
        $resolutionType = trim(post('resolution_type', 'refund'));
        $evidenceUrl    = trim(post('evidence_url', ''));

        if (!$orderId || !$reason) {
            jsonResponse(['error' => 'order_id and reason are required'], 422);
        }

        // Verify order belongs to user and is delivered
        $oStmt = $db->prepare('SELECT id, total, status FROM orders WHERE id = ? AND buyer_id = ?');
        $oStmt->execute([$orderId, $uid]);
        $order = $oStmt->fetch();

        if (!$order) jsonResponse(['error' => 'Order not found'], 404);
        if ($order['status'] !== 'delivered') {
            jsonResponse(['error' => 'Returns can only be requested for delivered orders'], 400);
        }

        // Prevent duplicate pending returns for same order
        $dup = $db->prepare('SELECT id FROM return_requests WHERE order_id = ? AND user_id = ? AND status = "pending"');
        $dup->execute([$orderId, $uid]);
        if ($dup->fetch()) {
            if (isset($_POST['_redirect'])) { flashMessage('warning', 'A pending return already exists for this order.'); redirect($_POST['_redirect']); }
            jsonResponse(['error' => 'A pending return already exists for this order'], 409);
        }

        $db->prepare(
            'INSERT INTO return_requests (user_id, order_id, reason_code, reason, resolution_type, evidence_url, status)
             VALUES (?, ?, ?, ?, ?, ?, "pending")'
        )->execute([$uid, $orderId, $reasonCode, $reason, $resolutionType, $evidenceUrl ?: null]);

        $returnId = (int)$db->lastInsertId();

        // Notify user
        $db->prepare('INSERT INTO notifications (user_id, type, title, message) VALUES (?, "return_requested", ?, ?)')
           ->execute([$uid, 'Return Requested', 'Your return request #' . $returnId . ' has been submitted for review.']);

        if (isset($_POST['_redirect'])) { flashMessage('success', 'Return request submitted successfully!'); redirect($_POST['_redirect']); }
        jsonResponse(['success' => true, 'return_id' => $returnId]);
        break;

    case 'cancel':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);

        $id   = (int)post('return_id', 0);
        $stmt = $db->prepare('SELECT id, status FROM return_requests WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $uid]);
        $row = $stmt->fetch();

        if (!$row) jsonResponse(['error' => 'Return request not found'], 404);
        if ($row['status'] !== 'pending') {
            jsonResponse(['error' => 'Only pending requests can be cancelled'], 400);
        }

        $db->prepare('UPDATE return_requests SET status = "cancelled", updated_at = NOW() WHERE id = ?')->execute([$id]);
        jsonResponse(['success' => true]);
        break;

    // Admin: approve / reject / process refund
    case 'approve':
    case 'reject':
        if (!isAdmin()) jsonResponse(['error' => 'Admin access required'], 403);
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);

        $id          = (int)post('return_id', 0);
        $adminNotes  = trim(post('admin_notes', ''));
        $refundAmount= (float)post('refund_amount', 0);
        $newStatus   = $action === 'approve' ? 'approved' : 'rejected';

        $stmt = $db->prepare('SELECT id FROM return_requests WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) jsonResponse(['error' => 'Return request not found'], 404);

        $db->prepare(
            'UPDATE return_requests SET status = ?, admin_notes = ?, refund_amount = ?, reviewed_at = NOW(), updated_at = NOW() WHERE id = ?'
        )->execute([$newStatus, $adminNotes, $refundAmount ?: null, $id]);

        jsonResponse(['success' => true]);
        break;

    case 'refund':
        if (!isAdmin()) jsonResponse(['error' => 'Admin access required'], 403);
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);

        $id = (int)post('return_id', 0);
        $db->prepare(
            'UPDATE return_requests SET status = "refunded", updated_at = NOW() WHERE id = ? AND status = "approved"'
        )->execute([$id]);

        jsonResponse(['success' => true]);
        break;

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}
