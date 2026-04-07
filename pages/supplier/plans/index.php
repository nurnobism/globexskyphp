<?php
/**
 * pages/supplier/plans/index.php — Plan Selection & Comparison (PR #9)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/plans.php';
requireRole(['supplier', 'admin', 'super_admin']);

$db          = getDB();
$supplierId  = $_SESSION['user_id'];
$currentPlan = getSupplierActivePlan($supplierId);
$allPlans    = getPlans();

// Billing period selector
$billingPeriod = in_array($_GET['billing'] ?? '', ['monthly','quarterly','semi_annual','annual'])
    ? $_GET['billing']
    : 'monthly';

$pageTitle = 'Supplier Plans';
include __DIR__ . '/../../../includes/header.php';

function planLimitLabel(mixed $val): string
{
    if ($val === -1 || $val === '-1' || $val === 'unlimited' || $val === 'Unlimited') return '<span class="text-success fw-semibold">Unlimited</span>';
    if ($val === false || $val === null || $val === 0 || $val === '0') return '<span class="text-danger">❌</span>';
    if ($val === true || $val === 1 || $val === '1') return '<span class="text-success">✅</span>';
    if (is_string($val) && in_array(strtolower($val), ['basic', 'full'])) {
        return '<span class="text-success">✅ ' . ucfirst($val) . '</span>';
    }
    return '<strong>' . htmlspecialchars((string)$val) . '</strong>';
}

$discountPct = getDurationDiscount($billingPeriod);
?>
<div class="container py-5">
    <div class="text-center mb-5">
        <h2 class="fw-bold">Choose Your Supplier Plan</h2>
        <p class="text-muted">Scale your business — upgrade or downgrade anytime</p>
        <?php if (!empty($currentPlan['name']) && $currentPlan['name'] !== 'Free'): ?>
        <span class="badge bg-primary fs-6 mb-3">
            <i class="bi bi-check-circle me-1"></i> Current Plan: <strong><?= e($currentPlan['name']) ?></strong>
        </span>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i><?= e($_GET['success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle me-2"></i><?= e($_GET['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Billing period selector -->
    <div class="d-flex justify-content-center mb-5">
        <div class="btn-group" role="group">
            <?php
            $periods = [
                'monthly'    => 'Monthly',
                'quarterly'  => 'Quarterly <span class="badge bg-success ms-1">-10%</span>',
                'semi_annual'=> 'Semi-Annual <span class="badge bg-success ms-1">-15%</span>',
                'annual'     => 'Annual <span class="badge bg-success ms-1">-25%</span>',
            ];
            foreach ($periods as $key => $label):
                $active = $billingPeriod === $key ? 'active' : '';
            ?>
            <a href="?billing=<?= $key ?>"
               class="btn btn-outline-primary <?= $active ?>">
                <?= $label ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Plan cards -->
    <div class="row g-4 justify-content-center mb-5">
        <?php foreach ($allPlans as $plan):
            $isCurrent   = ($currentPlan['slug'] ?? 'free') === $plan['slug'];
            $lim         = $plan['limits_decoded'] ?? [];
            $feat        = $plan['features_decoded'] ?? [];
            $basePrice   = (float)($plan['price'] ?? 0);
            $discount    = getDurationDiscount($billingPeriod);
            $displayPrice = $basePrice > 0 ? round($basePrice * (1 - $discount / 100), 2) : 0;

            $cardClass = $isCurrent ? 'border-primary border-3 shadow-lg' : 'border-0 shadow-sm';
            $badgeHtml = match ($feat['badge'] ?? 'none') {
                'pro'        => '<span class="badge bg-primary ms-2"><i class="bi bi-star-fill me-1"></i>Pro</span>',
                'enterprise' => '<span class="badge bg-warning text-dark ms-2"><i class="bi bi-gem me-1"></i>Enterprise</span>',
                default      => '',
            };

            $currentSlug  = $currentPlan['slug'] ?? 'free';
            $planOrder    = ['free' => 0, 'pro' => 1, 'enterprise' => 2];
            $isUpgrade    = ($planOrder[$plan['slug']] ?? 0) > ($planOrder[$currentSlug] ?? 0);
            $isDowngrade  = ($planOrder[$plan['slug']] ?? 0) < ($planOrder[$currentSlug] ?? 0);
        ?>
        <div class="col-md-4">
            <div class="card <?= $cardClass ?> h-100">
                <?php if ($isCurrent): ?>
                <div class="card-header bg-primary text-white text-center py-2 small fw-semibold">
                    <i class="bi bi-check-circle me-1"></i> Your Current Plan
                </div>
                <?php elseif ($plan['slug'] === 'pro'): ?>
                <div class="card-header bg-light text-center py-2 small fw-semibold text-primary">
                    ⭐ Most Popular
                </div>
                <?php endif; ?>
                <div class="card-body p-4 d-flex flex-column">
                    <h4 class="fw-bold mb-1"><?= e($plan['name']) ?><?= $badgeHtml ?></h4>
                    <div class="mb-3">
                        <?php if ($displayPrice > 0): ?>
                        <span class="display-6 fw-bold">$<?= number_format($displayPrice, 0) ?></span>
                        <span class="text-muted">/mo</span>
                        <?php if ($discount > 0): ?>
                        <div class="small text-muted text-decoration-line-through">$<?= number_format($basePrice, 0) ?>/mo</div>
                        <div class="small text-success">Save <?= $discount ?>% with <?= ucfirst(str_replace('_', '-', $billingPeriod)) ?> billing</div>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="display-6 fw-bold text-success">Free</span>
                        <span class="text-muted"> forever</span>
                        <?php endif; ?>
                    </div>

                    <ul class="list-unstyled mb-4 flex-grow-1">
                        <?php
                        $productLimit = $lim['products'] ?? 10;
                        $imgLimit     = $lim['images_per_product'] ?? 3;
                        $dropship     = $lim['dropshipping'] ?? false;
                        $livestream   = $lim['livestream_per_week'] ?? 0;
                        $apiAccess    = $lim['api_access'] ?? false;
                        $support      = $feat['support'] ?? 'community';

                        $features = [
                            '<i class="bi bi-box-seam text-primary me-2"></i>' . ($productLimit < 0 ? 'Unlimited products' : $productLimit . ' products'),
                            '<i class="bi bi-images text-primary me-2"></i>' . $imgLimit . ' images per product',
                            '<i class="bi bi-truck text-primary me-2"></i>Dropshipping: ' . ($dropship ? ($productLimit < 0 ? 'Unlimited' : '100 products') : 'Not available'),
                            '<i class="bi bi-camera-video text-primary me-2"></i>Livestream: ' . ($livestream < 0 ? 'Unlimited' : ($livestream === 0 ? 'Not available' : $livestream . '/week')),
                            '<i class="bi bi-code-slash text-primary me-2"></i>API access: ' . ($apiAccess ? ucfirst($apiAccess) : 'Not available'),
                            '<i class="bi bi-percent text-primary me-2"></i>' . ($plan['commission_discount'] ?? 0) . '% commission discount',
                            '<i class="bi bi-headset text-primary me-2"></i>Support: ' . ucwords(str_replace('_', ' ', $support)),
                        ];
                        foreach ($features as $f): ?>
                        <li class="mb-2 small"><?= $f ?></li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if ($isCurrent): ?>
                    <button class="btn btn-primary w-100" disabled>
                        <i class="bi bi-check-circle me-1"></i> Current Plan
                    </button>
                    <?php elseif ($isUpgrade): ?>
                    <a href="/pages/supplier/plans/upgrade.php?plan=<?= urlencode($plan['slug']) ?>&billing=<?= urlencode($billingPeriod) ?>"
                       class="btn btn-success w-100">
                        <i class="bi bi-arrow-up-circle me-1"></i> Upgrade to <?= e($plan['name']) ?>
                    </a>
                    <?php else: ?>
                    <form method="POST" action="/api/plans.php?action=downgrade">
                        <?= csrfField() ?>
                        <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
                        <button type="submit" class="btn btn-outline-secondary w-100"
                                onclick="return confirm('Downgrade to <?= e($plan['name']) ?> at end of billing period?')">
                            <i class="bi bi-arrow-down-circle me-1"></i> Downgrade to <?= e($plan['name']) ?>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Feature comparison table -->
    <div class="card border-0 shadow-sm mb-5">
        <div class="card-header bg-light fw-semibold">
            <i class="bi bi-table me-2 text-primary"></i>Full Feature Comparison
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Feature</th>
                            <?php foreach ($allPlans as $p): ?>
                            <th class="text-center <?= ($currentPlan['slug'] ?? 'free') === $p['slug'] ? 'table-primary' : '' ?>">
                                <?= e($p['name']) ?>
                                <?= ($currentPlan['slug'] ?? 'free') === $p['slug'] ? ' <span class="badge bg-primary">Current</span>' : '' ?>
                            </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rows = [
                            ['label' => 'Products',              'key' => 'products'],
                            ['label' => 'Images/product',        'key' => 'images_per_product'],
                            ['label' => 'Dropshipping',          'key' => 'dropshipping'],
                            ['label' => 'Livestream/week',       'key' => 'livestream_per_week'],
                            ['label' => 'API access',            'key' => 'api_access'],
                            ['label' => 'Commission discount',   'key' => 'commission_discount_pct'],
                            ['label' => 'Analytics',             'key' => 'analytics', 'source' => 'features'],
                            ['label' => 'Support',               'key' => 'support',   'source' => 'features'],
                        ];
                        foreach ($rows as $row):
                        ?>
                        <tr>
                            <td class="ps-4 fw-semibold text-muted small"><?= $row['label'] ?></td>
                            <?php foreach ($allPlans as $p):
                                $lim  = $p['limits_decoded'] ?? [];
                                $feat = $p['features_decoded'] ?? [];
                                $val  = '';
                                if (($row['source'] ?? 'limits') === 'features') {
                                    $val = $feat[$row['key']] ?? '';
                                    $val = $val ? ucwords(str_replace('_', ' ', $val)) : '—';
                                    echo '<td class="text-center small">' . e($val) . '</td>';
                                } elseif ($row['key'] === 'commission_discount_pct') {
                                    $cd = (float)($p['commission_discount'] ?? 0);
                                    echo '<td class="text-center small">' . ($cd > 0 ? '<span class="text-success fw-semibold">-' . $cd . '%</span>' : '—') . '</td>';
                                } else {
                                    $val = $lim[$row['key']] ?? false;
                                    echo '<td class="text-center small">' . planLimitLabel($val) . '</td>';
                                }
                            endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                        <!-- Pricing row -->
                        <tr class="table-light">
                            <td class="ps-4 fw-bold">Price/month</td>
                            <?php foreach ($allPlans as $p):
                                $basePrice   = (float)($p['price'] ?? 0);
                                $displayPrice = $basePrice > 0 ? round($basePrice * (1 - $discountPct / 100), 2) : 0;
                            ?>
                            <td class="text-center fw-bold">
                                <?= $displayPrice > 0 ? '$' . number_format($displayPrice, 0) : 'Free' ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- FAQ -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light fw-semibold">
            <i class="bi bi-question-circle me-2 text-primary"></i>Frequently Asked Questions
        </div>
        <div class="card-body">
            <div class="accordion accordion-flush" id="faqAccordion">
                <?php
                $faqs = [
                    ['q' => 'When will I be charged?',
                     'a' => 'You will be charged immediately upon upgrading. For billing periods longer than a month, a discount is applied to the total upfront charge.'],
                    ['q' => 'Can I upgrade mid-month?',
                     'a' => 'Yes. When you upgrade, a prorated credit from your current plan is applied to the new plan cost, so you only pay the difference.'],
                    ['q' => 'What happens when I downgrade?',
                     'a' => 'Downgrades take effect at the end of your current billing period. You retain your current plan access until then.'],
                    ['q' => 'What happens if I reach my product limit?',
                     'a' => 'You will not be able to add new products until you upgrade your plan or archive existing products.'],
                    ['q' => 'Can I cancel anytime?',
                     'a' => 'Yes. Cancelling downgrades you to the Free plan at the end of your current billing period. No further charges will occur.'],
                    ['q' => 'How does the commission discount work?',
                     'a' => 'Pro and Enterprise plans receive a discount on the platform commission charged per order. Pro saves 15%, Enterprise saves 30%.'],
                ];
                foreach ($faqs as $i => $faq): ?>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button"
                                data-bs-toggle="collapse" data-bs-target="#faq<?= $i ?>">
                            <?= e($faq['q']) ?>
                        </button>
                    </h2>
                    <div id="faq<?= $i ?>" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-muted">
                            <?= e($faq['a']) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
