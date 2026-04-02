<?php
require_once __DIR__ . '/../../includes/middleware.php';

$db = getDB();
$page = (int) get('page', 1);

$query = get('q', '');
$category = get('category', '');
$priceMin = get('price_min', '');
$priceMax = get('price_max', '');
$country = get('country', '');
$minRating = get('min_rating', '');
$inStock = get('in_stock', '');

$categories = $db->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$countries = $db->query("SELECT DISTINCT country FROM suppliers WHERE country IS NOT NULL ORDER BY country ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];

$sql = "SELECT p.*, s.company_name AS supplier_name, s.rating AS supplier_rating FROM products p LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE 1=1";
$params = [];

if ($query) {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = '%' . $query . '%';
    $params[] = '%' . $query . '%';
}
if ($category) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category;
}
if ($priceMin !== '') {
    $sql .= " AND p.price >= ?";
    $params[] = (float) $priceMin;
}
if ($priceMax !== '') {
    $sql .= " AND p.price <= ?";
    $params[] = (float) $priceMax;
}
if ($country) {
    $sql .= " AND s.country = ?";
    $params[] = $country;
}
if ($minRating !== '') {
    $sql .= " AND s.rating >= ?";
    $params[] = (float) $minRating;
}
if ($inStock) {
    $sql .= " AND p.stock > 0";
}

$sql .= " ORDER BY p.name ASC";
$result = paginate($db, $sql, $params, $page);
$products = $result['data'] ?? [];
$pagination = $result['pagination'] ?? [];
$hasSearch = $query || $category || $priceMin !== '' || $priceMax !== '' || $country || $minRating !== '' || $inStock;

$pageTitle = 'Advanced Search';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="text-center mb-4">
        <h1 class="h3"><i class="bi bi-search me-2"></i>Advanced Product Search</h1>
    </div>

    <form method="get" class="mb-4">
        <div class="input-group input-group-lg shadow-sm">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="text" class="form-control border-start-0" name="q"
                   value="<?= e($query) ?>" placeholder="Search products, suppliers, descriptions...">
            <button class="btn btn-primary px-4" type="submit">Search</button>
        </div>
    </form>

    <div class="row g-4">
        <div class="col-lg-3">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="bi bi-funnel me-1"></i>Filters</h6>
                </div>
                <div class="card-body">
                    <form method="get">
                        <input type="hidden" name="q" value="<?= e($query) ?>">

                        <div class="mb-3">
                            <label for="category" class="form-label fw-semibold">Category</label>
                            <select class="form-select form-select-sm" id="category" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int) $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Price Range</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="number" class="form-control form-control-sm" name="price_min"
                                           placeholder="Min" value="<?= e($priceMin) ?>" min="0" step="0.01">
                                </div>
                                <div class="col-6">
                                    <input type="number" class="form-control form-control-sm" name="price_max"
                                           placeholder="Max" value="<?= e($priceMax) ?>" min="0" step="0.01">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="country" class="form-label fw-semibold">Country</label>
                            <select class="form-select form-select-sm" id="country" name="country">
                                <option value="">All Countries</option>
                                <?php foreach ($countries as $c): ?>
                                    <option value="<?= e($c) ?>" <?= $country === $c ? 'selected' : '' ?>>
                                        <?= e($c) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="min_rating" class="form-label fw-semibold">Min Supplier Rating</label>
                            <select class="form-select form-select-sm" id="min_rating" name="min_rating">
                                <option value="">Any Rating</option>
                                <option value="4" <?= $minRating === '4' ? 'selected' : '' ?>>4+ Stars</option>
                                <option value="3" <?= $minRating === '3' ? 'selected' : '' ?>>3+ Stars</option>
                                <option value="2" <?= $minRating === '2' ? 'selected' : '' ?>>2+ Stars</option>
                                <option value="1" <?= $minRating === '1' ? 'selected' : '' ?>>1+ Stars</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="in_stock" name="in_stock"
                                       value="1" <?= $inStock ? 'checked' : '' ?>>
                                <label class="form-check-label" for="in_stock">In Stock Only</label>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">Apply Filters</button>
                            <a href="index.php" class="btn btn-outline-secondary btn-sm">Clear All</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            <?php if ($hasSearch): ?>
                <p class="text-muted mb-3">
                    <?= count($products) ?> result<?= count($products) !== 1 ? 's' : '' ?> found
                    <?= $query ? 'for "<strong>' . e($query) . '</strong>"' : '' ?>
                </p>
            <?php endif; ?>

            <?php if (empty($products) && $hasSearch): ?>
                <div class="text-center py-5">
                    <i class="bi bi-search display-1 text-muted"></i>
                    <h5 class="mt-3">No Products Found</h5>
                    <p class="text-muted">Try adjusting your search or filters.</p>
                </div>
            <?php elseif (empty($products)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-search display-1 text-muted"></i>
                    <h5 class="mt-3">Start Searching</h5>
                    <p class="text-muted">Use the search bar above to find products.</p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($products as $product): ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="card shadow-sm h-100">
                                <div class="bg-light d-flex align-items-center justify-content-center" style="height:160px;">
                                    <?php if (!empty($product['image'])): ?>
                                        <img src="<?= e($product['image']) ?>" alt="" class="img-fluid" style="max-height:140px;">
                                    <?php else: ?>
                                        <i class="bi bi-image display-4 text-muted"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body d-flex flex-column">
                                    <h6 class="card-title"><?= e($product['name'] ?? '') ?></h6>
                                    <p class="text-muted small mb-1">
                                        <i class="bi bi-building me-1"></i><?= e($product['supplier_name'] ?? 'Unknown') ?>
                                    </p>
                                    <?php if (!empty($product['supplier_rating'])): ?>
                                        <div class="mb-2"><?= starRating($product['supplier_rating']) ?></div>
                                    <?php endif; ?>
                                    <p class="fw-bold text-primary mb-2"><?= formatMoney($product['price'] ?? 0) ?></p>
                                    <a href="../products/detail.php?id=<?= (int) $product['id'] ?>" class="btn btn-outline-primary btn-sm mt-auto">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($pagination['last_page']) && $pagination['last_page'] > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $pagination['last_page']; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $pagination['last_page'] ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
