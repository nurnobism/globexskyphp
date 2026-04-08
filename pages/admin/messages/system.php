<?php
/**
 * pages/admin/messages/system.php — Admin System Messages Manager (PR #23)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/notification_preferences.php';
requireAdmin();

$db = getDB();

$success = '';
$error   = '';
$editing = null;

// Handle create / update / delete / toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $title     = trim($_POST['msg_title']    ?? '');
        $body      = trim($_POST['msg_body']     ?? '');
        $type      = $_POST['msg_type']          ?? 'feature_update';
        $priority  = $_POST['msg_priority']      ?? 'info';
        $roles     = array_filter((array) ($_POST['msg_roles'] ?? []));
        $isActive  = isset($_POST['msg_active']) ? 1 : 0;
        $startsAt  = !empty($_POST['msg_starts'])  ? $_POST['msg_starts']  : null;
        $expiresAt = !empty($_POST['msg_expires']) ? $_POST['msg_expires'] : null;

        if ($title === '' || $body === '') {
            $error = 'Title and body are required.';
        } elseif ($action === 'update') {
            $msgId = (int) ($_POST['msg_id'] ?? 0);
            $ok = updateSystemMessage($db, $msgId, [
                'title'             => $title,
                'body'              => $body,
                'type'              => $type,
                'priority'          => $priority,
                'target_roles_json' => json_encode(array_values($roles)),
                'starts_at'         => $startsAt ?? date('Y-m-d H:i:s'),
                'expires_at'        => $expiresAt,
                'is_active'         => $isActive,
            ]);
            $success = $ok ? 'Message updated.' : 'Update failed.';
        } else {
            $adminId = (int) ($_SESSION['user_id'] ?? 0);
            $id = sendSystemMessage($db, $title, $body, $type, $priority, $roles, $adminId, $startsAt, $expiresAt);
            $success = $id > 0 ? 'System message #' . $id . ' created.' : 'Failed to create message.';
            if (!$id) $error = 'Failed to create message.';
        }
    }

    if ($action === 'delete') {
        $msgId = (int) ($_POST['msg_id'] ?? 0);
        deleteSystemMessage($db, $msgId);
        $success = 'Message deleted.';
    }

    if ($action === 'toggle') {
        $msgId    = (int) ($_POST['msg_id']    ?? 0);
        $newState = (int) ($_POST['new_state'] ?? 0);
        updateSystemMessage($db, $msgId, ['is_active' => $newState]);
        $success = $newState ? 'Message activated.' : 'Message deactivated.';
    }
}

// Edit mode
$editId = (int) ($_GET['edit'] ?? 0);
if ($editId > 0) {
    try {
        $stmt = $db->prepare('SELECT * FROM system_messages WHERE id = ?');
        $stmt->execute([$editId]);
        $editing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        $editing = null;
    }
}

$page       = max(1, (int) ($_GET['page'] ?? 1));
$perPage    = 15;
$allMsgs    = getAllSystemMessages($db, $page, $perPage);
$messages   = $allMsgs['data'];
$totalMsgs  = $allMsgs['total'];
$totalPages = (int) ceil($totalMsgs / max(1, $perPage));

$priorityBadge = ['critical' => 'danger', 'warning' => 'warning', 'info' => 'info'];

$pageTitle = 'System Messages Manager';
include __DIR__ . '/../../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold"><i class="bi bi-megaphone-fill text-primary me-2"></i>System Messages</h3>
            <p class="text-muted mb-0">Create and manage platform-wide announcements.</p>
        </div>
        <a href="/pages/admin/settings/notifications.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-1"></i> <?= e($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-circle me-1"></i> <?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Form -->
        <div class="col-xl-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0 fw-semibold">
                        <?php if ($editing): ?>
                            <i class="bi bi-pencil me-2 text-warning"></i>Edit #<?= (int) $editing['id'] ?>
                        <?php else: ?>
                            <i class="bi bi-plus-circle me-2 text-primary"></i>New Message
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
                        <?php if ($editing): ?>
                            <input type="hidden" name="msg_id" value="<?= (int) $editing['id'] ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                            <input type="text" name="msg_title" class="form-control" required maxlength="255"
                                   value="<?= e($editing['title'] ?? '') ?>" placeholder="Announcement title">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Body <span class="text-danger">*</span></label>
                            <textarea name="msg_body" class="form-control" rows="4" required><?= e($editing['body'] ?? '') ?></textarea>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold">Type</label>
                                <select name="msg_type" class="form-select form-select-sm">
                                    <?php foreach ([
                                        'feature_update' => 'Feature Update',
                                        'maintenance'    => 'Maintenance',
                                        'policy_change'  => 'Policy Change',
                                        'promotion'      => 'Promotion',
                                        'security_alert' => 'Security Alert',
                                    ] as $val => $lbl): ?>
                                        <option value="<?= $val ?>" <?= ($editing['type'] ?? '') === $val ? 'selected' : '' ?>>
                                            <?= $lbl ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">Priority</label>
                                <select name="msg_priority" class="form-select form-select-sm">
                                    <?php foreach (['info' => 'Info', 'warning' => 'Warning', 'critical' => 'Critical'] as $val => $lbl): ?>
                                        <option value="<?= $val ?>" <?= ($editing['priority'] ?? 'info') === $val ? 'selected' : '' ?>>
                                            <?= $lbl ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Target Roles</label>
                            <?php
                            $editRoles = json_decode($editing['target_roles_json'] ?? '[]', true) ?: [];
                            foreach (['buyer' => 'Buyers', 'supplier' => 'Suppliers', 'carrier' => 'Carriers', 'admin' => 'Admins'] as $val => $lbl): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="msg_roles[]"
                                           value="<?= $val ?>" id="er_<?= $val ?>"
                                           <?= in_array($val, $editRoles, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="er_<?= $val ?>"><?= $lbl ?></label>
                                </div>
                            <?php endforeach; ?>
                            <small class="d-block text-muted mt-1">Unchecked = all users.</small>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold">Starts At</label>
                                <input type="datetime-local" name="msg_starts" class="form-control form-control-sm"
                                       value="<?= $editing ? e(str_replace(' ', 'T', substr($editing['starts_at'], 0, 16))) : '' ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">Expires At</label>
                                <input type="datetime-local" name="msg_expires" class="form-control form-control-sm"
                                       value="<?= ($editing && $editing['expires_at']) ? e(str_replace(' ', 'T', substr($editing['expires_at'], 0, 16))) : '' ?>">
                            </div>
                        </div>
                        <?php if ($editing): ?>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="msg_active" id="msg_active"
                                       <?= $editing['is_active'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="msg_active">Active (visible to users)</label>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-<?= $editing ? 'save' : 'send' ?> me-1"></i>
                                <?= $editing ? 'Update' : 'Create' ?>
                            </button>
                            <?php if ($editing): ?>
                                <a href="system.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Messages List -->
        <div class="col-xl-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-semibold"><i class="bi bi-list-ul me-2 text-primary"></i>All Messages</h5>
                    <span class="badge bg-secondary"><?= $totalMsgs ?> total</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($messages)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-inbox display-6"></i>
                            <p class="mt-2">No system messages yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                            <div class="border-bottom p-3 <?= !$msg['is_active'] ? 'bg-light opacity-75' : '' ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1 me-2">
                                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                            <span class="fw-semibold"><?= e($msg['title']) ?></span>
                                            <span class="badge bg-<?= $priorityBadge[$msg['priority']] ?? 'secondary' ?>">
                                                <?= e($msg['priority']) ?>
                                            </span>
                                            <span class="badge bg-light text-dark border"><?= e($msg['type']) ?></span>
                                            <?php if (!$msg['is_active']): ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-muted small mb-1"><?= e(mb_strimwidth($msg['body'], 0, 150, '…')) ?></p>
                                        <div class="d-flex gap-3 small text-muted flex-wrap">
                                            <?php $roles = json_decode($msg['target_roles_json'] ?? '[]', true); ?>
                                            <span><i class="bi bi-people me-1"></i><?= empty($roles) ? 'All users' : e(implode(', ', $roles)) ?></span>
                                            <span><i class="bi bi-eye-slash me-1"></i><?= (int) $msg['dismiss_count'] ?> dismissed</span>
                                            <span><i class="bi bi-calendar3 me-1"></i><?= e(substr($msg['created_at'], 0, 10)) ?></span>
                                            <?php if ($msg['expires_at']): ?>
                                                <span><i class="bi bi-hourglass-split me-1"></i>Expires <?= e(substr($msg['expires_at'], 0, 10)) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-1 flex-shrink-0">
                                        <a href="?edit=<?= (int) $msg['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="msg_id" value="<?= (int) $msg['id'] ?>">
                                            <input type="hidden" name="new_state" value="<?= $msg['is_active'] ? '0' : '1' ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-<?= $msg['is_active'] ? 'warning' : 'success' ?>"
                                                    title="<?= $msg['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                                <i class="bi bi-<?= $msg['is_active'] ? 'pause' : 'play' ?>"></i>
                                            </button>
                                        </form>
                                        <form method="POST" class="d-inline"
                                              onsubmit="return confirm('Delete this system message permanently?')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="msg_id" value="<?= (int) $msg['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($totalPages > 1): ?>
                            <nav class="p-3">
                                <ul class="pagination pagination-sm justify-content-center mb-0">
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
