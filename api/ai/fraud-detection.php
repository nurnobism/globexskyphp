<?php
/**
 * api/ai/fraud-detection.php — AI Fraud Detection
 * POST ?action=analyze   — analyze a single order
 * GET  ?action=list       — list flagged orders (admin)
 * POST ?action=update     — update flag status (admin)
 */

require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/deepseek.php';

header('Content-Type: application/json');

$action = get('action', 'list');
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

// Ensure fraud flags table exists
$db->exec("CREATE TABLE IF NOT EXISTS fraud_flags (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id        INT UNSIGNED NOT NULL,
    risk_score      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    risk_level      ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low',
    reasons         JSON         NULL,
    recommended_action VARCHAR(255) NULL,
    ai_raw_response TEXT         NULL,
    status          ENUM('open','reviewed','dismissed','blocked') NOT NULL DEFAULT 'open',
    reviewed_by     INT UNSIGNED NULL,
    reviewed_at     DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order  (order_id),
    INDEX idx_level  (risk_level),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// -----------------------------------------------------------------------
// ACTION: analyze
// -----------------------------------------------------------------------
if ($action === 'analyze' && $method === 'POST') {
    requireAdmin();

    if (!verifyCsrf()) {
        jsonResponse(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
    }

    $input   = json_decode(file_get_contents('php://input'), true) ?: [];
    $orderId = (int)($input['order_id'] ?? post('order_id', 0));

    if ($orderId <= 0) {
        jsonResponse(['success' => false, 'message' => 'order_id is required.'], 400);
    }

    // Load order with related data
    $stmt = $db->prepare(
        "SELECT o.*,
                u.email         AS buyer_email,
                u.first_name    AS buyer_first_name,
                u.last_name     AS buyer_last_name,
                u.created_at    AS buyer_registered,
                COUNT(o2.id)    AS buyer_total_orders,
                a.country, a.city, a.phone
         FROM orders o
         JOIN users   u  ON u.id  = o.buyer_id
         LEFT JOIN addresses a ON a.id = o.shipping_address_id
         LEFT JOIN orders    o2 ON o2.buyer_id = o.buyer_id AND o2.id != o.id
         WHERE o.id = ?
         GROUP BY o.id"
    );
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        jsonResponse(['success' => false, 'message' => 'Order not found.'], 404);
    }

    // Load order items
    $itemsStmt = $db->prepare(
        "SELECT oi.quantity, oi.unit_price, oi.total_price, p.name AS product_name
         FROM order_items oi
         JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id = ?"
    );
    $itemsStmt->execute([$orderId]);
    $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare transaction data for AI
    $transactionData = [
        'order_id'            => $order['id'],
        'total'               => $order['total'],
        'payment_method'      => $order['payment_method'] ?? 'unknown',
        'payment_status'      => $order['payment_status'],
        'order_status'        => $order['status'],
        'placed_at'           => $order['placed_at'],
        'buyer_registered'    => $order['buyer_registered'],
        'buyer_total_orders'  => (int)$order['buyer_total_orders'],
        'buyer_country'       => $order['country'],
        'shipping_country'    => $order['country'],
        'item_count'          => count($order['items']),
        'items'               => $order['items'],
    ];

    // Heuristic pre-check before calling AI
    $heuristicFlags = [];
    if ((float)$order['total'] > 10000) {
        $heuristicFlags[] = 'High-value order (>$10,000)';
    }
    if ((int)$order['buyer_total_orders'] === 0) {
        $heuristicFlags[] = 'First-time buyer';
    }
    if ($order['payment_status'] === 'pending' && (float)$order['total'] > 5000) {
        $heuristicFlags[] = 'Large unpaid order';
    }

    // AI fraud analysis
    $riskScore  = 0;
    $riskLevel  = 'low';
    $reasons    = $heuristicFlags;
    $recommended = 'No action required.';
    $rawResponse = '';

    try {
        $deepseek = getDeepSeek();
        $txStr    = json_encode($transactionData);

        $rawResponse = $deepseek->chat(
            "Analyze this e-commerce transaction for fraud risk:\n$txStr\n\n"
            . "Return ONLY valid JSON with these fields: "
            . '"risk_score" (0-100 integer), "risk_level" ("low","medium","high","critical"), '
            . '"reasons" (array of strings explaining risk factors), '
            . '"recommended_action" (string: one clear action). No markdown.',
            'You are an expert fraud detection AI for a B2B marketplace. '
            . 'Analyze transaction patterns, buyer history, and order characteristics. '
            . 'Return structured JSON only.',
            ['max_tokens' => 400, 'temperature' => 0.1]
        );

        $decoded = json_decode($rawResponse, true);
        if (is_array($decoded)) {
            $riskScore   = max(0, min(100, (int)($decoded['risk_score'] ?? 0)));
            $riskLevel   = in_array($decoded['risk_level'] ?? '', ['low','medium','high','critical'])
                           ? $decoded['risk_level']
                           : 'low';
            $reasons     = array_merge($reasons, (array)($decoded['reasons'] ?? []));
            $recommended = $decoded['recommended_action'] ?? 'Monitor order.';
        }
    } catch (Throwable $e) {
        error_log('AI fraud detection error: ' . $e->getMessage());
        // Fall back to heuristic score
        $riskScore = count($heuristicFlags) * 20;
        $riskLevel = $riskScore >= 60 ? 'high' : ($riskScore >= 30 ? 'medium' : 'low');
    }

    // Upsert fraud flag record
    $existing = $db->prepare('SELECT id FROM fraud_flags WHERE order_id = ?');
    $existing->execute([$orderId]);
    $flagId = $existing->fetchColumn();

    $reasonsJson = json_encode(array_values(array_unique($reasons)));

    if ($flagId) {
        $db->prepare(
            "UPDATE fraud_flags
             SET risk_score=?, risk_level=?, reasons=?, recommended_action=?, ai_raw_response=?, updated_at=NOW()
             WHERE id=?"
        )->execute([$riskScore, $riskLevel, $reasonsJson, $recommended, $rawResponse, $flagId]);
    } else {
        $db->prepare(
            "INSERT INTO fraud_flags (order_id, risk_score, risk_level, reasons, recommended_action, ai_raw_response, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        )->execute([$orderId, $riskScore, $riskLevel, $reasonsJson, $recommended, $rawResponse]);
    }

    jsonResponse([
        'success'            => true,
        'order_id'           => $orderId,
        'risk_score'         => $riskScore,
        'risk_level'         => $riskLevel,
        'reasons'            => array_values(array_unique($reasons)),
        'recommended_action' => $recommended,
    ]);
}

// -----------------------------------------------------------------------
// ACTION: list
// -----------------------------------------------------------------------
elseif ($action === 'list') {
    requireAdmin();

    $status = get('status', '');
    $level  = get('level', '');
    $page   = max(1, (int)get('page', 1));
    $limit  = 20;

    $where  = [];
    $params = [];

    if ($status !== '') {
        $where[]  = 'ff.status = ?';
        $params[] = $status;
    }
    if (in_array($level, ['low','medium','high','critical'])) {
        $where[]  = 'ff.risk_level = ?';
        $params[] = $level;
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $offset   = ($page - 1) * $limit;

    $countStmt = $db->prepare("SELECT COUNT(*) FROM fraud_flags ff $whereSql");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare(
        "SELECT ff.*, o.total AS order_total, o.placed_at, o.payment_method,
                u.email AS buyer_email, u.first_name, u.last_name
         FROM fraud_flags ff
         JOIN orders o ON o.id = ff.order_id
         JOIN users  u ON u.id = o.buyer_id
         $whereSql
         ORDER BY ff.risk_score DESC, ff.created_at DESC
         LIMIT $limit OFFSET $offset"
    );
    $stmt->execute($params);
    $flags = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($flags as &$flag) {
        $flag['reasons'] = json_decode($flag['reasons'] ?? '[]', true);
    }

    jsonResponse([
        'success' => true,
        'flags'   => $flags,
        'total'   => $total,
        'page'    => $page,
        'pages'   => max(1, (int)ceil($total / $limit)),
    ]);
}

// -----------------------------------------------------------------------
// ACTION: update
// -----------------------------------------------------------------------
elseif ($action === 'update' && $method === 'POST') {
    requireAdmin();

    if (!verifyCsrf()) {
        jsonResponse(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
    }

    $input   = json_decode(file_get_contents('php://input'), true) ?: [];
    $flagId  = (int)($input['flag_id'] ?? post('flag_id', 0));
    $status  = $input['status'] ?? post('status', '');

    if ($flagId <= 0 || !in_array($status, ['open','reviewed','dismissed','blocked'])) {
        jsonResponse(['success' => false, 'message' => 'Invalid parameters.'], 400);
    }

    $db->prepare(
        "UPDATE fraud_flags
         SET status=?, reviewed_by=?, reviewed_at=NOW()
         WHERE id=?"
    )->execute([$status, $_SESSION['user_id'], $flagId]);

    jsonResponse(['success' => true, 'flag_id' => $flagId, 'status' => $status]);
}

else {
    jsonResponse(['success' => false, 'message' => 'Unknown action.'], 400);
}
