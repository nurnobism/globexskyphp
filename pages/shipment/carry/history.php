<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];

$stmt = $db->prepare("SELECT * FROM carry_trips WHERE user_id = ? AND status IN ('completed','cancelled') ORDER BY created_at DESC");
$stmt->execute([$userId]);
$history = $stmt->fetchAll();

$pageTitle = 'Carry Job History';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-clock-history text-secondary me-2"></i>Carry History</h3>
        <a href="/pages/shipment/carry/dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <?php if (empty($history)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-inbox text-muted display-3"></i>
            <h5 class="mt-3 text-muted">No history yet</h5>
            <p class="text-muted">Completed and cancelled trips will appear here.</p>
        </div>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Route</th>
                        <th>Flight Date</th>
                        <th>Weight (kg)</th>
                        <th>Rate/kg</th>
                        <th>Status</th>
                        <th>Posted</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($history as $t): ?>
                <?php $colors = ['completed'=>'success', 'cancelled'=>'danger']; ?>
                <tr>
                    <td>
                        <strong><?= e($t['from_city']) ?></strong>
                        <i class="bi bi-arrow-right text-muted mx-1"></i>
                        <?= e($t['to_city']) ?>
                    </td>
                    <td><?= formatDate($t['flight_date']) ?></td>
                    <td><?= number_format($t['available_weight'], 1) ?></td>
                    <td>$<?= number_format($t['rate_per_kg'], 2) ?></td>
                    <td><span class="badge bg-<?= $colors[$t['status']] ?? 'secondary' ?>"><?= ucfirst($t['status']) ?></span></td>
                    <td><?= formatDate($t['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
