<?php
/**
 * pages/returns/detail.php — Return Request Detail
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$db  = getDB();
$uid = $_SESSION['user_id'];
$id  = (int)get('id', 0);

if (!$id) {
    flashMessage('danger', 'Return request ID is required.');
    redirect('/pages/returns/index.php');
}

$stmt = $db->prepare(
    'SELECT r.*, o.order_number, o.total order_total
     FROM return_requests r
     LEFT JOIN orders o ON o.id = r.order_id
     WHERE r.id = ? AND r.user_id = ?'
);
$stmt->execute([$id, $uid]);
$return = $stmt->fetch();

if (!$return) {
    flashMessage('danger', 'Return request not found.');
    redirect('/pages/returns/index.php');
}

$reasonLabels = [
    'wrong_item'      => 'Wrong item received',
    'damaged'         => 'Item arrived damaged',
    'not_as_described'=> 'Not as described',
    'defective'       => 'Item is defective',
    'changed_mind'    => 'Changed my mind',
    'other'           => 'Other',
];

$pageTitle = 'Return Request #' . $id;
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item"><a href="/pages/returns/index.php">Returns</a></li>
            <li class="breadcrumb-item active">Return #<?= $id ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <!-- Main Details -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-arrow-return-left text-warning me-2"></i>Return Request #<?= $id ?>
                    </h5>
                    <?php
                    $statusMap = [
                        'pending'  => 'badge bg-warning text-dark',
                        'approved' => 'badge bg-info',
                        'rejected' => 'badge bg-danger',
                        'refunded' => 'badge bg-success',
                        'shipped'  => 'badge bg-primary',
                    ];
                    $cls = $statusMap[$return['status']] ?? 'badge bg-secondary';
                    ?>
                    <span class="<?= $cls ?> fs-6"><?= ucfirst(e($return['status'])) ?></span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Order</dt>
                        <dd class="col-sm-8">
                            <?php if (!empty($return['order_number'])): ?>
                            <a href="/pages/order/detail.php?id=<?= (int)$return['order_id'] ?>">
                                <?= e($return['order_number']) ?>
                            </a> — <?= formatMoney((float)$return['order_total']) ?>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-4">Reason</dt>
                        <dd class="col-sm-8">
                            <?= e($reasonLabels[$return['reason_code'] ?? ''] ?? ucfirst($return['reason_code'] ?? 'N/A')) ?>
                        </dd>

                        <dt class="col-sm-4">Resolution Requested</dt>
                        <dd class="col-sm-8"><?= ucwords(str_replace('_', ' ', e($return['resolution_type'] ?? 'refund'))) ?></dd>

                        <?php if (!empty($return['refund_amount'])): ?>
                        <dt class="col-sm-4">Refund Amount</dt>
                        <dd class="col-sm-8 fw-bold text-success"><?= formatMoney((float)$return['refund_amount']) ?></dd>
                        <?php endif; ?>

                        <dt class="col-sm-4">Requested on</dt>
                        <dd class="col-sm-8"><?= date('M j, Y g:i A', strtotime($return['created_at'])) ?></dd>

                        <?php if (!empty($return['reviewed_at'])): ?>
                        <dt class="col-sm-4">Reviewed on</dt>
                        <dd class="col-sm-8"><?= date('M j, Y g:i A', strtotime($return['reviewed_at'])) ?></dd>
                        <?php endif; ?>
                    </dl>

                    <?php if (!empty($return['reason'])): ?>
                    <hr>
                    <h6 class="fw-semibold">Description</h6>
                    <p class="text-muted"><?= nl2br(e($return['reason'])) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($return['evidence_url'])): ?>
                    <h6 class="fw-semibold mt-3">Evidence</h6>
                    <a href="<?= e($return['evidence_url']) ?>" target="_blank" rel="noopener noreferrer" class="text-break">
                        <i class="bi bi-link-45deg me-1"></i><?= e($return['evidence_url']) ?>
                    </a>
                    <?php endif; ?>

                    <?php if (!empty($return['admin_notes'])): ?>
                    <div class="alert alert-info mt-4">
                        <h6 class="fw-semibold"><i class="bi bi-chat-text me-1"></i>Admin Notes</h6>
                        <p class="mb-0"><?= nl2br(e($return['admin_notes'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if ($return['status'] === 'refunded'): ?>
                    <div class="alert alert-success mt-4">
                        <i class="bi bi-check-circle me-1"></i>
                        Your refund of <strong><?= formatMoney((float)$return['refund_amount']) ?></strong>
                        has been processed. Please allow 3–5 business days to appear in your account.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Timeline Sidebar -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Return Status</h6>
                    <?php
                    $steps = [
                        ['pending',  'Request Submitted', 'bi-hourglass'],
                        ['approved', 'Approved',          'bi-check-circle'],
                        ['shipped',  'Return Shipped',    'bi-box-seam'],
                        ['refunded', 'Refunded',          'bi-cash-coin'],
                    ];
                    $statusOrder = ['pending' => 0, 'approved' => 1, 'shipped' => 2, 'refunded' => 3];
                    $currentIdx  = $statusOrder[$return['status']] ?? -1;
                    ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($steps as [$key, $label, $icon]): ?>
                        <?php
                        $idx  = $statusOrder[$key];
                        $done = $currentIdx >= $idx;
                        $curr = $currentIdx === $idx;
                        ?>
                        <li class="d-flex gap-2 mb-3">
                            <i class="bi <?= $icon ?> mt-1 <?= $done ? 'text-success' : 'text-muted' ?>"
                               style="font-size:1rem"></i>
                            <span class="<?= $curr ? 'fw-bold' : ($done ? 'text-success' : 'text-muted') ?> small">
                                <?= $label ?>
                                <?php if ($key === 'rejected' && $return['status'] === 'rejected'): ?>
                                <span class="badge bg-danger ms-1">Rejected</span>
                                <?php endif; ?>
                            </span>
                        </li>
                        <?php endforeach; ?>
                        <?php if ($return['status'] === 'rejected'): ?>
                        <li class="d-flex gap-2 mb-3">
                            <i class="bi bi-x-circle mt-1 text-danger" style="font-size:1rem"></i>
                            <span class="fw-bold text-danger small">Request Rejected</span>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <a href="/pages/returns/index.php" class="btn btn-outline-secondary w-100">
                <i class="bi bi-arrow-left me-1"></i>Back to Returns
            </a>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
