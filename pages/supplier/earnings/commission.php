<?php
/**
 * pages/supplier/earnings/commission.php — Supplier Commission History (PR #8)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/commission.php';
requireRole(['supplier', 'admin', 'super_admin']);

$db         = getDB();
$supplierId = (int)$_SESSION['user_id'];

// Stats
$stats = getCommissionStats($supplierId);

// Load tier config dynamically for the info box
$tierConfig = getCommissionTierConfig();

// Pagination + filters
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$filters = [];
if (!empty($_GET['from'])) $filters['from'] = $_GET['from'];
if (!empty($_GET['to']))   $filters['to']   = $_GET['to'];

$logs = getCommissionLogs($supplierId, $filters, $page, $perPage);

// Monthly summary (last 6 months)
$monthlySummary = [];
try {
    $stmt = $db->prepare(
        'SELECT DATE_FORMAT(created_at, "%Y-%m") AS mo,
                COUNT(*) AS orders,
                COALESCE(SUM(COALESCE(order_subtotal, order_amount, 0)), 0) AS order_total,
                COALESCE(SUM(commission_amount), 0) AS commission,
                COALESCE(SUM(COALESCE(net_amount, 0)), 0) AS net_earnings
         FROM commission_logs
         WHERE supplier_id = ?
           AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
         GROUP BY mo
         ORDER BY mo DESC'
    );
    $stmt->execute([$supplierId]);
    $monthlySummary = $stmt->fetchAll();
} catch (PDOException $e) { /* ignore */ }

$effectiveRatePct = round($stats['effective_rate'] * 100, 2);
$baseRatePct      = round($stats['base_rate'] * 100, 2);
$planDiscountPct  = round($stats['plan_discount'] * 100, 2);

$pageTitle = 'Commission History';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-percent text-primary me-2"></i>Commission History</h3>
        <a href="/pages/supplier/earnings.php" class="btn btn-outline-secondary btn-sm">← Earnings</a>
    </div>

    <!-- Tier info banner -->
    <div class="alert alert-primary d-flex align-items-start gap-3 mb-4">
        <i class="bi bi-award-fill fs-3 flex-shrink-0 mt-1"></i>
        <div>
            <strong>You are in the <?= e($stats['current_tier']) ?> tier</strong>
            — Base commission rate: <strong><?= $baseRatePct ?>%</strong>
            <?php if ($planDiscountPct > 0): ?>
            · Plan discount: <strong>-<?= $planDiscountPct ?>%</strong>
            · Effective rate: <strong><?= $effectiveRatePct ?>%</strong>
            <?php endif; ?>
            <br>
            <small class="text-muted">
                Your 90-day GMV: <strong>$<?= number_format($stats['gmv_90d'], 2) ?></strong>
            </small>
        </div>
    </div>

    <!-- Stats cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Total Commission Paid</div>
                    <div class="fw-bold fs-5 text-danger">$<?= number_format($stats['total_commission_paid'], 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">This Month</div>
                    <div class="fw-bold fs-5">$<?= number_format($stats['this_month'], 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Last Month</div>
                    <div class="fw-bold fs-5">$<?= number_format($stats['last_month'], 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Effective Rate</div>
                    <div class="fw-bold fs-5"><?= $effectiveRatePct ?>%</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly summary table -->
    <?php if (!empty($monthlySummary)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent fw-semibold">
            <i class="bi bi-calendar3 text-primary me-2"></i>Monthly Summary
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Month</th>
                        <th class="text-end">Orders</th>
                        <th class="text-end">Order Total</th>
                        <th class="text-end">Commission</th>
                        <th class="text-end">Net Earnings</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($monthlySummary as $row): ?>
                <tr>
                    <td><?= e($row['mo']) ?></td>
                    <td class="text-end"><?= (int)$row['orders'] ?></td>
                    <td class="text-end">$<?= number_format((float)$row['order_total'], 2) ?></td>
                    <td class="text-end text-danger">-$<?= number_format((float)$row['commission'], 2) ?></td>
                    <td class="text-end fw-semibold text-success">$<?= number_format((float)$row['net_earnings'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <form method="GET" class="card border-0 shadow-sm p-3 mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">From</label>
                <input type="date" name="from" class="form-control form-control-sm"
                       value="<?= e($filters['from'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">To</label>
                <input type="date" name="to" class="form-control form-control-sm"
                       value="<?= e($filters['to'] ?? '') ?>">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filter</button>
                <a href="?" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </div>
    </form>

    <!-- Commission log table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold">
            <i class="bi bi-list-ul text-primary me-2"></i>Commission Log
            <span class="badge bg-secondary ms-2"><?= number_format($logs['total']) ?></span>
        </div>
        <?php if (empty($logs['data'])): ?>
        <div class="card-body text-center text-muted py-5">No commission records found.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Order #</th>
                        <th class="text-end">Order Total</th>
                        <th class="text-end">Rate</th>
                        <th class="text-end">Commission</th>
                        <th class="text-end">Net Earnings</th>
                        <th>Tier</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs['data'] as $log): ?>
                <?php
                    $subtotal   = (float)($log['order_subtotal'] ?? $log['order_amount'] ?? 0);
                    $commAmt    = (float)$log['commission_amount'];
                    $netAmt     = isset($log['net_amount']) ? (float)$log['net_amount'] : ($subtotal - $commAmt);
                    $finalRate  = isset($log['final_rate'])
                        ? round((float)$log['final_rate'] * 100, 2)
                        : (float)($log['commission_rate'] ?? 0);
                    $tier       = $log['gmv_tier'] ?? $log['tier'] ?? '—';
                ?>
                <tr>
                    <td><?= formatDate($log['created_at']) ?></td>
                    <td>
                        <a href="/pages/account/orders/detail.php?id=<?= (int)$log['order_id'] ?>">
                            #<?= (int)$log['order_id'] ?>
                        </a>
                    </td>
                    <td class="text-end">$<?= number_format($subtotal, 2) ?></td>
                    <td class="text-end"><?= $finalRate ?>%</td>
                    <td class="text-end text-danger">-$<?= number_format($commAmt, 2) ?></td>
                    <td class="text-end fw-semibold text-success">$<?= number_format($netAmt, 2) ?></td>
                    <td><span class="badge bg-secondary"><?= e($tier) ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <?php if ($logs['pages'] > 1): ?>
        <div class="card-footer bg-light d-flex justify-content-center">
            <nav><ul class="pagination pagination-sm mb-0">
                <?php for ($i = 1; $i <= min($logs['pages'], 10); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link"
                       href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
            </ul></nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- How to reduce commission info box -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-transparent fw-semibold">
            <i class="bi bi-lightbulb text-warning me-2"></i>How to Reduce Your Commission Rate
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <h6 class="fw-bold"><i class="bi bi-graph-up-arrow text-success me-2"></i>Increase Your GMV</h6>
                    <p class="text-muted small mb-0">
                        Your commission tier is based on your 90-day rolling Gross Merchandise Value (GMV).
                        Higher sales volumes unlock lower commission rates:
                    </p>
                    <ul class="small text-muted mt-2 mb-0">
                        <?php foreach ($tierConfig as $tc): ?>
                        <li>
                            <?= e($tc['tier_name']) ?>
                            ($<?= number_format((float)$tc['min_gmv']) ?>
                            <?= $tc['max_gmv'] !== null ? '–$' . number_format((float)$tc['max_gmv']) : '+'?> GMV):
                            <strong><?= round((float)$tc['base_rate'] * 100, 2) ?>%</strong>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6 class="fw-bold"><i class="bi bi-star text-primary me-2"></i>Upgrade Your Plan</h6>
                    <p class="text-muted small mb-0">
                        Upgrading your subscription plan gives you a discount on top of your tier rate:
                    </p>
                    <ul class="small text-muted mt-2 mb-0">
                        <li>Free plan: <strong>0% discount</strong></li>
                        <li>Pro plan ($299/mo): <strong>-15% on commission</strong></li>
                        <li>Enterprise plan ($999/mo): <strong>-30% on commission</strong></li>
                    </ul>
                    <a href="/pages/supplier/plan-upgrade.php" class="btn btn-primary btn-sm mt-3">
                        <i class="bi bi-arrow-up-circle me-1"></i>Upgrade Plan
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
