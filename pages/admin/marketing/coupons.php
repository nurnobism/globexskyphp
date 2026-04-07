<?php
/**
 * pages/admin/marketing/coupons.php — Admin Coupon Dashboard (PR #13)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/coupons.php';
requireAdmin();

$page    = max(1, (int)($_GET['page'] ?? 1));
$filters = [
    'status'       => $_GET['status']       ?? '',
    'type'         => $_GET['type']         ?? '',
    'creator_role' => $_GET['creator_role'] ?? '',
    'search'       => $_GET['search']       ?? '',
];

$result  = getCoupons($filters, $page, 20);
$coupons = $result['data'];
$analytics = getCouponAnalytics();
$overview  = $analytics['overview'];

$pageTitle = 'Coupon Management';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-ticket-perforated text-primary me-2"></i>Coupon Management</h3>
        <a href="/pages/admin/marketing/coupon-analytics.php" class="btn btn-outline-primary btn-sm me-2">
            <i class="bi bi-bar-chart me-1"></i>Analytics
        </a>
    </div>

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
                <div class="text-muted small">Total Uses</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-warning">$<?= number_format($overview['total_discount_given'] ?? 0, 2) ?></div>
                <div class="text-muted small">Total Discount Given</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <form class="row g-2 align-items-end" method="get">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search code…" value="<?= e($filters['search']) ?>">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        <option value="active"   <?= $filters['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
                        <option value="expired"  <?= $filters['status'] === 'expired'  ? 'selected' : '' ?>>Expired</option>
                        <option value="upcoming" <?= $filters['status'] === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="type" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        <option value="percentage"    <?= $filters['type'] === 'percentage'    ? 'selected' : '' ?>>Percentage</option>
                        <option value="fixed"         <?= $filters['type'] === 'fixed'         ? 'selected' : '' ?>>Fixed</option>
                        <option value="free_shipping" <?= $filters['type'] === 'free_shipping' ? 'selected' : '' ?>>Free Shipping</option>
                        <option value="bxgy"          <?= $filters['type'] === 'bxgy'          ? 'selected' : '' ?>>Buy X Get Y</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="creator_role" class="form-select form-select-sm">
                        <option value="">All Creators</option>
                        <option value="admin"    <?= $filters['creator_role'] === 'admin'    ? 'selected' : '' ?>>Admin</option>
                        <option value="supplier" <?= $filters['creator_role'] === 'supplier' ? 'selected' : '' ?>>Supplier</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Coupons Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Type</th>
                        <th>Value</th>
                        <th>Creator</th>
                        <th>Usage</th>
                        <th>Valid Period</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($coupons)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No coupons found.</td></tr>
                <?php else: ?>
                <?php foreach ($coupons as $c): ?>
                <?php
                    $now = time();
                    $from = !empty($c['valid_from']) ? strtotime($c['valid_from']) : 0;
                    $to   = !empty($c['valid_to'])   ? strtotime($c['valid_to'])   : PHP_INT_MAX;
                    if (!$c['is_active']) {
                        $badge = '<span class="badge bg-secondary">Inactive</span>';
                    } elseif ($from > $now) {
                        $badge = '<span class="badge bg-info">Upcoming</span>';
                    } elseif ($to < $now) {
                        $badge = '<span class="badge bg-danger">Expired</span>';
                    } elseif ($c['usage_limit'] && $c['usage_count'] >= $c['usage_limit']) {
                        $badge = '<span class="badge bg-secondary">Depleted</span>';
                    } else {
                        $badge = '<span class="badge bg-success">Active</span>';
                    }
                    $typeMap = ['percentage' => '% Off', 'fixed' => '$ Off', 'free_shipping' => 'Free Ship', 'bxgy' => 'Buy X Get Y'];
                    $typeLabel = $typeMap[$c['type']] ?? $c['type'];
                    $valueLabel = $c['type'] === 'percentage' ? $c['value'] . '%'
                        : ($c['type'] === 'fixed' ? '$' . number_format($c['value'], 2)
                        : ($c['type'] === 'free_shipping' ? '—' : 'Buy ' . $c['buy_x'] . ' Get ' . $c['get_y']));
                ?>
                <tr>
                    <td><code class="text-primary"><?= e($c['code']) ?></code></td>
                    <td><span class="badge bg-light text-dark border"><?= $typeLabel ?></span></td>
                    <td><?= $valueLabel ?></td>
                    <td>
                        <span class="badge <?= $c['creator_role'] === 'admin' ? 'bg-primary' : 'bg-success' ?>">
                            <?= ucfirst($c['creator_role']) ?>
                        </span>
                    </td>
                    <td><?= (int)$c['usage_count'] ?><?= $c['usage_limit'] ? ' / ' . (int)$c['usage_limit'] : '' ?></td>
                    <td style="font-size:.8rem">
                        <?= !empty($c['valid_from']) ? date('M j, Y', strtotime($c['valid_from'])) : '—' ?>
                        → <?= !empty($c['valid_to']) ? date('M j, Y', strtotime($c['valid_to'])) : '∞' ?>
                    </td>
                    <td><?= $badge ?></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-info" onclick="viewStats(<?= (int)$c['id'] ?>)" title="Stats"><i class="bi bi-bar-chart"></i></button>
                            <button class="btn btn-outline-danger" onclick="deleteCoupon(<?= (int)$c['id'] ?>)" title="Delete"><i class="bi bi-trash"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($result['total_pages'] > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <small class="text-muted">Showing <?= count($coupons) ?> of <?= $result['total'] ?></small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($i = 1; $i <= $result['total_pages']; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query($filters) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
const CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;
function deleteCoupon(id) {
    if (!confirm('Delete/deactivate this coupon?')) return;
    fetch('/api/coupons.php?action=delete', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'coupon_id=' + id + '&csrf_token=' + encodeURIComponent(CSRF_TOKEN),
    })
    .then(r => r.json())
    .then(d => { if (d.success) location.reload(); else alert(d.message); });
}
function viewStats(id) {
    window.location.href = '/pages/admin/marketing/coupon-analytics.php?coupon_id=' + id;
}
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
