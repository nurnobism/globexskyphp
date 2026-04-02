<?php
require_once 'config/app.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
checkRememberToken();

$pageTitle = 'Globex Sky — Global B2B/B2C Marketplace & Logistics';
$pageDesc  = 'Source products globally, ship packages worldwide, and grow your business with Globex Sky — your trusted marketplace and logistics platform.';

$categories = [];
try {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT id, name, slug, image, icon FROM categories WHERE is_active = 1 AND parent_id IS NULL ORDER BY sort_order ASC LIMIT 8");
    $categories = $stmt->fetchAll();
} catch (Exception $e) {}

$trendingProducts = [];
try {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT p.id, p.name, p.slug, p.price, p.sale_price, p.currency, p.min_order_quantity, p.unit, s.business_name as supplier_name, (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image FROM products p JOIN suppliers s ON p.supplier_id = s.id WHERE p.is_active = 1 AND p.is_approved = 1 ORDER BY p.created_at DESC LIMIT 8");
    $trendingProducts = $stmt->fetchAll();
} catch (Exception $e) {}

$featuredSuppliers = [];
try {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT s.id, s.business_name, s.country, s.logo, s.rating, s.total_products, s.is_verified FROM suppliers s WHERE s.is_verified = 1 ORDER BY s.rating DESC LIMIT 6");
    $featuredSuppliers = $stmt->fetchAll();
} catch (Exception $e) {}

$banners = [];
try {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT * FROM banners WHERE is_active = 1 AND position = 'hero' AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW()) ORDER BY sort_order ASC LIMIT 3");
    $banners = $stmt->fetchAll();
} catch (Exception $e) {}

require_once 'includes/header.php';
?>

<style>
.door-card {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 2px solid rgba(255,255,255,0.1);
    transition: transform 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
    min-height: 380px;
}
.sourcing-door:hover { border-color: #ffc107; box-shadow: 0 0 40px rgba(255,193,7,0.3); transform: translateY(-8px); }
.shipment-door:hover { border-color: #0d6efd; box-shadow: 0 0 40px rgba(13,110,253,0.3); transform: translateY(-8px); }
.door-link:hover .door-card { cursor: pointer; }
.stat-item { padding: 1rem; }
.counter { font-size: 2.2rem; }
.hero-doors-section { background: linear-gradient(180deg, #0a0a0a 0%, #1a1a2e 100%) !important; }
.product-card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
.product-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
.supplier-card { transition: transform 0.2s ease; }
.supplier-card:hover { transform: translateY(-4px); }
.search-bar-section { background: linear-gradient(135deg, #1a1a2e, #16213e); }
</style>

<?php if (function_exists('renderFlash')) renderFlash(); ?>

<!-- Hero Two-Door Section -->
<section class="hero-doors-section py-5 bg-dark text-white">
  <div class="container">
    <div class="text-center mb-5">
      <h1 class="display-4 fw-bold">Welcome to <span class="text-warning">Globex Sky</span></h1>
      <p class="lead text-muted">Your Global Trade &amp; Logistics Platform</p>
    </div>
    <div class="row g-4 justify-content-center">
      <div class="col-md-5">
        <a href="/pages/product/index.php" class="door-link text-decoration-none">
          <div class="door-card sourcing-door text-center p-5 rounded-4">
            <div class="door-icon mb-3"><i class="fas fa-store fa-4x text-warning"></i></div>
            <h2 class="text-white">Product Sourcing</h2>
            <p class="text-white-50">Source products from verified global suppliers. B2B marketplace with quality inspection, escrow, and trade assurance.</p>
            <div class="door-features mt-3">
              <span class="badge bg-warning text-dark me-1">B2B Marketplace</span>
              <span class="badge bg-info me-1">Quality Inspection</span>
              <span class="badge bg-success">Trade Assurance</span>
            </div>
            <div class="door-cta mt-4">
              <span class="btn btn-warning btn-lg px-4">Start Sourcing <i class="fas fa-arrow-right ms-2"></i></span>
            </div>
          </div>
        </a>
      </div>
      <div class="col-md-5">
        <a href="/pages/shipment/index.php" class="door-link text-decoration-none">
          <div class="door-card shipment-door text-center p-5 rounded-4">
            <div class="door-icon mb-3"><i class="fas fa-shipping-fast fa-4x text-primary"></i></div>
            <h2 class="text-white">Shipment Services</h2>
            <p class="text-white-50">Personal carry service by travelers or send parcels worldwide. Affordable, reliable, tracked delivery.</p>
            <div class="door-features mt-3">
              <span class="badge bg-primary me-1">Carry Service</span>
              <span class="badge bg-info me-1">Send Parcel</span>
              <span class="badge bg-success">Live Tracking</span>
            </div>
            <div class="door-cta mt-4">
              <span class="btn btn-primary btn-lg px-4">Ship Now <i class="fas fa-arrow-right ms-2"></i></span>
            </div>
          </div>
        </a>
      </div>
    </div>
  </div>
</section>

<!-- Stats Bar -->
<section class="stats-section py-4 bg-warning">
  <div class="container">
    <div class="row text-center">
      <div class="col-6 col-md-3 stat-item" data-target="50000">
        <h3 class="counter fw-bold mb-0">0</h3><p class="mb-0 small">Products Listed</p>
      </div>
      <div class="col-6 col-md-3 stat-item" data-target="5000">
        <h3 class="counter fw-bold mb-0">0</h3><p class="mb-0 small">Verified Suppliers</p>
      </div>
      <div class="col-6 col-md-3 stat-item" data-target="120">
        <h3 class="counter fw-bold mb-0">0</h3><p class="mb-0 small">Countries Served</p>
      </div>
      <div class="col-6 col-md-3 stat-item" data-target="100000">
        <h3 class="counter fw-bold mb-0">0</h3><p class="mb-0 small">Deliveries Completed</p>
      </div>
    </div>
  </div>
</section>

<!-- Search Bar -->
<section class="search-bar-section py-5">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <h4 class="text-white text-center mb-3">Find Products &amp; Services</h4>
        <form action="/pages/product/search.php" method="GET" class="d-flex gap-2">
          <div class="input-group input-group-lg">
            <input type="text" name="q" class="form-control" placeholder="Search products, suppliers, categories...">
            <button class="btn btn-warning" type="button" title="AI Search"><i class="fas fa-robot"></i></button>
            <button class="btn btn-outline-light" type="button" title="Voice Search"><i class="fas fa-microphone"></i></button>
            <button class="btn btn-outline-light" type="button" title="Image Search"><i class="fas fa-camera"></i></button>
            <button class="btn btn-outline-light" type="button" title="Barcode Scan"><i class="fas fa-barcode"></i></button>
            <button class="btn btn-warning px-4" type="submit"><i class="fas fa-search"></i></button>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>

<!-- Featured Categories -->
<section class="categories-section py-5 bg-light">
  <div class="container">
    <h2 class="fw-bold mb-4 text-center">Browse Categories</h2>
    <div class="row g-3">
      <?php if (!empty($categories)): ?>
        <?php foreach ($categories as $cat): ?>
          <div class="col-6 col-md-3">
            <a href="/pages/product/index.php?category=<?= htmlspecialchars($cat['slug']) ?>" class="text-decoration-none">
              <div class="card text-center h-100 border-0 shadow-sm category-card">
                <div class="card-body py-4">
                  <?php if (!empty($cat['icon'])): ?>
                    <i class="<?= htmlspecialchars($cat['icon']) ?> fa-3x text-warning mb-3"></i>
                  <?php elseif (!empty($cat['image'])): ?>
                    <img src="<?= htmlspecialchars($cat['image']) ?>" alt="<?= htmlspecialchars($cat['name']) ?>" class="mb-3" style="height:60px;object-fit:contain;">
                  <?php else: ?>
                    <i class="fas fa-box fa-3x text-warning mb-3"></i>
                  <?php endif; ?>
                  <h6 class="fw-semibold text-dark"><?= htmlspecialchars($cat['name']) ?></h6>
                </div>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <?php
        $placeholderCats = [
          ['icon'=>'fas fa-mobile-alt','name'=>'Electronics'],
          ['icon'=>'fas fa-tshirt','name'=>'Clothing'],
          ['icon'=>'fas fa-couch','name'=>'Furniture'],
          ['icon'=>'fas fa-car','name'=>'Auto Parts'],
          ['icon'=>'fas fa-seedling','name'=>'Agriculture'],
          ['icon'=>'fas fa-hard-hat','name'=>'Industrial'],
          ['icon'=>'fas fa-heartbeat','name'=>'Health &amp; Beauty'],
          ['icon'=>'fas fa-utensils','name'=>'Food &amp; Beverage'],
        ];
        foreach ($placeholderCats as $pc): ?>
          <div class="col-6 col-md-3">
            <div class="card text-center h-100 border-0 shadow-sm">
              <div class="card-body py-4">
                <i class="<?= $pc['icon'] ?> fa-3x text-warning mb-3"></i>
                <h6 class="fw-semibold text-dark"><?= $pc['name'] ?></h6>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Trending Products -->
<section class="products-section py-5">
  <div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="fw-bold mb-0">Trending Products</h2>
      <a href="/pages/product/index.php" class="btn btn-outline-warning">View All <i class="fas fa-arrow-right ms-1"></i></a>
    </div>
    <div class="row g-3">
      <?php if (!empty($trendingProducts)): ?>
        <?php foreach ($trendingProducts as $prod): ?>
          <div class="col-6 col-md-3">
            <div class="card h-100 product-card border-0 shadow-sm">
              <a href="/pages/product/detail.php?slug=<?= htmlspecialchars($prod['slug']) ?>">
                <img src="<?= !empty($prod['image']) ? htmlspecialchars($prod['image']) : '/assets/images/placeholder-product.jpg' ?>" class="card-img-top" alt="<?= htmlspecialchars($prod['name']) ?>" style="height:200px;object-fit:cover;">
              </a>
              <div class="card-body">
                <h6 class="card-title"><a href="/pages/product/detail.php?slug=<?= htmlspecialchars($prod['slug']) ?>" class="text-dark text-decoration-none"><?= htmlspecialchars($prod['name']) ?></a></h6>
                <div class="mb-1">
                  <?php if (!empty($prod['sale_price']) && $prod['sale_price'] < $prod['price']): ?>
                    <span class="fw-bold text-danger"><?= htmlspecialchars($prod['currency'] ?? 'USD') ?> <?= number_format($prod['sale_price'], 2) ?></span>
                    <small class="text-muted text-decoration-line-through ms-1"><?= number_format($prod['price'], 2) ?></small>
                  <?php else: ?>
                    <span class="fw-bold text-warning"><?= htmlspecialchars($prod['currency'] ?? 'USD') ?> <?= number_format($prod['price'], 2) ?></span>
                  <?php endif; ?>
                </div>
                <small class="text-muted d-block">MOQ: <?= htmlspecialchars($prod['min_order_quantity'] ?? 1) ?> <?= htmlspecialchars($prod['unit'] ?? 'pcs') ?></small>
                <small class="text-muted"><?= htmlspecialchars($prod['supplier_name']) ?></small>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <?php for ($i = 0; $i < 8; $i++): ?>
          <div class="col-6 col-md-3">
            <div class="card h-100 border-0 shadow-sm">
              <div class="bg-secondary" style="height:200px;"></div>
              <div class="card-body">
                <div class="placeholder-glow"><span class="placeholder col-8 mb-2"></span><span class="placeholder col-4"></span></div>
              </div>
            </div>
          </div>
        <?php endfor; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Featured Suppliers -->
<section class="suppliers-section py-5 bg-light">
  <div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="fw-bold mb-0">Featured Suppliers</h2>
      <a href="/pages/supplier/index.php" class="btn btn-outline-warning">All Suppliers <i class="fas fa-arrow-right ms-1"></i></a>
    </div>
    <div class="row g-3">
      <?php if (!empty($featuredSuppliers)): ?>
        <?php foreach ($featuredSuppliers as $sup): ?>
          <div class="col-6 col-md-4 col-lg-2">
            <div class="card text-center h-100 supplier-card border-0 shadow-sm">
              <div class="card-body py-3">
                <?php if (!empty($sup['logo'])): ?>
                  <img src="<?= htmlspecialchars($sup['logo']) ?>" alt="<?= htmlspecialchars($sup['business_name']) ?>" style="height:50px;object-fit:contain;" class="mb-2">
                <?php else: ?>
                  <div class="bg-warning rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width:50px;height:50px;">
                    <i class="fas fa-building text-dark"></i>
                  </div>
                <?php endif; ?>
                <h6 class="fw-semibold small"><?= htmlspecialchars($sup['business_name']) ?></h6>
                <small class="text-muted d-block"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($sup['country']) ?></small>
                <div class="text-warning small my-1"><?= str_repeat('★', (int)round($sup['rating'] ?? 0)) ?><?= str_repeat('☆', 5 - (int)round($sup['rating'] ?? 0)) ?></div>
                <small class="text-muted"><?= number_format($sup['total_products'] ?? 0) ?> products</small>
                <?php if (!empty($sup['is_verified'])): ?>
                  <div><span class="badge bg-success mt-1"><i class="fas fa-check-circle me-1"></i>Verified</span></div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <?php for ($i = 0; $i < 6; $i++): ?>
          <div class="col-6 col-md-4 col-lg-2">
            <div class="card text-center h-100 border-0 shadow-sm">
              <div class="card-body py-3">
                <div class="bg-warning rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width:50px;height:50px;"><i class="fas fa-building text-dark"></i></div>
                <div class="placeholder-glow"><span class="placeholder col-10 mb-1"></span><span class="placeholder col-6"></span></div>
              </div>
            </div>
          </div>
        <?php endfor; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- How It Works -->
<section class="how-it-works py-5">
  <div class="container">
    <h2 class="fw-bold text-center mb-4">How It Works</h2>
    <ul class="nav nav-tabs justify-content-center mb-4" id="howTab">
      <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#buyers-tab">For Buyers</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#suppliers-tab">For Suppliers</button></li>
    </ul>
    <div class="tab-content">
      <div class="tab-pane fade show active" id="buyers-tab">
        <div class="row g-4 text-center">
          <div class="col-md-4">
            <div class="p-4"><div class="bg-warning rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;"><span class="fw-bold fs-4">1</span></div><h5>Search &amp; Discover</h5><p class="text-muted">Browse thousands of verified products and suppliers globally.</p></div>
          </div>
          <div class="col-md-4">
            <div class="p-4"><div class="bg-warning rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;"><span class="fw-bold fs-4">2</span></div><h5>Request &amp; Negotiate</h5><p class="text-muted">Send RFQ, negotiate price, and get samples with trade assurance.</p></div>
          </div>
          <div class="col-md-4">
            <div class="p-4"><div class="bg-warning rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;"><span class="fw-bold fs-4">3</span></div><h5>Pay &amp; Receive</h5><p class="text-muted">Secure escrow payment. Receive goods after quality inspection.</p></div>
          </div>
        </div>
      </div>
      <div class="tab-pane fade" id="suppliers-tab">
        <div class="row g-4 text-center">
          <div class="col-md-4">
            <div class="p-4"><div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;"><span class="fw-bold fs-4 text-white">1</span></div><h5>Create Storefront</h5><p class="text-muted">Register, verify your business, and list your products for free.</p></div>
          </div>
          <div class="col-md-4">
            <div class="p-4"><div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;"><span class="fw-bold fs-4 text-white">2</span></div><h5>Receive Orders</h5><p class="text-muted">Get RFQs and orders from global buyers directly to your dashboard.</p></div>
          </div>
          <div class="col-md-4">
            <div class="p-4"><div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;"><span class="fw-bold fs-4 text-white">3</span></div><h5>Ship &amp; Earn</h5><p class="text-muted">Ship goods, track payments in escrow, grow your global business.</p></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CTA Carrier -->
<section class="cta-carrier py-5 bg-dark text-white">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-md-8">
        <h2 class="fw-bold"><i class="fas fa-plane-departure text-warning me-2"></i>Become a Carrier</h2>
        <p class="text-muted mb-0">Traveling abroad? Earn money by carrying packages for others. Set your own price, choose your routes, and help connect the world.</p>
      </div>
      <div class="col-md-4 text-md-end">
        <a href="/pages/carrier/register.php" class="btn btn-warning btn-lg me-2">Join as Carrier</a>
        <a href="/pages/shipment/index.php" class="btn btn-outline-light btn-lg">Learn More</a>
      </div>
    </div>
  </div>
</section>

<!-- CTA Dropshipping -->
<section class="cta-dropship py-5 bg-warning">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-md-8">
        <h2 class="fw-bold"><i class="fas fa-store me-2"></i>Start Dropshipping Today</h2>
        <p class="mb-0">No inventory needed. Connect with suppliers, set your margins, and sell globally — we handle the rest.</p>
      </div>
      <div class="col-md-4 text-md-end">
        <a href="/pages/auth/register.php?type=supplier" class="btn btn-dark btn-lg">Get Started Free</a>
      </div>
    </div>
  </div>
</section>

<script>
// Animated counters
(function() {
  const counters = document.querySelectorAll('.stat-item');
  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const el = entry.target.querySelector('.counter');
        const target = parseInt(entry.target.dataset.target, 10);
        let current = 0;
        const step = Math.ceil(target / 60);
        const timer = setInterval(() => {
          current += step;
          if (current >= target) { current = target; clearInterval(timer); }
          el.textContent = current.toLocaleString();
        }, 30);
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.3 });
  counters.forEach(c => observer.observe(c));
})();
</script>

<?php require_once 'includes/footer.php'; ?>
