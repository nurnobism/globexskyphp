<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$msgId  = (int)get('id', 0);

if (!$msgId) {
    header('Location: inbox.php');
    exit;
}

// Get the message
$stmt = $db->prepare(
    'SELECT wm.*, u.first_name AS sender_first, u.last_name AS sender_last, u.email AS sender_email, u.avatar AS sender_avatar
     FROM webmail_messages wm
     JOIN users u ON u.id = wm.sender_id
     WHERE wm.id = ?'
);
$stmt->execute([$msgId]);
$message = $stmt->fetch();

if (!$message) {
    header('Location: inbox.php');
    exit;
}

// Verify access: user must be sender or recipient
$accessStmt = $db->prepare('SELECT id FROM webmail_recipients WHERE message_id=? AND user_id=? LIMIT 1');
$accessStmt->execute([$msgId, $userId]);
$isRecipient = (bool)$accessStmt->fetch();
$isSender    = ((int)$message['sender_id'] === $userId);

if (!$isRecipient && !$isSender) {
    header('Location: inbox.php');
    exit;
}

// Mark as read if recipient
if ($isRecipient) {
    $db->prepare('UPDATE webmail_recipients SET is_read=1, read_at=NOW() WHERE message_id=? AND user_id=? AND is_read=0')
       ->execute([$msgId, $userId]);
}

// Get recipients
$recipStmt = $db->prepare(
    "SELECT wr.type, u.first_name, u.last_name, u.email
     FROM webmail_recipients wr
     JOIN users u ON u.id = wr.user_id
     WHERE wr.message_id = ?
     ORDER BY wr.type"
);
$recipStmt->execute([$msgId]);
$recipients = $recipStmt->fetchAll();

// Get attachments
$attStmt = $db->prepare('SELECT * FROM webmail_attachments WHERE message_id=?');
$attStmt->execute([$msgId]);
$attachments = $attStmt->fetchAll();

// Get thread messages
$threadMessages = [];
if ($message['thread_id']) {
    $thStmt = $db->prepare(
        'SELECT wm.*, u.first_name, u.last_name, u.email
         FROM webmail_messages wm
         JOIN users u ON u.id = wm.sender_id
         WHERE wm.thread_id = ? AND wm.id != ? AND wm.is_draft = 0
         ORDER BY wm.created_at ASC'
    );
    $thStmt->execute([$message['thread_id'], $msgId]);
    $threadMessages = $thStmt->fetchAll();
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    if (isset($_POST['trash'])) {
        $db->prepare('UPDATE webmail_recipients SET is_trashed=1, trashed_at=NOW() WHERE message_id=? AND user_id=?')
           ->execute([$msgId, $userId]);
        header('Location: inbox.php');
        exit;
    }
    if (isset($_POST['mark_unread'])) {
        $db->prepare('UPDATE webmail_recipients SET is_read=0, read_at=NULL WHERE message_id=? AND user_id=?')
           ->execute([$msgId, $userId]);
        header('Location: inbox.php');
        exit;
    }
}

$pageTitle = e($message['subject'] ?? 'Message');
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-3 col-lg-2">
            <a href="compose.php" class="btn btn-primary w-100 mb-3"><i class="bi bi-pencil-square me-1"></i> Compose</a>
            <div class="list-group list-group-flush mb-3">
                <a href="inbox.php" class="list-group-item list-group-item-action"><i class="bi bi-inbox me-2"></i>Inbox</a>
                <a href="sent.php" class="list-group-item list-group-item-action"><i class="bi bi-send me-2"></i>Sent</a>
                <a href="drafts.php" class="list-group-item list-group-item-action"><i class="bi bi-file-earmark me-2"></i>Drafts</a>
                <a href="trash.php" class="list-group-item list-group-item-action"><i class="bi bi-trash me-2"></i>Trash</a>
            </div>
        </div>

        <div class="col-md-9 col-lg-10">
            <!-- Action Bar -->
            <div class="d-flex gap-2 mb-3 flex-wrap">
                <a href="inbox.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
                <a href="compose.php?reply_to=<?= $msgId ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-reply me-1"></i>Reply</a>
                <a href="compose.php?forward=<?= $msgId ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-forward me-1"></i>Forward</a>
                <form method="POST" class="d-inline">
                    <?= csrfField() ?>
                    <button type="submit" name="mark_unread" value="1" class="btn btn-outline-secondary btn-sm"><i class="bi bi-envelope me-1"></i>Mark Unread</button>
                </form>
                <form method="POST" class="d-inline">
                    <?= csrfField() ?>
                    <button type="submit" name="trash" value="1" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Delete</button>
                </form>
            </div>

            <!-- Message -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-1">
                        <?= e($message['subject'] ?? '(No Subject)') ?>
                        <?php if ($message['priority'] === 'high'): ?>
                            <span class="badge bg-warning text-dark ms-1">High Priority</span>
                        <?php elseif ($message['priority'] === 'urgent'): ?>
                            <span class="badge bg-danger ms-1">Urgent</span>
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-start mb-3">
                        <div class="me-3">
                            <?php if (!empty($message['sender_avatar'])): ?>
                                <img src="<?= e($message['sender_avatar']) ?>" class="rounded-circle" style="width:48px;height:48px;object-fit:cover">
                            <?php else: ?>
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:48px;height:48px;font-size:1.2rem">
                                    <?= strtoupper(substr($message['sender_first'] ?? 'U', 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-bold"><?= e(($message['sender_first'] ?? '') . ' ' . ($message['sender_last'] ?? '')) ?></div>
                            <small class="text-muted">&lt;<?= e($message['sender_email']) ?>&gt;</small>
                            <div class="small text-muted mt-1">
                                <?= date('l, F j, Y \a\t g:i A', strtotime($message['created_at'])) ?>
                            </div>
                            <div class="small text-muted">
                                <?php
                                $toList = array_filter($recipients, fn($r) => $r['type'] === 'to');
                                $ccList = array_filter($recipients, fn($r) => $r['type'] === 'cc');
                                ?>
                                <strong>To:</strong> <?= e(implode(', ', array_map(fn($r) => $r['first_name'] . ' ' . $r['last_name'] . ' <' . $r['email'] . '>', $toList))) ?>
                                <?php if (!empty($ccList)): ?>
                                    <br><strong>CC:</strong> <?= e(implode(', ', array_map(fn($r) => $r['first_name'] . ' ' . $r['last_name'] . ' <' . $r['email'] . '>', $ccList))) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <hr>
                    <div class="message-body">
                        <?php if (!empty($message['body_html'])): ?>
                            <?= $message['body_html'] ?>
                        <?php else: ?>
                            <?= nl2br(e($message['body'] ?? '')) ?>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($attachments)): ?>
                        <hr>
                        <h6><i class="bi bi-paperclip me-1"></i>Attachments (<?= count($attachments) ?>)</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($attachments as $att): ?>
                                <a href="<?= e($att['file_url']) ?>" class="btn btn-sm btn-outline-secondary" download="<?= e($att['file_name']) ?>">
                                    <i class="bi bi-download me-1"></i>
                                    <?= e($att['file_name']) ?>
                                    <small class="text-muted">(<?= number_format($att['file_size'] / 1024, 1) ?>KB)</small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Thread -->
            <?php if (!empty($threadMessages)): ?>
                <h6 class="mt-4 mb-3"><i class="bi bi-chat-left-text me-1"></i>Thread (<?= count($threadMessages) ?> more messages)</h6>
                <?php foreach ($threadMessages as $tm): ?>
                    <div class="card shadow-sm mb-2">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <strong><?= e($tm['first_name'] . ' ' . $tm['last_name']) ?></strong>
                                <small class="text-muted"><?= date('M j, g:i A', strtotime($tm['created_at'])) ?></small>
                            </div>
                            <div class="mt-2">
                                <?php if (!empty($tm['body_html'])): ?>
                                    <?= $tm['body_html'] ?>
                                <?php else: ?>
                                    <?= nl2br(e($tm['body'] ?? '')) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Quick Reply -->
            <div class="card shadow-sm mt-3">
                <div class="card-body">
                    <form method="POST" action="compose.php">
                        <?= csrfField() ?>
                        <input type="hidden" name="reply_to" value="<?= $msgId ?>">
                        <div class="mb-2">
                            <textarea name="body" class="form-control" rows="3" placeholder="Write a quick reply..."></textarea>
                        </div>
                        <button type="submit" name="action" value="send" class="btn btn-primary btn-sm">
                            <i class="bi bi-reply me-1"></i>Send Reply
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
