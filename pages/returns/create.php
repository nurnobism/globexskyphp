<?php
/**
 * pages/returns/create.php — Request a Return/Refund
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$db  = getDB();
$uid = $_SESSION['user_id'];

// Fetch delivered orders eligible for return
$stmt = $db->prepare(
    'SELECT o.id, o.order_number, o.placed_at, o.total
     FROM orders o
     WHERE o.buyer_id = ? AND o.status = "delivered"
     ORDER BY o.placed_at DESC
     LIMIT 50'
);
$stmt->execute([$uid]);
$orders = $stmt->fetchAll();

$reasons = [
    'wrong_item'      => 'Wrong item received',
    'damaged'         => 'Item arrived damaged',
    'not_as_described'=> 'Not as described',
    'defective'       => 'Item is defective',
    'changed_mind'    => 'Changed my mind',
    'other'           => 'Other',
];

$pageTitle = 'Request a Return';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/">Home</a></li>
                    <li class="breadcrumb-item"><a href="/pages/returns/index.php">Returns</a></li>
                    <li class="breadcrumb-item active">Request a Return</li>
                </ol>
            </nav>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-arrow-return-left text-warning me-2"></i>Request a Return / Refund
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info small mb-4">
                        <i class="bi bi-info-circle me-1"></i>
                        Please provide as much detail as possible to help us process your return request.
                        Our team will review your request within 3 business days.
                    </div>

                    <form method="post" action="/api/returns.php?action=create">
                        <input type="hidden" name="_csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="_redirect" value="/pages/returns/index.php">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Order <span class="text-danger">*</span></label>
                            <?php if (empty($orders)): ?>
                            <div class="alert alert-warning small">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                No delivered orders found eligible for return.
                                <a href="/pages/order/index.php">View all orders</a>
                            </div>
                            <?php else: ?>
                            <select name="order_id" class="form-select" required>
                                <option value="">— Select a delivered order —</option>
                                <?php foreach ($orders as $o): ?>
                                <option value="<?= (int)$o['id'] ?>">
                                    <?= e($o['order_number']) ?> — <?= formatMoney((float)$o['total']) ?>
                                    (<?= date('M j, Y', strtotime($o['placed_at'])) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Return Reason <span class="text-danger">*</span></label>
                            <select name="reason_code" class="form-select" required id="reasonSelect">
                                <option value="">— Select a reason —</option>
                                <?php foreach ($reasons as $code => $label): ?>
                                <option value="<?= e($code) ?>"><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Details <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control" rows="4"
                                      placeholder="Describe the issue in detail…" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Preferred Resolution</label>
                            <div class="d-flex gap-3 flex-wrap">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="resolution_type"
                                           id="resRefund" value="refund" checked>
                                    <label class="form-check-label" for="resRefund">Full Refund</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="resolution_type"
                                           id="resReplace" value="replacement">
                                    <label class="form-check-label" for="resReplace">Replacement</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="resolution_type"
                                           id="resPartial" value="partial_refund">
                                    <label class="form-check-label" for="resPartial">Partial Refund</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Evidence URL <span class="text-muted small">(optional)</span></label>
                            <input type="url" name="evidence_url" class="form-control"
                                   placeholder="https://example.com/photo.jpg">
                            <div class="form-text">Link to photos showing the issue.</div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-warning px-4" <?= empty($orders) ? 'disabled' : '' ?>>
                                <i class="bi bi-arrow-return-left me-1"></i>Submit Request
                            </button>
                            <a href="/pages/returns/index.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
