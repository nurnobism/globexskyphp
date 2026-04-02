<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db = getDB();
$userId = $_SESSION['user_id'] ?? 0;
$id = (int) get('id', 0);

$stmt = $db->prepare("SELECT sr.*, c.name AS category_name FROM sourcing_requests sr LEFT JOIN categories c ON sr.category_id = c.id WHERE sr.id = ?");
$stmt->execute([$id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

$quotes = [];
$isOwner = false;
if ($request) {
    $isOwner = ((int) ($request['user_id'] ?? 0)) === $userId;
    $stmt = $db->prepare("SELECT sq.*, s.company_name AS supplier_name FROM sourcing_quotes sq LEFT JOIN suppliers s ON sq.supplier_id = s.id WHERE sq.request_id = ? ORDER BY sq.created_at DESC");
    $stmt->execute([$id]);
    $quotes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$statusBadges = [
    'open'      => 'bg-success',
    'closed'    => 'bg-secondary',
    'pending'   => 'bg-warning text-dark',
    'awarded'   => 'bg-primary',
    'cancelled' => 'bg-danger',
];

$quoteStatusBadges = [
    'pending'  => 'bg-warning text-dark',
    'accepted' => 'bg-success',
    'rejected' => 'bg-danger',
];

$pageTitle = 'Sourcing Request Details';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="index.php" class="btn btn-outline-secondary me-3">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="h3 mb-0"><i class="bi bi-megaphone me-2"></i>Sourcing Request Details</h1>
    </div>

    <?php if (!$request): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>Sourcing request not found.
        </div>
    <?php else: ?>
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <h4 class="mb-3"><?= e($request['title'] ?? '') ?></h4>
                        <p class="text-muted"><?= nl2br(e($request['description'] ?? '')) ?></p>

                        <?php if (!empty($request['requirements'])): ?>
                            <h6 class="mt-3">Special Requirements</h6>
                            <p class="text-muted"><?= nl2br(e($request['requirements'])) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light rounded p-3">
                            <dl class="mb-0">
                                <dt class="text-muted small">Status</dt>
                                <dd>
                                    <?php $badge = $statusBadges[$request['status'] ?? ''] ?? 'bg-secondary'; ?>
                                    <span class="badge <?= $badge ?>"><?= e(ucfirst($request['status'] ?? 'Unknown')) ?></span>
                                </dd>
                                <dt class="text-muted small">Category</dt>
                                <dd><?= e($request['category_name'] ?? '—') ?></dd>
                                <dt class="text-muted small">Quantity</dt>
                                <dd><?= number_format((int) ($request['quantity'] ?? 0)) ?></dd>
                                <dt class="text-muted small">Budget</dt>
                                <dd><?= formatMoney($request['budget_min'] ?? 0) ?> – <?= formatMoney($request['budget_max'] ?? 0) ?></dd>
                                <dt class="text-muted small">Deadline</dt>
                                <dd><?= formatDate($request['deadline'] ?? '') ?></dd>
                                <dt class="text-muted small">Created</dt>
                                <dd class="mb-0"><?= formatDate($request['created_at'] ?? '') ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="bi bi-chat-left-quote me-2"></i>Quotes Received (<?= count($quotes) ?>)</h5>
        </div>

        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Supplier</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Delivery Time</th>
                            <th>Notes</th>
                            <th>Status</th>
                            <?php if ($isOwner): ?>
                                <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($quotes)): ?>
                            <tr>
                                <td colspan="<?= $isOwner ? 7 : 6 ?>" class="text-center py-4 text-muted">
                                    No quotes received yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($quotes as $quote): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e($quote['supplier_name'] ?? 'Unknown') ?></td>
                                    <td><?= formatMoney($quote['price'] ?? 0) ?></td>
                                    <td><?= number_format((int) ($quote['quantity'] ?? 0)) ?></td>
                                    <td><?= e($quote['delivery_time'] ?? '—') ?></td>
                                    <td><?= e($quote['notes'] ?? '—') ?></td>
                                    <td>
                                        <?php $qBadge = $quoteStatusBadges[$quote['status'] ?? ''] ?? 'bg-secondary'; ?>
                                        <span class="badge <?= $qBadge ?>"><?= e(ucfirst($quote['status'] ?? 'Unknown')) ?></span>
                                    </td>
                                    <?php if ($isOwner): ?>
                                        <td>
                                            <?php if (($quote['status'] ?? '') === 'pending'): ?>
                                                <div class="btn-group btn-group-sm">
                                                    <form method="post" action="../../api/sourcing.php?action=accept_quote" class="d-inline">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="quote_id" value="<?= (int) $quote['id'] ?>">
                                                        <input type="hidden" name="request_id" value="<?= $id ?>">
                                                        <button type="submit" class="btn btn-sm btn-success" title="Accept">
                                                            <i class="bi bi-check-lg"></i>
                                                        </button>
                                                    </form>
                                                    <form method="post" action="../../api/sourcing.php?action=reject_quote" class="d-inline">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="quote_id" value="<?= (int) $quote['id'] ?>">
                                                        <input type="hidden" name="request_id" value="<?= $id ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" title="Reject">
                                                            <i class="bi bi-x-lg"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
