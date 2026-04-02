<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$db = getDB();
$user = getCurrentUser();
$userId = $user['id'];
$page = max(1, (int)get('page', 1));

$sql = "SELECT e.*, 
        CASE WHEN e.buyer_id = ? THEN 'buyer' ELSE 'seller' END AS user_role_in_escrow,
        buyer.username AS buyer_name, seller.username AS seller_name
        FROM escrow_transactions e
        LEFT JOIN users buyer ON e.buyer_id = buyer.id
        LEFT JOIN users seller ON e.seller_id = seller.id
        WHERE e.buyer_id = ? OR e.seller_id = ?
        ORDER BY e.created_at DESC";
$result = paginate($db, $sql, [$userId, $userId, $userId], $page);
$transactions = $result['data'];
$pagination = $result;

$pageTitle = 'Escrow Transactions';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item active">Escrow</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2 mb-0"><i class="bi bi-shield-check me-2"></i>Escrow Transactions</h1>
        <a href="/pages/escrow/create.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>New Escrow
        </a>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <?php
        $statusCounts = ['pending' => 0, 'held' => 0, 'released' => 0, 'disputed' => 0];
        $totalAmount = 0;
        foreach ($transactions as $t) {
            $s = strtolower($t['status'] ?? 'pending');
            if (isset($statusCounts[$s])) $statusCounts[$s]++;
            $totalAmount += (float)($t['amount'] ?? 0);
        }
        ?>
        <div class="col-6 col-md-3">
            <div class="card text-center border-warning">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-warning"><?= $statusCounts['pending'] ?></div>
                    <small class="text-muted">Pending</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-info">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-info"><?= $statusCounts['held'] ?></div>
                    <small class="text-muted">Held</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-success">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-success"><?= $statusCounts['released'] ?></div>
                    <small class="text-muted">Released</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-danger">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-danger"><?= $statusCounts['disputed'] ?></div>
                    <small class="text-muted">Disputed</small>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($transactions)): ?>
        <div class="text-center py-5">
            <i class="bi bi-shield-check display-1 text-muted"></i>
            <h3 class="mt-3 text-muted">No Escrow Transactions</h3>
            <p class="text-muted">You don't have any escrow transactions yet.</p>
            <a href="/pages/escrow/create.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Create One</a>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Order Ref</th>
                            <th>Counterparty</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $txn): ?>
                            <?php
                                $role = $txn['user_role_in_escrow'] ?? 'buyer';
                                $counterparty = ($role === 'buyer')
                                    ? ($txn['seller_name'] ?? 'N/A')
                                    : ($txn['buyer_name'] ?? 'N/A');
                                $status = strtolower($txn['status'] ?? 'pending');
                                $statusMap = [
                                    'pending'  => ['warning', 'clock'],
                                    'held'     => ['info', 'lock'],
                                    'released' => ['success', 'check-circle'],
                                    'disputed' => ['danger', 'exclamation-triangle'],
                                ];
                                $badge = $statusMap[$status] ?? ['secondary', 'question-circle'];
                            ?>
                            <tr>
                                <td><code>#<?= e($txn['id']) ?></code></td>
                                <td><?= e($txn['order_id'] ?? $txn['order_ref'] ?? '—') ?></td>
                                <td>
                                    <i class="bi bi-person me-1"></i><?= e($counterparty) ?>
                                    <br><small class="text-muted">You are <?= e($role) ?></small>
                                </td>
                                <td class="fw-bold"><?= formatMoney($txn['amount'] ?? 0) ?></td>
                                <td>
                                    <span class="badge bg-<?= $badge[0] ?> <?= $status === 'pending' ? 'text-dark' : '' ?>">
                                        <i class="bi bi-<?= $badge[1] ?> me-1"></i><?= e(ucfirst($status)) ?>
                                    </span>
                                </td>
                                <td><?= formatDate($txn['created_at'] ?? '') ?></td>
                                <td>
                                    <a href="/pages/escrow/detail.php?id=<?= e($txn['id']) ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (($pagination['last_page'] ?? 1) > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>"><i class="bi bi-chevron-left"></i></a>
                    </li>
                    <?php for ($i = 1; $i <= $pagination['last_page']; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $pagination['last_page'] ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>"><i class="bi bi-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
