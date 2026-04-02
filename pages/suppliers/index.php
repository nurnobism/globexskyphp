<?php
require_once __DIR__ . '/../../includes/middleware.php';

$db = getDB();
$page = (int) get('page', 1);

$search = get('q', '');
$country = get('country', '');
$category = get('category', '');
$verifiedOnly = get('verified', '');

$countries = $db->query("SELECT DISTINCT country FROM suppliers WHERE country IS NOT NULL ORDER BY country ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$categories = $db->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$sql = "SELECT s.*, (SELECT COUNT(*) FROM products p WHERE p.supplier_id = s.id) AS product_count FROM suppliers s WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (s.company_name LIKE ? OR s.description LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($country) {
    $sql .= " AND s.country = ?";
    $params[] = $country;
}
if ($category) {
    $sql .= " AND s.id IN (SELECT DISTINCT supplier_id FROM products WHERE category_id = ?)";
    $params[] = $category;
}
if ($verifiedOnly) {
    $sql .= " AND s.is_verified = 1";
}

$sql .= " ORDER BY s.rating DESC, s.company_name ASC";
$result = paginate($db, $sql, $params, $page);
$suppliers = $result['data'] ?? [];
$pagination = $result['pagination'] ?? [];

$pageTitle = 'Supplier Directory';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="text-center mb-4">
        <h1 class="h3"><i class="bi bi-building me-2"></i>Supplier Directory</h1>
        <p class="text-muted">Find trusted suppliers for your business needs.</p>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="q" class="form-label">Search</label>
                    <input type="text" class="form-control" id="q" name="q"
                           value="<?= e($search) ?>" placeholder="Company name or keyword...">
                </div>
                <div class="col-md-2">
                    <label for="country" class="form-label">Country</label>
                    <select class="form-select" id="country" name="country">
                        <option value="">All Countries</option>
                        <?php foreach ($countries as $c): ?>
                            <option value="<?= e($c) ?>" <?= $country === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int) $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" id="verified" name="verified"
                               value="1" <?= $verifiedOnly ? 'checked' : '' ?>>
                        <label class="form-check-label" for="verified">Verified Only</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search me-1"></i>Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($suppliers)): ?>
        <div class="text-center py-5">
            <i class="bi bi-building display-1 text-muted"></i>
            <h5 class="mt-3">No Suppliers Found</h5>
            <p class="text-muted">Try adjusting your search criteria.</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($suppliers as $supplier): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title mb-0"><?= e($supplier['company_name'] ?? '') ?></h5>
                                <?php if (!empty($supplier['is_verified'])): ?>
                                    <span class="badge bg-success"><i class="bi bi-patch-check-fill me-1"></i>Verified</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-muted small mb-2">
                                <i class="bi bi-geo-alt me-1"></i><?= e($supplier['country'] ?? 'Unknown') ?>
                            </p>
                            <div class="mb-2"><?= starRating($supplier['rating'] ?? 0) ?></div>
                            <p class="small text-muted mb-3">
                                <i class="bi bi-box-seam me-1"></i><?= (int) ($supplier['product_count'] ?? 0) ?> products
                            </p>
                            <a href="detail.php?id=<?= (int) $supplier['id'] ?>" class="btn btn-outline-primary btn-sm w-100">
                                <i class="bi bi-eye me-1"></i>View Supplier
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

<?php include __DIR__ . '/../../includes/footer.php'; ?>
