<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db = getDB();
$page = (int) get('page', 1);
$perPage = 20;
$userId = $_SESSION['user_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$userId]);
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read_id'])) {
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$_POST['mark_read_id'], $userId]);
    header('Location: index.php?page=' . $page);
    exit;
}

$result = paginate($db, "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC", [$userId], $page);
$notifications = $result['data'] ?? [];
$pagination = $result['pagination'] ?? [];

$grouped = [];
foreach ($notifications as $n) {
    $dateKey = date('Y-m-d', strtotime($n['created_at']));
    $grouped[$dateKey][] = $n;
}

$pageTitle = 'Notifications';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-bell me-2"></i>Notification Center</h1>
        <form method="post" class="d-inline">
            <?= csrfField() ?>
            <button type="submit" name="mark_all_read" value="1" class="btn btn-outline-primary">
                <i class="bi bi-check2-all me-1"></i>Mark All Read
            </button>
        </form>
    </div>

    <?php if (empty($grouped)): ?>
        <div class="text-center py-5">
            <i class="bi bi-bell-slash display-1 text-muted"></i>
            <p class="text-muted mt-3">No notifications yet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($grouped as $date => $items): ?>
            <h6 class="text-muted mb-3 mt-4">
                <i class="bi bi-calendar3 me-1"></i>
                <?= date('F j, Y', strtotime($date)) ?>
            </h6>
            <div class="list-group mb-3">
                <?php foreach ($items as $notification): ?>
                    <?php
                    $type = $notification['type'] ?? 'info';
                    $icons = [
                        'order'    => 'bi-box-seam text-primary',
                        'payment'  => 'bi-credit-card text-success',
                        'message'  => 'bi-envelope text-info',
                        'shipment' => 'bi-truck text-warning',
                        'system'   => 'bi-gear text-secondary',
                        'info'     => 'bi-info-circle text-primary',
                    ];
                    $icon = $icons[$type] ?? $icons['info'];
                    $isUnread = empty($notification['is_read']);
                    ?>
                    <div class="list-group-item <?= $isUnread ? 'bg-light-subtle' : '' ?>">
                        <div class="d-flex align-items-start">
                            <div class="me-3 mt-1">
                                <i class="bi <?= $icon ?> fs-4"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">
                                            <?= e($notification['title'] ?? 'Notification') ?>
                                            <?php if ($isUnread): ?>
                                                <span class="badge bg-primary ms-1">New</span>
                                            <?php endif; ?>
                                        </h6>
                                        <p class="mb-1 text-muted"><?= e($notification['message'] ?? '') ?></p>
                                        <small class="text-muted">
                                            <i class="bi bi-clock me-1"></i>
                                            <?= formatDateTime($notification['created_at'] ?? '') ?>
                                        </small>
                                    </div>
                                    <?php if ($isUnread): ?>
                                        <form method="post" class="ms-2">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="mark_read_id" value="<?= (int) $notification['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Mark Read">
                                                <i class="bi bi-check2"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
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
                        <a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $pagination['last_page']; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $pagination['last_page'] ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
