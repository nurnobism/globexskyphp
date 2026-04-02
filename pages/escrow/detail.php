<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$db = getDB();
$user = getCurrentUser();
$id = (int)get('id', 0);

if (!$id) {
    flashMessage('danger', 'Escrow transaction not found.');
    redirect('/pages/escrow/');
}

$stmt = $db->prepare("
    SELECT e.*, buyer.username AS buyer_name, seller.username AS seller_name
    FROM escrow_transactions e
    LEFT JOIN users buyer ON e.buyer_id = buyer.id
    LEFT JOIN users seller ON e.seller_id = seller.id
    WHERE e.id = ? AND (e.buyer_id = ? OR e.seller_id = ? OR ? = 1)
");
$isAdmin = isAdmin() ? 1 : 0;
$stmt->execute([$id, $user['id'], $user['id'], $isAdmin]);
$escrow = $stmt->fetch();

if (!$escrow) {
    flashMessage('danger', 'Escrow transaction not found or access denied.');
    redirect('/pages/escrow/');
}

$isBuyer = ((int)($escrow['buyer_id'] ?? 0) === (int)$user['id']);
$status = strtolower($escrow['status'] ?? 'pending');

$pageTitle = 'Escrow #' . $escrow['id'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item"><a href="/pages/escrow/">Escrow</a></li>
            <li class="breadcrumb-item active">#<?= e($escrow['id']) ?></li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2 mb-0">
            <i class="bi bi-shield-check me-2"></i>Escrow #<?= e($escrow['id']) ?>
        </h1>
        <?php
            $statusMap = [
                'pending'  => ['warning', 'clock', 'text-dark'],
                'held'     => ['info', 'lock', ''],
                'released' => ['success', 'check-circle', ''],
                'disputed' => ['danger', 'exclamation-triangle', ''],
                'refunded' => ['secondary', 'arrow-return-left', ''],
            ];
            $b = $statusMap[$status] ?? ['secondary', 'question-circle', ''];
        ?>
        <span class="badge bg-<?= $b[0] ?> <?= $b[2] ?> fs-6 px-3 py-2">
            <i class="bi bi-<?= $b[1] ?> me-1"></i><?= e(ucfirst($status)) ?>
        </span>
    </div>

    <div class="row g-4">
        <!-- Details -->
        <div class="col-lg-8">
            <!-- Status Timeline -->
            <div class="card shadow-sm mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Status Timeline</h5></div>
                <div class="card-body">
                    <?php
                    $steps = [
                        'pending'  => ['Created', 'Escrow transaction initiated'],
                        'held'     => ['Funds Held', 'Payment deposited into escrow'],
                        'released' => ['Released', 'Funds released to seller'],
                        'disputed' => ['Disputed', 'Transaction under review'],
                    ];
                    $order = ['pending', 'held', 'released'];
                    $currentIdx = array_search($status, $order);
                    if ($currentIdx === false) $currentIdx = -1;
                    ?>
                    <div class="d-flex justify-content-between position-relative mb-3">
                        <div class="position-absolute top-50 start-0 end-0" style="height:3px;background:#dee2e6;z-index:0;transform:translateY(-50%)"></div>
                        <?php foreach ($order as $idx => $step): ?>
                            <?php
                                $active = $idx <= $currentIdx;
                                $isCurrent = ($step === $status);
                                $color = $active ? 'primary' : 'secondary';
                                if ($status === 'disputed' && $step === 'released') $color = 'secondary';
                            ?>
                            <div class="text-center position-relative" style="z-index:1;flex:1">
                                <div class="rounded-circle d-inline-flex align-items-center justify-content-center mx-auto mb-2
                                     <?= $active ? 'bg-' . $color . ' text-white' : 'bg-light border' ?>"
                                     style="width:40px;height:40px">
                                    <?php if ($active && !$isCurrent): ?>
                                        <i class="bi bi-check-lg"></i>
                                    <?php elseif ($isCurrent): ?>
                                        <i class="bi bi-circle-fill" style="font-size:0.5rem"></i>
                                    <?php else: ?>
                                        <i class="bi bi-circle" style="font-size:0.5rem"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="small fw-bold <?= $active ? '' : 'text-muted' ?>"><?= $steps[$step][0] ?></div>
                                <div class="small text-muted d-none d-md-block"><?= $steps[$step][1] ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($status === 'disputed'): ?>
                        <div class="alert alert-danger mb-0 mt-3">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            This transaction is currently under dispute. Our team is reviewing the case.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Transaction Details -->
            <div class="card shadow-sm mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Transaction Details</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label text-muted small mb-0">Order Reference</label>
                            <p class="fw-bold mb-2"><?= e($escrow['order_id'] ?? $escrow['order_ref'] ?? '—') ?></p>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label text-muted small mb-0">Amount</label>
                            <p class="fw-bold fs-4 text-primary mb-2"><?= formatMoney($escrow['amount'] ?? 0) ?>
                                <small class="text-muted"><?= e($escrow['currency'] ?? 'USD') ?></small>
                            </p>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label text-muted small mb-0">Buyer</label>
                            <p class="mb-2">
                                <i class="bi bi-person me-1"></i><?= e($escrow['buyer_name'] ?? 'N/A') ?>
                                <?php if ($isBuyer): ?><span class="badge bg-primary">You</span><?php endif; ?>
                            </p>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label text-muted small mb-0">Seller</label>
                            <p class="mb-2">
                                <i class="bi bi-shop me-1"></i><?= e($escrow['seller_name'] ?? 'N/A') ?>
                                <?php if (!$isBuyer): ?><span class="badge bg-primary">You</span><?php endif; ?>
                            </p>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label text-muted small mb-0">Created</label>
                            <p class="mb-2"><?= formatDateTime($escrow['created_at'] ?? '') ?></p>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label text-muted small mb-0">Last Updated</label>
                            <p class="mb-2"><?= formatDateTime($escrow['updated_at'] ?? $escrow['created_at'] ?? '') ?></p>
                        </div>
                        <?php if (!empty($escrow['description'])): ?>
                            <div class="col-12">
                                <label class="form-label text-muted small mb-0">Description</label>
                                <p class="mb-0"><?= e($escrow['description']) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions Sidebar -->
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Actions</h5></div>
                <div class="card-body">
                    <div id="actionAlert"></div>

                    <?php if ($isBuyer && $status === 'held'): ?>
                        <form method="post" action="/api/escrow.php?action=release" class="mb-3"
                              onsubmit="return confirm('Are you sure you want to release the funds to the seller? This action cannot be undone.')">
                            <?= csrfField() ?>
                            <input type="hidden" name="escrow_id" value="<?= e($escrow['id']) ?>">
                            <button type="submit" class="btn btn-success w-100 mb-2">
                                <i class="bi bi-unlock me-2"></i>Release Funds
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($status === 'held'): ?>
                        <form method="post" action="/api/escrow.php?action=dispute"
                              onsubmit="return confirm('Are you sure you want to dispute this transaction?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="escrow_id" value="<?= e($escrow['id']) ?>">
                            <div class="mb-3">
                                <label for="dispute_reason" class="form-label">Reason for Dispute</label>
                                <textarea class="form-control" id="dispute_reason" name="reason" rows="3"
                                          placeholder="Describe the issue..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-danger w-100">
                                <i class="bi bi-exclamation-triangle me-2"></i>Dispute Transaction
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($status === 'released'): ?>
                        <div class="text-center text-success py-3">
                            <i class="bi bi-check-circle display-4"></i>
                            <p class="mt-2 mb-0 fw-bold">Transaction Complete</p>
                            <small class="text-muted">Funds have been released.</small>
                        </div>
                    <?php endif; ?>

                    <?php if ($status === 'pending'): ?>
                        <div class="text-center text-warning py-3">
                            <i class="bi bi-hourglass-split display-4"></i>
                            <p class="mt-2 mb-0 fw-bold">Awaiting Payment</p>
                            <small class="text-muted">Funds have not yet been deposited.</small>
                        </div>
                    <?php endif; ?>

                    <hr>
                    <a href="/pages/escrow/" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-arrow-left me-1"></i>Back to Escrow List
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
