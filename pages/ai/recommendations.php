<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$pageTitle = 'Personalised Recommendations';
include __DIR__ . '/../../includes/header.php';
?>

<style>
    :root { --brand-orange: #FF6B35; --brand-dark: #1B2A4A; }
    .recs-hero   { background: linear-gradient(135deg, var(--brand-dark) 0%, #2d4070 100%); }
    .section-title { position: relative; display:inline-block; }
    .section-title::after { content:''; position:absolute; bottom:-4px; left:0; width:40px; height:3px;
                            background:var(--brand-orange); border-radius:2px; }
    .product-card   { border-radius: 14px; border: 1.5px solid #f0f0f0;
                      transition: transform .22s, box-shadow .22s; }
    .product-card:hover { transform: translateY(-5px); box-shadow: 0 10px 28px rgba(0,0,0,.1); }
    .product-card img   { height: 190px; object-fit: cover; border-radius: 12px 12px 0 0; }
    .section-icon-badge { width: 42px; height: 42px; border-radius: 12px;
                          display:inline-flex; align-items:center; justify-content:center; font-size:1.2rem; }
    .add-cart-btn { border-radius: 50px; font-size: .82rem; }
    .skeleton { background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
                background-size: 200% 100%; animation: shimmer 1.4s infinite; border-radius: 10px; }
    @keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
</style>

<!-- Hero -->
<div class="recs-hero text-white py-4">
    <div class="container">
        <div class="d-flex align-items-center gap-3">
            <div class="section-icon-badge" style="background:rgba(255,107,53,.2);">
                <i class="bi bi-stars text-warning"></i>
            </div>
            <div>
                <h3 class="fw-bold mb-0">Recommended for You</h3>
                <p class="mb-0 text-white-75 small">Personalised picks based on your order history & browsing</p>
            </div>
            <a href="<?= APP_URL ?>/pages/ai/index.php" class="btn btn-outline-light btn-sm ms-auto rounded-pill">
                <i class="bi bi-arrow-left me-1"></i> AI Hub
            </a>
        </div>
    </div>
</div>

<div class="container py-5">

    <!-- Skeleton loaders while fetching -->
    <div id="loadingState">
        <?php for ($s = 0; $s < 2; $s++): ?>
        <div class="mb-5">
            <div class="skeleton mb-3" style="height:28px;width:220px;"></div>
            <div class="row g-3">
                <?php for ($i = 0; $i < 4; $i++): ?>
                <div class="col-sm-6 col-md-4 col-lg-3">
                    <div class="skeleton" style="height:300px;"></div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
        <?php endfor; ?>
    </div>

    <!-- Rendered recommendations -->
    <div id="recsContainer" class="d-none"></div>

    <!-- Empty state -->
    <div id="emptyState" class="d-none text-center py-5">
        <i class="bi bi-stars text-warning" style="font-size:4rem;"></i>
        <h5 class="mt-3 fw-bold">Building your recommendations</h5>
        <p class="text-muted">Browse or order products and we'll personalise suggestions just for you.</p>
        <a href="<?= APP_URL ?>/pages/product/index.php" class="btn btn-warning fw-bold rounded-pill px-4">
            <i class="bi bi-shop me-2"></i> Browse Products
        </a>
    </div>

    <!-- Error state -->
    <div id="errorState" class="d-none text-center py-5">
        <i class="bi bi-exclamation-circle text-danger" style="font-size:4rem;"></i>
        <h5 class="mt-3 fw-bold">Could not load recommendations</h5>
        <p class="text-muted small" id="errorMessage"></p>
        <button class="btn btn-outline-primary rounded-pill" onclick="fetchRecommendations()">
            <i class="bi bi-arrow-clockwise me-2"></i> Try Again
        </button>
    </div>

</div>

<script>
const RECS_URL = '<?= APP_URL ?>/api/ai/recommendations.php';

function starHtml(rating, count) {
    let html = '';
    for (let i = 1; i <= 5; i++) {
        html += `<i class="bi bi-star${i <= Math.round(rating) ? '-fill' : ''} text-warning" style="font-size:.72rem;"></i>`;
    }
    return html + ` <small class="text-muted">(${count})</small>`;
}

function productCard(p) {
    const img = (p.image && !p.image.includes('no-image'))
        ? p.image : '<?= APP_URL ?>/assets/img/no-image.png';
    return `
    <div class="col-sm-6 col-md-4 col-lg-3">
        <div class="card product-card h-100 border-0 shadow-sm">
            <a href="<?= APP_URL ?>/pages/product/detail.php?id=${p.id}">
                <img src="${img}" class="card-img-top" alt="${p.name}"
                     onerror="this.src='<?= APP_URL ?>/assets/img/no-image.png'">
            </a>
            <div class="card-body p-3 d-flex flex-column">
                <small class="text-muted mb-1">${p.category || ''}</small>
                <a href="<?= APP_URL ?>/pages/product/detail.php?id=${p.id}"
                   class="fw-semibold text-dark text-decoration-none small lh-sm">${p.name}</a>
                <div class="my-1">${starHtml(p.rating, p.review_count)}</div>
                <div class="fw-bold mt-auto" style="color:#FF6B35;">$${parseFloat(p.price).toFixed(2)}</div>
                ${p.supplier ? `<small class="text-muted">${p.supplier}</small>` : ''}
                <div class="d-flex gap-2 mt-3">
                    <a href="<?= APP_URL ?>/pages/product/detail.php?id=${p.id}"
                       class="btn btn-sm btn-outline-primary rounded-pill flex-grow-1">View</a>
                    <button class="btn btn-sm btn-primary rounded-pill flex-grow-1 add-cart-btn"
                            style="background:#FF6B35;border-color:#FF6B35;"
                            onclick="addToCart(${p.id}, this)">
                        <i class="bi bi-cart-plus"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>`;
}

function sectionHtml(section) {
    const icons = {
        'stars': 'stars text-warning',
        'tag-fill': 'tag-fill text-primary',
        'graph-up-arrow': 'graph-up-arrow text-success',
    };
    const iconClass = icons[section.icon] || 'stars text-warning';
    const cards = section.products.map(productCard).join('');

    return `
    <div class="mb-5">
        <div class="d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-${iconClass} fs-5"></i>
            <div>
                <h5 class="fw-bold mb-0 section-title">${section.title}</h5>
                ${section.description ? `<small class="text-muted">${section.description}</small>` : ''}
            </div>
        </div>
        <div class="row g-3">${cards}</div>
    </div>`;
}

async function fetchRecommendations() {
    document.getElementById('loadingState').classList.remove('d-none');
    document.getElementById('recsContainer').classList.add('d-none');
    document.getElementById('emptyState').classList.add('d-none');
    document.getElementById('errorState').classList.add('d-none');

    try {
        const res  = await fetch(RECS_URL);
        const data = await res.json();
        document.getElementById('loadingState').classList.add('d-none');

        if (!data.success) throw new Error(data.message || 'Failed to load recommendations');

        const sections = data.sections || [];
        const allEmpty = sections.every(s => s.products.length === 0);

        if (sections.length === 0 || allEmpty) {
            document.getElementById('emptyState').classList.remove('d-none');
            return;
        }

        const container = document.getElementById('recsContainer');
        container.innerHTML = sections.map(sectionHtml).join('');
        container.classList.remove('d-none');

    } catch (err) {
        document.getElementById('loadingState').classList.add('d-none');
        document.getElementById('errorMessage').textContent = err.message;
        document.getElementById('errorState').classList.remove('d-none');
    }
}

async function addToCart(productId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    try {
        const fd = new FormData();
        fd.append('product_id', productId);
        fd.append('quantity', 1);
        fd.append('_csrf_token', '<?= csrfToken() ?>');
        const res  = await fetch('<?= APP_URL ?>/api/cart.php?action=add', {method:'POST', body: fd});
        const data = await res.json();
        if (data.success) {
            btn.innerHTML = '<i class="bi bi-check-lg"></i>';
            btn.style.background = '#198754';
            btn.style.borderColor = '#198754';
            setTimeout(() => {
                btn.innerHTML = '<i class="bi bi-cart-plus"></i>';
                btn.style.background = '';
                btn.style.borderColor = '';
                btn.disabled = false;
            }, 2000);
        } else {
            throw new Error(data.message || 'Failed');
        }
    } catch (err) {
        btn.innerHTML = '<i class="bi bi-exclamation-triangle"></i>';
        btn.disabled = false;
    }
}

fetchRecommendations();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
