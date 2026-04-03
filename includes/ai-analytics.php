<?php
/**
 * includes/ai-analytics.php — AI Business Analytics Helpers (Phase 8)
 */

require_once __DIR__ . '/deepseek.php';

/**
 * Analyze sales trends with AI-generated insights and predictions.
 */
function analyzeSalesTrends(int $supplierId, string $period = '30days'): array {
    $db = getDB();
    $interval = match ($period) {
        '7days'  => 'INTERVAL 7 DAY',
        '90days' => 'INTERVAL 90 DAY',
        default  => 'INTERVAL 30 DAY',
    };

    $data = [];
    try {
        $stmt = $db->prepare(
            "SELECT DATE(placed_at) AS date, COUNT(*) AS orders, SUM(total_amount) AS revenue
             FROM orders WHERE supplier_id = ? AND placed_at >= DATE_SUB(NOW(), $interval)
             GROUP BY DATE(placed_at) ORDER BY date ASC"
        );
        $stmt->execute([$supplierId]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { /* ignore */ }

    $ai       = getDeepSeek();
    $insights = $ai->analyzeSalesData($supplierId, $period);

    // Build simple linear prediction for next 7 days
    $prediction = [];
    if (count($data) >= 3) {
        $revenues = array_column($data, 'revenue');
        $avgRev   = array_sum($revenues) / count($revenues);
        for ($i = 1; $i <= 7; $i++) {
            $prediction[] = [
                'date'    => date('Y-m-d', strtotime("+$i days")),
                'revenue' => round($avgRev * (1 + (($i - 1) * 0.01)), 2),
            ];
        }
    }

    return [
        'historical'  => $data,
        'prediction'  => $prediction,
        'ai_insights' => $insights,
        'period'      => $period,
    ];
}

/**
 * Analyze customer behavior segments.
 */
function analyzeCustomerBehavior(string $segment = 'all'): array {
    $db = getDB();
    $data = [];

    try {
        $stmt = $db->query(
            "SELECT
                SUM(CASE WHEN placed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS active_30d,
                SUM(CASE WHEN placed_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                          AND placed_at < DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS active_90d,
                COUNT(DISTINCT buyer_id) AS total_buyers,
                AVG(total_amount) AS avg_order_value
             FROM orders WHERE status != 'cancelled'"
        );
        $data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) { /* ignore */ }

    $ai       = getDeepSeek();
    $insights = $ai->chat(
        "Analyze customer segments from this data: " . json_encode($data) . "\nSegment: $segment\n"
        . "Return JSON: {segments:[{name:string,size:int,characteristics:[string],value:string}], insights:[string]}",
        'You are a customer analytics specialist. Return valid JSON only.'
    );

    $result = json_decode($insights, true);
    return is_array($result) ? array_merge($result, ['raw_data' => $data]) : ['segments' => [], 'insights' => [], 'raw_data' => $data];
}

/**
 * Predict product demand for a given period.
 */
function predictDemand(int $productId, string $period = '30days'): array {
    $db = getDB();
    $data = [];

    try {
        $stmt = $db->prepare(
            "SELECT DATE(o.placed_at) AS date, SUM(oi.quantity) AS quantity
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE oi.product_id = ? AND o.placed_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
               AND o.status != 'cancelled'
             GROUP BY DATE(o.placed_at) ORDER BY date ASC"
        );
        $stmt->execute([$productId]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { /* ignore */ }

    $ai       = getDeepSeek();
    $response = $ai->chat(
        "Predict demand for a product based on historical sales: " . json_encode($data)
        . "\nPredict for next $period. Return JSON: {predicted_units:int, confidence:\"low|medium|high\", trend:string, factors:[string]}",
        'You are a demand forecasting AI. Return valid JSON only.'
    );

    $result = json_decode($response, true);
    return is_array($result) ? array_merge($result, ['historical' => $data]) : ['predicted_units' => 0, 'confidence' => 'low', 'trend' => 'stable', 'factors' => [], 'historical' => $data];
}

/**
 * Analyze competitor pricing for a product.
 */
function analyzeCompetitorPricing(int $productId): array {
    $db = getDB();
    $data = [];

    try {
        $stmt = $db->prepare(
            "SELECT p.price, p.name, c.name AS category FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.id = ?"
        );
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            // Get similar products in same category
            $compStmt = $db->prepare(
                "SELECT p.price, p.name, p.rating FROM products p
                 LEFT JOIN categories c ON c.id = p.category_id
                 WHERE c.name = ? AND p.id != ? AND p.status = 'active'
                 ORDER BY RAND() LIMIT 10"
            );
            $compStmt->execute([$product['category'] ?? '', $productId]);
            $competitors = $compStmt->fetchAll(PDO::FETCH_ASSOC);
            $data = ['product' => $product, 'competitors' => $competitors];
        }
    } catch (PDOException $e) { /* ignore */ }

    $ai       = getDeepSeek();
    $response = $ai->chat(
        "Analyze pricing competitiveness: " . json_encode($data)
        . "\nReturn JSON: {position:\"underpriced|competitive|overpriced\", recommendation:string, suggested_price:number, reasoning:string}",
        'You are a pricing strategy analyst. Return valid JSON only.'
    );

    $result = json_decode($response, true);
    return is_array($result) ? $result : ['position' => 'competitive', 'recommendation' => '', 'suggested_price' => 0, 'reasoning' => ''];
}

/**
 * Generate role-specific business insights.
 */
function generateBusinessInsights(int $userId, string $role = 'buyer'): array {
    $db = getDB();
    $data = ['user_id' => $userId, 'role' => $role];

    try {
        if ($role === 'supplier') {
            $stmt = $db->prepare(
                "SELECT COUNT(*) AS total_orders, SUM(total_amount) AS total_revenue,
                        AVG(total_amount) AS avg_order
                 FROM orders WHERE supplier_id = (SELECT id FROM suppliers WHERE user_id = ?)
                 AND placed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );
            $stmt->execute([$userId]);
            $data['metrics'] = $stmt->fetch(PDO::FETCH_ASSOC);
        } elseif ($role === 'buyer') {
            $stmt = $db->prepare(
                "SELECT COUNT(*) AS total_orders, SUM(total_amount) AS total_spent
                 FROM orders WHERE buyer_id = ? AND placed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );
            $stmt->execute([$userId]);
            $data['metrics'] = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) { /* ignore */ }

    $ai       = getDeepSeek();
    $response = $ai->chat(
        "Generate business insights for a $role user: " . json_encode($data)
        . "\nReturn JSON: {insights:[{title:string,description:string,action:string,priority:\"low|medium|high\"}]}",
        'You are a business intelligence analyst. Return valid JSON only.'
    );

    $result = json_decode($response, true);
    return is_array($result) ? $result : ['insights' => []];
}

/**
 * Analyze conversion funnel.
 */
function analyzeConversionFunnel(string $period = '30days'): array {
    $db = getDB();
    $interval = match ($period) {
        '7days'  => 'INTERVAL 7 DAY',
        '90days' => 'INTERVAL 90 DAY',
        default  => 'INTERVAL 30 DAY',
    };

    $data = [];
    try {
        $views = (int)$db->query("SELECT COUNT(*) FROM admin_logs WHERE action='product_view' AND created_at >= DATE_SUB(NOW(), $interval)")->fetchColumn();
        $carts = (int)$db->query("SELECT COUNT(*) FROM admin_logs WHERE action='add_to_cart' AND created_at >= DATE_SUB(NOW(), $interval)")->fetchColumn();
        $orders = (int)$db->query("SELECT COUNT(*) FROM orders WHERE placed_at >= DATE_SUB(NOW(), $interval)")->fetchColumn();

        $data = [
            'product_views' => $views,
            'cart_adds'     => $carts,
            'orders'        => $orders,
            'view_to_cart'  => $views > 0 ? round(($carts / $views) * 100, 1) : 0,
            'cart_to_order' => $carts > 0 ? round(($orders / $carts) * 100, 1) : 0,
        ];
    } catch (PDOException $e) { /* ignore */ }

    return $data;
}

/**
 * Get AI system health metrics.
 */
function getAIHealthMetrics(): array {
    $db = getDB();
    try {
        $today = $db->query(
            "SELECT COUNT(*) AS total_calls,
                    SUM(CASE WHEN status='error' THEN 1 ELSE 0 END) AS errors,
                    AVG(response_time_ms) AS avg_response_ms,
                    SUM(total_tokens) AS total_tokens,
                    SUM(cost_usd) AS total_cost
             FROM ai_usage WHERE DATE(created_at) = CURDATE()"
        )->fetch(PDO::FETCH_ASSOC);

        $featureBreakdown = $db->query(
            "SELECT feature, COUNT(*) AS calls, SUM(total_tokens) AS tokens
             FROM ai_usage WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY feature ORDER BY calls DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $errorRate = ($today['total_calls'] ?? 0) > 0
            ? round((($today['errors'] ?? 0) / $today['total_calls']) * 100, 1)
            : 0;

        return [
            'today'             => $today,
            'error_rate'        => $errorRate,
            'feature_breakdown' => $featureBreakdown,
            'status'            => $errorRate < 5 ? 'healthy' : ($errorRate < 20 ? 'degraded' : 'critical'),
        ];
    } catch (PDOException $e) {
        return ['today' => [], 'error_rate' => 0, 'feature_breakdown' => [], 'status' => 'unknown'];
    }
}
