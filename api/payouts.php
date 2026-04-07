<?php
/**
 * api/payouts.php — Supplier Payout System API (PR #11)
 *
 * Actions (GET):
 *   balance        — Supplier's balance breakdown
 *   history        — Payout request history
 *   methods        — Saved payout methods
 *   earnings       — Earnings breakdown by period
 *   admin_queue    — Admin: pending payout queue
 *   admin_stats    — Admin: payout statistics
 *
 * Actions (POST):
 *   request        — Submit payout request
 *   cancel         — Cancel pending payout
 *   save_method    — Save new payout method
 *   delete_method  — Delete saved method
 *   set_default    — Set default payout method
 *   admin_approve  — Admin approve payout
 *   admin_reject   — Admin reject payout
 *   admin_complete — Admin mark payout completed
 */

require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/payouts.php';

header('Content-Type: application/json');

$db     = getDB();
$action = $_REQUEST['action'] ?? '';

function payoutsJson(mixed $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

switch ($action) {

    // ── GET: Supplier balance breakdown ──────────────────────────────────────
    case 'balance':
        requireLogin();
        $supplierId = isAdmin() ? (int)($_GET['supplier_id'] ?? $_SESSION['user_id']) : (int)$_SESSION['user_id'];
        payoutsJson(['success' => true, 'balance' => getSupplierBalance($supplierId)]);

    // ── GET: Payout request history ──────────────────────────────────────────
    case 'history':
        requireLogin();
        $supplierId = (int)$_SESSION['user_id'];
        $filters    = [
            'status' => $_GET['status'] ?? '',
            'method' => $_GET['method'] ?? '',
            'from'   => $_GET['date_from'] ?? '',
            'to'     => $_GET['date_to'] ?? '',
        ];
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $result  = getPayoutRequests($supplierId, $filters, $page, $perPage);
        payoutsJson(['success' => true, 'data' => $result['rows'], 'total' => $result['total'], 'pages' => $result['pages']]);

    // ── GET: Saved payout methods ────────────────────────────────────────────
    case 'methods':
        requireLogin();
        $supplierId = (int)$_SESSION['user_id'];
        $methods    = getPayoutMethods($supplierId);
        // Mask details for API output
        foreach ($methods as &$m) {
            $m['details_masked'] = maskPayoutDetails($m['details_decoded']);
        }
        unset($m);
        payoutsJson(['success' => true, 'data' => $methods]);

    // ── GET: Earnings breakdown ──────────────────────────────────────────────
    case 'earnings':
        requireLogin();
        $supplierId = isAdmin() ? (int)($_GET['supplier_id'] ?? $_SESSION['user_id']) : (int)$_SESSION['user_id'];
        $period     = in_array($_GET['period'] ?? '', ['daily','weekly','monthly']) ? $_GET['period'] : 'monthly';
        $dateFrom   = $_GET['date_from'] ?? '';
        $dateTo     = $_GET['date_to'] ?? '';
        payoutsJson([
            'success' => true,
            'data'    => getEarningsBreakdown($supplierId, $period, $dateFrom, $dateTo),
        ]);

    // ── GET: Admin pending payout queue ─────────────────────────────────────
    case 'admin_queue':
        requireAdmin();
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $result  = getPendingPayouts($page, $perPage);
        payoutsJson(['success' => true, 'data' => $result['rows'], 'total' => $result['total'], 'pages' => $result['pages']]);

    // ── GET: Admin payout statistics ─────────────────────────────────────────
    case 'admin_stats':
        requireAdmin();
        payoutsJson(['success' => true, 'stats' => getPayoutStats()]);

    // ── POST: Submit payout request ──────────────────────────────────────────
    case 'request':
        requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') payoutsJson(['error' => 'POST required'], 405);
        if (!verifyCsrf()) payoutsJson(['error' => 'Invalid CSRF token'], 403);

        $supplierId    = (int)$_SESSION['user_id'];
        $amount        = round((float)($_POST['amount'] ?? 0), 2);
        $method        = trim($_POST['method'] ?? ($_POST['payout_method'] ?? ''));
        $payoutMethodId = (int)($_POST['payout_method_id'] ?? 0);

        // If using saved method, fetch its details
        $accountDetails = [];
        if ($payoutMethodId > 0) {
            $methods = getPayoutMethods($supplierId);
            foreach ($methods as $m) {
                if ((int)$m['id'] === $payoutMethodId) {
                    $method         = $m['method_type'];
                    $accountDetails = $m['details_decoded'];
                    break;
                }
            }
            if (!$accountDetails) {
                payoutsJson(['error' => 'Saved payout method not found'], 422);
            }
        } else {
            $detailsRaw = $_POST['account_details'] ?? $_POST['payout_details'] ?? [];
            if (is_string($detailsRaw)) {
                $detailsRaw = json_decode($detailsRaw, true) ?: [];
            }
            $accountDetails = is_array($detailsRaw) ? $detailsRaw : [];
        }

        $result = requestPayout($supplierId, $amount, $method, $accountDetails, $payoutMethodId);
        if ($result['success']) {
            // Optionally save method if requested
            if (!empty($_POST['save_method']) && $_POST['save_method'] == '1' && $payoutMethodId === 0) {
                $isDefault = !empty($_POST['is_default']);
                savePayoutMethod($supplierId, $method, $accountDetails, $isDefault);
            }
            payoutsJson(['success' => true, 'message' => 'Payout request submitted.', 'id' => $result['id']]);
        } else {
            payoutsJson(['error' => $result['error']], 422);
        }

    // ── POST: Cancel pending payout ──────────────────────────────────────────
    case 'cancel':
        requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') payoutsJson(['error' => 'POST required'], 405);
        if (!verifyCsrf()) payoutsJson(['error' => 'Invalid CSRF token'], 403);

        $payoutId   = (int)($_POST['payout_id'] ?? 0);
        $supplierId = (int)$_SESSION['user_id'];
        $result     = cancelPayoutRequest($payoutId, $supplierId);
        if ($result['success']) {
            payoutsJson(['success' => true, 'message' => $result['message']]);
        } else {
            payoutsJson(['error' => $result['error']], 422);
        }

    // ── POST: Save payout method ─────────────────────────────────────────────
    case 'save_method':
        requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') payoutsJson(['error' => 'POST required'], 405);
        if (!verifyCsrf()) payoutsJson(['error' => 'Invalid CSRF token'], 403);

        $supplierId = (int)$_SESSION['user_id'];
        $method     = trim($_POST['method'] ?? '');
        $isDefault  = !empty($_POST['is_default']);
        $detailsRaw = $_POST['details'] ?? [];
        if (is_string($detailsRaw)) {
            $detailsRaw = json_decode($detailsRaw, true) ?: [];
        }
        $details = is_array($detailsRaw) ? $detailsRaw : [];

        $result = savePayoutMethod($supplierId, $method, $details, $isDefault);
        if ($result['success']) {
            payoutsJson(['success' => true, 'message' => 'Payout method saved.', 'id' => $result['id']]);
        } else {
            payoutsJson(['error' => $result['error']], 422);
        }

    // ── POST: Delete payout method ───────────────────────────────────────────
    case 'delete_method':
        requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') payoutsJson(['error' => 'POST required'], 405);
        if (!verifyCsrf()) payoutsJson(['error' => 'Invalid CSRF token'], 403);

        $methodId   = (int)($_POST['method_id'] ?? 0);
        $supplierId = (int)$_SESSION['user_id'];
        $result     = deletePayoutMethod($methodId, $supplierId);
        if ($result['success']) {
            payoutsJson(['success' => true, 'message' => $result['message']]);
        } else {
            payoutsJson(['error' => $result['error']], 404);
        }

    // ── POST: Set default payout method ─────────────────────────────────────
    case 'set_default':
        requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') payoutsJson(['error' => 'POST required'], 405);
        if (!verifyCsrf()) payoutsJson(['error' => 'Invalid CSRF token'], 403);

        $methodId   = (int)($_POST['method_id'] ?? 0);
        $supplierId = (int)$_SESSION['user_id'];
        $result     = setDefaultPayoutMethod($methodId, $supplierId);
        if ($result['success']) {
            payoutsJson(['success' => true, 'message' => $result['message']]);
        } else {
            payoutsJson(['error' => $result['error']], 422);
        }

    // ── POST: Admin approve payout ───────────────────────────────────────────
    case 'admin_approve':
        requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') payoutsJson(['error' => 'POST required'], 405);
        if (!verifyCsrf()) payoutsJson(['error' => 'Invalid CSRF token'], 403);

        $payoutId      = (int)($_POST['payout_id'] ?? ($_POST['id'] ?? 0));
        $transactionRef = trim($_POST['transaction_ref'] ?? '');
        $result         = approvePayout($payoutId, (int)$_SESSION['user_id'], $transactionRef);
        if ($result['success']) {
            payoutsJson(['success' => true, 'message' => $result['message']]);
        } else {
            payoutsJson(['error' => $result['error']], 422);
        }

    // ── POST: Admin reject payout ────────────────────────────────────────────
    case 'admin_reject':
        requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') payoutsJson(['error' => 'POST required'], 405);
        if (!verifyCsrf()) payoutsJson(['error' => 'Invalid CSRF token'], 403);

        $payoutId = (int)($_POST['payout_id'] ?? ($_POST['id'] ?? 0));
        $reason   = trim($_POST['reason'] ?? '');
        $result   = rejectPayout($payoutId, (int)$_SESSION['user_id'], $reason);
        if ($result['success']) {
            payoutsJson(['success' => true, 'message' => $result['message']]);
        } else {
            payoutsJson(['error' => $result['error']], 422);
        }

    // ── POST: Admin mark payout completed ────────────────────────────────────
    case 'admin_complete':
        requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') payoutsJson(['error' => 'POST required'], 405);
        if (!verifyCsrf()) payoutsJson(['error' => 'Invalid CSRF token'], 403);

        $payoutId      = (int)($_POST['payout_id'] ?? ($_POST['id'] ?? 0));
        $transactionRef = trim($_POST['transaction_ref'] ?? ($_POST['reference_number'] ?? ''));
        $result         = markPayoutCompleted($payoutId, (int)$_SESSION['user_id'], $transactionRef);
        if ($result['success']) {
            payoutsJson(['success' => true, 'message' => $result['message']]);
        } else {
            payoutsJson(['error' => $result['error']], 422);
        }

    // ── Legacy: list (kept for backwards compat) ──────────────────────────────
    case 'list':
        requireLogin();
        $page   = max(1, (int)($_GET['page'] ?? 1));
        if (isAdmin()) {
            $result = getPendingPayouts($page);
        } else {
            $result = getPayoutRequests((int)$_SESSION['user_id'], [], $page);
        }
        payoutsJson(['success' => true, 'data' => $result['rows'], 'total' => $result['total']]);

    default:
        payoutsJson(['error' => 'Invalid action'], 400);
}
