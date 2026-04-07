<?php
/**
 * pages/admin/plans/subscribers.php — Plan Subscriber Management (PR #9)
 *
 * Table: Supplier, Plan, Start Date, Next Billing, Status, Revenue
 * Filter by plan, status
 * Actions: Override plan, extend trial, cancel
 */

require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/plans.php';
requireRole(['admin', 'super_admin']);

$db    = getDB();
$plans = getPlans();

// Filters
$filterPlanId    = (int)($_GET['plan_id']     ?? 0);
$filterStatus    = htmlspecialchars($_GET['status']      ?? '', ENT_QUOTES, 'UTF-8');
$filterSupplierId= (int)($_GET['supplier_id'] ?? 0);
$page            = max(1, (int)($_GET['page'] ?? 1));
$perPage         = 25;
$offset          = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if ($filterPlanId) {
    $where[]  = 'ps.plan_id = ?';
    $params[] = $filterPlanId;
}
if ($filterStatus) {
    $where[]  = 'ps.status = ?';
    $params[] = $filterStatus;
}
if ($filterSupplierId) {
    $where[]  = 'ps.supplier_id = ?';
    $params[] = $filterSupplierId;
}

$whereSQL = implode(' AND ', $where);

$subscribers = [];
$total       = 0;
try {
    $countStmt = $db->prepare(
        "SELECT COUNT(*) FROM plan_subscriptions ps WHERE $whereSQL"
    );
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $listStmt = $db->prepare(
        "SELECT ps.*, sp.name AS plan_name, sp.price_monthly AS plan_price,
                u.email, CONCAT(u.first_name,' ',u.last_name) AS supplier_name,
                (SELECT COALESCE(SUM(pi2.amount),0) FROM plan_invoices pi2
                 WHERE pi2.subscription_id = ps.id AND pi2.status = 'paid') AS total_paid
         FROM plan_subscriptions ps
         JOIN supplier_plans sp ON sp.id = ps.plan_id
         JOIN users u ON u.id = ps.supplier_id
         WHERE $whereSQL
         ORDER BY ps.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $listParams = array_merge($params, [$perPage, $offset]);
    $listStmt->execute($listParams);
    $subscribers = $listStmt->fetchAll();
} catch (PDOException $e) {
    $subscribers = [];
}

$totalPages = max(1, (int)ceil($total / $perPage));

$pageTitle = 'Plan Subscribers';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0"><i class="bi bi-people me-2"></i>Plan Subscribers</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="/pages/admin/plans/index.php">Plans</a></li>
                    <li class="breadcrumb-item active">Subscribers</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Plan</label>
                    <select name="plan_id" class="form-select form-select-sm">
                        <option value="">All Plans</option>
                        <?php foreach ($plans as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $filterPlanId === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="active"    <?= $filterStatus === 'active'    ? 'selected' : '' ?>>Active</option>
                        <option value="trialing"  <?= $filterStatus === 'trialing'  ? 'selected' : '' ?>>Trialing</option>
                        <option value="past_due"  <?= $filterStatus === 'past_due'  ? 'selected' : '' ?>>Past Due</option>
                        <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-search me-1"></i>Filter
                    </button>
                    <a href="?" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Subscribers Table -->
    <div class="card">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><?= number_format($total) ?> subscriber<?= $total !== 1 ? 's' : '' ?></span>
        </div>
        <?php if ($subscribers): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Supplier</th>
                        <th>Plan</th>
                        <th>Duration</th>
                        <th>Start Date</th>
                        <th>Next Billing</th>
                        <th>Revenue</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscribers as $sub): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars(trim($sub['supplier_name'] ?? '')) ?></div>
                            <div class="text-muted small"><?= htmlspecialchars($sub['email'] ?? '') ?></div>
                        </td>
                        <td>
                            <span class="badge bg-<?= ['free' => 'secondary', 'pro' => 'primary', 'enterprise' => 'warning text-dark'][$sub['plan_slug'] ?? ''] ?? 'info' ?>">
                                <?= htmlspecialchars($sub['plan_name'] ?? '') ?>
                            </span>
                        </td>
                        <td class="text-capitalize"><?= htmlspecialchars($sub['duration'] ?? 'monthly') ?></td>
                        <td><?= $sub['starts_at'] ? htmlspecialchars(date('M j, Y', strtotime($sub['starts_at']))) : '—' ?></td>
                        <td>
                            <?= $sub['ends_at'] ? htmlspecialchars(date('M j, Y', strtotime($sub['ends_at']))) : '—' ?>
                            <?php if ($sub['cancel_at_period_end'] ?? 0): ?>
                            <span class="badge bg-warning text-dark ms-1 small">Cancelling</span>
                            <?php endif; ?>
                        </td>
                        <td class="fw-semibold text-success">$<?= number_format((float)($sub['total_paid'] ?? 0), 2) ?></td>
                        <td>
                            <?php
                            echo match($sub['status'] ?? '') {
                                'active'   => '<span class="badge bg-success">Active</span>',
                                'trialing' => '<span class="badge bg-info">Trial</span>',
                                'past_due' => '<span class="badge bg-warning text-dark">Past Due</span>',
                                'cancelled'=> '<span class="badge bg-danger">Cancelled</span>',
                                default    => '<span class="badge bg-secondary">' . htmlspecialchars($sub['status'] ?? '?') . '</span>',
                            };
                            ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-secondary override-plan-btn"
                                        data-sub-id="<?= (int)$sub['id'] ?>"
                                        data-supplier-id="<?= (int)$sub['supplier_id'] ?>"
                                        data-supplier-name="<?= htmlspecialchars(trim($sub['supplier_name'] ?? '')) ?>"
                                        data-bs-toggle="modal"
                                        data-bs-target="#overridePlanModal">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                                <?php if (($sub['status'] ?? '') === 'active'): ?>
                                <button class="btn btn-outline-danger cancel-sub-btn"
                                        data-sub-id="<?= (int)$sub['id'] ?>"
                                        data-bs-toggle="modal"
                                        data-bs-target="#cancelSubModal">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <nav>
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link"
                           href="?page=<?= $i ?>&plan_id=<?= $filterPlanId ?>&status=<?= urlencode($filterStatus) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-people fs-1 d-block mb-3"></i>
            No subscribers found.
            <?php if ($filterPlanId || $filterStatus): ?>
            <br><a href="?" class="btn btn-outline-secondary btn-sm mt-2">Clear filters</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Override Plan Modal -->
<div class="modal fade" id="overridePlanModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-arrow-repeat me-2"></i>Override Plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/api/admin.php?action=override_plan">
                <input type="hidden" name="csrf_token"   value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                <input type="hidden" name="supplier_id"  id="overrideSupplierId">
                <input type="hidden" name="sub_id"       id="overrideSubId">
                <div class="modal-body">
                    <p>Override plan for: <strong id="overrideSupplierName"></strong></p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Plan</label>
                        <select name="plan_id" class="form-select" required>
                            <?php foreach ($plans as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Duration</label>
                        <select name="duration" class="form-select">
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="semi-annual">Semi-Annual</option>
                            <option value="annual">Annual</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Extend Until (optional)</label>
                        <input type="date" name="ends_at" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Apply Override</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Cancel Sub Modal -->
<div class="modal fade" id="cancelSubModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">Cancel Subscription</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/api/admin.php?action=cancel_subscription">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                <input type="hidden" name="sub_id"     id="cancelSubId">
                <div class="modal-body">
                    <p>Cancel this subscription immediately? The supplier will be moved to the Free plan.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep</button>
                    <button type="submit" class="btn btn-danger">Cancel Subscription</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.override-plan-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('overrideSupplierId').value  = this.dataset.supplierId;
        document.getElementById('overrideSubId').value       = this.dataset.subId;
        document.getElementById('overrideSupplierName').textContent = this.dataset.supplierName;
    });
});
document.querySelectorAll('.cancel-sub-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('cancelSubId').value = this.dataset.subId;
    });
});
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
