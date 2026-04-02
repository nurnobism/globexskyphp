<?php
/**
 * pages/api-platform/index.php — Developer Portal Landing
 */

require_once __DIR__ . '/../../includes/middleware.php';

$pageTitle = 'Developer API Platform — GlobexSky';
include __DIR__ . '/../../includes/header.php';
?>
<!-- Hero -->
<section class="py-5 text-white" style="background:linear-gradient(135deg,#1B2A4A 0%,#2d4270 100%);">
    <div class="container py-4 text-center">
        <span class="badge mb-3 px-3 py-2" style="background:#FF6B35;">REST API v2.0</span>
        <h1 class="display-5 fw-bold mb-3">Build on the GlobexSky Platform</h1>
        <p class="lead mb-4 text-white-75">Access products, orders, suppliers, and trade data through a powerful REST API.<br>Start integrating in minutes.</p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a href="/pages/api-platform/dashboard.php" class="btn btn-lg px-5 fw-semibold text-white" style="background:#FF6B35;">
                <i class="bi bi-rocket-takeoff me-2"></i>Start Building
            </a>
            <a href="/pages/api-platform/docs.php" class="btn btn-lg btn-outline-light px-5">
                <i class="bi bi-book me-2"></i>Documentation
            </a>
        </div>
    </div>
</section>

<!-- Features -->
<section class="py-5 bg-light">
    <div class="container">
        <h2 class="text-center fw-bold mb-5">Everything You Need to Integrate</h2>
        <div class="row g-4">
            <?php $features = [
                ['icon'=>'bi-hdd-network','title'=>'REST API','desc'=>'Clean, predictable RESTful endpoints with JSON responses and OpenAPI 3.0 spec.','color'=>'#FF6B35'],
                ['icon'=>'bi-broadcast','title'=>'Webhooks','desc'=>'Real-time event notifications delivered to your endpoint for orders, shipments & more.','color'=>'#1B2A4A'],
                ['icon'=>'bi-box-seam','title'=>'SDKs','desc'=>'Official PHP, Python, and JavaScript SDKs with auto-generated types and helpers.','color'=>'#FF6B35'],
                ['icon'=>'bi-file-earmark-code','title'=>'Documentation','desc'=>'Interactive API docs, code samples, and a sandbox environment for safe testing.','color'=>'#1B2A4A'],
            ];
            foreach ($features as $f): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100 text-center p-4">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mx-auto mb-3"
                         style="width:60px;height:60px;background:<?= $f['color'] ?>1a;">
                        <i class="<?= $f['icon'] ?> fs-3" style="color:<?= $f['color'] ?>;"></i>
                    </div>
                    <h5 class="fw-bold"><?= $f['title'] ?></h5>
                    <p class="text-muted small mb-0"><?= $f['desc'] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Code sample -->
<section class="py-5">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-5">
                <h2 class="fw-bold mb-3">Simple, Intuitive API</h2>
                <p class="text-muted">Authenticate with an API key, call any endpoint, and get back structured JSON. Works with any language or framework.</p>
                <a href="/pages/api-platform/keys.php" class="btn text-white" style="background:#FF6B35;">
                    <i class="bi bi-key me-1"></i>Get API Key
                </a>
            </div>
            <div class="col-lg-7">
                <div class="rounded-3 p-4 text-white" style="background:#1B2A4A;font-family:monospace;font-size:.85rem;">
                    <div class="text-warning mb-1"># List products</div>
                    <div>curl -X GET \</div>
                    <div>&nbsp; "https://api.globexsky.com/v2/products" \</div>
                    <div>&nbsp; -H <span class="text-success">"Authorization: Bearer gsk_live_..."</span></div>
                    <div class="mt-3 text-warning"># Response</div>
                    <div><span class="text-info">{</span></div>
                    <div>&nbsp; <span class="text-success">"data"</span>: [{</div>
                    <div>&nbsp;&nbsp;&nbsp; <span class="text-success">"id"</span>: <span class="text-warning">42</span>,</div>
                    <div>&nbsp;&nbsp;&nbsp; <span class="text-success">"name"</span>: <span class="text-info">"Wireless Earbuds"</span>,</div>
                    <div>&nbsp;&nbsp;&nbsp; <span class="text-success">"price"</span>: <span class="text-warning">24.99</span></div>
                    <div>&nbsp; }],</div>
                    <div>&nbsp; <span class="text-success">"total"</span>: <span class="text-warning">1284</span></div>
                    <div><span class="text-info">}</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Pricing preview -->
<section class="py-5 bg-light">
    <div class="container">
        <h2 class="text-center fw-bold mb-2">Simple, Transparent Pricing</h2>
        <p class="text-center text-muted mb-5">Start free. Scale as you grow.</p>
        <div class="row g-4 justify-content-center">
            <?php $plans = [
                ['name'=>'Free',       'price'=>0,   'calls'=>'1,000/day',   'highlight'=>false],
                ['name'=>'Basic',      'price'=>99,  'calls'=>'50,000/day',  'highlight'=>false],
                ['name'=>'Pro',        'price'=>299, 'calls'=>'500,000/day', 'highlight'=>true],
                ['name'=>'Enterprise', 'price'=>999, 'calls'=>'Unlimited',   'highlight'=>false],
            ];
            foreach ($plans as $p): ?>
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm text-center h-100 <?= $p['highlight'] ? 'border-top border-3' : '' ?>"
                     <?= $p['highlight'] ? 'style="border-color:#FF6B35!important;"' : '' ?>>
                    <?php if ($p['highlight']): ?>
                    <div class="py-1 text-white small fw-bold" style="background:#FF6B35;">MOST POPULAR</div>
                    <?php endif; ?>
                    <div class="card-body p-4">
                        <h6 class="text-muted text-uppercase"><?= $p['name'] ?></h6>
                        <div class="display-6 fw-bold my-2">$<?= $p['price'] ?><small class="fs-6 text-muted">/mo</small></div>
                        <p class="text-muted small"><?= $p['calls'] ?> API calls</p>
                        <a href="/pages/api-platform/pricing.php" class="btn btn-sm w-100 <?= $p['highlight'] ? 'text-white' : 'btn-outline-secondary' ?>"
                           <?= $p['highlight'] ? 'style="background:#FF6B35;"' : '' ?>>View Plan</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
            <a href="/pages/api-platform/pricing.php" class="text-decoration-none" style="color:#FF6B35;">
                Compare all features <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
