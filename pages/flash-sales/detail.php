<?php
require_once __DIR__ . '/../../includes/middleware.php';

$db = getDB();
$id = (int)get('id', 0);

if (!$id) {
    flashMessage('danger', 'Flash sale not found.');
    redirect('/pages/flash-sales/');
}

$stmt = $db->prepare("SELECT * FROM flash_sales WHERE id = ?");
$stmt->execute([$id]);
$sale = $stmt->fetch();

if (!$sale) {
    flashMessage('danger', 'Flash sale not found.');
    redirect('/pages/flash-sales/');
}

$products = [];
$prodStmt = $db->prepare("
    SELECT p.*, fsp.sale_price
    FROM flash_sale_products fsp
    JOIN products p ON fsp.product_id = p.id
    WHERE fsp.flash_sale_id = ?
    ORDER BY p.name
");
$prodStmt->execute([$id]);
$products = $prodStmt->fetchAll();

$now = date('Y-m-d H:i:s');
$isActive = ($sale['start_date'] <= $now && $sale['end_date'] >= $now);
$discount = $sale['discount'] ?? $sale['discount_percent'] ?? 0;

$pageTitle = e($sale['title'] ?? $sale['name'] ?? 'Flash Sale') . ' - Flash Sale';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item"><a href="/pages/flash-sales/">Flash Sales</a></li>
            <li class="breadcrumb-item active"><?= e($sale['title'] ?? $sale['name'] ?? 'Sale') ?></li>
        </ol>
    </nav>

    <!-- Hero Section -->
    <div class="card bg-dark text-white mb-4 <?= $isActive ? 'border-danger' : 'border-secondary' ?>">
        <div class="card-body text-center py-5">
            <?php if ($isActive): ?>
                <span class="badge bg-danger fs-6 mb-3 px-3 py-2">
                    <i class="bi bi-lightning-fill me-1"></i>LIVE NOW
                </span>
            <?php else: ?>
                <span class="badge bg-secondary fs-6 mb-3 px-3 py-2">
                    <?= ($sale['start_date'] > $now) ? 'Coming Soon' : 'Ended' ?>
                </span>
            <?php endif; ?>

            <h1 class="display-5 fw-bold"><?= e($sale['title'] ?? $sale['name'] ?? 'Flash Sale') ?></h1>
            <p class="lead mb-3"><?= e($sale['description'] ?? '') ?></p>

            <div class="d-inline-block bg-danger rounded-pill px-4 py-2 mb-4">
                <span class="fs-2 fw-bold">UP TO <?= e($discount) ?>% OFF</span>
            </div>

            <!-- Countdown Timer -->
            <div class="mt-3">
                <small class="text-white-50 d-block mb-2">
                    <?= $isActive ? 'Sale ends in' : ($sale['start_date'] > $now ? 'Sale starts in' : 'Sale has ended') ?>
                </small>
                <?php $countdownDate = $isActive ? $sale['end_date'] : ($sale['start_date'] > $now ? $sale['start_date'] : ''); ?>
                <?php if ($countdownDate): ?>
                    <div class="d-flex justify-content-center gap-3" id="heroCountdown" data-end="<?= e($countdownDate) ?>">
                        <div class="text-center">
                            <div class="bg-white bg-opacity-25 rounded px-3 py-2"><span class="fs-2 fw-bold days">00</span></div>
                            <small class="text-white-50">Days</small>
                        </div>
                        <div class="text-center">
                            <div class="bg-white bg-opacity-25 rounded px-3 py-2"><span class="fs-2 fw-bold hours">00</span></div>
                            <small class="text-white-50">Hours</small>
                        </div>
                        <div class="text-center">
                            <div class="bg-white bg-opacity-25 rounded px-3 py-2"><span class="fs-2 fw-bold minutes">00</span></div>
                            <small class="text-white-50">Min</small>
                        </div>
                        <div class="text-center">
                            <div class="bg-white bg-opacity-25 rounded px-3 py-2"><span class="fs-2 fw-bold seconds">00</span></div>
                            <small class="text-white-50">Sec</small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sale Info -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="bi bi-percent display-6 text-danger"></i>
                    <h5 class="mt-2">Up to <?= e($discount) ?>% Off</h5>
                    <small class="text-muted">On selected products</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="bi bi-box-seam display-6 text-primary"></i>
                    <h5 class="mt-2"><?= count($products) ?> Products</h5>
                    <small class="text-muted">In this flash sale</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="bi bi-truck display-6 text-success"></i>
                    <h5 class="mt-2">Fast Shipping</h5>
                    <small class="text-muted">Express delivery available</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Products Grid -->
    <h3 class="mb-3"><i class="bi bi-grid me-2"></i>Sale Products</h3>
    <?php if (empty($products)): ?>
        <div class="text-center py-5">
            <i class="bi bi-box display-1 text-muted"></i>
            <h4 class="mt-3 text-muted">Products loading soon</h4>
            <p class="text-muted">Check back when the sale is live!</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($products as $product): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="card h-100 shadow-sm">
                        <div class="position-relative">
                            <?php if (!empty($product['image'])): ?>
                                <img src="<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>" class="card-img-top" style="height:180px;object-fit:cover">
                            <?php else: ?>
                                <div class="bg-light d-flex align-items-center justify-content-center" style="height:180px">
                                    <i class="bi bi-image text-muted" style="font-size:3rem"></i>
                                </div>
                            <?php endif; ?>
                            <?php if ($discount > 0): ?>
                                <span class="position-absolute top-0 end-0 badge bg-danger m-2 fs-6">
                                    -<?= e($discount) ?>%
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <h6 class="card-title mb-1">
                                <a href="/pages/product/detail.php?id=<?= e($product['id']) ?>" class="text-decoration-none">
                                    <?= e($product['name']) ?>
                                </a>
                            </h6>
                            <div class="mb-2">
                                <span class="text-muted text-decoration-line-through small">
                                    <?= formatMoney($product['price'] ?? 0) ?>
                                </span>
                                <span class="fw-bold text-danger fs-5 ms-1">
                                    <?= formatMoney($product['sale_price'] ?? ($product['price'] * (1 - $discount / 100))) ?>
                                </span>
                            </div>
                            <?php if (!empty($product['rating'])): ?>
                                <small class="text-warning">
                                    <?= str_repeat('★', (int)round($product['rating'])) . str_repeat('☆', 5 - (int)round($product['rating'])) ?>
                                </small>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-transparent">
                            <?php if ($isActive): ?>
                                <button class="btn btn-danger btn-sm w-100" onclick="addToCart(<?= (int)$product['id'] ?>)">
                                    <i class="bi bi-cart-plus me-1"></i>Add to Cart
                                </button>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-sm w-100" disabled>
                                    <i class="bi bi-clock me-1"></i>Not Available
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function updateHeroCountdown() {
    const el = document.getElementById('heroCountdown');
    if (!el) return;
    const end = new Date(el.dataset.end).getTime();
    const now = Date.now();
    const diff = Math.max(0, end - now);

    const d = Math.floor(diff / 86400000);
    const h = Math.floor((diff % 86400000) / 3600000);
    const m = Math.floor((diff % 3600000) / 60000);
    const s = Math.floor((diff % 60000) / 1000);

    const daysEl = el.querySelector('.days');
    const hoursEl = el.querySelector('.hours');
    const minutesEl = el.querySelector('.minutes');
    const secondsEl = el.querySelector('.seconds');

    if (daysEl) daysEl.textContent = String(d).padStart(2, '0');
    if (hoursEl) hoursEl.textContent = String(h).padStart(2, '0');
    if (minutesEl) minutesEl.textContent = String(m).padStart(2, '0');
    if (secondsEl) secondsEl.textContent = String(s).padStart(2, '0');

    if (diff <= 0) {
        el.closest('.card')?.querySelector('.badge')?.classList.replace('bg-danger', 'bg-secondary');
        el.innerHTML = '<span class="text-white-50">This sale has ended</span>';
    }
}
updateHeroCountdown();
setInterval(updateHeroCountdown, 1000);

function addToCart(productId) {
    fetch('/api/cart.php?action=add', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'product_id=' + productId + '&quantity=1'
    }).then(r => r.json()).then(d => {
        alert(d.message || 'Added to cart');
        location.reload();
    }).catch(() => alert('Error adding to cart'));
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
