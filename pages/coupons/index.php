<?php
require_once __DIR__ . '/../../includes/middleware.php';

$db = getDB();
$search = get('search', '');
$page = max(1, (int)get('page', 1));
$admin = isLoggedIn() && isAdmin();

$escapedSearch = str_replace(['%', '_'], ['\\%', '\\_'], $search);

if ($admin) {
    $sql = "SELECT * FROM coupons WHERE 1=1";
    $params = [];
    if ($search) {
        $sql .= " AND code LIKE ?";
        $params[] = "%$escapedSearch%";
    }
    $sql .= " ORDER BY created_at DESC";
    $result = paginate($db, $sql, $params, $page);
    $coupons = $result['data'];
    $pagination = $result;
} else {
    $sql = "SELECT * FROM coupons WHERE status = 'active' AND (end_date IS NULL OR end_date >= NOW())";
    $params = [];
    if ($search) {
        $sql .= " AND code LIKE ?";
        $params[] = "%$escapedSearch%";
    }
    $sql .= " ORDER BY created_at DESC";
    $result = paginate($db, $sql, $params, $page);
    $coupons = $result['data'];
    $pagination = $result;
}

$pageTitle = 'Coupons';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item active">Coupons</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2 mb-0"><i class="bi bi-ticket-perforated me-2"></i>Coupons</h1>
        <div class="d-flex gap-2">
            <?php if ($admin): ?>
                <a href="/pages/coupons/create.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>Create Coupon
                </a>
            <?php endif; ?>
            <?php if (isLoggedIn()): ?>
                <a href="/pages/coupons/redeem.php" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-gift me-1"></i>Redeem Coupon
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Search -->
    <form method="get" class="mb-4">
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" name="search" class="form-control" placeholder="Search coupons..." value="<?= e($search) ?>">
            <button class="btn btn-primary" type="submit">Search</button>
            <?php if ($search): ?>
                <a href="/pages/coupons/" class="btn btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (empty($coupons)): ?>
        <div class="text-center py-5">
            <i class="bi bi-ticket-perforated display-1 text-muted"></i>
            <h3 class="mt-3 text-muted">No Coupons Found</h3>
            <p class="text-muted">
                <?= $search ? 'No coupons match your search.' : 'There are no coupons available at the moment.' ?>
            </p>
        </div>
    <?php elseif ($admin): ?>
        <!-- Admin Table View -->
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Type</th>
                            <th>Value</th>
                            <th>Min Order</th>
                            <th>Max Uses</th>
                            <th>Used</th>
                            <th>Expiry</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coupons as $coupon): ?>
                            <tr>
                                <td><code class="fs-6"><?= e($coupon['code']) ?></code></td>
                                <td>
                                    <?php if (($coupon['type'] ?? '') === 'percentage'): ?>
                                        <span class="badge bg-info text-dark">Percentage</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary">Fixed</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (($coupon['type'] ?? '') === 'percentage'): ?>
                                        <?= e($coupon['value']) ?>%
                                    <?php else: ?>
                                        <?= formatMoney($coupon['value'] ?? 0) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatMoney($coupon['min_order'] ?? $coupon['min_order_amount'] ?? 0) ?></td>
                                <td><?= e($coupon['max_uses'] ?? '∞') ?></td>
                                <td><?= e($coupon['used_count'] ?? $coupon['times_used'] ?? 0) ?></td>
                                <td>
                                    <?php if (!empty($coupon['end_date'])): ?>
                                        <?= formatDate($coupon['end_date']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">No expiry</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                        $status = $coupon['status'] ?? 'active';
                                        $statusColors = ['active' => 'success', 'inactive' => 'secondary', 'expired' => 'danger'];
                                    ?>
                                    <span class="badge bg-<?= $statusColors[$status] ?? 'secondary' ?>"><?= e(ucfirst($status)) ?></span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="/pages/coupons/create.php?edit=<?= e($coupon['id']) ?>" class="btn btn-outline-primary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="post" action="/api/coupons.php?action=delete" class="d-inline" onsubmit="return confirm('Delete this coupon?')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="id" value="<?= e($coupon['id']) ?>">
                                            <button class="btn btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <!-- User Card View -->
        <div class="row g-4">
            <?php foreach ($coupons as $coupon): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-dashed">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="bi bi-ticket-perforated display-4 text-primary"></i>
                            </div>
                            <h5 class="card-title">
                                <code class="fs-4 user-select-all"><?= e($coupon['code']) ?></code>
                            </h5>
                            <p class="fs-3 fw-bold text-success mb-1">
                                <?php if (($coupon['type'] ?? '') === 'percentage'): ?>
                                    <?= e($coupon['value']) ?>% OFF
                                <?php else: ?>
                                    <?= formatMoney($coupon['value'] ?? 0) ?> OFF
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($coupon['min_order']) || !empty($coupon['min_order_amount'])): ?>
                                <p class="text-muted mb-2">
                                    Min. order: <?= formatMoney($coupon['min_order'] ?? $coupon['min_order_amount'] ?? 0) ?>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($coupon['end_date'])): ?>
                                <p class="text-muted small">
                                    <i class="bi bi-clock me-1"></i>Expires: <?= formatDate($coupon['end_date']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-transparent text-center">
                            <button class="btn btn-outline-primary btn-sm" onclick="navigator.clipboard.writeText('<?= e($coupon['code']) ?>').then(() => this.innerHTML = '<i class=\'bi bi-check\'></i> Copied!')">
                                <i class="bi bi-clipboard"></i> Copy Code
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if (($pagination['last_page'] ?? 1) > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php for ($i = 1; $i <= $pagination['last_page']; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $pagination['last_page'] ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
