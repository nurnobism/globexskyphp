<?php
require_once __DIR__ . '/includes/middleware.php';

$db = getDB();

// Featured products
$featStmt = $db->query('SELECT p.*, s.company_name supplier_name FROM products p LEFT JOIN suppliers s ON s.id=p.supplier_id WHERE p.status="active" AND p.is_featured=1 ORDER BY p.created_at DESC LIMIT 8');
$featured = $featStmt->fetchAll();

// Recent products
$recentStmt = $db->query('SELECT p.*, s.company_name supplier_name FROM products p LEFT JOIN suppliers s ON s.id=p.supplier_id WHERE p.status="active" ORDER BY p.created_at DESC LIMIT 8');
$recent = $recentStmt->fetchAll();

// Categories
$catStmt = $db->query('SELECT c.*, COUNT(p.id) product_count FROM categories c LEFT JOIN products p ON p.category_id=c.id AND p.status="active" WHERE c.is_active=1 GROUP BY c.id ORDER BY c.sort_order LIMIT 10');
$categories = $catStmt->fetchAll();

// Stats
$totalProducts  = $db->query('SELECT COUNT(*) FROM products WHERE status="active"')->fetchColumn();
$totalSuppliers = $db->query('SELECT COUNT(*) FROM suppliers WHERE verified=1')->fetchColumn();

$pageTitle = 'Home — Global B2B Trade Platform';
$pageDesc  = 'GlobexSky connects buyers and suppliers worldwide. Find quality products, verified suppliers, and seamless trade solutions.';
include __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section text-white py-5" style="background:linear-gradient(135deg,#0d6efd,#6610f2);min-height:500px">
    <div class="container d-flex flex-column justify-content-center" style="min-height:440px">
        <div class="row align-items-center">
            <div class="col-lg-7 py-4">
                <h1 class="display-4 fw-bold mb-3">
                    Your Gateway to<br><span class="text-warning">Global Trade</span>
                </h1>
                <p class="lead mb-4 text-white-75">Connect with verified suppliers worldwide. Source quality products, request quotes, and manage your entire supply chain in one place.</p>
                <div class="d-flex gap-3 flex-wrap mb-5">
                    <a href="/pages/product/index.php" class="btn btn-light btn-lg px-4 fw-semibold">
                        <i class="bi bi-search me-2"></i>Browse Products
                    </a>
                    <a href="/pages/rfq/create.php" class="btn btn-warning btn-lg px-4 fw-semibold text-dark">
                        <i class="bi bi-file-text me-2"></i>Get Free Quotes
                    </a>
                </div>
                <div class="d-flex gap-4 flex-wrap">
                    <div class="text-center">
                        <div class="h4 fw-bold mb-0"><?= number_format($totalProducts) ?>+</div>
                        <small class="text-white-50">Products</small>
                    </div>
                    <div class="text-center">
                        <div class="h4 fw-bold mb-0"><?= number_format($totalSuppliers) ?>+</div>
                        <small class="text-white-50">Verified Suppliers</small>
                    </div>
                    <div class="text-center">
                        <div class="h4 fw-bold mb-0">50+</div>
                        <small class="text-white-50">Countries</small>
                    </div>
                    <div class="text-center">
                        <div class="h4 fw-bold mb-0">98%</div>
                        <small class="text-white-50">Satisfaction</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 d-none d-lg-flex justify-content-center">
                <div class="card bg-white bg-opacity-10 border-0 p-4 rounded-4 text-center" style="backdrop-filter:blur(10px)">
                    <i class="bi bi-globe2 display-1 text-warning mb-3"></i>
                    <h5 class="text-white fw-bold">Global B2B Marketplace</h5>
                    <p class="text-white-50 small mb-3">Trade with confidence using our secure platform</p>
                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                        <span class="badge bg-success"><i class="bi bi-shield-check me-1"></i>Trade Assurance</span>
                        <span class="badge bg-info"><i class="bi bi-truck me-1"></i>Logistics</span>
                        <span class="badge bg-warning text-dark"><i class="bi bi-star me-1"></i>Verified</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Hero Search -->
<section class="bg-white py-4 shadow-sm">
    <div class="container">
        <form action="/pages/product/index.php" method="GET" class="row g-2 justify-content-center">
            <div class="col-md-6">
                <input type="text" name="q" class="form-control form-control-lg" placeholder="Search products, suppliers...">
            </div>
            <div class="col-md-3">
                <select name="category" class="form-select form-select-lg">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-lg w-100"><i class="bi bi-search me-1"></i>Search</button>
            </div>
        </form>
    </div>
</section>

<!-- Categories -->
<?php if (!empty($categories)): ?>
<section class="py-5 bg-light">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0">Shop by Category</h2>
            <a href="/pages/product/index.php" class="text-decoration-none">View All <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="row row-cols-2 row-cols-md-5 g-3">
            <?php
            $catIcons = ['electronics'=>'cpu-fill','machinery'=>'gear-fill','apparel-fashion'=>'bag-fill','home-garden'=>'house-fill','food-beverage'=>'egg-fried','chemicals'=>'droplet-fill','automotive'=>'car-front-fill','health-beauty'=>'heart-fill','sports-outdoors'=>'bicycle','construction'=>'building-fill'];
            foreach ($categories as $cat):
                $icon = $catIcons[$cat['slug']] ?? 'grid-fill';
            ?>
            <div class="col">
                <a href="/pages/product/index.php?category=<?= urlencode($cat['slug']) ?>" class="card border-0 shadow-sm text-decoration-none text-center py-3 h-100 category-card">
                    <div class="card-body">
                        <i class="bi bi-<?= $icon ?> text-primary fs-1 mb-2"></i>
                        <h6 class="fw-bold mb-0"><?= e($cat['name']) ?></h6>
                        <small class="text-muted"><?= $cat['product_count'] ?> products</small>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Featured Products -->
<?php if (!empty($featured)): ?>
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0"><i class="bi bi-star-fill text-warning me-2"></i>Featured Products</h2>
            <a href="/pages/product/index.php" class="text-decoration-none">View All <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="row row-cols-2 row-cols-md-4 g-3">
            <?php foreach ($featured as $p): ?>
            <?php $imgs = json_decode($p['images'] ?? '[]', true); ?>
            <div class="col">
                <div class="card h-100 border-0 shadow-sm product-card">
                    <a href="/pages/product/detail.php?slug=<?= urlencode($p['slug']) ?>">
                        <img src="<?= !empty($imgs[0]) ? e(APP_URL.'/'.$imgs[0]) : 'https://via.placeholder.com/300x180?text=' . urlencode($p['name']) ?>"
                             class="card-img-top" style="height:180px;object-fit:cover" alt="<?= e($p['name']) ?>">
                    </a>
                    <div class="card-body d-flex flex-column">
                        <h6 class="card-title mb-1">
                            <a href="/pages/product/detail.php?slug=<?= urlencode($p['slug']) ?>" class="text-decoration-none text-dark">
                                <?= e(mb_strimwidth($p['name'], 0, 55, '…')) ?>
                            </a>
                        </h6>
                        <small class="text-muted mb-2"><?= e(mb_strimwidth($p['supplier_name'] ?? '', 0, 30, '…')) ?></small>
                        <?php if ($p['rating'] > 0): ?>
                        <div class="text-warning small mb-1"><?= str_repeat('★', starRating($p['rating'])) ?><?= str_repeat('☆', 5-starRating($p['rating'])) ?> <span class="text-muted">(<?= $p['review_count'] ?>)</span></div>
                        <?php endif; ?>
                        <div class="mt-auto d-flex justify-content-between align-items-center pt-2">
                            <span class="fw-bold text-primary"><?= formatMoney($p['price']) ?></span>
                            <form method="POST" action="/api/cart.php?action=add">
                                <?= csrfField() ?>
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-cart-plus"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- How It Works -->
<section class="py-5 bg-light">
    <div class="container">
        <h2 class="fw-bold text-center mb-5">How GlobexSky Works</h2>
        <div class="row g-4">
            <?php foreach ([
                ['1', 'bi-person-plus', 'Create Account', 'Sign up as a buyer or supplier in minutes. Verification takes 1-2 business days.'],
                ['2', 'bi-search', 'Find & Connect', 'Browse products, explore suppliers, or submit an RFQ to get competitive quotes.'],
                ['3', 'bi-handshake', 'Negotiate & Order', 'Compare quotes, negotiate terms, and place your order with confidence.'],
                ['4', 'bi-truck', 'Receive & Review', 'Track your shipment and leave a review after receiving your order.'],
            ] as [$step, $icon, $title, $desc]): ?>
            <div class="col-md-3 text-center">
                <div class="position-relative d-inline-block mb-3">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto" style="width:80px;height:80px;font-size:2rem">
                        <i class="bi <?= $icon ?>"></i>
                    </div>
                    <span class="position-absolute top-0 start-100 translate-middle badge bg-warning text-dark rounded-pill"><?= $step ?></span>
                </div>
                <h5 class="fw-bold"><?= $title ?></h5>
                <p class="text-muted"><?= $desc ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Recent Products -->
<?php if (!empty($recent)): ?>
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0"><i class="bi bi-clock text-primary me-2"></i>New Arrivals</h2>
            <a href="/pages/product/index.php?sort=created_at&dir=desc" class="text-decoration-none">View All <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="row row-cols-2 row-cols-md-4 g-3">
            <?php foreach ($recent as $p): ?>
            <?php $imgs = json_decode($p['images'] ?? '[]', true); ?>
            <div class="col">
                <div class="card h-100 border-0 shadow-sm product-card">
                    <a href="/pages/product/detail.php?slug=<?= urlencode($p['slug']) ?>">
                        <img src="<?= !empty($imgs[0]) ? e(APP_URL.'/'.$imgs[0]) : 'https://via.placeholder.com/300x180?text=' . urlencode($p['name']) ?>"
                             class="card-img-top" style="height:180px;object-fit:cover" alt="">
                    </a>
                    <div class="card-body d-flex flex-column">
                        <h6 class="mb-1"><a href="/pages/product/detail.php?slug=<?= urlencode($p['slug']) ?>" class="text-decoration-none text-dark"><?= e(mb_strimwidth($p['name'], 0, 55, '…')) ?></a></h6>
                        <small class="text-muted mb-auto"><?= e(mb_strimwidth($p['supplier_name'] ?? '', 0, 30, '…')) ?></small>
                        <div class="d-flex justify-content-between align-items-center pt-2">
                            <span class="fw-bold text-primary"><?= formatMoney($p['price']) ?></span>
                            <form method="POST" action="/api/cart.php?action=add">
                                <?= csrfField() ?>
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-cart-plus"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- CTA Banner -->
<section class="py-5 text-white" style="background:linear-gradient(135deg,#198754,#0d6efd)">
    <div class="container text-center">
        <h2 class="fw-bold mb-3">Ready to Grow Your Business?</h2>
        <p class="lead mb-4 text-white-75">Join thousands of businesses trading on GlobexSky. Free to register, transparent pricing.</p>
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <a href="/pages/auth/register.php?role=buyer" class="btn btn-light btn-lg px-5">I'm a Buyer</a>
            <a href="/pages/auth/register.php?role=supplier" class="btn btn-warning btn-lg px-5 text-dark fw-semibold">I'm a Supplier</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
