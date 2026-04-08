<?php
/**
 * pages/admin/settings/notifications.php — Admin Notification Config (PR #23)
 *
 * - Set system defaults for new users
 * - Send system broadcast messages
 * - View and manage active system messages
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/notification_preferences.php';
requireAdmin();

$db = getDB();

$allEvents = getNotificationEventTypes();
$criticals = getCriticalEventTypes();
$defaults  = getDefaultPreferences();

$error   = '';
$success = '';

// Handle send system message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf() && isset($_POST['send_message'])) {
    $title     = trim($_POST['msg_title']    ?? '');
    $body      = trim($_POST['msg_body']     ?? '');
    $type      = $_POST['msg_type']          ?? 'feature_update';
    $priority  = $_POST['msg_priority']      ?? 'info';
    $roles     = $_POST['msg_roles']         ?? [];
    $startsAt  = !empty($_POST['msg_starts'])  ? $_POST['msg_starts']  : null;
    $expiresAt = !empty($_POST['msg_expires']) ? $_POST['msg_expires'] : null;
    $adminId   = (int) ($_SESSION['user_id'] ?? 0);

    if ($title === '' || $body === '') {
        $error = 'Title and body are required.';
    } else {
        $id = sendSystemMessage($db, $title, $body, $type, $priority, $roles, $adminId, $startsAt, $expiresAt);
        if ($id > 0) {
            $success = 'System message #' . $id . ' created and will be shown to targeted users.';
        } else {
            $error = 'Failed to create system message. Please try again.';
        }
    }
}

// Handle deactivate message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf() && isset($_POST['deactivate_msg'])) {
    $msgId = (int) ($_POST['deactivate_msg'] ?? 0);
    updateSystemMessage($db, $msgId, ['is_active' => 0]);
    $success = 'Message deactivated.';
}

// Handle delete message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf() && isset($_POST['delete_msg'])) {
    $msgId = (int) ($_POST['delete_msg'] ?? 0);
    deleteSystemMessage($db, $msgId);
    $success = 'Message deleted.';
}

$messagesResult = getAllSystemMessages($db, 1, 10);
$activeMessages = $messagesResult['data'];

$priorityBadge = [
    'critical' => 'danger',
    'warning'  => 'warning',
    'info'     => 'info',
];

$pageTitle = 'Notification Config';
include __DIR__ . '/../../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold"><i class="bi bi-bell-fill text-primary me-2"></i>Notification Config</h3>
            <p class="text-muted mb-0">Manage system notification defaults and broadcast messages.</p>
        </div>
        <a href="/pages/admin/settings.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Settings
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
        <!-- Send System Message -->
        <div class="col-xl-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0 fw-semibold"><i class="bi bi-megaphone me-2 text-primary"></i>Send System Message</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrfField() ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                            <input type="text" name="msg_title" class="form-control" required maxlength="255"
                                   placeholder="e.g. Scheduled Maintenance on April 15">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Message Body <span class="text-danger">*</span></label>
                            <textarea name="msg_body" class="form-control" rows="4" required
                                      placeholder="Describe the message to be shown to users..."></textarea>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Type</label>
                                <select name="msg_type" class="form-select">
                                    <option value="feature_update">🌟 Feature Update</option>
                                    <option value="maintenance">🔧 Scheduled Maintenance</option>
                                    <option value="policy_change">📄 Policy Change</option>
                                    <option value="promotion">🎁 Promotion</option>
                                    <option value="security_alert">🔐 Security Alert</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Priority</label>
                                <select name="msg_priority" class="form-select">
                                    <option value="info">ℹ️ Info (Blue)</option>
                                    <option value="warning">⚠️ Warning (Yellow)</option>
                                    <option value="critical">🚨 Critical (Red, Sticky)</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Target Audience</label>
                            <div class="d-flex gap-3 flex-wrap">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="msg_roles[]" value="buyer" id="role_buyer">
                                    <label class="form-check-label" for="role_buyer">Buyers</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="msg_roles[]" value="supplier" id="role_supplier">
                                    <label class="form-check-label" for="role_supplier">Suppliers</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="msg_roles[]" value="carrier" id="role_carrier">
                                    <label class="form-check-label" for="role_carrier">Carriers</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="msg_roles[]" value="admin" id="role_admin">
                                    <label class="form-check-label" for="role_admin">Admins</label>
                                </div>
                            </div>
                            <small class="text-muted">Leave all unchecked to target all users.</small>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Starts At</label>
                                <input type="datetime-local" name="msg_starts" class="form-control">
                                <small class="text-muted">Leave blank to publish immediately.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Expires At</label>
                                <input type="datetime-local" name="msg_expires" class="form-control">
                                <small class="text-muted">Leave blank to never expire.</small>
                            </div>
                        </div>
                        <button type="submit" name="send_message" value="1" class="btn btn-primary">
                            <i class="bi bi-send me-1"></i>Send System Message
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Default Notification Settings -->
        <div class="col-xl-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-semibold"><i class="bi bi-sliders me-2 text-primary"></i>System Default Preferences</h5>
                    <span class="badge bg-secondary">New-user defaults</span>
                </div>
                <div class="card-body p-0" style="max-height:520px;overflow-y:auto;">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="min-width:180px;">Event Type</th>
                                <th class="text-center" style="width:70px;">In-App</th>
                                <th class="text-center" style="width:70px;">Email</th>
                                <th class="text-center" style="width:70px;">Push</th>
                                <th class="text-center" style="width:70px;">SMS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allEvents as $type => $meta): ?>
                                <?php $def = $defaults[$type]; $isCritical = in_array($type, $criticals, true); ?>
                                <tr>
                                    <td>
                                        <i class="bi <?= $meta['icon'] ?> me-1 text-muted"></i>
                                        <?= e($meta['label']) ?>
                                        <?php if ($isCritical): ?><span class="badge bg-danger ms-1" style="font-size:.65rem;">Critical</span><?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= $def['in_app'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>' ?></td>
                                    <td class="text-center"><?= $def['email']  ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>' ?></td>
                                    <td class="text-center"><?= $def['push']   ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>' ?></td>
                                    <td class="text-center"><?= $def['sms']    ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        These are the defaults applied when a new user has not set a preference. Critical events are always on.
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Active System Messages -->
    <div class="card shadow-sm mt-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-semibold"><i class="bi bi-list-ul me-2 text-primary"></i>Active System Messages</h5>
            <a href="/pages/admin/messages/system.php" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-grid me-1"></i>Manage All
            </a>
        </div>
        <div class="card-body p-0">
            <?php if (empty($activeMessages)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-inbox display-6"></i>
                    <p class="mt-2">No system messages yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Priority</th>
                                <th>Target</th>
                                <th>Starts</th>
                                <th>Expires</th>
                                <th>Dismissed</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeMessages as $msg): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e($msg['title']) ?></td>
                                    <td><span class="badge bg-secondary"><?= e($msg['type']) ?></span></td>
                                    <td>
                                        <span class="badge bg-<?= $priorityBadge[$msg['priority']] ?? 'secondary' ?>">
                                            <?= e($msg['priority']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $roles = json_decode($msg['target_roles_json'] ?? '[]', true);
                                        echo empty($roles) ? '<span class="text-muted">All</span>' : e(implode(', ', $roles));
                                        ?>
                                    </td>
                                    <td><small><?= e(substr($msg['starts_at'], 0, 16)) ?></small></td>
                                    <td><small><?= $msg['expires_at'] ? e(substr($msg['expires_at'], 0, 16)) : '—' ?></small></td>
                                    <td class="text-center"><?= (int) $msg['dismiss_count'] ?></td>
                                    <td>
                                        <?php if ($msg['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="deactivate_msg" value="<?= (int) $msg['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="Deactivate"
                                                    <?= !$msg['is_active'] ? 'disabled' : '' ?>>
                                                <i class="bi bi-pause"></i>
                                            </button>
                                        </form>
                                        <form method="POST" class="d-inline ms-1"
                                              onsubmit="return confirm('Delete this system message?')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="delete_msg" value="<?= (int) $msg['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
