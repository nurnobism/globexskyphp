<?php
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../config/stripe.php';
header('Content-Type: application/json');
$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOut($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

switch ($action) {
    case 'list_methods':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $stmt = $db->prepare(
            'SELECT * FROM payment_methods WHERE user_id = ? ORDER BY is_default DESC, created_at DESC'
        );
        $stmt->execute([$userId]);
        jsonOut(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'add_method':
        requireAuth();
        validateCsrf();
        $userId      = $_SESSION['user_id'];
        $type        = sanitize($_POST['type'] ?? '');
        $last4       = sanitize($_POST['last4'] ?? '');
        $brand       = sanitize($_POST['brand'] ?? '');
        $holderName  = sanitize($_POST['holder_name'] ?? '');
        $bankName    = sanitize($_POST['bank_name'] ?? '');
        $accountLast4 = sanitize($_POST['account_last4'] ?? '');
        $validTypes  = ['card', 'bank', 'wallet'];
        if (!in_array($type, $validTypes, true)) {
            jsonOut(['success' => false, 'message' => 'Invalid payment method type'], 400);
        }
        $countStmt = $db->prepare('SELECT COUNT(*) FROM payment_methods WHERE user_id = ?');
        $countStmt->execute([$userId]);
        $isFirst = (int) $countStmt->fetchColumn() === 0;
        $stmt = $db->prepare(
            'INSERT INTO payment_methods (user_id, type, last4, brand, holder_name, bank_name, account_last4, is_default, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $userId, $type, $last4, $brand, $holderName,
            $bankName, $accountLast4, $isFirst ? 1 : 0,
        ]);
        jsonOut(['success' => true, 'id' => $db->lastInsertId()]);
        break;

    case 'remove_method':
        requireAuth();
        validateCsrf();
        $userId = $_SESSION['user_id'];
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            jsonOut(['success' => false, 'message' => 'Payment method ID required'], 400);
        }
        $checkStmt = $db->prepare(
            'SELECT is_default FROM payment_methods WHERE id = ? AND user_id = ?'
        );
        $checkStmt->execute([$id, $userId]);
        $method = $checkStmt->fetch();
        if (!$method) {
            jsonOut(['success' => false, 'message' => 'Payment method not found'], 404);
        }
        if ($method['is_default']) {
            jsonOut(['success' => false, 'message' => 'Cannot remove the default payment method'], 400);
        }
        $stmt = $db->prepare('DELETE FROM payment_methods WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        jsonOut(['success' => true, 'message' => 'Payment method removed']);
        break;

    case 'set_default':
        requireAuth();
        validateCsrf();
        $userId = $_SESSION['user_id'];
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            jsonOut(['success' => false, 'message' => 'Payment method ID required'], 400);
        }
        $checkStmt = $db->prepare('SELECT id FROM payment_methods WHERE id = ? AND user_id = ?');
        $checkStmt->execute([$id, $userId]);
        if (!$checkStmt->fetch()) {
            jsonOut(['success' => false, 'message' => 'Payment method not found'], 404);
        }
        $db->prepare('UPDATE payment_methods SET is_default = 0 WHERE user_id = ?')->execute([$userId]);
        $db->prepare('UPDATE payment_methods SET is_default = 1 WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
        jsonOut(['success' => true, 'message' => 'Default payment method updated']);
        break;

    case 'list_transactions':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $page = (int) get('page', 1);
        $result = paginate(
            $db,
            'SELECT * FROM payment_transactions WHERE user_id = ? ORDER BY created_at DESC',
            [$userId],
            $page
        );
        jsonOut(['success' => true, 'data' => $result]);
        break;

    case 'process_payment':
        requireAuth();
        validateCsrf();
        $userId   = $_SESSION['user_id'];
        $orderId  = (int) ($_POST['order_id'] ?? 0);
        $amount   = sanitize($_POST['amount'] ?? '');
        $currency = sanitize($_POST['currency'] ?? 'USD');
        $methodId = (int) ($_POST['method_id'] ?? 0);
        if (!$orderId || !$amount || !$methodId) {
            jsonOut(['success' => false, 'message' => 'order_id, amount, and method_id are required'], 400);
        }
        if (!is_numeric($amount) || $amount <= 0) {
            jsonOut(['success' => false, 'message' => 'Invalid amount'], 400);
        }
        $methodStmt = $db->prepare('SELECT id FROM payment_methods WHERE id = ? AND user_id = ?');
        $methodStmt->execute([$methodId, $userId]);
        if (!$methodStmt->fetch()) {
            jsonOut(['success' => false, 'message' => 'Payment method not found'], 404);
        }
        $stmt = $db->prepare(
            'INSERT INTO payment_transactions (user_id, order_id, amount, currency, method_id, status, created_at)
             VALUES (?, ?, ?, ?, ?, \'completed\', NOW())'
        );
        $stmt->execute([$userId, $orderId, $amount, $currency, $methodId]);
        jsonOut(['success' => true, 'id' => $db->lastInsertId(), 'status' => 'completed']);
        break;

    case 'create_intent':
        if (!isLoggedIn()) jsonOut(['error' => 'Login required'], 401);
        if (!verifyCsrf()) jsonOut(['error' => 'Invalid CSRF token'], 403);
        $orderId = (int)($_POST['order_id'] ?? 0);
        if (!$orderId) jsonOut(['error' => 'Order ID required'], 400);
        $stmt = $db->prepare('SELECT * FROM orders WHERE id = ? AND buyer_id = ?');
        $stmt->execute([$orderId, $_SESSION['user_id']]);
        $order = $stmt->fetch();
        if (!$order) jsonOut(['error' => 'Order not found'], 404);
        if (!class_exists('\Stripe\Stripe')) jsonOut(['error' => 'Stripe is not configured'], 503);
        try {
            \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
            $intent = \Stripe\PaymentIntent::create([
                'amount'   => (int)round($order['total'] * 100),
                'currency' => strtolower($order['currency'] ?? 'usd'),
                'metadata' => ['order_id' => $orderId, 'order_number' => $order['order_number']],
            ]);
            $db->prepare('UPDATE orders SET stripe_payment_intent_id = ? WHERE id = ?')->execute([$intent->id, $orderId]);
            jsonOut(['success' => true, 'client_secret' => $intent->client_secret, 'intent_id' => $intent->id]);
        } catch (\Exception $e) {
            jsonOut(['error' => $e->getMessage()], 500);
        }
        break;

    case 'confirm':
        if (!isLoggedIn()) jsonOut(['error' => 'Login required'], 401);
        if (!verifyCsrf()) jsonOut(['error' => 'Invalid CSRF token'], 403);
        $orderId  = (int)($_POST['order_id'] ?? 0);
        $intentId = trim($_POST['intent_id'] ?? '');
        if (!$orderId || !$intentId) jsonOut(['error' => 'Order ID and intent ID required'], 400);
        $stmt = $db->prepare('SELECT id FROM orders WHERE id = ? AND buyer_id = ? AND stripe_payment_intent_id = ?');
        $stmt->execute([$orderId, $_SESSION['user_id'], $intentId]);
        if (!$stmt->fetch()) jsonOut(['error' => 'Order not found'], 404);
        $db->prepare('UPDATE orders SET payment_status = "paid", status = "confirmed" WHERE id = ?')->execute([$orderId]);
        jsonOut(['success' => true, 'order_id' => $orderId]);
        break;

    case 'webhook':
        $payload   = file_get_contents('php://input');
        $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        if (!class_exists('\Stripe\Stripe')) { http_response_code(200); echo json_encode(['received' => true]); exit; }
        try {
            \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, STRIPE_WEBHOOK_SECRET);
        } catch (\Exception $e) {
            http_response_code(400); echo json_encode(['error' => $e->getMessage()]); exit;
        }
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $pi = $event->data->object;
                $oid = (int)($pi->metadata->order_id ?? 0);
                if ($oid) $db->prepare('UPDATE orders SET payment_status = "paid", status = "confirmed" WHERE id = ? AND payment_status != "paid"')->execute([$oid]);
                break;
            case 'payment_intent.payment_failed':
                $pi = $event->data->object;
                $oid = (int)($pi->metadata->order_id ?? 0);
                if ($oid) $db->prepare('UPDATE orders SET payment_status = "failed" WHERE id = ?')->execute([$oid]);
                break;
        }
        http_response_code(200); echo json_encode(['received' => true]); exit;

    case 'refund':
        if (!isLoggedIn()) jsonOut(['error' => 'Login required'], 401);
        if (!isAdmin())    jsonOut(['error' => 'Forbidden'], 403);
        if (!verifyCsrf()) jsonOut(['error' => 'Invalid CSRF token'], 403);
        $orderId = (int)($_POST['order_id'] ?? 0);
        if (!$orderId) jsonOut(['error' => 'Order ID required'], 400);
        $stmt = $db->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) jsonOut(['error' => 'Order not found'], 404);
        if (empty($order['stripe_payment_intent_id'])) jsonOut(['error' => 'No Stripe payment found'], 400);
        if (!class_exists('\Stripe\Stripe')) jsonOut(['error' => 'Stripe is not configured'], 503);
        try {
            \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
            $refund = \Stripe\Refund::create(['payment_intent' => $order['stripe_payment_intent_id']]);
            $db->prepare('UPDATE orders SET payment_status = "refunded", status = "refunded" WHERE id = ?')->execute([$orderId]);
            jsonOut(['success' => true, 'refund_id' => $refund->id]);
        } catch (\Exception $e) {
            jsonOut(['error' => $e->getMessage()], 500);
        }
        break;

    default:
        jsonOut(['success' => false, 'message' => 'Invalid action'], 400);
}
