<?php
/**
 * pages/returns/index.php — My Return Requests
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$db  = getDB();
$uid = $_SESSION['user_id'];

$stmt = $db->prepare(
    'SELECT r.*, o.order_number
     FROM return_requests r
     LEFT JOIN orders o ON o.id = r.order_id
     WHERE r.user_id = ?
     ORDER BY r.created_at DESC'
);
$stmt->execute([$uid]);
$returns = $stmt->fetchAll();

$pageTitle = 'My Returns & Refunds';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold mb-0"><i class="bi bi-arrow-return-left text-warning me-2"></i>Returns &amp; Refunds</h3>
            <p class="text-muted small mb-0">Track your return and refund requests</p>
        </div>
        <a href="/pages/returns/create.php" class="btn btn-warning">
            <i class="bi bi-plus-circle me-1"></i>Request a Return
        </a>
    </div>

    <?php if (empty($returns)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-box-arrow-left display-3"></i>
        <h5 class="mt-3">No return requests found</h5>
        <p>You haven't requested any returns yet.</p>
        <a href="/pages/order/index.php" class="btn btn-outline-primary">View My Orders</a>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Order</th>
                        <th>Reason</th>
                        <th>Refund Amount</th>
                        <th>Status</th>
                        <th>Requested</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($returns as $r): ?>
                <tr>
                    <td class="text-muted small"><?= (int)$r['id'] ?></td>
                    <td>
                        <?php if (!empty($r['order_number'])): ?>
                        <a href="/pages/order/detail.php?id=<?= (int)$r['order_id'] ?>" class="text-decoration-none fw-semibold">
                            <?= e($r['order_number']) ?>
                        </a>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= e(mb_strimwidth($r['reason'], 0, 50, '…')) ?></td>
                    <td class="fw-semibold">
                        <?= !empty($r['refund_amount']) ? formatMoney((float)$r['refund_amount']) : '—' ?>
                    </td>
                    <td>
                        <?php
                        $statusMap = [
                            'pending'   => 'badge bg-warning text-dark',
                            'approved'  => 'badge bg-info',
                            'rejected'  => 'badge bg-danger',
                            'refunded'  => 'badge bg-success',
                            'shipped'   => 'badge bg-primary',
                        ];
                        $cls = $statusMap[$r['status']] ?? 'badge bg-secondary';
                        ?>
                        <span class="<?= $cls ?>"><?= ucfirst(e($r['status'])) ?></span>
                    </td>
                    <td class="text-muted small"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                    <td>
                        <a href="/pages/returns/detail.php?id=<?= (int)$r['id'] ?>"
                           class="btn btn-sm btn-outline-primary">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
