<?php
require_once __DIR__ . '/../../includes/middleware.php';
$db = getDB();

$id = (int)(get('id', 0));
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare('SELECT * FROM campaigns WHERE id=?');
$stmt->execute([$id]);
$campaign = $stmt->fetch();

if (!$campaign) {
    http_response_code(404);
    $pageTitle = 'Campaign Not Found';
    include __DIR__ . '/../../includes/header.php';
    echo '<div class="container py-5 text-center">
            <i class="bi bi-tag display-3 text-muted mb-3 d-block"></i>
            <h2>Campaign Not Found</h2>
            <p class="text-muted">This campaign does not exist or has ended.</p>
            <a href="index.php" class="btn btn-primary">Browse Campaigns</a>
          </div>';
    include __DIR__ . '/../../includes/footer.php';
    exit;
}

// Products in campaign
$prodStmt = $db->prepare(
    'SELECT p.* FROM campaign_products cp
     JOIN products p ON p.id = cp.product_id
     WHERE cp.campaign_id = ?
     ORDER BY p.name ASC'
);
$prodStmt->execute([$id]);
$products = $prodStmt->fetchAll();

$now      = new DateTime();
$endDate  = !empty($campaign['end_date']) ? new DateTime($campaign['end_date']) : null;
$isActive = $endDate && $now < $endDate && (!empty($campaign['start_date']) ? $now >= new DateTime($campaign['start_date']) : true);
$discount = (float)($campaign['discount_percent'] ?? $campaign['discount'] ?? 0);

$pageTitle = $campaign['title'] ?? 'Campaign Detail';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-5">
    <!-- Back link -->
    <div class="mb-4">
        <a href="index.php" class="text-decoration-none text-muted small">
            <i class="bi bi-arrow-left me-1"></i> Back to Campaigns
        </a>
    </div>

    <!-- Hero Section -->
    <div class="card border-0 shadow-sm mb-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="bg-primary bg-gradient text-white p-5">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <?php
                        $type       = $campaign['type'] ?? 'promotional';
                        $typeIcons  = ['flash' => 'lightning-charge-fill', 'seasonal' => 'sun-fill', 'clearance' => 'trash3-fill', 'promotional' => 'gift-fill'];
                        $typeIcon   = $typeIcons[$type] ?? 'tag-fill';
                        ?>
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <i class="bi bi-<?= $typeIcon ?> fs-4"></i>
                            <span class="badge bg-white text-primary text-capitalize"><?= e($type) ?> Campaign</span>
                            <?php if ($isActive): ?>
                                <span class="badge bg-success">Active</span>
                            <?php elseif ($endDate && $now > $endDate): ?>
                                <span class="badge bg-secondary">Ended</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Upcoming</span>
                            <?php endif; ?>
                        </div>

                        <h1 class="display-6 fw-bold mb-3"><?= e($campaign['title'] ?? $campaign['name'] ?? '') ?></h1>

                        <?php if (!empty($campaign['description'])): ?>
                        <p class="lead mb-4 opacity-90"><?= e($campaign['description']) ?></p>
                        <?php endif; ?>

                        <div class="d-flex flex-wrap gap-3 small">
                            <?php if (!empty($campaign['start_date'])): ?>
                            <span><i class="bi bi-calendar-check me-1"></i>
                                Starts <?= date('M j, Y', strtotime($campaign['start_date'])) ?>
                            </span>
                            <?php endif; ?>
                            <?php if ($endDate): ?>
                            <span><i class="bi bi-calendar-x me-1"></i>
                                Ends <?= date('M j, Y', $endDate->getTimestamp()) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-lg-4 text-center mt-4 mt-lg-0">
                        <?php if ($discount > 0): ?>
                        <div class="bg-white bg-opacity-15 rounded-4 p-4 d-inline-block">
                            <div class="display-3 fw-black text-white"><?= (int)$discount ?>%</div>
                            <div class="h5 mb-0 opacity-90"><i class="bi bi-tag me-1"></i>Discount</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Countdown Timer (only if active and has end date) -->
    <?php if ($isActive && $endDate): ?>
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4 text-center">
            <h5 class="fw-semibold mb-3 text-muted"><i class="bi bi-clock me-2"></i>Offer Ends In</h5>
            <div class="d-flex justify-content-center gap-3" id="countdown">
                <?php foreach (['days' => 'Days', 'hours' => 'Hours', 'minutes' => 'Minutes', 'seconds' => 'Seconds'] as $unit => $label): ?>
                <div class="text-center">
                    <div class="bg-primary text-white rounded-3 fw-bold d-flex align-items-center justify-content-center"
                         style="width:72px;height:72px;font-size:1.75rem;" id="cd-<?= $unit ?>">00</div>
                    <div class="small text-muted mt-1"><?= $label ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <script>
    (function () {
        const endTime = new Date('<?= $endDate->format('Y-m-d\TH:i:s') ?>').getTime();
        function updateCountdown() {
            const diff = endTime - Date.now();
            if (diff <= 0) {
                document.getElementById('countdown').innerHTML = '<span class="text-muted">Campaign has ended.</span>';
                return;
            }
            const days    = Math.floor(diff / 86400000);
            const hours   = Math.floor((diff % 86400000) / 3600000);
            const minutes = Math.floor((diff % 3600000) / 60000);
            const seconds = Math.floor((diff % 60000) / 1000);
            document.getElementById('cd-days').textContent    = String(days).padStart(2,'0');
            document.getElementById('cd-hours').textContent   = String(hours).padStart(2,'0');
            document.getElementById('cd-minutes').textContent = String(minutes).padStart(2,'0');
            document.getElementById('cd-seconds').textContent = String(seconds).padStart(2,'0');
        }
        updateCountdown();
        setInterval(updateCountdown, 1000);
    })();
    </script>
    <?php endif; ?>

    <!-- Products Section -->
    <h4 class="fw-bold mb-3"><i class="bi bi-grid me-2 text-primary"></i>Products in This Campaign</h4>

    <?php if (empty($products)): ?>
        <div class="text-center py-5 bg-light rounded-3 text-muted">
            <i class="bi bi-box-seam display-3 d-block mb-2"></i>
            <p class="mb-0">No products have been added to this campaign yet.</p>
        </div>
    <?php else: ?>
    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-4">
        <?php foreach ($products as $product):
            $originalPrice = (float)($product['price'] ?? 0);
            $salePrice     = $discount > 0 ? $originalPrice * (1 - $discount / 100) : $originalPrice;
        ?>
        <div class="col">
            <div class="card h-100 shadow-sm border-0 hover-shadow">
                <!-- Product Image -->
                <div class="ratio ratio-1x1 bg-light rounded-top overflow-hidden">
                    <?php if (!empty($product['image'])): ?>
                        <img src="<?= e($product['image']) ?>" alt="<?= e($product['name'] ?? '') ?>"
                             class="w-100 h-100 object-fit-contain p-2">
                    <?php else: ?>
                        <div class="d-flex align-items-center justify-content-center">
                            <i class="bi bi-box-seam display-4 text-muted"></i>
                        </div>
                    <?php endif; ?>
                    <?php if ($discount > 0): ?>
                    <div class="position-absolute top-0 start-0 m-2">
                        <span class="badge bg-danger"><?= (int)$discount ?>% OFF</span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="card-body p-3">
                    <h6 class="fw-semibold mb-1 text-truncate"><?= e($product['name'] ?? '') ?></h6>
                    <?php if (!empty($product['sku'])): ?>
                        <div class="small text-muted mb-2">SKU: <?= e($product['sku']) ?></div>
                    <?php endif; ?>

                    <div class="d-flex align-items-baseline gap-2">
                        <?php if ($discount > 0): ?>
                            <span class="fw-bold text-primary"><?= formatMoney($salePrice) ?></span>
                            <span class="small text-muted text-decoration-line-through"><?= formatMoney($originalPrice) ?></span>
                        <?php else: ?>
                            <span class="fw-bold text-primary"><?= formatMoney($originalPrice) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-footer bg-white border-top-0 px-3 pb-3">
                    <a href="/pages/products/detail.php?id=<?= (int)$product['id'] ?>"
                       class="btn btn-sm btn-outline-primary w-100">
                        View Product
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.hover-shadow { transition: box-shadow .2s; }
.hover-shadow:hover { box-shadow: 0 .5rem 1.5rem rgba(0,0,0,.12) !important; }
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
