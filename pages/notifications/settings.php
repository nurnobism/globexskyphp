<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db = getDB();
$userId = $_SESSION['user_id'] ?? 0;

$stmt = $db->prepare("SELECT * FROM notification_preferences WHERE user_id = ?");
$stmt->execute([$userId]);
$prefs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

$categories = [
    'order_updates' => ['label' => 'Order Updates', 'icon' => 'bi-box-seam', 'desc' => 'Status changes, confirmations, and delivery updates'],
    'promotions'    => ['label' => 'Promotions', 'icon' => 'bi-tag', 'desc' => 'Deals, discounts, and special offers'],
    'messages'      => ['label' => 'Messages', 'icon' => 'bi-envelope', 'desc' => 'Direct messages from suppliers and buyers'],
    'shipments'     => ['label' => 'Shipments', 'icon' => 'bi-truck', 'desc' => 'Shipping and tracking notifications'],
    'system_alerts' => ['label' => 'System Alerts', 'icon' => 'bi-gear', 'desc' => 'Account security and system announcements'],
];

$pageTitle = 'Notification Settings';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-sliders me-2"></i>Notification Preferences</h1>
            <p class="text-muted mb-0">Choose how you want to be notified for each category.</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Notifications
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <form method="post" action="../../api/notifications.php?action=update_preferences">
                <?= csrfField() ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4" style="min-width:250px;">Category</th>
                                <th class="text-center" style="width:120px;">
                                    <i class="bi bi-envelope me-1"></i>Email
                                </th>
                                <th class="text-center" style="width:120px;">
                                    <i class="bi bi-bell me-1"></i>Push
                                </th>
                                <th class="text-center" style="width:120px;">
                                    <i class="bi bi-phone me-1"></i>SMS
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $key => $cat): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <i class="bi <?= $cat['icon'] ?> fs-5 text-primary me-3"></i>
                                            <div>
                                                <div class="fw-semibold"><?= e($cat['label']) ?></div>
                                                <small class="text-muted"><?= e($cat['desc']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check d-flex justify-content-center">
                                            <input class="form-check-input" type="checkbox"
                                                   name="prefs[<?= $key ?>][email]" value="1"
                                                   <?= !empty($prefs[$key . '_email']) ? 'checked' : '' ?>>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check d-flex justify-content-center">
                                            <input class="form-check-input" type="checkbox"
                                                   name="prefs[<?= $key ?>][push]" value="1"
                                                   <?= !empty($prefs[$key . '_push']) ? 'checked' : '' ?>>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check d-flex justify-content-center">
                                            <input class="form-check-input" type="checkbox"
                                                   name="prefs[<?= $key ?>][sms]" value="1"
                                                   <?= !empty($prefs[$key . '_sms']) ? 'checked' : '' ?>>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white text-end py-3 px-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Save Preferences
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
