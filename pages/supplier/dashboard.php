<?php
/**
 * pages/supplier/dashboard.php — Supplier Dashboard
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireRole(['supplier', 'admin', 'super_admin']);

$db = getDB();

// Get supplier record
$suppStmt = $db->prepare('SELECT * FROM suppliers WHERE user_id = ?');
$suppStmt->execute([$_SESSION['user_id']]);
$supplier = $suppStmt->fetch();

if (!$supplier && !isAdmin()) {
    flashMessage('warning', 'Supplier account not found.');
    redirect('/pages/supplier/profile.php');
}

$supplierId = $supplier['id'] ?? 0;

// Stats
$statsData = [
    'total_products' => 0,
    'total_orders'   => 0,
    'revenue'        => 0,
    'pending_orders' => 0,
];

if ($supplierId) {
    $stmt = $db->prepare('SELECT COUNT(*) FROM products WHERE supplier_id = ? AND status != "archived"');
    $stmt->execute([$supplierId]);
    $statsData['total_products'] = (int)$stmt->fetchColumn();

    $stmt = $db->prepare('SELECT COUNT(DISTINCT o.id), COALESCE(SUM(oi.total_price), 0) FROM orders o JOIN order_items oi ON oi.order_id = o.id JOIN products p ON p.id = oi.product_id WHERE p.supplier_id = ? AND o.status NOT IN ("cancelled","refunded")');
    $stmt->execute([$supplierId]);
    $row = $stmt->fetch(PDO::FETCH_NUM);
    $statsData['total_orders'] = (int)$row[0];
    $statsData['revenue']      = (float)$row[1];

    $stmt = $db->prepare('SELECT COUNT(DISTINCT o.id) FROM orders o JOIN order_items oi ON oi.order_id = o.id JOIN products p ON p.id = oi.product_id WHERE p.supplier_id = ? AND o.status = "pending"');
    $stmt->execute([$supplierId]);
    $statsData['pending_orders'] = (int)$stmt->fetchColumn();

    // Recent products
    $pStmt = $db->prepare('SELECT p.*, c.name category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.supplier_id = ? AND p.status != "archived" ORDER BY p.created_at DESC LIMIT 5');
    $pStmt->execute([$supplierId]);
    $recentProducts = $pStmt->fetchAll();

    // Recent orders
    $oStmt = $db->prepare('SELECT o.*, COUNT(oi.id) item_count FROM orders o JOIN order_items oi ON oi.order_id = o.id JOIN products p ON p.id = oi.product_id WHERE p.supplier_id = ? GROUP BY o.id ORDER BY o.placed_at DESC LIMIT 5');
    $oStmt->execute([$supplierId]);
    $recentOrders = $oStmt->fetchAll();
} else {
    $recentProducts = [];
    $recentOrders   = [];
}

$pageTitle = 'Supplier Dashboard';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-speedometer2 text-primary me-2"></i>Supplier Dashboard</h3>
        <a href="/pages/supplier/product-add.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Add Product
        </a>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <i class="bi bi-box-seam display-5 text-primary mb-2"></i>
                <h2 class="fw-bold"><?= $statsData['total_products'] ?></h2>
                <p class="text-muted mb-0">Total Products</p>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <i class="bi bi-bag-check display-5 text-success mb-2"></i>
                <h2 class="fw-bold"><?= $statsData['total_orders'] ?></h2>
                <p class="text-muted mb-0">Total Orders</p>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <i class="bi bi-currency-dollar display-5 text-warning mb-2"></i>
                <h2 class="fw-bold"><?= formatMoney($statsData['revenue']) ?></h2>
                <p class="text-muted mb-0">Total Revenue</p>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <i class="bi bi-hourglass-split display-5 text-danger mb-2"></i>
                <h2 class="fw-bold"><?= $statsData['pending_orders'] ?></h2>
                <p class="text-muted mb-0">Pending Orders</p>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Recent Products -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h6 class="mb-0 fw-bold">Recent Products</h6>
                    <a href="/pages/supplier/products.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recentProducts)): ?>
                    <div class="text-center py-4 text-muted">No products yet. <a href="/pages/supplier/product-add.php">Add one?</a></div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 small">
                            <thead class="table-light"><tr><th>Product</th><th>Price</th><th>Stock</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($recentProducts as $p):
                                $badge = ['active'=>'success','draft'=>'warning','inactive'=>'secondary','pending_review'=>'info'];
                            ?>
                            <tr>
                                <td><a href="/pages/supplier/product-edit.php?id=<?= $p['id'] ?>" class="text-decoration-none fw-semibold"><?= e(mb_strimwidth($p['name'],0,30,'…')) ?></a></td>
                                <td><?= formatMoney($p['price']) ?></td>
                                <td><?= $p['stock_qty'] ?></td>
                                <td><span class="badge bg-<?= $badge[$p['status']]??'secondary' ?>"><?= e($p['status']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h6 class="mb-0 fw-bold">Recent Orders</h6>
                    <a href="/pages/supplier/orders.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recentOrders)): ?>
                    <div class="text-center py-4 text-muted">No orders yet.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 small">
                            <thead class="table-light"><tr><th>Order #</th><th>Items</th><th>Total</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($recentOrders as $o):
                                $sb = ['pending'=>'warning','confirmed'=>'info','processing'=>'info','shipped'=>'primary','delivered'=>'success','cancelled'=>'danger'];
                            ?>
                            <tr>
                                <td class="fw-semibold"><?= e($o['order_number']) ?></td>
                                <td><?= $o['item_count'] ?></td>
                                <td><?= formatMoney($o['total']) ?></td>
                                <td><span class="badge bg-<?= $sb[$o['status']]??'secondary' ?>"><?= ucfirst($o['status']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
