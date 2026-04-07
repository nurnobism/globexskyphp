<?php
/**
 * includes/orders.php — Order Management Library (PR #7)
 */

/**
 * Get order with items and address. Optionally verify ownership.
 */
function getOrder(PDO $db, int $orderId, int $userId = 0, string $role = 'buyer'): ?array
{
    $stmt = $db->prepare(
        'SELECT o.*,
                u.first_name, u.last_name, u.email AS buyer_email,
                u.phone AS buyer_phone
         FROM orders o
         JOIN users u ON u.id = o.buyer_id
         WHERE o.id = ?'
    );
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        return null;
    }

    if ($userId > 0 && $role !== 'admin') {
        if ($role === 'buyer') {
            if ((int)$order['buyer_id'] !== $userId) {
                return null;
            }
        } elseif ($role === 'supplier') {
            $chk = $db->prepare(
                'SELECT COUNT(*) FROM order_items oi
                 JOIN products p ON p.id = oi.product_id
                 WHERE oi.order_id = ? AND p.supplier_id = ?'
            );
            $chk->execute([$orderId, $userId]);
            if ((int)$chk->fetchColumn() === 0) {
                return null;
            }
        }
    }

    $order['items']   = getOrderItems($db, $orderId);
    $order['tracking'] = getTrackingInfo($db, $orderId);

    // Decode JSON fields
    if (!empty($order['shipping_address']) && is_string($order['shipping_address'])) {
        $order['shipping_address'] = json_decode($order['shipping_address'], true) ?? [];
    }
    if (!empty($order['billing_address']) && is_string($order['billing_address'])) {
        $order['billing_address'] = json_decode($order['billing_address'], true) ?? [];
    }

    return $order;
}

/**
 * Buyer's order list with filtering and pagination.
 */
function getBuyerOrders(PDO $db, int $buyerId, array $filters = [], int $page = 1, int $perPage = 15): array
{
    $where  = ['o.buyer_id = ?'];
    $params = [$buyerId];

    if (!empty($filters['status'])) {
        $where[]  = 'o.status = ?';
        $params[] = $filters['status'];
    }
    if (!empty($filters['date_from'])) {
        $where[]  = 'o.placed_at >= ?';
        $params[] = $filters['date_from'] . ' 00:00:00';
    }
    if (!empty($filters['date_to'])) {
        $where[]  = 'o.placed_at <= ?';
        $params[] = $filters['date_to'] . ' 23:59:59';
    }
    if (!empty($filters['search'])) {
        $where[]  = '(o.order_number LIKE ? OR oi_search.product_name LIKE ?)';
        $params[] = '%' . $filters['search'] . '%';
        $params[] = '%' . $filters['search'] . '%';
    }

    $joinSearch = !empty($filters['search'])
        ? 'LEFT JOIN order_items oi_search ON oi_search.order_id = o.id' : '';

    $sql = "SELECT o.id, o.order_number, o.status, o.payment_status, o.payment_method,
                   o.subtotal, o.shipping_fee, o.tax, o.discount, o.total, o.currency,
                   o.placed_at, o.confirmed_at, o.shipped_at, o.delivered_at, o.cancelled_at,
                   o.coupon_code, o.notes,
                   COUNT(DISTINCT oi.id) AS item_count
            FROM orders o
            LEFT JOIN order_items oi ON oi.order_id = o.id
            $joinSearch
            WHERE " . implode(' AND ', $where) . "
            GROUP BY o.id
            ORDER BY o.placed_at DESC";

    return paginate($db, $sql, $params, $page, $perPage);
}

/**
 * Supplier's order list (orders containing the supplier's products).
 */
function getSupplierOrders(PDO $db, int $supplierId, array $filters = [], int $page = 1, int $perPage = 15): array
{
    $where  = ['p.supplier_id = ?'];
    $params = [$supplierId];

    if (!empty($filters['status'])) {
        $where[]  = 'o.status = ?';
        $params[] = $filters['status'];
    }
    if (!empty($filters['date_from'])) {
        $where[]  = 'o.placed_at >= ?';
        $params[] = $filters['date_from'] . ' 00:00:00';
    }
    if (!empty($filters['date_to'])) {
        $where[]  = 'o.placed_at <= ?';
        $params[] = $filters['date_to'] . ' 23:59:59';
    }
    if (!empty($filters['search'])) {
        $where[]  = '(o.order_number LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)';
        $s        = '%' . $filters['search'] . '%';
        $params   = array_merge($params, [$s, $s, $s, $s]);
    }

    $sql = "SELECT o.id, o.order_number, o.status, o.payment_status, o.payment_method,
                   o.total, o.placed_at, o.confirmed_at, o.shipped_at, o.delivered_at,
                   u.first_name, u.last_name, u.email AS buyer_email,
                   COUNT(DISTINCT oi.id) AS item_count,
                   SUM(oi.total_price) AS supplier_subtotal
            FROM orders o
            JOIN order_items oi ON oi.order_id = o.id
            JOIN products p ON p.id = oi.product_id
            JOIN users u ON u.id = o.buyer_id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY o.id
            ORDER BY o.placed_at DESC";

    return paginate($db, $sql, $params, $page, $perPage);
}

/**
 * Admin: all platform orders.
 */
function getAdminOrders(PDO $db, array $filters = [], int $page = 1, int $perPage = 20): array
{
    $where  = ['1=1'];
    $params = [];

    if (!empty($filters['status'])) {
        $where[]  = 'o.status = ?';
        $params[] = $filters['status'];
    }
    if (!empty($filters['payment_method'])) {
        $where[]  = 'o.payment_method = ?';
        $params[] = $filters['payment_method'];
    }
    if (!empty($filters['supplier_id'])) {
        $where[]  = 'EXISTS (SELECT 1 FROM order_items oi2 JOIN products p2 ON p2.id = oi2.product_id WHERE oi2.order_id = o.id AND p2.supplier_id = ?)';
        $params[] = (int)$filters['supplier_id'];
    }
    if (!empty($filters['buyer_id'])) {
        $where[]  = 'o.buyer_id = ?';
        $params[] = (int)$filters['buyer_id'];
    }
    if (!empty($filters['date_from'])) {
        $where[]  = 'o.placed_at >= ?';
        $params[] = $filters['date_from'] . ' 00:00:00';
    }
    if (!empty($filters['date_to'])) {
        $where[]  = 'o.placed_at <= ?';
        $params[] = $filters['date_to'] . ' 23:59:59';
    }
    if (!empty($filters['search'])) {
        $where[]  = '(o.order_number LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)';
        $s        = '%' . $filters['search'] . '%';
        $params   = array_merge($params, [$s, $s, $s, $s]);
    }

    $sql = "SELECT o.id, o.order_number, o.status, o.payment_status, o.payment_method,
                   o.subtotal, o.shipping_fee, o.tax, o.discount, o.total, o.currency,
                   o.placed_at, o.confirmed_at, o.shipped_at, o.delivered_at, o.cancelled_at,
                   u.first_name, u.last_name, u.email AS buyer_email,
                   COUNT(DISTINCT oi.id) AS item_count
            FROM orders o
            JOIN users u ON u.id = o.buyer_id
            LEFT JOIN order_items oi ON oi.order_id = o.id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY o.id
            ORDER BY o.placed_at DESC";

    return paginate($db, $sql, $params, $page, $perPage);
}

/**
 * Get all items for an order with product info.
 */
function getOrderItems(PDO $db, int $orderId): array
{
    $stmt = $db->prepare(
        'SELECT oi.*,
                p.slug AS product_slug,
                p.images AS product_images,
                p.supplier_id
         FROM order_items oi
         LEFT JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id = ?
         ORDER BY oi.id'
    );
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll();

    foreach ($items as &$item) {
        if (!empty($item['attributes']) && is_string($item['attributes'])) {
            $item['attributes'] = json_decode($item['attributes'], true) ?? [];
        }
    }
    unset($item);

    return $items;
}

/**
 * Count orders for a user or all (admin).
 */
function getOrderCount(PDO $db, int $userId, string $role, string $status = ''): int
{
    if ($role === 'admin') {
        $where  = ['1=1'];
        $params = [];
    } elseif ($role === 'supplier') {
        $where  = ['p.supplier_id = ?'];
        $params = [$userId];
    } else {
        $where  = ['o.buyer_id = ?'];
        $params = [$userId];
    }

    if ($status !== '') {
        $where[]  = 'o.status = ?';
        $params[] = $status;
    }

    if ($role === 'supplier') {
        $sql = 'SELECT COUNT(DISTINCT o.id) FROM orders o
                JOIN order_items oi ON oi.order_id = o.id
                JOIN products p ON p.id = oi.product_id
                WHERE ' . implode(' AND ', $where);
    } else {
        $sql = 'SELECT COUNT(*) FROM orders o WHERE ' . implode(' AND ', $where);
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

/**
 * Update order status with role-based validation.
 */
function updateOrderStatus(PDO $db, int $orderId, string $newStatus, int $userId, string $role, string $note = ''): array
{
    $stmt = $db->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        return ['success' => false, 'message' => 'Order not found.'];
    }

    $currentStatus = $order['status'];

    // Validate transition
    $valid = getValidStatusTransitions($currentStatus, $role);
    if (!in_array($newStatus, $valid, true)) {
        return [
            'success' => false,
            'message' => "Cannot transition from '$currentStatus' to '$newStatus' as $role.",
        ];
    }

    // Determine timestamp column to update
    $tsCol = match ($newStatus) {
        'confirmed'  => 'confirmed_at',
        'shipped'    => 'shipped_at',
        'delivered'  => 'delivered_at',
        'cancelled'  => 'cancelled_at',
        default      => null,
    };

    if ($tsCol) {
        $db->prepare("UPDATE orders SET status = ?, $tsCol = NOW(), updated_at = NOW() WHERE id = ?")
           ->execute([$newStatus, $orderId]);
    } else {
        $db->prepare('UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?')
           ->execute([$newStatus, $orderId]);
    }

    // Log to history
    $db->prepare(
        'INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, note, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())'
    )->execute([$orderId, $currentStatus, $newStatus, $userId, $note]);

    // Notify buyer
    _notifyOrderStatusChange($db, $order, $newStatus);

    return ['success' => true, 'message' => 'Order status updated to ' . $newStatus . '.'];
}

/**
 * Internal: send notification on status change.
 */
function _notifyOrderStatusChange(PDO $db, array $order, string $newStatus): void
{
    $messages = [
        'confirmed'  => 'Your order ' . $order['order_number'] . ' has been confirmed.',
        'processing' => 'Your order ' . $order['order_number'] . ' is now being processed.',
        'shipped'    => 'Your order ' . $order['order_number'] . ' has been shipped.',
        'delivered'  => 'Your order ' . $order['order_number'] . ' has been delivered.',
        'cancelled'  => 'Your order ' . $order['order_number'] . ' has been cancelled.',
        'refunded'   => 'A refund has been issued for order ' . $order['order_number'] . '.',
    ];

    $typeMap = [
        'confirmed'  => 'order_confirmed',
        'processing' => 'order_processing',
        'shipped'    => 'order_shipped',
        'delivered'  => 'order_delivered',
        'cancelled'  => 'order_cancelled',
        'refunded'   => 'order_refunded',
    ];

    if (!isset($messages[$newStatus])) {
        return;
    }

    createNotification(
        $db,
        (int)$order['buyer_id'],
        $typeMap[$newStatus] ?? 'order_update',
        'Order ' . ucfirst($newStatus),
        $messages[$newStatus],
        ['order_id' => $order['id']],
        'normal',
        '/pages/account/orders/detail.php?order_id=' . $order['id']
    );
}

/**
 * Cancel an order (buyer or admin).
 */
function cancelOrder(PDO $db, int $orderId, int $userId, string $reason = ''): array
{
    $stmt = $db->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        return ['success' => false, 'message' => 'Order not found.'];
    }

    if (!in_array($order['status'], ['pending', 'confirmed'], true)) {
        return ['success' => false, 'message' => 'Order cannot be cancelled at this stage.'];
    }

    $db->prepare('UPDATE orders SET status = ?, cancelled_at = NOW(), updated_at = NOW() WHERE id = ?')
       ->execute(['cancelled', $orderId]);

    $db->prepare(
        'INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, note, created_at)
         VALUES (?, ?, "cancelled", ?, ?, NOW())'
    )->execute([$orderId, $order['status'], $userId, $reason]);

    // Notify buyer
    createNotification(
        $db,
        (int)$order['buyer_id'],
        'order_cancelled',
        'Order Cancelled',
        'Your order ' . $order['order_number'] . ' has been cancelled.',
        ['order_id' => $orderId],
        'normal',
        '/pages/account/orders/detail.php?order_id=' . $orderId
    );

    return ['success' => true, 'message' => 'Order cancelled successfully.'];
}

/**
 * Get status change timeline for an order.
 */
function getStatusHistory(PDO $db, int $orderId): array
{
    $stmt = $db->prepare(
        'SELECT osh.*, u.first_name, u.last_name
         FROM order_status_history osh
         LEFT JOIN users u ON u.id = osh.changed_by
         WHERE osh.order_id = ?
         ORDER BY osh.created_at ASC'
    );
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}

/**
 * Supplier adds shipping / tracking info and marks order as shipped.
 */
function addTrackingInfo(PDO $db, int $orderId, int $supplierId, string $carrier, string $trackingNumber, string $trackingUrl = ''): array
{
    // Verify supplier owns items in this order
    $chk = $db->prepare(
        'SELECT COUNT(*) FROM order_items oi
         JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id = ? AND p.supplier_id = ?'
    );
    $chk->execute([$orderId, $supplierId]);
    if ((int)$chk->fetchColumn() === 0) {
        return ['success' => false, 'message' => 'Order not found or access denied.'];
    }

    $db->prepare(
        'INSERT INTO order_tracking (order_id, carrier, tracking_number, tracking_url, created_at)
         VALUES (?, ?, ?, ?, NOW())'
    )->execute([$orderId, $carrier, $trackingNumber, $trackingUrl]);

    // Auto-update status to shipped
    $stmt = $db->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if ($order && in_array($order['status'], ['processing', 'confirmed', 'pending'], true)) {
        $db->prepare('UPDATE orders SET status = "shipped", shipped_at = NOW(), updated_at = NOW() WHERE id = ?')
           ->execute([$orderId]);

        $db->prepare(
            'INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, note, created_at)
             VALUES (?, ?, "shipped", ?, ?, NOW())'
        )->execute([$orderId, $order['status'], $supplierId, 'Tracking added: ' . $carrier . ' ' . $trackingNumber]);

        _notifyOrderStatusChange($db, $order, 'shipped');
    }

    return ['success' => true, 'message' => 'Tracking information added and order marked as shipped.'];
}

/**
 * Get latest tracking details for an order.
 */
function getTrackingInfo(PDO $db, int $orderId): ?array
{
    $stmt = $db->prepare(
        'SELECT * FROM order_tracking WHERE order_id = ? ORDER BY created_at DESC LIMIT 1'
    );
    $stmt->execute([$orderId]);
    return $stmt->fetch() ?: null;
}

/**
 * Dashboard stats per role.
 */
function getOrderStats(PDO $db, int $userId, string $role): array
{
    if ($role === 'buyer') {
        $stmt = $db->prepare(
            'SELECT
               COUNT(*) AS total_orders,
               SUM(status = "pending")   AS pending_count,
               SUM(status = "shipped")   AS shipped_count,
               SUM(status = "delivered") AS delivered_count
             FROM orders WHERE buyer_id = ?'
        );
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: [];
    }

    if ($role === 'supplier') {
        $stmt = $db->prepare(
            'SELECT
               COUNT(DISTINCT o.id) AS total_orders,
               SUM(DATE(o.placed_at) = CURDATE())    AS new_today,
               SUM(o.status = "processing")           AS processing_count,
               SUM(o.status = "shipped")              AS shipped_count,
               COALESCE(SUM(CASE WHEN MONTH(o.placed_at) = MONTH(NOW()) AND YEAR(o.placed_at) = YEAR(NOW())
                              AND o.payment_status = "paid" THEN oi.total_price ELSE 0 END), 0) AS revenue_month
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             JOIN products p ON p.id = oi.product_id
             WHERE p.supplier_id = ?'
        );
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: [];
    }

    // admin
    $stmt = $db->prepare(
        'SELECT
           COUNT(*) AS total_orders,
           SUM(DATE(placed_at) = CURDATE())  AS orders_today,
           COALESCE(SUM(CASE WHEN DATE(placed_at) = CURDATE() AND payment_status = "paid" THEN total ELSE 0 END), 0) AS revenue_today,
           COALESCE(SUM(CASE WHEN MONTH(placed_at) = MONTH(NOW()) AND YEAR(placed_at) = YEAR(NOW())
                          AND payment_status = "paid" THEN total ELSE 0 END), 0) AS revenue_month,
           SUM(status = "pending") AS pending_count
         FROM orders'
    );
    $stmt->execute();
    return $stmt->fetch() ?: [];
}

/**
 * Revenue breakdown by period for a supplier.
 */
function getRevenueStats(PDO $db, int $supplierId, string $period = 'monthly'): array
{
    if ($period === 'daily') {
        $groupBy  = 'DATE(o.placed_at)';
        $label    = 'DATE(o.placed_at) AS period_label';
        $interval = 'INTERVAL 30 DAY';
    } else {
        $groupBy  = 'YEAR(o.placed_at), MONTH(o.placed_at)';
        $label    = 'DATE_FORMAT(o.placed_at, "%Y-%m") AS period_label';
        $interval = 'INTERVAL 12 MONTH';
    }

    $stmt = $db->prepare(
        "SELECT $label,
                SUM(oi.total_price) AS revenue,
                COUNT(DISTINCT o.id) AS order_count
         FROM orders o
         JOIN order_items oi ON oi.order_id = o.id
         JOIN products p ON p.id = oi.product_id
         WHERE p.supplier_id = ? AND o.payment_status = 'paid'
           AND o.placed_at >= DATE_SUB(NOW(), $interval)
         GROUP BY $groupBy
         ORDER BY period_label ASC"
    );
    $stmt->execute([$supplierId]);
    return $stmt->fetchAll();
}

/**
 * Add a note to an order.
 */
function addOrderNote(PDO $db, int $orderId, int $userId, string $note, bool $isInternal = false): int
{
    $stmt = $db->prepare(
        'INSERT INTO order_notes (order_id, user_id, note, is_internal, created_at)
         VALUES (?, ?, ?, ?, NOW())'
    );
    $stmt->execute([$orderId, $userId, $note, $isInternal ? 1 : 0]);
    return (int)$db->lastInsertId();
}

/**
 * Get notes for an order, optionally including internal ones.
 */
function getOrderNotes(PDO $db, int $orderId, bool $includeInternal = false): array
{
    $sql = "SELECT on2.*, u.first_name, u.last_name, u.role AS user_role
            FROM order_notes on2
            LEFT JOIN users u ON u.id = on2.user_id
            WHERE on2.order_id = ?";
    if (!$includeInternal) {
        $sql .= ' AND on2.is_internal = 0';
    }
    $sql .= ' ORDER BY on2.created_at ASC';

    $stmt = $db->prepare($sql);
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}

/**
 * Returns Bootstrap badge color class for an order status.
 */
function getOrderStatusBadgeClass(string $status): string
{
    return match ($status) {
        'pending'    => 'warning',
        'confirmed'  => 'info',
        'processing' => 'info',
        'shipped'    => 'primary',
        'delivered'  => 'success',
        'cancelled'  => 'danger',
        'refunded'   => 'secondary',
        default      => 'secondary',
    };
}

/**
 * Returns valid next statuses for a given current status and role.
 */
function getValidStatusTransitions(string $currentStatus, string $role): array
{
    $transitions = [
        'admin' => [
            'pending'    => ['confirmed', 'cancelled'],
            'confirmed'  => ['processing', 'cancelled'],
            'processing' => ['shipped', 'refunded'],
            'shipped'    => ['delivered', 'refunded'],
            'delivered'  => ['refunded'],
            'paid'       => ['processing', 'refunded'],
            'cancelled'  => [],
            'refunded'   => [],
        ],
        'supplier' => [
            'confirmed'  => ['processing'],
            'paid'       => ['processing'],
            'processing' => ['shipped'],
            'pending'    => [],
            'shipped'    => [],
            'delivered'  => [],
            'cancelled'  => [],
            'refunded'   => [],
        ],
        'buyer' => [
            'pending'    => ['cancelled'],
            'confirmed'  => ['cancelled'],
            'shipped'    => ['delivered'],
            'processing' => [],
            'delivered'  => [],
            'cancelled'  => [],
            'refunded'   => [],
        ],
    ];

    if ($role === 'super_admin') {
        $role = 'admin';
    }

    return $transitions[$role][$currentStatus] ?? [];
}

/**
 * Mask an email address: joh***@example.com
 */
function maskEmail(string $email): string
{
    [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
    $visible = mb_substr($local, 0, min(3, mb_strlen($local)));
    return $visible . '***@' . $domain;
}
