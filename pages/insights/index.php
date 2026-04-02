<?php
require_once __DIR__ . '/../../includes/middleware.php';

$db = getDB();

$revenueRow = $db->query("SELECT COALESCE(SUM(total),0) AS revenue FROM orders WHERE status != 'cancelled'")->fetch();
$orderCount = $db->query("SELECT COUNT(*) AS cnt FROM orders WHERE status != 'cancelled'")->fetch();
$customerCount = $db->query("SELECT COUNT(*) AS cnt FROM users WHERE role = 'buyer'")->fetch();
$avgOrder = $orderCount['cnt'] > 0 ? $revenueRow['revenue'] / $orderCount['cnt'] : 0;

$topCategories = $db->query("
    SELECT c.name, COUNT(oi.id) AS order_count, SUM(oi.total_price) AS total_sales
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    GROUP BY c.id, c.name
    ORDER BY total_sales DESC
    LIMIT 5
")->fetchAll();

$monthlySales = $db->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, SUM(total) AS revenue, COUNT(*) AS orders
    FROM orders WHERE status != 'cancelled'
    GROUP BY month ORDER BY month DESC LIMIT 6
")->fetchAll();

$pageTitle = 'Business Insights';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-graph-up-arrow me-2"></i><?= e($pageTitle) ?></h1>
            <p class="text-muted mb-0">Key performance indicators and business analytics</p>
        </div>
        <div>
            <a href="/pages/insights/market.php" class="btn btn-outline-primary me-2"><i class="bi bi-bar-chart me-1"></i>Market Trends</a>
            <?php if (isLoggedIn() && isSupplier()): ?>
                <a href="/pages/insights/supplier.php" class="btn btn-outline-secondary"><i class="bi bi-people me-1"></i>Supplier Insights</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="bg-success bg-opacity-10 rounded-circle p-2 me-2">
                            <i class="bi bi-currency-dollar text-success fs-5"></i>
                        </div>
                        <span class="text-muted small">Revenue Trend</span>
                    </div>
                    <h3 class="mb-1"><?= formatMoney($revenueRow['revenue']) ?></h3>
                    <span class="badge bg-success-subtle text-success"><i class="bi bi-arrow-up"></i> Total Revenue</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-2">
                            <i class="bi bi-tags text-primary fs-5"></i>
                        </div>
                        <span class="text-muted small">Top Categories</span>
                    </div>
                    <h3 class="mb-1"><?= count($topCategories) ?></h3>
                    <span class="badge bg-primary-subtle text-primary">Active Categories</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="bg-warning bg-opacity-10 rounded-circle p-2 me-2">
                            <i class="bi bi-box-seam text-warning fs-5"></i>
                        </div>
                        <span class="text-muted small">Order Volume</span>
                    </div>
                    <h3 class="mb-1"><?= number_format($orderCount['cnt']) ?></h3>
                    <span class="badge bg-warning-subtle text-warning">Avg <?= formatMoney($avgOrder) ?>/order</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="bg-info bg-opacity-10 rounded-circle p-2 me-2">
                            <i class="bi bi-people text-info fs-5"></i>
                        </div>
                        <span class="text-muted small">Customer Growth</span>
                    </div>
                    <h3 class="mb-1"><?= number_format($customerCount['cnt']) ?></h3>
                    <span class="badge bg-info-subtle text-info">Registered Buyers</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Placeholders -->
    <div class="row g-3 mb-4">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0"><i class="bi bi-graph-up me-2"></i>Revenue Chart</h5>
                </div>
                <div class="card-body text-center py-5">
                    <div class="bg-light rounded p-5">
                        <i class="bi bi-bar-chart-line display-1 text-muted"></i>
                        <p class="text-muted mt-3 mb-0">Revenue chart visualization<br><small>Integrate with Chart.js for interactive charts</small></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0"><i class="bi bi-pie-chart me-2"></i>Category Distribution</h5>
                </div>
                <div class="card-body text-center py-5">
                    <div class="bg-light rounded p-5">
                        <i class="bi bi-pie-chart-fill display-1 text-muted"></i>
                        <p class="text-muted mt-3 mb-0">Category breakdown<br><small>Integrate with Chart.js</small></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Stats Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0"><i class="bi bi-table me-2"></i>Monthly Summary</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Month</th>
                            <th>Revenue</th>
                            <th>Orders</th>
                            <th>Avg Order Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($monthlySales)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No sales data available yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($monthlySales as $row): ?>
                                <tr>
                                    <td><i class="bi bi-calendar3 me-1 text-muted"></i><?= e($row['month']) ?></td>
                                    <td class="fw-semibold"><?= formatMoney($row['revenue']) ?></td>
                                    <td><?= number_format($row['orders']) ?></td>
                                    <td><?= $row['orders'] > 0 ? formatMoney($row['revenue'] / $row['orders']) : '—' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Top Categories Table -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0"><i class="bi bi-award me-2"></i>Top Performing Categories</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Category</th>
                            <th>Orders</th>
                            <th>Total Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topCategories)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No category data available.</td></tr>
                        <?php else: ?>
                            <?php foreach ($topCategories as $i => $cat): ?>
                                <tr>
                                    <td><span class="badge bg-secondary"><?= $i + 1 ?></span></td>
                                    <td class="fw-semibold"><?= e($cat['name']) ?></td>
                                    <td><?= number_format($cat['order_count']) ?></td>
                                    <td><?= formatMoney($cat['total_sales']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
