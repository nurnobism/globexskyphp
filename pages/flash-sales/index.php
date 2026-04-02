<?php
require_once __DIR__ . '/../../includes/middleware.php';

$db = getDB();
$now = date('Y-m-d H:i:s');

$activeSales = $db->prepare("SELECT * FROM flash_sales WHERE start_date <= ? AND end_date >= ? ORDER BY end_date ASC");
$activeSales->execute([$now, $now]);
$activeSales = $activeSales->fetchAll();

$upcomingSales = $db->prepare("SELECT * FROM flash_sales WHERE start_date > ? ORDER BY start_date ASC LIMIT 10");
$upcomingSales->execute([$now]);
$upcomingSales = $upcomingSales->fetchAll();

$pastSales = $db->prepare("SELECT * FROM flash_sales WHERE end_date < ? ORDER BY end_date DESC LIMIT 10");
$pastSales->execute([$now]);
$pastSales = $pastSales->fetchAll();

$pageTitle = 'Flash Sales';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item active">Flash Sales</li>
        </ol>
    </nav>

    <div class="text-center mb-4">
        <h1 class="display-5 fw-bold"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Flash Sales</h1>
        <p class="text-muted lead">Limited-time deals at incredible prices. Don't miss out!</p>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#active" role="tab">
                <i class="bi bi-lightning me-1"></i>Active
                <?php if (count($activeSales)): ?><span class="badge bg-danger"><?= count($activeSales) ?></span><?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#upcoming" role="tab">
                <i class="bi bi-calendar-event me-1"></i>Upcoming
                <?php if (count($upcomingSales)): ?><span class="badge bg-info"><?= count($upcomingSales) ?></span><?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#past" role="tab">
                <i class="bi bi-clock-history me-1"></i>Past Sales
            </a>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Active Sales -->
        <div class="tab-pane fade show active" id="active" role="tabpanel">
            <?php if (empty($activeSales)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-clock display-1 text-muted"></i>
                    <h3 class="mt-3 text-muted">No Active Sales</h3>
                    <p class="text-muted">Check back soon for new flash deals!</p>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($activeSales as $sale): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-danger shadow-sm">
                                <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                                    <span class="fw-bold"><i class="bi bi-lightning-fill me-1"></i>LIVE NOW</span>
                                    <span class="badge bg-white text-danger">
                                        <?= e($sale['discount'] ?? $sale['discount_percent'] ?? '0') ?>% OFF
                                    </span>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title"><?= e($sale['title'] ?? $sale['name'] ?? 'Flash Sale') ?></h5>
                                    <p class="text-muted small mb-3"><?= e($sale['description'] ?? '') ?></p>

                                    <div class="text-center mb-3">
                                        <small class="text-muted d-block mb-1">Ends in</small>
                                        <div class="countdown fw-bold fs-4 text-danger" data-end="<?= e($sale['end_date']) ?>">
                                            <span class="hours">00</span>:<span class="minutes">00</span>:<span class="seconds">00</span>
                                        </div>
                                    </div>

                                    <?php if (!empty($sale['product_count'])): ?>
                                        <p class="small text-muted mb-0">
                                            <i class="bi bi-box me-1"></i><?= e($sale['product_count']) ?> products
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <a href="/pages/flash-sales/detail.php?id=<?= e($sale['id']) ?>" class="btn btn-danger w-100">
                                        <i class="bi bi-bag me-1"></i>Shop Now
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Upcoming Sales -->
        <div class="tab-pane fade" id="upcoming" role="tabpanel">
            <?php if (empty($upcomingSales)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-calendar display-1 text-muted"></i>
                    <h3 class="mt-3 text-muted">No Upcoming Sales</h3>
                    <p class="text-muted">Stay tuned for new deals!</p>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($upcomingSales as $sale): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-info">
                                <div class="card-header bg-info text-white">
                                    <i class="bi bi-calendar-event me-1"></i>Coming Soon
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title"><?= e($sale['title'] ?? $sale['name'] ?? 'Flash Sale') ?></h5>
                                    <p class="text-muted small mb-3"><?= e($sale['description'] ?? '') ?></p>
                                    <div class="mb-2">
                                        <span class="badge bg-info text-dark fs-6">
                                            <?= e($sale['discount'] ?? $sale['discount_percent'] ?? '0') ?>% OFF
                                        </span>
                                    </div>
                                    <p class="small text-muted mb-0">
                                        <i class="bi bi-calendar me-1"></i>Starts: <?= formatDateTime($sale['start_date'] ?? '') ?>
                                    </p>
                                    <?php if (!empty($sale['product_count'])): ?>
                                        <p class="small text-muted mb-0">
                                            <i class="bi bi-box me-1"></i><?= e($sale['product_count']) ?> products
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <a href="/pages/flash-sales/detail.php?id=<?= e($sale['id']) ?>" class="btn btn-outline-info w-100">
                                        <i class="bi bi-eye me-1"></i>Preview
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Past Sales -->
        <div class="tab-pane fade" id="past" role="tabpanel">
            <?php if (empty($pastSales)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-archive display-1 text-muted"></i>
                    <h3 class="mt-3 text-muted">No Past Sales</h3>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($pastSales as $sale): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 bg-light">
                                <div class="card-body">
                                    <h5 class="card-title text-muted"><?= e($sale['title'] ?? $sale['name'] ?? 'Flash Sale') ?></h5>
                                    <p class="text-muted small mb-2"><?= e($sale['description'] ?? '') ?></p>
                                    <span class="badge bg-secondary">
                                        <?= e($sale['discount'] ?? $sale['discount_percent'] ?? '0') ?>% OFF
                                    </span>
                                    <p class="small text-muted mt-2 mb-0">
                                        <i class="bi bi-calendar-check me-1"></i>Ended: <?= formatDate($sale['end_date'] ?? '') ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function updateCountdowns() {
    document.querySelectorAll('.countdown').forEach(el => {
        const end = new Date(el.dataset.end).getTime();
        const now = Date.now();
        const diff = Math.max(0, end - now);

        const h = Math.floor(diff / 3600000);
        const m = Math.floor((diff % 3600000) / 60000);
        const s = Math.floor((diff % 60000) / 1000);

        el.querySelector('.hours').textContent = String(h).padStart(2, '0');
        el.querySelector('.minutes').textContent = String(m).padStart(2, '0');
        el.querySelector('.seconds').textContent = String(s).padStart(2, '0');

        if (diff <= 0) el.innerHTML = '<span class="text-muted">Ended</span>';
    });
}
updateCountdowns();
setInterval(updateCountdowns, 1000);
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
