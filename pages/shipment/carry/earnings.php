<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];

// Earnings summary
$stmt = $db->prepare("SELECT
    COALESCE(SUM(earning), 0) AS total,
    COALESCE(SUM(CASE WHEN paid = 0 THEN earning END), 0) AS pending,
    COALESCE(SUM(CASE WHEN paid = 1 THEN earning END), 0) AS withdrawn
    FROM carrier_earnings WHERE user_id = ?");
$stmt->execute([$userId]);
$summary = $stmt->fetch();

// Earnings list
$stmt2 = $db->prepare("SELECT * FROM carrier_earnings WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt2->execute([$userId]);
$earningsList = $stmt2->fetchAll();

$pageTitle = 'My Earnings';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-cash-stack text-warning me-2"></i>My Earnings</h3>
        <a href="/pages/shipment/carry/dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <?php $cards = [
            ['Total Earned', formatMoney($summary['total']), 'trophy-fill', 'warning'],
            ['Pending Payout', formatMoney($summary['pending']), 'hourglass-split', 'info'],
            ['Withdrawn', formatMoney($summary['withdrawn']), 'check-circle-fill', 'success'],
        ]; ?>
        <?php foreach ($cards as [$label, $value, $icon, $color]): ?>
        <div class="col-md-4">
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

    <!-- Payout Request -->
    <?php if ($summary['pending'] > 0): ?>
    <div class="card border-0 shadow-sm mb-4 border-start border-warning border-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h6 class="fw-bold mb-1">Ready for Payout</h6>
                <p class="text-muted mb-0">You have <?= formatMoney($summary['pending']) ?> available for withdrawal.</p>
            </div>
            <form method="POST" action="/api/carry.php?action=request_payout">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-warning">
                    <i class="bi bi-wallet2 me-1"></i> Request Payout
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Earnings Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold">Earnings History</h6>
        </div>
        <?php if (empty($earningsList)): ?>
        <div class="card-body text-center py-5">
            <i class="bi bi-coin text-muted display-3"></i>
            <h5 class="mt-3 text-muted">No earnings yet</h5>
            <p class="text-muted">Complete carry trips to start earning.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Date</th><th>Description</th><th>Amount</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($earningsList as $e): ?>
                <tr>
                    <td><?= formatDate($e['created_at']) ?></td>
                    <td><?= e($e['description'] ?? 'Carry service payment') ?></td>
                    <td class="fw-bold text-success">+<?= formatMoney($e['earning']) ?></td>
                    <td>
                        <span class="badge bg-<?= $e['paid'] ? 'success' : 'warning' ?>">
                            <?= $e['paid'] ? 'Paid' : 'Pending' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
