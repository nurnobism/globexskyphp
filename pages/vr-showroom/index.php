<?php
/**
 * pages/vr-showroom/index.php — VR / AR Showroom Landing Page
 */
require_once __DIR__ . '/../../includes/middleware.php';

$db = getDB();

// Load VR-featured products
$stmt = $db->prepare(
    "SELECT p.id, p.name, p.slug, p.price, p.thumbnail, p.short_desc, p.currency,
            COALESCE(p.has_vr, 0) AS has_vr, COALESCE(p.has_ar, 0) AS has_ar
     FROM products p
     WHERE p.status = 'active' AND (p.has_vr = 1 OR p.has_ar = 1)
     ORDER BY p.has_vr DESC, p.rating DESC
     LIMIT 8"
);
$stmt->execute();
$vrProducts = $stmt->fetchAll();

// Fall back to featured products if no VR flag exists
if (empty($vrProducts)) {
    $stmt = $db->prepare(
        "SELECT p.id, p.name, p.slug, p.price, p.thumbnail, p.short_desc, p.currency,
                0 AS has_vr, 0 AS has_ar
         FROM products p
         WHERE p.status = 'active'
         ORDER BY p.is_featured DESC, p.rating DESC
         LIMIT 8"
    );
    $stmt->execute();
    $vrProducts = $stmt->fetchAll();
}

$pageTitle = 'VR Showroom';
$pageDesc  = 'Explore products in immersive 360° VR and AR on the GlobexSky B2B platform';
include __DIR__ . '/../../includes/header.php';
?>

<style>
    /* ── Orb / 360 sphere animation ── */
    .vr-orb-wrap {
        perspective: 900px;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 300px;
    }
    .vr-orb {
        width: 220px;
        height: 220px;
        border-radius: 50%;
        background: radial-gradient(circle at 35% 35%,
            #4a90e2 0%, #1B2A4A 55%, #0a0f1e 100%);
        box-shadow:
            inset -30px -30px 60px rgba(0,0,0,.5),
            0 0 60px rgba(74,144,226,.4),
            0 0 120px rgba(255,107,53,.15);
        animation: vrSpin 18s linear infinite;
        position: relative;
        overflow: hidden;
    }
    .vr-orb::before {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: 50%;
        background:
            repeating-linear-gradient(0deg,
                transparent 0, transparent 12px,
                rgba(255,255,255,.05) 12px, rgba(255,255,255,.05) 13px),
            repeating-linear-gradient(90deg,
                transparent 0, transparent 12px,
                rgba(255,255,255,.05) 12px, rgba(255,255,255,.05) 13px);
    }
    .vr-orb::after {
        content: '360°';
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        font-weight: 900;
        color: rgba(255,255,255,.85);
        text-shadow: 0 2px 12px rgba(0,0,0,.6);
    }
    @keyframes vrSpin {
        from { transform: rotateY(0deg) rotateX(10deg); }
        to   { transform: rotateY(360deg) rotateX(10deg); }
    }
    .hero-vr {
        background: linear-gradient(135deg, #0a0f1e 0%, #1B2A4A 60%, #2d4a7a 100%);
        min-height: 480px;
    }
    .step-icon {
        width: 56px; height: 56px; border-radius: 50%;
        background: #fff5f0; color: #FF6B35;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem; flex-shrink: 0;
    }
    .product-vr-card { transition: .2s; }
    .product-vr-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(255,107,53,.25) !important; }
    .badge-vr { background: #7c3aed; }
    .badge-ar { background: #0ea5e9; }
    .ar-btn {
        background: linear-gradient(135deg, #FF6B35, #e05a2a);
        border: none; color: #fff; padding: .6rem 1.8rem;
        border-radius: 50px; font-weight: 700; font-size: 1.1rem;
        box-shadow: 0 4px 20px rgba(255,107,53,.45);
        transition: .2s;
    }
    .ar-btn:hover { transform: scale(1.05); box-shadow: 0 6px 28px rgba(255,107,53,.6); color:#fff; }
</style>

<!-- ── Hero Section ───────────────────────────────────────────────────────── -->
<section class="hero-vr d-flex align-items-center text-white py-5">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6 text-center text-lg-start">
                <span class="badge fw-normal mb-3 px-3 py-2" style="background:rgba(255,107,53,.2);color:#FF6B35;border:1px solid #FF6B35">
                    <i class="bi bi-badge-vr me-1"></i>Immersive Commerce
                </span>
                <h1 class="display-5 fw-bold mb-3">
                    Experience Products in<br>
                    <span style="color:#FF6B35">360° VR &amp; AR</span>
                </h1>
                <p class="lead mb-4 opacity-75">
                    Walk virtual showrooms, inspect products from every angle,
                    and place items in your own space — all before you buy.
                </p>
                <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-lg-start">
                    <button class="ar-btn" onclick="launchAR()">
                        <i class="bi bi-phone me-2"></i>View in Your Space
                    </button>
                    <a href="#products" class="btn btn-outline-light rounded-pill px-4 fw-semibold">
                        <i class="bi bi-grid me-2"></i>Browse VR Products
                    </a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="vr-orb-wrap">
                    <div class="vr-orb" id="vr-orb" title="360° Interactive Viewer"></div>
                </div>
                <p class="text-center text-white-50 small mt-2">
                    <i class="bi bi-cursor me-1"></i>Interactive 360° preview · hover to pause
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ── Stats Strip ────────────────────────────────────────────────────────── -->
<div style="background:#FF6B35" class="py-3">
    <div class="container">
        <div class="row g-3 text-white text-center">
            <div class="col-6 col-md-3">
                <strong class="d-block fs-4">500+</strong>
                <small>VR-enabled Products</small>
            </div>
            <div class="col-6 col-md-3">
                <strong class="d-block fs-4">120+</strong>
                <small>Virtual Showrooms</small>
            </div>
            <div class="col-6 col-md-3">
                <strong class="d-block fs-4">AR</strong>
                <small>Place in Your Space</small>
            </div>
            <div class="col-6 col-md-3">
                <strong class="d-block fs-4">WebXR</strong>
                <small>No App Required</small>
            </div>
        </div>
    </div>
</div>

<!-- ── How It Works ───────────────────────────────────────────────────────── -->
<section class="py-5 bg-light">
    <div class="container">
        <h2 class="text-center fw-bold mb-2" style="color:#1B2A4A">How to Use the VR Showroom</h2>
        <p class="text-center text-muted mb-5">Three easy steps to an immersive product experience</p>
        <div class="row g-4">
            <?php
            $steps = [
                ['bi-search',         'Browse Products',       'Filter by category and look for the VR or AR badge on any product card.'],
                ['bi-badge-vr',       'Open 360° Viewer',      'Click "View in VR" to enter the full 360° interactive product viewer, powered by WebXR / A-Frame.'],
                ['bi-phone-landscape','View in Your Space (AR)','Tap "View in Your Space" on a mobile device to place the 3D product model in your real environment using WebAR.'],
            ];
            foreach ($steps as $i => [$icon, $title, $desc]):
            ?>
            <div class="col-md-4">
                <div class="d-flex gap-3 align-items-start">
                    <div class="step-icon"><i class="bi <?= $icon ?>"></i></div>
                    <div>
                        <span class="badge rounded-pill mb-1" style="background:#1B2A4A">Step <?= $i+1 ?></span>
                        <h5 class="fw-bold mb-1"><?= $title ?></h5>
                        <p class="text-muted mb-0 small"><?= $desc ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ── Featured VR Products ───────────────────────────────────────────────── -->
<section id="products" class="py-5">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h2 class="fw-bold mb-1" style="color:#1B2A4A">
                    <i class="bi bi-badge-vr me-2" style="color:#FF6B35"></i>VR &amp; AR Products
                </h2>
                <p class="text-muted mb-0 small">Products with immersive 3D experiences</p>
            </div>
            <a href="/pages/product/index.php" class="btn btn-sm btn-outline-secondary">View All</a>
        </div>

        <?php if (empty($vrProducts)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-badge-vr display-3"></i>
                <p class="mt-3">VR-enabled products coming soon.</p>
            </div>
        <?php else: ?>
        <div class="row g-4">
            <?php foreach ($vrProducts as $p): ?>
            <div class="col-sm-6 col-lg-4 col-xl-3">
                <div class="card h-100 border-0 shadow-sm product-vr-card overflow-hidden">
                    <div class="position-relative bg-light" style="height:180px;overflow:hidden">
                        <?php if (!empty($p['thumbnail'])): ?>
                            <img src="<?= e($p['thumbnail']) ?>" class="w-100 h-100 object-fit-cover" alt="">
                        <?php else: ?>
                            <div class="w-100 h-100 d-flex align-items-center justify-content-center"
                                 style="background:linear-gradient(135deg,#1B2A4A,#2d4a7a)">
                                <i class="bi bi-box-seam text-white opacity-50" style="font-size:3rem"></i>
                            </div>
                        <?php endif; ?>
                        <!-- VR / AR Badges -->
                        <div class="position-absolute top-0 start-0 m-2 d-flex gap-1">
                            <?php if ($p['has_vr']): ?>
                                <span class="badge badge-vr"><i class="bi bi-badge-vr me-1"></i>VR</span>
                            <?php endif; ?>
                            <?php if ($p['has_ar']): ?>
                                <span class="badge badge-ar"><i class="bi bi-phone me-1"></i>AR</span>
                            <?php endif; ?>
                            <?php if (!$p['has_vr'] && !$p['has_ar']): ?>
                                <span class="badge bg-secondary">3D Ready</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <h6 class="fw-bold mb-1 text-truncate"><?= e($p['name']) ?></h6>
                        <?php if (!empty($p['short_desc'])): ?>
                            <p class="text-muted small mb-2 flex-grow-1">
                                <?= e(mb_strimwidth($p['short_desc'], 0, 70, '…')) ?>
                            </p>
                        <?php endif; ?>
                        <p class="fw-bold mb-3" style="color:#FF6B35">
                            <?= formatMoney((float)$p['price'], $p['currency'] ?? 'USD') ?>
                        </p>
                        <div class="d-flex gap-2">
                            <a href="/pages/product/detail.php?slug=<?= e($p['slug']) ?>"
                               class="btn btn-sm flex-fill text-white" style="background:#1B2A4A">
                                View
                            </a>
                            <button class="btn btn-sm fw-semibold text-white" style="background:#FF6B35"
                                    onclick="openVRViewer(<?= (int)$p['id'] ?>, '<?= e(addslashes($p['name'])) ?>')">
                                <i class="bi bi-badge-vr"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ── A-Frame / Three.js note ────────────────────────────────────────────── -->
<section class="py-4" style="background:#1B2A4A">
    <div class="container text-white text-center">
        <p class="mb-1 opacity-75 small">
            <i class="bi bi-info-circle me-1"></i>
            Immersive experiences powered by
            <strong>A-Frame</strong> (WebXR) &amp; <strong>Three.js</strong> — no headset required.
            Works on Chrome, Edge, Safari, and most VR headsets via WebXR API.
        </p>
    </div>
</section>

<!-- ── VR Viewer Modal ────────────────────────────────────────────────────── -->
<div class="modal fade" id="vrModal" tabindex="-1" aria-labelledby="vrModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0" style="background:#0a0f1e">
            <div class="modal-header border-0">
                <h5 class="modal-title text-white" id="vrModalLabel">
                    <i class="bi bi-badge-vr me-2" style="color:#FF6B35"></i><span id="vr-product-name">VR Viewer</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="min-height:420px">
                <!--
                    A-Frame 360° viewer placeholder.
                    In production, replace with:
                    <script src="https://aframe.io/releases/1.5.0/aframe.min.js"></script>
                    <a-scene embedded>
                        <a-sky src="product-360.jpg" rotation="0 -130 0"></a-sky>
                        <a-entity gltf-model="product.glb" position="0 1 -3"></a-entity>
                    </a-scene>
                -->
                <div id="vr-viewer-placeholder"
                     class="d-flex flex-column align-items-center justify-content-center text-white"
                     style="min-height:420px;background:radial-gradient(circle,#1B2A4A 0%,#0a0f1e 100%)">
                    <div class="vr-orb" style="width:160px;height:160px;font-size:1.5rem"></div>
                    <p class="mt-4 opacity-75">360° viewer loading…</p>
                    <p class="small opacity-50">Full A-Frame / Three.js integration required</p>
                    <button class="btn btn-sm mt-2 fw-semibold" style="background:#FF6B35;color:#fff"
                            onclick="launchAR()">
                        <i class="bi bi-phone me-1"></i>Try AR Instead
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openVRViewer(productId, productName) {
    document.getElementById('vr-product-name').textContent = productName;
    var modal = new bootstrap.Modal(document.getElementById('vrModal'));
    modal.show();
}

function launchAR() {
    if ('xr' in navigator) {
        navigator.xr.isSessionSupported('immersive-ar').then(function(supported) {
            if (supported) {
                alert('AR session supported! Connect your A-Frame AR scene here.');
            } else {
                alert('AR is not supported on this device. Try on a modern Android or iOS device.');
            }
        });
    } else {
        alert('WebXR not available. Please use a compatible browser (Chrome on Android, Safari on iOS 15+).');
    }
}

// Pause orb spin on hover for UX feedback
document.getElementById('vr-orb')?.addEventListener('mouseenter', function() {
    this.style.animationPlayState = 'paused';
});
document.getElementById('vr-orb')?.addEventListener('mouseleave', function() {
    this.style.animationPlayState = 'running';
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
