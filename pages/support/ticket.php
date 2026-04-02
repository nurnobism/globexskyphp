<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db = getDB();
$userId = $_SESSION['user_id'] ?? 0;
$ticketId = (int) get('id', 0);

$ticket = null;
$replies = [];

if ($ticketId > 0) {
    $stmt = $db->prepare("SELECT * FROM support_tickets WHERE id = ? AND user_id = ?");
    $stmt->execute([$ticketId, $userId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($ticket) {
        $stmt = $db->prepare("SELECT tr.*, u.name AS author_name FROM ticket_replies tr LEFT JOIN users u ON tr.user_id = u.id WHERE tr.ticket_id = ? ORDER BY tr.created_at ASC");
        $stmt->execute([$ticketId]);
        $replies = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

$statusBadges = [
    'open'        => 'bg-success',
    'in_progress' => 'bg-primary',
    'resolved'    => 'bg-secondary',
    'closed'      => 'bg-dark',
    'pending'     => 'bg-warning text-dark',
];

$pageTitle = $ticket ? 'Ticket #' . $ticketId : 'Create Support Ticket';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <?php if ($ticket): ?>
        <div class="d-flex align-items-center mb-4">
            <a href="index.php" class="btn btn-outline-secondary me-3">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="h3 mb-0">Ticket #<?= $ticketId ?></h1>
                <small class="text-muted">Created <?= formatDateTime($ticket['created_at'] ?? '') ?></small>
            </div>
            <div class="ms-auto">
                <?php $badge = $statusBadges[$ticket['status'] ?? ''] ?? 'bg-secondary'; ?>
                <span class="badge <?= $badge ?> fs-6">
                    <?= e(ucwords(str_replace('_', ' ', $ticket['status'] ?? ''))) ?>
                </span>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title"><?= e($ticket['subject'] ?? '') ?></h5>
                <div class="d-flex gap-3 mb-3">
                    <?php if (!empty($ticket['category'])): ?>
                        <span class="badge bg-info"><?= e(ucfirst($ticket['category'])) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($ticket['priority'])): ?>
                        <?php
                        $priorityBadges = ['low' => 'bg-secondary', 'medium' => 'bg-warning text-dark', 'high' => 'bg-danger', 'urgent' => 'bg-danger'];
                        $pBadge = $priorityBadges[$ticket['priority']] ?? 'bg-secondary';
                        ?>
                        <span class="badge <?= $pBadge ?>"><?= e(ucfirst($ticket['priority'])) ?> Priority</span>
                    <?php endif; ?>
                </div>
                <p class="mb-0"><?= nl2br(e($ticket['description'] ?? '')) ?></p>
            </div>
        </div>

        <h5 class="mb-3"><i class="bi bi-chat-dots me-2"></i>Replies (<?= count($replies) ?>)</h5>

        <?php if (empty($replies)): ?>
            <p class="text-muted mb-4">No replies yet. Our team will respond soon.</p>
        <?php else: ?>
            <?php foreach ($replies as $reply): ?>
                <?php $isStaff = !empty($reply['is_staff']); ?>
                <div class="card shadow-sm mb-3 <?= $isStaff ? 'border-start border-4 border-primary' : '' ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <strong><?= e($reply['author_name'] ?? 'Unknown') ?></strong>
                                <?php if ($isStaff): ?>
                                    <span class="badge bg-primary ms-1">Staff</span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted"><?= formatDateTime($reply['created_at'] ?? '') ?></small>
                        </div>
                        <p class="mb-0"><?= nl2br(e($reply['message'] ?? '')) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (in_array($ticket['status'] ?? '', ['open', 'in_progress', 'pending'])): ?>
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="bi bi-reply me-1"></i>Add Reply</h6>
                </div>
                <div class="card-body">
                    <form method="post" action="../../api/support.php?action=reply">
                        <?= csrfField() ?>
                        <input type="hidden" name="ticket_id" value="<?= $ticketId ?>">
                        <div class="mb-3">
                            <textarea class="form-control" name="message" rows="4" required
                                      placeholder="Type your reply..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-1"></i>Send Reply
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="d-flex align-items-center mb-4">
                    <a href="index.php" class="btn btn-outline-secondary me-3">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <h1 class="h3 mb-0"><i class="bi bi-plus-circle me-2"></i>Create Support Ticket</h1>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <form method="post" action="../../api/support.php?action=create">
                            <?= csrfField() ?>

                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="subject" name="subject" required
                                       placeholder="Brief description of your issue">
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="description" name="description" rows="5" required
                                          placeholder="Provide details about your issue..."></textarea>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="">Select category...</option>
                                        <option value="general">General</option>
                                        <option value="orders">Orders</option>
                                        <option value="shipping">Shipping</option>
                                        <option value="payments">Payments</option>
                                        <option value="returns">Returns</option>
                                        <option value="account">Account</option>
                                        <option value="technical">Technical</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="priority" class="form-label">Priority <span class="text-danger">*</span></label>
                                    <select class="form-select" id="priority" name="priority" required>
                                        <option value="">Select priority...</option>
                                        <option value="low">Low</option>
                                        <option value="medium">Medium</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-send me-1"></i>Submit Ticket
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
