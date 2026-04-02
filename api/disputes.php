<?php
require_once __DIR__ . '/../includes/middleware.php';
header('Content-Type: application/json');
$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOut($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

switch ($action) {
    case 'list':
        requireAuth();
        $uid = $_SESSION['user_id'];
        $stmt = $db->prepare('SELECT * FROM disputes WHERE buyer_id = ? OR seller_id = ? ORDER BY id DESC');
        $stmt->execute([$uid, $uid]);
        jsonOut(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'create':
        requireAuth();
        validateCsrf();
        $uid      = $_SESSION['user_id'];
        $orderId  = sanitize($_POST['order_id'] ?? '');
        $title    = sanitize($_POST['title'] ?? '');
        $desc     = sanitize($_POST['description'] ?? '');
        $evidence = sanitize($_POST['evidence_url'] ?? '');

        if (!$orderId || !$title || !$desc) {
            jsonOut(['success' => false, 'message' => 'order_id, title, and description are required'], 422);
        }

        // Verify the order belongs to this user and fetch seller
        $ord = $db->prepare('SELECT * FROM orders WHERE id = ? AND (buyer_id = ? OR supplier_id = ?)');
        $ord->execute([$orderId, $uid, $uid]);
        $order = $ord->fetch();
        if (!$order) {
            jsonOut(['success' => false, 'message' => 'Order not found or access denied'], 404);
        }

        $sellerId = $order['supplier_id'] ?? $order['seller_id'] ?? null;
        $buyerId  = $order['buyer_id'] ?? null;

        $stmt = $db->prepare(
            'INSERT INTO disputes (order_id, buyer_id, seller_id, title, description, evidence_url, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, \'open\', NOW())'
        );
        $stmt->execute([$orderId, $buyerId, $sellerId, $title, $desc, $evidence]);
        jsonOut(['success' => true, 'id' => $db->lastInsertId()]);
        break;

    case 'update':
        requireAuth();
        validateCsrf();
        $uid  = $_SESSION['user_id'];
        $id   = sanitize($_POST['id'] ?? '');
        $desc = sanitize($_POST['description'] ?? '');
        $evidence = sanitize($_POST['evidence_url'] ?? '');

        if (!$id) {
            jsonOut(['success' => false, 'message' => 'id is required'], 422);
        }

        $check = $db->prepare('SELECT * FROM disputes WHERE id = ? AND (buyer_id = ? OR seller_id = ?)');
        $check->execute([$id, $uid, $uid]);
        $dispute = $check->fetch();
        if (!$dispute) {
            jsonOut(['success' => false, 'message' => 'Dispute not found or access denied'], 404);
        }
        if ($dispute['status'] === 'resolved') {
            jsonOut(['success' => false, 'message' => 'Cannot update a resolved dispute'], 403);
        }

        $fields = [];
        $params = [];
        if ($desc !== '') { $fields[] = 'description = ?'; $params[] = $desc; }
        if ($evidence !== '') { $fields[] = 'evidence_url = ?'; $params[] = $evidence; }

        if (empty($fields)) {
            jsonOut(['success' => false, 'message' => 'No fields to update'], 422);
        }

        $params[] = $id;
        $stmt = $db->prepare('UPDATE disputes SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = ?');
        $stmt->execute($params);
        jsonOut(['success' => true, 'message' => 'Dispute updated']);
        break;

    case 'resolve':
        requireRole('admin');
        validateCsrf();
        $id   = sanitize($_POST['id'] ?? '');
        $note = sanitize($_POST['resolution_note'] ?? '');

        if (!$id) {
            jsonOut(['success' => false, 'message' => 'id is required'], 422);
        }

        $check = $db->prepare('SELECT id FROM disputes WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetch()) {
            jsonOut(['success' => false, 'message' => 'Dispute not found'], 404);
        }

        $stmt = $db->prepare(
            'UPDATE disputes SET status = \'resolved\', resolution_note = ?, resolved_at = NOW(), updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$note, $id]);
        jsonOut(['success' => true, 'message' => 'Dispute resolved']);
        break;

    case 'get_evidence':
        requireAuth();
        $uid = $_SESSION['user_id'];
        $id  = sanitize($_GET['id'] ?? $_POST['id'] ?? '');

        if (!$id) {
            jsonOut(['success' => false, 'message' => 'id is required'], 422);
        }

        $stmt = $db->prepare(
            'SELECT evidence_url, notes FROM disputes WHERE id = ? AND (buyer_id = ? OR seller_id = ?)'
        );
        $stmt->execute([$id, $uid, $uid]);
        $row = $stmt->fetch();
        if (!$row) {
            jsonOut(['success' => false, 'message' => 'Dispute not found or access denied'], 404);
        }
        jsonOut(['success' => true, 'data' => $row]);
        break;

    default:
        jsonOut(['success' => false, 'message' => 'Invalid action'], 400);
}
