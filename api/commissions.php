<?php
/**
 * api/commissions.php — Commission Engine API
 *
 * Actions:
 *   calculate       — Calculate commission for an order (admin/internal)
 *   list            — List commission logs (supplier: own, admin: all)
 *   summary         — Commission summary (daily/weekly/monthly/yearly)
 *   rates           — Get current commission rates (public)
 *   update_rates    — Admin updates commission tier rates
 *   update_category_rate — Admin sets category-specific rate
 *   tiers           — Get tier information
 */

require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/commission.php';

header('Content-Type: application/json');

$db     = getDB();
$action = $_REQUEST['action'] ?? '';

function commJson(mixed $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

switch ($action) {

    // ── Calculate commission for an order ──────────────────────────────
    case 'calculate':
        requireAdmin();
        $orderId = (int)($_REQUEST['order_id'] ?? 0);
        if ($orderId <= 0) commJson(['error' => 'order_id required'], 400);
        $amount = calculateCommission($orderId);
        if ($amount === false) commJson(['error' => 'Order not found or invalid'], 404);
        commJson(['success' => true, 'commission_amount' => $amount]);

    // ── List commission logs ───────────────────────────────────────────
    case 'list':
        requireLogin();
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        if (isAdmin()) {
            $supplierId = (int)($_GET['supplier_id'] ?? 0);
            $from       = $_GET['from'] ?? '';
            $to         = $_GET['to'] ?? '';

            $where  = [];
            $params = [];
            if ($supplierId > 0) { $where[] = 'cl.supplier_id = ?'; $params[] = $supplierId; }
            if ($from)           { $where[] = 'cl.created_at >= ?'; $params[] = $from . ' 00:00:00'; }
            if ($to)             { $where[] = 'cl.created_at <= ?'; $params[] = $to . ' 23:59:59'; }

            $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            $countStmt   = $db->prepare("SELECT COUNT(*) FROM commission_logs cl $whereClause");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            $stmt = $db->prepare("SELECT cl.*, u.email, u.company_name
                FROM commission_logs cl
                LEFT JOIN users u ON u.id = cl.supplier_id
                $whereClause
                ORDER BY cl.created_at DESC
                LIMIT $limit OFFSET $offset");
            $stmt->execute($params);
        } else {
            // Supplier sees only own
            $supplierId = $_SESSION['user_id'];
            $total      = (int)$db->prepare('SELECT COUNT(*) FROM commission_logs WHERE supplier_id = ?')
                             ->execute([$supplierId]) ? 0 : 0;
            $cStmt = $db->prepare('SELECT COUNT(*) FROM commission_logs WHERE supplier_id = ?');
            $cStmt->execute([$supplierId]);
            $total = (int)$cStmt->fetchColumn();

            $stmt = $db->prepare('SELECT cl.* FROM commission_logs cl
                WHERE cl.supplier_id = ?
                ORDER BY cl.created_at DESC
                LIMIT ' . $limit . ' OFFSET ' . $offset);
            $stmt->execute([$supplierId]);
        }

        commJson([
            'success' => true,
            'data'    => $stmt->fetchAll(),
            'total'   => $total,
            'page'    => $page,
            'pages'   => max(1, (int)ceil($total / $limit)),
        ]);

    // ── Summary ───────────────────────────────────────────────────────
    case 'summary':
        requireLogin();
        $period     = $_GET['period'] ?? 'monthly';
        $supplierId = isAdmin() ? (int)($_GET['supplier_id'] ?? $_SESSION['user_id']) : $_SESSION['user_id'];
        $summary    = getCommissionSummary($supplierId, $period);
        commJson(['success' => true, 'data' => $summary, 'period' => $period]);

    // ── Get current commission rates (public) ─────────────────────────
    case 'rates':
        $tiers = getCommissionTiers();
        commJson(['success' => true, 'tiers' => $tiers]);

    // ── Admin: Update commission tier rates ───────────────────────────
    case 'update_rates':
        requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') commJson(['error' => 'POST required'], 405);
        if (!verifyCsrf()) commJson(['error' => 'Invalid CSRF token'], 403);

        $tiers = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        if (empty($tiers['tiers']) || !is_array($tiers['tiers'])) {
            commJson(['error' => 'tiers array required'], 422);
        }
        foreach ($tiers['tiers'] as $tier) {
            $id   = (int)($tier['id'] ?? 0);
            $rate = (float)($tier['rate'] ?? 0);
            if ($id > 0 && $rate > 0) {
                $db->prepare('UPDATE commission_tiers SET rate = ? WHERE id = ?')->execute([$rate, $id]);
            }
        }
        commJson(['success' => true, 'message' => 'Commission tiers updated']);

    // ── Admin: Set category-specific rate ────────────────────────────
    case 'update_category_rate':
        requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') commJson(['error' => 'POST required'], 405);
        if (!verifyCsrf()) commJson(['error' => 'Invalid CSRF token'], 403);

        $categoryId = (int)($_POST['category_id'] ?? 0);
        $rate       = (float)($_POST['rate'] ?? 0);
        if ($categoryId <= 0 || $rate < 0) commJson(['error' => 'category_id and rate required'], 422);

        $check = $db->prepare('SELECT id FROM category_commission_rates WHERE category_id = ?');
        $check->execute([$categoryId]);
        if ($check->fetch()) {
            $db->prepare('UPDATE category_commission_rates SET rate = ?, updated_at = NOW() WHERE category_id = ?')
               ->execute([$rate, $categoryId]);
        } else {
            $db->prepare('INSERT INTO category_commission_rates (category_id, rate) VALUES (?, ?)')
               ->execute([$categoryId, $rate]);
        }
        commJson(['success' => true, 'message' => 'Category commission rate updated']);

    // ── Tier info ─────────────────────────────────────────────────────
    case 'tiers':
        $tiers = getCommissionTiers();
        commJson(['success' => true, 'tiers' => $tiers]);

    default:
        commJson(['error' => 'Invalid action'], 400);
}
