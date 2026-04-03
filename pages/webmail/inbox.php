<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db      = getDB();
$userId  = (int)$_SESSION['user_id'];
$page    = max(1, (int)get('page', 1));
$perPage = 25;
$search  = trim(get('q', ''));
$label   = trim(get('label', ''));

// Bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $ids    = array_map('intval', $_POST['ids'] ?? []);
    $action = $_POST['bulk_action'] ?? '';
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params       = array_merge($ids, [$userId]);
        switch ($action) {
            case 'mark_read':
                $db->prepare("UPDATE webmail_recipients SET is_read=1, read_at=NOW() WHERE message_id IN ($placeholders) AND user_id=?")->execute($params);
                break;
            case 'trash':
                $db->prepare("UPDATE webmail_recipients SET is_trashed=1, trashed_at=NOW() WHERE message_id IN ($placeholders) AND user_id=?")->execute($params);
                break;
            case 'delete':
                $db->prepare("DELETE FROM webmail_recipients WHERE message_id IN ($placeholders) AND user_id=?")->execute($params);
                break;
        }
    }
    header('Location: inbox.php?page=' . $page);
    exit;
}

// Build query
$where  = ['wr.user_id = ?', 'wr.is_trashed = 0', "wr.type IN ('to','cc')"];
$params = [$userId];
if ($search !== '') {
    $where[]  = '(wm.subject LIKE ? OR wm.body LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($label !== '') {
    $where[]  = 'wr.label = ?';
    $params[] = $label;
}
$whereClause = implode(' AND ', $where);

$countSql = "SELECT COUNT(*) FROM webmail_recipients wr JOIN webmail_messages wm ON wm.id=wr.message_id WHERE $whereClause AND wm.is_draft=0";
$total    = (int)$db->prepare($countSql)->execute($params) ? (int)$db->prepare($countSql) : 0;
$stmtC    = $db->prepare($countSql);
$stmtC->execute($params);
$total    = (int)$stmtC->fetchColumn();
$pages    = max(1, (int)ceil($total / $perPage));
$offset   = ($page - 1) * $perPage;

$sql = "SELECT wm.*, wr.is_read, wr.label, wr.id as recipient_id,
               u.first_name AS sender_first, u.last_name AS sender_last, u.email AS sender_email,
               (SELECT COUNT(*) FROM webmail_attachments wa WHERE wa.message_id=wm.id) as attachment_count
        FROM webmail_recipients wr
        JOIN webmail_messages wm ON wm.id = wr.message_id
        JOIN users u ON u.id = wm.sender_id
        WHERE $whereClause AND wm.is_draft=0
        ORDER BY wm.created_at DESC
        LIMIT $perPage OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$messages = $stmt->fetchAll();

// Labels
$labelStmt = $db->prepare('SELECT * FROM webmail_labels WHERE user_id=? ORDER BY sort_order');
$labelStmt->execute([$userId]);
$labels = $labelStmt->fetchAll();

// Unread count
$unreadStmt = $db->prepare("SELECT COUNT(*) FROM webmail_recipients wr JOIN webmail_messages wm ON wm.id=wr.message_id WHERE wr.user_id=? AND wr.is_read=0 AND wr.is_trashed=0 AND wm.is_draft=0 AND wr.type IN ('to','cc')");
$unreadStmt->execute([$userId]);
$unreadCount = (int)$unreadStmt->fetchColumn();

$pageTitle = 'Webmail - Inbox';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2">
            <a href="<?= APP_URL ?>/pages/webmail/compose.php" class="btn btn-primary w-100 mb-3">
                <i class="bi bi-pencil-square me-1"></i> Compose
            </a>
            <div class="list-group list-group-flush mb-3">
                <a href="inbox.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center active">
                    <span><i class="bi bi-inbox me-2"></i>Inbox</span>
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge bg-danger rounded-pill"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="sent.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-send me-2"></i>Sent
                </a>
                <a href="drafts.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-file-earmark me-2"></i>Drafts
                </a>
                <a href="trash.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-trash me-2"></i>Trash
                </a>
            </div>
            <?php if (!empty($labels)): ?>
            <h6 class="text-muted small text-uppercase mb-2">Labels</h6>
            <div class="list-group list-group-flush mb-3">
                <?php foreach ($labels as $lbl): ?>
                    <a href="inbox.php?label=<?= urlencode($lbl['name']) ?>" class="list-group-item list-group-item-action <?= $label === $lbl['name'] ? 'active' : '' ?>">
                        <i class="bi bi-tag-fill me-2" style="color:<?= e($lbl['color']) ?>"></i><?= e($lbl['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <a href="labels.php" class="btn btn-sm btn-outline-secondary w-100"><i class="bi bi-tags me-1"></i>Manage Labels</a>
        </div>

        <!-- Main -->
        <div class="col-md-9 col-lg-10">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0"><i class="bi bi-inbox me-2"></i>Inbox</h5>
                        <form class="d-flex gap-2" method="GET">
                            <input type="text" name="q" class="form-control form-control-sm" placeholder="Search mail..." value="<?= e($search) ?>">
                            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
                        </form>
                    </div>
                </div>
                <div class="card-body p-0">
                    <form method="POST" id="bulkForm">
                        <?= csrfField() ?>
                        <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom bg-light">
                            <input type="checkbox" id="selectAll" class="form-check-input">
                            <select name="bulk_action" class="form-select form-select-sm" style="width:auto">
                                <option value="">Bulk Action</option>
                                <option value="mark_read">Mark Read</option>
                                <option value="trash">Move to Trash</option>
                                <option value="delete">Delete</option>
                            </select>
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Apply</button>
                            <span class="ms-auto text-muted small"><?= $total ?> messages</span>
                        </div>

                        <?php if (empty($messages)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox display-1 text-muted"></i>
                                <p class="text-muted mt-3">Your inbox is empty</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($messages as $msg): ?>
                                    <?php $isUnread = empty($msg['is_read']); ?>
                                    <div class="list-group-item list-group-item-action <?= $isUnread ? 'bg-light-subtle' : '' ?>">
                                        <div class="d-flex align-items-center">
                                            <input type="checkbox" name="ids[]" value="<?= (int)$msg['id'] ?>" class="form-check-input me-3 msg-check">
                                            <a href="read.php?id=<?= (int)$msg['id'] ?>" class="text-decoration-none text-dark flex-grow-1">
                                                <div class="d-flex justify-content-between">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="<?= $isUnread ? 'fw-bold' : '' ?>">
                                                            <?= e(($msg['sender_first'] ?? '') . ' ' . ($msg['sender_last'] ?? '')) ?>
                                                        </span>
                                                        <?php if ($msg['priority'] === 'high'): ?>
                                                            <span class="badge bg-warning text-dark">High</span>
                                                        <?php elseif ($msg['priority'] === 'urgent'): ?>
                                                            <span class="badge bg-danger">Urgent</span>
                                                        <?php endif; ?>
                                                        <?php if ($msg['attachment_count'] > 0): ?>
                                                            <i class="bi bi-paperclip text-muted"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <small class="text-muted"><?= date('M j, g:i A', strtotime($msg['created_at'])) ?></small>
                                                </div>
                                                <div class="<?= $isUnread ? 'fw-semibold' : '' ?>"><?= e($msg['subject'] ?? '(No Subject)') ?></div>
                                                <small class="text-muted"><?= e(mb_substr(strip_tags($msg['body'] ?? ''), 0, 100)) ?>...</small>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
                <?php if ($pages > 1): ?>
                <div class="card-footer bg-white">
                    <nav>
                        <ul class="pagination pagination-sm justify-content-center mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>">Prev</a>
                            </li>
                            <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($search) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
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
