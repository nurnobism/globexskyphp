<?php
require_once __DIR__ . '/../includes/middleware.php';
header('Content-Type: application/json');
$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOut($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

switch ($action) {
    case 'list_products':
        $stmt = $db->query(
            'SELECT id, name, sku, price, supplier_id, sample_available
             FROM products
             WHERE sample_available = 1
             ORDER BY name ASC'
        );
        jsonOut(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'request':
        requireAuth();
        validateCsrf();
        $userId          = $_SESSION['user_id'];
        $productId       = (int) ($_POST['product_id'] ?? 0);
        $quantity        = (int) ($_POST['quantity'] ?? 1);
        $shippingAddress = sanitize($_POST['shipping_address'] ?? '');
        if (!$productId || !$shippingAddress) {
            jsonOut(['success' => false, 'message' => 'product_id and shipping_address are required'], 400);
        }
        if ($quantity < 1) {
            jsonOut(['success' => false, 'message' => 'Quantity must be at least 1'], 400);
        }
        $productStmt = $db->prepare('SELECT id FROM products WHERE id = ? AND sample_available = 1');
        $productStmt->execute([$productId]);
        if (!$productStmt->fetch()) {
            jsonOut(['success' => false, 'message' => 'Product not found or sample not available'], 404);
        }
        $stmt = $db->prepare(
            'INSERT INTO sample_requests (user_id, product_id, quantity, shipping_address, status, created_at)
             VALUES (?, ?, ?, ?, \'pending\', NOW())'
        );
        $stmt->execute([$userId, $productId, $quantity, $shippingAddress]);
        jsonOut(['success' => true, 'id' => $db->lastInsertId()]);
        break;

    case 'list_requests':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $page   = (int) get('page', 1);
        $result = paginate(
            $db,
            'SELECT sr.*, p.name AS product_name
             FROM sample_requests sr
             JOIN products p ON p.id = sr.product_id
             WHERE sr.user_id = ?
             ORDER BY sr.created_at DESC',
            [$userId],
            $page
        );
        jsonOut(['success' => true, 'data' => $result]);
        break;

    case 'update_status':
        requireRole('admin');
        validateCsrf();
        $id             = (int) ($_POST['id'] ?? 0);
        $status         = sanitize($_POST['status'] ?? '');
        $trackingNumber = sanitize($_POST['tracking_number'] ?? '');
        $validStatuses  = ['pending', 'approved', 'shipped', 'delivered', 'rejected'];
        if (!$id || !$status) {
            jsonOut(['success' => false, 'message' => 'id and status are required'], 400);
        }
        if (!in_array($status, $validStatuses, true)) {
            jsonOut(['success' => false, 'message' => 'Invalid status'], 400);
        }
        $stmt = $db->prepare(
            'UPDATE sample_requests SET status = ?, tracking_number = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$status, $trackingNumber ?: null, $id]);
        if (!$stmt->rowCount()) {
            jsonOut(['success' => false, 'message' => 'Sample request not found'], 404);
        }
        jsonOut(['success' => true, 'message' => 'Status updated']);
        break;

    case 'track':
        requireAuth();
        $userId         = $_SESSION['user_id'];
        $trackingNumber = sanitize($_GET['tracking_number'] ?? '');
        if (!$trackingNumber) {
            jsonOut(['success' => false, 'message' => 'tracking_number is required'], 400);
        }
        $stmt = $db->prepare(
            'SELECT * FROM sample_requests WHERE tracking_number = ? AND user_id = ?'
        );
        $stmt->execute([$trackingNumber, $userId]);
        $request = $stmt->fetch();
        if (!$request) {
            jsonOut(['success' => false, 'message' => 'Tracking record not found'], 404);
        }
        jsonOut(['success' => true, 'data' => $request]);
        break;

    default:
        jsonOut(['success' => false, 'message' => 'Invalid action'], 400);
}
