<?php
/**
 * pages/reviews/create.php — Submit a Product Review
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$db        = getDB();
$productId = (int)get('product_id', 0);

if (!$productId) {
    flashMessage('danger', 'Product ID is required.');
    redirect('/pages/product/index.php');
}

$stmt = $db->prepare('SELECT id, name, slug, price, images FROM products WHERE id = ? AND status = "active"');
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    flashMessage('danger', 'Product not found.');
    redirect('/pages/product/index.php');
}

// Check if user already reviewed
$check = $db->prepare('SELECT id FROM reviews WHERE user_id = ? AND product_id = ?');
$check->execute([$_SESSION['user_id'], $productId]);
if ($check->fetch()) {
    flashMessage('warning', 'You have already reviewed this product.');
    redirect('/pages/reviews/index.php?product_id=' . $productId);
}

$pageTitle = 'Write a Review';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/">Home</a></li>
                    <li class="breadcrumb-item">
                        <a href="/pages/product/detail.php?slug=<?= urlencode($product['slug']) ?>"><?= e($product['name']) ?></a>
                    </li>
                    <li class="breadcrumb-item active">Write a Review</li>
                </ol>
            </nav>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-star-fill text-warning me-2"></i>Write a Review</h5>
                </div>
                <div class="card-body">
                    <?php
                    $imgArr = is_string($product['images'] ?? '') ? json_decode($product['images'], true) : ($product['images'] ?? []);
                    $img    = (is_array($imgArr) && !empty($imgArr[0])) ? APP_URL . '/' . $imgArr[0] : 'https://via.placeholder.com/60?text=P';
                    ?>
                    <div class="d-flex align-items-center gap-3 p-3 bg-light rounded mb-4">
                        <img src="<?= e($img) ?>" alt="" style="width:60px;height:60px;object-fit:cover;border-radius:6px">
                        <div>
                            <strong class="d-block"><?= e($product['name']) ?></strong>
                            <span class="text-muted small"><?= formatMoney((float)$product['price']) ?></span>
                        </div>
                    </div>

                    <form method="post" action="/api/reviews.php?action=create">
                        <input type="hidden" name="_csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="product_id" value="<?= $productId ?>">
                        <input type="hidden" name="_redirect" value="/pages/reviews/index.php?product_id=<?= $productId ?>">

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Rating <span class="text-danger">*</span></label>
                            <div class="d-flex gap-2 fs-2" id="starRating">
                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                <i class="bi bi-star text-warning star-btn" style="cursor:pointer" data-val="<?= $s ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" name="rating" id="ratingInput" value="5" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Review Title</label>
                            <input type="text" name="title" class="form-control"
                                   placeholder="Summarize your experience" maxlength="200">
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Your Review <span class="text-danger">*</span></label>
                            <textarea name="body" class="form-control" rows="5"
                                      placeholder="Share your experience with this product..." required></textarea>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-send me-1"></i>Submit Review
                            </button>
                            <a href="/pages/product/detail.php?slug=<?= urlencode($product['slug']) ?>"
                               class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const stars = document.querySelectorAll('.star-btn');
    const input = document.getElementById('ratingInput');
    let current = 5;

    function highlight(val) {
        stars.forEach(function(s) {
            const v = parseInt(s.dataset.val);
            s.className = 'bi ' + (v <= val ? 'bi-star-fill' : 'bi-star') + ' text-warning star-btn';
        });
    }

    highlight(current);

    stars.forEach(function(s) {
        s.addEventListener('mouseenter', function() { highlight(parseInt(s.dataset.val)); });
        s.addEventListener('mouseleave', function() { highlight(current); });
        s.addEventListener('click', function() {
            current = parseInt(s.dataset.val);
            input.value = current;
            highlight(current);
        });
    });
}());
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
