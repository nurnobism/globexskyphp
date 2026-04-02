<?php
/**
 * pages/reviews/index.php — Product Reviews
 */
require_once __DIR__ . '/../../includes/middleware.php';

$db        = getDB();
$productId = (int)get('product_id', 0);
$page      = max(1, (int)get('page', 1));

$product = null;
$reviews = [];
$stats   = ['avg_rating' => 0, 'total' => 0];

if ($productId) {
    $stmt = $db->prepare('SELECT id, name, slug, price, images FROM products WHERE id = ? AND status = "active"');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if ($product) {
        $sql    = 'SELECT r.*, u.first_name, u.last_name, u.avatar
                   FROM reviews r JOIN users u ON u.id = r.user_id
                   WHERE r.product_id = ? AND r.status = "approved"
                   ORDER BY r.created_at DESC';
        $result = paginate($db, $sql, [$productId], $page, 10);
        $reviews = $result['data'] ?? [];

        $sSt = $db->prepare('SELECT AVG(rating) avg_rating, COUNT(*) total FROM reviews WHERE product_id = ? AND status = "approved"');
        $sSt->execute([$productId]);
        $stats = $sSt->fetch();
    }
}

$pageTitle = $product ? 'Reviews: ' . $product['name'] : 'Product Reviews';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item"><a href="/pages/product/index.php">Products</a></li>
            <?php if ($product): ?>
            <li class="breadcrumb-item">
                <a href="/pages/product/detail.php?slug=<?= urlencode($product['slug']) ?>"><?= e($product['name']) ?></a>
            </li>
            <?php endif; ?>
            <li class="breadcrumb-item active">Reviews</li>
        </ol>
    </nav>

    <?php if (!$productId || !$product): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>Please select a product to view its reviews.
        <a href="/pages/product/index.php" class="btn btn-sm btn-primary ms-3">Browse Products</a>
    </div>
    <?php else: ?>

    <div class="row g-4">
        <!-- Rating Summary -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-4">
                    <?php
                    $imgArr = is_string($product['images'] ?? '') ? json_decode($product['images'], true) : ($product['images'] ?? []);
                    $img    = (is_array($imgArr) && !empty($imgArr[0])) ? APP_URL . '/' . $imgArr[0] : 'https://via.placeholder.com/120?text=P';
                    ?>
                    <img src="<?= e($img) ?>" alt="" class="rounded mb-3" style="width:100px;height:100px;object-fit:cover">
                    <h5 class="fw-bold"><?= e($product['name']) ?></h5>
                    <div class="display-4 fw-bold text-warning mt-3">
                        <?= number_format((float)$stats['avg_rating'], 1) ?>
                    </div>
                    <div class="mb-1">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                        <i class="bi bi-star<?= $s <= round((float)$stats['avg_rating']) ? '-fill' : '' ?> text-warning"></i>
                        <?php endfor; ?>
                    </div>
                    <small class="text-muted"><?= (int)$stats['total'] ?> review<?= (int)$stats['total'] !== 1 ? 's' : '' ?></small>
                    <?php if (isLoggedIn()): ?>
                    <div class="mt-4">
                        <a href="/pages/reviews/create.php?product_id=<?= $productId ?>"
                           class="btn btn-primary w-100">
                            <i class="bi bi-pencil-square me-1"></i>Write a Review
                        </a>
                    </div>
                    <?php else: ?>
                    <p class="text-muted small mt-3">
                        <a href="/pages/auth/login.php">Login</a> to write a review
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Review List -->
        <div class="col-lg-8">
            <h4 class="fw-bold mb-3">Customer Reviews</h4>

            <?php if (empty($reviews)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-chat-square-text display-3"></i>
                <p class="mt-3">No reviews yet. Be the first to review this product!</p>
                <?php if (isLoggedIn()): ?>
                <a href="/pages/reviews/create.php?product_id=<?= $productId ?>" class="btn btn-primary">
                    <i class="bi bi-pencil-square me-1"></i>Write a Review
                </a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <?php foreach ($reviews as $review): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3">
                        <?php
                        $avatar = !empty($review['avatar']) ? APP_URL . '/' . $review['avatar'] : 'https://ui-avatars.com/api/?name=' . urlencode($review['first_name'] . '+' . $review['last_name']) . '&size=40';
                        ?>
                        <img src="<?= e($avatar) ?>" alt="" class="rounded-circle" width="40" height="40" style="object-fit:cover">
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div>
                                    <strong><?= e($review['first_name'] . ' ' . $review['last_name']) ?></strong>
                                    <div>
                                        <?php for ($s = 1; $s <= 5; $s++): ?>
                                        <i class="bi bi-star<?= $s <= $review['rating'] ? '-fill' : '' ?> text-warning small"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <small class="text-muted"><?= date('M j, Y', strtotime($review['created_at'])) ?></small>
                            </div>
                            <?php if (!empty($review['title'])): ?>
                            <h6 class="fw-semibold mt-2 mb-1"><?= e($review['title']) ?></h6>
                            <?php endif; ?>
                            <?php if (!empty($review['body'])): ?>
                            <p class="text-muted small mb-2"><?= nl2br(e($review['body'])) ?></p>
                            <?php endif; ?>
                            <form method="post" action="/api/reviews.php?action=helpful" class="d-inline">
                                <input type="hidden" name="_csrf_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>">
                                <button class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-hand-thumbs-up me-1"></i>Helpful (<?= (int)($review['helpful'] ?? 0) ?>)
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if (($result['total_pages'] ?? 1) > 1): ?>
            <nav class="mt-3">
                <ul class="pagination justify-content-center">
                    <?php for ($p = 1; $p <= $result['total_pages']; $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?product_id=<?= $productId ?>&page=<?= $p ?>"><?= $p ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
