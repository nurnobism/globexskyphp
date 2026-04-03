<?php
/**
 * pages/admin/index.php — Admin Dashboard
 * Protected: admin + super_admin roles only
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireRole(['admin', 'super_admin']);

$db = getDB();

// ── Real metrics from DB ──────────────────────────────────
try {
    $totalUsers     = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $buyerCount     = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='buyer'")->fetchColumn();
    $supplierCount  = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='supplier'")->fetchColumn();
    $carrierCount   = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='carrier'")->fetchColumn();
    $adminCount     = (int)$db->query("SELECT COUNT(*) FROM users WHERE role IN ('admin','super_admin')")->fetchColumn();
    $productCount   = (int)$db->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
    $orderTotal     = (int)$db->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    $orderToday     = (int)$db->query("SELECT COUNT(*) FROM orders WHERE DATE(placed_at) = CURDATE()")->fetchColumn();
    $orderWeek      = (int)$db->query("SELECT COUNT(*) FROM orders WHERE placed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    $orderMonth     = (int)$db->query("SELECT COUNT(*) FROM orders WHERE placed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    $revenue        = (float)$db->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE payment_status='paid'")->fetchColumn();
    $pendingOrders  = (int)$db->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
    $openRfqs       = (int)$db->query("SELECT COUNT(*) FROM rfqs WHERE status='open'")->fetchColumn();

    $recentUsers  = $db->query('SELECT id,email,first_name,last_name,name,role,created_at FROM users ORDER BY created_at DESC LIMIT 10')->fetchAll();
    $recentOrders = $db->query('SELECT o.*, u.first_name, u.last_name, u.email FROM orders o LEFT JOIN users u ON u.id=o.buyer_id ORDER BY o.placed_at DESC LIMIT 10')->fetchAll();

    $phpVersion   = PHP_VERSION;
    $mysqlVersion = $db->query('SELECT VERSION()')->fetchColumn();
    // Use document root path for disk space; suppress errors for open_basedir restrictions
    $diskPath  = defined('UPLOAD_DIR') ? UPLOAD_DIR : __DIR__;
    $diskFree  = function_exists('disk_free_space')  ? @disk_free_space($diskPath)  : null;
    $diskTotal = function_exists('disk_total_space') ? @disk_total_space($diskPath) : null;
    if ($diskFree === false)  $diskFree  = null;
    if ($diskTotal === false) $diskTotal = null;
    $dbOk = true;
} catch (PDOException $e) {
    $dbOk = false;
    $totalUsers = $buyerCount = $supplierCount = $carrierCount = $adminCount = 0;
    $productCount = $orderTotal = $orderToday = $orderWeek = $orderMonth = 0;
    $revenue = $pendingOrders = $openRfqs = 0;
    $recentUsers = $recentOrders = [];
    $phpVersion = PHP_VERSION;
    $mysqlVersion = 'N/A';
    $diskFree = $diskTotal = null;
}

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Header row -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-0"><i class="bi bi-speedometer2 text-primary me-2"></i>Admin Dashboard</h3>
            <small class="text-muted">Welcome back, <?= e($_SESSION['user_name'] ?? 'Admin') ?> &mdash; <?= date('l, F j, Y') ?></small>
        </div>
        <div class="d-flex gap-2">
            <a href="/pages/admin/users.php"    class="btn btn-outline-secondary btn-sm"><i class="bi bi-people me-1"></i>Users</a>
            <a href="/pages/admin/products.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-box-seam me-1"></i>Products</a>
            <a href="/pages/admin/orders.php"   class="btn btn-outline-success btn-sm"><i class="bi bi-bag me-1"></i>Orders</a>
            <a href="/pages/admin/settings.php" class="btn btn-outline-dark btn-sm"><i class="bi bi-gear"></i></a>
            <a href="/pages/admin/kyc/index.php"  class="btn btn-outline-warning btn-sm"><i class="bi bi-shield-check me-1"></i>KYC</a>
            <a href="/pages/admin/audit-log.php"  class="btn btn-outline-info btn-sm"><i class="bi bi-journal-text me-1"></i>Audit Log</a>
        </div>
    </div>

    <?php if (!$dbOk): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        Database connection issue — some metrics may be unavailable. Check your database configuration.
    </div>
    <?php endif; ?>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <!-- Users -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                            <i class="bi bi-people-fill text-primary fs-4"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold mb-0"><?= number_format($totalUsers) ?></h4>
                            <small class="text-muted">Total Users</small>
                        </div>
                    </div>
                    <div class="small text-muted d-flex gap-3 flex-wrap">
                        <span><i class="bi bi-person text-secondary"></i> <?= $buyerCount ?> buyers</span>
                        <span><i class="bi bi-building text-primary"></i> <?= $supplierCount ?> suppliers</span>
                        <span><i class="bi bi-truck text-success"></i> <?= $carrierCount ?> carriers</span>
                        <span><i class="bi bi-shield text-danger"></i> <?= $adminCount ?> admins</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3">
                        <i class="bi bi-box-seam-fill text-success fs-4"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0"><?= number_format($productCount) ?></h4>
                        <small class="text-muted">Active Products</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Orders -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                            <i class="bi bi-bag-fill text-warning fs-4"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold mb-0"><?= number_format($orderTotal) ?></h4>
                            <small class="text-muted">Total Orders</small>
                        </div>
                    </div>
                    <div class="small text-muted d-flex gap-3 flex-wrap">
                        <span>Today: <?= $orderToday ?></span>
                        <span>Week: <?= $orderWeek ?></span>
                        <span>Month: <?= $orderMonth ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-info bg-opacity-10 p-3">
                        <i class="bi bi-currency-dollar text-info fs-4"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0"><?= formatMoney($revenue) ?></h4>
                        <small class="text-muted">Total Revenue (Paid)</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Phase 9: KYC Stats -->
    <?php
    $kycPending = 0; $kycApproved = 0; $kycRejected = 0; $kycUnderReview = 0;
    try {
        $kycPending     = (int)$db->query("SELECT COUNT(*) FROM kyc_submissions WHERE status='pending'")->fetchColumn();
        $kycUnderReview = (int)$db->query("SELECT COUNT(*) FROM kyc_submissions WHERE status='under_review'")->fetchColumn();
        $kycApproved    = (int)$db->query("SELECT COUNT(*) FROM kyc_submissions WHERE status='approved'")->fetchColumn();
        $kycRejected    = (int)$db->query("SELECT COUNT(*) FROM kyc_submissions WHERE status='rejected'")->fetchColumn();
    } catch (PDOException $e) { /* kyc tables may not exist yet */ }
    if ($kycPending + $kycUnderReview + $kycApproved + $kycRejected > 0):
    ?>
    <div class="row g-3 mb-4">
        <div class="col-12"><h6 class="text-muted fw-semibold">KYC Verification</h6></div>
        <div class="col-6 col-md-3">
            <a href="/pages/admin/kyc/index.php?status=pending" class="card border-0 shadow-sm text-decoration-none h-100">
                <div class="card-body text-center">
                    <div class="fs-3 fw-bold text-warning"><?= $kycPending ?></div>
                    <small class="text-muted">Pending Review</small>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="/pages/admin/kyc/index.php?status=under_review" class="card border-0 shadow-sm text-decoration-none h-100">
                <div class="card-body text-center">
                    <div class="fs-3 fw-bold text-info"><?= $kycUnderReview ?></div>
                    <small class="text-muted">Under Review</small>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="/pages/admin/kyc/index.php?status=approved" class="card border-0 shadow-sm text-decoration-none h-100">
                <div class="card-body text-center">
                    <div class="fs-3 fw-bold text-success"><?= $kycApproved ?></div>
                    <small class="text-muted">Approved</small>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="/pages/admin/kyc/index.php?status=rejected" class="card border-0 shadow-sm text-decoration-none h-100">
                <div class="card-body text-center">
                    <div class="fs-3 fw-bold text-danger"><?= $kycRejected ?></div>
                    <small class="text-muted">Rejected</small>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Secondary stats row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-danger bg-opacity-10 p-3">
                        <i class="bi bi-clock-fill text-danger fs-5"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0"><?= $pendingOrders ?></h5>
                        <small class="text-muted">Pending Orders</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                        <i class="bi bi-file-text-fill text-primary fs-5"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0"><?= $openRfqs ?></h5>
                        <small class="text-muted">Open RFQs</small>
                    </div>
                </div>
            </div>
        </div>
        <!-- System health -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-activity text-success me-2"></i>System Health</h6>
                    <div class="row g-2 small">
                        <div class="col-6">
                            <span class="text-muted">PHP Version:</span>
                            <span class="ms-1 fw-semibold"><?= e($phpVersion) ?></span>
                        </div>
                        <div class="col-6">
                            <span class="text-muted">MySQL Version:</span>
                            <span class="ms-1 fw-semibold"><?= e($mysqlVersion) ?></span>
                        </div>
                        <?php if ($diskFree !== null && $diskTotal !== null): ?>
                        <div class="col-12">
                            <span class="text-muted">Disk Space:</span>
                            <span class="ms-1 fw-semibold">
                                <?= round($diskFree / 1073741824, 1) ?> GB free
                                / <?= round($diskTotal / 1073741824, 1) ?> GB total
                            </span>
                            <div class="progress mt-1" style="height:6px;">
                                <?php $used = 100 - round(($diskFree / $diskTotal) * 100); ?>
                                <div class="progress-bar <?= $used > 80 ? 'bg-danger' : 'bg-success' ?>"
                                     style="width:<?= $used ?>%"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="col-6">
                            <span class="text-muted">DB Status:</span>
                            <span class="ms-1 badge bg-<?= $dbOk ? 'success' : 'danger' ?>">
                                <?= $dbOk ? 'OK' : 'Error' ?>
                            </span>
                        </div>
                        <div class="col-6">
                            <span class="text-muted">Environment:</span>
                            <span class="ms-1 badge bg-<?= APP_ENV === 'production' ? 'warning text-dark' : 'info' ?>">
                                <?= e(APP_ENV) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tables row -->
    <div class="row g-4">

        <!-- Recent Registrations -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-person-plus text-primary me-2"></i>Recent Registrations</h6>
                    <a href="/pages/admin/users.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr><th>Name</th><th>Role</th><th>Joined</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentUsers as $u): ?>
                        <?php
                            $displayName = !empty($u['name']) ? $u['name']
                                : trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                            if (!$displayName) $displayName = $u['email'];
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($displayName) ?></div>
                                <div class="text-muted"><?= e($u['email']) ?></div>
                            </td>
                            <td>
                                <?php
                                $roleColors = [
                                    'super_admin' => 'danger',
                                    'admin'       => 'danger',
                                    'supplier'    => 'primary',
                                    'carrier'     => 'success',
                                    'buyer'       => 'secondary',
                                ];
                                $rc = $roleColors[$u['role']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $rc ?>"><?= e($u['role']) ?></span>
                            </td>
                            <td><?= formatDate($u['created_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentUsers)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">No users yet</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-bag-check text-success me-2"></i>Recent Orders</h6>
                    <a href="/pages/admin/orders.php" class="btn btn-sm btn-outline-success">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr><th>Order #</th><th>Buyer</th><th>Total</th><th>Status</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                        <?php
                        $statusColors = [
                            'pending'    => 'warning',
                            'confirmed'  => 'info',
                            'processing' => 'info',
                            'shipped'    => 'primary',
                            'delivered'  => 'success',
                            'cancelled'  => 'danger',
                            'refunded'   => 'secondary',
                        ];
                        foreach ($recentOrders as $o):
                            $sc = $statusColors[$o['status']] ?? 'secondary';
                        ?>
                        <tr>
                            <td class="fw-semibold text-primary"><?= e($o['order_number']) ?></td>
                            <td>
                                <?= e(trim(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? '')) ?: ($o['email'] ?? 'Guest')) ?>
                            </td>
                            <td><?= formatMoney($o['total']) ?></td>
                            <td><span class="badge bg-<?= $sc ?>"><?= e($o['status']) ?></span></td>
                            <td><?= formatDate($o['placed_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentOrders)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">No orders yet</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div><!-- /row -->
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
