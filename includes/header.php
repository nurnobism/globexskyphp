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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
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
                        <i class="bi bi-globe2 text-primary fs-3"></i>
                        <span class="fw-bold text-primary fs-5 lh-1"><?= e(APP_NAME) ?></span>
                    </a>
                </div>

                <!-- Search bar -->
                <div class="col">
                    <form action="<?= APP_URL ?>/pages/product/index.php" method="GET" class="gs-search-form">
                        <div class="gs-search-inner d-flex align-items-center">
                            <input type="text" name="q" class="gs-search-input form-control border-0 shadow-none"
                                   placeholder="Search products, suppliers, categories..."
                                   value="<?= e(get('q', '')) ?>">
                            <!-- Search-tool icons (right side inside input) -->
                            <div class="gs-search-icons d-none d-md-flex align-items-center gap-1 px-2">
                                <button type="button" class="gs-search-icon-btn" title="Voice Search (coming soon)" aria-label="Voice Search (coming soon)" disabled aria-disabled="true" tabindex="-1">
                                    <i class="bi bi-mic" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="gs-search-icon-btn" title="Image Search (coming soon)" aria-label="Image Search (coming soon)" disabled aria-disabled="true" tabindex="-1">
                                    <i class="bi bi-camera" aria-hidden="true"></i>
                                </button>
                                <a href="<?= APP_URL ?>/pages/barcode-scanner/index.php" class="gs-search-icon-btn" title="Barcode Scan">
                                    <i class="bi bi-upc-scan"></i>
                                </a>
                                <a href="<?= APP_URL ?>/pages/ai/search.php" class="gs-search-icon-btn" title="AI Search">
                                    <i class="bi bi-robot"></i>
                                </a>
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
                            <i class="bi bi-globe"></i>
                            <?php if (!empty($availLangs) && isset($availLangs[$curLang])): ?>
                                <?= e($availLangs[$curLang]['flag'] ?? '🌐') ?> <?= e(strtoupper($curLang)) ?>
                            <?php else: ?>
                                🌐 EN
                            <?php endif; ?>
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
                                        <?= $info['flag'] ?> <?= e($info['native']) ?>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <!-- Currency selector (hidden on mobile) -->
                    <div class="dropdown d-none d-lg-block">
                        <a href="#" class="gs-util-btn dropdown-toggle text-decoration-none text-dark small" data-bs-toggle="dropdown">
                            <i class="bi bi-currency-dollar"></i>
                            <?= e($curCurrency) ?>
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

                    <!-- PWA Install Button -->
                    <button id="pwa-install-btn" class="btn btn-outline-secondary btn-sm d-none d-lg-inline-flex align-items-center gap-1" onclick="pwaInstall()">
                        <i class="bi bi-download"></i><span class="d-none d-xl-inline">Install App</span>
                    </button>

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
                                <li><a class="dropdown-item fw-semibold" href="<?= APP_URL ?>/pages/shipment/carrier/"><i class="bi bi-truck me-2"></i>Carrier Dashboard</a></li>
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
                        <?php if ($cartCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?= $cartCount > 99 ? '99+' : $cartCount ?>
                            </span>
                        <?php endif; ?>
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
    <nav class="gs-catbar bg-white border-bottom d-none d-lg-block" aria-label="Category navigation">
        <div class="container-fluid px-3">
            <ul class="gs-catbar-list list-unstyled d-flex align-items-center mb-0 gap-1">
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
                <li class="ms-auto">
                    <a href="<?= APP_URL ?>/pages/rfq/create.php" class="gs-cat-link gs-cat-link--highlight">
                        <i class="bi bi-file-earmark-plus"></i> Get Quote
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

