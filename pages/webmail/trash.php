<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$page   = max(1, (int)get('page', 1));
$perPage = 25;

// Bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $ids    = array_map('intval', $_POST['ids'] ?? []);
    $action = $_POST['bulk_action'] ?? '';
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params       = array_merge($ids, [$userId]);
        switch ($action) {
            case 'restore':
                $db->prepare("UPDATE webmail_recipients SET is_trashed=0, trashed_at=NULL WHERE message_id IN ($placeholders) AND user_id=?")->execute($params);
                break;
            case 'permanent_delete':
                $db->prepare("DELETE FROM webmail_recipients WHERE message_id IN ($placeholders) AND user_id=?")->execute($params);
                break;
        }
    }
    if (isset($_POST['restore_id'])) {
        $db->prepare('UPDATE webmail_recipients SET is_trashed=0, trashed_at=NULL WHERE message_id=? AND user_id=?')
           ->execute([(int)$_POST['restore_id'], $userId]);
    }
    if (isset($_POST['perm_delete_id'])) {
        $db->prepare('DELETE FROM webmail_recipients WHERE message_id=? AND user_id=?')
           ->execute([(int)$_POST['perm_delete_id'], $userId]);
    }
    header('Location: trash.php?page=' . $page);
    exit;
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM webmail_recipients wr JOIN webmail_messages wm ON wm.id=wr.message_id WHERE wr.user_id=? AND wr.is_trashed=1");
$countStmt->execute([$userId]);
$total  = (int)$countStmt->fetchColumn();
$pages  = max(1, (int)ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare(
    "SELECT wm.*, wr.trashed_at,
            u.first_name AS sender_first, u.last_name AS sender_last
     FROM webmail_recipients wr
     JOIN webmail_messages wm ON wm.id=wr.message_id
     JOIN users u ON u.id=wm.sender_id
     WHERE wr.user_id=? AND wr.is_trashed=1
     ORDER BY wr.trashed_at DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute([$userId]);
$messages = $stmt->fetchAll();

$pageTitle = 'Webmail - Trash';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-3 col-lg-2">
            <a href="compose.php" class="btn btn-primary w-100 mb-3"><i class="bi bi-pencil-square me-1"></i> Compose</a>
            <div class="list-group list-group-flush mb-3">
                <a href="inbox.php" class="list-group-item list-group-item-action"><i class="bi bi-inbox me-2"></i>Inbox</a>
                <a href="sent.php" class="list-group-item list-group-item-action"><i class="bi bi-send me-2"></i>Sent</a>
                <a href="drafts.php" class="list-group-item list-group-item-action"><i class="bi bi-file-earmark me-2"></i>Drafts</a>
                <a href="trash.php" class="list-group-item list-group-item-action active"><i class="bi bi-trash me-2"></i>Trash</a>
            </div>
        </div>

        <div class="col-md-9 col-lg-10">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-trash me-2"></i>Trash</h5>
                        <small class="text-muted">Messages in trash are auto-purged after 30 days</small>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($messages)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-trash display-1 text-muted"></i>
                            <p class="text-muted mt-3">Trash is empty</p>
                        </div>
                    <?php else: ?>
                        <form method="POST" id="bulkForm">
                            <?= csrfField() ?>
                            <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom bg-light">
                                <input type="checkbox" id="selectAll" class="form-check-input">
                                <select name="bulk_action" class="form-select form-select-sm" style="width:auto">
                                    <option value="">Bulk Action</option>
                                    <option value="restore">Restore</option>
                                    <option value="permanent_delete">Delete Permanently</option>
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Apply</button>
                            </div>
                            <div class="list-group list-group-flush">
                                <?php foreach ($messages as $msg): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex align-items-center">
                                            <input type="checkbox" name="ids[]" value="<?= (int)$msg['id'] ?>" class="form-check-input me-3 msg-check">
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between">
                                                    <span><?= e(($msg['sender_first'] ?? '') . ' ' . ($msg['sender_last'] ?? '')) ?></span>
                                                    <small class="text-muted">Trashed <?= date('M j', strtotime($msg['trashed_at'] ?? $msg['created_at'])) ?></small>
                                                </div>
                                                <div><?= e($msg['subject'] ?? '(No Subject)') ?></div>
                                            </div>
                                            <div class="ms-2 d-flex gap-1">
                                                <form method="POST" class="d-inline">
                                                    <?= csrfField() ?>
                                                    <button type="submit" name="restore_id" value="<?= (int)$msg['id'] ?>" class="btn btn-sm btn-outline-success" title="Restore"><i class="bi bi-arrow-counterclockwise"></i></button>
                                                </form>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Permanently delete?')">
                                                    <?= csrfField() ?>
                                                    <button type="submit" name="perm_delete_id" value="<?= (int)$msg['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete Permanently"><i class="bi bi-x-lg"></i></button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </form>
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

<script>
document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.msg-check').forEach(cb => cb.checked = this.checked);
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
