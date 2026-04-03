<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

$eventTypes = [
    'order_placed'      => ['label' => 'Order Placed',        'icon' => 'bi-box-seam'],
    'order_status'      => ['label' => 'Order Status Update', 'icon' => 'bi-arrow-repeat'],
    'payment_confirmed' => ['label' => 'Payment Confirmed',   'icon' => 'bi-credit-card'],
    'shipment_update'   => ['label' => 'Shipment Update',     'icon' => 'bi-truck'],
    'delivery_complete' => ['label' => 'Delivery Complete',    'icon' => 'bi-check-circle'],
    'new_message'       => ['label' => 'New Message',          'icon' => 'bi-chat-dots'],
    'new_review'        => ['label' => 'New Review',           'icon' => 'bi-star'],
    'product_approved'  => ['label' => 'Product Approved',     'icon' => 'bi-bag-check'],
    'product_rejected'  => ['label' => 'Product Rejected',     'icon' => 'bi-bag-x'],
    'payout_processed'  => ['label' => 'Payout Processed',     'icon' => 'bi-cash-stack'],
    'payout_requested'  => ['label' => 'Payout Requested',     'icon' => 'bi-wallet2'],
    'low_stock_alert'   => ['label' => 'Low Stock Alert',      'icon' => 'bi-exclamation-triangle'],
    'plan_renewal'      => ['label' => 'Plan Renewal',         'icon' => 'bi-arrow-clockwise'],
    'dispute_opened'    => ['label' => 'Dispute Opened',       'icon' => 'bi-flag'],
    'dispute_update'    => ['label' => 'Dispute Update',       'icon' => 'bi-flag-fill'],
    'system_alert'      => ['label' => 'System Alert',         'icon' => 'bi-gear'],
    'security_alert'    => ['label' => 'Security Alert',       'icon' => 'bi-shield-exclamation'],
    'coupon_received'   => ['label' => 'Coupon Received',      'icon' => 'bi-ticket-perforated'],
    'carry_match'       => ['label' => 'Carry Match',          'icon' => 'bi-airplane'],
    'carry_delivery'    => ['label' => 'Carry Delivery',       'icon' => 'bi-box2-heart'],
];

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    foreach ($eventTypes as $type => $info) {
        $inApp = 1; // always on
        $email = !empty($_POST['email'][$type]) ? 1 : 0;
        $push  = 0; // future feature
        $sms   = 0;

        $db->prepare(
            'INSERT INTO notification_preferences (user_id, event_type, in_app, email, push, sms)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE in_app=VALUES(in_app), email=VALUES(email), push=VALUES(push), sms=VALUES(sms)'
        )->execute([$userId, $type, $inApp, $email, $push, $sms]);
    }
    $saved = true;
}

// Load current preferences
$stmt = $db->prepare('SELECT * FROM notification_preferences WHERE user_id=?');
$stmt->execute([$userId]);
$prefs = [];
foreach ($stmt->fetchAll() as $row) {
    $prefs[$row['event_type']] = $row;
}

$pageTitle = 'Notification Preferences';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-bell me-2 text-primary"></i>Notification Preferences</h3>
        <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Notifications</a>
    </div>

    <?php if (!empty($saved)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-1"></i> Preferences saved successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <p class="mb-0 text-muted">Choose how you want to be notified for each event type.</p>
        </div>
        <div class="card-body p-0">
            <form method="POST">
                <?= csrfField() ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40%">Event</th>
                                <th class="text-center" style="width:15%">In-App</th>
                                <th class="text-center" style="width:15%">Email</th>
                                <th class="text-center" style="width:15%">Push</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($eventTypes as $type => $info): ?>
                                <?php
                                $pref      = $prefs[$type] ?? null;
                                $emailOn   = $pref ? (bool)$pref['email'] : true;
                                ?>
                                <tr>
                                    <td>
                                        <i class="bi <?= $info['icon'] ?> me-2 text-primary"></i>
                                        <?= e($info['label']) ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check form-switch d-flex justify-content-center">
                                            <input class="form-check-input" type="checkbox" checked disabled>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check form-switch d-flex justify-content-center">
                                            <input class="form-check-input" type="checkbox" name="email[<?= e($type) ?>]" value="1" <?= $emailOn ? 'checked' : '' ?>>
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
                <div class="p-3 border-top">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Preferences</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
