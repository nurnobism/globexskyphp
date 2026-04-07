<?php
/**
 * pages/admin/plans/index.php — Admin Plan Configuration (PR #9)
 *
 * View/edit plan details: name, price, limits, features
 * View subscriber count per plan
 * Revenue from plans this month
 */

require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/plans.php';
requireRole(['admin', 'super_admin']);

$db    = getDB();
$plans = getPlans();

// Enrich with subscriber counts and monthly revenue
foreach ($plans as &$p) {
    $p['subscriber_count'] = 0;
    $p['monthly_revenue']  = 0.0;
    try {
        $cStmt = $db->prepare(
            'SELECT COUNT(*) FROM plan_subscriptions WHERE plan_id = ? AND status = "active"'
        );
        $cStmt->execute([$p['id']]);
        $p['subscriber_count'] = (int)$cStmt->fetchColumn();

        $rStmt = $db->prepare(
            "SELECT COALESCE(SUM(pi.amount),0)
             FROM plan_invoices pi
             WHERE pi.subscription_id IN (
                 SELECT id FROM plan_subscriptions WHERE plan_id = ?
             )
             AND pi.status = 'paid'
             AND pi.created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')"
        );
        $rStmt->execute([$p['id']]);
        $p['monthly_revenue'] = (float)$rStmt->fetchColumn();
    } catch (PDOException $e) { /* ignore */ }
}
unset($p);

// Overall stats
$totalSubs    = 0;
$totalRevenue = 0.0;
$totalMRR     = 0.0;
foreach ($plans as $p) {
    $totalSubs    += $p['subscriber_count'];
    $totalRevenue += $p['monthly_revenue'];
    $totalMRR     += $p['subscriber_count'] * (float)($p['price_monthly'] ?? $p['price'] ?? 0);
}

$pageTitle = 'Plan Management';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0"><i class="bi bi-diagram-3 me-2"></i>Supplier Plans</h2>
        <a href="/pages/admin/plans/subscribers.php" class="btn btn-outline-primary">
            <i class="bi bi-people me-1"></i>View Subscribers
        </a>
    </div>

    <!-- Stats Row -->
    <div class="row g-4 mb-5">
        <div class="col-sm-6 col-xl-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-4">
                    <i class="bi bi-people fs-1 text-primary mb-2"></i>
                    <h3 class="fw-bold"><?= number_format($totalSubs) ?></h3>
                    <div class="text-muted">Active Subscribers</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-4">
                    <i class="bi bi-currency-dollar fs-1 text-success mb-2"></i>
                    <h3 class="fw-bold">$<?= number_format($totalRevenue, 0) ?></h3>
                    <div class="text-muted">Revenue This Month</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-4">
                    <i class="bi bi-graph-up fs-1 text-info mb-2"></i>
                    <h3 class="fw-bold">$<?= number_format($totalMRR, 0) ?></h3>
                    <div class="text-muted">MRR (Monthly Recurring)</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-4">
                    <i class="bi bi-layers fs-1 text-warning mb-2"></i>
                    <h3 class="fw-bold"><?= count($plans) ?></h3>
                    <div class="text-muted">Active Plans</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Plan Cards -->
    <div class="row g-4 mb-5">
        <?php foreach ($plans as $plan):
            $icons = ['free' => 'bi-shop', 'pro' => 'bi-star-fill', 'enterprise' => 'bi-gem'];
            $colors = ['free' => 'secondary', 'pro' => 'primary', 'enterprise' => 'warning'];
            $icon  = $icons[$plan['slug']]  ?? 'bi-tag';
            $color = $colors[$plan['slug']] ?? 'info';
        ?>
        <div class="col-md-4">
            <div class="card h-100 border-<?= $color ?> border-2">
                <div class="card-header bg-<?= $color ?> <?= $color === 'warning' ? 'text-dark' : 'text-white' ?> d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi <?= $icon ?> me-2"></i><?= htmlspecialchars($plan['name']) ?></h5>
                    <span class="badge bg-light <?= $color === 'warning' ? 'text-dark' : 'text-' . $color ?>">
                        <?= $plan['subscriber_count'] ?> subs
                    </span>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <td class="text-muted">Monthly Price</td>
                            <td class="fw-semibold">$<?= number_format((float)($plan['price_monthly'] ?? $plan['price'] ?? 0), 0) ?>/mo</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Quarterly</td>
                            <td>$<?= number_format((float)($plan['price_quarterly'] ?? 0), 2) ?>/mo</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Semi-Annual</td>
                            <td>$<?= number_format((float)($plan['price_semi_annual'] ?? 0), 2) ?>/mo</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Annual</td>
                            <td>$<?= number_format((float)($plan['price_annual'] ?? 0), 2) ?>/mo</td>
                        </tr>
                        <tr><td colspan="2"><hr class="my-1"></td></tr>
                        <tr>
                            <td class="text-muted">Products</td>
                            <td><?= (int)($plan['max_products'] ?? 10) < 0 ? '<span class="text-success fw-bold">Unlimited</span>' : number_format((int)($plan['max_products'] ?? 10)) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Images/Product</td>
                            <td><?= (int)($plan['max_images_per_product'] ?? 3) < 0 ? '∞' : (int)($plan['max_images_per_product'] ?? 3) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Shipping Templates</td>
                            <td><?= (int)($plan['max_shipping_templates'] ?? 1) < 0 ? '∞' : (int)($plan['max_shipping_templates'] ?? 1) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Dropship Imports</td>
                            <td><?= (int)($plan['max_dropship_imports'] ?? 0) < 0 ? '∞' : (int)($plan['max_dropship_imports'] ?? 0) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Commission Discount</td>
                            <td class="text-success fw-bold"><?= (float)($plan['commission_discount'] ?? 0) ?>%</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Revenue (this mo.)</td>
                            <td class="text-success fw-bold">$<?= number_format($plan['monthly_revenue'], 2) ?></td>
                        </tr>
                    </table>
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="/pages/admin/plans/subscribers.php?plan_id=<?= $plan['id'] ?>"
                       class="btn btn-outline-<?= $color ?> btn-sm flex-grow-1">
                        <i class="bi bi-people me-1"></i>Subscribers
                    </a>
                    <button class="btn btn-outline-secondary btn-sm"
                            data-bs-toggle="modal"
                            data-bs-target="#editPlanModal"
                            data-plan='<?= htmlspecialchars(json_encode([
                                'id'                     => $plan['id'],
                                'name'                   => $plan['name'],
                                'price_monthly'          => $plan['price_monthly'] ?? $plan['price'] ?? 0,
                                'price_quarterly'        => $plan['price_quarterly']   ?? 0,
                                'price_semi_annual'      => $plan['price_semi_annual'] ?? 0,
                                'price_annual'           => $plan['price_annual']      ?? 0,
                                'commission_discount'    => $plan['commission_discount'] ?? 0,
                                'max_products'           => $plan['max_products']           ?? 10,
                                'max_images_per_product' => $plan['max_images_per_product'] ?? 3,
                                'max_shipping_templates' => $plan['max_shipping_templates'] ?? 1,
                                'max_dropship_imports'   => $plan['max_dropship_imports']   ?? 0,
                            ]), ENT_QUOTES) ?>'>
                        <i class="bi bi-pencil"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Subscribers Overview Table -->
    <div class="card">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-table me-2"></i>Recent Subscribers</h5>
            <a href="/pages/admin/plans/subscribers.php" class="btn btn-sm btn-outline-primary">View All</a>
        </div>
        <?php
        $recentSubs = [];
        try {
            $stmt = $db->query(
                "SELECT ps.*, sp.name AS plan_name, sp.price_monthly AS plan_price,
                        u.email, CONCAT(u.first_name,' ',u.last_name) AS supplier_name
                 FROM plan_subscriptions ps
                 JOIN supplier_plans sp ON sp.id = ps.plan_id
                 JOIN users u ON u.id = ps.supplier_id
                 ORDER BY ps.created_at DESC
                 LIMIT 10"
            );
            $recentSubs = $stmt->fetchAll();
        } catch (PDOException $e) { /* ignore */ }
        ?>
        <?php if ($recentSubs): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Supplier</th>
                        <th>Plan</th>
                        <th>Duration</th>
                        <th>Start Date</th>
                        <th>Next Billing</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentSubs as $sub): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars(trim($sub['supplier_name'] ?? '')) ?></div>
                            <div class="text-muted small"><?= htmlspecialchars($sub['email'] ?? '') ?></div>
                        </td>
                        <td><?= htmlspecialchars($sub['plan_name'] ?? '') ?></td>
                        <td class="text-capitalize"><?= htmlspecialchars($sub['duration'] ?? 'monthly') ?></td>
                        <td><?= $sub['starts_at'] ? htmlspecialchars(date('M j, Y', strtotime($sub['starts_at']))) : '—' ?></td>
                        <td><?= $sub['ends_at'] ? htmlspecialchars(date('M j, Y', strtotime($sub['ends_at']))) : '—' ?></td>
                        <td>
                            <?php
                            echo match($sub['status'] ?? '') {
                                'active'   => '<span class="badge bg-success">Active</span>',
                                'trialing' => '<span class="badge bg-info">Trialing</span>',
                                'past_due' => '<span class="badge bg-warning text-dark">Past Due</span>',
                                'cancelled'=> '<span class="badge bg-danger">Cancelled</span>',
                                default    => '<span class="badge bg-secondary">' . htmlspecialchars($sub['status'] ?? '?') . '</span>',
                            };
                            ?>
                        </td>
                        <td>
                            <a href="/pages/admin/plans/subscribers.php?supplier_id=<?= (int)$sub['supplier_id'] ?>"
                               class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="card-body text-center text-muted py-4">
            No subscribers yet.
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Plan Modal -->
<div class="modal fade" id="editPlanModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/api/admin.php?action=update_plan">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                <input type="hidden" name="plan_id"    id="editPlanId">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Plan Name</label>
                            <input type="text" class="form-control" name="name" id="editPlanName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Commission Discount (%)</label>
                            <input type="number" class="form-control" name="commission_discount"
                                   id="editPlanCommDisc" min="0" max="100" step="0.01">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Monthly Price ($)</label>
                            <input type="number" class="form-control" name="price_monthly"
                                   id="editPriceMonthly" min="0" step="0.01">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Quarterly ($/mo)</label>
                            <input type="number" class="form-control" name="price_quarterly"
                                   id="editPriceQuarterly" min="0" step="0.01">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Semi-Annual ($/mo)</label>
                            <input type="number" class="form-control" name="price_semi_annual"
                                   id="editPriceSemiAnnual" min="0" step="0.01">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Annual ($/mo)</label>
                            <input type="number" class="form-control" name="price_annual"
                                   id="editPriceAnnual" min="0" step="0.01">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Max Products</label>
                            <input type="number" class="form-control" name="max_products"
                                   id="editMaxProducts" min="-1" title="-1 = unlimited">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Max Images/Product</label>
                            <input type="number" class="form-control" name="max_images_per_product"
                                   id="editMaxImages" min="-1">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Max Shipping Templates</label>
                            <input type="number" class="form-control" name="max_shipping_templates"
                                   id="editMaxShipping" min="-1">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Max Dropship Imports</label>
                            <input type="number" class="form-control" name="max_dropship_imports"
                                   id="editMaxDropship" min="-1">
                        </div>
                    </div>
                    <p class="text-muted small mt-2 mb-0">Use <strong>-1</strong> for unlimited.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('editPlanModal').addEventListener('show.bs.modal', function (e) {
    const btn  = e.relatedTarget;
    const plan = JSON.parse(btn.dataset.plan || '{}');
    document.getElementById('editPlanId').value          = plan.id                    || '';
    document.getElementById('editPlanName').value        = plan.name                  || '';
    document.getElementById('editPlanCommDisc').value    = plan.commission_discount   || 0;
    document.getElementById('editPriceMonthly').value    = plan.price_monthly         || 0;
    document.getElementById('editPriceQuarterly').value  = plan.price_quarterly       || 0;
    document.getElementById('editPriceSemiAnnual').value = plan.price_semi_annual     || 0;
    document.getElementById('editPriceAnnual').value     = plan.price_annual          || 0;
    document.getElementById('editMaxProducts').value     = plan.max_products          ?? 10;
    document.getElementById('editMaxImages').value       = plan.max_images_per_product ?? 3;
    document.getElementById('editMaxShipping').value     = plan.max_shipping_templates ?? 1;
    document.getElementById('editMaxDropship').value     = plan.max_dropship_imports  ?? 0;
});
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
