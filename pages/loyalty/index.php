<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db = getDB();
$userId = $_SESSION['user_id'];

$pointsRow = $db->prepare("SELECT COALESCE(SUM(CASE WHEN type='earned' THEN points ELSE -points END), 0) AS balance FROM loyalty_points WHERE user_id = ?");
$pointsRow->execute([$userId]);
$balance = (int)$pointsRow->fetch()['balance'];

if ($balance >= 5000) {
    $tier = 'Gold';
    $tierClass = 'warning';
    $tierIcon = 'bi-trophy-fill';
    $nextTier = null;
    $nextTierPoints = 0;
} elseif ($balance >= 1000) {
    $tier = 'Silver';
    $tierClass = 'secondary';
    $tierIcon = 'bi-award-fill';
    $nextTier = 'Gold';
    $nextTierPoints = 5000;
} else {
    $tier = 'Bronze';
    $tierClass = 'danger';
    $tierIcon = 'bi-shield-fill';
    $nextTier = 'Silver';
    $nextTierPoints = 1000;
}

$progressPct = 0;
if ($nextTier) {
    $prevThreshold = $tier === 'Silver' ? 1000 : 0;
    $progressPct = min(100, round((($balance - $prevThreshold) / ($nextTierPoints - $prevThreshold)) * 100));
}

$recentActivity = [];
try {
    $stmt = $db->prepare("SELECT * FROM loyalty_points WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$userId]);
    $recentActivity = $stmt->fetchAll();
} catch (\Exception $e) {
    $recentActivity = [];
}

$pageTitle = 'Loyalty Program';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-gem me-2"></i><?= e($pageTitle) ?></h1>
            <p class="text-muted mb-0">Earn and redeem loyalty points</p>
        </div>
        <div>
            <a href="/pages/loyalty/rewards.php" class="btn btn-outline-primary me-2"><i class="bi bi-gift me-1"></i>Rewards</a>
            <a href="/pages/loyalty/history.php" class="btn btn-outline-secondary"><i class="bi bi-clock-history me-1"></i>History</a>
        </div>
    </div>

    <!-- Points Balance Card -->
    <div class="row justify-content-center mb-4">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <div class="mb-3">
                        <span class="badge bg-<?= $tierClass ?> fs-5 px-3 py-2">
                            <i class="bi <?= $tierIcon ?> me-1"></i><?= $tier ?> Member
                        </span>
                    </div>
                    <h1 class="display-3 fw-bold text-primary mb-2"><?= number_format($balance) ?></h1>
                    <p class="text-muted fs-5 mb-4">Available Points</p>

                    <?php if ($nextTier): ?>
                        <div class="mx-auto" style="max-width: 400px;">
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted"><?= $tier ?></small>
                                <small class="fw-semibold"><?= $nextTier ?> (<?= number_format($nextTierPoints) ?> pts)</small>
                            </div>
                            <div class="progress" style="height: 12px;">
                                <div class="progress-bar bg-<?= $tierClass ?> progress-bar-striped progress-bar-animated" style="width: <?= $progressPct ?>%"></div>
                            </div>
                            <small class="text-muted mt-1 d-block"><?= number_format($nextTierPoints - $balance) ?> points to <?= $nextTier ?></small>
                        </div>
                    <?php else: ?>
                        <p class="text-success"><i class="bi bi-check-circle-fill me-1"></i>You've reached the highest tier!</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Tier Benefits -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 <?= $tier === 'Bronze' ? 'border-start border-3 border-danger' : '' ?>">
                <div class="card-body text-center">
                    <i class="bi bi-shield-fill fs-2 text-danger"></i>
                    <h6 class="mt-2">Bronze</h6>
                    <small class="text-muted">0 – 999 pts</small>
                    <ul class="list-unstyled mt-2 small">
                        <li><i class="bi bi-check text-success"></i> 1x point multiplier</li>
                        <li><i class="bi bi-check text-success"></i> Basic rewards access</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 <?= $tier === 'Silver' ? 'border-start border-3 border-secondary' : '' ?>">
                <div class="card-body text-center">
                    <i class="bi bi-award-fill fs-2 text-secondary"></i>
                    <h6 class="mt-2">Silver</h6>
                    <small class="text-muted">1,000 – 4,999 pts</small>
                    <ul class="list-unstyled mt-2 small">
                        <li><i class="bi bi-check text-success"></i> 1.5x point multiplier</li>
                        <li><i class="bi bi-check text-success"></i> Priority support</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 <?= $tier === 'Gold' ? 'border-start border-3 border-warning' : '' ?>">
                <div class="card-body text-center">
                    <i class="bi bi-trophy-fill fs-2 text-warning"></i>
                    <h6 class="mt-2">Gold</h6>
                    <small class="text-muted">5,000+ pts</small>
                    <ul class="list-unstyled mt-2 small">
                        <li><i class="bi bi-check text-success"></i> 2x point multiplier</li>
                        <li><i class="bi bi-check text-success"></i> Exclusive rewards</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0"><i class="bi bi-activity me-2"></i>Recent Activity</h5>
            <a href="/pages/loyalty/history.php" class="btn btn-sm btn-outline-primary">View All</a>
        </div>
        <ul class="list-group list-group-flush">
            <?php if (empty($recentActivity)): ?>
                <li class="list-group-item text-center text-muted py-4">No activity yet. Start earning points by making purchases!</li>
            <?php else: ?>
                <?php foreach ($recentActivity as $act): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <?php if ($act['type'] === 'earned'): ?>
                                <i class="bi bi-plus-circle-fill text-success me-2"></i>
                            <?php else: ?>
                                <i class="bi bi-dash-circle-fill text-warning me-2"></i>
                            <?php endif; ?>
                            <?= e($act['description'] ?? 'Points ' . $act['type']) ?>
                            <br><small class="text-muted"><?= formatDateTime($act['created_at']) ?></small>
                        </div>
                        <span class="badge bg-<?= $act['type'] === 'earned' ? 'success' : 'warning' ?> fs-6">
                            <?= $act['type'] === 'earned' ? '+' : '-' ?><?= number_format($act['points']) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
