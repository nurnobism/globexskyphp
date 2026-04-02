<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db = getDB();
$userId = $_SESSION['user_id'] ?? 0;
$page = (int) get('page', 1);
$statusFilter = get('status', '');

$sql = "SELECT sr.*, c.name AS category_name, (SELECT COUNT(*) FROM sourcing_quotes sq WHERE sq.request_id = sr.id) AS quote_count FROM sourcing_requests sr LEFT JOIN categories c ON sr.category_id = c.id WHERE sr.user_id = ?";
$params = [$userId];

if ($statusFilter) {
    $sql .= " AND sr.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY sr.created_at DESC";
$result = paginate($db, $sql, $params, $page);
$requests = $result['data'] ?? [];
$pagination = $result['pagination'] ?? [];

$statusBadges = [
    'open'      => 'bg-success',
    'closed'    => 'bg-secondary',
    'pending'   => 'bg-warning text-dark',
    'awarded'   => 'bg-primary',
    'cancelled' => 'bg-danger',
];

$pageTitle = 'Sourcing Requests';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-megaphone me-2"></i>Sourcing Requests</h1>
        <a href="create.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Create Request
        </a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body py-2">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-auto">
                    <label class="form-label mb-0 fw-semibold">Filter:</label>
                </div>
                <div class="col-auto">
                    <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Open</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="awarded" <?= $statusFilter === 'awarded' ? 'selected' : '' ?>>Awarded</option>
                        <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
                        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Budget Range</th>
                        <th>Deadline</th>
                        <th class="text-center">Quotes</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No sourcing requests found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $req): ?>
                            <tr>
                                <td class="fw-semibold"><?= e($req['title'] ?? '') ?></td>
                                <td><?= e($req['category_name'] ?? '—') ?></td>
                                <td>
                                    <?= formatMoney($req['budget_min'] ?? 0) ?> – <?= formatMoney($req['budget_max'] ?? 0) ?>
                                </td>
                                <td><?= formatDate($req['deadline'] ?? '') ?></td>
                                <td class="text-center">
                                    <span class="badge bg-info"><?= (int) ($req['quote_count'] ?? 0) ?></span>
                                </td>
                                <td>
                                    <?php $badge = $statusBadges[$req['status'] ?? ''] ?? 'bg-secondary'; ?>
                                    <span class="badge <?= $badge ?>"><?= e(ucfirst($req['status'] ?? 'Unknown')) ?></span>
                                </td>
                                <td>
                                    <a href="detail.php?id=<?= (int) $req['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye me-1"></i>View
                                    </a>
                                </td>
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
                    <a class="page-link" href="?page=<?= $page - 1 ?>&status=<?= e($statusFilter) ?>">Previous</a>
                </li>
                <?php for ($i = 1; $i <= $pagination['last_page']; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&status=<?= e($statusFilter) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $pagination['last_page'] ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>&status=<?= e($statusFilter) ?>">Next</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
