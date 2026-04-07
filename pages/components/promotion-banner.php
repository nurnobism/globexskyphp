<?php
/**
 * pages/components/promotion-banner.php — Active Promotions Banner (PR #13)
 *
 * Usage: include once on homepage or any page
 * Requires: includes/coupons.php already loaded
 */

if (!function_exists('getActivePromotions')) {
    require_once __DIR__ . '/../../includes/coupons.php';
}

$activePromos = getActivePromotions();
if (empty($activePromos)) return;
?>
<section class="bg-light border-bottom py-4 mb-4" id="promotionBanner">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Active Deals &amp; Promotions</h5>
            <a href="/pages/product/index.php" class="btn btn-sm btn-outline-primary">View All Deals</a>
        </div>

        <?php if (count($activePromos) > 1): ?>
        <!-- Carousel if multiple -->
        <div id="promoCarousel" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-inner rounded">
                <?php foreach ($activePromos as $i => $promo): ?>
                <?php
                    $endTs   = strtotime($promo['end_date']);
                    $diff    = $endTs - time();
                    $days    = max(0, (int)floor($diff / 86400));
                    $hours   = max(0, (int)floor(($diff % 86400) / 3600));
                    $bgClass = $i % 3 === 0 ? 'bg-primary' : ($i % 3 === 1 ? 'bg-success' : 'bg-danger');
                ?>
                <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                    <?php if (!empty($promo['banner_image'])): ?>
                    <img src="<?= e($promo['banner_image']) ?>" class="d-block w-100 rounded" style="max-height:200px;object-fit:cover" alt="<?= e($promo['name']) ?>">
                    <div class="carousel-caption d-none d-md-block">
                        <h5><?= e($promo['name']) ?></h5>
                        <p>
                            <?php if ($promo['discount_type'] === 'percentage'): ?>
                                Up to <?= (float)$promo['discount_value'] ?>% off
                            <?php else: ?>
                                $<?= number_format((float)$promo['discount_value'], 2) ?> off
                            <?php endif; ?>
                            — <?= $days > 0 ? $days . 'd ' : '' ?><?= $hours ?>h left
                        </p>
                    </div>
                    <?php else: ?>
                    <div class="<?= $bgClass ?> text-white rounded p-4 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1"><?= e($promo['name']) ?></h5>
                            <p class="mb-0 opacity-75"><?= e(mb_strimwidth($promo['description'] ?? '', 0, 80, '…')) ?></p>
                        </div>
                        <div class="text-end">
                            <div class="fs-3 fw-bold">
                                <?php if ($promo['discount_type'] === 'percentage'): ?>
                                    <?= (float)$promo['discount_value'] ?>% OFF
                                <?php else: ?>
                                    $<?= number_format((float)$promo['discount_value'], 2) ?> OFF
                                <?php endif; ?>
                            </div>
                            <div class="small opacity-75">
                                Ends in <?= $days > 0 ? $days . 'd ' : '' ?><?= $hours ?>h
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#promoCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon bg-secondary rounded-circle p-3"></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#promoCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon bg-secondary rounded-circle p-3"></span>
            </button>
        </div>

        <?php else: // Single promotion ?>
        <?php $promo = $activePromos[0];
              $endTs = strtotime($promo['end_date']);
              $diff  = $endTs - time();
              $days  = max(0, (int)floor($diff / 86400));
              $hours = max(0, (int)floor(($diff % 86400) / 3600));
        ?>
        <?php if (!empty($promo['banner_image'])): ?>
        <div class="position-relative rounded overflow-hidden" style="max-height:200px">
            <img src="<?= e($promo['banner_image']) ?>" class="w-100" style="max-height:200px;object-fit:cover" alt="<?= e($promo['name']) ?>">
            <div class="position-absolute bottom-0 start-0 end-0 p-3 text-white" style="background:rgba(0,0,0,.5)">
                <strong><?= e($promo['name']) ?></strong>
                —
                <?php if ($promo['discount_type'] === 'percentage'): ?>Up to <?= (float)$promo['discount_value'] ?>% off<?php else: ?>$<?= number_format((float)$promo['discount_value'], 2) ?> off<?php endif; ?>
                <span class="ms-3 badge bg-warning text-dark" id="singlePromoCountdown" data-end="<?= e($promo['end_date']) ?>">
                    <?= $days > 0 ? $days . 'd ' : '' ?><?= $hours ?>h left
                </span>
            </div>
        </div>
        <?php else: ?>
        <div class="bg-primary text-white rounded p-4 d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3">
            <div>
                <h5 class="mb-1"><?= e($promo['name']) ?></h5>
                <p class="mb-0 opacity-75"><?= e($promo['description'] ?? '') ?></p>
            </div>
            <div class="text-end flex-shrink-0">
                <div class="fs-2 fw-bold">
                    <?php if ($promo['discount_type'] === 'percentage'): ?>
                        <?= (float)$promo['discount_value'] ?>% OFF
                    <?php else: ?>
                        $<?= number_format((float)$promo['discount_value'], 2) ?> OFF
                    <?php endif; ?>
                </div>
                <div class="small opacity-75" id="singlePromoCountdown" data-end="<?= e($promo['end_date']) ?>">
                    Sale ends in <?= $days ?>d <?= $hours ?>h
                </div>
                <a href="/pages/product/index.php" class="btn btn-light btn-sm mt-2">Shop Now</a>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<script>
(function() {
    const el = document.getElementById('singlePromoCountdown');
    if (!el) return;
    const endDate = new Date(el.dataset.end.replace(' ', 'T'));
    function tick() {
        const diff = endDate - Date.now();
        if (diff <= 0) { el.textContent = 'Ended'; return; }
        const d = Math.floor(diff / 86400000);
        const h = Math.floor((diff % 86400000) / 3600000);
        const m = Math.floor((diff % 3600000) / 60000);
        const s = Math.floor((diff % 60000) / 1000);
        el.textContent = 'Sale ends in ' + (d > 0 ? d + 'd ' : '') + h + 'h ' + m + 'm ' + s + 's';
    }
    tick();
    setInterval(tick, 1000);
})();
</script>
