<?php
/**
 * pages/supplier/settings/notifications.php — Supplier Notification Settings (PR #23)
 *
 * Allows suppliers to configure their per-channel preferences for
 * supplier-specific and general event types.
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/notification_preferences.php';
requireAuth();
requireRole(['supplier', 'admin']);

$db     = getDB();
$userId = (int) $_SESSION['user_id'];

$allEvents = getNotificationEventTypes();
$criticals = getCriticalEventTypes();

// Supplier sees supplier-specific + security + system + financial + orders categories
$supplierCategories = ['supplier', 'orders', 'financial', 'messages', 'security', 'system'];

$categories = [
    'supplier'  => ['label' => 'Supplier Events',   'icon' => 'bi-shop'],
    'orders'    => ['label' => 'Orders & Shopping',  'icon' => 'bi-bag-check'],
    'financial' => ['label' => 'Financial',          'icon' => 'bi-cash-stack'],
    'messages'  => ['label' => 'Messages',           'icon' => 'bi-chat-dots'],
    'security'  => ['label' => 'Security',           'icon' => 'bi-shield-exclamation'],
    'system'    => ['label' => 'System',             'icon' => 'bi-gear'],
];

// Handle mute-all
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf() && isset($_POST['mute_all'])) {
    $mutePrefs = [];
    foreach ($allEvents as $type => $meta) {
        if (in_array($meta['category'], $supplierCategories, true) && !in_array($type, $criticals, true)) {
            $mutePrefs[$type] = ['in_app' => 0, 'email' => 0, 'push' => 0, 'sms' => 0];
        }
    }
    updateBulkPreferences($db, $userId, $mutePrefs);
    header('Location: notifications.php?saved=1');
    exit;
}

// Handle reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf() && isset($_POST['reset_defaults'])) {
    resetToDefaults($db, $userId);
    header('Location: notifications.php?saved=2');
    exit;
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf() && isset($_POST['save_prefs'])) {
    $newPrefs = [];
    foreach ($allEvents as $type => $meta) {
        if (!in_array($meta['category'], $supplierCategories, true)) {
            continue;
        }
        $newPrefs[$type] = [
            'in_app' => 1,
            'email'  => !empty($_POST['pref'][$type]['email'])  ? 1 : 0,
            'push'   => !empty($_POST['pref'][$type]['push'])   ? 1 : 0,
            'sms'    => 0,
        ];
    }
    updateBulkPreferences($db, $userId, $newPrefs);
    header('Location: notifications.php?saved=1');
    exit;
}

$prefs     = getPreferences($db, $userId);
$savedFlag = (int) ($_GET['saved'] ?? 0);

$pageTitle = 'Notification Settings';
include __DIR__ . '/../../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold"><i class="bi bi-bell me-2 text-primary"></i>Notification Settings</h3>
            <p class="text-muted mb-0">Manage how you receive notifications for your store and orders.</p>
        </div>
        <a href="/pages/supplier/dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
        </a>
    </div>

    <?php if ($savedFlag === 1): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-1"></i> Preferences saved successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($savedFlag === 2): ?>
        <div class="alert alert-info alert-dismissible fade show">
            <i class="bi bi-arrow-counterclockwise me-1"></i> Preferences reset to defaults.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="d-flex gap-2 mb-3 flex-wrap">
        <form method="POST" class="d-inline">
            <?= csrfField() ?>
            <button type="submit" name="mute_all" value="1" class="btn btn-outline-warning btn-sm">
                <i class="bi bi-bell-slash me-1"></i>Mute All (except critical)
            </button>
        </form>
        <form method="POST" class="d-inline">
            <?= csrfField() ?>
            <button type="submit" name="reset_defaults" value="1" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Reset to Defaults
            </button>
        </form>
    </div>

    <form method="POST">
        <?= csrfField() ?>
        <?php foreach ($categories as $catKey => $catInfo): ?>
            <?php
            $catEvents = array_filter($allEvents, static fn($m) => $m['category'] === $catKey);
            if (empty($catEvents)) continue;
            ?>
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi <?= $catInfo['icon'] ?> me-2 text-primary"></i><?= e($catInfo['label']) ?>
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width:220px;">Event</th>
                                    <th class="text-center" style="width:110px;">In-App</th>
                                    <th class="text-center" style="width:110px;">Email</th>
                                    <th class="text-center" style="width:110px;">Push</th>
                                    <th class="text-center" style="width:110px;">SMS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($catEvents as $type => $meta): ?>
                                    <?php
                                    $isCritical = in_array($type, $criticals, true);
                                    $pref       = $prefs[$type] ?? $meta['defaults'];
                                    ?>
                                    <tr>
                                        <td>
                                            <i class="bi <?= $meta['icon'] ?> me-2 text-primary"></i>
                                            <?= e($meta['label']) ?>
                                            <?php if ($isCritical): ?>
                                                <span class="badge bg-danger ms-1" title="Cannot be disabled">Critical</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="form-check form-switch d-flex justify-content-center">
                                                <input class="form-check-input" type="checkbox" checked disabled>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="form-check form-switch d-flex justify-content-center">
                                                <?php if ($isCritical): ?>
                                                    <input class="form-check-input" type="checkbox" checked disabled>
                                                <?php else: ?>
                                                    <input class="form-check-input" type="checkbox"
                                                           name="pref[<?= e($type) ?>][email]" value="1"
                                                           <?= $pref['email'] ? 'checked' : '' ?>>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="form-check form-switch d-flex justify-content-center">
                                                <?php if ($isCritical): ?>
                                                    <input class="form-check-input" type="checkbox" checked disabled>
                                                <?php else: ?>
                                                    <input class="form-check-input" type="checkbox"
                                                           name="pref[<?= e($type) ?>][push]" value="1"
                                                           <?= $pref['push'] ? 'checked' : '' ?>>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="form-check form-switch d-flex justify-content-center">
                                                <input class="form-check-input" type="checkbox" disabled title="Coming soon">
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="d-flex gap-2 mt-1">
            <button type="submit" name="save_prefs" value="1" class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i>Save Preferences
            </button>
            <a href="/pages/supplier/dashboard.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
