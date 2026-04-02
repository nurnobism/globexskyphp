<?php
require_once __DIR__ . '/../../includes/middleware.php';

$db = getDB();

$trendingCategories = $db->query("
    SELECT c.name,
           COUNT(oi.id) AS order_count,
           SUM(oi.total_price) AS total_sales,
           AVG(p.price) AS avg_price
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    GROUP BY c.id, c.name
    ORDER BY order_count DESC
    LIMIT 10
")->fetchAll();

$maxOrders = 1;
foreach ($trendingCategories as $cat) {
    if ($cat['order_count'] > $maxOrders) {
        $maxOrders = $cat['order_count'];
    }
}

$priceStats = $db->query("
    SELECT c.name AS category,
           AVG(p.price) AS avg_price,
           MIN(p.price) AS min_price,
           MAX(p.price) AS max_price,
           COUNT(p.id) AS product_count
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.status = 'active'
    GROUP BY c.id, c.name
    ORDER BY avg_price DESC
    LIMIT 6
")->fetchAll();

$recentProducts = $db->query("
    SELECT p.name, p.price, c.name AS category, p.created_at
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.status = 'active'
    ORDER BY p.created_at DESC
    LIMIT 8
")->fetchAll();

$pageTitle = 'Market Trends';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-bar-chart-line me-2"></i><?= e($pageTitle) ?></h1>
            <p class="text-muted mb-0">Market trends, category growth, and demand signals</p>
        </div>
        <a href="/pages/insights/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Insights</a>
    </div>

    <!-- Trending Categories -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0"><i class="bi bi-fire me-2 text-danger"></i>Trending Categories</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Category</th>
                            <th>Orders</th>
                            <th>Growth Indicator</th>
                            <th>Demand</th>
                            <th>Total Sales</th>
                            <th>Avg Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($trendingCategories)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No trending data available yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($trendingCategories as $cat):
                                $growthPct = round(($cat['order_count'] / $maxOrders) * 100);
                                if ($growthPct >= 70) {
                                    $demandClass = 'success';
                                    $demandLabel = 'High';
                                    $demandIcon = 'bi-arrow-up-circle-fill';
                                } elseif ($growthPct >= 40) {
                                    $demandClass = 'warning';
                                    $demandLabel = 'Medium';
                                    $demandIcon = 'bi-dash-circle-fill';
                                } else {
                                    $demandClass = 'secondary';
                                    $demandLabel = 'Low';
                                    $demandIcon = 'bi-arrow-down-circle-fill';
                                }
                            ?>
                                <tr>
                                    <td class="fw-semibold"><?= e($cat['name']) ?></td>
                                    <td><?= number_format($cat['order_count']) ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                <div class="progress-bar bg-<?= $demandClass ?>" style="width: <?= $growthPct ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?= $growthPct ?>%</small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $demandClass ?>-subtle text-<?= $demandClass ?>">
                                            <i class="bi <?= $demandIcon ?> me-1"></i><?= $demandLabel ?>
                                        </span>
                                    </td>
                                    <td><?= formatMoney($cat['total_sales']) ?></td>
                                    <td><?= formatMoney($cat['avg_price']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Price Movement Cards -->
    <h5 class="mb-3"><i class="bi bi-currency-exchange me-2"></i>Price Movement by Category</h5>
    <div class="row g-3 mb-4">
        <?php if (empty($priceStats)): ?>
            <div class="col-12">
                <div class="alert alert-info mb-0"><i class="bi bi-info-circle me-2"></i>No pricing data available yet.</div>
            </div>
        <?php else: ?>
            <?php foreach ($priceStats as $stat): ?>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="card-title"><?= e($stat['category']) ?></h6>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted small">Avg Price</span>
                                <span class="fw-semibold"><?= formatMoney($stat['avg_price']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted small">Min</span>
                                <span class="text-success"><?= formatMoney($stat['min_price']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted small">Max</span>
                                <span class="text-danger"><?= formatMoney($stat['max_price']) ?></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted small">Products</span>
                                <span class="badge bg-primary"><?= number_format($stat['product_count']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Demand Signals -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0"><i class="bi bi-lightning me-2 text-warning"></i>Latest Demand Signals</h5>
        </div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                <?php if (empty($recentProducts)): ?>
                    <li class="list-group-item text-center text-muted py-4">No recent demand signals.</li>
                <?php else: ?>
                    <?php foreach ($recentProducts as $prod): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-box-seam me-2 text-primary"></i>
                                <strong><?= e($prod['name']) ?></strong>
                                <span class="badge bg-light text-dark ms-2"><?= e($prod['category']) ?></span>
                            </div>
                            <div>
                                <span class="fw-semibold me-3"><?= formatMoney($prod['price']) ?></span>
                                <small class="text-muted"><?= formatDate($prod['created_at']) ?></small>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
