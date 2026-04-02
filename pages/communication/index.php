<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$db   = getDB();
$uid  = (int)$_SESSION['user_id'];

$stmt = $db->prepare(
    'SELECT m.*, u.first_name, u.last_name, u.email sender_email
     FROM messages m
     JOIN users u ON u.id = m.sender_id
     WHERE m.recipient_id = ?
     ORDER BY m.created_at DESC
     LIMIT 10'
);
$stmt->execute([$uid]);
$messages = $stmt->fetchAll();

$pageTitle = 'Messages';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">

  <!-- Page Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0"><i class="bi bi-chat-dots me-2 text-primary"></i>Messages</h2>
      <p class="text-muted small mb-0">Your recent conversations</p>
    </div>
    <a href="chat.php" class="btn btn-primary">
      <i class="bi bi-pencil-square me-1"></i>Compose Message
    </a>
  </div>

  <?php if (empty($messages)): ?>
    <div class="text-center py-5">
      <i class="bi bi-chat-text display-1 text-muted"></i>
      <h5 class="mt-3 text-muted">No messages yet</h5>
      <p class="text-muted">Start a conversation with suppliers or buyers.</p>
      <a href="chat.php" class="btn btn-primary mt-2">
        <i class="bi bi-pencil-square me-1"></i>Compose Message
      </a>
    </div>
  <?php else: ?>
    <div class="list-group shadow-sm">
      <?php foreach ($messages as $msg):
        $senderName = e(trim(($msg['first_name'] ?? '') . ' ' . ($msg['last_name'] ?? '')));
        $isUnread   = empty($msg['read_at']);
        $timeAgo    = !empty($msg['created_at'])
          ? date('M j, g:i A', strtotime($msg['created_at']))
          : '';
      ?>
        <a href="chat.php?thread=<?= (int)$msg['id'] ?>"
           class="list-group-item list-group-item-action py-3 <?= $isUnread ? 'bg-light' : '' ?>">
          <div class="d-flex gap-3 align-items-center">

            <!-- Avatar -->
            <div class="flex-shrink-0">
              <i class="bi bi-person-circle fs-2 text-secondary"></i>
            </div>

            <!-- Message info -->
            <div class="flex-grow-1 overflow-hidden">
              <div class="d-flex justify-content-between align-items-center">
                <span class="<?= $isUnread ? 'fw-bold' : 'fw-semibold' ?> text-dark">
                  <?= $senderName ?>
                </span>
                <small class="text-muted ms-2 text-nowrap"><?= e($timeAgo) ?></small>
              </div>
              <div class="d-flex justify-content-between align-items-center mt-1">
                <span class="text-truncate <?= $isUnread ? 'fw-semibold' : '' ?> text-secondary small">
                  <?= e($msg['subject'] ?? '(No subject)') ?>
                </span>
                <?php if ($isUnread): ?>
                  <span class="badge bg-primary ms-2 flex-shrink-0">New</span>
                <?php endif; ?>
              </div>
              <p class="mb-0 text-truncate text-muted small mt-1">
                <?= e(mb_substr(strip_tags($msg['body'] ?? ''), 0, 100)) ?>
              </p>
            </div>

          </div>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-3">
      <a href="inbox.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-inbox me-1"></i>View Full Inbox
      </a>
      <small class="text-muted">Showing <?= count($messages) ?> most recent</small>
    </div>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
