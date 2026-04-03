<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$page   = max(1, (int)get('page', 1));
$perPage = 25;

// Delete draft
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf() && isset($_POST['delete_draft'])) {
    $draftId = (int)$_POST['delete_draft'];
    $db->prepare('DELETE FROM webmail_messages WHERE id=? AND sender_id=? AND is_draft=1')->execute([$draftId, $userId]);
    header('Location: drafts.php');
    exit;
}

$countStmt = $db->prepare('SELECT COUNT(*) FROM webmail_messages WHERE sender_id=? AND is_draft=1');
$countStmt->execute([$userId]);
$total  = (int)$countStmt->fetchColumn();
$pages  = max(1, (int)ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare(
    "SELECT wm.*,
            GROUP_CONCAT(DISTINCT u.email SEPARATOR ', ') as recipients
     FROM webmail_messages wm
     LEFT JOIN webmail_recipients wr ON wr.message_id=wm.id AND wr.type='to'
     LEFT JOIN users u ON u.id=wr.user_id
     WHERE wm.sender_id=? AND wm.is_draft=1
     GROUP BY wm.id
     ORDER BY wm.updated_at DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute([$userId]);
$drafts = $stmt->fetchAll();

$pageTitle = 'Webmail - Drafts';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-3 col-lg-2">
            <a href="compose.php" class="btn btn-primary w-100 mb-3"><i class="bi bi-pencil-square me-1"></i> Compose</a>
            <div class="list-group list-group-flush mb-3">
                <a href="inbox.php" class="list-group-item list-group-item-action"><i class="bi bi-inbox me-2"></i>Inbox</a>
                <a href="sent.php" class="list-group-item list-group-item-action"><i class="bi bi-send me-2"></i>Sent</a>
                <a href="drafts.php" class="list-group-item list-group-item-action active"><i class="bi bi-file-earmark me-2"></i>Drafts</a>
                <a href="trash.php" class="list-group-item list-group-item-action"><i class="bi bi-trash me-2"></i>Trash</a>
            </div>
        </div>

        <div class="col-md-9 col-lg-10">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-file-earmark me-2"></i>Drafts</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($drafts)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-file-earmark display-1 text-muted"></i>
                            <p class="text-muted mt-3">No drafts</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($drafts as $draft): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <a href="compose.php?draft=<?= (int)$draft['id'] ?>" class="text-decoration-none text-dark flex-grow-1">
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted">To: <?= e($draft['recipients'] ?: 'No recipients') ?></span>
                                                <small class="text-muted"><?= date('M j, g:i A', strtotime($draft['updated_at'])) ?></small>
                                            </div>
                                            <div class="fw-semibold"><?= e($draft['subject'] ?: '(No Subject)') ?></div>
                                            <small class="text-muted"><?= e(mb_substr(strip_tags($draft['body'] ?? ''), 0, 100)) ?></small>
                                        </a>
                                        <form method="POST" class="ms-2">
                                            <?= csrfField() ?>
                                            <button type="submit" name="delete_draft" value="<?= (int)$draft['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete Draft">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
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
