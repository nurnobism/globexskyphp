<?php
/**
 * pages/supplier/plans.php — Supplier Plan Selection Page (PR #9)
 *
 * Beautiful pricing page with:
 *  - 3-column plan cards (Free / Pro / Enterprise)
 *  - Duration toggle (Monthly / Quarterly / Semi-Annual / Annual)
 *  - Feature comparison table
 *  - Current plan badge + expiry
 *  - FAQ section
 */

require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/plans.php';
requireRole(['supplier', 'admin', 'super_admin']);

$supplierId  = (int)$_SESSION['user_id'];
$currentPlan = getCurrentPlan($supplierId);
$usage       = getPlanUsage($supplierId);
$expiry      = getPlanExpiry($supplierId);
$plans       = getPlans();

$pageTitle = 'Supplier Plans';
include __DIR__ . '/../../includes/header.php';

function fmtPrice(float $price, string $duration = 'monthly'): string {
    if ($price == 0) return '$0';
    $months = PLAN_DURATION_MONTHS[$duration] ?? 1;
    $total  = round($price * $months, 2);
    return '$' . number_format($price, 0) . '/mo';
}

function limitLabel(mixed $val): string {
    if ($val === -1 || $val === '-1')                     return '<span class="text-success fw-bold">Unlimited</span>';
    if ($val === false || $val === null || $val === 0)     return '<span class="text-muted">—</span>';
    if ($val === true || $val === 1)                       return '<span class="text-success">✓</span>';
    if (is_string($val) && in_array(strtolower($val), ['basic', 'full', 'advanced'])) {
        return '<span class="text-success">✓ ' . ucfirst($val) . '</span>';
    }
    return '<span class="fw-semibold">' . htmlspecialchars((string)$val) . '</span>';
}
?>
<div class="container py-5">

    <!-- Header -->
    <div class="text-center mb-5">
        <h2 class="fw-bold display-6">Choose Your Supplier Plan</h2>
        <p class="lead text-muted">Scale your business with the right tools — upgrade or downgrade anytime.</p>

        <?php if (!empty($currentPlan['name'])): ?>
        <div class="d-inline-flex align-items-center gap-2 bg-light border rounded px-4 py-2 mt-2">
            <i class="bi bi-check-circle-fill text-success"></i>
            <span>Current plan: <strong><?= htmlspecialchars($currentPlan['name']) ?></strong></span>
            <?php if ($expiry): ?>
            <span class="text-muted small">· expires <?= htmlspecialchars(date('M j, Y', strtotime($expiry))) ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Duration Toggle -->
    <div class="d-flex justify-content-center mb-5">
        <div class="btn-group" role="group" id="durationToggle">
            <input type="radio" class="btn-check" name="duration" id="dur-monthly"     value="monthly"     checked autocomplete="off">
            <label class="btn btn-outline-primary" for="dur-monthly">Monthly</label>

            <input type="radio" class="btn-check" name="duration" id="dur-quarterly"   value="quarterly"   autocomplete="off">
            <label class="btn btn-outline-primary" for="dur-quarterly">
                Quarterly <span class="badge bg-success ms-1">–10%</span>
            </label>

            <input type="radio" class="btn-check" name="duration" id="dur-semi-annual" value="semi-annual" autocomplete="off">
            <label class="btn btn-outline-primary" for="dur-semi-annual">
                6 Months <span class="badge bg-success ms-1">–15%</span>
            </label>

            <input type="radio" class="btn-check" name="duration" id="dur-annual"      value="annual"      autocomplete="off">
            <label class="btn btn-outline-primary" for="dur-annual">
                Annual <span class="badge bg-warning text-dark ms-1">–25%</span>
            </label>
        </div>
    </div>

    <!-- Plan Cards -->
    <div class="row g-4 mb-5">
        <?php foreach ($plans as $plan):
            $isCurrent = ($currentPlan['slug'] ?? '') === $plan['slug'];
            $isPro     = $plan['slug'] === 'pro';
        ?>
        <div class="col-md-4">
            <div class="card h-100 border<?= $isPro ? ' border-primary border-2 shadow-lg' : '' ?> position-relative">
                <?php if ($isPro): ?>
                <div class="position-absolute top-0 start-50 translate-middle">
                    <span class="badge bg-primary px-3 py-2 fs-6">⭐ Most Popular</span>
                </div>
                <?php endif; ?>

                <div class="card-header text-center py-4 <?= $isPro ? 'bg-primary text-white' : 'bg-light' ?>">
                    <?php if ($plan['slug'] === 'enterprise'): ?>
                        <i class="bi bi-gem fs-1 text-warning"></i>
                    <?php elseif ($isPro): ?>
                        <i class="bi bi-star-fill fs-1 text-white"></i>
                    <?php else: ?>
                        <i class="bi bi-shop fs-1 text-secondary"></i>
                    <?php endif; ?>
                    <h4 class="fw-bold mt-2 mb-0"><?= htmlspecialchars($plan['name']) ?></h4>
                </div>

                <div class="card-body text-center">
                    <!-- Price display (updated by JS) -->
                    <div class="my-3">
                        <span class="display-5 fw-bold"
                              data-plan-prices='<?= htmlspecialchars(json_encode([
                                  "monthly"     => '$' . number_format((float)($plan['price_monthly']    ?? $plan['price'] ?? 0), 0),
                                  "quarterly"   => '$' . number_format((float)($plan['price_quarterly']  ?? 0), 0),
                                  "semi-annual" => '$' . number_format((float)($plan['price_semi_annual']?? 0), 0),
                                  "annual"      => '$' . number_format((float)($plan['price_annual']     ?? 0), 0),
                              ]), ENT_QUOTES) ?>'
                              id="price-<?= $plan['id'] ?>">
                            $<?= number_format((float)($plan['price_monthly'] ?? $plan['price'] ?? 0), 0) ?>
                        </span>
                        <span class="text-muted">/mo</span>
                    </div>

                    <!-- Savings badge (shown when non-monthly) -->
                    <div id="savings-<?= $plan['id'] ?>" class="mb-2 d-none">
                        <span class="badge bg-success fs-6 px-3">
                            You save <span class="savings-pct"></span>% vs monthly
                        </span>
                    </div>

                    <!-- Features list -->
                    <ul class="list-unstyled text-start mt-3">
                        <?php
                        $maxProd  = (int)($plan['max_products']           ?? 10);
                        $maxImg   = (int)($plan['max_images_per_product'] ?? 3);
                        $maxShip  = (int)($plan['max_shipping_templates'] ?? 1);
                        $maxDrop  = (int)($plan['max_dropship_imports']   ?? 0);
                        $commDisc = (float)($plan['commission_discount']  ?? 0);
                        ?>
                        <li class="mb-2">
                            <i class="bi bi-box-seam text-primary me-2"></i>
                            <strong><?= $maxProd < 0 ? 'Unlimited' : number_format($maxProd) ?></strong> products
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-images text-primary me-2"></i>
                            <strong><?= $maxImg < 0 ? 'Unlimited' : $maxImg ?></strong> images/product
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-truck text-primary me-2"></i>
                            <strong><?= $maxShip < 0 ? 'Unlimited' : $maxShip ?></strong> shipping template<?= $maxShip !== 1 ? 's' : '' ?>
                        </li>
                        <?php if ($maxDrop > 0 || $maxDrop < 0): ?>
                        <li class="mb-2">
                            <i class="bi bi-arrow-left-right text-primary me-2"></i>
                            <strong><?= $maxDrop < 0 ? 'Unlimited' : number_format($maxDrop) ?></strong> dropship imports/mo
                        </li>
                        <?php endif; ?>
                        <?php if ($commDisc > 0): ?>
                        <li class="mb-2">
                            <i class="bi bi-percent text-success me-2"></i>
                            <strong><?= $commDisc ?>% off</strong> commission
                        </li>
                        <?php endif; ?>
                        <?php
                        $feat = $plan['features_decoded'] ?? [];
                        $analyticsLabel = ['basic' => 'Basic Analytics', 'advanced' => 'Advanced Analytics', 'full_ai' => 'Full AI Analytics'][$feat['analytics'] ?? 'basic'] ?? 'Analytics';
                        $supportLabel   = ['community' => 'Community Support', 'priority_email' => 'Priority Email', 'dedicated_phone_email' => 'Dedicated Support'][$feat['support'] ?? 'community'] ?? 'Support';
                        ?>
                        <li class="mb-2">
                            <i class="bi bi-bar-chart text-primary me-2"></i>
                            <?= htmlspecialchars($analyticsLabel) ?>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-headset text-primary me-2"></i>
                            <?= htmlspecialchars($supportLabel) ?>
                        </li>
                        <?php if (!empty($feat['api_access']) && $feat['api_access'] !== false): ?>
                        <li class="mb-2">
                            <i class="bi bi-code-slash text-primary me-2"></i>
                            API Access (<?= htmlspecialchars((string)$feat['api_access']) ?>)
                        </li>
                        <?php endif; ?>
                        <?php if (!empty($feat['dedicated_manager'])): ?>
                        <li class="mb-2">
                            <i class="bi bi-person-check text-primary me-2"></i>
                            Dedicated Account Manager
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="card-footer text-center py-3">
                    <?php if ($isCurrent): ?>
                        <button class="btn btn-secondary w-100" disabled>
                            <i class="bi bi-check-circle me-1"></i> Current Plan
                        </button>
                    <?php elseif ($plan['slug'] === 'free'): ?>
                        <button class="btn btn-outline-secondary w-100 plan-action-btn"
                                data-plan-id="<?= $plan['id'] ?>"
                                data-plan-slug="<?= $plan['slug'] ?>"
                                data-plan-name="<?= htmlspecialchars($plan['name']) ?>">
                            Downgrade to Free
                        </button>
                    <?php elseif ($plan['slug'] === 'enterprise'): ?>
                        <a href="/pages/contact.php?subject=Enterprise+Plan" class="btn btn-warning w-100">
                            <i class="bi bi-telephone me-1"></i> Contact Sales
                        </a>
                        <button class="btn btn-outline-warning w-100 mt-2 plan-action-btn"
                                data-plan-id="<?= $plan['id'] ?>"
                                data-plan-slug="<?= $plan['slug'] ?>"
                                data-plan-name="<?= htmlspecialchars($plan['name']) ?>">
                            Upgrade to Enterprise
                        </button>
                    <?php else: ?>
                        <button class="btn btn-primary w-100 plan-action-btn"
                                data-plan-id="<?= $plan['id'] ?>"
                                data-plan-slug="<?= $plan['slug'] ?>"
                                data-plan-name="<?= htmlspecialchars($plan['name']) ?>">
                            <?= ($currentPlan['id'] ?? 0) > ($plan['id'] ?? 0) ? 'Downgrade to ' : 'Upgrade to ' ?>
                            <?= htmlspecialchars($plan['name']) ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Feature Comparison Table -->
    <div class="card mb-5">
        <div class="card-header bg-light">
            <h5 class="mb-0 fw-bold"><i class="bi bi-table me-2"></i>Full Feature Comparison</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th width="40%">Feature</th>
                        <?php foreach ($plans as $p): ?>
                        <th class="text-center"><?= htmlspecialchars($p['name']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $featureRows = [
                        ['label' => 'Product Slots',          'key' => 'max_products',           'type' => 'limit'],
                        ['label' => 'Images per Product',     'key' => 'max_images_per_product', 'type' => 'limit'],
                        ['label' => 'Shipping Templates',     'key' => 'max_shipping_templates', 'type' => 'limit'],
                        ['label' => 'Dropship Imports/month', 'key' => 'max_dropship_imports',   'type' => 'limit'],
                        ['label' => 'Featured Listings/month','key' => 'max_featured_listings',  'type' => 'limit'],
                        ['label' => 'Livestreams/week',       'key' => 'max_livestreams',        'type' => 'limit'],
                        ['label' => 'Commission Discount',    'key' => 'commission_discount',    'type' => 'pct'],
                        ['label' => 'Analytics',              'key' => 'analytics',              'type' => 'feat'],
                        ['label' => 'Support',                'key' => 'support',                'type' => 'feat'],
                        ['label' => 'API Access',             'key' => 'api_access',             'type' => 'feat'],
                        ['label' => 'Custom Store',           'key' => 'custom_store',           'type' => 'feat'],
                        ['label' => 'Custom Domain',          'key' => 'custom_domain',          'type' => 'feat'],
                        ['label' => 'Dedicated Manager',      'key' => 'dedicated_manager',      'type' => 'feat'],
                        ['label' => 'Custom Integrations',    'key' => 'custom_integrations',    'type' => 'feat'],
                    ];
                    foreach ($featureRows as $row):
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($row['label']) ?></td>
                        <?php foreach ($plans as $p):
                            $val = '';
                            if ($row['type'] === 'limit') {
                                $v = (int)($p[$row['key']] ?? ($p['limits_decoded'][$row['key']] ?? 0));
                                $val = $v < 0 ? '<span class="text-success fw-bold">Unlimited</span>' : ($v === 0 ? '<span class="text-muted">—</span>' : '<span class="fw-semibold">' . number_format($v) . '</span>');
                            } elseif ($row['type'] === 'pct') {
                                $v = (float)($p['commission_discount'] ?? 0);
                                $val = $v > 0 ? '<span class="text-success fw-bold">-' . $v . '%</span>' : '<span class="text-muted">0%</span>';
                            } else {
                                $feat = $p['features_decoded'] ?? [];
                                $fv   = $feat[$row['key']] ?? false;
                                if ($fv === false || $fv === null) {
                                    $val = '<span class="text-muted">—</span>';
                                } elseif ($fv === true) {
                                    $val = '<span class="text-success"><i class="bi bi-check-lg"></i></span>';
                                } else {
                                    $labels = [
                                        'basic'       => 'Basic',
                                        'advanced'    => 'Advanced',
                                        'full_ai'     => 'Full AI',
                                        'community'   => 'Community',
                                        'priority_email' => 'Priority Email',
                                        'dedicated_phone_email' => 'Dedicated',
                                        'basic_api'   => 'Basic',
                                        'full'        => 'Full',
                                    ];
                                    $val = '<span class="text-success"><i class="bi bi-check-lg"></i> ' . htmlspecialchars($labels[$fv] ?? ucfirst((string)$fv)) . '</span>';
                                }
                            }
                        ?>
                        <td class="text-center"><?= $val ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Current Usage Summary -->
    <?php if (($currentPlan['slug'] ?? 'free') !== 'free'): ?>
    <div class="card mb-5">
        <div class="card-header bg-light">
            <h5 class="mb-0 fw-bold"><i class="bi bi-speedometer2 me-2"></i>Your Current Usage</h5>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <?php
                $usageItems = [
                    ['label' => 'Products',           'used' => $usage['used_products'],           'max' => $usage['max_products']],
                    ['label' => 'Shipping Templates',  'used' => $usage['used_shipping_templates'], 'max' => $usage['max_shipping_templates']],
                    ['label' => 'Dropship Imports',    'used' => $usage['used_dropship_imports'],   'max' => $usage['max_dropship_imports']],
                    ['label' => 'Featured Listings',   'used' => $usage['used_featured_listings'],  'max' => $usage['max_featured_listings']],
                ];
                foreach ($usageItems as $item):
                    $max  = (int)$item['max'];
                    $used = (int)$item['used'];
                    if ($max === 0) continue;
                    $pct   = $max < 0 ? 0 : min(100, round($used / max(1, $max) * 100));
                    $color = $pct >= 90 ? 'danger' : ($pct >= 75 ? 'warning' : 'success');
                ?>
                <div class="col-md-6">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="fw-semibold"><?= htmlspecialchars($item['label']) ?></small>
                        <small><?= $used ?> / <?= $max < 0 ? '∞' : $max ?></small>
                    </div>
                    <div class="progress" style="height:8px">
                        <div class="progress-bar bg-<?= $color ?>"
                             role="progressbar"
                             style="width:<?= $max < 0 ? 10 : $pct ?>%"
                             aria-valuenow="<?= $used ?>"
                             aria-valuemin="0"
                             aria-valuemax="<?= $max < 0 ? $used : $max ?>">
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- FAQ -->
    <div class="card mb-5">
        <div class="card-header bg-light">
            <h5 class="mb-0 fw-bold"><i class="bi bi-question-circle me-2"></i>Frequently Asked Questions</h5>
        </div>
        <div class="card-body">
            <div class="accordion accordion-flush" id="faqAccordion">
                <?php
                $faqs = [
                    ['q' => 'Can I upgrade or downgrade anytime?',
                     'a' => 'Yes! You can upgrade instantly and the change takes effect immediately. Downgrades take effect at the end of your current billing cycle.'],
                    ['q' => 'What happens when I upgrade mid-cycle?',
                     'a' => 'We calculate a prorated credit for the remaining time on your current plan and apply it toward your new plan cost.'],
                    ['q' => 'What payment methods are accepted?',
                     'a' => 'We accept all major credit cards (Visa, Mastercard, Amex) via Stripe. Your payment information is handled securely by Stripe.'],
                    ['q' => 'Can I cancel my subscription?',
                     'a' => 'Yes. You can cancel anytime. Your plan stays active until the end of the current billing period, then downgrades to Free.'],
                    ['q' => 'Do duration discounts apply automatically?',
                     'a' => 'Yes! Choose Quarterly, Semi-Annual, or Annual billing to save 10%, 15%, or 25% respectively. The discount is applied immediately.'],
                    ['q' => 'Is there a free trial for Pro or Enterprise?',
                     'a' => 'Contact our sales team about trial options for the Enterprise plan. Pro plan subscribers get full access from day one.'],
                ];
                foreach ($faqs as $i => $faq):
                ?>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-semibold" type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#faq<?= $i ?>">
                            <?= htmlspecialchars($faq['q']) ?>
                        </button>
                    </h2>
                    <div id="faq<?= $i ?>" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-muted"><?= htmlspecialchars($faq['a']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Confirm Plan Modal -->
<div class="modal fade" id="planConfirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Plan Change</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="planConfirmBody">
                Loading…
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="/api/plans.php" id="planConfirmForm">
                    <input type="hidden" name="action"   id="planConfirmAction" value="subscribe">
                    <input type="hidden" name="plan_id"  id="planConfirmPlanId">
                    <input type="hidden" name="duration" id="planConfirmDuration" value="monthly">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                    <button type="submit" class="btn btn-primary" id="planConfirmBtn">Confirm</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const currentSlug = <?= json_encode($currentPlan['slug'] ?? 'free') ?>;
    const pricingData = <?= json_encode(array_map(function($p) {
        return [
            'id'           => $p['id'],
            'slug'         => $p['slug'],
            'name'         => $p['name'],
            'price_monthly'=> (float)($p['price_monthly'] ?? $p['price'] ?? 0),
            'prices'       => [
                'monthly'     => (float)($p['price_monthly']    ?? $p['price'] ?? 0),
                'quarterly'   => (float)($p['price_quarterly']  ?? 0),
                'semi-annual' => (float)($p['price_semi_annual']?? 0),
                'annual'      => (float)($p['price_annual']     ?? 0),
            ],
        ];
    }, $plans)) ?>;

    const discounts = {
        'monthly': 0, 'quarterly': 10, 'semi-annual': 15, 'annual': 25
    };

    // Duration toggle — update prices
    document.querySelectorAll('input[name="duration"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            const dur = this.value;
            pricingData.forEach(function (plan) {
                const priceEl = document.getElementById('price-' + plan.id);
                const saveEl  = document.getElementById('savings-' + plan.id);
                if (!priceEl) return;
                const price = plan.prices[dur] || 0;
                priceEl.textContent = price === 0 ? '$0' : '$' + price.toFixed(0);
                if (saveEl) {
                    if (discounts[dur] > 0 && plan.price_monthly > 0) {
                        saveEl.classList.remove('d-none');
                        saveEl.querySelector('.savings-pct').textContent = discounts[dur];
                    } else {
                        saveEl.classList.add('d-none');
                    }
                }
            });
            document.querySelectorAll('.plan-action-btn').forEach(function (btn) {
                btn.dataset.duration = dur;
            });
            document.getElementById('planConfirmDuration').value = dur;
        });
    });

    // Plan action buttons
    document.querySelectorAll('.plan-action-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const planId   = this.dataset.planId;
            const planSlug = this.dataset.planSlug;
            const planName = this.dataset.planName;
            const duration = document.querySelector('input[name="duration"]:checked').value;
            const disc     = discounts[duration] || 0;
            const planData = pricingData.find(p => p.id == planId);
            const price    = planData ? planData.prices[duration] : 0;
            const total    = planData ? price * ({monthly:1,quarterly:3,'semi-annual':6,annual:12}[duration] || 1) : 0;

            let action = 'subscribe';
            if (planSlug === 'free') action = 'downgrade';
            else if (currentSlug !== 'free') action = 'upgrade';

            let bodyHtml = '<p>You are about to <strong>' + (action === 'downgrade' ? 'downgrade to' : 'subscribe to') + '</strong> the <strong>' + planName + '</strong> plan.</p>';
            if (price > 0) {
                bodyHtml += '<ul class="list-group list-group-flush">';
                bodyHtml += '<li class="list-group-item d-flex justify-content-between"><span>Duration</span><strong>' + duration.charAt(0).toUpperCase() + duration.slice(1) + '</strong></li>';
                bodyHtml += '<li class="list-group-item d-flex justify-content-between"><span>Monthly rate</span><strong>$' + price.toFixed(0) + '/mo</strong></li>';
                if (disc > 0) bodyHtml += '<li class="list-group-item d-flex justify-content-between text-success"><span>Discount</span><strong>–' + disc + '%</strong></li>';
                bodyHtml += '<li class="list-group-item d-flex justify-content-between fw-bold"><span>Total charged now</span><span>$' + total.toFixed(2) + '</span></li>';
                bodyHtml += '</ul>';
                bodyHtml += '<p class="mt-3 text-muted small">You will be redirected to complete payment securely via Stripe.</p>';
            } else {
                bodyHtml += '<p class="text-success">No payment required — Free plan is always $0.</p>';
            }

            document.getElementById('planConfirmBody').innerHTML  = bodyHtml;
            document.getElementById('planConfirmAction').value    = action;
            document.getElementById('planConfirmPlanId').value    = planId;
            document.getElementById('planConfirmDuration').value  = duration;
            document.getElementById('planConfirmBtn').textContent = action === 'downgrade' ? 'Confirm Downgrade' : (price > 0 ? 'Proceed to Payment' : 'Confirm');

            const modal = new bootstrap.Modal(document.getElementById('planConfirmModal'));
            modal.show();
        });
    });

    // Handle form submit — redirect to stripe checkout if needed
    document.getElementById('planConfirmForm').addEventListener('submit', function (e) {
        const btn  = document.getElementById('planConfirmBtn');
        btn.disabled = true;
        btn.textContent = 'Processing…';
    });
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
