<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$db         = getDB();
$uid        = (int)$_SESSION['user_id'];
$threadId   = (int)get('thread', 0);

// Conversation list (distinct threads)
$convStmt = $db->prepare(
    'SELECT m.id, m.subject, m.body, m.created_at, m.read_at,
            u.first_name, u.last_name, u.id AS other_user_id
     FROM messages m
     JOIN users u ON u.id = CASE WHEN m.sender_id = ? THEN m.recipient_id ELSE m.sender_id END
     WHERE m.sender_id = ? OR m.recipient_id = ?
     ORDER BY m.created_at DESC'
);
$convStmt->execute([$uid, $uid, $uid]);
$conversations = $convStmt->fetchAll();

// Load thread messages if thread selected
$thread = [];
$threadSubject = '';
if ($threadId) {
    $tStmt = $db->prepare(
        'SELECT m.*, u.first_name, u.last_name
         FROM messages m
         JOIN users u ON u.id = m.sender_id
         WHERE m.id = ? OR (m.subject = (SELECT subject FROM messages WHERE id = ?)
               AND (m.sender_id = ? OR m.recipient_id = ?))
         ORDER BY m.created_at ASC'
    );
    $tStmt->execute([$threadId, $threadId, $uid, $uid]);
    $thread = $tStmt->fetchAll();
    $threadSubject = !empty($thread) ? ($thread[0]['subject'] ?? '') : '';
}

$pageTitle = 'Chat';
include __DIR__ . '/../../includes/header.php';
?>

<style>
.chat-panel   { height: calc(100vh - 220px); min-height: 500px; }
.conv-list    { overflow-y: auto; height: 100%; }
.message-area { overflow-y: auto; flex: 1 1 auto; min-height: 0; }
.conv-item.active { background-color: #e9f0ff; border-left: 3px solid #0d6efd; }
.bubble-sent  { max-width: 72%; margin-left: auto; }
.bubble-recv  { max-width: 72%; margin-right: auto; }
</style>

<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-center mb-3 px-2">
    <h4 class="mb-0"><i class="bi bi-chat-dots me-2 text-primary"></i>Chat</h4>
    <a href="chat.php?compose=1" class="btn btn-primary btn-sm">
      <i class="bi bi-pencil-square me-1"></i>Compose
    </a>
  </div>

  <div class="row g-0 border rounded shadow-sm chat-panel">

    <!-- Left: Conversation List -->
    <div class="col-md-4 border-end d-flex flex-column">
      <div class="p-2 border-bottom bg-light">
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-white border-end-0">
            <i class="bi bi-search text-muted"></i>
          </span>
          <input type="text" id="convSearch" class="form-control border-start-0"
                 placeholder="Search conversations…">
        </div>
      </div>

      <div class="conv-list" id="convList">
        <?php if (empty($conversations)): ?>
          <div class="text-center text-muted py-5 small">
            <i class="bi bi-chat-left display-6"></i><br>No conversations yet
          </div>
        <?php else: ?>
          <?php foreach ($conversations as $c):
            $name    = e(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')));
            $preview = e(mb_substr(strip_tags($c['body'] ?? ''), 0, 55));
            $active  = ($c['id'] == $threadId) ? 'active' : '';
            $unread  = empty($c['read_at']) && $c['other_user_id'] != $uid ? 'fw-bold' : '';
          ?>
            <a href="chat.php?thread=<?= (int)$c['id'] ?>"
               class="d-flex gap-2 p-3 text-decoration-none text-dark border-bottom conv-item <?= $active ?>">
              <div class="flex-shrink-0 pt-1">
                <i class="bi bi-person-circle fs-4 text-secondary"></i>
              </div>
              <div class="flex-grow-1 overflow-hidden">
                <div class="d-flex justify-content-between">
                  <span class="<?= $unread ?> small"><?= $name ?></span>
                  <small class="text-muted text-nowrap" style="font-size:.7rem">
                    <?= !empty($c['created_at']) ? date('M j', strtotime($c['created_at'])) : '' ?>
                  </small>
                </div>
                <p class="mb-0 text-truncate text-muted" style="font-size:.78rem">
                  <?= $preview ?>
                </p>
              </div>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Right: Thread Area -->
    <div class="col-md-8 d-flex flex-column">
      <?php if (!$threadId && !get('compose')): ?>
        <!-- No thread selected -->
        <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted">
          <i class="bi bi-chat-square-text display-2 mb-3"></i>
          <h5>Select a conversation</h5>
          <p class="small">Choose from the list or compose a new message.</p>
          <a href="chat.php?compose=1" class="btn btn-outline-primary btn-sm mt-1">
            <i class="bi bi-pencil-square me-1"></i>Compose New Message
          </a>
        </div>

      <?php elseif (get('compose')): ?>
        <!-- Compose form -->
        <div class="p-3 border-bottom bg-light">
          <h6 class="mb-0"><i class="bi bi-pencil-square me-1"></i>New Message</h6>
        </div>
        <div class="p-4 flex-grow-1 overflow-auto">
          <form method="post" action="/api/messages.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="send">
            <div class="mb-3">
              <label class="form-label fw-semibold">To (User ID or Email)</label>
              <input type="text" name="recipient" class="form-control" required
                     placeholder="Recipient email or ID">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Subject</label>
              <input type="text" name="subject" class="form-control" required
                     placeholder="Message subject">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Message</label>
              <textarea name="body" class="form-control" rows="8" required
                        placeholder="Write your message…"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-send me-1"></i>Send Message
            </button>
            <a href="chat.php" class="btn btn-outline-secondary ms-2">Cancel</a>
          </form>
        </div>

      <?php else: ?>
        <!-- Thread header -->
        <div class="p-3 border-bottom bg-light d-flex align-items-center gap-2">
          <i class="bi bi-person-circle fs-4 text-secondary"></i>
          <div>
            <div class="fw-semibold small">
              <?php if (!empty($thread)):
                $first = $thread[0];
                echo e(trim(($first['first_name'] ?? '') . ' ' . ($first['last_name'] ?? '')));
              endif; ?>
            </div>
            <div class="text-muted" style="font-size:.78rem"><?= e($threadSubject) ?></div>
          </div>
        </div>

        <!-- Messages -->
        <div class="message-area p-3" id="messageArea">
          <?php if (empty($thread)): ?>
            <p class="text-muted small text-center mt-4">No messages in this thread.</p>
          <?php else: ?>
            <?php foreach ($thread as $msg):
              $isSent  = ((int)$msg['sender_id'] === $uid);
              $msgName = e(trim(($msg['first_name'] ?? '') . ' ' . ($msg['last_name'] ?? '')));
              $msgTime = !empty($msg['created_at'])
                ? date('M j, g:i A', strtotime($msg['created_at']))
                : '';
            ?>
              <div class="mb-3 d-flex flex-column <?= $isSent ? 'align-items-end' : 'align-items-start' ?>">
                <div class="<?= $isSent ? 'bubble-sent' : 'bubble-recv' ?>">
                  <div class="rounded-3 px-3 py-2 <?= $isSent ? 'bg-primary text-white' : 'bg-light text-dark border' ?>">
                    <?= nl2br(e($msg['body'] ?? '')) ?>
                  </div>
                  <small class="text-muted d-block mt-1 <?= $isSent ? 'text-end' : '' ?>" style="font-size:.72rem">
                    <?= $isSent ? 'You' : $msgName ?> &middot; <?= e($msgTime) ?>
                  </small>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Reply form -->
        <div class="p-3 border-top bg-white">
          <form method="post" action="/api/messages.php" class="d-flex gap-2 align-items-end">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="reply">
            <input type="hidden" name="thread_id" value="<?= $threadId ?>">
            <textarea name="body" class="form-control" rows="2"
                      placeholder="Type a message…" required
                      style="resize:none"></textarea>
            <button type="submit" class="btn btn-primary px-3">
              <i class="bi bi-send-fill"></i>
            </button>
          </form>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
// Conversation search filter
document.getElementById('convSearch')?.addEventListener('input', function () {
  const q = this.value.toLowerCase();
  document.querySelectorAll('#convList .conv-item').forEach(item => {
    item.style.display = item.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});

// Scroll message area to bottom on load
(function () {
  const area = document.getElementById('messageArea');
  if (area) area.scrollTop = area.scrollHeight;
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
