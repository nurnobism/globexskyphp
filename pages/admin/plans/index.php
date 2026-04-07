<?php
/**
 * pages/admin/plans/index.php — Admin Plan Overview (PR #9)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/plans.php';
requireAdmin();

$db = getDB();

// Stats: subscribers by plan + MRR
$planStats = getPlanSubscriberCounts();

// Total MRR
$totalMRR = array_sum(array_column($planStats, 'mrr'));

// Recent subscription changes
$recentChanges = [];
try {
    $stmt = $db->query(
        'SELECT ps.*, sp.name AS plan_name, sp.slug AS plan_slug,
                u.name AS supplier_name, u.email AS supplier_email
         FROM plan_subscriptions ps
         JOIN supplier_plans sp ON sp.id = ps.plan_id
         JOIN users u ON u.id = ps.supplier_id
         ORDER BY ps.updated_at DESC LIMIT 10'
    );
    $recentChanges = $stmt->fetchAll();
} catch (PDOException $e) { /* table may not exist */ }

// Supplier plan table (paginated)
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;
$search  = trim($_GET['search'] ?? '');
$planFilter = trim($_GET['plan'] ?? '');

$suppliers   = [];
$totalCount  = 0;
try {
    $where  = 'WHERE 1=1';
    $params = [];
    if ($search !== '') {
        $where  .= ' AND (u.name LIKE ? OR u.email LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($planFilter !== '') {
        $where  .= ' AND sp.slug = ?';
        $params[] = $planFilter;
    }

    $countStmt = $db->prepare(
        "SELECT COUNT(DISTINCT u.id)
         FROM users u
         LEFT JOIN plan_subscriptions ps ON ps.supplier_id = u.id AND ps.status IN ('active','trialing')
         LEFT JOIN supplier_plans sp ON sp.id = ps.plan_id
         $where"
    );
    $countStmt->execute($params);
    $totalCount = (int)$countStmt->fetchColumn();

    $listStmt = $db->prepare(
        "SELECT u.id AS user_id, u.name, u.email,
                COALESCE(sp.name,'Free') AS plan_name,
                COALESCE(sp.slug,'free') AS plan_slug,
                ps.status AS sub_status,
                ps.billing_period,
                ps.current_period_end,
                ps.cancel_at_period_end,
                ps.created_at AS subscribed_at,
                COALESCE(ps.amount,0) AS amount
         FROM users u
         LEFT JOIN plan_subscriptions ps ON ps.supplier_id = u.id AND ps.status IN ('active','trialing')
         LEFT JOIN supplier_plans sp ON sp.id = ps.plan_id
         $where
         ORDER BY subscribed_at DESC
         LIMIT ? OFFSET ?"
    );
    $listStmt->execute(array_merge($params, [$perPage, $offset]));
    $suppliers = $listStmt->fetchAll();
} catch (PDOException $e) { /* ignore */ }

$allPlans  = getPlans();
$totalPages = (int)ceil($totalCount / $perPage);

$pageTitle = 'Admin — Supplier Plans';
include __DIR__ . '/../../../includes/header.php';

$planBadge = function (string $slug, string $name): string {
    return match ($slug) {
        'pro'        => '<span class="badge bg-primary">' . htmlspecialchars($name) . '</span>',
        'enterprise' => '<span class="badge bg-warning text-dark">' . htmlspecialchars($name) . '</span>',
        default      => '<span class="badge bg-light text-dark border">' . htmlspecialchars($name) . '</span>',
    };
};

$statusBadge = function (?string $status): string {
    return match ($status) {
        'active'   => '<span class="badge bg-success">Active</span>',
        'trialing' => '<span class="badge bg-info">Trial</span>',
        'past_due' => '<span class="badge bg-warning text-dark">Past Due</span>',
        null       => '<span class="badge bg-light text-dark border">Free</span>',
        default    => '<span class="badge bg-secondary">' . htmlspecialchars(ucfirst((string)$status)) . '</span>',
    };
};
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-layers text-primary me-2"></i>Supplier Plans</h3>
        <a href="/pages/admin/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Admin Dashboard
        </a>
    </div>

    <!-- Stats cards -->
    <div class="row g-3 mb-4">
        <?php foreach ($planStats as $stat): ?>
        <div class="col-sm-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small mb-1"><?= e($stat['name']) ?> Plan</div>
                            <div class="fs-3 fw-bold"><?= (int)($stat['subscriber_count'] ?? 0) ?></div>
                            <div class="small text-muted">subscribers</div>
                        </div>
                        <span class="fs-2">
                            <?= match ($stat['slug'] ?? 'free') {
                                'pro'        => '⭐',
                                'enterprise' => '💎',
                                default      => '🆓',
                            } ?>
                        </span>
                    </div>
                    <?php if ((float)($stat['mrr'] ?? 0) > 0): ?>
                    <div class="mt-2 text-success small fw-semibold">
                        MRR: $<?= number_format((float)$stat['mrr'], 0) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <!-- Total MRR card -->
        <div class="col-sm-6 col-md-3">
            <div class="card border-0 shadow-sm h-100 bg-success bg-opacity-10">
                <div class="card-body">
                    <div class="text-muted small mb-1">Total MRR</div>
                    <div class="fs-3 fw-bold text-success">$<?= number_format($totalMRR, 0) ?></div>
                    <div class="small text-muted">monthly recurring revenue</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent changes -->
    <?php if (!empty($recentChanges)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-light fw-semibold">
            <i class="bi bi-clock-history me-2 text-primary"></i>Recent Subscription Changes
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Supplier</th>
                            <th>Plan</th>
                            <th>Status</th>
                            <th>Changed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentChanges as $chg): ?>
                        <tr>
                            <td class="ps-3 small">
                                <div class="fw-semibold"><?= e($chg['supplier_name'] ?? '—') ?></div>
                                <div class="text-muted"><?= e($chg['supplier_email'] ?? '') ?></div>
                            </td>
                            <td><?= $planBadge($chg['plan_slug'] ?? 'free', $chg['plan_name'] ?? 'Free') ?></td>
                            <td><?= $statusBadge($chg['status'] ?? null) ?></td>
                            <td class="small text-muted">
                                <?= htmlspecialchars(!empty($chg['updated_at']) ? date('M j, Y H:i', strtotime($chg['updated_at'])) : '—') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Supplier plan list -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-people me-2 text-primary"></i>All Suppliers</span>
                <form method="GET" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control form-control-sm"
                           placeholder="Search name / email…" value="<?= htmlspecialchars($search) ?>">
                    <select name="plan" class="form-select form-select-sm" style="width:auto">
                        <option value="">All plans</option>
                        <?php foreach ($allPlans as $p): ?>
                        <option value="<?= e($p['slug']) ?>" <?= $planFilter === $p['slug'] ? 'selected' : '' ?>>
                            <?= e($p['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline-primary btn-sm" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </form>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Supplier</th>
                            <th>Plan</th>
                            <th>Billing</th>
                            <th>Status</th>
                            <th>Next Billing</th>
                            <th>MRR</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($suppliers)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No suppliers found.</td></tr>
                        <?php else: foreach ($suppliers as $sup): ?>
                        <tr>
                            <td class="ps-3">
                                <div class="fw-semibold"><?= e($sup['name'] ?? '—') ?></div>
                                <div class="small text-muted"><?= e($sup['email'] ?? '') ?></div>
                            </td>
                            <td><?= $planBadge($sup['plan_slug'] ?? 'free', $sup['plan_name'] ?? 'Free') ?></td>
                            <td class="small text-muted">
                                <?= e(ucwords(str_replace('_', '-', $sup['billing_period'] ?? 'monthly'))) ?>
                            </td>
                            <td>
                                <?= $statusBadge($sup['sub_status'] ?? null) ?>
                                <?php if (!empty($sup['cancel_at_period_end'])): ?>
                                <span class="badge bg-warning text-dark ms-1 small">Cancelling</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted">
                                <?= !empty($sup['current_period_end'])
                                    ? htmlspecialchars(date('M j, Y', strtotime($sup['current_period_end'])))
                                    : '—' ?>
                            </td>
                            <td class="small">
                                <?= (float)($sup['amount'] ?? 0) > 0
                                    ? '$' . number_format((float)$sup['amount'], 0)
                                    : '—' ?>
                            </td>
                            <td class="text-end pe-3">
                                <div class="dropdown">
                                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle"
                                            data-bs-toggle="dropdown">
                                        Manage
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php foreach ($allPlans as $p): ?>
                                        <?php if ($p['slug'] !== ($sup['plan_slug'] ?? 'free')): ?>
                                        <li>
                                            <form method="POST" action="/api/plans.php?action=admin_update" class="d-inline">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="supplier_id" value="<?= (int)$sup['user_id'] ?>">
                                                <input type="hidden" name="plan_slug"   value="<?= e($p['slug']) ?>">
                                                <button type="submit" class="dropdown-item"
                                                        onclick="return confirm('Set <?= e($sup['name'] ?? '') ?> to <?= e($p['name']) ?> plan?')">
                                                    <i class="bi bi-arrow-right-circle me-2"></i>Set to <?= e($p['name']) ?>
                                                </button>
                                            </form>
                                        </li>
                                        <?php endif; endforeach; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a href="/pages/admin/users.php?id=<?= (int)$sup['user_id'] ?>" class="dropdown-item">
                                                <i class="bi bi-person me-2"></i>View Profile
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-transparent">
            <nav>
                <ul class="pagination pagination-sm mb-0 justify-content-center">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&plan=<?= urlencode($planFilter) ?>">
                            <?= $p ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
