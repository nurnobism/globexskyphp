<?php
/**
 * pages/disputes/create.php — File a Dispute
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$db  = getDB();
$uid = $_SESSION['user_id'];

// Load orders eligible for dispute (delivered or in dispute window)
$stmt = $db->prepare(
    'SELECT o.id, o.order_number, o.placed_at, o.total, o.status
     FROM orders o
     WHERE o.buyer_id = ? AND o.status NOT IN ("cancelled")
     ORDER BY o.placed_at DESC
     LIMIT 50'
);
$stmt->execute([$uid]);
$orders = $stmt->fetchAll();

$pageTitle = 'File a Dispute';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/">Home</a></li>
                    <li class="breadcrumb-item"><a href="/pages/disputes/index.php">Disputes</a></li>
                    <li class="breadcrumb-item active">File a Dispute</li>
                </ol>
            </nav>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-shield-exclamation text-danger me-2"></i>File a Dispute
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info small mb-4">
                        <i class="bi bi-info-circle me-1"></i>
                        Please provide as much detail as possible to help us resolve your dispute quickly.
                        Our team will review within 2 business days.
                    </div>

                    <form method="post" action="/api/disputes.php?action=create">
                        <input type="hidden" name="_csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="_redirect" value="/pages/disputes/index.php">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Related Order <span class="text-danger">*</span></label>
                            <?php if (empty($orders)): ?>
                            <p class="text-muted small">No eligible orders found.</p>
                            <input type="text" name="order_id" class="form-control" placeholder="Order ID" required>
                            <?php else: ?>
                            <select name="order_id" class="form-select" required>
                                <option value="">— Select an order —</option>
                                <?php foreach ($orders as $o): ?>
                                <option value="<?= (int)$o['id'] ?>">
                                    <?= e($o['order_number']) ?> — <?= formatMoney((float)$o['total']) ?> (<?= date('M j, Y', strtotime($o['placed_at'])) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Dispute Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control"
                                   placeholder="e.g. Item not received, Wrong item shipped" required maxlength="200">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="5"
                                      placeholder="Describe the issue in detail…" required></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Evidence URL <span class="text-muted small">(optional)</span></label>
                            <input type="url" name="evidence_url" class="form-control"
                                   placeholder="https://example.com/photo.jpg">
                            <div class="form-text">Link to photos, screenshots, or other evidence.</div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-danger px-4">
                                <i class="bi bi-shield-exclamation me-1"></i>Submit Dispute
                            </button>
                            <a href="/pages/disputes/index.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
