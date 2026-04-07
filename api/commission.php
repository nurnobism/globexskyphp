<?php
/**
 * api/commission.php — Commission Engine API (PR #8)
 *
 * Actions:
 *   calculate            POST  Calculate commission for an order (admin/internal)
 *   logs                 GET   Commission logs (supplier: own, admin: all)
 *   stats                GET   Commission statistics
 *   category_rates       GET   All category commission rates (admin)
 *   update_category_rate POST  Update a category commission override (admin)
 *   update_tier_rates    POST  Update GMV tier rates (admin)
 *   supplier_tier        GET   Supplier's current GMV tier info
 */

require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/commission.php';

header('Content-Type: application/json');

$db     = getDB();
$action = $_REQUEST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

/** @return never */
function commissionApiResponse(mixed $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

switch ($action) {

    // ── calculate ──────────────────────────────────────────────────────
    case 'calculate':
        requireAdmin();
        if ($method !== 'POST') {
            commissionApiResponse(['error' => 'POST required'], 405);
        }
        $orderId = (int)($_REQUEST['order_id'] ?? 0);
        if ($orderId <= 0) {
            commissionApiResponse(['error' => 'order_id required'], 400);
        }
        $result = calculateCommission($orderId);
        if ($result === false) {
            commissionApiResponse(['error' => 'Order not found or invalid'], 404);
        }
        commissionApiResponse(['success' => true, 'data' => $result]);

    // ── logs ───────────────────────────────────────────────────────────
    case 'logs':
        requireLogin();
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));

        if (isAdmin()) {
            $supplierId = (int)($_GET['supplier_id'] ?? 0);
        } else {
            requireRole(['supplier', 'admin', 'super_admin']);
            $supplierId = (int)$_SESSION['user_id'];
        }

        $filters = [];
        if (!empty($_GET['from'])) $filters['from'] = $_GET['from'];
        if (!empty($_GET['to']))   $filters['to']   = $_GET['to'];
        if (!empty($_GET['tier'])) $filters['tier']  = $_GET['tier'];

        $result = getCommissionLogs($supplierId, $filters, $page, $perPage);
        commissionApiResponse(array_merge(['success' => true], $result));

    // ── stats ──────────────────────────────────────────────────────────
    case 'stats':
        requireLogin();

        if (isAdmin() && isset($_GET['admin']) && $_GET['admin'] === '1') {
            $data = getAdminCommissionStats();
        } else {
            if (!isAdmin()) {
                requireRole(['supplier', 'admin', 'super_admin']);
            }
            $supplierId = isAdmin()
                ? (int)($_GET['supplier_id'] ?? $_SESSION['user_id'])
                : (int)$_SESSION['user_id'];
            $data = getCommissionStats($supplierId);
        }
        commissionApiResponse(['success' => true, 'data' => $data]);

    // ── category_rates (admin) ─────────────────────────────────────────
    case 'category_rates':
        requireAdmin();
        try {
            $stmt = $db->query(
                'SELECT ccr.id, ccr.category_id,
                        COALESCE(c.name, CONCAT("Category #", ccr.category_id)) AS category_name,
                        COALESCE(ccr.override_rate, ccr.rate, 0) AS override_rate,
                        ccr.is_active, ccr.created_at, ccr.updated_at
                 FROM category_commission_rates ccr
                 LEFT JOIN categories c ON c.id = ccr.category_id
                 ORDER BY category_name ASC'
            );
            $rows = $stmt->fetchAll();
        } catch (PDOException $e) {
            $rows = [];
        }
        commissionApiResponse(['success' => true, 'data' => $rows]);

    // ── update_category_rate (admin POST) ──────────────────────────────
    case 'update_category_rate':
        requireAdmin();
        if ($method !== 'POST') {
            commissionApiResponse(['error' => 'POST required'], 405);
        }
        if (!verifyCsrf()) {
            commissionApiResponse(['error' => 'Invalid CSRF token'], 403);
        }
        $categoryId   = (int)($_POST['category_id'] ?? 0);
        $overrideRate = (float)($_POST['override_rate'] ?? $_POST['rate'] ?? -1);
        if ($categoryId <= 0 || $overrideRate < 0) {
            commissionApiResponse(['error' => 'category_id and override_rate required'], 422);
        }
        // Normalise: accept both percent (8) and fraction (0.08)
        $rateStored = $overrideRate > 1 ? round($overrideRate / 100, 6) : round($overrideRate, 6);

        try {
            $check = $db->prepare('SELECT id FROM category_commission_rates WHERE category_id = ?');
            $check->execute([$categoryId]);
            if ($check->fetchColumn()) {
                $db->prepare(
                    'UPDATE category_commission_rates
                     SET override_rate = ?, is_active = 1, updated_at = NOW()
                     WHERE category_id = ?'
                )->execute([$rateStored, $categoryId]);
            } else {
                $db->prepare(
                    'INSERT INTO category_commission_rates (category_id, override_rate)
                     VALUES (?, ?)'
                )->execute([$categoryId, $rateStored]);
            }
            commissionApiResponse(['success' => true, 'message' => 'Category rate updated']);
        } catch (PDOException $e) {
            commissionApiResponse(['error' => 'Database error'], 500);
        }

    // ── update_tier_rates (admin POST) ────────────────────────────────
    case 'update_tier_rates':
        requireAdmin();
        if ($method !== 'POST') {
            commissionApiResponse(['error' => 'POST required'], 405);
        }
        if (!verifyCsrf()) {
            commissionApiResponse(['error' => 'Invalid CSRF token'], 403);
        }
        $body  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $tiers = $body['tiers'] ?? [];
        if (empty($tiers) || !is_array($tiers)) {
            commissionApiResponse(['error' => 'tiers array required'], 422);
        }
        $updated = 0;
        foreach ($tiers as $tier) {
            $id       = (int)($tier['id'] ?? 0);
            $baseRate = isset($tier['base_rate']) ? (float)$tier['base_rate'] : null;
            if ($baseRate === null && isset($tier['rate'])) {
                // Accept percent-style input
                $baseRate = (float)$tier['rate'];
                $baseRate = $baseRate > 1 ? round($baseRate / 100, 6) : round($baseRate, 6);
            }
            if ($id > 0 && $baseRate !== null && $baseRate > 0) {
                try {
                    $db->prepare(
                        'UPDATE commission_tier_config SET base_rate = ?, updated_at = NOW() WHERE id = ?'
                    )->execute([$baseRate, $id]);
                    $updated++;
                } catch (PDOException $e) {
                    // Fallback: old commission_tiers table
                    try {
                        $pct = $baseRate > 1 ? $baseRate : round($baseRate * 100, 4);
                        $db->prepare(
                            'UPDATE commission_tiers SET rate = ? WHERE id = ?'
                        )->execute([$pct, $id]);
                        $updated++;
                    } catch (PDOException $e2) { /* ignore */ }
                }
            }
        }
        commissionApiResponse(['success' => true, 'updated' => $updated]);

    // ── supplier_tier ──────────────────────────────────────────────────
    case 'supplier_tier':
        requireLogin();
        if (isAdmin()) {
            $supplierId = (int)($_GET['supplier_id'] ?? $_SESSION['user_id']);
        } else {
            requireRole(['supplier', 'admin', 'super_admin']);
            $supplierId = (int)$_SESSION['user_id'];
        }
        $tierInfo     = getSupplierGmvTier($supplierId);
        $planDiscount = getSupplierPlanDiscount($supplierId);
        commissionApiResponse([
            'success'       => true,
            'tier_name'     => $tierInfo['tier_name'],
            'base_rate'     => $tierInfo['base_rate'],
            'base_rate_pct' => round($tierInfo['base_rate'] * 100, 2),
            'gmv_90d'       => $tierInfo['gmv_90d'],
            'plan_discount'     => $planDiscount,
            'plan_discount_pct' => round($planDiscount * 100, 2),
            'effective_rate'    => round($tierInfo['base_rate'] * (1 - $planDiscount), 6),
            'effective_rate_pct' => round($tierInfo['base_rate'] * (1 - $planDiscount) * 100, 2),
        ]);

    default:
        commissionApiResponse(['error' => 'Invalid action. Valid actions: calculate, logs, stats, category_rates, update_category_rate, update_tier_rates, supplier_tier'], 400);
}
