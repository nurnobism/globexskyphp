<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$page   = max(1, (int)get('page', 1));
$perPage = 25;

$countStmt = $db->prepare('SELECT COUNT(*) FROM webmail_messages WHERE sender_id=? AND is_draft=0');
$countStmt->execute([$userId]);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare(
    "SELECT wm.*,
            GROUP_CONCAT(DISTINCT CONCAT(u.first_name, ' ', u.last_name) SEPARATOR ', ') as recipients,
            (SELECT COUNT(*) FROM webmail_attachments wa WHERE wa.message_id=wm.id) as attachment_count
     FROM webmail_messages wm
     LEFT JOIN webmail_recipients wr ON wr.message_id=wm.id AND wr.type='to'
     LEFT JOIN users u ON u.id=wr.user_id
     WHERE wm.sender_id=? AND wm.is_draft=0
     GROUP BY wm.id
     ORDER BY wm.created_at DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute([$userId]);
$messages = $stmt->fetchAll();

$pageTitle = 'Webmail - Sent';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2">
            <a href="compose.php" class="btn btn-primary w-100 mb-3"><i class="bi bi-pencil-square me-1"></i> Compose</a>
            <div class="list-group list-group-flush mb-3">
                <a href="inbox.php" class="list-group-item list-group-item-action"><i class="bi bi-inbox me-2"></i>Inbox</a>
                <a href="sent.php" class="list-group-item list-group-item-action active"><i class="bi bi-send me-2"></i>Sent</a>
                <a href="drafts.php" class="list-group-item list-group-item-action"><i class="bi bi-file-earmark me-2"></i>Drafts</a>
                <a href="trash.php" class="list-group-item list-group-item-action"><i class="bi bi-trash me-2"></i>Trash</a>
            </div>
        </div>

        <!-- Main -->
        <div class="col-md-9 col-lg-10">
            <?php if (get('msg') === 'sent'): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle me-1"></i> Message sent successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-send me-2"></i>Sent Messages</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($messages)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-send display-1 text-muted"></i>
                            <p class="text-muted mt-3">No sent messages</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($messages as $msg): ?>
                                <a href="read.php?id=<?= (int)$msg['id'] ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <span class="text-muted">To: </span>
                                            <span class="fw-semibold"><?= e($msg['recipients'] ?: 'Unknown') ?></span>
                                            <?php if ($msg['attachment_count'] > 0): ?>
                                                <i class="bi bi-paperclip text-muted ms-1"></i>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted"><?= date('M j, g:i A', strtotime($msg['created_at'])) ?></small>
                                    </div>
                                    <div><?= e($msg['subject'] ?? '(No Subject)') ?></div>
                                    <small class="text-muted"><?= e(mb_substr(strip_tags($msg['body'] ?? ''), 0, 100)) ?>...</small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($pages > 1): ?>
                <div class="card-footer bg-white">
                    <nav><ul class="pagination pagination-sm justify-content-center mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?page=<?= $page-1 ?>">Prev</a></li>
                        <?php for ($i = max(1,$page-2); $i <= min($pages,$page+2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a></li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="?page=<?= $page+1 ?>">Next</a></li>
                    </ul></nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
