<?php
require_once __DIR__ . '/../../includes/middleware.php';

$db = getDB();
$id = (int) get('id', 0);

$stmt = $db->prepare("SELECT * FROM suppliers WHERE id = ?");
$stmt->execute([$id]);
$supplier = $stmt->fetch(PDO::FETCH_ASSOC);

$products = [];
$reviews = [];
if ($supplier) {
    $stmt = $db->prepare("SELECT * FROM products WHERE supplier_id = ? ORDER BY name ASC LIMIT 12");
    $stmt->execute([$id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmt = $db->prepare("SELECT r.*, u.name AS reviewer_name FROM reviews r LEFT JOIN users u ON r.user_id = u.id WHERE r.supplier_id = ? ORDER BY r.created_at DESC LIMIT 10");
    $stmt->execute([$id]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$pageTitle = $supplier ? e($supplier['company_name']) : 'Supplier Not Found';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="index.php" class="btn btn-outline-secondary me-3">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="h3 mb-0"><i class="bi bi-building me-2"></i>Supplier Profile</h1>
    </div>

    <?php if (!$supplier): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>Supplier not found.
        </div>
    <?php else: ?>
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center mb-3">
                            <h3 class="mb-0 me-3"><?= e($supplier['company_name'] ?? '') ?></h3>
                            <?php if (!empty($supplier['is_verified'])): ?>
                                <span class="badge bg-success fs-6"><i class="bi bi-patch-check-fill me-1"></i>Verified</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-muted"><?= nl2br(e($supplier['description'] ?? 'No description available.')) ?></p>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light rounded p-3">
                            <dl class="mb-0">
                                <dt class="text-muted small">Country</dt>
                                <dd><i class="bi bi-geo-alt me-1"></i><?= e($supplier['country'] ?? 'Unknown') ?></dd>
                                <dt class="text-muted small">Rating</dt>
                                <dd><?= starRating($supplier['rating'] ?? 0) ?></dd>
                                <dt class="text-muted small">Response Rate</dt>
                                <dd><?= e($supplier['response_rate'] ?? '—') ?>%</dd>
                                <dt class="text-muted small">Member Since</dt>
                                <dd class="mb-0"><?= formatDate($supplier['created_at'] ?? '') ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs mb-4" id="supplierTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="products-tab" data-bs-toggle="tab"
                        data-bs-target="#products-pane" type="button" role="tab">
                    <i class="bi bi-box-seam me-1"></i>Products (<?= count($products) ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="reviews-tab" data-bs-toggle="tab"
                        data-bs-target="#reviews-pane" type="button" role="tab">
                    <i class="bi bi-star me-1"></i>Reviews (<?= count($reviews) ?>)
                </button>
            </li>
        </ul>

        <div class="tab-content" id="supplierTabsContent">
            <div class="tab-pane fade show active" id="products-pane" role="tabpanel">
                <?php if (empty($products)): ?>
                    <p class="text-muted text-center py-4">No products listed yet.</p>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($products as $product): ?>
                            <div class="col-md-4 col-lg-3">
                                <div class="card shadow-sm h-100">
                                    <div class="bg-light d-flex align-items-center justify-content-center" style="height:140px;">
                                        <?php if (!empty($product['image'])): ?>
                                            <img src="<?= e($product['image']) ?>" alt="" class="img-fluid" style="max-height:120px;">
                                        <?php else: ?>
                                            <i class="bi bi-image display-5 text-muted"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body">
                                        <h6 class="card-title small"><?= e($product['name'] ?? '') ?></h6>
                                        <p class="fw-bold text-primary mb-0"><?= formatMoney($product['price'] ?? 0) ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tab-pane fade" id="reviews-pane" role="tabpanel">
                <?php if (empty($reviews)): ?>
                    <p class="text-muted text-center py-4">No reviews yet.</p>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="card shadow-sm mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1"><?= e($review['reviewer_name'] ?? 'Anonymous') ?></h6>
                                        <div class="mb-2"><?= starRating($review['rating'] ?? 0) ?></div>
                                    </div>
                                    <small class="text-muted"><?= formatDate($review['created_at'] ?? '') ?></small>
                                </div>
                                <p class="mb-0"><?= e($review['comment'] ?? '') ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
