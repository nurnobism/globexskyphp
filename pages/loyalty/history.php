<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db = getDB();
$userId = $_SESSION['user_id'];
$page = max(1, (int)get('page', 1));

$history = [];
$totalPages = 1;
try {
    $result = paginate(
        $db,
        "SELECT * FROM loyalty_points WHERE user_id = ? ORDER BY created_at DESC",
        [$userId],
        $page
    );
    $history = $result['data'];
    $totalPages = $result['pages'];
} catch (\Exception $e) {
    $history = [];
}

// Calculate running balance (from oldest to newest for the current page)
$balanceRow = $db->prepare("SELECT COALESCE(SUM(CASE WHEN type='earned' THEN points ELSE -points END), 0) AS balance FROM loyalty_points WHERE user_id = ?");
$balanceRow->execute([$userId]);
$totalBalance = (int)$balanceRow->fetch()['balance'];

$pageTitle = 'Points History';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-clock-history me-2"></i><?= e($pageTitle) ?></h1>
            <p class="text-muted mb-0">Complete record of your loyalty points</p>
        </div>
        <div>
            <span class="badge bg-primary fs-6 px-3 py-2"><i class="bi bi-gem me-1"></i><?= number_format($totalBalance) ?> Points</span>
            <a href="/pages/loyalty/index.php" class="btn btn-outline-secondary ms-2"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th class="text-center">Type</th>
                            <th class="text-end">Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-5">
                                <i class="bi bi-clock-history display-6 d-block mb-2"></i>
                                No points history yet. Start earning points by placing orders!
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($history as $entry):
                                $isEarned = $entry['type'] === 'earned';
                            ?>
                                <tr>
                                    <td>
                                        <i class="bi bi-calendar3 me-1 text-muted"></i>
                                        <?= formatDateTime($entry['created_at']) ?>
                                    </td>
                                    <td><?= e($entry['description'] ?? 'Points ' . $entry['type']) ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $isEarned ? 'success' : 'warning' ?>-subtle text-<?= $isEarned ? 'success' : 'warning' ?>">
                                            <i class="bi <?= $isEarned ? 'bi-plus-circle' : 'bi-dash-circle' ?> me-1"></i>
                                            <?= ucfirst(e($entry['type'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-end fw-semibold <?= $isEarned ? 'text-success' : 'text-warning' ?>">
                                        <?= $isEarned ? '+' : '-' ?><?= number_format($entry['points']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white">
                <nav>
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>">&laquo; Prev</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>">Next &raquo;</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
