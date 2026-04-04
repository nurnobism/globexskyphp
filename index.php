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

$catIcons = ['electronics'=>'cpu-fill','machinery'=>'gear-fill','apparel-fashion'=>'bag-fill','home-garden'=>'house-fill','food-beverage'=>'egg-fried','chemicals'=>'droplet-fill','automotive'=>'car-front-fill','health-beauty'=>'heart-fill','sports-outdoors'=>'bicycle','construction'=>'building-fill'];
?>

<!-- ===== HERO SECTION ===== -->
<section class="text-white position-relative overflow-hidden" style="background:linear-gradient(135deg,#1B4B66 0%,#2563EB 55%,#7c3aed 100%);min-height:520px">
    <div class="container d-flex flex-column justify-content-center py-5" style="min-height:480px">

        <!-- AI Badge -->
        <div class="mb-3">
            <span class="gs-ai-badge">
                <span class="gs-ai-dot"></span>
                AI-Powered B2B Marketplace
            </span>
        </div>

        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <h1 class="display-4 fw-bold mb-3 lh-sm">
                    Your Gateway to<br>
                    <span class="text-warning">Global Trade</span>
                </h1>
                <p class="lead mb-4" style="color:rgba(255,255,255,.8)">
                    Connect with verified suppliers worldwide. Source quality products, request quotes, and manage your entire supply chain in one place.
                </p>

                <!-- Search Bar -->
                <form action="/pages/product/index.php" method="GET" class="mb-4">
                    <div class="d-flex bg-white rounded-pill overflow-hidden shadow" style="max-width:560px">
                        <select name="category" class="border-0 ps-3 pe-2 py-2 text-dark" style="outline:none;font-size:.85rem;background:transparent;min-width:120px;max-width:150px">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div style="width:1px;background:#e5e7eb;margin:8px 0"></div>
                        <input type="text" name="q" class="border-0 flex-fill px-3 py-2 text-dark" placeholder="Search products, suppliers…" style="outline:none;font-size:.9rem">
                        <button type="submit" class="btn btn-primary px-4 m-1 rounded-pill">
                            <i class="bi bi-search me-1"></i>Search
                        </button>
                    </div>
                    <div class="mt-2 d-flex gap-3 flex-wrap">
                        <small style="color:rgba(255,255,255,.65)">Popular:</small>
                        <?php foreach (['Electronics','Machinery','Apparel','Home Decor'] as $kw): ?>
                        <a href="/pages/product/index.php?q=<?= urlencode($kw) ?>" class="text-decoration-none" style="color:rgba(255,255,255,.75);font-size:.82rem"><?= $kw ?></a>
                        <?php endforeach; ?>
                    </div>
                </form>

                <!-- Trust Badges -->
                <div class="d-flex gs-trust-badges flex-wrap">
                    <span class="gs-trust-badge"><i class="bi bi-shield-check"></i>Trade Assurance</span>
                    <span class="gs-trust-badge"><i class="bi bi-patch-check"></i>Verified Suppliers</span>
                    <span class="gs-trust-badge"><i class="bi bi-lock"></i>Secure Payments</span>
                    <span class="gs-trust-badge"><i class="bi bi-truck"></i>Global Shipping</span>
                </div>
            </div>

            <!-- Stats Panel -->
            <div class="col-lg-5 d-none d-lg-block">
                <div class="rounded-4 p-4 ms-auto" style="background:rgba(255,255,255,.1);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.2);max-width:340px">
                    <div class="text-center mb-3">
                        <i class="bi bi-globe2 display-3 text-warning"></i>
                    </div>
                    <div class="row g-3 text-center">
                        <div class="col-6">
                            <div class="h3 fw-bold mb-0 gs-counter-animate"><?= number_format($totalProducts) ?>+</div>
                            <small style="color:rgba(255,255,255,.7)">Products</small>
                        </div>
                        <div class="col-6">
                            <div class="h3 fw-bold mb-0 gs-counter-animate"><?= number_format($totalSuppliers) ?>+</div>
                            <small style="color:rgba(255,255,255,.7)">Verified Suppliers</small>
                        </div>
                        <div class="col-6">
                            <div class="h3 fw-bold mb-0 gs-counter-animate">50+</div>
                            <small style="color:rgba(255,255,255,.7)">Countries</small>
                        </div>
                        <div class="col-6">
                            <div class="h3 fw-bold mb-0 gs-counter-animate">98%</div>
                            <small style="color:rgba(255,255,255,.7)">Satisfaction</small>
                        </div>
                    </div>
                    <hr style="border-color:rgba(255,255,255,.2)">
                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                        <a href="/pages/auth/register.php?role=buyer" class="btn btn-light btn-sm px-3 fw-semibold">Start Buying</a>
                        <a href="/pages/auth/register.php?role=supplier" class="btn btn-warning btn-sm px-3 fw-semibold text-dark">Become Supplier</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SVG Wave Separator -->
    <div class="gs-hero-wave">
        <svg viewBox="0 0 1440 60" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" style="height:50px">
            <path d="M0,30 C240,60 480,0 720,30 C960,60 1200,0 1440,30 L1440,60 L0,60 Z" fill="#f8fafc"/>
        </svg>
    </div>
</section>

<!-- ===== SERVICE HIGHLIGHTS ===== -->
<section class="py-5" style="background:#f8fafc">
    <div class="container">
        <div class="row g-3">
            <div class="col-md-3 col-sm-6">
                <div class="gs-service-card gs-service-card--blue">
                    <div>
                        <div class="gs-service-icon">🌍</div>
                        <div class="gs-service-title">Global Sourcing</div>
                        <div class="gs-service-desc">Source from 50+ countries with verified suppliers</div>
                    </div>
                    <a href="/pages/product/index.php" class="btn-service">Explore now <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="gs-service-card gs-service-card--green">
                    <div>
                        <div class="gs-service-icon">✅</div>
                        <div class="gs-service-title">GlobexSky Guaranteed</div>
                        <div class="gs-service-desc">Trade assurance & money-back guarantee</div>
                    </div>
                    <a href="/pages/rfq/create.php" class="btn-service">Learn more <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="gs-service-card gs-service-card--orange">
                    <div>
                        <div class="gs-service-icon">⚡</div>
                        <div class="gs-service-title">Fast Customization</div>
                        <div class="gs-service-desc">Low MOQ, custom orders, quick turnaround</div>
                    </div>
                    <a href="/pages/rfq/create.php" class="btn-service">Get quote <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="gs-service-card gs-service-card--purple">
                    <div>
                        <div class="gs-service-icon">🚢</div>
                        <div class="gs-service-title">Logistics & Shipping</div>
                        <div class="gs-service-desc">End-to-end logistics from factory to door</div>
                    </div>
                    <a href="/pages/product/index.php" class="btn-service">View plans <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== CATEGORIES + TRENDING SIDEBAR ===== -->
<section class="py-5 bg-white">
    <div class="container">
        <div class="row g-4">
            <!-- Left: Category Sidebar -->
            <div class="col-lg-2 col-md-3 d-none d-md-block">
                <div class="gs-category-sidebar shadow-sm">
                    <div class="px-3 py-2 border-bottom" style="font-size:.8rem;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.05em">Categories</div>
                    <?php foreach ($categories as $cat):
                        $icon = $catIcons[$cat['slug']] ?? 'grid-fill';
                    ?>
                    <a href="/pages/product/index.php?category=<?= urlencode($cat['slug']) ?>" class="gs-cat-sidebar-item">
                        <i class="bi bi-<?= $icon ?>"></i>
                        <span><?= e($cat['name']) ?></span>
                    </a>
                    <?php endforeach; ?>
                    <a href="/pages/product/index.php" class="gs-cat-sidebar-item" style="color:var(--gs-primary);border-top:1px solid #f3f4f6">
                        <i class="bi bi-grid-3x3-gap"></i>
                        <span>All Categories</span>
                    </a>
                </div>
            </div>

            <!-- Right: Trending / Featured Products -->
            <div class="col-lg-10 col-md-9">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="gs-section-label">Marketplace</div>
                        <h2 class="gs-section-title mb-0">Frequently Searched</h2>
                    </div>
                    <a href="/pages/product/index.php" class="btn btn-outline-primary btn-sm">View All <i class="bi bi-arrow-right"></i></a>
                </div>

                <!-- Mobile categories (horizontal scroll) -->
                <div class="d-md-none mb-3">
                    <div class="gs-scroll-row pb-2" style="gap:.5rem">
                        <?php foreach ($categories as $cat):
                            $icon = $catIcons[$cat['slug']] ?? 'grid-fill';
                        ?>
                        <a href="/pages/product/index.php?category=<?= urlencode($cat['slug']) ?>" class="text-decoration-none text-center flex-shrink-0" style="width:72px">
                            <div class="rounded-3 p-2 mb-1" style="background:#eff6ff">
                                <i class="bi bi-<?= $icon ?> text-primary fs-5"></i>
                            </div>
                            <div style="font-size:.7rem;color:#374151;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($cat['name']) ?></div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Featured Product Grid -->
                <?php if (!empty($featured)): ?>
                <div class="row row-cols-2 row-cols-sm-3 row-cols-xl-4 g-3">
                    <?php foreach ($featured as $i => $p):
                        $imgs = json_decode($p['images'] ?? '[]', true);
                        $imgUrl = !empty($imgs[0]) ? e(APP_URL.'/'.$imgs[0]) : 'https://via.placeholder.com/300x160?text=' . urlencode($p['name']);
                        $badges = ['hot','new','trending'];
                        $badgeClass = ['gs-trending-badge--hot','gs-trending-badge--new','gs-trending-badge--trending'];
                    ?>
                    <div class="col">
                        <div class="gs-product-card-modern h-100">
                            <div class="gs-card-img-wrap">
                                <a href="/pages/product/detail.php?slug=<?= urlencode($p['slug']) ?>">
                                    <img src="<?= $imgUrl ?>" alt="<?= e($p['name']) ?>">
                                </a>
                                <?php if ($i < 3): ?>
                                <span class="gs-trending-badge <?= $badgeClass[$i] ?>"><?= $badges[$i] ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="gs-card-body">
                                <div class="gs-product-name">
                                    <a href="/pages/product/detail.php?slug=<?= urlencode($p['slug']) ?>" class="text-decoration-none text-dark"><?= e($p['name']) ?></a>
                                </div>
                                <div class="gs-product-price"><?= formatMoney($p['price']) ?></div>
                                <?php if (!empty($p['min_order'])): ?>
                                <div class="gs-product-moq">MOQ: <?= (int)$p['min_order'] ?> pcs</div>
                                <?php endif; ?>
                                <?php if ($p['rating'] > 0): ?>
                                <div class="mb-1">
                                    <span class="gs-stars"><?= str_repeat('★', starRating($p['rating'])) ?><?= str_repeat('☆', 5-starRating($p['rating'])) ?></span>
                                    <span class="gs-stars-count">(<?= $p['review_count'] ?>)</span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($p['supplier_name'])): ?>
                                <div class="gs-supplier-line">
                                    <span class="gs-verified-dot"></span>
                                    <?= e(mb_strimwidth($p['supplier_name'], 0, 28, '…')) ?>
                                </div>
                                <?php endif; ?>
                                <div class="gs-card-actions">
                                    <form method="POST" action="/api/cart.php?action=add" class="flex-fill">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-cart-plus me-1"></i>Cart</button>
                                    </form>
                                    <a href="/pages/rfq/create.php?product=<?= $p['id'] ?>" class="btn btn-primary flex-fill">Quote</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-box-seam display-3 mb-3 d-block opacity-25"></i>
                    <p>Products coming soon — <a href="/pages/auth/register.php?role=supplier">become a supplier</a> to list yours.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ===== TOP DEALS (Horizontal Scroll) ===== -->
<?php if (!empty($featured)): ?>
<section class="py-5" style="background:#f8fafc">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <div class="gs-section-label">Limited Offers</div>
                <h2 class="gs-section-title mb-0">🔥 Top Deals</h2>
            </div>
            <a href="/pages/product/index.php?sort=created_at&dir=desc" class="btn btn-outline-primary btn-sm">View All <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="gs-scroll-row">
            <?php foreach ($featured as $p):
                $imgs = json_decode($p['images'] ?? '[]', true);
                $imgUrl = !empty($imgs[0]) ? e(APP_URL.'/'.$imgs[0]) : 'https://via.placeholder.com/200x160?text=' . urlencode($p['name']);
            ?>
            <div class="gs-product-card-modern">
                <div class="gs-card-img-wrap">
                    <a href="/pages/product/detail.php?slug=<?= urlencode($p['slug']) ?>">
                        <img src="<?= $imgUrl ?>" alt="<?= e($p['name']) ?>">
                    </a>
                    <span class="gs-trending-badge gs-trending-badge--hot">Deal</span>
                </div>
                <div class="gs-card-body">
                    <div class="gs-product-name">
                        <a href="/pages/product/detail.php?slug=<?= urlencode($p['slug']) ?>" class="text-decoration-none text-dark"><?= e($p['name']) ?></a>
                    </div>
                    <div class="gs-product-price"><?= formatMoney($p['price']) ?></div>
                    <?php if (!empty($p['min_order'])): ?>
                    <div class="gs-product-moq">MOQ: <?= (int)$p['min_order'] ?> pcs</div>
                    <?php endif; ?>
                    <?php if (!empty($p['supplier_name'])): ?>
                    <div class="gs-supplier-line mt-1">
                        <span class="gs-verified-dot"></span>
                        <?= e(mb_strimwidth($p['supplier_name'], 0, 22, '…')) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ===== TOP RANKING ===== -->
<section class="py-5 bg-white">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <div class="gs-section-label">Data-Driven</div>
                <h2 class="gs-section-title mb-0">📊 Top Ranking</h2>
                <p class="gs-section-sub">Navigate trends with market rankings</p>
            </div>
        </div>
        <div class="row g-3">
            <?php
            $rankingData = [
                ['Electronics & Gadgets','cpu-fill','#3b82f6','Hot Selling'],
                ['Industrial Machinery','gear-fill','#f97316','Trending Up'],
                ['Apparel & Fashion','bag-fill','#ec4899','New Season'],
                ['Home & Garden','house-fill','#16a34a','Best Value'],
                ['Food & Beverage','egg-fried','#f59e0b','Popular'],
                ['Health & Beauty','heart-fill','#8b5cf6','Rising'],
            ];
            foreach ($rankingData as $idx => [$name, $icon, $color, $label]):
            ?>
            <div class="col-md-4 col-sm-6">
                <a href="/pages/product/index.php" class="text-decoration-none">
                    <div class="gs-rank-card p-3 d-flex align-items-center gap-3">
                        <span class="gs-rank-num <?= $idx < 3 ? 'gs-rank-num--top' : '' ?>">#<?= $idx+1 ?></span>
                        <div class="rounded-3 p-2 flex-shrink-0" style="background:<?= $color ?>22">
                            <i class="bi bi-<?= $icon ?>" style="font-size:1.4rem;color:<?= $color ?>"></i>
                        </div>
                        <div class="flex-fill">
                            <div style="font-size:.88rem;font-weight:600;color:#111827"><?= $name ?></div>
                            <span class="badge" style="background:<?= $color ?>22;color:<?= $color ?>;font-size:.7rem"><?= $label ?></span>
                        </div>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===== TAILORED SELECTIONS ===== -->
<section class="py-5" style="background:#f8fafc">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <div class="gs-section-label">Curated for You</div>
                <h2 class="gs-section-title mb-0">✨ Tailored Selections</h2>
            </div>
            <a href="/pages/product/index.php" class="btn btn-outline-primary btn-sm">Browse All</a>
        </div>
        <?php
        $selections = [
            ['Office Supplies','briefcase','#3b82f6'],
            ['Home Decor','house-heart','#ec4899'],
            ['Electronics','laptop','#8b5cf6'],
            ['Industrial Tools','tools','#f97316'],
        ];
        ?>
        <div class="row g-3">
            <?php foreach ($selections as [$selName, $selIcon, $selColor]): ?>
            <div class="col-lg-3 col-md-6">
                <a href="/pages/product/index.php?q=<?= urlencode($selName) ?>" class="text-decoration-none">
                    <div class="gs-selection-card">
                        <div class="gs-selection-header d-flex align-items-center gap-2">
                            <i class="bi bi-<?= $selIcon ?>" style="color:<?= $selColor ?>"></i>
                            <?= $selName ?>
                        </div>
                        <div class="gs-selection-grid">
                            <?php for ($t = 0; $t < 4; $t++): ?>
                            <img src="https://via.placeholder.com/150x80/<?= ltrim($selColor,'#') ?>/ffffff?text=<?= urlencode($selName) ?>" alt="<?= $selName ?>">
                            <?php endfor; ?>
                        </div>
                        <div class="gs-selection-footer">
                            <i class="bi bi-arrow-right-circle me-1" style="color:<?= $selColor ?>"></i>
                            Explore collection
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===== AI FEATURES SHOWCASE ===== -->
<section class="py-5 bg-white">
    <div class="container">
        <div class="text-center mb-5">
            <div class="gs-section-label">Powered by Technology</div>
            <h2 class="gs-section-title">🤖 AI-Powered Features</h2>
            <p class="gs-section-sub">Smart tools to streamline your B2B sourcing experience</p>
        </div>
        <?php
        $aiFeatures = [
            ['bi-search','AI Smart Search','linear-gradient(135deg,#2563EB,#1d4ed8)','Find products instantly with AI-powered semantic search','Search'],
            ['bi-camera','Image Search','linear-gradient(135deg,#7c3aed,#5b21b6)','Upload a photo to find matching products','Coming Soon'],
            ['bi-mic','Voice Search','linear-gradient(135deg,#16a34a,#15803d)','Search hands-free with voice commands','Coming Soon'],
            ['bi-graph-up-arrow','Price Prediction','linear-gradient(135deg,#f97316,#c2410c)','AI forecasts market price trends for smart buying','Explore'],
            ['bi-translate','AI Translation','linear-gradient(135deg,#0891b2,#0e7490)','Break language barriers with real-time translation','Active'],
            ['bi-robot','Smart Matching','linear-gradient(135deg,#db2777,#be185d)','AI matches your needs to the best suppliers','Try Now'],
        ];
        ?>
        <div class="row row-cols-2 row-cols-md-3 row-cols-xl-6 g-3">
            <?php foreach ($aiFeatures as [$icon, $title, $grad, $desc, $label]): ?>
            <div class="col">
                <div class="gs-ai-feature-card">
                    <div class="gs-ai-icon" style="background:<?= $grad ?>">
                        <i class="bi <?= $icon ?> text-white"></i>
                    </div>
                    <h6><?= $title ?></h6>
                    <p><?= $desc ?></p>
                    <span class="badge bg-primary bg-opacity-10 text-primary mt-2" style="font-size:.7rem"><?= $label ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===== NEW ARRIVALS ===== -->
<?php if (!empty($recent)): ?>
<section class="py-5" style="background:#f8fafc">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <div class="gs-section-label">Just In</div>
                <h2 class="gs-section-title mb-0"><i class="bi bi-clock-history text-primary me-1"></i>New Arrivals</h2>
            </div>
            <a href="/pages/product/index.php?sort=created_at&dir=desc" class="btn btn-outline-primary btn-sm">View All <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 g-3">
            <?php foreach ($recent as $p):
                $imgs = json_decode($p['images'] ?? '[]', true);
                $imgUrl = !empty($imgs[0]) ? e(APP_URL.'/'.$imgs[0]) : 'https://via.placeholder.com/300x160?text=' . urlencode($p['name']);
            ?>
            <div class="col">
                <div class="gs-product-card-modern h-100">
                    <div class="gs-card-img-wrap">
                        <a href="/pages/product/detail.php?slug=<?= urlencode($p['slug']) ?>">
                            <img src="<?= $imgUrl ?>" alt="<?= e($p['name']) ?>">
                        </a>
                        <span class="gs-trending-badge gs-trending-badge--new">New</span>
                    </div>
                    <div class="gs-card-body">
                        <div class="gs-product-name">
                            <a href="/pages/product/detail.php?slug=<?= urlencode($p['slug']) ?>" class="text-decoration-none text-dark"><?= e($p['name']) ?></a>
                        </div>
                        <div class="gs-product-price"><?= formatMoney($p['price']) ?></div>
                        <?php if (!empty($p['min_order'])): ?>
                        <div class="gs-product-moq">MOQ: <?= (int)$p['min_order'] ?> pcs</div>
                        <?php endif; ?>
                        <?php if (!empty($p['supplier_name'])): ?>
                        <div class="gs-supplier-line">
                            <span class="gs-verified-dot"></span>
                            <?= e(mb_strimwidth($p['supplier_name'], 0, 28, '…')) ?>
                        </div>
                        <?php endif; ?>
                        <div class="gs-card-actions">
                            <form method="POST" action="/api/cart.php?action=add" class="flex-fill">
                                <?= csrfField() ?>
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-cart-plus"></i></button>
                            </form>
                            <a href="/pages/rfq/create.php?product=<?= $p['id'] ?>" class="btn btn-primary flex-fill">Quote</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ===== HOW IT WORKS (Modernized) ===== -->
<section class="py-5 bg-white">
    <div class="container">
        <div class="text-center mb-5">
            <div class="gs-section-label">Simple Process</div>
            <h2 class="gs-section-title">How GlobexSky Works</h2>
            <p class="gs-section-sub">Start trading globally in 4 simple steps</p>
        </div>
        <div class="row g-4">
            <?php foreach ([
                ['1','bi-person-plus-fill','Create Account','Sign up as a buyer or supplier in minutes. Verification takes 1-2 business days.'],
                ['2','bi-search','Find & Connect','Browse products, explore suppliers, or submit an RFQ to get competitive quotes.'],
                ['3','bi-handshake-fill','Negotiate & Order','Compare quotes, negotiate terms, and place your order with confidence.'],
                ['4','bi-truck','Receive & Review','Track your shipment and leave a review after receiving your order.'],
            ] as [$step, $icon, $title, $desc]): ?>
            <div class="col-md-3 gs-how-step">
                <div class="gs-step-circle">
                    <i class="bi <?= $icon ?>"></i>
                    <span class="gs-step-num"><?= $step ?></span>
                </div>
                <h5 class="fw-bold mb-2"><?= $title ?></h5>
                <p class="text-muted" style="font-size:.88rem"><?= $desc ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===== CTA SECTION ===== -->
<section class="py-5 text-white gs-cta-section">
    <div class="container position-relative" style="z-index:1">
        <div class="text-center mb-5">
            <h2 class="fw-bold display-6 mb-2">Ready to Grow Your Business?</h2>
            <p class="lead mb-1" style="color:rgba(255,255,255,.8)">Join businesses across 50+ countries already trading on GlobexSky</p>
            <div class="d-flex justify-content-center gap-3 flex-wrap mt-2">
                <span class="gs-trust-badge"><i class="bi bi-check-circle-fill text-success"></i>Free to Register</span>
                <span class="gs-trust-badge"><i class="bi bi-check-circle-fill text-success"></i>No Hidden Fees</span>
                <span class="gs-trust-badge"><i class="bi bi-check-circle-fill text-success"></i>Cancel Anytime</span>
            </div>
        </div>
        <div class="row g-4 justify-content-center">
            <div class="col-md-5">
                <div class="gs-cta-card text-center">
                    <i class="bi bi-bag-check display-4 mb-3 text-warning d-block"></i>
                    <h4 class="fw-bold mb-2">I'm a Buyer</h4>
                    <p style="color:rgba(255,255,255,.8);font-size:.9rem" class="mb-3">Source from thousands of verified suppliers. Get competitive quotes instantly.</p>
                    <ul class="list-unstyled text-start mb-4 mx-auto" style="max-width:220px;font-size:.85rem;color:rgba(255,255,255,.8)">
                        <li class="mb-1"><i class="bi bi-check2 text-success me-2"></i>Browse 1M+ products</li>
                        <li class="mb-1"><i class="bi bi-check2 text-success me-2"></i>Request free quotes</li>
                        <li><i class="bi bi-check2 text-success me-2"></i>Trade Assurance protection</li>
                    </ul>
                    <a href="/pages/auth/register.php?role=buyer" class="btn btn-light btn-lg px-5 fw-semibold">Start Buying Free</a>
                </div>
            </div>
            <div class="col-md-5">
                <div class="gs-cta-card text-center">
                    <i class="bi bi-shop display-4 mb-3 text-warning d-block"></i>
                    <h4 class="fw-bold mb-2">I'm a Supplier</h4>
                    <p style="color:rgba(255,255,255,.8);font-size:.9rem" class="mb-3">Reach millions of global buyers. Grow your export business today.</p>
                    <ul class="list-unstyled text-start mb-4 mx-auto" style="max-width:220px;font-size:.85rem;color:rgba(255,255,255,.8)">
                        <li class="mb-1"><i class="bi bi-check2 text-success me-2"></i>List products for free</li>
                        <li class="mb-1"><i class="bi bi-check2 text-success me-2"></i>Receive buyer inquiries</li>
                        <li><i class="bi bi-check2 text-success me-2"></i>Get verified badge</li>
                    </ul>
                    <a href="/pages/auth/register.php?role=supplier" class="btn btn-warning btn-lg px-5 fw-semibold text-dark">Become a Supplier</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== TRUST & SECURITY ===== -->
<section class="py-5 gs-trust-section">
    <div class="container">
        <div class="text-center mb-4">
            <div class="gs-section-label">Safe & Secure</div>
            <h2 class="gs-section-title">Trusted Worldwide</h2>
            <p class="gs-section-sub">Secure, transparent B2B trading across 50+ countries</p>
        </div>
        <div class="row g-3 justify-content-center mb-4">
            <?php
            $certBadges = [
                ['bi-shield-lock-fill','SSL Secured'],
                ['bi-credit-card','PCI DSS Compliant'],
                ['bi-patch-check-fill','Verified Suppliers'],
                ['bi-award','ISO Certified'],
                ['bi-lock-fill','Data Protected'],
                ['bi-eye-slash','Privacy First'],
            ];
            foreach ($certBadges as [$icon, $label]): ?>
            <div class="col-auto">
                <div class="gs-trust-cert-badge">
                    <i class="bi <?= $icon ?>"></i><?= $label ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="row g-3 text-center">
            <div class="col-md-3 col-6">
                <div class="p-3">
                    <div class="h2 fw-bold text-primary mb-0"><?= number_format($totalProducts) ?>+</div>
                    <div class="text-muted small">Active Products</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3">
                    <div class="h2 fw-bold text-primary mb-0"><?= number_format($totalSuppliers) ?>+</div>
                    <div class="text-muted small">Verified Suppliers</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3">
                    <div class="h2 fw-bold text-primary mb-0">50+</div>
                    <div class="text-muted small">Countries Served</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3">
                    <div class="h2 fw-bold text-primary mb-0">10K+</div>
                    <div class="text-muted small">Businesses Trading</div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

