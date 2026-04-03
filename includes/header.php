<?php
// includes/header.php — must be called after middleware.php is loaded
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
?>
<!DOCTYPE html>
<html lang="en">
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
</head>
<body>

<!-- Top Bar -->
<div class="topbar bg-primary text-white py-1 small">
    <div class="container d-flex justify-content-between align-items-center">
        <span><i class="bi bi-telephone-fill"></i> +1 (800) GLOBEX-SKY</span>
        <div class="d-flex gap-3">
            <a href="<?= APP_URL ?>/pages/help.php" class="text-white text-decoration-none">Help</a>
            <?php if (isLoggedIn() && $currentUser): ?>
                <?php
                $email       = $currentUser['email'] ?? '';
                $displayName = $currentUser['first_name']
                    ?? (!empty($email) ? explode('@', $email)[0] : 'User');
                $userRole    = $currentUser['role'] ?? ($_SESSION['user_role'] ?? '');
                ?>
                <?php if (in_array($userRole, ['admin', 'super_admin'])): ?>
                    <a href="<?= APP_URL ?>/pages/admin/index.php" class="text-warning text-decoration-none fw-semibold">
                        <i class="bi bi-shield-fill"></i> Admin Panel
                    </a>
                <?php elseif ($userRole === 'supplier'): ?>
                    <a href="<?= APP_URL ?>/pages/supplier/index.php" class="text-white text-decoration-none">
                        <i class="bi bi-building"></i> Supplier Dashboard
                    </a>
                <?php elseif ($userRole === 'carrier'): ?>
                    <a href="<?= APP_URL ?>/pages/shipment/carrier/" class="text-white text-decoration-none">
                        <i class="bi bi-truck"></i> Carrier Dashboard
                    </a>
                <?php endif; ?>
                <div class="dropdown">
                    <a href="#" class="text-white text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                        <?php if (!empty($currentUser['avatar'])): ?>
                            <img src="<?= e($currentUser['avatar']) ?>" alt="avatar"
                                 style="width:24px;height:24px;border-radius:50%;object-fit:cover;">
                        <?php else: ?>
                            <i class="bi bi-person-circle"></i>
                        <?php endif; ?>
                        <?= e($displayName) ?>
                        <span class="badge bg-<?= in_array($userRole,['admin','super_admin'])?'danger':($userRole==='supplier'?'primary':($userRole==='carrier'?'success':'secondary')) ?> ms-1 small">
                            <?= e(ucfirst(str_replace('_',' ', $userRole))) ?>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/account/profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/order/index.php"><i class="bi bi-bag me-2"></i>My Orders</a></li>
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
                <a href="<?= APP_URL ?>/pages/auth/login.php" class="text-white text-decoration-none">Login</a>
                <a href="<?= APP_URL ?>/pages/auth/register.php" class="text-white text-decoration-none">Register</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Main Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary fs-4" href="<?= APP_URL ?>/">
            <i class="bi bi-globe2"></i> <?= e(APP_NAME) ?>
        </a>

        <!-- Search -->
        <form class="d-none d-lg-flex flex-grow-1 mx-4" action="<?= APP_URL ?>/pages/product/index.php" method="GET">
            <div class="input-group">
                <input type="text" class="form-control" name="q"
                       placeholder="Search products, suppliers..."
                       value="<?= e(get('q', '')) ?>">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
            </div>
        </form>

        <!-- Notifications, Messages, Cart & toggler -->
        <div class="d-flex align-items-center gap-2">
            <?php if (isLoggedIn()): ?>
            <!-- Notification Bell -->
            <div class="dropdown" id="notificationDropdown">
                <a href="#" class="btn btn-outline-secondary position-relative" data-bs-toggle="dropdown" data-bs-auto-close="outside" title="Notifications">
                    <i class="bi bi-bell"></i>
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

            <!-- Chat Icon -->
            <a href="<?= APP_URL ?>/pages/messages/index.php" class="btn btn-outline-secondary position-relative" title="Messages">
                <i class="bi bi-chat-dots"></i>
                <?php if ($chatUnread > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary">
                        <?= $chatUnread > 99 ? '99+' : $chatUnread ?>
                    </span>
                <?php endif; ?>
            </a>

            <!-- Webmail Icon -->
            <a href="<?= APP_URL ?>/pages/webmail/inbox.php" class="btn btn-outline-secondary" title="Webmail">
                <i class="bi bi-envelope"></i>
            </a>
            <?php endif; ?>

            <a href="<?= APP_URL ?>/pages/cart/index.php" class="btn btn-outline-primary position-relative">
                <i class="bi bi-cart3"></i>
                <?php if ($cartCount > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?= $cartCount ?>
                    </span>
                <?php endif; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/pages/product/index.php">Products</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/pages/supplier/index.php">Suppliers</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/pages/rfq/create.php">Get Quote</a>
                </li>

                <!-- Services Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-grid"></i> Services
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/sourcing/index.php"><i class="bi bi-search me-2"></i>Sourcing</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/shipment/index.php"><i class="bi bi-truck me-2"></i>Parcel Service</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/shipment/carry/register.php"><i class="bi bi-airplane me-2"></i>Carry Service</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/order/track.php"><i class="bi bi-search me-2"></i>Track Shipment</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/logistics/index.php"><i class="bi bi-geo-alt me-2"></i>Logistics</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/inspection/request.php"><i class="bi bi-clipboard-check me-2"></i>Inspection</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/escrow/index.php"><i class="bi bi-shield-lock me-2"></i>Escrow</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/samples/index.php"><i class="bi bi-box-seam me-2"></i>Samples</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/dropshipping/index.php"><i class="bi bi-shop me-2"></i>Dropshipping</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/customization/index.php"><i class="bi bi-palette me-2"></i>Customization</a></li>
                    </ul>
                </li>

                <!-- Trade & Events -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-calendar-event"></i> Trade
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/trade-shows/index.php"><i class="bi bi-building me-2"></i>Trade Shows</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/livestream/index.php"><i class="bi bi-broadcast me-2"></i>Live Streams</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/vr-showroom/index.php"><i class="bi bi-headset-vr me-2"></i>VR Showroom</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/meetings/index.php"><i class="bi bi-camera-video me-2"></i>Meetings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/flash-sales/index.php"><i class="bi bi-lightning me-2"></i>Flash Sales</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/campaigns/index.php"><i class="bi bi-megaphone me-2"></i>Campaigns</a></li>
                    </ul>
                </li>

                <!-- AI & Tools -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-robot"></i> AI & Tools
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/ai/index.php"><i class="bi bi-robot me-2"></i>AI Hub</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/ai/chatbot.php"><i class="bi bi-chat-dots me-2"></i>AI Chatbot</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/ai/search.php"><i class="bi bi-search-heart me-2"></i>AI Search</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/barcode-scanner/index.php"><i class="bi bi-upc-scan me-2"></i>Barcode Scanner</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/insights/index.php"><i class="bi bi-graph-up me-2"></i>Insights</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/api-platform/index.php"><i class="bi bi-plug me-2"></i>API Platform</a></li>
                    </ul>
                </li>

                <?php if (isLoggedIn()): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> Account
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/account/profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/order/index.php"><i class="bi bi-bag me-2"></i>My Orders</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/rfq/index.php"><i class="bi bi-file-text me-2"></i>My RFQs</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/notifications/index.php"><i class="bi bi-bell me-2"></i>Notifications</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/messages/index.php"><i class="bi bi-chat-dots me-2"></i>Chat Messages</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/webmail/inbox.php"><i class="bi bi-envelope me-2"></i>Webmail</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/communication/index.php"><i class="bi bi-chat-left-text me-2"></i>Communication</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/payment/index.php"><i class="bi bi-credit-card me-2"></i>Payments</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/loyalty/index.php"><i class="bi bi-trophy me-2"></i>Loyalty Rewards</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/teams/index.php"><i class="bi bi-people me-2"></i>Teams</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/support/index.php"><i class="bi bi-life-preserver me-2"></i>Support</a></li>
                        <?php if (isAdmin()): ?>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/admin/dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Admin Panel</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/api/auth.php?action=logout"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Flash Messages -->
<?php foreach (getFlashMessages() as $flash): ?>
<div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show m-0 rounded-0" role="alert">
    <div class="container"><?= e($flash['message']) ?></div>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endforeach; ?>

