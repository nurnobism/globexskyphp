<?php
// includes/header.php — must be called after middleware.php is loaded
if (function_exists('i18nInit')) { i18nInit(); }
$pageTitle    = $pageTitle ?? APP_NAME;
$pageDesc     = $pageDesc ?? 'GlobexSky — Global B2B Trade Platform';
$currentUser  = isLoggedIn() ? getCurrentUser() : null;
$cartCount    = 0;
$notifCount   = 0;
$chatUnread   = 0;
if (isLoggedIn()) {
    try {
        $stmt = getDB()->prepare('SELECT COALESCE(SUM(quantity),0) FROM cart_items WHERE user_id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $cartCount = (int)$stmt->fetchColumn();
    } catch (PDOException $e) { /* ignore */ }
    try {
        $stmt = getDB()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$_SESSION['user_id']]);
        $notifCount = (int)$stmt->fetchColumn();
    } catch (PDOException $e) { /* ignore */ }
    try {
        $stmt = getDB()->prepare(
            'SELECT COUNT(DISTINCT cm.id) FROM chat_messages cm
             JOIN chat_participants cp ON cp.room_id = cm.room_id AND cp.user_id = ?
             WHERE cm.created_at > COALESCE(cp.last_read_at, "1970-01-01")
             AND cm.sender_id != ?'
        );
        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
        $chatUnread = (int)$stmt->fetchColumn();
    } catch (PDOException $e) { /* ignore */ }
} else {
    $cartCount = array_sum(array_column($_SESSION['cart'] ?? [], 'quantity'));
}

// Language / currency helpers
$availLangs  = function_exists('getAvailableLanguages') ? getAvailableLanguages() : [];
$curLang     = function_exists('getLocale') ? getLocale() : 'en';
$curCurrency = function_exists('getSelectedCurrency') ? getSelectedCurrency() : 'USD';
$activeCurrencies = function_exists('getActiveCurrencies') ? getActiveCurrencies() : [];

// Fallback language list (used when i18n module is not available)
$fallbackLangs = [
    'en' => ['flag' => '🇬🇧', 'native' => 'English'],
    'bn' => ['flag' => '🇧🇩', 'native' => 'বাংলা'],
    'es' => ['flag' => '🇪🇸', 'native' => 'Español'],
    'fr' => ['flag' => '🇫🇷', 'native' => 'Français'],
    'ar' => ['flag' => '🇸🇦', 'native' => 'العربية'],
    'zh' => ['flag' => '🇨🇳', 'native' => '中文'],
];

// Fallback currency list (used when currency module is not available)
$fallbackCurrencies = [
    'USD' => ['symbol' => '$',  'name' => 'US Dollar'],
    'EUR' => ['symbol' => '€',  'name' => 'Euro'],
    'GBP' => ['symbol' => '£',  'name' => 'Pound'],
    'BDT' => ['symbol' => '৳', 'name' => 'Taka'],
    'CNY' => ['symbol' => '¥',  'name' => 'Yuan'],
];
?>
<!DOCTYPE html>
<html lang="<?= function_exists('getLocale') ? e(getLocale()) : 'en' ?>" <?= (function_exists('isRTL') && isRTL()) ? 'dir="rtl"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($pageDesc) ?>">
    <title><?= e($pageTitle) ?> — <?= e(APP_NAME) ?></title>

    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
          crossorigin="anonymous"
          onerror="this.onerror=null;this.href='https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css'">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css"
          crossorigin="anonymous"
          onerror="this.onerror=null;this.href='https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.0/font/bootstrap-icons.css'">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= rtrim(APP_URL, '/') ?>/assets/css/style.css">
    <!-- Favicon -->
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="GlobexSky">
</head>
<body>

<!-- ========================================================
     NAVBAR — Alibaba-style two-row sticky header
     Row 1: Logo | Search | Lang/Currency | Auth | Cart
     Row 2: Category mega-menu
     ======================================================== -->
<header class="gs-header sticky-top">

    <!-- ── Row 1: Top bar ── -->
    <div class="gs-topbar bg-white border-bottom py-3">
        <div class="container-fluid px-3">
            <div class="row align-items-center g-2">

                <!-- Logo -->
                <div class="col-auto">
                    <a href="<?= APP_URL ?>/" class="gs-logo text-decoration-none d-flex align-items-center gap-2">
                        <i class="bi bi-globe2 fs-3"></i>
                        <span class="lh-1"><span class="gs-logo-globex">Globex</span><span class="gs-logo-sky">Sky</span></span>
                    </a>
                </div>

                <!-- Search bar -->
                <div class="col d-flex justify-content-center">
                    <form action="<?= APP_URL ?>/pages/product/index.php" method="GET" class="gs-search-form">
                        <div class="gs-search-inner d-flex align-items-center">
                            <input type="text" name="q" class="gs-search-input form-control border-0 shadow-none"
                                   placeholder="Search products, suppliers, categories..."
                                   value="<?= e(get('q', '')) ?>">
                            <!-- Search-tool icons (right side inside input) -->
                            <div class="gs-search-icons d-none d-md-flex align-items-center gap-1 px-2">
                                <button type="button" class="gs-search-icon-btn" title="Voice Search" aria-label="Voice Search" data-bs-toggle="modal" data-bs-target="#voiceSearchModal">
                                    <i class="bi bi-mic" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="gs-search-icon-btn" title="Image Search" aria-label="Image Search" data-bs-toggle="modal" data-bs-target="#imageSearchModal">
                                    <i class="bi bi-camera" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="gs-search-icon-btn" title="QR / Barcode Search" aria-label="QR / Barcode Search" data-bs-toggle="modal" data-bs-target="#qrSearchModal">
                                    <i class="bi bi-upc-scan" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="gs-search-icon-btn" title="AI Smart Search" aria-label="AI Smart Search" data-bs-toggle="modal" data-bs-target="#aiSearchModal">
                                    <i class="bi bi-robot" aria-hidden="true"></i>
                                </button>
                            </div>
                            <button type="submit" class="gs-search-btn btn btn-primary">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Right-side controls -->
                <div class="col-auto d-flex align-items-center gap-2">

                    <!-- Language selector (hidden on mobile, shown in hamburger menu) -->
                    <div class="dropdown d-none d-lg-block">
                        <a href="#" class="gs-util-btn dropdown-toggle text-decoration-none text-dark small" data-bs-toggle="dropdown">
                            <?php if (!empty($availLangs) && isset($availLangs[$curLang])): ?>
                                <?= e($availLangs[$curLang]['flag'] ?? '🌐') ?> <?= e($availLangs[$curLang]['native'] ?? strtoupper($curLang)) ?>
                            <?php elseif (isset($fallbackLangs[$curLang])): ?>
                                <?= e($fallbackLangs[$curLang]['flag']) ?> <?= e($fallbackLangs[$curLang]['native']) ?>
                            <?php else: ?>
                                🇬🇧 English
                            <?php endif; ?>
                            <i class="bi bi-chevron-down gs-chevron-sm"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" style="max-height:300px;overflow-y:auto;min-width:160px">
                            <?php if (!empty($availLangs)): ?>
                                <?php foreach ($availLangs as $code => $info): ?>
                                <li>
                                    <a class="dropdown-item <?= $code === $curLang ? 'active' : '' ?>"
                                       href="?lang=<?= e($code) ?>">
                                        <?= e($info['flag'] ?? '') ?> <?= e($info['native'] ?? $code) ?>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach ($fallbackLangs as $code => $info): ?>
                                <li>
                                    <a class="dropdown-item <?= $code === $curLang ? 'active' : '' ?>"
                                       href="?lang=<?= e($code) ?>">
                                        <?= e($info['flag']) ?> <?= e($info['native']) ?>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <!-- Currency selector (hidden on mobile) -->
                    <div class="dropdown d-none d-lg-block">
                        <a href="#" class="gs-util-btn dropdown-toggle text-decoration-none text-dark small" data-bs-toggle="dropdown">
                            <?php
                            $currSymbol = '';
                            if (!empty($activeCurrencies)) {
                                foreach ($activeCurrencies as $c) {
                                    if ($c['code'] === $curCurrency) { $currSymbol = $c['symbol']; break; }
                                }
                            }
                            if ($currSymbol === '' && isset($fallbackCurrencies[$curCurrency])) {
                                $currSymbol = $fallbackCurrencies[$curCurrency]['symbol'];
                            }
                            echo e($currSymbol ? $currSymbol . ' ' : '') . e($curCurrency);
                            ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" style="max-height:300px;overflow-y:auto;min-width:180px">
                            <?php if (!empty($activeCurrencies)): ?>
                                <?php foreach ($activeCurrencies as $curr): ?>
                                <li>
                                    <a class="dropdown-item <?= $curr['code'] === $curCurrency ? 'active' : '' ?>"
                                       href="?currency=<?= e($curr['code']) ?>"
                                       onclick="document.cookie='currency=<?= e($curr['code']) ?>;path=/;max-age=31536000';return true;">
                                        <?= e($curr['symbol']) ?> <?= e($curr['code']) ?> — <?= e($curr['name']) ?>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach ($fallbackCurrencies as $code => $curr): ?>
                                <li>
                                    <a class="dropdown-item <?= $code === $curCurrency ? 'active' : '' ?>"
                                       href="?currency=<?= e($code) ?>"
                                       onclick="document.cookie='currency=<?= e($code) ?>;path=/;max-age=31536000';return true;">
                                        <?= e($curr['symbol']) ?> <?= e($code) ?> — <?= e($curr['name']) ?>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <!-- PWA Install Button — logged-in users only, managed by JS -->
                    <!-- Auth / User area -->
                    <?php if (isLoggedIn() && $currentUser): ?>
                        <?php
                        $email       = $currentUser['email'] ?? '';
                        $displayName = $currentUser['first_name']
                            ?? (!empty($email) ? explode('@', $email)[0] : 'User');
                        $userRole    = $currentUser['role'] ?? ($_SESSION['user_role'] ?? '');
                        $kycBadgeClass = 'secondary';
                        $kycLabel      = 'KYC';
                        if (function_exists('getKycStatus')) {
                            $kycStatusDropdown = getKycStatus((int)$_SESSION['user_id']);
                        } else {
                            try {
                                $stmt = getDB()->prepare('SELECT kyc_status FROM users WHERE id = ?');
                                $stmt->execute([$_SESSION['user_id']]);
                                $kycStatusDropdown = $stmt->fetchColumn() ?: 'none';
                            } catch (Exception $e) {
                                $kycStatusDropdown = 'none';
                            }
                        }
                        $kycBadgeClass = match($kycStatusDropdown) {
                            'none'     => 'secondary',
                            'pending'  => 'warning',
                            'approved' => 'success',
                            'rejected' => 'danger',
                            'expired'  => 'warning',
                            default    => 'secondary'
                        };
                        $kycLabel = match($kycStatusDropdown) {
                            'none'         => 'Not Verified',
                            'pending'      => 'KYC Pending',
                            'under_review' => 'Under Review',
                            'approved'     => 'KYC Verified',
                            'rejected'     => 'KYC Rejected',
                            'expired'      => 'KYC Expired',
                            default        => 'KYC'
                        };
                        ?>

                        <!-- Install App (logged-in only) -->
                        <button id="pwa-install-btn" class="btn btn-outline-secondary btn-sm d-none d-lg-inline-flex align-items-center gap-1" onclick="pwaInstall()">
                            <i class="bi bi-download"></i><span class="d-none d-xl-inline">Install App</span>
                        </button>

                        <!-- Notifications bell (logged-in only) -->
                        <div class="dropdown" id="notificationDropdown">
                            <a href="#" class="gs-icon-btn position-relative" data-bs-toggle="dropdown" data-bs-auto-close="outside" title="Notifications">
                                <i class="bi bi-bell fs-5"></i>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge" <?= $notifCount > 0 ? '' : 'style="display:none"' ?>>
                                    <?= $notifCount > 99 ? '99+' : $notifCount ?>
                                </span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end shadow" style="width:320px;max-height:400px;overflow-y:auto">
                                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                                    <strong>Notifications</strong>
                                    <a href="#" onclick="GlobexNotifications.markAllRead();return false;" class="small text-decoration-none">Mark all read</a>
                                </div>
                                <div id="notificationList">
                                    <div class="text-center py-3 text-muted"><div class="spinner-border spinner-border-sm"></div></div>
                                </div>
                                <div class="border-top text-center py-2">
                                    <a href="<?= APP_URL ?>/pages/notifications/index.php" class="small text-decoration-none">View All Notifications</a>
                                </div>
                            </div>
                        </div>

                        <!-- Chat icon -->
                        <a href="<?= APP_URL ?>/pages/messages/index.php" class="gs-icon-btn position-relative" title="Messages">
                            <i class="bi bi-chat-dots fs-5"></i>
                            <?php if ($chatUnread > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary">
                                    <?= $chatUnread > 99 ? '99+' : $chatUnread ?>
                                </span>
                            <?php endif; ?>
                        </a>

                        <!-- Webmail icon -->
                        <a href="<?= APP_URL ?>/pages/webmail/inbox.php" class="gs-icon-btn" title="Webmail">
                            <i class="bi bi-envelope fs-5"></i>
                        </a>

                        <!-- User dropdown -->
                        <div class="dropdown">
                            <a href="#" class="gs-user-btn dropdown-toggle text-decoration-none d-flex align-items-center gap-1" data-bs-toggle="dropdown">
                                <?php if (!empty($currentUser['avatar'])): ?>
                                    <img src="<?= e($currentUser['avatar']) ?>" alt="avatar"
                                         class="rounded-circle" style="width:28px;height:28px;object-fit:cover;">
                                <?php else: ?>
                                    <i class="bi bi-person-circle fs-5"></i>
                                <?php endif; ?>
                                <span class="d-none d-xl-inline small fw-semibold text-dark"><?= e($displayName) ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php if (in_array($userRole, ['admin', 'super_admin'])): ?>
                                <li><a class="dropdown-item fw-semibold text-danger" href="<?= APP_URL ?>/pages/admin/index.php"><i class="bi bi-shield-fill me-2"></i>Admin Panel</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <?php elseif ($userRole === 'supplier'): ?>
                                <li><a class="dropdown-item fw-semibold" href="<?= APP_URL ?>/pages/supplier/index.php"><i class="bi bi-building me-2"></i>Supplier Dashboard</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <?php elseif ($userRole === 'carrier'): ?>
                                <li><a class="dropdown-item fw-semibold" href="<?= APP_URL ?>/pages/shipment/carry/dashboard.php"><i class="bi bi-truck me-2"></i>Carrier Dashboard</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/account/profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
                                <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/order/index.php"><i class="bi bi-bag me-2"></i>My Orders</a></li>
                                <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/rfq/index.php"><i class="bi bi-file-text me-2"></i>My RFQs</a></li>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-2" href="<?= APP_URL ?>/pages/account/kyc.php">
                                        <i class="bi bi-shield-check"></i>KYC Verification
                                        <span class="badge bg-<?= $kycBadgeClass ?> ms-auto"><?= e($kycLabel) ?></span>
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/payment/index.php"><i class="bi bi-credit-card me-2"></i>Payments</a></li>
                                <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/support/index.php"><i class="bi bi-life-preserver me-2"></i>Support</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="/api/auth.php?action=logout" class="d-inline">
                                        <?= csrfField() ?>
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>

                    <?php else: ?>
                        <!-- Guest: Login + Register -->
                        <a href="<?= APP_URL ?>/pages/auth/login.php" class="btn btn-outline-primary btn-sm d-none d-md-inline-flex align-items-center gap-1">
                            <i class="bi bi-box-arrow-in-right"></i> Login
                        </a>
                        <a href="<?= APP_URL ?>/pages/auth/register.php" class="btn btn-primary btn-sm d-none d-md-inline-flex align-items-center gap-1">
                            <i class="bi bi-person-plus"></i> Register
                        </a>
                    <?php endif; ?>

                    <!-- Cart -->
                    <a href="<?= APP_URL ?>/pages/cart/index.php" class="gs-icon-btn position-relative" title="Cart">
                        <i class="bi bi-cart3 fs-5"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                              aria-label="Cart items: <?= $cartCount > 99 ? '99+' : $cartCount ?>">
                            <?= $cartCount > 99 ? '99+' : $cartCount ?>
                        </span>
                    </a>

                    <!-- Mobile hamburger -->
                    <button class="navbar-toggler border-0 d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#gs-mobile-nav" aria-expanded="false" aria-label="Toggle navigation">
                        <i class="bi bi-list fs-4"></i>
                    </button>
                </div><!-- /col-auto right controls -->
            </div><!-- /row -->
        </div><!-- /container-fluid -->
    </div><!-- /gs-topbar -->

    <!-- Orange separator line -->
    <div class="gs-separator"></div>

    <!-- ── Row 2: Category menu (desktop) ── -->
    <nav class="gs-catbar d-none d-lg-block" aria-label="Category navigation">
        <div class="container-fluid px-3">
            <ul class="gs-catbar-list list-unstyled d-flex align-items-center mb-0">
                <li>
                    <a href="<?= APP_URL ?>/pages/sourcing/index.php" class="gs-cat-link">
                        <i class="bi bi-factory"></i> Sourcing
                    </a>
                </li>
                <li>
                    <a href="<?= APP_URL ?>/pages/shipment/index.php" class="gs-cat-link">
                        <i class="bi bi-ship"></i> Shipment
                    </a>
                </li>
                <li>
                    <a href="<?= APP_URL ?>/pages/shipment/carry/register.php" class="gs-cat-link">
                        <i class="bi bi-truck"></i> Carry Service
                    </a>
                </li>
                <li>
                    <a href="<?= APP_URL ?>/pages/supplier/index.php" class="gs-cat-link">
                        <i class="bi bi-building"></i> Suppliers
                    </a>
                </li>
                <li>
                    <a href="<?= APP_URL ?>/pages/livestream/index.php" class="gs-cat-link">
                        <i class="bi bi-broadcast"></i> Live Streams
                    </a>
                </li>
                <li>
                    <a href="<?= APP_URL ?>/pages/trade-shows/index.php" class="gs-cat-link">
                        <i class="bi bi-ticket-perforated"></i> Trade Shows
                    </a>
                </li>
                <li>
                    <a href="<?= APP_URL ?>/pages/vr-showroom/index.php" class="gs-cat-link">
                        <i class="bi bi-display"></i> VR Showroom
                    </a>
                </li>
                <li>
                    <a href="<?= APP_URL ?>/pages/inspection/request.php" class="gs-cat-link">
                        <i class="bi bi-clipboard-check"></i> Inspection
                    </a>
                </li>
                <li>
                    <a href="<?= APP_URL ?>/pages/api-platform/index.php" class="gs-cat-link">
                        <i class="bi bi-plug"></i> API Platform
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- ── Mobile collapsible nav ── -->
    <div class="collapse bg-white border-bottom" id="gs-mobile-nav">
        <div class="container-fluid px-3 py-2">

            <!-- Mobile search (full width) -->
            <form action="<?= APP_URL ?>/pages/product/index.php" method="GET" class="mb-3">
                <div class="input-group">
                    <input type="text" name="q" class="form-control" placeholder="Search products, suppliers..."
                           value="<?= e(get('q', '')) ?>">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
                </div>
            </form>

            <!-- Mobile auth (guest only) -->
            <?php if (!isLoggedIn()): ?>
            <div class="d-flex gap-2 mb-3">
                <a href="<?= APP_URL ?>/pages/auth/login.php" class="btn btn-outline-primary flex-fill">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Login
                </a>
                <a href="<?= APP_URL ?>/pages/auth/register.php" class="btn btn-primary flex-fill">
                    <i class="bi bi-person-plus me-1"></i>Register
                </a>
            </div>
            <?php endif; ?>

            <!-- Mobile category links -->
            <nav aria-label="Mobile category navigation">
                <ul class="list-unstyled mb-2">
                    <li><a href="<?= APP_URL ?>/pages/sourcing/index.php" class="gs-mobile-cat-link"><i class="bi bi-factory me-2"></i>Sourcing</a></li>
                    <li><a href="<?= APP_URL ?>/pages/shipment/index.php" class="gs-mobile-cat-link"><i class="bi bi-ship me-2"></i>Shipment</a></li>
                    <li><a href="<?= APP_URL ?>/pages/shipment/carry/register.php" class="gs-mobile-cat-link"><i class="bi bi-truck me-2"></i>Carry Service</a></li>
                    <li><a href="<?= APP_URL ?>/pages/supplier/index.php" class="gs-mobile-cat-link"><i class="bi bi-building me-2"></i>Suppliers</a></li>
                    <li><a href="<?= APP_URL ?>/pages/livestream/index.php" class="gs-mobile-cat-link"><i class="bi bi-broadcast me-2"></i>Live Streams</a></li>
                    <li><a href="<?= APP_URL ?>/pages/trade-shows/index.php" class="gs-mobile-cat-link"><i class="bi bi-ticket-perforated me-2"></i>Trade Shows</a></li>
                    <li><a href="<?= APP_URL ?>/pages/vr-showroom/index.php" class="gs-mobile-cat-link"><i class="bi bi-display me-2"></i>VR Showroom</a></li>
                    <li><a href="<?= APP_URL ?>/pages/inspection/request.php" class="gs-mobile-cat-link"><i class="bi bi-clipboard-check me-2"></i>Inspection</a></li>
                    <li><a href="<?= APP_URL ?>/pages/api-platform/index.php" class="gs-mobile-cat-link"><i class="bi bi-plug me-2"></i>API Platform</a></li>
                    <li><a href="<?= APP_URL ?>/pages/rfq/create.php" class="gs-mobile-cat-link fw-semibold text-primary"><i class="bi bi-file-earmark-plus me-2"></i>Get Quote</a></li>
                </ul>
            </nav>

            <!-- Mobile language & currency -->
            <div class="d-flex gap-2 flex-wrap mb-2">
                <div class="dropdown">
                    <a href="#" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-globe me-1"></i>
                        <?php if (!empty($availLangs) && isset($availLangs[$curLang])): ?>
                            <?= e($availLangs[$curLang]['flag'] ?? '🌐') ?> <?= e(strtoupper($curLang)) ?>
                        <?php else: ?>
                            🌐 EN
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu" style="max-height:250px;overflow-y:auto">
                        <?php if (!empty($availLangs)): ?>
                            <?php foreach ($availLangs as $code => $info): ?>
                            <li><a class="dropdown-item <?= $code === $curLang ? 'active' : '' ?>" href="?lang=<?= e($code) ?>"><?= e($info['flag'] ?? '') ?> <?= e($info['native'] ?? $code) ?></a></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php foreach ($fallbackLangs as $code => $info): ?>
                            <li><a class="dropdown-item <?= $code === $curLang ? 'active' : '' ?>" href="?lang=<?= e($code) ?>"><?= $info['flag'] ?> <?= e($info['native']) ?></a></li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="dropdown">
                    <a href="#" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-currency-dollar me-1"></i><?= e($curCurrency) ?>
                    </a>
                    <ul class="dropdown-menu" style="max-height:250px;overflow-y:auto">
                        <?php if (!empty($activeCurrencies)): ?>
                            <?php foreach ($activeCurrencies as $curr): ?>
                            <li><a class="dropdown-item <?= $curr['code'] === $curCurrency ? 'active' : '' ?>" href="?currency=<?= e($curr['code']) ?>" onclick="document.cookie='currency=<?= e($curr['code']) ?>;path=/;max-age=31536000';return true;"><?= e($curr['symbol']) ?> <?= e($curr['code']) ?></a></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php foreach ($fallbackCurrencies as $code => $curr): ?>
                            <li><a class="dropdown-item <?= $code === $curCurrency ? 'active' : '' ?>" href="?currency=<?= e($code) ?>" onclick="document.cookie='currency=<?= e($code) ?>;path=/;max-age=31536000';return true;"><?= e($curr['symbol']) ?> <?= e($code) ?></a></li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Mobile extra account links (logged-in) -->
            <?php if (isLoggedIn() && $currentUser): ?>
            <ul class="list-unstyled mb-0 border-top pt-2">
                <li><a href="<?= APP_URL ?>/pages/account/profile.php" class="gs-mobile-cat-link"><i class="bi bi-person me-2"></i>My Profile</a></li>
                <li><a href="<?= APP_URL ?>/pages/order/index.php" class="gs-mobile-cat-link"><i class="bi bi-bag me-2"></i>My Orders</a></li>
                <li><a href="<?= APP_URL ?>/pages/notifications/index.php" class="gs-mobile-cat-link"><i class="bi bi-bell me-2"></i>Notifications</a></li>
                <li><a href="<?= APP_URL ?>/pages/messages/index.php" class="gs-mobile-cat-link"><i class="bi bi-chat-dots me-2"></i>Messages</a></li>
                <?php if (in_array($currentUser['role'] ?? ($_SESSION['user_role'] ?? ''), ['admin', 'super_admin'])): ?>
                <li><a href="<?= APP_URL ?>/pages/admin/dashboard.php" class="gs-mobile-cat-link text-danger"><i class="bi bi-speedometer2 me-2"></i>Admin Panel</a></li>
                <?php endif; ?>
                <li>
                    <form method="POST" action="/api/auth.php?action=logout" class="d-inline">
                        <?= csrfField() ?>
                        <button type="submit" class="gs-mobile-cat-link w-100 text-start text-danger border-0 bg-transparent p-0">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </button>
                    </form>
                </li>
            </ul>
            <?php endif; ?>

        </div>
    </div><!-- /gs-mobile-nav -->

</header>

<!-- Flash Messages -->
<?php foreach (getFlashMessages() as $flash): ?>
<div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show m-0 rounded-0" role="alert">
    <div class="container"><?= e($flash['message']) ?></div>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endforeach; ?>

<!-- ── Voice Search Modal ── -->
<div class="modal fade" id="voiceSearchModal" tabindex="-1" aria-labelledby="voiceSearchModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="voiceSearchModalLabel"><i class="bi bi-mic-fill text-danger me-2"></i>Voice Search</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <p class="text-muted mb-4">Click the microphone and speak your search query</p>
                <button id="gsStartVoiceBtn" class="btn btn-outline-danger btn-lg rounded-circle p-3 mb-3" aria-label="Start voice search">
                    <i class="bi bi-mic fs-2"></i>
                </button>
                <p id="gsVoiceStatus" class="text-muted small mt-2">Press the button and start speaking</p>
                <p id="gsVoiceResult" class="fw-semibold mt-2 d-none"></p>
            </div>
        </div>
    </div>
</div>

<!-- ── Image Search Modal ── -->
<div class="modal fade" id="imageSearchModal" tabindex="-1" aria-labelledby="imageSearchModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="imageSearchModalLabel"><i class="bi bi-camera-fill text-primary me-2"></i>Search by Image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Upload an image to find similar products</p>
                <div id="gsImageUploadArea" class="border border-2 border-dashed rounded-3 text-center p-4 mb-3" style="cursor:pointer;border-style:dashed!important">
                    <i class="bi bi-cloud-arrow-up fs-2 text-muted"></i>
                    <p class="mb-0 mt-2 text-muted">Drag &amp; drop or click to upload</p>
                </div>
                <input type="file" id="gsImageFileInput" accept="image/*" class="d-none">
                <p id="gsImageFileName" class="small text-success d-none"></p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="gsImageSearchBtn" class="btn btn-primary" disabled>
                    <i class="bi bi-search me-1"></i>Search
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── QR / Barcode Search Modal ── -->
<div class="modal fade" id="qrSearchModal" tabindex="-1" aria-labelledby="qrSearchModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="qrSearchModalLabel"><i class="bi bi-upc-scan text-success me-2"></i>Scan QR Code or Barcode</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Upload a barcode/QR image or enter the code manually</p>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Upload barcode / QR image</label>
                    <input type="file" id="gsQrFileInput" class="form-control" accept="image/*" capture="environment">
                    <div class="form-text">On mobile, this opens the camera. On desktop, select an image file.</div>
                </div>
                <div class="text-center text-muted my-2">— or —</div>
                <div class="mb-3">
                    <label for="gsQrTextInput" class="form-label fw-semibold">Enter barcode number</label>
                    <input type="text" id="gsQrTextInput" class="form-control" placeholder="e.g. 0123456789012">
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="gsQrSearchBtn" class="btn btn-success">
                    <i class="bi bi-search me-1"></i>Search
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── AI Smart Search Modal ── -->
<div class="modal fade" id="aiSearchModal" tabindex="-1" aria-labelledby="aiSearchModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="aiSearchModalLabel">🤖 AI-Powered Smart Search</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Describe what you're looking for in your own words</p>
                <textarea id="gsAiSearchText" class="form-control" rows="4"
                          placeholder="e.g. I need waterproof LED lights for outdoor use, minimum 50W, IP67 rated..."></textarea>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="gsAiSearchBtn" class="btn btn-primary">
                    <i class="bi bi-stars me-1"></i>Search with AI
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    /* ── Helpers ── */
    var gsBaseUrl = <?= json_encode(rtrim(APP_URL, '/')) ?>;
    function getSearchInput() {
        return document.querySelector('.gs-search-input') || document.querySelector('input[name="q"]');
    }
    function redirectSearch(q, extra) {
        var params = new URLSearchParams({ q: q });
        if (extra) { Object.keys(extra).forEach(function(k){ params.set(k, extra[k]); }); }
        window.location.href = gsBaseUrl + '/pages/product/index.php?' + params.toString();
    }

    /* ── Voice Search ── */
    var voiceModal = document.getElementById('voiceSearchModal');
    if (voiceModal) {
        var startBtn  = document.getElementById('gsStartVoiceBtn');
        var statusEl  = document.getElementById('gsVoiceStatus');
        var resultEl  = document.getElementById('gsVoiceResult');
        var recognition = null;

        voiceModal.addEventListener('hidden.bs.modal', function () {
            if (recognition) { try { recognition.stop(); } catch(e){} }
            statusEl.textContent = 'Press the button and start speaking';
            statusEl.className = 'text-muted small mt-2';
            resultEl.classList.add('d-none');
            resultEl.textContent = '';
            startBtn.classList.remove('btn-danger');
            startBtn.classList.add('btn-outline-danger');
        });

        startBtn.addEventListener('click', function () {
            var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SpeechRecognition) {
                statusEl.textContent = 'Voice search is not supported in this browser. Please use Chrome or Edge.';
                statusEl.className = 'text-danger small mt-2';
                return;
            }
            recognition = new SpeechRecognition();
            recognition.lang = navigator.language || 'en-US';
            recognition.interimResults = false;
            recognition.maxAlternatives = 1;

            startBtn.classList.remove('btn-outline-danger');
            startBtn.classList.add('btn-danger');
            statusEl.textContent = 'Listening… speak now';
            resultEl.classList.add('d-none');

            recognition.onresult = function (e) {
                var transcript = e.results[0][0].transcript;
                var inp = getSearchInput();
                if (inp) { inp.value = transcript; }
                resultEl.textContent = '"' + transcript + '"';
                resultEl.classList.remove('d-none');
                statusEl.textContent = 'Got it! Searching…';
                var modal = bootstrap.Modal.getInstance(voiceModal);
                if (modal) { modal.hide(); }
                redirectSearch(transcript);
            };
            recognition.onerror = function (e) {
                statusEl.textContent = 'Error: ' + (e.error || 'unknown') + '. Try again.';
                startBtn.classList.remove('btn-danger');
                startBtn.classList.add('btn-outline-danger');
            };
            recognition.onend = function () {
                startBtn.classList.remove('btn-danger');
                startBtn.classList.add('btn-outline-danger');
            };
            recognition.start();
        });
    }

    /* ── Image Search ── */
    var imageModal = document.getElementById('imageSearchModal');
    if (imageModal) {
        var uploadArea    = document.getElementById('gsImageUploadArea');
        var fileInput     = document.getElementById('gsImageFileInput');
        var fileNameEl    = document.getElementById('gsImageFileName');
        var imageSearchBtn = document.getElementById('gsImageSearchBtn');
        var selectedFile  = null;

        imageModal.addEventListener('hidden.bs.modal', function () {
            fileInput.value = '';
            fileNameEl.classList.add('d-none');
            fileNameEl.textContent = '';
            imageSearchBtn.disabled = true;
            selectedFile = null;
        });

        uploadArea.addEventListener('click', function () { fileInput.click(); });
        uploadArea.addEventListener('dragover', function (e) { e.preventDefault(); uploadArea.classList.add('bg-light'); });
        uploadArea.addEventListener('dragleave', function () { uploadArea.classList.remove('bg-light'); });
        uploadArea.addEventListener('drop', function (e) {
            e.preventDefault();
            uploadArea.classList.remove('bg-light');
            var f = e.dataTransfer.files[0];
            if (f && f.type.startsWith('image/')) { handleImageFile(f); }
        });
        fileInput.addEventListener('change', function () {
            if (fileInput.files[0]) { handleImageFile(fileInput.files[0]); }
        });

        function handleImageFile(f) {
            selectedFile = f;
            fileNameEl.textContent = '✓ ' + f.name;
            fileNameEl.classList.remove('d-none');
            imageSearchBtn.disabled = false;
        }

        imageSearchBtn.addEventListener('click', function () {
            if (!selectedFile) { return; }
            redirectSearch(selectedFile.name.replace(/\.[^.]+$/, ''), { search_type: 'image' });
        });
    }

    /* ── QR / Barcode Search ── */
    var qrModal = document.getElementById('qrSearchModal');
    if (qrModal) {
        var qrSearchBtn = document.getElementById('gsQrSearchBtn');
        var qrFileInput = document.getElementById('gsQrFileInput');
        var qrTextInput = document.getElementById('gsQrTextInput');

        qrModal.addEventListener('hidden.bs.modal', function () {
            qrFileInput.value = '';
            qrTextInput.value = '';
        });

        qrSearchBtn.addEventListener('click', function () {
            var code = qrTextInput.value.trim();
            if (code) {
                var modal = bootstrap.Modal.getInstance(qrModal);
                if (modal) { modal.hide(); }
                redirectSearch(code, { search_type: 'barcode' });
            } else if (qrFileInput.files[0]) {
                var modal = bootstrap.Modal.getInstance(qrModal);
                if (modal) { modal.hide(); }
                redirectSearch(qrFileInput.files[0].name.replace(/\.[^.]+$/, ''), { search_type: 'barcode' });
            } else {
                qrTextInput.focus();
            }
        });
    }

    /* ── AI Smart Search ── */
    var aiModal = document.getElementById('aiSearchModal');
    if (aiModal) {
        var aiSearchBtn  = document.getElementById('gsAiSearchBtn');
        var aiSearchText = document.getElementById('gsAiSearchText');

        aiModal.addEventListener('hidden.bs.modal', function () {
            aiSearchText.value = '';
        });

        aiSearchBtn.addEventListener('click', function () {
            var query = aiSearchText.value.trim();
            if (!query) { aiSearchText.focus(); return; }
            var modal = bootstrap.Modal.getInstance(aiModal);
            if (modal) { modal.hide(); }
            redirectSearch(query, { search_type: 'ai' });
        });

        aiSearchText.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { aiSearchBtn.click(); }
        });
    }
}());
</script>


