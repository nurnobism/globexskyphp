<?php
/**
 * includes/ai-fraud.php — AI Fraud Detection Helpers (Phase 8)
 */

require_once __DIR__ . '/deepseek.php';

/**
 * Run comprehensive fraud analysis on an order.
 */
function analyzeOrder(int $orderId): array {
    $db = getDB();
    $data = ['order_id' => $orderId];

    try {
        $stmt = $db->prepare(
            "SELECT o.*, u.email, u.created_at AS user_created, u.status AS user_status
             FROM orders o LEFT JOIN users u ON u.id = o.buyer_id WHERE o.id = ?"
        );
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($order) {
            $data = array_merge($data, $order);
        }
    } catch (PDOException $e) { /* ignore */ }

    $ai = getDeepSeek();
    return $ai->detectFraud('order', $orderId, $data);
}

/**
 * Analyze user behavior for fraud signals.
 */
function analyzeUser(int $userId): array {
    $db = getDB();
    $data = ['user_id' => $userId];

    try {
        $stmt = $db->prepare("SELECT id, email, created_at, status, role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $data = array_merge($data, $user);
        }

        // Order count and history
        $ordStmt = $db->prepare(
            "SELECT COUNT(*) AS order_count, SUM(total_amount) AS total_spent,
                    SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled_count
             FROM orders WHERE buyer_id = ? AND placed_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
        $ordStmt->execute([$userId]);
        $orderStats = $ordStmt->fetch(PDO::FETCH_ASSOC);
        if ($orderStats) {
            $data['order_stats'] = $orderStats;
        }
    } catch (PDOException $e) { /* ignore */ }

    $ai = getDeepSeek();
    return $ai->detectFraud('user', $userId, $data);
}

/**
 * Analyze a transaction for payment fraud.
 */
function analyzeTransaction(int $transactionId): array {
    $db = getDB();
    $data = ['transaction_id' => $transactionId];

    try {
        $stmt = $db->prepare("SELECT * FROM payments WHERE id = ?");
        $stmt->execute([$transactionId]);
        $tx = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($tx) {
            $data = array_merge($data, $tx);
        }
    } catch (PDOException $e) { /* ignore */ }

    $ai = getDeepSeek();
    return $ai->detectFraud('transaction', $transactionId, $data);
}

/**
 * Detect fake or spam reviews.
 */
function analyzeReview(int $reviewId): array {
    $db = getDB();
    $data = ['review_id' => $reviewId];

    try {
        $stmt = $db->prepare("SELECT r.*, u.email, u.created_at AS user_created FROM reviews r LEFT JOIN users u ON u.id = r.user_id WHERE r.id = ?");
        $stmt->execute([$reviewId]);
        $review = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($review) {
            $data = array_merge($data, $review);
        }
    } catch (PDOException $e) { /* ignore */ }

    $ai = getDeepSeek();
    return $ai->detectFraud('review', $reviewId, $data);
}

/**
 * Detect fraudulent product listings.
 */
function analyzeListing(int $productId): array {
    $db = getDB();
    $data = ['product_id' => $productId];

    try {
        $stmt = $db->prepare("SELECT p.*, s.company_name, s.created_at AS supplier_created FROM products p LEFT JOIN suppliers s ON s.id = p.supplier_id WHERE p.id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($product) {
            $data = array_merge($data, $product);
        }
    } catch (PDOException $e) { /* ignore */ }

    $ai = getDeepSeek();
    return $ai->detectFraud('listing', $productId, $data);
}

/**
 * Get fraud detection audit log.
 */
function getAuditLog(array $filters = [], int $page = 1, int $perPage = 20): array {
    $db = getDB();
    $where = ['1=1'];
    $params = [];

    if (!empty($filters['entity_type'])) {
        $where[] = 'entity_type = ?';
        $params[] = $filters['entity_type'];
    }
    if (!empty($filters['risk_level'])) {
        $where[] = 'risk_level = ?';
        $params[] = $filters['risk_level'];
    }
    if (!empty($filters['action_taken'])) {
        $where[] = 'action_taken = ?';
        $params[] = $filters['action_taken'];
    }
    if (!empty($filters['date_from'])) {
        $where[] = 'created_at >= ?';
        $params[] = $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $where[] = 'created_at <= ?';
        $params[] = $filters['date_to'];
    }

    $offset = ($page - 1) * $perPage;
    $sql    = 'SELECT * FROM ai_fraud_logs WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
    $params[] = $perPage;
    $params[] = $offset;

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countStmt = $db->prepare('SELECT COUNT(*) FROM ai_fraud_logs WHERE ' . implode(' AND ', $where));
        $countStmt->execute(array_slice($params, 0, -2));
        $total = (int)$countStmt->fetchColumn();

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    } catch (PDOException $e) {
        return ['data' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
    }
}

/**
 * Mark a fraud log entry as a false positive.
 */
function markFalsePositive(int $fraudLogId, int $adminId): bool {
    $db = getDB();
    try {
        $db->prepare(
            "UPDATE ai_fraud_logs SET is_false_positive = 1, reviewed_by = ?, reviewed_at = NOW(), action_taken = 'none' WHERE id = ?"
        )->execute([$adminId, $fraudLogId]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get fraud risk dashboard statistics.
 */
function getRiskDashboardStats(): array {
    $db = getDB();
    try {
        $stats = [];

        $stmt = $db->query(
            "SELECT risk_level, COUNT(*) AS cnt FROM ai_fraud_logs
             WHERE DATE(created_at) = CURDATE() GROUP BY risk_level"
        );
        $byLevel = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $byLevel[$row['risk_level']] = (int)$row['cnt'];
        }
        $stats['today'] = [
            'critical' => $byLevel['critical'] ?? 0,
            'high'     => $byLevel['high']     ?? 0,
            'medium'   => $byLevel['medium']   ?? 0,
            'low'      => $byLevel['low']       ?? 0,
        ];

        $fp = $db->query("SELECT COUNT(*) FROM ai_fraud_logs WHERE is_false_positive = 1")->fetchColumn();
        $total = $db->query("SELECT COUNT(*) FROM ai_fraud_logs WHERE is_false_positive IS NOT NULL")->fetchColumn();
        $stats['false_positive_rate'] = $total > 0 ? round(($fp / $total) * 100, 1) : 0;

        $trendStmt = $db->query(
            "SELECT DATE(created_at) AS date, COUNT(*) AS alerts
             FROM ai_fraud_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(created_at) ORDER BY date ASC"
        );
        $stats['trend'] = $trendStmt->fetchAll(PDO::FETCH_ASSOC);

        return $stats;
    } catch (PDOException $e) {
        return ['today' => [], 'false_positive_rate' => 0, 'trend' => []];
    }
}

/**
 * Get quick risk score for an entity.
 */
function getRiskScore(string $entityType, int $entityId): ?array {
    $db = getDB();
    try {
        $stmt = $db->prepare(
            "SELECT risk_score, risk_level, action_taken, created_at FROM ai_fraud_logs
             WHERE entity_type = ? AND entity_id = ? ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute([$entityType, $entityId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        return null;
    }
}
