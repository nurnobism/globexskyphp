<?php
require_once __DIR__ . '/../../includes/middleware.php';

$db = getDB();
$products = [];
$ids = array_filter(array_map('intval', explode(',', get('ids', ''))));
$ids = array_slice($ids, 0, 4);

if (!empty($ids)) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("
        SELECT p.*, u.company_name AS supplier_name
        FROM products p
        LEFT JOIN users u ON p.supplier_id = u.id
        WHERE p.id IN ($placeholders)
    ");
    $stmt->execute($ids);
    $products = $stmt->fetchAll();
}

$allProducts = $db->query("SELECT id, name FROM products ORDER BY name LIMIT 100")->fetchAll();

$pageTitle = 'Compare Products';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item"><a href="/pages/product/">Products</a></li>
            <li class="breadcrumb-item active">Compare</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2 mb-0"><i class="bi bi-arrow-left-right me-2"></i>Compare Products</h1>
        <div class="d-flex gap-2">
            <form method="get" class="d-flex gap-2">
                <select name="add_product" class="form-select form-select-sm" style="min-width:200px">
                    <option value="">Add a product...</option>
                    <?php foreach ($allProducts as $p): ?>
                        <?php if (!in_array($p['id'], $ids)): ?>
                            <option value="<?= e($p['id']) ?>"><?= e($p['name']) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm" id="addProductBtn">
                    <i class="bi bi-plus-lg"></i> Add
                </button>
                <?php if (!empty($ids)): ?>
                    <input type="hidden" name="ids" id="currentIds" value="<?= e(implode(',', $ids)) ?>">
                <?php endif; ?>
            </form>
            <?php if (!empty($products)): ?>
                <a href="/pages/compare/" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-x-lg"></i> Clear All
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($products)): ?>
        <div class="text-center py-5">
            <i class="bi bi-columns-gap display-1 text-muted"></i>
            <h3 class="mt-3 text-muted">No Products to Compare</h3>
            <p class="text-muted">Select products from the catalog to compare them side by side.</p>
            <a href="/pages/product/" class="btn btn-primary"><i class="bi bi-grid me-1"></i> Browse Products</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered align-middle text-center">
                <thead class="table-light">
                    <tr>
                        <th style="min-width:140px">Feature</th>
                        <?php foreach ($products as $product): ?>
                            <th style="min-width:200px">
                                <a href="/pages/product/detail.php?id=<?= e($product['id']) ?>" class="text-decoration-none">
                                    <?= e($product['name']) ?>
                                </a>
                                <?php
                                    $remaining = array_diff($ids, [$product['id']]);
                                    $removeUrl = !empty($remaining) ? '/pages/compare/?ids=' . implode(',', $remaining) : '/pages/compare/';
                                ?>
                                <a href="<?= $removeUrl ?>" class="ms-2 text-danger" title="Remove"><i class="bi bi-x-circle"></i></a>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="fw-bold text-start">Image</td>
                        <?php foreach ($products as $product): ?>
                            <td>
                                <?php if (!empty($product['image'])): ?>
                                    <img src="<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>" class="img-fluid rounded" style="max-height:150px">
                                <?php else: ?>
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center mx-auto" style="width:150px;height:150px">
                                        <i class="bi bi-image text-muted" style="font-size:3rem"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td class="fw-bold text-start">Price</td>
                        <?php foreach ($products as $product): ?>
                            <td class="fw-bold text-success fs-5"><?= formatMoney($product['price'] ?? 0) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td class="fw-bold text-start">Supplier</td>
                        <?php foreach ($products as $product): ?>
                            <td><?= e($product['supplier_name'] ?? 'N/A') ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td class="fw-bold text-start">Rating</td>
                        <?php foreach ($products as $product): ?>
                            <td>
                                <?php $rating = $product['rating'] ?? 0; ?>
                                <span class="text-warning"><?= str_repeat('★', (int)round($rating)) . str_repeat('☆', 5 - (int)round($rating)) ?></span>
                                <br><small class="text-muted">(<?= number_format($rating, 1) ?>)</small>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td class="fw-bold text-start">MOQ</td>
                        <?php foreach ($products as $product): ?>
                            <td><?= e($product['moq'] ?? $product['min_order_qty'] ?? 'N/A') ?> units</td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td class="fw-bold text-start">Availability</td>
                        <?php foreach ($products as $product): ?>
                            <td>
                                <?php $stock = $product['stock'] ?? $product['quantity'] ?? 0; ?>
                                <?php if ($stock > 10): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle"></i> In Stock</span>
                                <?php elseif ($stock > 0): ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-circle"></i> Low Stock</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Out of Stock</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td class="fw-bold text-start">Description</td>
                        <?php foreach ($products as $product): ?>
                            <td class="small text-muted"><?= e(mb_strimwidth($product['description'] ?? '', 0, 200, '...')) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td class="fw-bold text-start">Actions</td>
                        <?php foreach ($products as $product): ?>
                            <td>
                                <a href="/pages/product/detail.php?id=<?= e($product['id']) ?>" class="btn btn-sm btn-outline-primary mb-1">
                                    <i class="bi bi-eye"></i> View
                                </a>
                                <button class="btn btn-sm btn-primary mb-1" onclick="addToCart(<?= (int)$product['id'] ?>)">
                                    <i class="bi bi-cart-plus"></i> Add to Cart
                                </button>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php if (count($products) < 4): ?>
            <p class="text-muted text-center mt-3">
                <i class="bi bi-info-circle"></i> You can compare up to 4 products. Currently comparing <?= count($products) ?>.
            </p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
document.querySelector('form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const sel = this.querySelector('[name="add_product"]');
    const newId = sel.value;
    if (!newId) return;
    const current = document.getElementById('currentIds');
    const ids = current ? current.value + ',' + newId : newId;
    window.location.href = '/pages/compare/?ids=' + ids;
});

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
