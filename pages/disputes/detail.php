<?php
/**
 * pages/disputes/detail.php — Dispute Detail
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$db  = getDB();
$uid = $_SESSION['user_id'];
$id  = (int)get('id', 0);

if (!$id) {
    flashMessage('danger', 'Dispute ID is required.');
    redirect('/pages/disputes/index.php');
}

$stmt = $db->prepare(
    'SELECT d.*, o.order_number, o.total order_total,
            CONCAT(bu.first_name, " ", bu.last_name) buyer_name,
            CONCAT(su.first_name, " ", su.last_name) seller_name
     FROM disputes d
     LEFT JOIN orders o ON o.id = d.order_id
     LEFT JOIN users bu ON bu.id = d.buyer_id
     LEFT JOIN users su ON su.id = d.seller_id
     WHERE d.id = ? AND (d.buyer_id = ? OR d.seller_id = ?)'
);
$stmt->execute([$id, $uid, $uid]);
$dispute = $stmt->fetch();

if (!$dispute) {
    flashMessage('danger', 'Dispute not found or access denied.');
    redirect('/pages/disputes/index.php');
}

$pageTitle = 'Dispute #' . $id;
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item"><a href="/pages/disputes/index.php">Disputes</a></li>
            <li class="breadcrumb-item active">Dispute #<?= $id ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <!-- Dispute Info -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-shield-exclamation text-danger me-2"></i>
                        <?= e($dispute['title']) ?>
                    </h5>
                    <?php
                    $statusMap = [
                        'open'     => 'badge bg-danger',
                        'resolved' => 'badge bg-success',
                        'closed'   => 'badge bg-secondary',
                    ];
                    $cls = $statusMap[$dispute['status']] ?? 'badge bg-secondary';
                    ?>
                    <span class="<?= $cls ?> fs-6"><?= ucfirst(e($dispute['status'])) ?></span>
                </div>
                <div class="card-body">
                    <dl class="row mb-4">
                        <dt class="col-sm-4">Order</dt>
                        <dd class="col-sm-8">
                            <?php if (!empty($dispute['order_number'])): ?>
                            <a href="/pages/order/detail.php?id=<?= (int)$dispute['order_id'] ?>">
                                <?= e($dispute['order_number']) ?>
                            </a> — <?= formatMoney((float)$dispute['order_total']) ?>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-4">Filed by</dt>
                        <dd class="col-sm-8"><?= e($dispute['buyer_name']) ?></dd>

                        <dt class="col-sm-4">Against</dt>
                        <dd class="col-sm-8"><?= e($dispute['seller_name'] ?? 'N/A') ?></dd>

                        <dt class="col-sm-4">Filed on</dt>
                        <dd class="col-sm-8"><?= date('M j, Y g:i A', strtotime($dispute['created_at'])) ?></dd>

                        <?php if (!empty($dispute['resolved_at'])): ?>
                        <dt class="col-sm-4">Resolved on</dt>
                        <dd class="col-sm-8"><?= date('M j, Y g:i A', strtotime($dispute['resolved_at'])) ?></dd>
                        <?php endif; ?>
                    </dl>

                    <h6 class="fw-semibold">Description</h6>
                    <p class="text-muted"><?= nl2br(e($dispute['description'])) ?></p>

                    <?php if (!empty($dispute['evidence_url'])): ?>
                    <h6 class="fw-semibold mt-3">Evidence</h6>
                    <a href="<?= e($dispute['evidence_url']) ?>" target="_blank" rel="noopener noreferrer" class="text-break">
                        <i class="bi bi-link-45deg me-1"></i><?= e($dispute['evidence_url']) ?>
                    </a>
                    <?php endif; ?>

                    <?php if (!empty($dispute['resolution_note'])): ?>
                    <div class="alert alert-success mt-4">
                        <h6 class="fw-semibold"><i class="bi bi-check-circle me-1"></i>Resolution</h6>
                        <p class="mb-0"><?= nl2br(e($dispute['resolution_note'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Update Form (if open) -->
            <?php if ($dispute['status'] === 'open'): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-semibold">Update Dispute</h6>
                </div>
                <div class="card-body">
                    <form method="post" action="/api/disputes.php?action=update">
                        <input type="hidden" name="_csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="_redirect" value="/pages/disputes/detail.php?id=<?= $id ?>">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Additional Information</label>
                            <textarea name="description" class="form-control" rows="4"
                                      placeholder="Add more details or updates…"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Additional Evidence URL</label>
                            <input type="url" name="evidence_url" class="form-control"
                                   placeholder="https://example.com/photo.jpg">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-arrow-up-circle me-1"></i>Update Dispute
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Dispute Timeline</h6>
                    <ul class="list-unstyled">
                        <li class="d-flex gap-2 mb-3">
                            <i class="bi bi-circle-fill text-danger mt-1" style="font-size:.6rem"></i>
                            <div>
                                <strong class="d-block small">Dispute Filed</strong>
                                <span class="text-muted small"><?= date('M j, Y', strtotime($dispute['created_at'])) ?></span>
                            </div>
                        </li>
                        <?php if (!empty($dispute['updated_at']) && $dispute['updated_at'] !== $dispute['created_at']): ?>
                        <li class="d-flex gap-2 mb-3">
                            <i class="bi bi-circle-fill text-warning mt-1" style="font-size:.6rem"></i>
                            <div>
                                <strong class="d-block small">Last Updated</strong>
                                <span class="text-muted small"><?= date('M j, Y', strtotime($dispute['updated_at'])) ?></span>
                            </div>
                        </li>
                        <?php endif; ?>
                        <?php if ($dispute['status'] === 'resolved'): ?>
                        <li class="d-flex gap-2">
                            <i class="bi bi-circle-fill text-success mt-1" style="font-size:.6rem"></i>
                            <div>
                                <strong class="d-block small">Resolved</strong>
                                <span class="text-muted small"><?= date('M j, Y', strtotime($dispute['resolved_at'] ?? $dispute['updated_at'])) ?></span>
                            </div>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <a href="/pages/disputes/index.php" class="btn btn-outline-secondary w-100">
                <i class="bi bi-arrow-left me-1"></i>Back to Disputes
            </a>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
