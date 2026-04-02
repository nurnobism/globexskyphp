<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db = getDB();
$userId = $_SESSION['user_id'] ?? 0;

$stmt = $db->prepare("SELECT sr.*, p.name AS product_name FROM sample_requests sr LEFT JOIN products p ON sr.product_id = p.id WHERE sr.user_id = ? ORDER BY sr.created_at DESC");
$stmt->execute([$userId]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$statusBadges = [
    'pending'   => 'bg-warning text-dark',
    'approved'  => 'bg-info',
    'shipped'   => 'bg-primary',
    'delivered' => 'bg-success',
    'rejected'  => 'bg-danger',
];

$pageTitle = 'Sample Tracking';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-truck me-2"></i>Sample Request Tracking</h1>
        <a href="index.php" class="btn btn-primary">
            <i class="bi bi-gift me-1"></i>Browse Samples
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Product</th>
                        <th class="text-center">Quantity</th>
                        <th>Status</th>
                        <th>Tracking Number</th>
                        <th>Requested Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox display-6 d-block mb-2"></i>
                                No sample requests yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $req): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($req['product_name'] ?? 'Unknown Product') ?></div>
                                </td>
                                <td class="text-center"><?= (int) ($req['quantity'] ?? 0) ?></td>
                                <td>
                                    <?php $badge = $statusBadges[$req['status'] ?? ''] ?? 'bg-secondary'; ?>
                                    <span class="badge <?= $badge ?>">
                                        <?= e(ucfirst($req['status'] ?? 'Unknown')) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($req['tracking_number'])): ?>
                                        <code><?= e($req['tracking_number']) ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatDate($req['created_at'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
