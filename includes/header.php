<?php
// includes/header.php — Global header, meta tags, and navbar

if (session_status() === PHP_SESSION_NONE) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME ?? 7200,
        'path'     => '/',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Resolve logged-in user from session or remember-me cookie
if (!isset($_SESSION['user']) && isset($_COOKIE['remember_token'])) {
    require_once __DIR__ . '/../includes/auth.php';
    checkRememberToken();
}

$currentUser   = $_SESSION['user'] ?? null;
$isLoggedIn    = $currentUser !== null;
$userRole      = $currentUser['role'] ?? 'guest';
$userAvatar    = $currentUser['avatar'] ?? null;
$userName      = $currentUser['name'] ?? '';

// Cart count
$cartCount = 0;
if ($isLoggedIn) {
    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(ci.quantity), 0)
             FROM cart c
             JOIN cart_items ci ON ci.cart_id = c.id
             WHERE c.user_id = ?"
        );
        $stmt->execute([$currentUser['id']]);
        $cartCount = (int)$stmt->fetchColumn();
    } catch (Throwable) {
        $cartCount = 0;
    }
} elseif (!empty($_SESSION['cart'])) {
    $cartCount = array_sum(array_column($_SESSION['cart'], 'quantity'));
}

// Unread notification count
$notifCount = 0;
if ($isLoggedIn) {
    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$currentUser['id']]);
        $notifCount = (int)$stmt->fetchColumn();
    } catch (Throwable) {
        $notifCount = 0;
    }
}

// Active language and currency (stored in session or defaults)
$activeLang     = $_SESSION['lang']     ?? APP_LOCALE    ?? 'en';
$activeCurrency = $_SESSION['currency'] ?? APP_CURRENCY  ?? 'USD';

// CSRF token for forms in header (search, etc.)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Current page URL for OG tags
$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'globexsky.com')
    . ($_SERVER['REQUEST_URI'] ?? '/');

// Allow individual pages to set these before including header
$pageTitle       = $pageTitle       ?? 'GlobexSky — Global B2B Marketplace & Logistics Platform';
$pageDescription = $pageDescription ?? 'GlobexSky connects buyers, suppliers, and carriers worldwide. Source products, ship parcels, and scale your business with AI-powered tools.';
$pageOgImage     = $pageOgImage     ?? (APP_URL . '/assets/images/og-default.jpg');
$pageKeywords    = $pageKeywords    ?? 'B2B marketplace, product sourcing, dropshipping, international shipping, wholesale';

$languages = [
    'en' => ['label' => 'English',    'flag' => '🇺🇸'],
    'bn' => ['label' => 'বাংলা',       'flag' => '🇧🇩'],
    'ar' => ['label' => 'العربية',     'flag' => '🇸🇦'],
    'hi' => ['label' => 'हिन्दी',       'flag' => '🇮🇳'],
    'zh' => ['label' => '中文',         'flag' => '🇨🇳'],
    'fr' => ['label' => 'Français',    'flag' => '🇫🇷'],
    'es' => ['label' => 'Español',     'flag' => '🇪🇸'],
];

$currencies = [
    'USD' => ['symbol' => '$',  'name' => 'US Dollar'],
    'BDT' => ['symbol' => '৳', 'name' => 'Bangladeshi Taka'],
    'EUR' => ['symbol' => '€',  'name' => 'Euro'],
    'GBP' => ['symbol' => '£',  'name' => 'British Pound'],
    'CNY' => ['symbol' => '¥',  'name' => 'Chinese Yuan'],
    'INR' => ['symbol' => '₹',  'name' => 'Indian Rupee'],
    'AED' => ['symbol' => 'د.إ', 'name' => 'UAE Dirham'],
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($activeLang) ?>" <?= $activeLang === 'ar' ? 'dir="rtl"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <!-- SEO Meta -->
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description"  content="<?= htmlspecialchars($pageDescription) ?>">
    <meta name="keywords"     content="<?= htmlspecialchars($pageKeywords) ?>">
    <meta name="author"       content="GlobexSky">
    <meta name="robots"       content="index, follow">
    <link rel="canonical"     href="<?= htmlspecialchars($currentUrl) ?>">

    <!-- Open Graph -->
    <meta property="og:type"        content="website">
    <meta property="og:title"       content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta property="og:url"         content="<?= htmlspecialchars($currentUrl) ?>">
    <meta property="og:image"       content="<?= htmlspecialchars($pageOgImage) ?>">
    <meta property="og:site_name"   content="GlobexSky">

    <!-- Twitter Card -->
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="<?= htmlspecialchars($pageTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta name="twitter:image"       content="<?= htmlspecialchars($pageOgImage) ?>">

    <!-- Favicon -->
    <link rel="icon"             type="image/x-icon" href="<?= APP_URL ?>/assets/images/favicon.ico">
    <link rel="apple-touch-icon"                     href="<?= APP_URL ?>/assets/images/apple-touch-icon.png">

    <!-- Bootstrap 5.3 CSS -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">

    <!-- Font Awesome 6.5 -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
          integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
          crossorigin="anonymous"
          referrerpolicy="no-referrer">

    <!-- RTL Bootstrap for Arabic -->
    <?php if ($activeLang === 'ar'): ?>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css"
          crossorigin="anonymous">
    <?php endif; ?>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --gs-primary:     #0d6efd;
            --gs-dark:        #0a0f1e;
            --gs-dark-2:      #111827;
            --gs-accent:      #00d4ff;
            --gs-gradient:    linear-gradient(135deg, #0d6efd 0%, #00d4ff 100%);
        }

        body { font-family: 'Inter', sans-serif; }

        /* ── Topbar ─────────────────────────────────────────── */
        .gs-topbar {
            background: var(--gs-dark);
            font-size: .8rem;
            border-bottom: 1px solid rgba(255,255,255,.07);
        }
        .gs-topbar a        { color: rgba(255,255,255,.7); text-decoration: none; }
        .gs-topbar a:hover  { color: #fff; }
        .gs-topbar .badge   { font-size: .7rem; }

        /* ── Navbar ─────────────────────────────────────────── */
        .gs-navbar {
            background: var(--gs-dark-2);
            border-bottom: 1px solid rgba(255,255,255,.08);
            padding: .6rem 0;
        }
        .gs-navbar .navbar-brand .brand-logo {
            font-size: 1.45rem;
            font-weight: 800;
            letter-spacing: -.5px;
            background: var(--gs-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .gs-navbar .navbar-brand .brand-tagline {
            font-size: .6rem;
            color: rgba(255,255,255,.45);
            letter-spacing: .5px;
            text-transform: uppercase;
            display: block;
            line-height: 1;
        }

        /* Nav links */
        .gs-navbar .nav-link {
            color: rgba(255,255,255,.8) !important;
            font-weight: 500;
            font-size: .88rem;
            padding: .5rem .75rem !important;
            border-radius: .375rem;
            transition: background .18s, color .18s;
        }
        .gs-navbar .nav-link:hover,
        .gs-navbar .nav-link.active {
            color: #fff !important;
            background: rgba(255,255,255,.07);
        }
        .gs-navbar .nav-link .fa-chevron-down {
            font-size: .65rem;
            vertical-align: middle;
            margin-left: 3px;
            transition: transform .2s;
        }
        .gs-navbar .dropdown:hover .nav-link .fa-chevron-down,
        .gs-navbar .dropdown.show .nav-link .fa-chevron-down { transform: rotate(180deg); }

        /* Mega dropdown */
        .mega-dropdown { position: static !important; }
        .mega-menu {
            position: absolute !important;
            left: 0; right: 0;
            width: 100%;
            background: #161d2e;
            border: none;
            border-top: 2px solid var(--gs-primary);
            border-radius: 0 0 .75rem .75rem;
            box-shadow: 0 20px 60px rgba(0,0,0,.4);
            padding: 2rem;
            margin-top: 0 !important;
        }
        .mega-menu .mega-col-title {
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--gs-accent);
            font-weight: 700;
            margin-bottom: .75rem;
        }
        .mega-menu .dropdown-item {
            color: rgba(255,255,255,.75);
            font-size: .875rem;
            padding: .35rem .5rem;
            border-radius: .3rem;
            transition: background .15s, color .15s;
        }
        .mega-menu .dropdown-item:hover {
            background: rgba(13,110,253,.15);
            color: #fff;
        }
        .mega-menu .dropdown-item .fa { width: 1.2rem; color: var(--gs-primary); }
        .mega-menu .mega-feature-card {
            background: linear-gradient(135deg, rgba(13,110,253,.15), rgba(0,212,255,.1));
            border: 1px solid rgba(13,110,253,.25);
            border-radius: .5rem;
            padding: 1rem;
        }
        .mega-menu .mega-feature-card h6 { color: #fff; font-size: .875rem; }
        .mega-menu .mega-feature-card p  { color: rgba(255,255,255,.6); font-size: .75rem; }

        /* Standard dropdown */
        .gs-navbar .dropdown-menu {
            background: #161d2e;
            border: 1px solid rgba(255,255,255,.1);
            border-radius: .5rem;
            box-shadow: 0 10px 40px rgba(0,0,0,.35);
            min-width: 180px;
        }
        .gs-navbar .dropdown-item {
            color: rgba(255,255,255,.8);
            font-size: .85rem;
            padding: .45rem 1rem;
        }
        .gs-navbar .dropdown-item:hover,
        .gs-navbar .dropdown-item.active {
            background: rgba(13,110,253,.15);
            color: #fff;
        }
        .gs-navbar .dropdown-divider { border-color: rgba(255,255,255,.1); }

        /* Search bar */
        .gs-search { max-width: 380px; }
        .gs-search .form-control {
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.15);
            color: #fff;
            font-size: .875rem;
            border-radius: .5rem 0 0 .5rem;
        }
        .gs-search .form-control::placeholder { color: rgba(255,255,255,.4); }
        .gs-search .form-control:focus {
            background: rgba(255,255,255,.1);
            border-color: var(--gs-primary);
            box-shadow: none;
            color: #fff;
        }
        .gs-search .btn-search {
            background: var(--gs-gradient);
            border: none;
            color: #fff;
            border-radius: 0 .5rem .5rem 0;
            padding: .45rem .85rem;
            font-size: .875rem;
        }
        .gs-search .btn-search:hover { opacity: .9; }

        /* Icon buttons */
        .gs-icon-btn {
            color: rgba(255,255,255,.75);
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: .5rem;
            padding: .42rem .7rem;
            font-size: .95rem;
            text-decoration: none;
            transition: background .18s, color .18s;
            position: relative;
            display: inline-flex;
            align-items: center;
        }
        .gs-icon-btn:hover { background: rgba(255,255,255,.12); color: #fff; }
        .gs-icon-btn .badge {
            position: absolute;
            top: -6px; right: -6px;
            font-size: .6rem;
            min-width: 16px; height: 16px;
            line-height: 16px;
            padding: 0 4px;
            border-radius: 8px;
        }

        /* User avatar */
        .gs-avatar {
            width: 32px; height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(13,110,253,.5);
        }
        .gs-avatar-placeholder {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: var(--gs-gradient);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .75rem;
            font-weight: 700;
            color: #fff;
        }

        /* Auth buttons */
        .btn-gs-login    { border: 1px solid rgba(255,255,255,.2); color: rgba(255,255,255,.85); font-size: .84rem; border-radius: .4rem; }
        .btn-gs-login:hover { background: rgba(255,255,255,.07); color: #fff; border-color: rgba(255,255,255,.4); }
        .btn-gs-register { background: var(--gs-gradient); border: none; color: #fff; font-size: .84rem; border-radius: .4rem; font-weight: 600; }
        .btn-gs-register:hover { opacity: .9; color: #fff; }

        /* Mobile adjustments */
        @media (max-width: 991.98px) {
            .gs-topbar { display: none; }
            .mega-menu { position: static !important; box-shadow: none; border-radius: 0; }
            .gs-search  { max-width: 100%; margin: .5rem 0; }
            .gs-navbar .navbar-nav { padding: .5rem 0; }
        }
    </style>
</head>
<body>

<!-- ══════════════════════ TOP BAR ══════════════════════ -->
<div class="gs-topbar py-1 d-none d-lg-block">
    <div class="container-fluid px-4">
        <div class="row align-items-center">
            <div class="col-auto">
                <span class="text-white-50 me-3">
                    <i class="fa fa-phone-alt me-1"></i>+1 (800) GLOBEX-SKY
                </span>
                <span class="text-white-50">
                    <i class="fa fa-envelope me-1"></i>support@globexsky.com
                </span>
            </div>
            <div class="col text-end d-flex align-items-center justify-content-end gap-3">

                <!-- Language Selector -->
                <div class="dropdown">
                    <a class="dropdown-toggle d-flex align-items-center gap-1"
                       href="#" data-bs-toggle="dropdown" aria-expanded="false">
                        <span><?= $languages[$activeLang]['flag'] ?></span>
                        <span><?= strtoupper($activeLang) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" style="min-width:160px">
                        <?php foreach ($languages as $code => $lang): ?>
                        <li>
                            <a class="dropdown-item <?= $code === $activeLang ? 'active' : '' ?>"
                               href="<?= APP_URL ?>/api/set-language?lang=<?= $code ?>&redirect=<?= urlencode($currentUrl) ?>">
                                <?= $lang['flag'] ?> <?= htmlspecialchars($lang['label']) ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Currency Selector -->
                <div class="dropdown">
                    <a class="dropdown-toggle d-flex align-items-center gap-1"
                       href="#" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa fa-coins fa-xs me-1"></i>
                        <?= htmlspecialchars($activeCurrency) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" style="min-width:170px">
                        <?php foreach ($currencies as $code => $cur): ?>
                        <li>
                            <a class="dropdown-item <?= $code === $activeCurrency ? 'active' : '' ?>"
                               href="<?= APP_URL ?>/api/set-currency?currency=<?= $code ?>&redirect=<?= urlencode($currentUrl) ?>">
                                <span class="fw-bold me-1"><?= htmlspecialchars($cur['symbol']) ?></span>
                                <?= htmlspecialchars($cur['name']) ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <a href="<?= APP_URL ?>/pages/blog">Blog</a>
                <a href="<?= APP_URL ?>/pages/support">Help Center</a>

                <?php if (!$isLoggedIn): ?>
                <a href="<?= APP_URL ?>/pages/auth/login">Sign In</a>
                <a href="<?= APP_URL ?>/pages/auth/register"
                   class="badge rounded-pill"
                   style="background:var(--gs-gradient);color:#fff;text-decoration:none;padding:.3rem .75rem">
                    Get Started Free
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<!-- ── End Top Bar ─────────────────────────────────────── -->


<!-- ══════════════════════ MAIN NAVBAR ══════════════════════ -->
<nav class="gs-navbar navbar navbar-expand-lg sticky-top" id="mainNavbar">
    <div class="container-fluid px-3 px-lg-4">

        <!-- Brand -->
        <a class="navbar-brand me-lg-4" href="<?= APP_URL ?>">
            <div class="brand-logo">
                <i class="fa fa-globe-asia me-1" style="background:var(--gs-gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text"></i>
                GlobexSky
            </div>
            <span class="brand-tagline">Global B2B Marketplace</span>
        </a>

        <!-- Mobile right-side icons (before toggler) -->
        <div class="d-flex align-items-center gap-2 ms-auto me-2 d-lg-none">
            <a href="<?= APP_URL ?>/pages/cart" class="gs-icon-btn">
                <i class="fa fa-shopping-cart"></i>
                <?php if ($cartCount > 0): ?>
                <span class="badge bg-danger"><?= min($cartCount, 99) ?></span>
                <?php endif; ?>
            </a>
        </div>

        <!-- Hamburger -->
        <button class="navbar-toggler border-0 p-1" type="button"
                data-bs-toggle="collapse" data-bs-target="#navbarMain"
                aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon" style="filter:invert(1)"></span>
        </button>

        <!-- Collapsible content -->
        <div class="collapse navbar-collapse" id="navbarMain">

            <!-- ── Mega Menu Nav ─────────────────────────── -->
            <ul class="navbar-nav me-auto align-items-lg-center">

                <!-- Product Sourcing Mega Menu -->
                <li class="nav-item dropdown mega-dropdown">
                    <a class="nav-link dropdown-toggle" href="#"
                       data-bs-toggle="dropdown" data-bs-auto-close="outside"
                       aria-expanded="false">
                        <i class="fa fa-boxes-stacked me-1"></i>Product Sourcing
                        <i class="fa fa-chevron-down ms-1"></i>
                    </a>
                    <div class="dropdown-menu mega-menu">
                        <div class="row g-4">
                            <div class="col-lg-3 col-md-6">
                                <div class="mega-col-title">Find Products</div>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/product">
                                    <i class="fa fa-search me-2"></i>Browse Products
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/rfq">
                                    <i class="fa fa-file-alt me-2"></i>Post RFQ
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/sourcing">
                                    <i class="fa fa-handshake me-2"></i>Sourcing Requests
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/product?featured=1">
                                    <i class="fa fa-star me-2"></i>Featured Products
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/product?new=1">
                                    <i class="fa fa-plus-circle me-2"></i>New Arrivals
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <div class="mega-col-title">Categories</div>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/product?category=electronics">
                                    <i class="fa fa-microchip me-2"></i>Electronics
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/product?category=fashion">
                                    <i class="fa fa-tshirt me-2"></i>Fashion &amp; Apparel
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/product?category=home-garden">
                                    <i class="fa fa-home me-2"></i>Home &amp; Garden
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/product?category=beauty">
                                    <i class="fa fa-spa me-2"></i>Beauty &amp; Health
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/product?category=sports">
                                    <i class="fa fa-football-ball me-2"></i>Sports
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/product">
                                    <i class="fa fa-th-large me-2"></i>All Categories →
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <div class="mega-col-title">Verified Suppliers</div>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/supplier">
                                    <i class="fa fa-building me-2"></i>Browse Suppliers
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/supplier?verified=1">
                                    <i class="fa fa-shield-alt me-2"></i>Verified Only
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/inspection">
                                    <i class="fa fa-clipboard-check me-2"></i>Quality Inspection
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/trade-shows">
                                    <i class="fa fa-store me-2"></i>Trade Shows
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/livestream">
                                    <i class="fa fa-video me-2"></i>Live Sourcing
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <div class="mega-feature-card">
                                    <div class="mb-2">
                                        <span class="badge" style="background:var(--gs-gradient)">AI Powered</span>
                                    </div>
                                    <h6><i class="fa fa-robot me-1"></i>Smart Sourcing</h6>
                                    <p>Let AI match you with the best suppliers based on your requirements, budget, and delivery timeline.</p>
                                    <a href="<?= APP_URL ?>/pages/ai" class="btn btn-sm btn-outline-info">
                                        Try AI Sourcing
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </li><!-- /Product Sourcing -->

                <!-- Shipments Mega Menu -->
                <li class="nav-item dropdown mega-dropdown">
                    <a class="nav-link dropdown-toggle" href="#"
                       data-bs-toggle="dropdown" data-bs-auto-close="outside"
                       aria-expanded="false">
                        <i class="fa fa-truck me-1"></i>Shipments
                        <i class="fa fa-chevron-down ms-1"></i>
                    </a>
                    <div class="dropdown-menu mega-menu">
                        <div class="row g-4">
                            <div class="col-lg-3 col-md-6">
                                <div class="mega-col-title">Parcel Services</div>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/shipment">
                                    <i class="fa fa-box me-2"></i>Send a Parcel
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/shipment/track">
                                    <i class="fa fa-map-marker-alt me-2"></i>Track Shipment
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/shipment/rates">
                                    <i class="fa fa-calculator me-2"></i>Rate Calculator
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/shipment/history">
                                    <i class="fa fa-history me-2"></i>My Parcels
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <div class="mega-col-title">Carry Service</div>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/carry">
                                    <i class="fa fa-plane me-2"></i>How Carry Works
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/carry/travelers">
                                    <i class="fa fa-user-friends me-2"></i>Find Travelers
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/carry/register">
                                    <i class="fa fa-id-badge me-2"></i>Become a Carrier
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/carry/earnings">
                                    <i class="fa fa-wallet me-2"></i>Carrier Earnings
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <div class="mega-col-title">Trade Finance</div>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/trade-finance">
                                    <i class="fa fa-shield-alt me-2"></i>Escrow Protection
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/trade-finance/insurance">
                                    <i class="fa fa-file-contract me-2"></i>Cargo Insurance
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/trade-finance/letter-of-credit">
                                    <i class="fa fa-university me-2"></i>Letter of Credit
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <div class="mega-feature-card">
                                    <h6><i class="fa fa-qrcode me-1"></i>QR Delivery</h6>
                                    <p>Every Carry delivery gets a unique QR code for secure handoff verification between carrier and buyer.</p>
                                    <a href="<?= APP_URL ?>/pages/carry" class="btn btn-sm btn-outline-info">Learn More</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </li><!-- /Shipments -->

                <!-- Dropshipping Mega Menu -->
                <li class="nav-item dropdown mega-dropdown">
                    <a class="nav-link dropdown-toggle" href="#"
                       data-bs-toggle="dropdown" data-bs-auto-close="outside"
                       aria-expanded="false">
                        <i class="fa fa-store me-1"></i>Dropshipping
                        <i class="fa fa-chevron-down ms-1"></i>
                    </a>
                    <div class="dropdown-menu mega-menu">
                        <div class="row g-4">
                            <div class="col-lg-3 col-md-6">
                                <div class="mega-col-title">Get Started</div>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/dropshipping">
                                    <i class="fa fa-rocket me-2"></i>How It Works
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/dropshipping/products">
                                    <i class="fa fa-th me-2"></i>Browse Products
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/dropshipping/import">
                                    <i class="fa fa-file-import me-2"></i>Import from Alibaba
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/dropshipping/markup">
                                    <i class="fa fa-percentage me-2"></i>Markup Rules
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <div class="mega-col-title">Manage Store</div>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/dropshipping/orders">
                                    <i class="fa fa-clipboard-list me-2"></i>Dropship Orders
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/dropshipping/sync">
                                    <i class="fa fa-sync me-2"></i>Sync Inventory
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/campaigns">
                                    <i class="fa fa-bullhorn me-2"></i>Campaigns
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <div class="mega-col-title">Integrations</div>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/dropshipping/alibaba">
                                    <i class="fa fa-link me-2"></i>Alibaba / 1688
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/dropshipping/aliexpress">
                                    <i class="fa fa-link me-2"></i>AliExpress
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/api-platform">
                                    <i class="fa fa-plug me-2"></i>Custom Integration
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <div class="mega-feature-card">
                                    <h6><i class="fa fa-magic me-1"></i>AI Pricing</h6>
                                    <p>Automatically set competitive markup rules based on category, price range, and market trends.</p>
                                    <a href="<?= APP_URL ?>/pages/dropshipping/markup" class="btn btn-sm btn-outline-info">Configure</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </li><!-- /Dropshipping -->

                <!-- API Platform Mega Menu -->
                <li class="nav-item dropdown mega-dropdown">
                    <a class="nav-link dropdown-toggle" href="#"
                       data-bs-toggle="dropdown" data-bs-auto-close="outside"
                       aria-expanded="false">
                        <i class="fa fa-code me-1"></i>API Platform
                        <i class="fa fa-chevron-down ms-1"></i>
                    </a>
                    <div class="dropdown-menu mega-menu">
                        <div class="row g-4">
                            <div class="col-lg-3 col-md-6">
                                <div class="mega-col-title">Developer Tools</div>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/api-platform">
                                    <i class="fa fa-book me-2"></i>Documentation
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/api-platform/keys">
                                    <i class="fa fa-key me-2"></i>API Keys
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/api-platform/sandbox">
                                    <i class="fa fa-flask me-2"></i>Sandbox
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/api-platform/logs">
                                    <i class="fa fa-terminal me-2"></i>Usage Logs
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <div class="mega-col-title">API Plans</div>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/api-platform/plans">
                                    <i class="fa fa-tags me-2"></i>View Plans
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/api-platform/webhooks">
                                    <i class="fa fa-bell me-2"></i>Webhooks
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/api-platform/sdks">
                                    <i class="fa fa-cube me-2"></i>SDKs &amp; Libraries
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <div class="mega-col-title">Resources</div>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/api-platform/changelog">
                                    <i class="fa fa-list-ul me-2"></i>Changelog
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/api-platform/status">
                                    <i class="fa fa-signal me-2"></i>API Status
                                </a>
                                <a class="dropdown-item" href="<?= APP_URL ?>/pages/support">
                                    <i class="fa fa-life-ring me-2"></i>Developer Support
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <div class="mega-feature-card">
                                    <h6><i class="fa fa-bolt me-1"></i>RESTful API</h6>
                                    <p>Full access to products, orders, suppliers, shipping rates, and AI features via our REST API.</p>
                                    <a href="<?= APP_URL ?>/pages/api-platform" class="btn btn-sm btn-outline-info">Get API Key</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </li><!-- /API Platform -->

                <li class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/pages/campaigns">
                        <i class="fa fa-fire me-1" style="color:#ff6b35"></i>Deals
                    </a>
                </li>

            </ul><!-- /navbar-nav -->

            <!-- ── Search Bar ───────────────────────────── -->
            <form class="gs-search d-flex w-100 my-2 my-lg-0 mx-lg-3"
                  action="<?= APP_URL ?>/pages/search" method="GET" role="search">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input class="form-control"
                       type="search"
                       name="q"
                       placeholder="Search products, suppliers…"
                       autocomplete="off"
                       value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                       aria-label="Search">
                <button class="btn btn-search" type="submit" aria-label="Search with AI">
                    <i class="fa fa-wand-magic-sparkles"></i>
                </button>
            </form>

            <!-- ── Right-side Icons ──────────────────────── -->
            <div class="d-flex align-items-center gap-2 mt-2 mt-lg-0 flex-wrap flex-lg-nowrap">

                <!-- Language (mobile) -->
                <div class="dropdown d-lg-none">
                    <a class="gs-icon-btn dropdown-toggle" href="#"
                       data-bs-toggle="dropdown" aria-label="Language">
                        <i class="fa fa-language"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <?php foreach ($languages as $code => $lang): ?>
                        <li>
                            <a class="dropdown-item <?= $code === $activeLang ? 'active' : '' ?>"
                               href="<?= APP_URL ?>/api/set-language?lang=<?= $code ?>&redirect=<?= urlencode($currentUrl) ?>">
                                <?= $lang['flag'] ?> <?= htmlspecialchars($lang['label']) ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Cart -->
                <a href="<?= APP_URL ?>/pages/cart" class="gs-icon-btn d-none d-lg-inline-flex" aria-label="Cart">
                    <i class="fa fa-shopping-cart"></i>
                    <?php if ($cartCount > 0): ?>
                    <span class="badge bg-danger"><?= min($cartCount, 99) ?></span>
                    <?php endif; ?>
                </a>

                <?php if ($isLoggedIn): ?>

                <!-- Notifications -->
                <div class="dropdown">
                    <a class="gs-icon-btn dropdown-toggle" href="#"
                       data-bs-toggle="dropdown" aria-label="Notifications">
                        <i class="fa fa-bell"></i>
                        <?php if ($notifCount > 0): ?>
                        <span class="badge bg-warning text-dark"><?= min($notifCount, 99) ?></span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" style="min-width:320px;max-height:400px;overflow-y:auto">
                        <li class="px-3 py-2 d-flex justify-content-between align-items-center border-bottom border-secondary">
                            <span class="text-white fw-semibold">Notifications</span>
                            <?php if ($notifCount > 0): ?>
                            <a href="<?= APP_URL ?>/api/notifications/mark-all-read"
                               class="small text-info">Mark all read</a>
                            <?php endif; ?>
                        </li>
                        <?php if ($notifCount === 0): ?>
                        <li class="text-center py-4 text-white-50">
                            <i class="fa fa-bell-slash fa-2x mb-2 d-block"></i>
                            <small>No new notifications</small>
                        </li>
                        <?php else: ?>
                        <li>
                            <a class="dropdown-item py-2" href="<?= APP_URL ?>/pages/account/notifications">
                                <i class="fa fa-bell me-2 text-warning"></i>
                                You have <?= $notifCount ?> unread notification<?= $notifCount > 1 ? 's' : '' ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="border-top border-secondary">
                            <a class="dropdown-item text-center py-2 small text-info"
                               href="<?= APP_URL ?>/pages/account/notifications">
                                View all notifications
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- User Menu -->
                <div class="dropdown">
                    <a class="gs-icon-btn dropdown-toggle d-flex align-items-center gap-2 px-2"
                       href="#" data-bs-toggle="dropdown" aria-label="User menu">
                        <?php if ($userAvatar): ?>
                        <img src="<?= htmlspecialchars(UPLOAD_URL . $userAvatar) ?>"
                             class="gs-avatar" alt="<?= htmlspecialchars($userName) ?>">
                        <?php else: ?>
                        <span class="gs-avatar-placeholder">
                            <?= strtoupper(mb_substr($userName, 0, 1)) ?>
                        </span>
                        <?php endif; ?>
                        <span class="d-none d-xl-inline" style="font-size:.84rem;color:rgba(255,255,255,.85)">
                            <?= htmlspecialchars(mb_substr($userName, 0, 14)) ?>
                        </span>
                        <i class="fa fa-chevron-down" style="font-size:.65rem"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" style="min-width:220px">
                        <li class="px-3 py-2">
                            <div class="fw-semibold text-white"><?= htmlspecialchars($userName) ?></div>
                            <div class="small text-white-50 text-capitalize">
                                <i class="fa fa-circle me-1 text-success" style="font-size:.5rem;vertical-align:middle"></i>
                                <?= htmlspecialchars($userRole) ?>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?= APP_URL ?>/pages/account">
                                <i class="fa fa-user me-2"></i>My Account
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= APP_URL ?>/pages/order">
                                <i class="fa fa-box me-2"></i>My Orders
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= APP_URL ?>/pages/cart">
                                <i class="fa fa-shopping-cart me-2"></i>Cart
                                <?php if ($cartCount > 0): ?>
                                <span class="badge bg-danger float-end"><?= $cartCount ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= APP_URL ?>/pages/account/wishlist">
                                <i class="fa fa-heart me-2"></i>Wishlist
                            </a>
                        </li>
                        <?php if (in_array($userRole, ['supplier', 'admin', 'superadmin'])): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?= APP_URL ?>/pages/supplier/dashboard">
                                <i class="fa fa-store me-2"></i>Supplier Dashboard
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (in_array($userRole, ['admin', 'superadmin'])): ?>
                        <li>
                            <a class="dropdown-item" href="<?= APP_URL ?>/pages/admin">
                                <i class="fa fa-cog me-2"></i>Admin Panel
                            </a>
                        </li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?= APP_URL ?>/pages/account/settings">
                                <i class="fa fa-sliders me-2"></i>Settings
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item text-danger" href="<?= APP_URL ?>/pages/auth/logout">
                                <i class="fa fa-sign-out-alt me-2"></i>Sign Out
                            </a>
                        </li>
                    </ul>
                </div>

                <?php else: ?>

                <!-- Guest: Login / Register -->
                <a href="<?= APP_URL ?>/pages/auth/login"
                   class="btn btn-gs-login px-3 py-2">
                    <i class="fa fa-sign-in-alt me-1"></i>Sign In
                </a>
                <a href="<?= APP_URL ?>/pages/auth/register"
                   class="btn btn-gs-register px-3 py-2">
                    <i class="fa fa-user-plus me-1"></i>Register
                </a>

                <?php endif; ?>

            </div><!-- /right icons -->

        </div><!-- /navbar-collapse -->
    </div><!-- /container-fluid -->
</nav>
<!-- ── End Main Navbar ─────────────────────────────────── -->

<!-- Bootstrap 5.3 JS Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmFJoCBFsatw3GnKnLAEjxZJEUQ1"
        crossorigin="anonymous"></script>

<script>
(function () {
    'use strict';

    /* Sticky navbar shadow on scroll */
    var navbar = document.getElementById('mainNavbar');
    window.addEventListener('scroll', function () {
        if (window.scrollY > 10) {
            navbar.style.boxShadow = '0 4px 24px rgba(0,0,0,.45)';
        } else {
            navbar.style.boxShadow = 'none';
        }
    }, { passive: true });

    /* Close mega-menus when clicking outside */
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.mega-dropdown')) {
            document.querySelectorAll('.mega-dropdown .dropdown-menu.show').forEach(function (m) {
                var toggle = m.closest('.dropdown');
                if (toggle) bootstrap.Dropdown.getInstance(toggle.querySelector('[data-bs-toggle="dropdown"]'))?.hide();
            });
        }
    });
})();
</script>
