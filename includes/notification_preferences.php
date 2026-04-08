<?php
/**
 * includes/notification_preferences.php — Notification Preferences & System Messages Engine (PR #23)
 *
 * Sections:
 *   1. Default Preference Definitions
 *   2. Per-User Preference CRUD
 *   3. System Messages (Admin Broadcast)
 *
 * Critical notifications (security_alert, account_suspended, legal_notice, dispute_update)
 * always have channel_in_app = 1 and cannot be toggled off.
 */

// ─────────────────────────────────────────────────────────────────────────────
// 1. Default Preference Definitions
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Return the canonical list of event types with their category, label, and
 * whether they are "critical" (cannot be disabled by the user).
 *
 * Structure:
 *   [ event_type => [
 *       'label'    => string,
 *       'category' => string,
 *       'icon'     => string (Bootstrap Icons class),
 *       'critical' => bool,
 *       'defaults' => ['in_app'=>1,'email'=>1,'push'=>0,'sms'=>0]
 *   ], ... ]
 */
function getNotificationEventTypes(): array
{
    return [
        // Orders & Shopping
        'order_placed'       => ['label' => 'Order Placed',          'category' => 'orders',    'icon' => 'bi-bag-check',              'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 1, 'sms' => 0]],
        'order_status'       => ['label' => 'Order Status Update',   'category' => 'orders',    'icon' => 'bi-arrow-repeat',           'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 1, 'sms' => 0]],
        'order_cancelled'    => ['label' => 'Order Cancelled',       'category' => 'orders',    'icon' => 'bi-x-circle',               'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 1, 'sms' => 0]],
        'order_refunded'     => ['label' => 'Order Refunded',        'category' => 'orders',    'icon' => 'bi-arrow-counterclockwise', 'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 0, 'sms' => 0]],
        'shipment_update'    => ['label' => 'Shipment Update',       'category' => 'orders',    'icon' => 'bi-truck',                  'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 1, 'sms' => 0]],
        'delivery_complete'  => ['label' => 'Delivery Complete',     'category' => 'orders',    'icon' => 'bi-check-circle',           'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 1, 'sms' => 0]],
        // Financial
        'payment_confirmed'  => ['label' => 'Payment Confirmed',     'category' => 'financial', 'icon' => 'bi-credit-card',            'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 0, 'sms' => 0]],
        'payment_failed'     => ['label' => 'Payment Failed',        'category' => 'financial', 'icon' => 'bi-credit-card-2-back',     'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 1, 'sms' => 0]],
        'payout_processed'   => ['label' => 'Payout Processed',      'category' => 'financial', 'icon' => 'bi-cash-stack',             'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 0, 'sms' => 0]],
        'payout_requested'   => ['label' => 'Payout Requested',      'category' => 'financial', 'icon' => 'bi-wallet2',                'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 0, 'sms' => 0]],
        'commission_earned'  => ['label' => 'Commission Earned',     'category' => 'financial', 'icon' => 'bi-percent',                'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 0, 'push' => 0, 'sms' => 0]],
        'invoice_available'  => ['label' => 'Invoice Available',     'category' => 'financial', 'icon' => 'bi-receipt',                'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 0, 'sms' => 0]],
        'plan_changed'       => ['label' => 'Plan Changed',          'category' => 'financial', 'icon' => 'bi-arrow-up-circle',        'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 0, 'sms' => 0]],
        'plan_renewal'       => ['label' => 'Plan Renewal',          'category' => 'financial', 'icon' => 'bi-arrow-clockwise',        'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 0, 'sms' => 0]],
        // Messages
        'new_message'        => ['label' => 'New Message',           'category' => 'messages',  'icon' => 'bi-chat-dots',              'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 1, 'sms' => 0]],
        'new_mention'        => ['label' => 'Mention',               'category' => 'messages',  'icon' => 'bi-at',                     'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 1, 'sms' => 0]],
        'webmail_received'   => ['label' => 'Webmail Received',      'category' => 'messages',  'icon' => 'bi-envelope',               'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 0, 'push' => 0, 'sms' => 0]],
        // Marketing
        'coupon_received'    => ['label' => 'Coupon Received',       'category' => 'marketing', 'icon' => 'bi-ticket-perforated',      'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 0, 'push' => 0, 'sms' => 0]],
        'promo_offer'        => ['label' => 'Promotions & Deals',    'category' => 'marketing', 'icon' => 'bi-tag',                    'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 0, 'push' => 0, 'sms' => 0]],
        'newsletter'         => ['label' => 'Newsletter',            'category' => 'marketing', 'icon' => 'bi-newspaper',              'critical' => false, 'defaults' => ['in_app' => 0, 'email' => 0, 'push' => 0, 'sms' => 0]],
        // Security (critical — cannot be disabled)
        'security_alert'     => ['label' => 'Security Alert',        'category' => 'security',  'icon' => 'bi-shield-exclamation',     'critical' => true,  'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 1, 'sms' => 0]],
        'login_alert'        => ['label' => 'New Login Alert',       'category' => 'security',  'icon' => 'bi-key',                    'critical' => true,  'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 1, 'sms' => 0]],
        'password_changed'   => ['label' => 'Password Changed',      'category' => 'security',  'icon' => 'bi-lock',                   'critical' => true,  'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 0, 'sms' => 0]],
        'account_suspended'  => ['label' => 'Account Suspended',     'category' => 'security',  'icon' => 'bi-slash-circle',           'critical' => true,  'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 0, 'sms' => 0]],
        'legal_notice'       => ['label' => 'Legal / Compliance',    'category' => 'security',  'icon' => 'bi-file-earmark-text',      'critical' => true,  'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 0, 'sms' => 0]],
        'dispute_update'     => ['label' => 'Dispute Update',        'category' => 'security',  'icon' => 'bi-flag',                   'critical' => true,  'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 0, 'sms' => 0]],
        // System
        'system_maintenance' => ['label' => 'Scheduled Maintenance', 'category' => 'system',    'icon' => 'bi-tools',                  'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 0, 'sms' => 0]],
        'feature_update'     => ['label' => 'New Feature Update',    'category' => 'system',    'icon' => 'bi-stars',                  'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 0, 'push' => 0, 'sms' => 0]],
        'system_alert'       => ['label' => 'System Announcement',   'category' => 'system',    'icon' => 'bi-gear',                   'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 0, 'push' => 0, 'sms' => 0]],
        // Supplier-specific
        'new_order'          => ['label' => 'New Order Received',    'category' => 'supplier',  'icon' => 'bi-bag-plus',               'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 1, 'sms' => 0]],
        'order_cancellation' => ['label' => 'Order Cancellation',    'category' => 'supplier',  'icon' => 'bi-bag-x',                  'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 1, 'sms' => 0]],
        'low_stock_alert'    => ['label' => 'Low Stock Alert',       'category' => 'supplier',  'icon' => 'bi-exclamation-triangle',   'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 0, 'sms' => 0]],
        'review_received'    => ['label' => 'Review Received',       'category' => 'supplier',  'icon' => 'bi-star',                   'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 0, 'sms' => 0]],
        'product_approved'   => ['label' => 'Product Approved',      'category' => 'supplier',  'icon' => 'bi-check-circle',           'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 0, 'sms' => 0]],
        'product_rejected'   => ['label' => 'Product Rejected',      'category' => 'supplier',  'icon' => 'bi-slash-circle',           'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 0, 'sms' => 0]],
        'plan_expiry'        => ['label' => 'Plan Expiry Reminder',  'category' => 'supplier',  'icon' => 'bi-calendar-x',             'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 0, 'sms' => 0]],
        'api_usage_alert'    => ['label' => 'API Usage Alert',       'category' => 'supplier',  'icon' => 'bi-cloud-lightning',        'critical' => false, 'defaults' => ['in_app' => 1, 'email' => 1, 'push' => 0, 'sms' => 0]],
    ];
}

/**
 * Return the system default channel settings for all event types.
 *
 * Returns an associative array keyed by event_type, each value being:
 *   ['in_app' => 0|1, 'email' => 0|1, 'push' => 0|1, 'sms' => 0|1]
 */
function getDefaultPreferences(): array
{
    $defaults = [];
    foreach (getNotificationEventTypes() as $type => $meta) {
        $defaults[$type] = $meta['defaults'];
    }
    return $defaults;
}

/**
 * Return the set of event types that are critical (cannot be toggled off).
 */
function getCriticalEventTypes(): array
{
    $critical = [];
    foreach (getNotificationEventTypes() as $type => $meta) {
        if ($meta['critical']) {
            $critical[] = $type;
        }
    }
    return $critical;
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. Per-User Preference CRUD
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Get all notification preferences for a user.
 *
 * Missing rows are backfilled with system defaults so the caller always
 * receives a complete set for every known event type.
 *
 * Returns an array keyed by event_type:
 *   [ event_type => ['in_app' => 0|1, 'email' => 0|1, 'push' => 0|1, 'sms' => 0|1] ]
 */
function getPreferences(PDO $db, int $userId): array
{
    $defaults = getDefaultPreferences();
    $result   = $defaults; // start from defaults

    try {
        $stmt = $db->prepare(
            'SELECT event_type, channel_in_app, channel_email, channel_push, channel_sms
               FROM notification_preferences
              WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[$row['event_type']] = [
                'in_app' => (int) $row['channel_in_app'],
                'email'  => (int) $row['channel_email'],
                'push'   => (int) $row['channel_push'],
                'sms'    => (int) $row['channel_sms'],
            ];
        }
    } catch (PDOException $e) {
        error_log('getPreferences error: ' . $e->getMessage());
    }

    // Enforce critical notification constraints
    foreach (getCriticalEventTypes() as $type) {
        if (isset($result[$type])) {
            $result[$type]['in_app'] = 1;
            $result[$type]['email']  = 1;
        }
    }

    return $result;
}

/**
 * Update a single preference channel for a user.
 *
 * Critical event types always keep in_app=1 and email=1 regardless of $enabled.
 *
 * @param  PDO    $db
 * @param  int    $userId
 * @param  string $eventType  e.g. 'order_placed'
 * @param  string $channel    'in_app' | 'email' | 'push' | 'sms'
 * @param  bool   $enabled
 * @return bool
 */
function updatePreference(PDO $db, int $userId, string $eventType, string $channel, bool $enabled): bool
{
    $allowedChannels = ['in_app', 'email', 'push', 'sms'];
    if (!in_array($channel, $allowedChannels, true)) {
        return false;
    }

    $criticals = getCriticalEventTypes();
    // Critical events: in_app and email cannot be disabled
    if (in_array($eventType, $criticals, true) && in_array($channel, ['in_app', 'email'], true)) {
        $enabled = true;
    }

    $colMap = [
        'in_app' => 'channel_in_app',
        'email'  => 'channel_email',
        'push'   => 'channel_push',
        'sms'    => 'channel_sms',
    ];
    $col = $colMap[$channel];

    try {
        // Fetch existing row to preserve other channels (if any)
        $existStmt = $db->prepare(
            'SELECT channel_in_app, channel_email, channel_push, channel_sms
               FROM notification_preferences
              WHERE user_id = ? AND event_type = ? LIMIT 1'
        );
        $existStmt->execute([$userId, $eventType]);
        $existing = $existStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Row exists: update only the target column
            $db->prepare(
                "UPDATE notification_preferences SET {$col} = ?, updated_at = NOW()
                  WHERE user_id = ? AND event_type = ?"
            )->execute([(int) $enabled, $userId, $eventType]);
        } else {
            // No row yet: insert with defaults, then apply the target channel
            $defaults = getDefaultPreferences();
            $def      = $defaults[$eventType] ?? ['in_app' => 1, 'email' => 1, 'push' => 0, 'sms' => 0];
            $def[$channel] = (int) $enabled;

            // Re-apply critical constraints
            if (in_array($eventType, $criticals, true)) {
                $def['in_app'] = 1;
                $def['email']  = 1;
            }

            $db->prepare(
                'INSERT INTO notification_preferences (user_id, event_type, channel_in_app, channel_email, channel_push, channel_sms)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$userId, $eventType, $def['in_app'], $def['email'], $def['push'], $def['sms']]);
        }
        return true;
    } catch (PDOException $e) {
        error_log('updatePreference error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Bulk-update preferences for a user.
 *
 * @param PDO   $db
 * @param int   $userId
 * @param array $preferences  Keyed by event_type, each value:
 *                            ['in_app'=>0|1,'email'=>0|1,'push'=>0|1,'sms'=>0|1]
 * @return bool
 */
function updateBulkPreferences(PDO $db, int $userId, array $preferences): bool
{
    if (empty($preferences)) {
        return true;
    }

    $criticals = getCriticalEventTypes();

    try {
        $stmt = $db->prepare(
            'INSERT INTO notification_preferences
                (user_id, event_type, channel_in_app, channel_email, channel_push, channel_sms)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                channel_in_app = VALUES(channel_in_app),
                channel_email  = VALUES(channel_email),
                channel_push   = VALUES(channel_push),
                channel_sms    = VALUES(channel_sms),
                updated_at     = NOW()'
        );

        $db->beginTransaction();
        foreach ($preferences as $eventType => $channels) {
            $inApp = (int) ($channels['in_app'] ?? 1);
            $email = (int) ($channels['email']  ?? 1);
            $push  = (int) ($channels['push']   ?? 0);
            $sms   = (int) ($channels['sms']    ?? 0);

            // Enforce critical constraints
            if (in_array($eventType, $criticals, true)) {
                $inApp = 1;
                $email = 1;
            }

            $stmt->execute([$userId, $eventType, $inApp, $email, $push, $sms]);
        }
        $db->commit();
        return true;
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('updateBulkPreferences error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Reset a user's preferences back to system defaults.
 *
 * @return bool
 */
function resetToDefaults(PDO $db, int $userId): bool
{
    try {
        $db->prepare('DELETE FROM notification_preferences WHERE user_id = ?')
           ->execute([$userId]);
        return true;
    } catch (PDOException $e) {
        error_log('resetToDefaults error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Check whether a specific channel is enabled for a user + event type.
 * Falls back to system defaults if no row exists.
 *
 * @param  string $channel  'in_app' | 'email' | 'push' | 'sms'
 */
function isNotificationEnabled(PDO $db, int $userId, string $eventType, string $channel): bool
{
    $prefs = getPreferences($db, $userId);
    return (bool) ($prefs[$eventType][$channel] ?? false);
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. System Messages (Admin Broadcast)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Create (broadcast) a system message.
 *
 * @param PDO    $db
 * @param string $title
 * @param string $body
 * @param string $type        'maintenance'|'feature_update'|'policy_change'|'promotion'|'security_alert'
 * @param string $priority    'critical'|'warning'|'info'
 * @param array  $targetRoles e.g. ['buyer','supplier'] — empty array = all users
 * @param int    $createdBy   Admin user ID
 * @param string|null $startsAt   ISO datetime, null = now
 * @param string|null $expiresAt  ISO datetime, null = never expires
 * @return int   Inserted message ID, or 0 on failure
 */
function sendSystemMessage(
    PDO $db,
    string $title,
    string $body,
    string $type = 'feature_update',
    string $priority = 'info',
    array $targetRoles = [],
    int $createdBy = 0,
    ?string $startsAt = null,
    ?string $expiresAt = null
): int {
    $allowedTypes     = ['maintenance', 'feature_update', 'policy_change', 'promotion', 'security_alert'];
    $allowedPriority  = ['critical', 'warning', 'info'];

    if (!in_array($type, $allowedTypes, true)) {
        $type = 'feature_update';
    }
    if (!in_array($priority, $allowedPriority, true)) {
        $priority = 'info';
    }

    try {
        $stmt = $db->prepare(
            'INSERT INTO system_messages
                (title, body, type, priority, target_roles_json, starts_at, expires_at, is_active, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)'
        );
        $stmt->execute([
            $title,
            $body,
            $type,
            $priority,
            json_encode(array_values($targetRoles)),
            $startsAt  ?? date('Y-m-d H:i:s'),
            $expiresAt,
            $createdBy,
        ]);
        return (int) $db->lastInsertId();
    } catch (PDOException $e) {
        error_log('sendSystemMessage error: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Get active, non-expired, non-dismissed system messages for a user.
 *
 * @param PDO    $db
 * @param int    $userId
 * @param string $userRole  e.g. 'buyer', 'supplier', 'admin'
 * @param int    $page
 * @param int    $perPage
 * @return array ['data' => [...], 'total' => int]
 */
function getSystemMessages(PDO $db, int $userId, string $userRole = '', int $page = 1, int $perPage = 20): array
{
    try {
        $offset = ($page - 1) * $perPage;
        $now    = date('Y-m-d H:i:s');

        $sql = "SELECT sm.*
                  FROM system_messages sm
                 WHERE sm.is_active = 1
                   AND sm.starts_at <= ?
                   AND (sm.expires_at IS NULL OR sm.expires_at >= ?)
                   AND sm.id NOT IN (
                       SELECT message_id FROM system_message_dismissals WHERE user_id = ?
                   )
                 ORDER BY
                   FIELD(sm.priority, 'critical', 'warning', 'info'),
                   sm.starts_at DESC
                 LIMIT ? OFFSET ?";

        $stmt = $db->prepare($sql);
        $stmt->execute([$now, $now, $userId, $perPage, $offset]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Filter by target role if message targets specific roles
        if ($userRole !== '') {
            $rows = array_values(array_filter($rows, static function (array $msg) use ($userRole): bool {
                $targets = json_decode($msg['target_roles_json'] ?? '[]', true);
                return empty($targets) || in_array($userRole, $targets, true);
            }));
        }

        // Count total
        $countStmt = $db->prepare(
            "SELECT COUNT(*) FROM system_messages
              WHERE is_active = 1
                AND starts_at <= ?
                AND (expires_at IS NULL OR expires_at >= ?)
                AND id NOT IN (SELECT message_id FROM system_message_dismissals WHERE user_id = ?)"
        );
        $countStmt->execute([$now, $now, $userId]);
        $total = (int) $countStmt->fetchColumn();

        return ['data' => $rows, 'total' => $total];
    } catch (PDOException $e) {
        error_log('getSystemMessages error: ' . $e->getMessage());
        return ['data' => [], 'total' => 0];
    }
}

/**
 * Get all system messages for admin management (no dismissal filter).
 *
 * @return array
 */
function getAllSystemMessages(PDO $db, int $page = 1, int $perPage = 20): array
{
    try {
        $offset = ($page - 1) * $perPage;
        $stmt   = $db->prepare(
            'SELECT sm.*,
                    (SELECT COUNT(*) FROM system_message_dismissals d WHERE d.message_id = sm.id) AS dismiss_count
               FROM system_messages sm
              ORDER BY sm.created_at DESC
              LIMIT ? OFFSET ?'
        );
        $stmt->execute([$perPage, $offset]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = (int) $db->query('SELECT COUNT(*) FROM system_messages')->fetchColumn();

        return ['data' => $rows, 'total' => $total];
    } catch (PDOException $e) {
        error_log('getAllSystemMessages error: ' . $e->getMessage());
        return ['data' => [], 'total' => 0];
    }
}

/**
 * Dismiss a system message for a user.
 *
 * @return bool
 */
function dismissSystemMessage(PDO $db, int $messageId, int $userId): bool
{
    try {
        $db->prepare(
            'INSERT IGNORE INTO system_message_dismissals (message_id, user_id) VALUES (?, ?)'
        )->execute([$messageId, $userId]);
        return true;
    } catch (PDOException $e) {
        error_log('dismissSystemMessage error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update an existing system message.
 *
 * @return bool
 */
function updateSystemMessage(PDO $db, int $messageId, array $data): bool
{
    $allowedFields = ['title', 'body', 'type', 'priority', 'target_roles_json', 'starts_at', 'expires_at', 'is_active'];
    $sets          = [];
    $params        = [];

    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $data)) {
            $sets[]   = "{$field} = ?";
            $params[] = $data[$field];
        }
    }

    if (empty($sets)) {
        return false;
    }

    $params[] = $messageId;

    try {
        $db->prepare(
            'UPDATE system_messages SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ?'
        )->execute($params);
        return true;
    } catch (PDOException $e) {
        error_log('updateSystemMessage error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete (hard-delete) a system message and its dismissals.
 *
 * @return bool
 */
function deleteSystemMessage(PDO $db, int $messageId): bool
{
    try {
        $db->prepare('DELETE FROM system_message_dismissals WHERE message_id = ?')->execute([$messageId]);
        $db->prepare('DELETE FROM system_messages WHERE id = ?')->execute([$messageId]);
        return true;
    } catch (PDOException $e) {
        error_log('deleteSystemMessage error: ' . $e->getMessage());
        return false;
    }
}
