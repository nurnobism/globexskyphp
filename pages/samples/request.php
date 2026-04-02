<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db = getDB();
$productId = (int) get('product_id', 0);

$product = null;
if ($productId > 0) {
    $stmt = $db->prepare("SELECT p.*, s.company_name AS supplier_name FROM products p LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE p.id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
}

$pageTitle = 'Request Sample';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex align-items-center mb-4">
                <a href="index.php" class="btn btn-outline-secondary me-3">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h1 class="h3 mb-0"><i class="bi bi-gift me-2"></i>Request a Sample</h1>
            </div>

            <?php if ($product): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width:80px;height:80px;">
                                    <?php if (!empty($product['image'])): ?>
                                        <img src="<?= e($product['image']) ?>" alt="" class="img-fluid" style="max-height:70px;">
                                    <?php else: ?>
                                        <i class="bi bi-image fs-3 text-muted"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col">
                                <h5 class="mb-1"><?= e($product['name'] ?? 'Product') ?></h5>
                                <p class="text-muted mb-0">
                                    <i class="bi bi-building me-1"></i><?= e($product['supplier_name'] ?? 'Unknown') ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>Product not found. Please select a product from the
                    <a href="index.php">samples listing</a>.
                </div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <form method="post" action="../../api/samples.php?action=request">
                        <?= csrfField() ?>
                        <input type="hidden" name="product_id" value="<?= $productId ?>">

                        <div class="mb-3">
                            <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                            <select class="form-select" id="quantity" name="quantity" required>
                                <?php for ($q = 1; $q <= 5; $q++): ?>
                                    <option value="<?= $q ?>"><?= $q ?> unit<?= $q > 1 ? 's' : '' ?></option>
                                <?php endfor; ?>
                            </select>
                            <div class="form-text">Maximum 5 sample units per request.</div>
                        </div>

                        <div class="mb-3">
                            <label for="shipping_address" class="form-label">Shipping Address <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="shipping_address" name="shipping_address"
                                      rows="3" required placeholder="Enter your full shipping address"></textarea>
                        </div>

                        <div class="mb-4">
                            <label for="instructions" class="form-label">Special Instructions</label>
                            <textarea class="form-control" id="instructions" name="instructions"
                                      rows="3" placeholder="Any special requirements or notes for the supplier"></textarea>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg" <?= !$product ? 'disabled' : '' ?>>
                                <i class="bi bi-send me-1"></i>Submit Sample Request
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
