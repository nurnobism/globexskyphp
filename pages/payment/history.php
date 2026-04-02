<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db = getDB();
$userId = $_SESSION['user_id'] ?? 0;
$page = (int) get('page', 1);

$dateFrom = get('date_from', '');
$dateTo = get('date_to', '');
$status = get('status', '');

$sql = "SELECT * FROM payment_transactions WHERE user_id = ?";
$params = [$userId];

if ($dateFrom) {
    $sql .= " AND created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo) {
    $sql .= " AND created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}
if ($status) {
    $sql .= " AND status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY created_at DESC";
$result = paginate($db, $sql, $params, $page);
$transactions = $result['data'] ?? [];
$pagination = $result['pagination'] ?? [];

$statusBadges = [
    'completed' => 'bg-success',
    'pending'   => 'bg-warning text-dark',
    'failed'    => 'bg-danger',
    'refunded'  => 'bg-info',
];

$pageTitle = 'Payment History';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-clock-history me-2"></i>Payment History</h1>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Payment Methods
        </a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="date_from" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="date_from" name="date_from"
                           value="<?= e($dateFrom) ?>">
                </div>
                <div class="col-md-3">
                    <label for="date_to" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="date_to" name="date_to"
                           value="<?= e($dateTo) ?>">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Statuses</option>
                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
                        <option value="refunded" <?= $status === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search me-1"></i>Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Order #</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Reference #</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No transactions found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $tx): ?>
                            <tr>
                                <td><?= formatDate($tx['created_at'] ?? '') ?></td>
                                <td>
                                    <a href="../orders/detail.php?id=<?= (int) ($tx['order_id'] ?? 0) ?>">
                                        #<?= e($tx['order_number'] ?? $tx['order_id'] ?? '-') ?>
                                    </a>
                                </td>
                                <td class="fw-semibold"><?= formatMoney($tx['amount'] ?? 0) ?></td>
                                <td><?= e($tx['method'] ?? '-') ?></td>
                                <td>
                                    <?php $badge = $statusBadges[$tx['status'] ?? ''] ?? 'bg-secondary'; ?>
                                    <span class="badge <?= $badge ?>">
                                        <?= e(ucfirst($tx['status'] ?? 'Unknown')) ?>
                                    </span>
                                </td>
                                <td><code><?= e($tx['reference'] ?? '-') ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (!empty($pagination['last_page']) && $pagination['last_page'] > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?>&date_from=<?= e($dateFrom) ?>&date_to=<?= e($dateTo) ?>&status=<?= e($status) ?>">Previous</a>
                </li>
                <?php for ($i = 1; $i <= $pagination['last_page']; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&date_from=<?= e($dateFrom) ?>&date_to=<?= e($dateTo) ?>&status=<?= e($status) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $pagination['last_page'] ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>&date_from=<?= e($dateFrom) ?>&date_to=<?= e($dateTo) ?>&status=<?= e($status) ?>">Next</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
