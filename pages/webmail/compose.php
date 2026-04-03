<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$errors = [];
$success = '';

// Pre-fill for reply/forward
$replyTo   = (int)get('reply_to', 0);
$forwardId = (int)get('forward', 0);
$draftId   = (int)get('draft', 0);

$prefillTo      = '';
$prefillSubject = '';
$prefillBody    = '';
$prefillPriority = 'normal';

if ($replyTo > 0) {
    $stmt = $db->prepare('SELECT wm.*, u.first_name, u.last_name, u.email FROM webmail_messages wm JOIN users u ON u.id=wm.sender_id WHERE wm.id=?');
    $stmt->execute([$replyTo]);
    $orig = $stmt->fetch();
    if ($orig) {
        $prefillTo      = $orig['email'];
        $prefillSubject = 'Re: ' . $orig['subject'];
        $prefillBody    = "\n\n--- Original Message ---\nFrom: " . $orig['first_name'] . ' ' . $orig['last_name'] . "\nDate: " . $orig['created_at'] . "\n\n" . strip_tags($orig['body']);
    }
}
if ($forwardId > 0) {
    $stmt = $db->prepare('SELECT wm.*, u.first_name, u.last_name FROM webmail_messages wm JOIN users u ON u.id=wm.sender_id WHERE wm.id=?');
    $stmt->execute([$forwardId]);
    $orig = $stmt->fetch();
    if ($orig) {
        $prefillSubject = 'Fwd: ' . $orig['subject'];
        $prefillBody    = "\n\n--- Forwarded Message ---\nFrom: " . $orig['first_name'] . ' ' . $orig['last_name'] . "\nDate: " . $orig['created_at'] . "\nSubject: " . $orig['subject'] . "\n\n" . strip_tags($orig['body']);
    }
}
if ($draftId > 0) {
    $stmt = $db->prepare('SELECT * FROM webmail_messages WHERE id=? AND sender_id=? AND is_draft=1');
    $stmt->execute([$draftId, $userId]);
    $draft = $stmt->fetch();
    if ($draft) {
        $prefillSubject  = $draft['subject'];
        $prefillBody     = strip_tags($draft['body']);
        $prefillPriority = $draft['priority'];
        // Get recipients
        $rStmt = $db->prepare('SELECT u.email FROM webmail_recipients wr JOIN users u ON u.id=wr.user_id WHERE wr.message_id=? AND wr.type="to"');
        $rStmt->execute([$draftId]);
        $prefillTo = implode(', ', array_column($rStmt->fetchAll(), 'email'));
    }
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action   = $_POST['action'] ?? 'send';
    $to       = trim($_POST['to'] ?? '');
    $cc       = trim($_POST['cc'] ?? '');
    $subject  = trim($_POST['subject'] ?? '');
    $body     = trim($_POST['body'] ?? '');
    $priority = in_array($_POST['priority'] ?? '', ['normal','high','urgent']) ? $_POST['priority'] : 'normal';
    $isDraft  = ($action === 'draft') ? 1 : 0;

    if (empty($to) && !$isDraft) $errors[] = 'Recipient is required.';
    if (empty($subject)) $errors[] = 'Subject is required.';

    // Resolve recipient emails to user IDs
    $recipientIds = [];
    $ccIds        = [];
    if (!empty($to)) {
        foreach (array_map('trim', explode(',', $to)) as $email) {
            $stmt = $db->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
            $stmt->execute([$email]);
            $uid = $stmt->fetchColumn();
            if ($uid) {
                $recipientIds[] = (int)$uid;
            } elseif (!$isDraft) {
                $errors[] = "User not found: $email";
            }
        }
    }
    if (!empty($cc)) {
        foreach (array_map('trim', explode(',', $cc)) as $email) {
            $stmt = $db->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
            $stmt->execute([$email]);
            $uid = $stmt->fetchColumn();
            if ($uid) $ccIds[] = (int)$uid;
        }
    }

    if (empty($errors)) {
        $bodyHtml = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));

        // Update or insert
        if ($draftId > 0) {
            $db->prepare('UPDATE webmail_messages SET subject=?, body=?, body_html=?, priority=?, is_draft=?, updated_at=NOW() WHERE id=? AND sender_id=?')
               ->execute([$subject, $body, $bodyHtml, $priority, $isDraft, $draftId, $userId]);
            $messageId = $draftId;
            $db->prepare('DELETE FROM webmail_recipients WHERE message_id=?')->execute([$messageId]);
        } else {
            $stmt = $db->prepare('INSERT INTO webmail_messages (sender_id, subject, body, body_html, priority, is_draft, thread_id) VALUES (?,?,?,?,?,?,NULL)');
            $stmt->execute([$userId, $subject, $body, $bodyHtml, $priority, $isDraft]);
            $messageId = (int)$db->lastInsertId();
            $db->prepare('UPDATE webmail_messages SET thread_id=? WHERE id=?')->execute([$messageId, $messageId]);
        }

        // Add recipients
        foreach ($recipientIds as $rid) {
            $db->prepare('INSERT INTO webmail_recipients (message_id, user_id, type) VALUES (?,?,?)')->execute([$messageId, $rid, 'to']);
        }
        foreach ($ccIds as $rid) {
            $db->prepare('INSERT INTO webmail_recipients (message_id, user_id, type) VALUES (?,?,?)')->execute([$messageId, $rid, 'cc']);
        }

        // Handle reply threading
        if ($replyTo > 0 && !$isDraft) {
            $origStmt = $db->prepare('SELECT thread_id FROM webmail_messages WHERE id=?');
            $origStmt->execute([$replyTo]);
            $threadId = $origStmt->fetchColumn();
            if ($threadId) {
                $db->prepare('UPDATE webmail_messages SET thread_id=?, parent_message_id=? WHERE id=?')
                   ->execute([$threadId, $replyTo, $messageId]);
            }
        }

        // Handle file attachments
        if (!empty($_FILES['attachments']['name'][0])) {
            $uploadDir = __DIR__ . '/../../assets/uploads/webmail/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $totalSize = 0;
            foreach ($_FILES['attachments']['name'] as $i => $name) {
                if ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $size = $_FILES['attachments']['size'][$i];
                $totalSize += $size;
                if ($size > 10 * 1024 * 1024) continue; // 10MB per file
                if ($totalSize > 25 * 1024 * 1024) break; // 25MB total
                $ext  = pathinfo($name, PATHINFO_EXTENSION);
                $safe = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                if (move_uploaded_file($_FILES['attachments']['tmp_name'][$i], $uploadDir . $safe)) {
                    $mime = mime_content_type($uploadDir . $safe) ?: 'application/octet-stream';
                    $db->prepare('INSERT INTO webmail_attachments (message_id, file_name, file_url, file_size, mime_type) VALUES (?,?,?,?,?)')
                       ->execute([$messageId, $name, '/assets/uploads/webmail/' . $safe, $size, $mime]);
                }
            }
        }

        if ($isDraft) {
            $success = 'Draft saved successfully.';
            $draftId = $messageId;
        } else {
            header('Location: sent.php?msg=sent');
            exit;
        }
    }
}

$pageTitle = 'Compose Mail';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2">
            <div class="list-group list-group-flush mb-3">
                <a href="inbox.php" class="list-group-item list-group-item-action"><i class="bi bi-inbox me-2"></i>Inbox</a>
                <a href="sent.php" class="list-group-item list-group-item-action"><i class="bi bi-send me-2"></i>Sent</a>
                <a href="drafts.php" class="list-group-item list-group-item-action"><i class="bi bi-file-earmark me-2"></i>Drafts</a>
                <a href="trash.php" class="list-group-item list-group-item-action"><i class="bi bi-trash me-2"></i>Trash</a>
            </div>
        </div>

        <!-- Compose -->
        <div class="col-md-9 col-lg-10">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Compose Message</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $err): ?>
                                <div><?= e($err) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= e($success) ?></div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <?= csrfField() ?>
                        <?php if ($draftId): ?>
                            <input type="hidden" name="draft_id" value="<?= $draftId ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">To <span class="text-danger">*</span></label>
                            <input type="text" name="to" class="form-control" placeholder="Enter email addresses (comma separated)" value="<?= e($prefillTo) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">CC</label>
                            <input type="text" name="cc" class="form-control" placeholder="CC (comma separated)">
                        </div>
                        <div class="mb-3 row">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                                <input type="text" name="subject" class="form-control" value="<?= e($prefillSubject) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Priority</label>
                                <select name="priority" class="form-select">
                                    <option value="normal" <?= $prefillPriority === 'normal' ? 'selected' : '' ?>>Normal</option>
                                    <option value="high" <?= $prefillPriority === 'high' ? 'selected' : '' ?>>High</option>
                                    <option value="urgent" <?= $prefillPriority === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Message</label>
                            <textarea name="body" class="form-control" rows="12"><?= e($prefillBody) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Attachments</label>
                            <input type="file" name="attachments[]" class="form-control" multiple>
                            <small class="text-muted">Max 5 files, 10MB each, 25MB total</small>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="action" value="send" class="btn btn-primary">
                                <i class="bi bi-send me-1"></i> Send
                            </button>
                            <button type="submit" name="action" value="draft" class="btn btn-outline-secondary">
                                <i class="bi bi-file-earmark me-1"></i> Save Draft
                            </button>
                            <a href="inbox.php" class="btn btn-outline-danger ms-auto">
                                <i class="bi bi-x-lg me-1"></i> Discard
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
