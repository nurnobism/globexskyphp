<?php
/**
 * api/ai/analytics.php — AI Analytics Commentary
 * GET ?period=7|30|90|365
 */

require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/deepseek.php';

header('Content-Type: application/json');
requireAdmin();

$db     = getDB();
$period = max(7, min(365, (int)get('period', 30)));
$question = trim(get('question', ''));   // optional natural-language question

// ------------------------------------------------------------------
// Build chart datasets
// ------------------------------------------------------------------

// Sales over time
$salesStmt = $db->prepare(
    "SELECT DATE(placed_at) AS label, COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue
     FROM orders
     WHERE placed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
     GROUP BY DATE(placed_at)
     ORDER BY label ASC"
);
$salesStmt->execute([$period]);
$salesRows = $salesStmt->fetchAll(PDO::FETCH_ASSOC);

// Traffic proxy: product views stored in admin_logs
$viewsStmt = $db->prepare(
    "SELECT DATE(created_at) AS label, COUNT(*) AS views
     FROM admin_logs
     WHERE action = 'view_product'
       AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
     GROUP BY DATE(created_at)
     ORDER BY label ASC"
);
$viewsStmt->execute([$period]);
$viewsRows = $viewsStmt->fetchAll(PDO::FETCH_ASSOC);

// Revenue by category
$catRevStmt = $db->prepare(
    "SELECT c.name AS label, COALESCE(SUM(oi.total_price),0) AS revenue
     FROM order_items oi
     JOIN orders    o ON o.id  = oi.order_id
     JOIN products  p ON p.id  = oi.product_id
     JOIN categories c ON c.id = p.category_id
     WHERE o.placed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
       AND o.payment_status = 'paid'
     GROUP BY c.id
     ORDER BY revenue DESC
     LIMIT 8"
);
$catRevStmt->execute([$period]);
$catRevRows = $catRevStmt->fetchAll(PDO::FETCH_ASSOC);

// New users vs orders per week
$weeklyStmt = $db->prepare(
    "SELECT YEARWEEK(placed_at, 1) AS week_key,
            MIN(DATE(placed_at))   AS week_start,
            COUNT(*)               AS orders
     FROM orders
     WHERE placed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
     GROUP BY YEARWEEK(placed_at, 1)
     ORDER BY week_key ASC"
);
$weeklyStmt->execute([$period]);
$weeklyOrders = $weeklyStmt->fetchAll(PDO::FETCH_ASSOC);

// Build chart structures
$charts = [
    [
        'id'    => 'salesChart',
        'type'  => 'line',
        'label' => 'Daily Orders & Revenue',
        'data'  => [
            'labels'   => array_column($salesRows, 'label'),
            'orders'   => array_map('intval', array_column($salesRows, 'orders')),
            'revenue'  => array_map('floatval', array_column($salesRows, 'revenue')),
        ],
        'ai_explanation' => '',
    ],
    [
        'id'    => 'categoryChart',
        'type'  => 'doughnut',
        'label' => 'Revenue by Category',
        'data'  => [
            'labels'  => array_column($catRevRows, 'label'),
            'revenue' => array_map('floatval', array_column($catRevRows, 'revenue')),
        ],
        'ai_explanation' => '',
    ],
    [
        'id'    => 'weeklyChart',
        'type'  => 'bar',
        'label' => 'Weekly Order Volume',
        'data'  => [
            'labels' => array_column($weeklyOrders, 'week_start'),
            'orders' => array_map('intval', array_column($weeklyOrders, 'orders')),
        ],
        'ai_explanation' => '',
    ],
];

// ------------------------------------------------------------------
// AI commentary
// ------------------------------------------------------------------
$commentary  = '';
$aiPromptData = [
    'period_days'     => $period,
    'sales_summary'   => $salesRows,
    'category_revenue'=> $catRevRows,
    'weekly_orders'   => $weeklyOrders,
    'question'        => $question ?: null,
];

try {
    $deepseek = getDeepSeek();
    $dataStr  = json_encode($aiPromptData);

    $promptText = $question !== ''
        ? "Given this marketplace data for the last {$period} days:\n$dataStr\n\nAnswer this question: $question\nAlso provide a short general commentary."
        : "Given this marketplace data for the last {$period} days:\n$dataStr\n\nProvide a 3-4 sentence executive summary of performance, highlight any notable trends, and give 2-3 quick wins.";

    $aiRaw = $deepseek->chat(
        $promptText,
        'You are a data analyst for a B2B marketplace. Provide clear, actionable commentary in plain English. Be concise.',
        ['max_tokens' => 500, 'temperature' => 0.4]
    );

    $commentary = $aiRaw;

    // Generate per-chart explanations
    foreach ($charts as &$chart) {
        $chartData = json_encode($chart['data']);
        $explanation = $deepseek->chat(
            "In 1-2 sentences, explain what this chart shows and what action a business owner should take:\nChart: {$chart['label']}\nData: $chartData",
            'You are a concise data analyst. Respond in plain English without bullet points.',
            ['max_tokens' => 120, 'temperature' => 0.3]
        );
        $chart['ai_explanation'] = trim($explanation);
    }
    unset($chart);

} catch (Throwable $e) {
    error_log('AI analytics error: ' . $e->getMessage());
    $commentary = 'AI commentary is temporarily unavailable. The charts below show your platform performance for the selected period.';
}

jsonResponse([
    'success'    => true,
    'period'     => $period,
    'commentary' => $commentary,
    'charts'     => $charts,
    'question'   => $question ?: null,
]);
