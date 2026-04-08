<?php
/**
 * GlobexSky Notification Engine — PR #21
 *
 * 50+ event types with smart channel routing based on user online status:
 *   Online  (< 5 min)  → toast/push only
 *   Away    (5-60 min) → toast + badge + email digest
 *   Offline (> 1 hour) → badge + email immediately
 *
 * Core public API:
 *   notify($db, $userId, $type, $data, $channels)
 *   getNotifications($db, $userId, $page, $perPage, $tab)
 *   getUnreadCount($db, $userId)
 *   markAsRead($db, $notificationId, $userId)
 *   markAllAsRead($db, $userId)
 *   deleteNotification($db, $notificationId, $userId)
 *   clearAll($db, $userId)
 *   groupNotifications($db, $userId, $type, $timeWindow)
 *
 * Legacy aliases retained for backwards compatibility:
 *   createNotification(...)
 *   getUserNotifications(...)
 *   markNotificationAsRead(...)
 *   markAllNotificationsAsRead(...)
 *   getUnreadNotificationCount(...)
 *   createBulkNotification(...)
 */

// ── Event-type catalogue ──────────────────────────────────────────────────────

/**
 * Returns the full catalogue of known notification event types.
 * Each entry: [ title_template, message_template, icon, category, channels[] ]
 */
function getNotificationEventTypes(): array
{
    return [
        // Order Events — Buyer
        'order.placed'              => ['title' => 'Order Placed',              'tpl' => 'Your order #[num] has been placed',        'icon' => 'bi-bag-check',             'cat' => 'orders',    'ch' => ['in_app','email']],
        'order.confirmed'           => ['title' => 'Order Confirmed',           'tpl' => 'Order #[num] confirmed by seller',          'icon' => 'bi-check-circle',          'cat' => 'orders',    'ch' => ['in_app']],
        'order.shipped'             => ['title' => 'Order Shipped',             'tpl' => 'Order #[num] has been shipped',             'icon' => 'bi-truck',                 'cat' => 'orders',    'ch' => ['in_app','email']],
        'order.delivered'           => ['title' => 'Order Delivered',           'tpl' => 'Order #[num] delivered',                   'icon' => 'bi-box-seam',              'cat' => 'orders',    'ch' => ['in_app','email']],
        'order.cancelled'           => ['title' => 'Order Cancelled',           'tpl' => 'Order #[num] has been cancelled',          'icon' => 'bi-x-circle',              'cat' => 'orders',    'ch' => ['in_app','email']],
        'order.refunded'            => ['title' => 'Refund Processed',          'tpl' => 'Refund processed for order #[num]',        'icon' => 'bi-arrow-counterclockwise','cat' => 'orders',    'ch' => ['in_app','email']],
        // Order Events — Supplier
        'supplier.new_order'        => ['title' => 'New Order Received',        'tpl' => 'New order #[num] received',                'icon' => 'bi-bag-plus',              'cat' => 'orders',    'ch' => ['in_app','email']],
        'supplier.order_paid'       => ['title' => 'Payment Received',          'tpl' => 'Payment received for order #[num]',        'icon' => 'bi-credit-card',           'cat' => 'financial', 'ch' => ['in_app','email']],
        'supplier.order_cancelled'  => ['title' => 'Order Cancelled by Buyer',  'tpl' => 'Order #[num] cancelled by buyer',          'icon' => 'bi-x-circle',              'cat' => 'orders',    'ch' => ['in_app','email']],
        'supplier.review_received'  => ['title' => 'New Review',                'tpl' => 'New review on [product]',                  'icon' => 'bi-star',                  'cat' => 'orders',    'ch' => ['in_app']],
        // Financial Events
        'payout.requested'          => ['title' => 'Payout Requested',          'tpl' => 'Payout request submitted',                 'icon' => 'bi-wallet2',               'cat' => 'financial', 'ch' => ['in_app','email']],
        'payout.approved'           => ['title' => 'Payout Approved',           'tpl' => 'Payout of $[amount] approved',             'icon' => 'bi-cash-stack',            'cat' => 'financial', 'ch' => ['in_app','email']],
        'payout.completed'          => ['title' => 'Payout Sent',               'tpl' => 'Payout of $[amount] sent',                 'icon' => 'bi-cash-coin',             'cat' => 'financial', 'ch' => ['in_app','email']],
        'payout.rejected'           => ['title' => 'Payout Rejected',           'tpl' => 'Payout request rejected',                  'icon' => 'bi-x-octagon',             'cat' => 'financial', 'ch' => ['in_app','email']],
        'commission.earned'         => ['title' => 'Commission Earned',          'tpl' => 'Commission earned on order #[num]',        'icon' => 'bi-percent',               'cat' => 'financial', 'ch' => ['in_app']],
        'plan.expires_soon'         => ['title' => 'Plan Expiring Soon',         'tpl' => 'Your plan expires in [days] days',         'icon' => 'bi-calendar-x',            'cat' => 'financial', 'ch' => ['in_app','email']],
        'plan.expired'              => ['title' => 'Plan Expired',               'tpl' => 'Your plan has expired',                   'icon' => 'bi-calendar-x-fill',       'cat' => 'financial', 'ch' => ['in_app','email']],
        'plan.upgraded'             => ['title' => 'Plan Upgraded',              'tpl' => 'Plan upgraded to [plan]',                 'icon' => 'bi-arrow-up-circle',       'cat' => 'financial', 'ch' => ['in_app','email']],
        'invoice.created'           => ['title' => 'New Invoice',                'tpl' => 'New invoice #[num]',                      'icon' => 'bi-receipt',               'cat' => 'financial', 'ch' => ['in_app','email']],
        // Chat & Communication
        'message.new'               => ['title' => 'New Message',                'tpl' => 'New message from [user]',                  'icon' => 'bi-chat-dots',             'cat' => 'messages',  'ch' => ['in_app','email']],
        'message.group_mention'     => ['title' => 'You Were Mentioned',         'tpl' => 'You were mentioned in [group]',            'icon' => 'bi-at',                    'cat' => 'messages',  'ch' => ['in_app']],
        'webmail.new'               => ['title' => 'New Webmail',                'tpl' => 'New webmail from [user]',                  'icon' => 'bi-envelope',              'cat' => 'messages',  'ch' => ['in_app','email']],
        // Shipping & Delivery
        'tracking.update'           => ['title' => 'Tracking Update',            'tpl' => 'Tracking update for order #[num]',        'icon' => 'bi-geo-alt',               'cat' => 'orders',    'ch' => ['in_app']],
        'tracking.delivered'        => ['title' => 'Package Delivered',          'tpl' => 'Package delivered for order #[num]',      'icon' => 'bi-box-seam-fill',         'cat' => 'orders',    'ch' => ['in_app','email']],
        'carry.request_received'    => ['title' => 'New Carry Request',          'tpl' => 'New carry request',                       'icon' => 'bi-airplane',              'cat' => 'orders',    'ch' => ['in_app','email']],
        'carry.request_accepted'    => ['title' => 'Carry Request Accepted',     'tpl' => 'Carry request accepted',                  'icon' => 'bi-airplane-fill',         'cat' => 'orders',    'ch' => ['in_app','email']],
        'carry.picked_up'           => ['title' => 'Package Picked Up',          'tpl' => 'Package picked up',                       'icon' => 'bi-box2-heart',            'cat' => 'orders',    'ch' => ['in_app']],
        // Admin Notifications
        'admin.new_supplier'        => ['title' => 'New Supplier Registration',  'tpl' => 'New supplier registration',               'icon' => 'bi-person-plus',           'cat' => 'system',    'ch' => ['in_app','email']],
        'admin.kyc_submitted'       => ['title' => 'KYC Submitted',              'tpl' => 'KYC document submitted',                  'icon' => 'bi-file-earmark-person',   'cat' => 'system',    'ch' => ['in_app','email']],
        'admin.payout_request'      => ['title' => 'Payout Request',             'tpl' => 'New payout request',                      'icon' => 'bi-wallet2',               'cat' => 'financial', 'ch' => ['in_app','email']],
        'admin.dispute_opened'      => ['title' => 'Dispute Opened',             'tpl' => 'New dispute opened',                      'icon' => 'bi-flag',                  'cat' => 'system',    'ch' => ['in_app','email']],
        'admin.low_stock'           => ['title' => 'Low Stock Alert',            'tpl' => 'Low stock alert',                         'icon' => 'bi-exclamation-triangle',  'cat' => 'system',    'ch' => ['in_app','email']],
        'admin.system_error'        => ['title' => 'System Error',               'tpl' => 'System error detected',                   'icon' => 'bi-bug',                   'cat' => 'system',    'ch' => ['in_app','email']],
        // Account & Security
        'account.login'             => ['title' => 'New Login',                  'tpl' => 'New login from [device]',                 'icon' => 'bi-box-arrow-in-right',    'cat' => 'system',    'ch' => ['in_app','email']],
        'account.password_changed'  => ['title' => 'Password Changed',           'tpl' => 'Password changed',                        'icon' => 'bi-key',                   'cat' => 'system',    'ch' => ['in_app','email']],
        'account.kyc_approved'      => ['title' => 'KYC Approved',               'tpl' => 'KYC verification approved',               'icon' => 'bi-shield-check',          'cat' => 'system',    'ch' => ['in_app','email']],
        'account.kyc_rejected'      => ['title' => 'KYC Rejected',               'tpl' => 'KYC verification rejected',               'icon' => 'bi-shield-x',              'cat' => 'system',    'ch' => ['in_app','email']],
        'account.email_verified'    => ['title' => 'Email Verified',             'tpl' => 'Your email has been verified',            'icon' => 'bi-envelope-check',        'cat' => 'system',    'ch' => ['in_app']],
        'account.profile_updated'   => ['title' => 'Profile Updated',            'tpl' => 'Your profile was updated',                'icon' => 'bi-person-check',          'cat' => 'system',    'ch' => ['in_app']],
        // Product Events
        'product.approved'          => ['title' => 'Product Approved',           'tpl' => 'Your product [product] was approved',     'icon' => 'bi-check-circle',          'cat' => 'orders',    'ch' => ['in_app','email']],
        'product.rejected'          => ['title' => 'Product Rejected',           'tpl' => 'Your product [product] was rejected',     'icon' => 'bi-slash-circle',          'cat' => 'orders',    'ch' => ['in_app','email']],
        'product.out_of_stock'      => ['title' => 'Out of Stock',               'tpl' => 'Product [product] is out of stock',       'icon' => 'bi-archive',               'cat' => 'orders',    'ch' => ['in_app','email']],
        'product.back_in_stock'     => ['title' => 'Back in Stock',              'tpl' => 'Product [product] is back in stock',      'icon' => 'bi-archive-fill',          'cat' => 'orders',    'ch' => ['in_app']],
        // Review Events
        'review.received'           => ['title' => 'New Review',                 'tpl' => 'New review on your product',              'icon' => 'bi-star-fill',             'cat' => 'orders',    'ch' => ['in_app']],
        'review.reply'              => ['title' => 'Review Reply',               'tpl' => 'Someone replied to your review',          'icon' => 'bi-star',                  'cat' => 'messages',  'ch' => ['in_app']],
        // Promotion / Marketing
        'promo.flash_sale'          => ['title' => 'Flash Sale!',                'tpl' => 'Flash sale started on [product]',         'icon' => 'bi-lightning',             'cat' => 'system',    'ch' => ['in_app']],
        'promo.coupon_expiring'     => ['title' => 'Coupon Expiring Soon',       'tpl' => 'Your coupon [code] expires in [days] days','icon' => 'bi-ticket-perforated',    'cat' => 'system',    'ch' => ['in_app','email']],
        'promo.coupon_used'         => ['title' => 'Coupon Applied',             'tpl' => 'Coupon [code] applied to order #[num]',   'icon' => 'bi-ticket-perforated-fill','cat' => 'financial', 'ch' => ['in_app']],
        // Dispute Events
        'dispute.opened'            => ['title' => 'Dispute Opened',             'tpl' => 'Dispute opened for order #[num]',         'icon' => 'bi-flag',                  'cat' => 'orders',    'ch' => ['in_app','email']],
        'dispute.updated'           => ['title' => 'Dispute Update',             'tpl' => 'Dispute for order #[num] updated',        'icon' => 'bi-flag-fill',             'cat' => 'orders',    'ch' => ['in_app','email']],
        'dispute.resolved'          => ['title' => 'Dispute Resolved',           'tpl' => 'Dispute for order #[num] resolved',       'icon' => 'bi-check2-all',            'cat' => 'orders',    'ch' => ['in_app','email']],
        // System
        'system.maintenance'        => ['title' => 'Scheduled Maintenance',      'tpl' => 'Scheduled maintenance in [time]',         'icon' => 'bi-tools',                 'cat' => 'system',    'ch' => ['in_app','email']],
        'system.announcement'       => ['title' => 'Announcement',               'tpl' => 'New announcement from GlobexSky',         'icon' => 'bi-megaphone',             'cat' => 'system',    'ch' => ['in_app']],
        // Legacy aliases (kept for backwards compatibility)
        'order_placed'              => ['title' => 'Order Placed',               'tpl' => 'Your order has been placed',              'icon' => 'bi-bag-check',             'cat' => 'orders',    'ch' => ['in_app','email']],
        'order_shipped'             => ['title' => 'Order Shipped',              'tpl' => 'Your order has been shipped',             'icon' => 'bi-truck',                 'cat' => 'orders',    'ch' => ['in_app','email']],
        'order_delivered'           => ['title' => 'Order Delivered',            'tpl' => 'Your order has been delivered',           'icon' => 'bi-box-seam',              'cat' => 'orders',    'ch' => ['in_app','email']],
        'order_cancelled'           => ['title' => 'Order Cancelled',            'tpl' => 'Your order has been cancelled',           'icon' => 'bi-x-circle',              'cat' => 'orders',    'ch' => ['in_app','email']],
        'order_refunded'            => ['title' => 'Refund Processed',           'tpl' => 'Your refund has been processed',          'icon' => 'bi-arrow-counterclockwise','cat' => 'orders',    'ch' => ['in_app','email']],
        'payment_received'          => ['title' => 'Payment Received',           'tpl' => 'Payment received',                        'icon' => 'bi-credit-card',           'cat' => 'financial', 'ch' => ['in_app','email']],
        'payment_failed'            => ['title' => 'Payment Failed',             'tpl' => 'Payment failed',                          'icon' => 'bi-credit-card-2-back',    'cat' => 'financial', 'ch' => ['in_app','email']],
        'message_received'          => ['title' => 'New Message',                'tpl' => 'You have a new message',                  'icon' => 'bi-chat-dots',             'cat' => 'messages',  'ch' => ['in_app','email']],
        'product_approved'          => ['title' => 'Product Approved',           'tpl' => 'Your product was approved',               'icon' => 'bi-check-circle',          'cat' => 'orders',    'ch' => ['in_app','email']],
        'product_rejected'          => ['title' => 'Product Rejected',           'tpl' => 'Your product was rejected',               'icon' => 'bi-slash-circle',          'cat' => 'orders',    'ch' => ['in_app','email']],
        'low_stock'                 => ['title' => 'Low Stock Alert',            'tpl' => 'Low stock alert',                         'icon' => 'bi-exclamation-triangle',  'cat' => 'system',    'ch' => ['in_app','email']],
        'account_verified'          => ['title' => 'Account Verified',           'tpl' => 'Your account has been verified',          'icon' => 'bi-shield-check',          'cat' => 'system',    'ch' => ['in_app','email']],
        'password_changed'          => ['title' => 'Password Changed',           'tpl' => 'Your password has been changed',          'icon' => 'bi-key',                   'cat' => 'system',    'ch' => ['in_app','email']],
        'promo'                     => ['title' => 'Promotion',                  'tpl' => 'New promotion available',                 'icon' => 'bi-tag',                   'cat' => 'system',    'ch' => ['in_app']],
        'system'                    => ['title' => 'System Notice',              'tpl' => 'System notice',                           'icon' => 'bi-gear',                  'cat' => 'system',    'ch' => ['in_app']],
    ];
}

// ── User online-status helper ─────────────────────────────────────────────────

/**
 * Determine user activity status based on last_seen timestamp.
 * Returns 'online' | 'away' | 'offline'
 */
function getUserActivityStatus(PDO $db, int $userId): string
{
    try {
        $stmt = $db->prepare('SELECT last_seen FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $lastSeen = $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 'offline';
    }

    if (empty($lastSeen)) {
        return 'offline';
    }

    $diff = time() - strtotime($lastSeen);
    if ($diff < 300) {        // < 5 min
        return 'online';
    }
    if ($diff < 3600) {       // 5 min – 1 hour
        return 'away';
    }
    return 'offline';
}

// ── Core: notify() ────────────────────────────────────────────────────────────

/**
 * Send a notification to a user.
 *
 * Smart channel routing:
 *   Online  (< 5 min)  → toast/push only (no email)
 *   Away    (5-60 min) → in_app + badge + email digest
 *   Offline (> 1 hour) → in_app + badge + email immediately
 *
 * @param PDO    $db
 * @param int    $userId    Recipient user ID
 * @param string $type      Event type (e.g. 'order.placed')
 * @param array  $data      Template variables & extra payload
 * @param array  $channels  Override automatic channel selection
 * @return int  Notification ID (0 on failure)
 */
function notify(PDO $db, int $userId, string $type, array $data = [], array $channels = []): int
{
    $catalogue = getNotificationEventTypes();
    $meta = $catalogue[$type] ?? [
        'title' => 'Notification',
        'tpl'   => 'You have a new notification',
        'icon'  => 'bi-bell',
        'cat'   => 'system',
        'ch'    => ['in_app'],
    ];

    // Build title & message from templates
    $title   = $data['title']   ?? $meta['title'];
    $message = $data['message'] ?? $meta['tpl'];
    foreach ($data as $key => $value) {
        if (is_scalar($value)) {
            $message = str_replace('[' . $key . ']', htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'), $message);
        }
    }

    $icon      = $data['icon']       ?? $meta['icon'];
    $actionUrl = $data['action_url'] ?? '';
    $priority  = $data['priority']   ?? 'normal';
    $groupKey  = $data['group_key']  ?? null;
    $dataJson  = [];
    foreach ($data as $k => $v) {
        if (!in_array($k, ['title','message','icon','action_url','priority','group_key'], true)) {
            $dataJson[$k] = $v;
        }
    }

    // Determine delivery channels
    if (empty($channels)) {
        $status = getUserActivityStatus($db, $userId);
        if ($status === 'online') {
            $channels = ['in_app'];           // toast/push only, skip email
        } elseif ($status === 'away') {
            $channels = ['in_app', 'email'];  // badge + email digest
        } else {
            $channels = ['in_app', 'email'];  // badge + email immediately
        }
        // Intersect with the event's default channels
        $channels = array_intersect($channels, array_merge($meta['ch'], ['in_app']));
        if (empty($channels)) {
            $channels = ['in_app'];
        }
    }

    $primaryChannel = in_array('in_app', $channels) ? 'in_app' : ($channels[0] ?? 'in_app');

    // Persist in-app notification
    $notifId = createNotification(
        $db, $userId, $type, $title, $message, $dataJson,
        $priority, $actionUrl, $icon, $groupKey, $primaryChannel
    );

    // Queue email delivery if needed
    if ($notifId && in_array('email', $channels)) {
        try {
            $emailStatus = getUserActivityStatus($db, $userId);
            $scheduledAt = ($emailStatus === 'away') ? date('Y-m-d H:i:s', time() + 900) : null; // 15 min digest for away
            $stmt = $db->prepare(
                'INSERT INTO notification_queue (notification_id, channel, status, scheduled_at, created_at)
                 VALUES (:nid, "email", "pending", :sched, NOW())'
            );
            $stmt->execute([':nid' => $notifId, ':sched' => $scheduledAt]);
        } catch (PDOException $e) {
            error_log('notify: failed to queue email: ' . $e->getMessage());
        }
    }

    return $notifId;
}

// ── Core: Insert a new notification ──────────────────────────────────────────

/**
 * Insert a new notification for a user.
 * Returns the inserted notification ID, or 0 on failure.
 */
function createNotification(
    PDO $db,
    int $userId,
    string $type,
    string $title,
    string $message,
    array $data = [],
    string $priority = 'normal',
    string $actionUrl = '',
    string $icon = '',
    ?string $groupKey = null,
    string $channel = 'in_app'
): int {
    if ($icon === '') {
        $icon = getNotificationIcon($type);
    }

    try {
        $stmt = $db->prepare(
            'INSERT INTO notifications
                (user_id, type, title, message, data, priority, action_url, icon, group_key, channel, is_read, created_at)
             VALUES
                (:user_id, :type, :title, :message, :data, :priority, :action_url, :icon, :group_key, :channel, 0, NOW())'
        );
        $stmt->execute([
            ':user_id'    => $userId,
            ':type'       => $type,
            ':title'      => $title,
            ':message'    => $message,
            ':data'       => !empty($data) ? json_encode($data) : null,
            ':priority'   => $priority,
            ':action_url' => $actionUrl,
            ':icon'       => $icon,
            ':group_key'  => $groupKey,
            ':channel'    => $channel,
        ]);
        return (int) $db->lastInsertId();
    } catch (PDOException $e) {
        error_log('createNotification error: ' . $e->getMessage());
        return 0;
    }
}

// ── Core: getNotifications() ─────────────────────────────────────────────────

/**
 * Get paginated notifications for a user, optionally filtered by tab/category.
 *
 * @param string $tab  'all' | 'unread' | 'orders' | 'financial' | 'messages' | 'system'
 */
function getNotifications(
    PDO $db,
    int $userId,
    int $page = 1,
    int $perPage = 20,
    string $tab = 'all'
): array {
    $conditions = ['user_id = :user_id'];
    $params     = [':user_id' => $userId];

    switch ($tab) {
        case 'unread':
            $conditions[] = 'is_read = 0';
            break;
        case 'orders':
            $conditions[] = "JSON_UNQUOTE(JSON_EXTRACT(data, '$.cat')) = 'orders'
                             OR type LIKE 'order%' OR type LIKE 'supplier%' OR type LIKE 'tracking%' OR type LIKE 'carry%'";
            break;
        case 'financial':
            $conditions[] = "JSON_UNQUOTE(JSON_EXTRACT(data, '$.cat')) = 'financial'
                             OR type LIKE 'payout%' OR type LIKE 'commission%' OR type LIKE 'plan%' OR type LIKE 'invoice%'
                             OR type IN ('payment_received','payment_failed','supplier.order_paid')";
            break;
        case 'messages':
            $conditions[] = "type LIKE 'message%' OR type LIKE 'webmail%' OR type = 'message_received'";
            break;
        case 'system':
            $conditions[] = "type LIKE 'admin%' OR type LIKE 'account%' OR type LIKE 'system%' OR type IN ('system','promo','low_stock','account_verified','password_changed')";
            break;
    }

    $where  = 'WHERE ' . implode(' AND ', $conditions);
    $offset = ($page - 1) * $perPage;

    try {
        $countStmt = $db->prepare("SELECT COUNT(*) FROM notifications {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $db->prepare(
            "SELECT * FROM notifications {$where} ORDER BY created_at DESC LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['time_ago'] = formatTimeAgo($row['created_at']);
            $row['data']     = !empty($row['data']) ? json_decode($row['data'], true) : [];
        }
        unset($row);

        return [
            'data'       => $rows,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $perPage,
            'last_page'  => (int) ceil($total / $perPage),
        ];
    } catch (PDOException $e) {
        error_log('getNotifications error: ' . $e->getMessage());
        return ['data' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'last_page' => 1];
    }
}

// ── Core: getUnreadCount() ────────────────────────────────────────────────────

/**
 * Get unread notification count for the header badge.
 */
function getUnreadCount(PDO $db, int $userId): int
{
    return getUnreadNotificationCount($db, $userId);
}

// ── Core: markAsRead() ────────────────────────────────────────────────────────

/**
 * Mark a single notification as read (user-scoped).
 */
function markAsRead(PDO $db, int $notificationId, int $userId): bool
{
    return markNotificationAsRead($db, $notificationId, $userId);
}

// ── Core: markAllAsRead() ─────────────────────────────────────────────────────

/**
 * Mark all notifications for a user as read.
 */
function markAllAsRead(PDO $db, int $userId): bool
{
    return markAllNotificationsAsRead($db, $userId);
}

// ── Core: clearAll() ─────────────────────────────────────────────────────────

/**
 * Delete all notifications for a user.
 */
function clearAll(PDO $db, int $userId): bool
{
    try {
        $stmt = $db->prepare('DELETE FROM notifications WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $userId]);
        return true;
    } catch (PDOException $e) {
        error_log('clearAll error: ' . $e->getMessage());
        return false;
    }
}
{
    try {
        $stmt = $db->prepare('DELETE FROM notifications WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $userId]);
        return true;
    } catch (PDOException $e) {
        error_log('clearAll error: ' . $e->getMessage());
        return false;
    }
}

// ── Core: groupNotifications() ───────────────────────────────────────────────

/**
 * Group similar notifications within a time window.
 * Returns a summary row with count for collapsed groups.
 *
 * @param int $timeWindow  Seconds to look back (default 3600 = 1 hour)
 */
function groupNotifications(PDO $db, int $userId, string $type, int $timeWindow = 3600): array
{
    try {
        $since = date('Y-m-d H:i:s', time() - $timeWindow);
        $stmt  = $db->prepare(
            'SELECT COUNT(*) AS cnt, MIN(id) AS first_id, MAX(created_at) AS latest,
                    GROUP_CONCAT(id ORDER BY created_at DESC) AS ids
             FROM notifications
             WHERE user_id = :user_id AND type = :type AND created_at >= :since'
        );
        $stmt->execute([':user_id' => $userId, ':type' => $type, ':since' => $since]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || (int) $row['cnt'] === 0) {
            return [];
        }

        $catalogue = getNotificationEventTypes();
        $meta      = $catalogue[$type] ?? ['title' => 'Notifications'];
        $count     = (int) $row['cnt'];
        $title     = $count > 1
            ? "{$count} new " . strtolower($meta['title']) . 's'
            : $meta['title'];

        return [
            'count'      => $count,
            'first_id'   => (int) $row['first_id'],
            'latest'     => $row['latest'],
            'ids'        => $row['ids'],
            'title'      => $title,
            'type'       => $type,
            'icon'       => getNotificationIcon($type),
        ];
    } catch (PDOException $e) {
        error_log('groupNotifications error: ' . $e->getMessage());
        return [];
    }
}

// ── Legacy / compatibility functions ─────────────────────────────────────────

/**
 * Return paginated notifications for a user (legacy alias).
 * Each row includes a human-readable 'time_ago' field.
 */
function getUserNotifications(
    PDO $db,
    int $userId,
    int $limit = 20,
    int $offset = 0,
    bool $unreadOnly = false
): array {
    $page    = (int) floor($offset / $limit) + 1;
    $tab     = $unreadOnly ? 'unread' : 'all';
    $result  = getNotifications($db, $userId, $page, $limit, $tab);
    return $result['data'];
}

/**
 * Delete a notification (scoped to owner).
 */
function deleteNotification(PDO $db, int $notificationId, int $userId): bool
{
    try {
        $stmt = $db->prepare(
            'DELETE FROM notifications WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([':id' => $notificationId, ':user_id' => $userId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('deleteNotification error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Mark a single notification as read (scoped to the owning user for safety).
 */
function markNotificationAsRead(PDO $db, int $notificationId, int $userId): bool
{
    try {
        $stmt = $db->prepare(
            'UPDATE notifications SET is_read = 1, read_at = NOW()
             WHERE id = :id AND user_id = :user_id AND is_read = 0'
        );
        $stmt->execute([':id' => $notificationId, ':user_id' => $userId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('markNotificationAsRead error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Mark every unread notification for a user as read.
 */
function markAllNotificationsAsRead(PDO $db, int $userId): bool
{
    try {
        $stmt = $db->prepare(
            'UPDATE notifications SET is_read = 1, read_at = NOW()
             WHERE user_id = :user_id AND is_read = 0'
        );
        $stmt->execute([':user_id' => $userId]);
        return true;
    } catch (PDOException $e) {
        error_log('markAllNotificationsAsRead error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Return the count of unread notifications for a user.
 */
function getUnreadNotificationCount(PDO $db, int $userId): int
{
    try {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0'
        );
        $stmt->execute([':user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('getUnreadNotificationCount error: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Create the same notification for multiple users at once.
 * Returns the number of successfully inserted rows.
 */
function createBulkNotification(
    PDO $db,
    array $userIds,
    string $type,
    string $title,
    string $message,
    array $data = [],
    string $priority = 'normal'
): int {
    if (empty($userIds)) {
        return 0;
    }

    $icon     = getNotificationIcon($type);
    $dataJson = !empty($data) ? json_encode($data) : null;
    $inserted = 0;

    try {
        $stmt = $db->prepare(
            'INSERT INTO notifications
                (user_id, type, title, message, data, priority, action_url, icon, channel, is_read, created_at)
             VALUES
                (:user_id, :type, :title, :message, :data, :priority, "", :icon, "in_app", 0, NOW())'
        );

        $db->beginTransaction();
        foreach ($userIds as $uid) {
            $stmt->execute([
                ':user_id'  => (int) $uid,
                ':type'     => $type,
                ':title'    => $title,
                ':message'  => $message,
                ':data'     => $dataJson,
                ':priority' => $priority,
                ':icon'     => $icon,
            ]);
            $inserted++;
        }
        $db->commit();
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('createBulkNotification error: ' . $e->getMessage());
    }

    return $inserted;
}

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Convert a datetime string into a human-friendly relative time string.
 * e.g. "just now", "3 min ago", "2 hours ago", "Yesterday", "Jan 5"
 */
function formatTimeAgo(string $datetime): string
{
    if (empty($datetime)) {
        return '';
    }

    $then = strtotime($datetime);
    if ($then === false) {
        return $datetime;
    }

    $now         = time();
    $diff        = $now - $then;
    $currentYear = date('Y');

    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        $mins = (int) floor($diff / 60);
        return $mins === 1 ? '1 min ago' : "{$mins} min ago";
    }
    if ($diff < 86400) {
        $hours = (int) floor($diff / 3600);
        return $hours === 1 ? '1 hour ago' : "{$hours} hours ago";
    }
    if ($diff < 172800) {
        return 'Yesterday';
    }
    if ($diff < 604800) {
        $days = (int) floor($diff / 86400);
        return "{$days} days ago";
    }

    $year = date('Y', $then);
    if ($year === $currentYear) {
        return date('M j', $then);
    }
    return date('M j, Y', $then);
}

/**
 * Map a notification type to a Bootstrap Icons class.
 */
function getNotificationIcon(string $type): string
{
    $catalogue = getNotificationEventTypes();
    if (isset($catalogue[$type]['icon'])) {
        return $catalogue[$type]['icon'];
    }

    // Prefix-based fallback
    if (str_starts_with($type, 'order') || str_starts_with($type, 'supplier'))  return 'bi-bag';
    if (str_starts_with($type, 'payout') || str_starts_with($type, 'commission')) return 'bi-cash-stack';
    if (str_starts_with($type, 'message') || str_starts_with($type, 'webmail')) return 'bi-chat-dots';
    if (str_starts_with($type, 'tracking') || str_starts_with($type, 'carry'))  return 'bi-truck';
    if (str_starts_with($type, 'admin'))                                          return 'bi-shield';
    if (str_starts_with($type, 'account'))                                        return 'bi-person';
    if (str_starts_with($type, 'system'))                                         return 'bi-gear';
    return 'bi-bell';
}
