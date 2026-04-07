<?php
/**
 * api/promotions.php — Promotions API (PR #13)
 *
 * Actions:
 *   active   GET   Get active promotions (public)
 *   products GET   Get products in a promotion (public)
 *   create   POST  Create promotion (admin only)
 *   update   POST  Update promotion (admin only)
 *   delete   POST  Delete promotion (admin only)
 *   list     GET   List all promotions (admin)
 */

require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/coupons.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function promoJsonOut(mixed $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function promoSanitize(string $v): string { return trim(htmlspecialchars($v, ENT_QUOTES, 'UTF-8')); }

function promoValidateCsrf(): void
{
    if (!verifyCsrf()) {
        promoJsonOut(['success' => false, 'message' => 'Invalid CSRF token'], 403);
    }
}

switch ($action) {

    // ── Public: active promotions ────────────────────────────────────────────
    case 'active':
        $promos = getActivePromotions();
        promoJsonOut(['success' => true, 'data' => $promos]);
    break;

    // ── Public: products in promotion ────────────────────────────────────────
    case 'products':
        $promoId = (int)($_GET['promotion_id'] ?? 0);
        if ($promoId <= 0) promoJsonOut(['success' => false, 'message' => 'promotion_id required'], 400);
        $products = getPromotionProducts($promoId);
        promoJsonOut(['success' => true, 'data' => $products]);
    break;

    // ── Admin: create promotion ──────────────────────────────────────────────
    case 'create':
        requireAdmin();
        promoValidateCsrf();
        $user = getCurrentUser();

        $data = [
            'name'           => promoSanitize($_POST['name'] ?? ''),
            'description'    => promoSanitize($_POST['description'] ?? ''),
            'discount_type'  => promoSanitize($_POST['discount_type'] ?? 'percentage'),
            'discount_value' => (float)($_POST['discount_value'] ?? 0),
            'start_date'     => promoSanitize($_POST['start_date'] ?? ''),
            'end_date'       => promoSanitize($_POST['end_date'] ?? ''),
            'banner_image'   => promoSanitize($_POST['banner_image'] ?? ''),
            'is_featured'    => isset($_POST['is_featured']) ? (bool)$_POST['is_featured'] : false,
            'created_by'     => (int)$user['id'],
        ];
        if (!empty($_POST['products'])) {
            $data['products'] = array_map('intval', (array)$_POST['products']);
        }
        if (!empty($_POST['categories'])) {
            $data['categories'] = array_map('intval', (array)$_POST['categories']);
        }

        try {
            $id = createPromotion($data);
            promoJsonOut(['success' => true, 'message' => 'Promotion created', 'id' => $id], 201);
        } catch (RuntimeException $e) {
            promoJsonOut(['success' => false, 'message' => $e->getMessage()], 400);
        }
    break;

    // ── Admin: update promotion ──────────────────────────────────────────────
    case 'update':
        requireAdmin();
        promoValidateCsrf();
        $promoId = (int)($_POST['promotion_id'] ?? $_POST['id'] ?? 0);
        if ($promoId <= 0) promoJsonOut(['success' => false, 'message' => 'promotion_id required'], 400);

        $data = [];
        $allowed = ['name','description','discount_type','discount_value','start_date','end_date',
                    'banner_image','is_featured','is_active'];
        foreach ($allowed as $f) {
            if (isset($_POST[$f])) $data[$f] = promoSanitize((string)$_POST[$f]);
        }
        if (isset($_POST['products'])) $data['products']   = array_map('intval', (array)$_POST['products']);
        if (isset($_POST['categories'])) $data['categories'] = array_map('intval', (array)$_POST['categories']);

        updatePromotion($promoId, $data);
        promoJsonOut(['success' => true, 'message' => 'Promotion updated']);
    break;

    // ── Admin: delete promotion ──────────────────────────────────────────────
    case 'delete':
        requireAdmin();
        promoValidateCsrf();
        $promoId = (int)($_POST['promotion_id'] ?? $_POST['id'] ?? 0);
        if ($promoId <= 0) promoJsonOut(['success' => false, 'message' => 'promotion_id required'], 400);

        deletePromotion($promoId);
        promoJsonOut(['success' => true, 'message' => 'Promotion deleted']);
    break;

    // ── Admin: list promotions ───────────────────────────────────────────────
    case 'list':
        requireAdmin();
        $db      = getDB();
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        try {
            $total = (int)$db->query('SELECT COUNT(*) FROM promotions')->fetchColumn();
            $offset = ($page - 1) * $perPage;
            $stmt = $db->prepare('SELECT * FROM promotions ORDER BY created_at DESC LIMIT ? OFFSET ?');
            $stmt->execute([$perPage, $offset]);
            $promos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            promoJsonOut([
                'success'     => true,
                'data'        => $promos,
                'total'       => $total,
                'page'        => $page,
                'total_pages' => (int)ceil($total / $perPage),
            ]);
        } catch (PDOException $e) {
            promoJsonOut(['success' => false, 'message' => 'Database error'], 500);
        }
    break;

    default:
        promoJsonOut(['success' => false, 'message' => 'Invalid action'], 400);
}
