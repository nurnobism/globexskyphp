<?php
/**
 * pages/api-platform/pricing.php — API Pricing Plans
 */

require_once __DIR__ . '/../../includes/middleware.php';

$pageTitle = 'API Pricing Plans — GlobexSky';
include __DIR__ . '/../../includes/header.php';

$plans = [
    [
        'id'       => 'free',
        'name'     => 'Free',
        'price'    => 0,
        'period'   => 'month',
        'calls'    => '1,000 calls/day',
        'color'    => '#6c757d',
        'popular'  => false,
        'features' => [
            ['label' => '1,000 API calls/day',    'ok' => true],
            ['label' => 'Basic endpoints only',    'ok' => true],
            ['label' => 'Community support',       'ok' => true],
            ['label' => 'Rate limiting: 10 req/s', 'ok' => true],
            ['label' => 'Webhooks',                'ok' => false],
            ['label' => 'Analytics dashboard',     'ok' => false],
            ['label' => 'Priority support',        'ok' => false],
            ['label' => 'SLA guarantee',           'ok' => false],
        ],
    ],
    [
        'id'       => 'basic',
        'name'     => 'Basic',
        'price'    => 99,
        'period'   => 'month',
        'calls'    => '50,000 calls/day',
        'color'    => '#1B2A4A',
        'popular'  => false,
        'features' => [
            ['label' => '50,000 API calls/day',    'ok' => true],
            ['label' => 'All endpoints',            'ok' => true],
            ['label' => 'Email support',            'ok' => true],
            ['label' => 'Rate limiting: 50 req/s',  'ok' => true],
            ['label' => 'Webhooks',                 'ok' => true],
            ['label' => 'Analytics dashboard',      'ok' => false],
            ['label' => 'Priority support',         'ok' => false],
            ['label' => 'SLA guarantee',            'ok' => false],
        ],
    ],
    [
        'id'       => 'pro',
        'name'     => 'Pro',
        'price'    => 299,
        'period'   => 'month',
        'calls'    => '500,000 calls/day',
        'color'    => '#FF6B35',
        'popular'  => true,
        'features' => [
            ['label' => '500,000 API calls/day',    'ok' => true],
            ['label' => 'All endpoints',             'ok' => true],
            ['label' => 'Priority email support',    'ok' => true],
            ['label' => 'Rate limiting: 200 req/s',  'ok' => true],
            ['label' => 'Webhooks',                  'ok' => true],
            ['label' => 'Analytics dashboard',       'ok' => true],
            ['label' => 'Priority support',          'ok' => true],
            ['label' => 'SLA guarantee',             'ok' => false],
        ],
    ],
    [
        'id'       => 'enterprise',
        'name'     => 'Enterprise',
        'price'    => 999,
        'period'   => 'month',
        'calls'    => 'Unlimited',
        'color'    => '#1B2A4A',
        'popular'  => false,
        'features' => [
            ['label' => 'Unlimited API calls',      'ok' => true],
            ['label' => 'All endpoints',            'ok' => true],
            ['label' => 'Dedicated support manager','ok' => true],
            ['label' => 'Custom rate limits',       'ok' => true],
            ['label' => 'Webhooks',                 'ok' => true],
            ['label' => 'Advanced analytics',       'ok' => true],
            ['label' => 'Priority support',         'ok' => true],
            ['label' => '99.9% SLA guarantee',      'ok' => true],
        ],
    ],
];
?>
<!-- Page header -->
<section class="py-5 text-white" style="background:linear-gradient(135deg,#1B2A4A 0%,#2d4270 100%);">
    <div class="container text-center py-3">
        <h1 class="display-5 fw-bold mb-2">API Pricing Plans</h1>
        <p class="lead text-white-75 mb-0">Choose the right plan for your integration needs. Upgrade or downgrade anytime.</p>
    </div>
</section>

<div class="container py-5">
    <div id="alertBox" class="d-none mb-4"></div>

    <!-- Plan cards -->
    <div class="row g-4 justify-content-center">
        <?php foreach ($plans as $plan): ?>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow h-100 d-flex flex-column position-relative"
                 style="<?= $plan['popular'] ? 'border-top:4px solid #FF6B35!important;' : '' ?>">
                <?php if ($plan['popular']): ?>
                <div class="position-absolute top-0 start-50 translate-middle">
                    <span class="badge px-3 py-2 text-white" style="background:#FF6B35;">★ MOST POPULAR</span>
                </div>
                <?php endif; ?>

                <div class="card-body p-4 d-flex flex-column <?= $plan['popular'] ? 'pt-5' : '' ?>">
                    <div class="mb-3">
                        <div class="fw-bold fs-5 mb-1"><?= $plan['name'] ?></div>
                        <div>
                            <span class="display-5 fw-bold" style="color:<?= $plan['color'] ?>;">
                                $<?= number_format($plan['price']) ?>
                            </span>
                            <span class="text-muted fs-6">/<?= $plan['period'] ?></span>
                        </div>
                        <div class="badge mt-2 px-3 py-1" style="background:<?= $plan['color'] ?>22;color:<?= $plan['color'] ?>;">
                            <?= $plan['calls'] ?>
                        </div>
                    </div>

                    <ul class="list-unstyled flex-grow-1 mb-4">
                        <?php foreach ($plan['features'] as $feat): ?>
                        <li class="py-1 d-flex align-items-center gap-2 <?= !$feat['ok'] ? 'text-muted' : '' ?>">
                            <i class="bi <?= $feat['ok'] ? 'bi-check-circle-fill' : 'bi-x-circle' ?> flex-shrink-0"
                               style="color:<?= $feat['ok'] ? '#198754' : '#adb5bd' ?>;"></i>
                            <span class="small"><?= $feat['label'] ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <button class="btn btn-lg w-100 fw-semibold subscribe-btn"
                            data-plan="<?= $plan['id'] ?>"
                            style="background:<?= $plan['color'] ?>;color:#fff;border:none;">
                        <?= $plan['price'] === 0 ? 'Get Started Free' : 'Subscribe — $' . number_format($plan['price']) . '/mo' ?>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- FAQ / note -->
    <div class="row justify-content-center mt-5">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-question-circle me-2" style="color:#FF6B35;"></i>Frequently Asked Questions</h5>
                    <dl class="mb-0">
                        <dt>Can I change my plan at any time?</dt>
                        <dd class="text-muted mb-3">Yes. Upgrades apply immediately; downgrades take effect at the next billing cycle.</dd>
                        <dt>What happens if I exceed my daily call limit?</dt>
                        <dd class="text-muted mb-3">Requests beyond the daily limit receive a <code>429 Too Many Requests</code> response until the next UTC day resets your quota.</dd>
                        <dt>Is there a free trial for paid plans?</dt>
                        <dd class="text-muted mb-0">All new accounts start on the Free plan. Contact sales for a Pro or Enterprise trial.</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.subscribe-btn').forEach(btn => {
    btn.addEventListener('click', async function () {
        const planId = this.dataset.plan;
        const fd = new FormData();
        fd.append('plan_id', planId);
        const csrfInput = document.querySelector('input[name="_csrf_token"]');
        if (csrfInput) fd.append('_csrf_token', csrfInput.value);
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing…';
        const res = await fetch('/api/api-platform.php?action=subscribe_plan', {method:'POST', body: fd});
        const data = await res.json();
        const box = document.getElementById('alertBox');
        box.className = 'alert ' + (data.success ? 'alert-success' : 'alert-danger');
        box.textContent = data.message || data.error || (data.success ? 'Plan updated!' : 'Error');
        box.classList.remove('d-none');
        this.disabled = false;
        this.innerHTML = planId === 'free' ? 'Get Started Free' : 'Subscribe';
        window.scrollTo({top: 0, behavior: 'smooth'});
    });
});
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
