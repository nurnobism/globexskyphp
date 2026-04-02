<?php
// includes/header.php — must be called after middleware.php is loaded
$pageTitle    = $pageTitle ?? APP_NAME;
$pageDesc     = $pageDesc ?? 'GlobexSky — Global B2B Trade Platform';
$currentUser  = isLoggedIn() ? getCurrentUser() : null;
$cartCount    = 0;
if (isLoggedIn()) {
    try {
        $stmt = getDB()->prepare('SELECT COALESCE(SUM(quantity),0) FROM cart_items WHERE user_id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $cartCount = (int)$stmt->fetchColumn();
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
            <?php if (isLoggedIn()): ?>
                <span class="text-white-50">Hi, <?= e($currentUser['first_name'] ?? 'User') ?></span>
                <a href="<?= APP_URL ?>/api/auth.php?action=logout" class="text-white text-decoration-none">Logout</a>
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

        <!-- Cart & toggler -->
        <div class="d-flex align-items-center gap-2">
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
                <?php if (isLoggedIn()): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> Account
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/account/profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/order/index.php"><i class="bi bi-bag me-2"></i>My Orders</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/rfq/index.php"><i class="bi bi-file-text me-2"></i>My RFQs</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php if (isAdmin()): ?>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/admin/dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Admin Panel</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
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

