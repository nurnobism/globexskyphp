<?php
/**
 * pages/disputes/index.php — My Disputes
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$db  = getDB();
$uid = $_SESSION['user_id'];

$stmt = $db->prepare(
    'SELECT d.*, o.order_number,
            CONCAT(bu.first_name, " ", bu.last_name) buyer_name,
            CONCAT(su.first_name, " ", su.last_name) seller_name
     FROM disputes d
     LEFT JOIN orders o ON o.id = d.order_id
     LEFT JOIN users bu ON bu.id = d.buyer_id
     LEFT JOIN users su ON su.id = d.seller_id
     WHERE d.buyer_id = ? OR d.seller_id = ?
     ORDER BY d.created_at DESC'
);
$stmt->execute([$uid, $uid]);
$disputes = $stmt->fetchAll();

$pageTitle = 'My Disputes';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold mb-0"><i class="bi bi-shield-exclamation text-danger me-2"></i>My Disputes</h3>
            <p class="text-muted small mb-0">Track and manage your open disputes</p>
        </div>
        <a href="/pages/disputes/create.php" class="btn btn-danger">
            <i class="bi bi-plus-circle me-1"></i>File a Dispute
        </a>
    </div>

    <?php if (empty($disputes)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-shield-check display-3"></i>
        <h5 class="mt-3">No disputes found</h5>
        <p>You don't have any open or resolved disputes.</p>
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
                        <th>Title</th>
                        <th>Status</th>
                        <th>Filed</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($disputes as $d): ?>
                <tr>
                    <td class="text-muted small"><?= (int)$d['id'] ?></td>
                    <td>
                        <?php if (!empty($d['order_number'])): ?>
                        <a href="/pages/order/detail.php?id=<?= (int)$d['order_id'] ?>" class="text-decoration-none fw-semibold">
                            <?= e($d['order_number']) ?>
                        </a>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e(mb_strimwidth($d['title'], 0, 60, '…')) ?></td>
                    <td>
                        <?php
                        $statusMap = [
                            'open'     => 'badge bg-danger',
                            'resolved' => 'badge bg-success',
                            'closed'   => 'badge bg-secondary',
                        ];
                        $cls = $statusMap[$d['status']] ?? 'badge bg-secondary';
                        ?>
                        <span class="<?= $cls ?>"><?= ucfirst(e($d['status'])) ?></span>
                    </td>
                    <td class="text-muted small"><?= date('M j, Y', strtotime($d['created_at'])) ?></td>
                    <td>
                        <a href="/pages/disputes/detail.php?id=<?= (int)$d['id'] ?>"
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
