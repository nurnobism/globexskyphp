<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];

// Check registration
$stmt = $db->prepare("SELECT * FROM carriers WHERE user_id = ?");
$stmt->execute([$userId]);
$carrier = $stmt->fetch();

if (!$carrier || $carrier['status'] !== 'active') {
    header('Location: /pages/shipment/carry/register.php');
    exit;
}

// Stats
$trips = $db->prepare("SELECT COUNT(*) FROM carry_trips WHERE user_id = ?");
$trips->execute([$userId]);
$tripCount = (int)$trips->fetchColumn();

$activeTrips = $db->prepare("SELECT COUNT(*) FROM carry_trips WHERE user_id = ? AND status = 'active'");
$activeTrips->execute([$userId]);
$activeCount = (int)$activeTrips->fetchColumn();

$earnings = $db->prepare("SELECT COALESCE(SUM(earning),0) FROM carrier_earnings WHERE user_id = ?");
$earnings->execute([$userId]);
$totalEarnings = (float)$earnings->fetchColumn();

$pending = $db->prepare("SELECT COALESCE(SUM(earning),0) FROM carrier_earnings WHERE user_id = ? AND paid = 0");
$pending->execute([$userId]);
$pendingPayout = (float)$pending->fetchColumn();

// Recent trips
$recentTrips = $db->prepare("SELECT * FROM carry_trips WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$recentTrips->execute([$userId]);
$recentTripsList = $recentTrips->fetchAll();

$pageTitle = 'Carry Provider Dashboard';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-airplane-fill text-primary me-2"></i>Carry Dashboard</h3>
        <a href="/pages/shipment/carry/create-request.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> New Trip
        </a>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <?php $cards = [
            ['Total Trips', $tripCount, 'airplane', 'primary'],
            ['Active Trips', $activeCount, 'broadcast', 'success'],
            ['Total Earnings', formatMoney($totalEarnings), 'currency-dollar', 'warning'],
            ['Pending Payout', formatMoney($pendingPayout), 'wallet2', 'info'],
        ]; ?>
        <?php foreach ($cards as [$label, $value, $icon, $color]): ?>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-<?= $color ?> bg-opacity-10 p-3">
                        <i class="bi bi-<?= $icon ?> text-<?= $color ?> fs-4"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0"><?= $value ?></h5>
                        <small class="text-muted"><?= $label ?></small>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Quick Links -->
    <div class="row g-3 mb-4">
        <?php $links = [
            ['/pages/shipment/carry/create-request.php', 'plus-circle-fill', 'primary', 'New Trip', 'Post a new carry trip'],
            ['/pages/shipment/carry/active.php', 'broadcast', 'success', 'Active Jobs', 'View your active carry jobs'],
            ['/pages/shipment/carry/history.php', 'clock-history', 'secondary', 'History', 'View completed trips'],
            ['/pages/shipment/carry/earnings.php', 'cash-stack', 'warning', 'Earnings', 'View your earnings & payouts'],
        ]; ?>
        <?php foreach ($links as [$url, $icon, $color, $title, $desc]): ?>
        <div class="col-6 col-md-3">
            <a href="<?= $url ?>" class="card border-0 shadow-sm text-decoration-none h-100">
                <div class="card-body text-center py-4">
                    <i class="bi bi-<?= $icon ?> text-<?= $color ?> display-5"></i>
                    <h6 class="mt-2 fw-bold"><?= $title ?></h6>
                    <small class="text-muted"><?= $desc ?></small>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Recent Trips -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between">
            <h6 class="mb-0 fw-bold">Recent Trips</h6>
            <a href="/pages/shipment/carry/history.php" class="btn btn-sm btn-outline-primary">View All</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr><th>Route</th><th>Date</th><th>Weight (kg)</th><th>Rate/kg</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php if (empty($recentTripsList)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No trips yet. <a href="/pages/shipment/carry/create-request.php">Post your first trip!</a></td></tr>
                <?php else: ?>
                <?php foreach ($recentTripsList as $t): ?>
                <?php $colors = ['active'=>'success','completed'=>'secondary','cancelled'=>'danger']; ?>
                <tr>
                    <td><strong><?= e($t['from_city']) ?></strong> → <?= e($t['to_city']) ?></td>
                    <td><?= formatDate($t['flight_date']) ?></td>
                    <td><?= number_format($t['available_weight'], 1) ?></td>
                    <td>$<?= number_format($t['rate_per_kg'], 2) ?></td>
                    <td><span class="badge bg-<?= $colors[$t['status']] ?? 'secondary' ?>"><?= ucfirst($t['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
