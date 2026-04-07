<?php
/**
 * includes/payouts.php — Payout Management Library (PR #11)
 *
 * Supplier Withdrawals — 7-day hold, Bank/PayPal/Wise, Admin approval queue.
 *
 * Hold period: orders delivered 7+ days ago have released funds.
 * Minimum payout: $50.
 * Methods: bank_transfer, paypal, wise.
 */

// ── Constants ────────────────────────────────────────────────────────────────

define('PAYOUT_MIN_AMOUNT',  50.00);
define('PAYOUT_HOLD_DAYS',   7);
define('PAYOUT_VALID_METHODS', ['bank_transfer', 'paypal', 'wise']);

// ── Earnings Calculation ─────────────────────────────────────────────────────

/**
 * Calculate a supplier's full balance breakdown.
 *
 * Returns:
 *   total_earned        — net earnings from completed/delivered orders (order total - commission)
 *   total_paid          — sum of completed payouts
 *   pending_payouts     — sum of pending/processing payout requests
 *   in_hold             — funds in 7-day hold (delivered, hold not yet expired)
 *   available_balance   — total_earned - total_paid - pending_payouts - in_hold
 */
function getSupplierBalance(int $supplierId): array
{
    $db = getDB();

    // --- Earnings from orders --------------------------------------------------
    // Try commission_logs first (most accurate — has net_amount per order)
    $totalEarned = 0.0;
    try {
        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(cl.net_amount), 0)
             FROM commission_logs cl
             JOIN orders o ON o.id = cl.order_id
             WHERE cl.supplier_id = ?
               AND o.status IN ("completed","delivered","shipped")'
        );
        $stmt->execute([$supplierId]);
        $totalEarned = (float)$stmt->fetchColumn();
    } catch (PDOException $e) {
        // Fallback: order_items subtotals minus commissions from orders columns
        try {
            $stmt = $db->prepare(
                'SELECT COALESCE(SUM(oi.subtotal - COALESCE(o.commission_amount, 0)), 0)
                 FROM order_items oi
                 JOIN orders o ON o.id = oi.order_id
                 JOIN products p ON p.id = oi.product_id
                 WHERE p.supplier_id = ?
                   AND o.status IN ("completed","delivered","shipped")'
            );
            $stmt->execute([$supplierId]);
            $totalEarned = (float)$stmt->fetchColumn();
        } catch (PDOException $e2) {
            $totalEarned = 0.0;
        }
    }

    // --- Total paid out -------------------------------------------------------
    $totalPaid = 0.0;
    try {
        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(amount), 0)
             FROM payout_requests
             WHERE supplier_id = ? AND status = "completed"'
        );
        $stmt->execute([$supplierId]);
        $totalPaid = (float)$stmt->fetchColumn();
    } catch (PDOException $e) { /* ignore */ }

    // --- Pending payouts (in queue) ------------------------------------------
    $pendingPayouts = 0.0;
    try {
        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(amount), 0)
             FROM payout_requests
             WHERE supplier_id = ? AND status IN ("pending","processing")'
        );
        $stmt->execute([$supplierId]);
        $pendingPayouts = (float)$stmt->fetchColumn();
    } catch (PDOException $e) { /* ignore */ }

    // --- Funds in 7-day hold -------------------------------------------------
    $inHold = 0.0;
    try {
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . PAYOUT_HOLD_DAYS . ' days'));
        $stmt   = $db->prepare(
            'SELECT COALESCE(SUM(cl.net_amount), 0)
             FROM commission_logs cl
             JOIN orders o ON o.id = cl.order_id
             WHERE cl.supplier_id = ?
               AND o.status = "delivered"
               AND o.hold_released_at IS NULL
               AND (o.delivered_at IS NULL OR o.delivered_at > ?)'
        );
        $stmt->execute([$supplierId, $cutoff]);
        $inHold = (float)$stmt->fetchColumn();
    } catch (PDOException $e) { /* ignore */ }

    $available = max(0.0, round($totalEarned - $totalPaid - $pendingPayouts - $inHold, 2));

    return [
        'total_earned'    => round($totalEarned, 2),
        'total_paid'      => round($totalPaid, 2),
        'pending_payouts' => round($pendingPayouts, 2),
        'in_hold'         => round($inHold, 2),
        'available_balance' => $available,
    ];
}

/**
 * Get earnings breakdown by period (daily / weekly / monthly).
 *
 * @param  int    $supplierId
 * @param  string $period    'daily'|'weekly'|'monthly'
 * @param  string $dateFrom  Y-m-d
 * @param  string $dateTo    Y-m-d
 * @return array  rows: date, orders_count, gross_revenue, commission, net_earnings
 */
function getEarningsBreakdown(int $supplierId, string $period = 'monthly', string $dateFrom = '', string $dateTo = ''): array
{
    $db = getDB();

    $groupFormat = match($period) {
        'daily'  => '%Y-%m-%d',
        'weekly' => '%x-W%v',
        default  => '%Y-%m',
    };

    if (!$dateFrom) $dateFrom = date('Y-m-d', strtotime('-90 days'));
    if (!$dateTo)   $dateTo   = date('Y-m-d');

    try {
        $stmt = $db->prepare(
            'SELECT
                DATE_FORMAT(cl.created_at, :fmt) AS period_label,
                COUNT(DISTINCT cl.order_id)       AS orders_count,
                COALESCE(SUM(cl.order_subtotal), 0) AS gross_revenue,
                COALESCE(SUM(cl.commission_amount), 0) AS commission,
                COALESCE(SUM(cl.net_amount), 0)  AS net_earnings
             FROM commission_logs cl
             WHERE cl.supplier_id = :sid
               AND DATE(cl.created_at) BETWEEN :dfrom AND :dto
             GROUP BY period_label
             ORDER BY MIN(cl.created_at) ASC'
        );
        $stmt->execute([
            ':fmt'   => $groupFormat,
            ':sid'   => $supplierId,
            ':dfrom' => $dateFrom,
            ':dto'   => $dateTo,
        ]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get orders currently in 7-day hold for a supplier.
 *
 * Returns: order_id, amount, delivered_at, hold_expires_at, days_remaining
 */
function getHoldingFunds(int $supplierId): array
{
    $db = getDB();
    try {
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . PAYOUT_HOLD_DAYS . ' days'));
        $stmt   = $db->prepare(
            'SELECT
                o.id AS order_id,
                cl.net_amount AS amount,
                o.delivered_at,
                DATE_ADD(COALESCE(o.delivered_at, o.updated_at), INTERVAL :days DAY) AS hold_expires_at,
                GREATEST(0,
                    DATEDIFF(DATE_ADD(COALESCE(o.delivered_at, o.updated_at), INTERVAL :days2 DAY), NOW())
                ) AS days_remaining
             FROM commission_logs cl
             JOIN orders o ON o.id = cl.order_id
             WHERE cl.supplier_id = :sid
               AND o.status = "delivered"
               AND o.hold_released_at IS NULL
               AND (o.delivered_at IS NULL OR o.delivered_at > :cutoff)
             ORDER BY hold_expires_at ASC'
        );
        $stmt->execute([
            ':days'   => PAYOUT_HOLD_DAYS,
            ':days2'  => PAYOUT_HOLD_DAYS,
            ':sid'    => $supplierId,
            ':cutoff' => $cutoff,
        ]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * When will a specific order's funds be released?
 * Returns ISO datetime string or null.
 */
function getHoldExpiryDate(int $orderId): ?string
{
    $db = getDB();
    try {
        $stmt = $db->prepare(
            'SELECT DATE_ADD(COALESCE(delivered_at, updated_at), INTERVAL :days DAY)
             FROM orders WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':days' => PAYOUT_HOLD_DAYS, ':id' => $orderId]);
        $v = $stmt->fetchColumn();
        return $v ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

// ── Payout Requests ──────────────────────────────────────────────────────────

/**
 * Validate account details for a given payout method.
 * Returns [] on success, or array of error strings.
 */
function validatePayoutAccountDetails(string $method, array $details): array
{
    $errors = [];
    switch ($method) {
        case 'bank_transfer':
            foreach (['account_name','bank_name','account_number','routing_number','country'] as $f) {
                if (empty($details[$f])) {
                    $errors[] = "Field '$f' is required for bank transfer.";
                }
            }
            break;
        case 'paypal':
            if (empty($details['email']) || !filter_var($details['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'A valid PayPal email address is required.';
            }
            break;
        case 'wise':
            if (empty($details['email']) && empty($details['account_id'])) {
                $errors[] = 'A Wise email or Account ID is required.';
            }
            if (!empty($details['email']) && !filter_var($details['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Wise email address is not valid.';
            }
            break;
        default:
            $errors[] = 'Unsupported payout method.';
    }
    return $errors;
}

/**
 * Submit a payout request.
 *
 * @param  int    $supplierId
 * @param  float  $amount
 * @param  string $method        bank_transfer | paypal | wise
 * @param  array  $accountDetails  validated account details
 * @param  int    $payoutMethodId  saved payout_methods.id (0 = ad-hoc)
 * @return array  ['success'=>true,'id'=>int] or ['success'=>false,'error'=>string]
 */
function requestPayout(int $supplierId, float $amount, string $method, array $accountDetails, int $payoutMethodId = 0): array
{
    $db = getDB();

    // Validate method
    if (!in_array($method, PAYOUT_VALID_METHODS, true)) {
        return ['success' => false, 'error' => 'Invalid payout method.'];
    }

    // Minimum amount
    $amount = round($amount, 2);
    if ($amount < PAYOUT_MIN_AMOUNT) {
        return ['success' => false, 'error' => 'Minimum payout amount is $' . number_format(PAYOUT_MIN_AMOUNT, 2) . '.'];
    }

    // Validate account details
    $detailErrors = validatePayoutAccountDetails($method, $accountDetails);
    if ($detailErrors) {
        return ['success' => false, 'error' => implode(' ', $detailErrors)];
    }

    // Validate available balance
    $balance = getSupplierBalance($supplierId);
    if ($amount > $balance['available_balance']) {
        return [
            'success' => false,
            'error'   => 'Amount exceeds available balance of $' . number_format($balance['available_balance'], 2) . '.',
        ];
    }

    // Sanitize account details
    $cleanDetails = [];
    foreach ($accountDetails as $k => $v) {
        $cleanDetails[htmlspecialchars($k, ENT_QUOTES, 'UTF-8')] =
            htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }

    try {
        $stmt = $db->prepare(
            'INSERT INTO payout_requests
                (supplier_id, amount, currency, payout_method, payout_method_id, payout_details, status, requested_at, created_at, updated_at)
             VALUES
                (:sid, :amt, "USD", :method, :mid, :details, "pending", NOW(), NOW(), NOW())'
        );
        $stmt->execute([
            ':sid'     => $supplierId,
            ':amt'     => $amount,
            ':method'  => $method,
            ':mid'     => $payoutMethodId ?: null,
            ':details' => json_encode($cleanDetails),
        ]);
        $requestId = (int)$db->lastInsertId();

        // Notify admin
        try {
            if (function_exists('createNotification')) {
                $admins = $db->query('SELECT id FROM users WHERE role IN ("admin","super_admin") AND is_active = 1 LIMIT 5');
                foreach ($admins->fetchAll() as $admin) {
                    createNotification(
                        $db,
                        (int)$admin['id'],
                        'payout_requested',
                        'New Payout Request',
                        'New payout request: $' . number_format($amount, 2) . ' from supplier #' . $supplierId,
                        ['payout_id' => $requestId, 'supplier_id' => $supplierId],
                        'normal',
                        '/pages/admin/finance/payout-detail.php?id=' . $requestId
                    );
                }
            }
        } catch (Exception $e) { /* ignore */ }

        return ['success' => true, 'id' => $requestId];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Cancel a pending payout request (supplier only).
 */
function cancelPayoutRequest(int $payoutId, int $supplierId): array
{
    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT * FROM payout_requests WHERE id = ? AND supplier_id = ?');
        $stmt->execute([$payoutId, $supplierId]);
        $req = $stmt->fetch();
        if (!$req) {
            return ['success' => false, 'error' => 'Payout request not found.'];
        }
        if ($req['status'] !== 'pending') {
            return ['success' => false, 'error' => 'Only pending requests can be cancelled.'];
        }
        $db->prepare(
            'UPDATE payout_requests SET status = "cancelled", cancelled_at = NOW(), updated_at = NOW() WHERE id = ?'
        )->execute([$payoutId]);
        return ['success' => true, 'message' => 'Payout request cancelled.'];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Get paginated payout request history for a supplier.
 */
function getPayoutRequests(int $supplierId, array $filters = [], int $page = 1, int $perPage = 20): array
{
    $db     = getDB();
    $where  = ['supplier_id = ?'];
    $params = [$supplierId];

    if (!empty($filters['status'])) {
        $where[]  = 'status = ?';
        $params[] = $filters['status'];
    }
    if (!empty($filters['method'])) {
        $where[]  = 'payout_method = ?';
        $params[] = $filters['method'];
    }
    if (!empty($filters['from'])) {
        $where[]  = 'DATE(created_at) >= ?';
        $params[] = $filters['from'];
    }
    if (!empty($filters['to'])) {
        $where[]  = 'DATE(created_at) <= ?';
        $params[] = $filters['to'];
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);
    $offset   = ($page - 1) * $perPage;

    try {
        $cStmt = $db->prepare("SELECT COUNT(*) FROM payout_requests $whereSql");
        $cStmt->execute($params);
        $total = (int)$cStmt->fetchColumn();

        $stmt = $db->prepare("SELECT * FROM payout_requests $whereSql ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return ['rows' => $rows, 'total' => $total, 'pages' => max(1, (int)ceil($total / $perPage))];
    } catch (PDOException $e) {
        return ['rows' => [], 'total' => 0, 'pages' => 1];
    }
}

/**
 * Get a single payout request detail.
 */
function getPayoutRequest(int $payoutId): ?array
{
    $db = getDB();
    try {
        $stmt = $db->prepare(
            'SELECT pr.*, u.email AS supplier_email, u.company_name AS supplier_company
             FROM payout_requests pr
             LEFT JOIN users u ON u.id = pr.supplier_id
             WHERE pr.id = ?'
        );
        $stmt->execute([$payoutId]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

// ── Admin Payout Processing ───────────────────────────────────────────────────

/**
 * Get all pending payout requests for the admin queue.
 */
function getPendingPayouts(int $page = 1, int $perPage = 20): array
{
    $db     = getDB();
    $offset = ($page - 1) * $perPage;
    try {
        $total = (int)$db->query("SELECT COUNT(*) FROM payout_requests WHERE status = 'pending'")->fetchColumn();
        $stmt  = $db->prepare(
            "SELECT pr.*, u.email AS supplier_email, u.company_name AS supplier_company
             FROM payout_requests pr
             LEFT JOIN users u ON u.id = pr.supplier_id
             WHERE pr.status = 'pending'
             ORDER BY pr.created_at ASC
             LIMIT $perPage OFFSET $offset"
        );
        $stmt->execute();
        return ['rows' => $stmt->fetchAll(), 'total' => $total, 'pages' => max(1, (int)ceil($total / $perPage))];
    } catch (PDOException $e) {
        return ['rows' => [], 'total' => 0, 'pages' => 1];
    }
}

/**
 * Admin approves a pending payout (moves to processing).
 */
function approvePayout(int $payoutId, int $adminId, string $transactionRef = ''): array
{
    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT * FROM payout_requests WHERE id = ?');
        $stmt->execute([$payoutId]);
        $req = $stmt->fetch();
        if (!$req) return ['success' => false, 'error' => 'Payout request not found.'];
        if ($req['status'] !== 'pending') return ['success' => false, 'error' => 'Only pending requests can be approved.'];

        $db->prepare(
            'UPDATE payout_requests
             SET status = "processing", processed_by = ?, transaction_ref = ?, approved_at = NOW(), updated_at = NOW()
             WHERE id = ?'
        )->execute([$adminId, $transactionRef, $payoutId]);

        // Notify supplier
        try {
            if (function_exists('createNotification')) {
                createNotification(
                    $db,
                    (int)$req['supplier_id'],
                    'payout_approved',
                    'Payout Approved',
                    'Your payout of $' . number_format((float)$req['amount'], 2) . ' has been approved and is being processed.',
                    ['payout_id' => $payoutId],
                    'high',
                    '/pages/supplier/earnings/history.php'
                );
            }
        } catch (Exception $e) { /* ignore */ }

        return ['success' => true, 'message' => 'Payout approved and set to processing.'];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Admin marks a payout as completed (money sent).
 */
function markPayoutCompleted(int $payoutId, int $adminId, string $transactionRef = ''): array
{
    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT * FROM payout_requests WHERE id = ?');
        $stmt->execute([$payoutId]);
        $req = $stmt->fetch();
        if (!$req) return ['success' => false, 'error' => 'Payout request not found.'];
        if ($req['status'] !== 'processing') return ['success' => false, 'error' => 'Only processing requests can be marked complete.'];

        $db->prepare(
            'UPDATE payout_requests
             SET status = "completed", reference_number = ?, transaction_ref = ?,
                 processed_by = ?, completed_at = NOW(), updated_at = NOW()
             WHERE id = ?'
        )->execute([$transactionRef, $transactionRef, $adminId, $payoutId]);

        // Log to supplier_earnings
        try {
            $db->prepare(
                'INSERT INTO supplier_earnings (supplier_id, type, amount, balance_after, description, reference_id, created_at)
                 VALUES (?, "payout", ?, 0, ?, ?, NOW())'
            )->execute([
                $req['supplier_id'],
                $req['amount'],
                'Payout completed #' . $payoutId,
                'payout_' . $payoutId,
            ]);
        } catch (PDOException $e) { /* ignore */ }

        // Notify supplier
        try {
            if (function_exists('createNotification')) {
                createNotification(
                    $db,
                    (int)$req['supplier_id'],
                    'payout_completed',
                    'Payout Sent',
                    'Your payout of $' . number_format((float)$req['amount'], 2) . ' has been sent via ' . $req['payout_method'] . '. Ref: ' . $transactionRef,
                    ['payout_id' => $payoutId, 'transaction_ref' => $transactionRef],
                    'high',
                    '/pages/supplier/earnings/history.php'
                );
            }
        } catch (Exception $e) { /* ignore */ }

        return ['success' => true, 'message' => 'Payout marked as completed.'];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Admin rejects a payout with a reason.
 */
function rejectPayout(int $payoutId, int $adminId, string $reason): array
{
    $db = getDB();
    if (!trim($reason)) {
        return ['success' => false, 'error' => 'A rejection reason is required.'];
    }
    try {
        $stmt = $db->prepare('SELECT * FROM payout_requests WHERE id = ?');
        $stmt->execute([$payoutId]);
        $req = $stmt->fetch();
        if (!$req) return ['success' => false, 'error' => 'Payout request not found.'];
        if ($req['status'] === 'completed') return ['success' => false, 'error' => 'Cannot reject a completed payout.'];

        $db->prepare(
            'UPDATE payout_requests
             SET status = "rejected", rejection_reason = ?, admin_note = ?,
                 processed_by = ?, rejected_at = NOW(), updated_at = NOW()
             WHERE id = ?'
        )->execute([$reason, $reason, $adminId, $payoutId]);

        // Reverse any supplier_earnings entry
        try {
            $db->prepare("DELETE FROM supplier_earnings WHERE reference_id = ? AND type = 'payout'")->execute(['payout_' . $payoutId]);
        } catch (PDOException $e) { /* ignore */ }

        // Notify supplier
        try {
            if (function_exists('createNotification')) {
                createNotification(
                    $db,
                    (int)$req['supplier_id'],
                    'payout_rejected',
                    'Payout Rejected',
                    'Your payout request of $' . number_format((float)$req['amount'], 2) . ' was rejected. Reason: ' . $reason,
                    ['payout_id' => $payoutId, 'reason' => $reason],
                    'high',
                    '/pages/supplier/earnings/history.php'
                );
            }
        } catch (Exception $e) { /* ignore */ }

        return ['success' => true, 'message' => 'Payout rejected.'];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Admin payout statistics overview.
 */
function getPayoutStats(): array
{
    $db    = getDB();
    $stats = [
        'total_paid_out'       => 0.0,
        'pending_count'        => 0,
        'pending_amount'       => 0.0,
        'this_month_payouts'   => 0.0,
        'this_month_count'     => 0,
        'processing_count'     => 0,
        'processing_amount'    => 0.0,
        'avg_processing_days'  => 0.0,
    ];
    try {
        $stats['total_paid_out'] = (float)$db->query(
            "SELECT COALESCE(SUM(amount),0) FROM payout_requests WHERE status = 'completed'"
        )->fetchColumn();

        $r = $db->query("SELECT COUNT(*), COALESCE(SUM(amount),0) FROM payout_requests WHERE status = 'pending'");
        [$stats['pending_count'], $stats['pending_amount']] = $r->fetch(\PDO::FETCH_NUM);
        $stats['pending_count']  = (int)$stats['pending_count'];
        $stats['pending_amount'] = (float)$stats['pending_amount'];

        $r = $db->query("SELECT COUNT(*), COALESCE(SUM(amount),0) FROM payout_requests
            WHERE status = 'completed' AND completed_at >= DATE_FORMAT(NOW(),'%Y-%m-01')");
        [$stats['this_month_count'], $stats['this_month_payouts']] = $r->fetch(\PDO::FETCH_NUM);
        $stats['this_month_count']   = (int)$stats['this_month_count'];
        $stats['this_month_payouts'] = (float)$stats['this_month_payouts'];

        $r = $db->query("SELECT COUNT(*), COALESCE(SUM(amount),0) FROM payout_requests WHERE status = 'processing'");
        [$stats['processing_count'], $stats['processing_amount']] = $r->fetch(\PDO::FETCH_NUM);
        $stats['processing_count']  = (int)$stats['processing_count'];
        $stats['processing_amount'] = (float)$stats['processing_amount'];
    } catch (PDOException $e) { /* ignore */ }

    return $stats;
}

// ── Saved Payout Methods ─────────────────────────────────────────────────────

/**
 * Save a payout method for a supplier.
 */
function savePayoutMethod(int $supplierId, string $method, array $details, bool $isDefault = false): array
{
    $db = getDB();

    if (!in_array($method, PAYOUT_VALID_METHODS, true)) {
        return ['success' => false, 'error' => 'Invalid payout method.'];
    }
    $errors = validatePayoutAccountDetails($method, $details);
    if ($errors) {
        return ['success' => false, 'error' => implode(' ', $errors)];
    }

    // Sanitize details
    $clean = [];
    foreach ($details as $k => $v) {
        $clean[htmlspecialchars($k, ENT_QUOTES, 'UTF-8')] = htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }

    try {
        if ($isDefault) {
            $db->prepare('UPDATE payout_methods SET is_default = 0 WHERE supplier_id = ?')->execute([$supplierId]);
        }
        $stmt = $db->prepare(
            'INSERT INTO payout_methods (supplier_id, method_type, account_details_json, is_default, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$supplierId, $method, json_encode($clean), (int)$isDefault]);
        return ['success' => true, 'id' => (int)$db->lastInsertId()];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Get all saved payout methods for a supplier.
 */
function getPayoutMethods(int $supplierId): array
{
    $db = getDB();
    try {
        $stmt = $db->prepare(
            'SELECT * FROM payout_methods WHERE supplier_id = ? ORDER BY is_default DESC, created_at DESC'
        );
        $stmt->execute([$supplierId]);
        $rows = $stmt->fetchAll();
        // Decode details for display
        foreach ($rows as &$row) {
            $row['details_decoded'] = json_decode($row['account_details_json'] ?? '{}', true) ?: [];
        }
        unset($row);
        return $rows;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Delete a saved payout method (supplier only).
 */
function deletePayoutMethod(int $methodId, int $supplierId): array
{
    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT id FROM payout_methods WHERE id = ? AND supplier_id = ?');
        $stmt->execute([$methodId, $supplierId]);
        if (!$stmt->fetch()) {
            return ['success' => false, 'error' => 'Payout method not found.'];
        }
        $db->prepare('DELETE FROM payout_methods WHERE id = ? AND supplier_id = ?')->execute([$methodId, $supplierId]);
        return ['success' => true, 'message' => 'Payout method deleted.'];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Set a saved method as default.
 */
function setDefaultPayoutMethod(int $methodId, int $supplierId): array
{
    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT id FROM payout_methods WHERE id = ? AND supplier_id = ?');
        $stmt->execute([$methodId, $supplierId]);
        if (!$stmt->fetch()) {
            return ['success' => false, 'error' => 'Payout method not found.'];
        }
        $db->prepare('UPDATE payout_methods SET is_default = 0 WHERE supplier_id = ?')->execute([$supplierId]);
        $db->prepare('UPDATE payout_methods SET is_default = 1, updated_at = NOW() WHERE id = ?')->execute([$methodId]);
        return ['success' => true, 'message' => 'Default payout method updated.'];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

// ── 7-Day Hold Logic (Cron) ───────────────────────────────────────────────────

/**
 * Cron job: Release held funds from orders delivered 7+ days ago.
 * Updates order status from 'delivered' to 'completed', sets hold_released_at.
 * Returns count of orders released.
 */
function releaseHeldFunds(): int
{
    $db     = getDB();
    $cutoff = date('Y-m-d H:i:s', strtotime('-' . PAYOUT_HOLD_DAYS . ' days'));
    $count  = 0;

    try {
        // Find orders to release
        $stmt = $db->prepare(
            "SELECT o.id, cl.supplier_id, cl.net_amount
             FROM orders o
             LEFT JOIN commission_logs cl ON cl.order_id = o.id
             WHERE o.status = 'delivered'
               AND o.hold_released_at IS NULL
               AND COALESCE(o.delivered_at, o.updated_at) <= ?
               AND cl.id IS NOT NULL"
        );
        $stmt->execute([$cutoff]);
        $orders = $stmt->fetchAll();

        foreach ($orders as $order) {
            $db->prepare(
                "UPDATE orders SET status = 'completed', hold_released_at = NOW(), updated_at = NOW() WHERE id = ?"
            )->execute([$order['id']]);

            // Notify supplier
            try {
                if (function_exists('createNotification') && $order['supplier_id']) {
                    createNotification(
                        $db,
                        (int)$order['supplier_id'],
                        'funds_released',
                        'Funds Available',
                        '$' . number_format((float)$order['net_amount'], 2) . ' from Order #' . $order['id'] . ' is now available for withdrawal.',
                        ['order_id' => $order['id']],
                        'normal',
                        '/pages/supplier/earnings/'
                    );
                }
            } catch (Exception $e) { /* ignore */ }

            $count++;
        }
    } catch (PDOException $e) {
        error_log('releaseHeldFunds error: ' . $e->getMessage());
    }

    return $count;
}

// ── Masking helpers ──────────────────────────────────────────────────────────

/**
 * Return a masked version of account details for display.
 * Hides sensitive fields like account numbers and routing numbers.
 */
function maskPayoutDetails(array $details): array
{
    $sensitiveKeys = ['account_number', 'routing_number', 'swift_code', 'account_id'];
    $masked        = [];
    foreach ($details as $k => $v) {
        if (in_array($k, $sensitiveKeys, true) && strlen((string)$v) > 4) {
            $masked[$k] = str_repeat('*', strlen((string)$v) - 4) . substr((string)$v, -4);
        } elseif ($k === 'email') {
            // mask email: us**@example.com — only mask if valid email format
            $atPos = strpos((string)$v, '@');
            if ($atPos !== false && $atPos > 0) {
                $local  = substr((string)$v, 0, $atPos);
                $domain = substr((string)$v, $atPos + 1);
                $masked[$k] = substr($local, 0, min(2, strlen($local)))
                    . str_repeat('*', max(0, strlen($local) - 2))
                    . '@' . $domain;
            } else {
                $masked[$k] = $v;
            }
        } else {
            $masked[$k] = $v;
        }
    }
    return $masked;
}

/**
 * Human-readable method label.
 */
function payoutMethodLabel(string $method): string
{
    return match($method) {
        'bank_transfer' => '🏦 Bank Transfer',
        'paypal'        => '💙 PayPal',
        'wise'          => '💚 Wise',
        default         => ucfirst($method),
    };
}

/**
 * Status badge HTML.
 */
function payoutStatusBadge(string $status): string
{
    [$emoji, $label, $color] = match($status) {
        'pending'    => ['⏳', 'Pending',    'warning'],
        'processing' => ['✅', 'Approved',   'info'],
        'completed'  => ['💸', 'Completed',  'success'],
        'rejected'   => ['❌', 'Rejected',   'danger'],
        'cancelled'  => ['↩️', 'Cancelled',  'secondary'],
        default      => ['', ucfirst($status), 'secondary'],
    };
    return '<span class="badge bg-' . $color . '">' . $emoji . ' ' . $label . '</span>';
}
