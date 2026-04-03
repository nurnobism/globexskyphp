<?php
/**
 * GlobexSky Notification Engine
 *
 * Notification types:
 *  order_placed, order_shipped, order_delivered, order_cancelled, order_refunded
 *  payment_received, payment_failed
 *  message_received
 *  product_approved, product_rejected, low_stock
 *  account_verified, password_changed
 *  system, promo
 */

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
    string $icon = ''
): int {
    if ($icon === '') {
        $icon = getNotificationIcon($type);
    }

    try {
        $stmt = $db->prepare(
            'INSERT INTO notifications
                (user_id, type, title, message, data, priority, action_url, icon, is_read, created_at)
             VALUES
                (:user_id, :type, :title, :message, :data, :priority, :action_url, :icon, 0, NOW())'
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
        ]);
        return (int) $db->lastInsertId();
    } catch (PDOException $e) {
        error_log('createNotification error: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Return paginated notifications for a user.
 * Each row includes a human-readable 'time_ago' field.
 */
function getUserNotifications(
    PDO $db,
    int $userId,
    int $limit = 20,
    int $offset = 0,
    bool $unreadOnly = false
): array {
    try {
        $where = 'WHERE user_id = :user_id';
        if ($unreadOnly) {
            $where .= ' AND is_read = 0';
        }

        $stmt = $db->prepare(
            "SELECT * FROM notifications
             {$where}
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit',   $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset',  $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['time_ago'] = formatTimeAgo($row['created_at']);
            $row['data']     = !empty($row['data']) ? json_decode($row['data'], true) : [];
        }
        unset($row);

        return $rows;
    } catch (PDOException $e) {
        error_log('getUserNotifications error: ' . $e->getMessage());
        return [];
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

    $icon        = getNotificationIcon($type);
    $dataJson    = !empty($data) ? json_encode($data) : null;
    $inserted    = 0;

    try {
        $stmt = $db->prepare(
            'INSERT INTO notifications
                (user_id, type, title, message, data, priority, action_url, icon, is_read, created_at)
             VALUES
                (:user_id, :type, :title, :message, :data, :priority, "", :icon, 0, NOW())'
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

    $now  = time();
    $diff = $now - $then;
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

    // Older than 7 days: show date
    $year = date('Y', $then);
    if ($year === $currentYear) {
        return date('M j', $then);           // "Jan 5"
    }
    return date('M j, Y', $then);            // "Jan 5, 2023"
}

/**
 * Map a notification type to a Bootstrap Icons class.
 */
function getNotificationIcon(string $type): string
{
    return match ($type) {
        'order_placed'      => 'bi-bag-check',
        'order_shipped'     => 'bi-truck',
        'order_delivered'   => 'bi-box-seam',
        'order_cancelled'   => 'bi-x-circle',
        'order_refunded'    => 'bi-arrow-counterclockwise',
        'payment_received'  => 'bi-credit-card',
        'payment_failed'    => 'bi-credit-card-2-back',
        'message_received'  => 'bi-chat-dots',
        'product_approved'  => 'bi-check-circle',
        'product_rejected'  => 'bi-slash-circle',
        'low_stock'         => 'bi-exclamation-triangle',
        'account_verified'  => 'bi-shield-check',
        'password_changed'  => 'bi-key',
        'promo'             => 'bi-tag',
        'system'            => 'bi-gear',
        default             => 'bi-bell',
    };
}
