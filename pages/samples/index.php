<?php
require_once __DIR__ . '/../../includes/middleware.php';

$db = getDB();
$page = (int) get('page', 1);

$result = paginate($db, "SELECT p.*, s.company_name AS supplier_name FROM products p LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE p.sample_available = 1 ORDER BY p.name ASC", [], $page);
$products = $result['data'] ?? [];
$pagination = $result['pagination'] ?? [];

$pageTitle = 'Product Samples';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="text-center mb-4">
        <h1 class="h3"><i class="bi bi-gift me-2"></i>Free Product Samples</h1>
        <p class="text-muted">Explore products and request free samples before placing bulk orders.</p>
    </div>

    <?php if (empty($products)): ?>
        <div class="text-center py-5">
            <i class="bi bi-box-seam display-1 text-muted"></i>
            <h5 class="mt-3">No Samples Available</h5>
            <p class="text-muted">Check back later for new product samples.</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($products as $product): ?>
                <div class="col-md-6 col-lg-4 col-xl-3">
                    <div class="card shadow-sm h-100">
                        <div class="bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                            <?php if (!empty($product['image'])): ?>
                                <img src="<?= e($product['image']) ?>" alt="<?= e($product['name'] ?? '') ?>"
                                     class="img-fluid" style="max-height: 180px;">
                            <?php else: ?>
                                <i class="bi bi-image display-3 text-muted"></i>
                            <?php endif; ?>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <h6 class="card-title"><?= e($product['name'] ?? 'Untitled Product') ?></h6>
                            <p class="text-muted small mb-3">
                                <i class="bi bi-building me-1"></i>
                                <?= e($product['supplier_name'] ?? 'Unknown Supplier') ?>
                            </p>
                            <div class="mt-auto">
                                <a href="request.php?product_id=<?= (int) $product['id'] ?>" class="btn btn-primary w-100">
                                    <i class="bi bi-gift me-1"></i>Request Sample
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($pagination['last_page']) && $pagination['last_page'] > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $pagination['last_page']; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $pagination['last_page'] ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
