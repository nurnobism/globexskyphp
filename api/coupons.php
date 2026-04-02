<?php
require_once __DIR__ . '/../includes/middleware.php';
header('Content-Type: application/json');
$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOut($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

switch ($action) {
    case 'list':
        $page   = (int)($_GET['page'] ?? 1);
        $sql    = 'SELECT * FROM coupons ORDER BY created_at DESC';
        $result = paginate($db, $sql, [], $page);
        jsonOut(['success' => true, 'data' => $result]);

    case 'detail':
        $id   = (int)($_GET['id'] ?? 0);
        $code = sanitize($_GET['code'] ?? '');
        if ($id > 0) {
            $stmt = $db->prepare('SELECT * FROM coupons WHERE id = ?');
            $stmt->execute([$id]);
        } elseif ($code !== '') {
            $stmt = $db->prepare('SELECT * FROM coupons WHERE code = ?');
            $stmt->execute([$code]);
        } else {
            jsonOut(['success' => false, 'message' => 'id or code required'], 400);
        }
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$coupon) jsonOut(['success' => false, 'message' => 'Coupon not found'], 404);
        jsonOut(['success' => true, 'data' => $coupon]);

    case 'create':
        requireRole('admin');
        validateCsrf();
        $code           = sanitize($_POST['code'] ?? '');
        $type           = sanitize($_POST['type'] ?? 'percent');
        $discount       = (float)($_POST['discount'] ?? 0);
        $min_order      = (float)($_POST['min_order'] ?? 0);
        $usage_limit    = (int)($_POST['usage_limit'] ?? 0);
        $expires_at     = sanitize($_POST['expires_at'] ?? '');
        $description    = sanitize($_POST['description'] ?? '');
        if ($code === '' || $discount <= 0) {
            jsonOut(['success' => false, 'message' => 'code and discount are required'], 400);
        }
        $dup = $db->prepare('SELECT id FROM coupons WHERE code = ?');
        $dup->execute([$code]);
        if ($dup->fetch()) jsonOut(['success' => false, 'message' => 'Coupon code already exists'], 409);
        $stmt = $db->prepare(
            'INSERT INTO coupons (code, type, discount, min_order, usage_limit, expires_at, description, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$code, $type, $discount, $min_order, $usage_limit ?: null, $expires_at ?: null, $description]);
        $newId = $db->lastInsertId();
        jsonOut(['success' => true, 'message' => 'Coupon created', 'id' => $newId], 201);

    case 'update':
        requireRole('admin');
        validateCsrf();
        $id          = (int)($_POST['id'] ?? 0);
        $code        = sanitize($_POST['code'] ?? '');
        $type        = sanitize($_POST['type'] ?? '');
        $discount    = (float)($_POST['discount'] ?? 0);
        $min_order   = (float)($_POST['min_order'] ?? 0);
        $usage_limit = (int)($_POST['usage_limit'] ?? 0);
        $expires_at  = sanitize($_POST['expires_at'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        if ($id <= 0) jsonOut(['success' => false, 'message' => 'Invalid id'], 400);
        $check = $db->prepare('SELECT id FROM coupons WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetch()) jsonOut(['success' => false, 'message' => 'Coupon not found'], 404);
        if ($code !== '') {
            $dup = $db->prepare('SELECT id FROM coupons WHERE code = ? AND id != ?');
            $dup->execute([$code, $id]);
            if ($dup->fetch()) jsonOut(['success' => false, 'message' => 'Coupon code already in use'], 409);
        }
        $stmt = $db->prepare(
            'UPDATE coupons SET code=?, type=?, discount=?, min_order=?, usage_limit=?, expires_at=?, description=?, updated_at=NOW()
             WHERE id=?'
        );
        $stmt->execute([$code, $type, $discount, $min_order, $usage_limit ?: null, $expires_at ?: null, $description, $id]);
        jsonOut(['success' => true, 'message' => 'Coupon updated']);

    case 'delete':
        requireRole('admin');
        validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) jsonOut(['success' => false, 'message' => 'Invalid id'], 400);
        $check = $db->prepare('SELECT id FROM coupons WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetch()) jsonOut(['success' => false, 'message' => 'Coupon not found'], 404);
        $db->prepare('DELETE FROM coupon_usage WHERE coupon_id = ?')->execute([$id]);
        $db->prepare('DELETE FROM coupons WHERE id = ?')->execute([$id]);
        jsonOut(['success' => true, 'message' => 'Coupon deleted']);

    case 'validate':
        $code      = sanitize($_GET['code'] ?? $_POST['code'] ?? '');
        $order_amt = (float)($_GET['order_amount'] ?? $_POST['order_amount'] ?? 0);
        if ($code === '') jsonOut(['success' => false, 'message' => 'code is required'], 400);
        $stmt = $db->prepare('SELECT * FROM coupons WHERE code = ?');
        $stmt->execute([$code]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$coupon) jsonOut(['success' => false, 'message' => 'Coupon not found'], 404);
        if ($coupon['expires_at'] && strtotime($coupon['expires_at']) < time()) {
            jsonOut(['success' => false, 'message' => 'Coupon has expired'], 400);
        }
        if ($coupon['usage_limit'] !== null && (int)$coupon['usage_limit'] > 0) {
            $usageStmt = $db->prepare('SELECT COUNT(*) FROM coupon_usage WHERE coupon_id = ?');
            $usageStmt->execute([$coupon['id']]);
            $usageCount = (int)$usageStmt->fetchColumn();
            if ($usageCount >= (int)$coupon['usage_limit']) {
                jsonOut(['success' => false, 'message' => 'Coupon usage limit reached'], 400);
            }
        }
        if ($coupon['min_order'] > 0 && $order_amt < (float)$coupon['min_order']) {
            jsonOut([
                'success'   => false,
                'message'   => 'Order amount does not meet the minimum requirement of ' . formatMoney($coupon['min_order']),
            ], 400);
        }
        $discount_amount = 0;
        if ($coupon['type'] === 'percent') {
            $discount_amount = round($order_amt * ((float)$coupon['discount'] / 100), 2);
        } else {
            $discount_amount = min((float)$coupon['discount'], $order_amt);
        }
        jsonOut([
            'success'         => true,
            'message'         => 'Coupon is valid',
            'coupon'          => $coupon,
            'discount_amount' => $discount_amount,
        ]);

    case 'redeem':
        requireAuth();
        validateCsrf();
        $user      = getCurrentUser();
        $code      = sanitize($_POST['code'] ?? '');
        $order_id  = (int)($_POST['order_id'] ?? 0);
        $order_amt = (float)($_POST['order_amount'] ?? 0);
        if ($code === '' || $order_id <= 0) {
            jsonOut(['success' => false, 'message' => 'code and order_id are required'], 400);
        }
        $stmt = $db->prepare('SELECT * FROM coupons WHERE code = ?');
        $stmt->execute([$code]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$coupon) jsonOut(['success' => false, 'message' => 'Coupon not found'], 404);
        if ($coupon['expires_at'] && strtotime($coupon['expires_at']) < time()) {
            jsonOut(['success' => false, 'message' => 'Coupon has expired'], 400);
        }
        if ($coupon['usage_limit'] !== null && (int)$coupon['usage_limit'] > 0) {
            $usageStmt = $db->prepare('SELECT COUNT(*) FROM coupon_usage WHERE coupon_id = ?');
            $usageStmt->execute([$coupon['id']]);
            if ((int)$usageStmt->fetchColumn() >= (int)$coupon['usage_limit']) {
                jsonOut(['success' => false, 'message' => 'Coupon usage limit reached'], 400);
            }
        }
        $dupUse = $db->prepare('SELECT id FROM coupon_usage WHERE coupon_id = ? AND user_id = ? AND order_id = ?');
        $dupUse->execute([$coupon['id'], $user['id'], $order_id]);
        if ($dupUse->fetch()) jsonOut(['success' => false, 'message' => 'Coupon already redeemed for this order'], 409);
        $discount_amount = ($coupon['type'] === 'percent')
            ? round($order_amt * ((float)$coupon['discount'] / 100), 2)
            : min((float)$coupon['discount'], $order_amt);
        $insert = $db->prepare(
            'INSERT INTO coupon_usage (coupon_id, user_id, order_id, discount_amount, used_at) VALUES (?, ?, ?, ?, NOW())'
        );
        $insert->execute([$coupon['id'], $user['id'], $order_id, $discount_amount]);
        jsonOut([
            'success'         => true,
            'message'         => 'Coupon redeemed',
            'discount_amount' => $discount_amount,
        ]);

    default:
        jsonOut(['success' => false, 'message' => 'Invalid action'], 400);
}
