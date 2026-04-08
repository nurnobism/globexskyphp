<?php
require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/notifications.php';
requireAuth();

$db     = getDB();
$page   = max(1, (int) get('page', 1));
$userId = (int) ($_SESSION['user_id'] ?? 0);
$tab    = in_array($_GET['tab'] ?? 'all', ['all','unread','orders','financial','messages','system'], true)
          ? ($_GET['tab'] ?? 'all') : 'all';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    if (isset($_POST['mark_all_read'])) {
        markAllAsRead($db, $userId);
        header('Location: index.php?tab=' . urlencode($tab));
        exit;
    }
    if (!empty($_POST['mark_read_id'])) {
        markAsRead($db, (int) $_POST['mark_read_id'], $userId);
        header('Location: index.php?tab=' . urlencode($tab) . '&page=' . $page);
        exit;
    }
    if (!empty($_POST['delete_id'])) {
        deleteNotification($db, (int) $_POST['delete_id'], $userId);
        header('Location: index.php?tab=' . urlencode($tab) . '&page=' . $page);
        exit;
    }
    if (isset($_POST['clear_all'])) {
        clearAll($db, $userId);
        header('Location: index.php?tab=' . urlencode($tab));
        exit;
    }
}

$result        = getNotifications($db, $userId, $page, 20, $tab);
$notifications = $result['data'] ?? [];
$pagination    = $result;
$unreadCount   = getUnreadCount($db, $userId);

$grouped = [];
foreach ($notifications as $n) {
    $dateKey = date('Y-m-d', strtotime($n['created_at']));
    $grouped[$dateKey][] = $n;
}

$tabs = [
    'all'       => ['label' => 'All',       'icon' => 'bi-bell'],
    'unread'    => ['label' => 'Unread',    'icon' => 'bi-bell-fill'],
    'orders'    => ['label' => 'Orders',    'icon' => 'bi-bag'],
    'financial' => ['label' => 'Financial', 'icon' => 'bi-cash-stack'],
    'messages'  => ['label' => 'Messages',  'icon' => 'bi-chat-dots'],
    'system'    => ['label' => 'System',    'icon' => 'bi-gear'],
];

$pageTitle = 'Notifications';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">
            <i class="bi bi-bell me-2"></i>Notification Center
            <?php if ($unreadCount > 0): ?>
                <span class="badge bg-danger ms-1"><?= $unreadCount ?></span>
            <?php endif; ?>
        </h1>
        <div class="d-flex gap-2">
            <?php if ($unreadCount > 0): ?>
                <form method="post" class="d-inline">
                    <?= csrfField() ?>
                    <button type="submit" name="mark_all_read" value="1" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-check2-all me-1"></i>Mark All Read
                    </button>
                </form>
            <?php endif; ?>
            <form method="post" class="d-inline" onsubmit="return confirm('Clear all notifications?')">
                <?= csrfField() ?>
                <button type="submit" name="clear_all" value="1" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-trash me-1"></i>Clear All
                </button>
            </form>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4">
        <?php foreach ($tabs as $key => $t): ?>
            <li class="nav-item">
                <a class="nav-link <?= $tab === $key ? 'active' : '' ?>"
                   href="?tab=<?= urlencode($key) ?>">
                    <i class="bi <?= $t['icon'] ?> me-1"></i>
                    <?= $t['label'] ?>
                    <?php if ($key === 'unread' && $unreadCount > 0): ?>
                        <span class="badge bg-danger ms-1"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if (empty($grouped)): ?>
        <div class="text-center py-5">
            <i class="bi bi-bell-slash display-1 text-muted"></i>
            <p class="text-muted mt-3">No notifications here yet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($grouped as $date => $items): ?>
            <h6 class="text-muted mb-3 mt-4">
                <i class="bi bi-calendar3 me-1"></i>
                <?= date('F j, Y', strtotime($date)) ?>
            </h6>
            <div class="list-group mb-3">
                <?php foreach ($items as $notification):
                    $type    = $notification['type'] ?? 'system';
                    $icon    = $notification['icon'] ?? getNotificationIcon($type);
                    $isUnread = empty($notification['is_read']);
                    $actionUrl = $notification['action_url'] ?? '';
                    ?>
                    <div class="list-group-item <?= $isUnread ? 'bg-light-subtle border-start border-primary border-3' : '' ?>">
                        <div class="d-flex align-items-start">
                            <div class="me-3 mt-1 fs-4 text-primary">
                                <i class="bi <?= e($icon) ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">
                                            <?php if ($actionUrl): ?>
                                                <a href="<?= e($actionUrl) ?>" class="text-decoration-none text-dark">
                                                    <?= e($notification['title'] ?? 'Notification') ?>
                                                </a>
                                            <?php else: ?>
                                                <?= e($notification['title'] ?? 'Notification') ?>
                                            <?php endif; ?>
                                            <?php if ($isUnread): ?>
                                                <span class="badge bg-primary ms-1 rounded-pill">New</span>
                                            <?php endif; ?>
                                        </h6>
                                        <p class="mb-1 text-muted small"><?= e($notification['message'] ?? '') ?></p>
                                        <small class="text-muted">
                                            <i class="bi bi-clock me-1"></i>
                                            <?= e($notification['time_ago'] ?? formatTimeAgo($notification['created_at'] ?? '')) ?>
                                        </small>
                                    </div>
                                    <div class="d-flex gap-1 ms-2">
                                        <?php if ($isUnread): ?>
                                            <form method="post">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="mark_read_id" value="<?= (int) $notification['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Mark Read">
                                                    <i class="bi bi-check2"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" onsubmit="return confirm('Delete this notification?')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="delete_id" value="<?= (int) $notification['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <?php if (!empty($pagination['last_page']) && $pagination['last_page'] > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?tab=<?= urlencode($tab) ?>&page=<?= $page - 1 ?>">Previous</a>
                    </li>
                    <?php
                    $start = max(1, $page - 4);
                    $end   = min($pagination['last_page'], $start + 9);
                    $start = max(1, $end - 9);
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?tab=<?= urlencode($tab) ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $pagination['last_page'] ? 'disabled' : '' ?>">
                        <a class="page-link" href="?tab=<?= urlencode($tab) ?>&page=<?= $page + 1 ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

