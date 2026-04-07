<?php
/**
 * pages/supplier/marketing/coupons.php — Supplier Coupon Dashboard (PR #13)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/coupons.php';
requireRole(['supplier', 'admin', 'super_admin']);

$user       = getCurrentUser();
$supplierId = (int)$user['id'];
$page       = max(1, (int)($_GET['page'] ?? 1));
$filters    = [
    'status' => $_GET['status'] ?? '',
    'type'   => $_GET['type']   ?? '',
    'search' => $_GET['search'] ?? '',
];

$result  = getSupplierCoupons($supplierId, $filters, $page, 15);
$coupons = $result['data'];

// Stats
$db = getDB();
$stats = ['active' => 0, 'total_uses' => 0, 'total_discount' => 0, 'avg_discount' => 0];
try {
    $statsRow = $db->prepare(
        'SELECT
            SUM(c.is_active = 1 AND (c.valid_to IS NULL OR c.valid_to >= NOW())) active_coupons,
            COALESCE(SUM(c.usage_count), 0) total_uses
         FROM coupons c
         WHERE c.created_by = ? AND c.creator_role = "supplier" AND c.deleted_at IS NULL'
    );
    $statsRow->execute([$supplierId]);
    $s = $statsRow->fetch(PDO::FETCH_ASSOC);
    $stats['active']      = (int)($s['active_coupons'] ?? 0);
    $stats['total_uses']  = (int)($s['total_uses'] ?? 0);

    $discountRow = $db->prepare(
        'SELECT COALESCE(SUM(cu.discount_amount),0) total, COALESCE(AVG(cu.discount_amount),0) avg_d
         FROM coupon_usages cu
         JOIN coupons c ON c.id = cu.coupon_id
         WHERE c.created_by = ? AND c.creator_role = "supplier"'
    );
    $discountRow->execute([$supplierId]);
    $dr = $discountRow->fetch(PDO::FETCH_ASSOC);
    $stats['total_discount'] = (float)($dr['total'] ?? 0);
    $stats['avg_discount']   = round((float)($dr['avg_d'] ?? 0), 2);
} catch (PDOException $e) { /* ignore */ }

$pageTitle = 'My Coupons';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-tags text-success me-2"></i>My Coupons</h3>
        <a href="/pages/supplier/marketing/coupon-form.php" class="btn btn-success">
            <i class="bi bi-plus-lg me-1"></i>Create Coupon
        </a>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-success"><?= $stats['active'] ?></div>
                <div class="text-muted small">Active Coupons</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-primary"><?= number_format($stats['total_uses']) ?></div>
                <div class="text-muted small">Total Uses</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-warning">$<?= number_format($stats['total_discount'], 2) ?></div>
                <div class="text-muted small">Total Discount Given</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-info">$<?= number_format($stats['avg_discount'], 2) ?></div>
                <div class="text-muted small">Average Discount</div>
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
                        <option value="percentage"   <?= $filters['type'] === 'percentage'   ? 'selected' : '' ?>>Percentage</option>
                        <option value="fixed"        <?= $filters['type'] === 'fixed'        ? 'selected' : '' ?>>Fixed Amount</option>
                        <option value="free_shipping"<?= $filters['type'] === 'free_shipping' ? 'selected' : '' ?>>Free Shipping</option>
                        <option value="bxgy"         <?= $filters['type'] === 'bxgy'         ? 'selected' : '' ?>>Buy X Get Y</option>
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
                        <th>Min Order</th>
                        <th>Usage</th>
                        <th>Valid Period</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($coupons)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No coupons found. <a href="/pages/supplier/marketing/coupon-form.php">Create your first coupon</a>.</td></tr>
                <?php else: ?>
                <?php foreach ($coupons as $c): ?>
                <?php
                    $now = time();
                    $from = !empty($c['valid_from']) ? strtotime($c['valid_from']) : 0;
                    $to   = !empty($c['valid_to'])   ? strtotime($c['valid_to'])   : PHP_INT_MAX;
                    if (!$c['is_active']) {
                        $statusBadge = '<span class="badge bg-secondary">Inactive</span>';
                    } elseif ($from > $now) {
                        $statusBadge = '<span class="badge bg-info">Upcoming</span>';
                    } elseif ($to < $now) {
                        $statusBadge = '<span class="badge bg-danger">Expired</span>';
                    } elseif ($c['usage_limit'] && $c['usage_count'] >= $c['usage_limit']) {
                        $statusBadge = '<span class="badge bg-secondary">Depleted</span>';
                    } else {
                        $statusBadge = '<span class="badge bg-success">Active</span>';
                    }
                    $typeLabel = ['percentage' => '% Off', 'fixed' => '$ Off', 'free_shipping' => 'Free Ship', 'bxgy' => 'Buy X Get Y'][$c['type']] ?? $c['type'];
                    $valueLabel = $c['type'] === 'percentage' ? $c['value'] . '%'
                        : ($c['type'] === 'fixed' ? '$' . number_format($c['value'], 2)
                        : ($c['type'] === 'free_shipping' ? '—' : 'Buy ' . $c['buy_x'] . ' Get ' . $c['get_y']));
                ?>
                <tr>
                    <td>
                        <code class="text-success"><?= e($c['code']) ?></code>
                        <button class="btn btn-link btn-sm p-0 ms-1 text-muted" title="Copy code" onclick="navigator.clipboard.writeText('<?= e($c['code']) ?>');this.innerHTML='<i class=\'bi bi-check text-success\'></i>';setTimeout(()=>this.innerHTML='<i class=\'bi bi-clipboard\'></i>',1500)">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </td>
                    <td><span class="badge bg-light text-dark border"><?= $typeLabel ?></span></td>
                    <td><?= $valueLabel ?></td>
                    <td><?= $c['min_order_amount'] > 0 ? '$' . number_format($c['min_order_amount'], 2) : '—' ?></td>
                    <td>
                        <span><?= (int)$c['usage_count'] ?><?= $c['usage_limit'] ? ' / ' . (int)$c['usage_limit'] : '' ?></span>
                    </td>
                    <td>
                        <div style="font-size:.8rem">
                            <?= !empty($c['valid_from']) ? date('M j, Y', strtotime($c['valid_from'])) : 'Any' ?>
                            →
                            <?= !empty($c['valid_to']) ? date('M j, Y', strtotime($c['valid_to'])) : 'Forever' ?>
                        </div>
                    </td>
                    <td><?= $statusBadge ?></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="/pages/supplier/marketing/coupon-form.php?id=<?= (int)$c['id'] ?>" class="btn btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                            <button class="btn btn-outline-danger" title="Deactivate" onclick="deactivateCoupon(<?= (int)$c['id'] ?>)"><i class="bi bi-pause-circle"></i></button>
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
function deactivateCoupon(id) {
    if (!confirm('Deactivate this coupon?')) return;
    fetch('/api/coupons.php?action=delete', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'coupon_id=' + id + '&csrf_token=' + encodeURIComponent(CSRF_TOKEN),
    })
    .then(r => r.json())
    .then(d => { if (d.success) location.reload(); else alert(d.message); });
}
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
