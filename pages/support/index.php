<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db = getDB();
$userId = $_SESSION['user_id'] ?? 0;

$openCount = 0;
$resolvedCount = 0;
$avgResponse = '—';

$stmt = $db->prepare("SELECT COUNT(*) FROM support_tickets WHERE user_id = ? AND status IN ('open', 'in_progress')");
$stmt->execute([$userId]);
$openCount = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM support_tickets WHERE user_id = ? AND status = 'resolved'");
$stmt->execute([$userId]);
$resolvedCount = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, first_reply_at)) FROM support_tickets WHERE user_id = ? AND first_reply_at IS NOT NULL");
$stmt->execute([$userId]);
$avgHours = $stmt->fetchColumn();
if ($avgHours !== null && $avgHours !== false) {
    $avgResponse = round($avgHours) . 'h';
}

$stmt = $db->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY updated_at DESC LIMIT 10");
$stmt->execute([$userId]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$statusBadges = [
    'open'        => 'bg-success',
    'in_progress' => 'bg-primary',
    'resolved'    => 'bg-secondary',
    'closed'      => 'bg-dark',
    'pending'     => 'bg-warning text-dark',
];

$pageTitle = 'Support Center';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-headset me-2"></i>Support Center</h1>
        <a href="ticket.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Create Ticket
        </a>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-start border-4 border-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Open Tickets</h6>
                            <h2 class="mb-0"><?= $openCount ?></h2>
                        </div>
                        <i class="bi bi-envelope-open fs-1 text-success"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-start border-4 border-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Resolved</h6>
                            <h2 class="mb-0"><?= $resolvedCount ?></h2>
                        </div>
                        <i class="bi bi-check-circle fs-1 text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-start border-4 border-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Avg Response Time</h6>
                            <h2 class="mb-0"><?= e($avgResponse) ?></h2>
                        </div>
                        <i class="bi bi-clock-history fs-1 text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Tickets</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Updated</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tickets)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No tickets yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($tickets as $ticket): ?>
                                    <tr>
                                        <td class="fw-semibold">#<?= (int) $ticket['id'] ?></td>
                                        <td><?= e($ticket['subject'] ?? '') ?></td>
                                        <td>
                                            <?php $badge = $statusBadges[$ticket['status'] ?? ''] ?? 'bg-secondary'; ?>
                                            <span class="badge <?= $badge ?>">
                                                <?= e(ucwords(str_replace('_', ' ', $ticket['status'] ?? ''))) ?>
                                            </span>
                                        </td>
                                        <td class="small"><?= formatDateTime($ticket['updated_at'] ?? '') ?></td>
                                        <td>
                                            <a href="ticket.php?id=<?= (int) $ticket['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-link-45deg me-1"></i>Quick Links</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="faq.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-question-circle me-2 text-primary"></i>Frequently Asked Questions
                    </a>
                    <a href="faq.php#orders" class="list-group-item list-group-item-action">
                        <i class="bi bi-box-seam me-2 text-info"></i>Order Help
                    </a>
                    <a href="faq.php#shipping" class="list-group-item list-group-item-action">
                        <i class="bi bi-truck me-2 text-success"></i>Shipping Information
                    </a>
                    <a href="faq.php#payments" class="list-group-item list-group-item-action">
                        <i class="bi bi-credit-card me-2 text-warning"></i>Payment Issues
                    </a>
                    <a href="faq.php#returns" class="list-group-item list-group-item-action">
                        <i class="bi bi-arrow-return-left me-2 text-danger"></i>Returns &amp; Refunds
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
