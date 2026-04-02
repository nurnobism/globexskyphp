<?php
require_once __DIR__ . '/../includes/middleware.php';
header('Content-Type: application/json');
$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOut($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

switch ($action) {
    case 'list':
        requireAuth();
        $uid  = $_SESSION['user_id'];
        $stmt = $db->prepare(
            'SELECT * FROM escrow_transactions WHERE buyer_id = ? OR seller_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$uid, $uid]);
        jsonOut(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'detail':
        requireAuth();
        $uid = $_SESSION['user_id'];
        $id  = sanitize($_GET['id'] ?? $_POST['id'] ?? '');

        if (!$id) {
            jsonOut(['success' => false, 'message' => 'id is required'], 422);
        }

        $stmt = $db->prepare(
            'SELECT * FROM escrow_transactions WHERE id = ? AND (buyer_id = ? OR seller_id = ?)'
        );
        $stmt->execute([$id, $uid, $uid]);
        $row = $stmt->fetch();
        if (!$row) {
            jsonOut(['success' => false, 'message' => 'Escrow transaction not found or access denied'], 404);
        }
        jsonOut(['success' => true, 'data' => $row]);
        break;

    case 'create':
        requireAuth();
        validateCsrf();
        $uid      = $_SESSION['user_id'];
        $orderId  = sanitize($_POST['order_id'] ?? '');
        $amount   = sanitize($_POST['amount'] ?? '');
        $currency = sanitize($_POST['currency'] ?? 'USD');
        $desc     = sanitize($_POST['description'] ?? '');

        if (!$orderId || !$amount) {
            jsonOut(['success' => false, 'message' => 'order_id and amount are required'], 422);
        }
        if (!is_numeric($amount) || $amount <= 0) {
            jsonOut(['success' => false, 'message' => 'amount must be a positive number'], 422);
        }

        // Resolve seller from order
        $ord = $db->prepare('SELECT * FROM orders WHERE id = ? AND buyer_id = ?');
        $ord->execute([$orderId, $uid]);
        $order = $ord->fetch();
        if (!$order) {
            jsonOut(['success' => false, 'message' => 'Order not found or you are not the buyer'], 404);
        }

        $sellerId = $order['supplier_id'] ?? $order['seller_id'] ?? null;

        $stmt = $db->prepare(
            'INSERT INTO escrow_transactions (order_id, buyer_id, seller_id, amount, currency, description, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, \'pending\', NOW())'
        );
        $stmt->execute([$orderId, $uid, $sellerId, $amount, $currency, $desc]);
        jsonOut(['success' => true, 'id' => $db->lastInsertId()]);
        break;

    case 'release':
        requireAuth();
        validateCsrf();
        $uid = $_SESSION['user_id'];
        $id  = sanitize($_POST['id'] ?? '');

        if (!$id) {
            jsonOut(['success' => false, 'message' => 'id is required'], 422);
        }

        $check = $db->prepare('SELECT * FROM escrow_transactions WHERE id = ? AND buyer_id = ?');
        $check->execute([$id, $uid]);
        $tx = $check->fetch();
        if (!$tx) {
            jsonOut(['success' => false, 'message' => 'Transaction not found or you are not the buyer'], 404);
        }
        if ($tx['status'] !== 'pending') {
            jsonOut(['success' => false, 'message' => 'Only pending transactions can be released'], 403);
        }

        $stmt = $db->prepare(
            'UPDATE escrow_transactions SET status = \'released\', released_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$id]);
        jsonOut(['success' => true, 'message' => 'Escrow released']);
        break;

    case 'dispute':
        requireAuth();
        validateCsrf();
        $uid    = $_SESSION['user_id'];
        $id     = sanitize($_POST['id'] ?? '');
        $reason = sanitize($_POST['dispute_reason'] ?? '');

        if (!$id || !$reason) {
            jsonOut(['success' => false, 'message' => 'id and dispute_reason are required'], 422);
        }

        $check = $db->prepare(
            'SELECT * FROM escrow_transactions WHERE id = ? AND (buyer_id = ? OR seller_id = ?)'
        );
        $check->execute([$id, $uid, $uid]);
        $tx = $check->fetch();
        if (!$tx) {
            jsonOut(['success' => false, 'message' => 'Transaction not found or access denied'], 404);
        }
        if (!in_array($tx['status'], ['pending', 'released'])) {
            jsonOut(['success' => false, 'message' => 'Transaction cannot be disputed in its current state'], 403);
        }

        $stmt = $db->prepare(
            'UPDATE escrow_transactions SET status = \'disputed\', disputed_at = NOW(), dispute_reason = ? WHERE id = ?'
        );
        $stmt->execute([$reason, $id]);
        jsonOut(['success' => true, 'message' => 'Escrow dispute raised']);
        break;

    default:
        jsonOut(['success' => false, 'message' => 'Invalid action'], 400);
}
