<?php
/**
 * api/ai/insights.php — AI Business Insights (Admin)
 * GET — returns aggregated stats + DeepSeek commentary
 */

require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/deepseek.php';

header('Content-Type: application/json');
requireAdmin();

$db   = getDB();
$days = max(7, min(365, (int)get('days', 30)));

// ------------------------------------------------------------------
// Gather aggregated stats
// ------------------------------------------------------------------

// Revenue by period
$revStmt = $db->prepare(
    "SELECT
         DATE(placed_at) AS day,
         COUNT(*)        AS order_count,
         SUM(total)      AS revenue
     FROM orders
     WHERE placed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
       AND payment_status = 'paid'
     GROUP BY DATE(placed_at)
     ORDER BY day ASC"
);
$revStmt->execute([$days]);
$dailyRevenue = $revStmt->fetchAll(PDO::FETCH_ASSOC);

// Previous period for comparison
$prevRevStmt = $db->prepare(
    "SELECT COALESCE(SUM(total), 0) AS revenue, COUNT(*) AS orders
     FROM orders
     WHERE placed_at BETWEEN DATE_SUB(NOW(), INTERVAL ? DAY) AND DATE_SUB(NOW(), INTERVAL ? DAY)
       AND payment_status = 'paid'"
);
$prevRevStmt->execute([$days * 2, $days]);
$prevPeriod = $prevRevStmt->fetch(PDO::FETCH_ASSOC);

$currRevStmt = $db->prepare(
    "SELECT COALESCE(SUM(total), 0) AS revenue, COUNT(*) AS orders
     FROM orders
     WHERE placed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
       AND payment_status = 'paid'"
);
$currRevStmt->execute([$days]);
$currPeriod = $currRevStmt->fetch(PDO::FETCH_ASSOC);

// Top products
$topProducts = $db->prepare(
    "SELECT p.name, SUM(oi.quantity) AS units_sold, SUM(oi.total_price) AS revenue
     FROM order_items oi
     JOIN orders  o ON o.id  = oi.order_id
     JOIN products p ON p.id = oi.product_id
     WHERE o.placed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
       AND o.payment_status = 'paid'
     GROUP BY p.id
     ORDER BY revenue DESC
     LIMIT 10"
);
$topProducts->execute([$days]);
$topProductsData = $topProducts->fetchAll(PDO::FETCH_ASSOC);

// Top categories
$topCats = $db->prepare(
    "SELECT c.name AS category, COUNT(DISTINCT o.id) AS orders, SUM(oi.total_price) AS revenue
     FROM order_items oi
     JOIN orders    o ON o.id  = oi.order_id
     JOIN products  p ON p.id  = oi.product_id
     JOIN categories c ON c.id = p.category_id
     WHERE o.placed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
       AND o.payment_status = 'paid'
     GROUP BY c.id
     ORDER BY revenue DESC
     LIMIT 5"
);
$topCats->execute([$days]);
$topCategoriesData = $topCats->fetchAll(PDO::FETCH_ASSOC);

// New users trend
$newUsers = $db->prepare(
    "SELECT DATE(created_at) AS day, COUNT(*) AS count
     FROM users
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
     GROUP BY DATE(created_at)
     ORDER BY day ASC"
);
$newUsers->execute([$days]);
$newUsersData = $newUsers->fetchAll(PDO::FETCH_ASSOC);

// Conversion (orders / new users)
$totalNewUsers  = array_sum(array_column($newUsersData, 'count')) ?: 1;
$totalOrders    = (int)$currPeriod['orders'];
$conversionRate = round($totalOrders / $totalNewUsers * 100, 2);

// Cart abandonment proxy
$abandonedCarts = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM cart_items")->fetchColumn();

// Summary payload for AI
$summaryData = [
    'period_days'        => $days,
    'current_revenue'    => round((float)$currPeriod['revenue'], 2),
    'previous_revenue'   => round((float)$prevPeriod['revenue'], 2),
    'revenue_change_pct' => $prevPeriod['revenue'] > 0
                            ? round(((float)$currPeriod['revenue'] - (float)$prevPeriod['revenue']) / (float)$prevPeriod['revenue'] * 100, 1)
                            : 0,
    'current_orders'     => $totalOrders,
    'previous_orders'    => (int)$prevPeriod['orders'],
    'new_users'          => $totalNewUsers,
    'conversion_rate'    => $conversionRate,
    'abandoned_carts'    => $abandonedCarts,
    'top_products'       => $topProductsData,
    'top_categories'     => $topCategoriesData,
    'daily_revenue'      => $dailyRevenue,
];

// ------------------------------------------------------------------
// AI analysis
// ------------------------------------------------------------------
$salesTrend          = 'Stable';
$revenuePrediction   = 'N/A';
$recommendations     = [];
$growthSuggestions   = [];
$rawAiInsight        = '';

try {
    $deepseek = getDeepSeek();
    $dataStr  = json_encode($summaryData);

    $rawAiInsight = $deepseek->chat(
        "Analyse this marketplace data for the last {$days} days:\n$dataStr\n\n"
        . "Return ONLY valid JSON with these fields:\n"
        . '"sales_trend" (string: up/down/stable with brief reason),\n'
        . '"revenue_prediction" (string: predicted revenue for next period),\n'
        . '"recommendations" (array of 3-5 actionable strings),\n'
        . '"growth_suggestions" (array of 3-5 strategic growth ideas).\n'
        . 'No markdown, no explanation outside the JSON.',
        'You are a senior business intelligence analyst specialising in B2B e-commerce marketplaces. '
        . 'Provide data-driven insights and practical recommendations.',
        ['max_tokens' => 600, 'temperature' => 0.3]
    );

    $decoded = json_decode($rawAiInsight, true);
    if (is_array($decoded)) {
        $salesTrend        = $decoded['sales_trend']        ?? $salesTrend;
        $revenuePrediction = $decoded['revenue_prediction']  ?? $revenuePrediction;
        $recommendations   = (array)($decoded['recommendations']   ?? []);
        $growthSuggestions = (array)($decoded['growth_suggestions'] ?? []);
    }
} catch (Throwable $e) {
    error_log('AI insights error: ' . $e->getMessage());
    // Provide basic heuristic insights
    $change = $summaryData['revenue_change_pct'];
    $salesTrend      = $change > 0 ? "Up {$change}% vs previous period" : ($change < 0 ? "Down " . abs($change) . "% vs previous period" : 'Stable');
    $recommendations = ['Review top-performing products for restocking', 'Follow up on abandoned carts', 'Consider promotions in low-performing categories'];
    $growthSuggestions = ['Expand supplier network', 'Launch loyalty programme', 'Improve mobile checkout experience'];
}

jsonResponse([
    'success'    => true,
    'period_days' => $days,
    'summary'    => $summaryData,
    'insights'   => [
        'sales_trend'        => $salesTrend,
        'revenue_prediction' => $revenuePrediction,
        'recommendations'    => $recommendations,
        'growth_suggestions' => $growthSuggestions,
    ],
]);
