<?php
/**
 * api/orders.php — Orders API (PR #7)
 */
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/orders.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$role   = $_SESSION['user_role'] ?? 'buyer';

// Determine supplierId if supplier
$supplierId = 0;
if (isSupplier() && !isAdmin()) {
    $sStmt = $db->prepare('SELECT id FROM suppliers WHERE user_id = ?');
    $sStmt->execute([$userId]);
    $supp = $sStmt->fetch();
    $supplierId = $supp ? (int)$supp['id'] : 0;
}

switch ($action) {

    // ── List orders ──────────────────────────────────────────
    case 'list':
        $page    = max(1, (int)get('page', 1));
        $perPage = max(5, min(100, (int)get('per_page', 15)));
        $filters = [
            'status'    => get('status', ''),
            'search'    => get('search', ''),
            'date_from' => get('date_from', ''),
            'date_to'   => get('date_to', ''),
        ];

        if (isAdmin()) {
            $filters['supplier_id']    = get('supplier_id', '');
            $filters['buyer_id']       = get('buyer_id', '');
            $filters['payment_method'] = get('payment_method', '');
            $result = getAdminOrders($db, $filters, $page, $perPage);
        } elseif (isSupplier()) {
            $result = getSupplierOrders($db, $supplierId, $filters, $page, $perPage);
        } else {
            $result = getBuyerOrders($db, $userId, $filters, $page, $perPage);
        }

        jsonResponse(['success' => true, 'data' => $result]);
        break;

    // ── Get single order ─────────────────────────────────────
    case 'get':
        $orderId = (int)get('order_id', 0);
        if (!$orderId) {
            jsonResponse(['success' => false, 'message' => 'order_id required'], 400);
        }

        if (isAdmin()) {
            $order = getOrder($db, $orderId);
        } elseif (isSupplier()) {
            $order = getOrder($db, $orderId, $supplierId, 'supplier');
        } else {
            $order = getOrder($db, $orderId, $userId, 'buyer');
        }

        if (!$order) {
            jsonResponse(['success' => false, 'message' => 'Order not found'], 404);
        }

        $order['status_history'] = getStatusHistory($db, $orderId);
        $order['notes']          = getOrderNotes($db, $orderId, isAdmin() || isSupplier());

        jsonResponse(['success' => true, 'data' => $order]);
        break;

    // ── Update status ─────────────────────────────────────────
    case 'update_status':
        if ($method !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);

        $orderId   = (int)post('order_id', 0);
        $newStatus = trim(post('status', ''));
        $note      = trim(post('note', ''));

        if (!$orderId || !$newStatus) {
            jsonResponse(['success' => false, 'message' => 'order_id and status required'], 400);
        }

        $actorRole = isAdmin() ? 'admin' : (isSupplier() ? 'supplier' : 'buyer');
        $actorId   = isAdmin() ? $userId : (isSupplier() ? $supplierId : $userId);

        $result = updateOrderStatus($db, $orderId, $newStatus, $actorId, $actorRole, $note);
        jsonResponse($result, $result['success'] ? 200 : 422);
        break;

    // ── Cancel order (buyer) ──────────────────────────────────
    case 'cancel':
        if ($method !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);

        $orderId = (int)post('order_id', 0);
        $reason  = trim(post('reason', ''));

        if (!$orderId) {
            jsonResponse(['success' => false, 'message' => 'order_id required'], 400);
        }

        // Verify ownership for buyers
        if (!isAdmin()) {
            $chk = $db->prepare('SELECT id FROM orders WHERE id = ? AND buyer_id = ?');
            $chk->execute([$orderId, $userId]);
            if (!$chk->fetch()) {
                jsonResponse(['success' => false, 'message' => 'Order not found'], 404);
            }
        }

        $result = cancelOrder($db, $orderId, $userId, $reason);
        jsonResponse($result, $result['success'] ? 200 : 422);
        break;

    // ── Add tracking (supplier only) ──────────────────────────
    case 'add_tracking':
        if ($method !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        if (!isSupplier())      jsonResponse(['success' => false, 'message' => 'Forbidden'], 403);
        if (!verifyCsrf())      jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);

        $orderId        = (int)post('order_id', 0);
        $carrier        = trim(post('carrier', ''));
        $trackingNumber = trim(post('tracking_number', ''));
        $trackingUrl    = trim(post('tracking_url', ''));

        if (!$orderId || !$trackingNumber) {
            jsonResponse(['success' => false, 'message' => 'order_id and tracking_number required'], 400);
        }

        $result = addTrackingInfo($db, $orderId, $supplierId > 0 ? $supplierId : $userId, $carrier, $trackingNumber, $trackingUrl);
        jsonResponse($result, $result['success'] ? 200 : 422);
        break;

    // ── Buyer confirms delivery ───────────────────────────────
    case 'confirm_delivery':
        if ($method !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);

        $orderId = (int)post('order_id', 0);
        if (!$orderId) {
            jsonResponse(['success' => false, 'message' => 'order_id required'], 400);
        }

        // Verify buyer ownership
        $chk = $db->prepare('SELECT id, status FROM orders WHERE id = ? AND buyer_id = ?');
        $chk->execute([$orderId, $userId]);
        $order = $chk->fetch();
        if (!$order) {
            jsonResponse(['success' => false, 'message' => 'Order not found'], 404);
        }

        $result = updateOrderStatus($db, $orderId, 'delivered', $userId, 'buyer', 'Delivery confirmed by buyer');
        jsonResponse($result, $result['success'] ? 200 : 422);
        break;

    // ── Add note ─────────────────────────────────────────────
    case 'add_note':
        if ($method !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);

        $orderId    = (int)post('order_id', 0);
        $noteText   = trim(post('note', ''));
        $isInternal = (bool)post('is_internal', false);

        if (!$orderId || !$noteText) {
            jsonResponse(['success' => false, 'message' => 'order_id and note required'], 400);
        }

        // Only admins/suppliers may add internal notes
        if ($isInternal && !isAdmin() && !isSupplier()) {
            $isInternal = false;
        }

        $noteId = addOrderNote($db, $orderId, $userId, $noteText, $isInternal);
        jsonResponse(['success' => true, 'data' => ['note_id' => $noteId]]);
        break;

    // ── Dashboard stats ───────────────────────────────────────
    case 'stats':
        if (isAdmin()) {
            $stats = getOrderStats($db, $userId, 'admin');
        } elseif (isSupplier()) {
            $stats = getOrderStats($db, $supplierId, 'supplier');
        } else {
            $stats = getOrderStats($db, $userId, 'buyer');
        }
        jsonResponse(['success' => true, 'data' => $stats]);
        break;

    // ── CSV export (supplier/admin) ───────────────────────────
    case 'export':
        if (!isAdmin() && !isSupplier()) {
            jsonResponse(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $filters = [
            'status'    => get('status', ''),
            'date_from' => get('date_from', ''),
            'date_to'   => get('date_to', ''),
        ];

        if (isAdmin()) {
            $result = getAdminOrders($db, $filters, 1, 5000);
        } else {
            $result = getSupplierOrders($db, $supplierId, $filters, 1, 5000);
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="orders.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Order #', 'Date', 'Status', 'Payment', 'Total', 'Buyer']);
        foreach ($result['data'] as $row) {
            fputcsv($out, [
                $row['order_number'],
                $row['placed_at'],
                $row['status'],
                $row['payment_status'],
                $row['total'],
                trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
            ]);
        }
        fclose($out);
        exit;

    // ── Bulk update (admin only) ──────────────────────────────
    case 'bulk_update':
        if ($method !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        if (!isAdmin())         jsonResponse(['success' => false, 'message' => 'Forbidden'], 403);
        if (!verifyCsrf())      jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);

        $rawIds    = post('order_ids', '');
        $newStatus = trim(post('status', ''));

        // Accept JSON array or regular POST array
        if (is_string($rawIds)) {
            $decoded  = json_decode($rawIds, true);
            $orderIds = is_array($decoded) ? $decoded : [];
        } elseif (is_array($rawIds)) {
            $orderIds = $rawIds;
        } else {
            $orderIds = [];
        }

        if (empty($orderIds) || !$newStatus) {
            jsonResponse(['success' => false, 'message' => 'order_ids and status required'], 400);
        }

        $updated = 0;
        $errors  = [];
        foreach ($orderIds as $oid) {
            $oid = (int)$oid;
            if (!$oid) continue;
            $res = updateOrderStatus($db, $oid, $newStatus, $userId, 'admin');
            if ($res['success']) {
                $updated++;
            } else {
                $errors[] = "Order #$oid: " . $res['message'];
            }
        }

        jsonResponse([
            'success' => true,
            'data'    => ['updated' => $updated, 'errors' => $errors],
            'message' => "$updated order(s) updated.",
        ]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
}
