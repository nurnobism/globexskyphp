<?php
/**
 * pages/admin/marketing/coupon-analytics.php — Coupon Analytics Dashboard (PR #13)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/coupons.php';
requireAdmin();

$couponId = (int)($_GET['coupon_id'] ?? 0);
$singleCoupon = $couponId > 0 ? getCoupon($couponId) : null;

$analytics   = getCouponAnalytics();
$overview    = $analytics['overview'];
$topCoupons  = $analytics['top_coupons'];

// Single-coupon detail
$singleUsage = null;
if ($singleCoupon) {
    $singleUsage = getCouponUsage($couponId);
}

// Usage trend (last 30 days)
$db = getDB();
$usageTrend = [];
try {
    $stmt = $db->query(
        'SELECT DATE(used_at) d, COUNT(*) cnt, COALESCE(SUM(discount_amount),0) total_discount
         FROM coupon_usages
         WHERE used_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY d ORDER BY d ASC'
    );
    $usageTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* ignore */ }

$pageTitle = $singleCoupon ? 'Coupon Stats: ' . $singleCoupon['code'] : 'Coupon Analytics';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">

    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/pages/admin/marketing/coupons.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <h3 class="fw-bold mb-0">
            <i class="bi bi-bar-chart text-primary me-2"></i>
            <?= $singleCoupon ? 'Stats: ' . e($singleCoupon['code']) : 'Coupon Analytics' ?>
        </h3>
    </div>

    <?php if ($singleCoupon && $singleUsage): ?>
    <!-- Single Coupon Detail -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-primary"><?= number_format($singleUsage['total_used']) ?></div>
                <div class="text-muted small">Total Uses</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-warning">$<?= number_format($singleUsage['total_discount'], 2) ?></div>
                <div class="text-muted small">Total Discount Given</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-success"><?= number_format($singleCoupon['usage_limit'] ?? 0) ?></div>
                <div class="text-muted small">Usage Limit</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <?php
                    $remaining = $singleCoupon['usage_limit']
                        ? max(0, $singleCoupon['usage_limit'] - $singleUsage['total_used'])
                        : '∞';
                ?>
                <div class="fs-2 fw-bold text-info"><?= $remaining ?></div>
                <div class="text-muted small">Remaining</div>
            </div>
        </div>
    </div>

    <?php if (!empty($singleUsage['by_user'])): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold">Top Users</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr><th>User</th><th>Times Used</th><th>Total Saved</th></tr>
                </thead>
                <tbody>
                <?php foreach ($singleUsage['by_user'] as $u): ?>
                <tr>
                    <td><?= e($u['email'] ?? 'User #' . $u['user_id']) ?></td>
                    <td><?= (int)$u['times'] ?></td>
                    <td>$<?= number_format((float)$u['total_saved'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Overview Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-primary"><?= number_format($overview['total_coupons'] ?? 0) ?></div>
                <div class="text-muted small">Total Coupons</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-success"><?= number_format($overview['active_coupons'] ?? 0) ?></div>
                <div class="text-muted small">Active</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-info"><?= number_format($overview['total_uses'] ?? 0) ?></div>
                <div class="text-muted small">Platform Total Uses</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-warning">$<?= number_format($overview['total_discount_given'] ?? 0, 2) ?></div>
                <div class="text-muted small">Total Discount Given</div>
            </div>
        </div>
    </div>

    <!-- Top Coupons -->
    <?php if (!empty($topCoupons)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold"><i class="bi bi-trophy text-warning me-2"></i>Top Coupons by Usage</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Code</th><th>Type</th><th>Uses</th><th>Discount Given</th></tr>
                </thead>
                <tbody>
                <?php foreach ($topCoupons as $tc): ?>
                <tr>
                    <td>
                        <a href="?coupon_id=<?= (int)($tc['id'] ?? 0) ?>" class="text-decoration-none">
                            <code class="text-primary"><?= e($tc['code']) ?></code>
                        </a>
                    </td>
                    <td><?= e($tc['type'] ?? '') ?></td>
                    <td><?= number_format((int)$tc['uses']) ?></td>
                    <td>$<?= number_format((float)$tc['discount_given'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Usage Trend -->
    <?php if (!empty($usageTrend)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold"><i class="bi bi-graph-up me-2 text-info"></i>Usage Trend (Last 30 Days)</h6>
        </div>
        <div class="card-body">
            <canvas id="usageTrendChart" height="80"></canvas>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <script>
    (function() {
        const labels = <?= json_encode(array_column($usageTrend, 'd')) ?>;
        const counts = <?= json_encode(array_map(function($r) { return (int)$r['cnt']; }, $usageTrend)) ?>;
        new Chart(document.getElementById('usageTrendChart'), {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Coupon Uses',
                    data: counts,
                    backgroundColor: 'rgba(13,110,253,.6)',
                    borderColor: 'rgba(13,110,253,1)',
                    borderWidth: 1,
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false } } }
        });
    })();
    </script>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
