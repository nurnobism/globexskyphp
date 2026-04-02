<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db = getDB();

$suppliers = $db->query("
    SELECT s.company_name,
           s.rating,
           s.verified,
           u.created_at,
           COUNT(DISTINCT o.id) AS total_orders,
           AVG(r.rating) AS avg_review
    FROM suppliers s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN products p ON p.supplier_id = s.id
    LEFT JOIN order_items oi ON oi.product_id = p.id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status != 'cancelled'
    LEFT JOIN reviews r ON r.product_id = p.id AND r.status = 'approved'
    GROUP BY s.id, s.company_name, s.rating, s.verified, u.created_at
    ORDER BY s.rating DESC
    LIMIT 20
")->fetchAll();

$pageTitle = 'Supplier Performance Insights';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-people-fill me-2"></i><?= e($pageTitle) ?></h1>
            <p class="text-muted mb-0">Supplier scorecards and performance metrics</p>
        </div>
        <a href="/pages/insights/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Insights</a>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <i class="bi bi-shop fs-3 text-primary"></i>
                    <h4 class="mt-2 mb-0"><?= count($suppliers) ?></h4>
                    <small class="text-muted">Total Suppliers</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <i class="bi bi-patch-check fs-3 text-success"></i>
                    <h4 class="mt-2 mb-0"><?= count(array_filter($suppliers, fn($s) => $s['verified'])) ?></h4>
                    <small class="text-muted">Verified Suppliers</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <i class="bi bi-star fs-3 text-warning"></i>
                    <?php
                        $ratings = array_filter(array_column($suppliers, 'rating'), fn($r) => $r > 0);
                        $avgRating = count($ratings) > 0 ? array_sum($ratings) / count($ratings) : 0;
                    ?>
                    <h4 class="mt-2 mb-0"><?= number_format($avgRating, 1) ?></h4>
                    <small class="text-muted">Avg Rating</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <i class="bi bi-box-seam fs-3 text-info"></i>
                    <h4 class="mt-2 mb-0"><?= number_format(array_sum(array_column($suppliers, 'total_orders'))) ?></h4>
                    <small class="text-muted">Total Orders</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Supplier Scorecards Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0"><i class="bi bi-clipboard-data me-2"></i>Supplier Scorecards</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Supplier Name</th>
                            <th>Quality Score</th>
                            <th>Delivery Score</th>
                            <th class="text-center">Communication</th>
                            <th class="text-center">Overall Score</th>
                            <th>Review Period</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($suppliers)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No supplier data available.</td></tr>
                        <?php else: ?>
                            <?php foreach ($suppliers as $sup):
                                $baseRating = (float)($sup['rating'] ?: 0);
                                $reviewRating = (float)($sup['avg_review'] ?: 0);

                                // Derive scores from available data
                                $qualityScore = min(100, round(($reviewRating > 0 ? $reviewRating : $baseRating) * 20));
                                $deliveryScore = min(100, round($baseRating * 20));
                                $commScore = min(100, round((($baseRating + ($reviewRating ?: $baseRating)) / 2) * 20));
                                $overallScore = round(($qualityScore + $deliveryScore + $commScore) / 3);

                                $qualityClass = $qualityScore >= 75 ? 'success' : ($qualityScore >= 50 ? 'warning' : 'danger');
                                $deliveryClass = $deliveryScore >= 75 ? 'success' : ($deliveryScore >= 50 ? 'warning' : 'danger');
                                $overallClass = $overallScore >= 75 ? 'success' : ($overallScore >= 50 ? 'warning' : 'danger');
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e($sup['company_name']) ?></div>
                                        <?php if ($sup['verified']): ?>
                                            <span class="badge bg-success-subtle text-success"><i class="bi bi-patch-check-fill me-1"></i>Verified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="min-width: 150px;">
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1 me-2" style="height: 10px;">
                                                <div class="progress-bar bg-<?= $qualityClass ?>" style="width: <?= $qualityScore ?>%"></div>
                                            </div>
                                            <small class="fw-semibold"><?= $qualityScore ?>%</small>
                                        </div>
                                    </td>
                                    <td style="min-width: 150px;">
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1 me-2" style="height: 10px;">
                                                <div class="progress-bar bg-<?= $deliveryClass ?>" style="width: <?= $deliveryScore ?>%"></div>
                                            </div>
                                            <small class="fw-semibold"><?= $deliveryScore ?>%</small>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $commScore >= 75 ? 'success' : ($commScore >= 50 ? 'warning' : 'danger') ?> fs-6"><?= $commScore ?>%</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $overallClass ?> fs-6"><?= $overallScore ?>%</span>
                                    </td>
                                    <td>
                                        <small class="text-muted">Since <?= formatDate($sup['created_at']) ?></small>
                                    </td>
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
