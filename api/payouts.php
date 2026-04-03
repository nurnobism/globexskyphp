<?php
/**
 * api/payouts.php — Supplier Payout System API
 *
 * Actions:
 *   request  — Supplier requests payout
 *   list     — List payouts (supplier: own, admin: all)
 *   approve  — Admin approves payout
 *   reject   — Admin rejects payout (with reason)
 *   complete — Admin marks payout as completed
 *   balance  — Get supplier's current available balance
 */

require_once __DIR__ . '/../includes/middleware.php';

header('Content-Type: application/json');

$db     = getDB();
$action = $_REQUEST['action'] ?? '';

function payoutsJson(mixed $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

/**
 * Get supplier's available balance: total sales - commissions - pending/completed payouts
 */
function getSupplierBalance(int $supplierId): array
{
    $db = getDB();

    // Total earnings from sales
    try {
        $sStmt = $db->prepare('SELECT COALESCE(SUM(amount),0) FROM supplier_earnings WHERE supplier_id = ? AND type = "sale"');
        $sStmt->execute([$supplierId]);
        $totalSales = (float)$sStmt->fetchColumn();

        $cStmt = $db->prepare('SELECT COALESCE(SUM(amount),0) FROM supplier_earnings WHERE supplier_id = ? AND type = "commission_deduct"');
        $cStmt->execute([$supplierId]);
        $totalCommissions = (float)$cStmt->fetchColumn();

        $pStmt = $db->prepare('SELECT COALESCE(SUM(amount),0) FROM supplier_earnings WHERE supplier_id = ? AND type = "payout"');
        $pStmt->execute([$supplierId]);
        $totalPayouts = (float)$pStmt->fetchColumn();

        $rStmt = $db->prepare('SELECT COALESCE(SUM(amount),0) FROM supplier_earnings WHERE supplier_id = ? AND type = "refund"');
        $rStmt->execute([$supplierId]);
        $totalRefunds = (float)$rStmt->fetchColumn();

        $available = round($totalSales - $totalCommissions - $totalPayouts - $totalRefunds, 2);
    } catch (PDOException $e) {
        // Fallback: calculate from orders
        try {
            $oStmt = $db->prepare('SELECT COALESCE(SUM(oi.subtotal),0) FROM order_items oi
                JOIN orders o ON o.id = oi.order_id
                JOIN products p ON p.id = oi.product_id
                WHERE p.supplier_id = ? AND o.status IN ("completed","delivered","shipped")');
            $oStmt->execute([$supplierId]);
            $totalSales = (float)$oStmt->fetchColumn();
        } catch (PDOException $e2) {
            $totalSales = 0;
        }
        $totalCommissions = 0;
        $totalPayouts     = 0;
        $totalRefunds     = 0;
        $available        = $totalSales;
    }

    // Pending payouts
    try {
        $ppStmt = $db->prepare('SELECT COALESCE(SUM(amount),0) FROM payout_requests WHERE supplier_id = ? AND status IN ("pending","processing")');
        $ppStmt->execute([$supplierId]);
        $pendingPayout = (float)$ppStmt->fetchColumn();
    } catch (PDOException $e) {
        $pendingPayout = 0;
    }

    return [
        'total_sales'       => $totalSales,
        'total_commissions' => $totalCommissions,
        'total_payouts'     => $totalPayouts,
        'total_refunds'     => $totalRefunds,
        'available_balance' => max(0, $available - $pendingPayout),
        'pending_payout'    => $pendingPayout,
        'gross_balance'     => max(0, $available),
    ];
}

switch ($action) {

    // ── Get balance ───────────────────────────────────────────────────
    case 'balance':
        requireLogin();
        $supplierId = isAdmin() ? (int)($_GET['supplier_id'] ?? $_SESSION['user_id']) : $_SESSION['user_id'];
        payoutsJson(['success' => true, 'balance' => getSupplierBalance($supplierId)]);

    // ── Request payout ────────────────────────────────────────────────
    case 'request':
        requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') payoutsJson(['error' => 'POST required'], 405);
        if (!verifyCsrf()) payoutsJson(['error' => 'Invalid CSRF token'], 403);

        $supplierId    = $_SESSION['user_id'];
        $amount        = round((float)($_POST['amount'] ?? 0), 2);
        $method        = $_POST['payout_method'] ?? '';
        $detailsRaw    = $_POST['payout_details'] ?? [];

        // Validate
        if ($amount < 50) payoutsJson(['error' => 'Minimum payout amount is $50'], 422);
        if (!in_array($method, ['bank_transfer', 'paypal', 'wise'])) {
            payoutsJson(['error' => 'Invalid payout method'], 422);
        }

        $balance = getSupplierBalance($supplierId);
        if ($amount > $balance['available_balance']) {
            payoutsJson(['error' => 'Amount exceeds available balance of $' . number_format($balance['available_balance'], 2)], 422);
        }

        // Sanitize payout details
        $details = is_array($detailsRaw) ? $detailsRaw : [];
        foreach ($details as $k => $v) {
            $details[$k] = htmlspecialchars(strip_tags((string)$v), ENT_QUOTES, 'UTF-8');
        }

        $stmt = $db->prepare('INSERT INTO payout_requests
            (supplier_id, amount, payout_method, payout_details, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, "pending", NOW(), NOW())');
        $stmt->execute([$supplierId, $amount, $method, json_encode($details)]);
        $requestId = $db->lastInsertId();

        // Log to supplier_earnings as a deduction (pending)
        try {
            $balAfter = $balance['available_balance'] - $amount;
            $db->prepare('INSERT INTO supplier_earnings (supplier_id, type, amount, balance_after, description, reference_id, created_at)
                VALUES (?, "payout", ?, ?, ?, ?, NOW())')
               ->execute([$supplierId, $amount, $balAfter, 'Payout request #' . $requestId, 'payout_' . $requestId]);
        } catch (PDOException $e) { /* ignore */ }

        payoutsJson(['success' => true, 'message' => 'Payout request submitted', 'id' => $requestId]);

    // ── List payouts ──────────────────────────────────────────────────
    case 'list':
        requireLogin();
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        if (isAdmin()) {
            $status = $_GET['status'] ?? '';
            $where  = $status ? 'WHERE pr.status = ?' : '';
            $params = $status ? [$status] : [];

            $cStmt = $db->prepare("SELECT COUNT(*) FROM payout_requests pr $where");
            $cStmt->execute($params);
            $total = (int)$cStmt->fetchColumn();

            $stmt = $db->prepare("SELECT pr.*, u.email, u.company_name
                FROM payout_requests pr
                LEFT JOIN users u ON u.id = pr.supplier_id
                $where
                ORDER BY pr.created_at DESC
                LIMIT $limit OFFSET $offset");
            $stmt->execute($params);
        } else {
            $supplierId = $_SESSION['user_id'];
            $cStmt = $db->prepare('SELECT COUNT(*) FROM payout_requests WHERE supplier_id = ?');
            $cStmt->execute([$supplierId]);
            $total = (int)$cStmt->fetchColumn();

            $stmt = $db->prepare('SELECT * FROM payout_requests WHERE supplier_id = ?
                ORDER BY created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
            $stmt->execute([$supplierId]);
        }

        payoutsJson(['success' => true, 'data' => $stmt->fetchAll(), 'total' => $total]);

    // ── Admin: Approve payout ─────────────────────────────────────────
    case 'approve':
        requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') payoutsJson(['error' => 'POST required'], 405);
        if (!verifyCsrf()) payoutsJson(['error' => 'Invalid CSRF token'], 403);

        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare('SELECT * FROM payout_requests WHERE id = ?');
        $stmt->execute([$id]);
        $req = $stmt->fetch();
        if (!$req) payoutsJson(['error' => 'Payout request not found'], 404);
        if ($req['status'] !== 'pending') payoutsJson(['error' => 'Only pending requests can be approved'], 422);

        $db->prepare('UPDATE payout_requests SET status = "processing", processed_by = ?, processed_at = NOW(), updated_at = NOW() WHERE id = ?')
           ->execute([$_SESSION['user_id'], $id]);

        // Log admin action
        try {
            $db->prepare('INSERT INTO admin_activity_logs (admin_id, action, description, created_at)
                VALUES (?, "payout_approve", ?, NOW())')
               ->execute([$_SESSION['user_id'], 'Approved payout #' . $id . ' for $' . $req['amount']]);
        } catch (PDOException $e) { /* ignore */ }

        payoutsJson(['success' => true, 'message' => 'Payout approved and set to processing']);

    // ── Admin: Reject payout ──────────────────────────────────────────
    case 'reject':
        requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') payoutsJson(['error' => 'POST required'], 405);
        if (!verifyCsrf()) payoutsJson(['error' => 'Invalid CSRF token'], 403);

        $id     = (int)($_POST['id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $stmt = $db->prepare('SELECT * FROM payout_requests WHERE id = ?');
        $stmt->execute([$id]);
        $req = $stmt->fetch();
        if (!$req) payoutsJson(['error' => 'Payout request not found'], 404);
        if ($req['status'] === 'completed') payoutsJson(['error' => 'Cannot reject completed payout'], 422);

        $db->prepare('UPDATE payout_requests SET status = "rejected", admin_note = ?, processed_by = ?, processed_at = NOW(), updated_at = NOW() WHERE id = ?')
           ->execute([$reason, $_SESSION['user_id'], $id]);

        // Reverse the payout earnings entry
        try {
            $db->prepare('DELETE FROM supplier_earnings WHERE reference_id = ? AND type = "payout"')
               ->execute(['payout_' . $id]);
        } catch (PDOException $e) { /* ignore */ }

        payoutsJson(['success' => true, 'message' => 'Payout rejected']);

    // ── Admin: Mark as completed ──────────────────────────────────────
    case 'complete':
        requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') payoutsJson(['error' => 'POST required'], 405);
        if (!verifyCsrf()) payoutsJson(['error' => 'Invalid CSRF token'], 403);

        $id        = (int)($_POST['id'] ?? 0);
        $reference = trim($_POST['reference_number'] ?? '');
        $stmt = $db->prepare('SELECT * FROM payout_requests WHERE id = ?');
        $stmt->execute([$id]);
        $req = $stmt->fetch();
        if (!$req) payoutsJson(['error' => 'Payout request not found'], 404);
        if ($req['status'] !== 'processing') payoutsJson(['error' => 'Only processing requests can be marked complete'], 422);

        $db->prepare('UPDATE payout_requests SET status = "completed", reference_number = ?, processed_by = ?, processed_at = NOW(), updated_at = NOW() WHERE id = ?')
           ->execute([$reference, $_SESSION['user_id'], $id]);

        payoutsJson(['success' => true, 'message' => 'Payout marked as completed']);

    default:
        payoutsJson(['error' => 'Invalid action'], 400);
}
