<?php
require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/plan_limits.php';
requireRole(['supplier', 'admin', 'super_admin']);

$db          = getDB();
$supplierId  = $_SESSION['user_id'];
$currentPlan = getSupplierPlan($supplierId);
$remaining   = getRemainingLimits($supplierId);

// Load all plans
$plans = [];
try {
    $stmt  = $db->query('SELECT * FROM supplier_plans WHERE is_active = 1 ORDER BY sort_order ASC');
    $plans = $stmt->fetchAll();
    foreach ($plans as &$p) {
        $p['features_decoded'] = json_decode($p['features'] ?? '{}', true) ?: [];
        $p['limits_decoded']   = json_decode($p['limits'] ?? '{}', true) ?: [];
        // Subscriber count
        $cStmt = $db->prepare('SELECT COUNT(*) FROM plan_subscriptions WHERE plan_id = ? AND status = "active"');
        $cStmt->execute([$p['id']]);
        $p['subscriber_count'] = (int)$cStmt->fetchColumn();
    }
    unset($p);
} catch (PDOException $e) {
    // No plans table yet; use defaults
    $plans = [
        ['id'=>1,'name'=>'Free','slug'=>'free','price'=>0,'commission_discount'=>0,'sort_order'=>1,'limits_decoded'=>['products'=>10,'images_per_product'=>3,'featured_per_month'=>0,'livestream_per_week'=>0,'dropshipping'=>false,'api_access'=>false],'features_decoded'=>['badge'=>'none','analytics'=>'basic','support'=>'community'],'subscriber_count'=>0],
        ['id'=>2,'name'=>'Pro','slug'=>'pro','price'=>299,'commission_discount'=>15,'sort_order'=>2,'limits_decoded'=>['products'=>500,'images_per_product'=>10,'featured_per_month'=>2,'livestream_per_week'=>2,'dropshipping'=>true,'api_access'=>'basic'],'features_decoded'=>['badge'=>'pro','analytics'=>'advanced','support'=>'email','custom_store'=>true],'subscriber_count'=>0],
        ['id'=>3,'name'=>'Enterprise','slug'=>'enterprise','price'=>999,'commission_discount'=>30,'sort_order'=>3,'limits_decoded'=>['products'=>-1,'images_per_product'=>20,'featured_per_month'=>-1,'livestream_per_week'=>-1,'dropshipping'=>true,'api_access'=>'full'],'features_decoded'=>['badge'=>'enterprise','analytics'=>'full_ai','support'=>'phone_email','custom_store'=>true,'custom_domain'=>true],'subscriber_count'=>0],
    ];
}

$pageTitle = 'Supplier Plans';
include __DIR__ . '/../../includes/header.php';

function limitLabel(mixed $val): string {
    if ($val === -1 || $val === '-1') return 'Unlimited';
    if ($val === false || $val === null || $val === 0 || $val === '0') return '❌';
    if ($val === true || $val === 1)  return '✅';
    if (is_string($val) && in_array(strtolower($val), ['basic','full'])) return '✅ ' . ucfirst($val);
    return (string)$val;
}
?>
<div class="container py-5">
    <div class="text-center mb-5">
        <h2 class="fw-bold">Supplier Plans</h2>
        <p class="text-muted">Choose the plan that fits your business — upgrade anytime</p>
        <?php if (!empty($currentPlan['name'])): ?>
        <span class="badge bg-primary fs-6">
            Your current plan: <strong><?= e($currentPlan['name']) ?></strong>
        </span>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success"><?= e($_GET['success']) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger"><?= e($_GET['error']) ?></div>
    <?php endif; ?>

    <div class="row g-4 justify-content-center">
        <?php foreach ($plans as $plan):
            $isCurrent = ($currentPlan['slug'] ?? 'free') === $plan['slug'];
            $lim       = $plan['limits_decoded'] ?? [];
            $feat      = $plan['features_decoded'] ?? [];
            $cardClass = $isCurrent ? 'border-primary shadow-lg' : 'border-0 shadow-sm';
            $badgeHtml = match ($feat['badge'] ?? 'none') {
                'pro'        => '<span class="badge bg-primary ms-2">⭐ Pro Seller</span>',
                'enterprise' => '<span class="badge bg-warning text-dark ms-2">💎 Enterprise</span>',
                default      => '',
            };
        ?>
        <div class="col-md-4">
            <div class="card <?= $cardClass ?> h-100 <?= $isCurrent ? 'border-3' : '' ?>">
                <?php if ($isCurrent): ?>
                <div class="card-header bg-primary text-white text-center py-2 small fw-semibold">
                    ✓ Your Current Plan
                </div>
                <?php endif; ?>
                <div class="card-body text-center p-4">
                    <h4 class="fw-bold"><?= e($plan['name']) ?> <?= $badgeHtml ?></h4>
                    <div class="my-3">
                        <?php if ((float)$plan['price'] == 0): ?>
                        <span class="display-5 fw-bold">Free</span>
                        <?php else: ?>
                        <span class="display-5 fw-bold">$<?= number_format((float)$plan['price']) ?></span>
                        <span class="text-muted">/month</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($plan['commission_discount'] > 0): ?>
                    <p class="text-success fw-semibold mb-2">
                        <i class="bi bi-tag-fill"></i> <?= $plan['commission_discount'] ?>% Commission Discount
                    </p>
                    <?php else: ?>
                    <p class="text-muted mb-2 small">Standard commission rates</p>
                    <?php endif; ?>

                    <hr>
                    <ul class="list-unstyled text-start small">
                        <li class="mb-2"><i class="bi bi-box-seam text-primary me-2"></i>
                            <strong>Products:</strong> <?= limitLabel($lim['products'] ?? 10) ?>
                        </li>
                        <li class="mb-2"><i class="bi bi-images text-primary me-2"></i>
                            <strong>Images/product:</strong> <?= limitLabel($lim['images_per_product'] ?? 3) ?>
                        </li>
                        <li class="mb-2"><i class="bi bi-headset text-primary me-2"></i>
                            <strong>Support:</strong> <?= match($feat['support']??'community'){'community'=>'Community','email'=>'✅ Email','phone_email'=>'✅ Phone + Email',default=>ucfirst($feat['support']??'')} ?>
                        </li>
                        <li class="mb-2"><i class="bi bi-bar-chart text-primary me-2"></i>
                            <strong>Analytics:</strong> <?= match($feat['analytics']??'basic'){'basic'=>'Basic','advanced'=>'Advanced','full_ai'=>'Full + AI',default=>ucfirst($feat['analytics']??'')} ?>
                        </li>
                        <li class="mb-2"><i class="bi bi-star text-primary me-2"></i>
                            <strong>Featured/month:</strong> <?= limitLabel($lim['featured_per_month'] ?? 0) ?>
                        </li>
                        <li class="mb-2"><i class="bi bi-camera-video text-primary me-2"></i>
                            <strong>Live sessions/week:</strong> <?= limitLabel($lim['livestream_per_week'] ?? 0) ?>
                        </li>
                        <li class="mb-2"><i class="bi bi-truck text-primary me-2"></i>
                            <strong>Dropshipping:</strong> <?= limitLabel($lim['dropshipping'] ?? false) ?>
                        </li>
                        <li class="mb-2"><i class="bi bi-code-slash text-primary me-2"></i>
                            <strong>API Access:</strong> <?= limitLabel($lim['api_access'] ?? false) ?>
                        </li>
                        <?php if (!empty($feat['custom_store'])): ?>
                        <li class="mb-2"><i class="bi bi-shop text-success me-2"></i>
                            <strong>Custom Store:</strong> ✅
                        </li>
                        <?php endif; ?>
                        <?php if (!empty($feat['custom_domain'])): ?>
                        <li class="mb-2"><i class="bi bi-globe text-success me-2"></i>
                            <strong>Custom Domain:</strong> ✅
                        </li>
                        <?php endif; ?>
                    </ul>

                    <?php if ($isCurrent): ?>
                    <button class="btn btn-outline-primary w-100 mt-3" disabled>Current Plan</button>
                    <?php elseif ((float)$plan['price'] == 0): ?>
                    <form method="POST" action="/api/plans.php?action=upgrade">
                        <?= csrfField() ?>
                        <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
                        <button type="submit" class="btn btn-outline-secondary w-100 mt-3">Downgrade to Free</button>
                    </form>
                    <?php else: ?>
                    <a href="/pages/supplier/plan-upgrade.php?plan_id=<?= (int)$plan['id'] ?>"
                       class="btn btn-primary w-100 mt-3">
                        Upgrade to <?= e($plan['name']) ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- FAQ Accordion -->
    <div class="mt-5">
        <h4 class="fw-bold mb-4 text-center">Frequently Asked Questions</h4>
        <div class="accordion" id="planFaq">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                        When does the commission discount take effect?
                    </button>
                </h2>
                <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#planFaq">
                    <div class="accordion-body">The commission discount takes effect immediately upon upgrading. It applies to all new orders placed after the upgrade.</div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                        Can I cancel my plan at any time?
                    </button>
                </h2>
                <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#planFaq">
                    <div class="accordion-body">Yes. You can cancel at any time from the <a href="/pages/supplier/billing.php">billing page</a>. You retain access until the end of the current billing cycle.</div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                        What happens if I exceed my product limit?
                    </button>
                </h2>
                <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#planFaq">
                    <div class="accordion-body">You won't be able to add new products until you upgrade to a higher plan or remove existing ones.</div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                        Is there a free trial for Pro or Enterprise?
                    </button>
                </h2>
                <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#planFaq">
                    <div class="accordion-body">Contact our support team to discuss trial options for Pro and Enterprise plans.</div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
