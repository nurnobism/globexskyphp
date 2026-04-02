<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$db   = getDB();
$uid  = (int)$_SESSION['user_id'];
$page = max(1, (int)get('page', 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$total = (int)$db->prepare('SELECT COUNT(*) FROM messages WHERE recipient_id=?')
              ->execute([$uid]) ? $db->query("SELECT COUNT(*) FROM messages WHERE recipient_id=$uid")->fetchColumn() : 0;
$stmt = $db->prepare(
    'SELECT m.*, u.first_name, u.last_name
     FROM messages m JOIN users u ON u.id=m.sender_id
     WHERE m.recipient_id=? ORDER BY m.created_at DESC LIMIT ? OFFSET ?'
);
$stmt->execute([$uid, $perPage, $offset]);
$messages = $stmt->fetchAll();
$totalPages = max(1, (int)ceil($total / $perPage));

$pageTitle = 'Inbox';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-inbox me-2 text-primary"></i>Inbox</h2>
    <a href="chat.php?compose=1" class="btn btn-primary"><i class="bi bi-pencil-square me-1"></i>Compose</a>
  </div>

  <?php if (empty($messages)): ?>
    <div class="text-center py-5">
      <i class="bi bi-inbox display-1 text-muted"></i>
      <h5 class="mt-3 text-muted">Your inbox is empty</h5>
    </div>
  <?php else: ?>
  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th>From</th>
            <th>Subject</th>
            <th>Date</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($messages as $m):
            $isUnread = empty($m['read_at']);
            $sender   = e(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')));
          ?>
          <tr class="<?= $isUnread ? 'fw-bold' : '' ?>">
            <td>
              <i class="bi bi-person-circle me-1 text-secondary"></i><?= $sender ?>
            </td>
            <td>
              <a href="chat.php?thread=<?= (int)$m['id'] ?>" class="text-decoration-none text-dark">
                <?= e($m['subject'] ?? '(No subject)') ?>
              </a>
              <p class="mb-0 fw-normal text-muted small text-truncate" style="max-width:300px">
                <?= e(mb_substr(strip_tags($m['body'] ?? ''), 0, 80)) ?>
              </p>
            </td>
            <td class="text-nowrap text-muted small">
              <?= !empty($m['created_at']) ? date('M j, Y g:i A', strtotime($m['created_at'])) : '—' ?>
            </td>
            <td>
              <?php if ($isUnread): ?>
                <span class="badge bg-primary">Unread</span>
              <?php else: ?>
                <span class="badge bg-secondary">Read</span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <?php if ($isUnread): ?>
                <a href="/api/messages.php?action=mark_read&id=<?= (int)$m['id'] ?>&_csrf=<?= urlencode(csrfToken()) ?>"
                   class="btn btn-outline-secondary btn-sm">Mark Read</a>
              <?php endif; ?>
              <a href="chat.php?thread=<?= (int)$m['id'] ?>" class="btn btn-outline-primary btn-sm ms-1">
                <i class="bi bi-reply"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($totalPages > 1): ?>
  <nav class="mt-4">
    <ul class="pagination justify-content-center">
      <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
        <a class="page-link" href="?page=<?= $page - 1 ?>"><i class="bi bi-chevron-left"></i></a>
      </li>
      <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
          <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
      <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
        <a class="page-link" href="?page=<?= $page + 1 ?>"><i class="bi bi-chevron-right"></i></a>
      </li>
    </ul>
  </nav>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
