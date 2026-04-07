<?php
/**
 * api/coupons.php — Coupon API (PR #13)
 *
 * Actions:
 *   validate   POST  Validate coupon against cart (buyer)
 *   apply      POST  Apply coupon to session cart (buyer)
 *   remove     POST  Remove applied coupon from session (buyer)
 *   available  GET   Get available coupons for current cart (buyer)
 *   create     POST  Create coupon (admin or supplier)
 *   update     POST  Update coupon (creator only)
 *   delete     POST  Soft-delete coupon (creator only)
 *   list       GET   List coupons (admin: all, supplier: own)
 *   get        GET   Get coupon detail
 *   stats      GET   Coupon usage analytics
 */

require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/coupons.php';
require_once __DIR__ . '/../includes/cart.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db     = getDB();

function couponJsonOut(mixed $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function couponSanitize(string $v): string { return trim(htmlspecialchars($v, ENT_QUOTES, 'UTF-8')); }

function couponValidateCsrf(): void
{
    if (!verifyCsrf()) {
        couponJsonOut(['success' => false, 'message' => 'Invalid CSRF token'], 403);
    }
}

switch ($action) {

    // ── Buyer: validate ─────────────────────────────────────────────────────
    case 'validate':
        requireLogin();
        $user      = getCurrentUser();
        $userId    = (int)$user['id'];
        $code      = couponSanitize($_POST['code'] ?? $_GET['code'] ?? '');
        if ($code === '') couponJsonOut(['success' => false, 'message' => 'Coupon code required'], 400);

        $cartItems = getCart($userId);
        $cartTotal = array_sum(array_map(fn($i) => (float)($i['price'] ?? 0) * (int)($i['quantity'] ?? 1), $cartItems));

        $result = validateCoupon($code, $userId, $cartItems, $cartTotal);
        if (!$result['valid']) {
            couponJsonOut(['success' => false, 'message' => $result['error'] ?? 'Invalid coupon'], 400);
        }
        couponJsonOut([
            'success'         => true,
            'discount_amount' => $result['discount_amount'],
            'free_shipping'   => ($result['coupon']['type'] ?? '') === 'free_shipping',
            'message'         => $result['message'],
        ]);
    break;

    // ── Buyer: apply ────────────────────────────────────────────────────────
    case 'apply':
        requireLogin();
        couponValidateCsrf();
        $user   = getCurrentUser();
        $userId = (int)$user['id'];
        $code   = couponSanitize($_POST['code'] ?? '');
        if ($code === '') couponJsonOut(['success' => false, 'message' => 'Coupon code required'], 400);

        $cartItems = getCart($userId);
        $cartTotal = array_sum(array_map(fn($i) => (float)($i['price'] ?? 0) * (int)($i['quantity'] ?? 1), $cartItems));

        $result = applyCoupon($code, $userId, $cartItems, $cartTotal);
        if (!($result['valid'] ?? false)) {
            couponJsonOut(['success' => false, 'message' => $result['error'] ?? 'Invalid coupon'], 400);
        }

        // Store in session for checkout
        $_SESSION['applied_coupon'] = [
            'coupon_id'      => $result['coupon_id'],
            'coupon_code'    => $result['coupon_code'],
            'discount_type'  => $result['discount_type'],
            'discount_value' => $result['discount_value'],
            'discount_amount'=> $result['discount_amount'],
            'free_shipping'  => $result['free_shipping'],
        ];

        couponJsonOut([
            'success'         => true,
            'discount_amount' => $result['discount_amount'],
            'new_total'       => $result['new_total'],
            'free_shipping'   => $result['free_shipping'],
            'coupon_code'     => $result['coupon_code'],
            'message'         => $result['message'],
        ]);
    break;

    // ── Buyer: remove ───────────────────────────────────────────────────────
    case 'remove':
        requireLogin();
        couponValidateCsrf();
        unset($_SESSION['applied_coupon']);
        couponJsonOut(['success' => true, 'message' => 'Coupon removed']);
    break;

    // ── Buyer: available ────────────────────────────────────────────────────
    case 'available':
        requireLogin();
        $user      = getCurrentUser();
        $userId    = (int)$user['id'];
        $cartItems = getCart($userId);
        $cartTotal = array_sum(array_map(fn($i) => (float)($i['price'] ?? 0) * (int)($i['quantity'] ?? 1), $cartItems));

        $coupons = getAvailableCoupons($userId, $cartTotal);
        couponJsonOut(['success' => true, 'data' => $coupons]);
    break;

    // ── Admin / Supplier: create ─────────────────────────────────────────────
    case 'create':
        requireLogin();
        couponValidateCsrf();
        $user     = getCurrentUser();
        $role     = $user['role'] ?? 'buyer';
        if (!in_array($role, ['admin', 'super_admin', 'supplier'])) {
            couponJsonOut(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $data = [
            'code'                   => couponSanitize($_POST['code'] ?? ''),
            'type'                   => couponSanitize($_POST['type'] ?? 'percentage'),
            'value'                  => (float)($_POST['value'] ?? 0),
            'min_order_amount'       => (float)($_POST['min_order_amount'] ?? 0),
            'max_discount_amount'    => $_POST['max_discount_amount'] ?? '',
            'usage_limit'            => $_POST['usage_limit'] ?? '',
            'per_user_limit'         => (int)($_POST['per_user_limit'] ?? 1),
            'valid_from'             => couponSanitize($_POST['valid_from'] ?? ''),
            'valid_to'               => couponSanitize($_POST['valid_to'] ?? ''),
            'is_active'              => isset($_POST['is_active']) ? (bool)$_POST['is_active'] : true,
            'description'            => couponSanitize($_POST['description'] ?? ''),
            'created_by'             => (int)$user['id'],
            'creator_role'           => in_array($role, ['admin', 'super_admin']) ? 'admin' : 'supplier',
        ];

        // Supplier: scope coupon to their products
        if ($role === 'supplier') {
            $supplierProductIds = array_column(
                $db->prepare("SELECT id FROM products WHERE supplier_id = ? AND status = 'active'")
                    ->execute([(int)$user['id']]) ? [] : [],
                'id'
            );
            try {
                $pStmt = $db->prepare("SELECT id FROM products WHERE supplier_id = ? AND status = 'active'");
                $pStmt->execute([(int)$user['id']]);
                $supplierProductIds = array_column($pStmt->fetchAll(PDO::FETCH_ASSOC), 'id');
            } catch (PDOException $e) {
                $supplierProductIds = [];
            }
            $data['applicable_suppliers'] = [(int)$user['id']];
            if (!empty($_POST['applicable_products'])) {
                $reqProds = array_map('intval', (array)$_POST['applicable_products']);
                $data['applicable_products'] = array_values(array_intersect($reqProds, $supplierProductIds));
            }
        } else {
            if (!empty($_POST['applicable_categories'])) {
                $data['applicable_categories'] = array_map('intval', (array)$_POST['applicable_categories']);
            }
            if (!empty($_POST['applicable_products'])) {
                $data['applicable_products'] = array_map('intval', (array)$_POST['applicable_products']);
            }
            if (!empty($_POST['applicable_suppliers'])) {
                $data['applicable_suppliers'] = array_map('intval', (array)$_POST['applicable_suppliers']);
            }
        }

        try {
            $id = createCoupon($data);
            couponJsonOut(['success' => true, 'message' => 'Coupon created', 'id' => $id], 201);
        } catch (RuntimeException $e) {
            couponJsonOut(['success' => false, 'message' => $e->getMessage()], 400);
        }
    break;

    // ── Admin / Supplier: update ─────────────────────────────────────────────
    case 'update':
        requireLogin();
        couponValidateCsrf();
        $user     = getCurrentUser();
        $role     = $user['role'] ?? '';
        $couponId = (int)($_POST['coupon_id'] ?? $_POST['id'] ?? 0);
        if ($couponId <= 0) couponJsonOut(['success' => false, 'message' => 'coupon_id required'], 400);

        $existing = getCoupon($couponId);
        if (!$existing) couponJsonOut(['success' => false, 'message' => 'Coupon not found'], 404);

        // Only creator or admin may update
        if (!in_array($role, ['admin', 'super_admin'])) {
            if ((int)$existing['created_by'] !== (int)$user['id']) {
                couponJsonOut(['success' => false, 'message' => 'Unauthorized'], 403);
            }
        }

        $fields = ['code','type','value','min_order_amount','max_discount_amount','usage_limit',
                   'per_user_limit','valid_from','valid_to','is_active','description'];
        $data = [];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) $data[$f] = couponSanitize((string)$_POST[$f]);
        }

        try {
            updateCoupon($couponId, $data);
            couponJsonOut(['success' => true, 'message' => 'Coupon updated']);
        } catch (RuntimeException $e) {
            couponJsonOut(['success' => false, 'message' => $e->getMessage()], 400);
        }
    break;

    // ── Admin / Supplier: delete ─────────────────────────────────────────────
    case 'delete':
        requireLogin();
        couponValidateCsrf();
        $user     = getCurrentUser();
        $role     = $user['role'] ?? '';
        $couponId = (int)($_POST['coupon_id'] ?? $_POST['id'] ?? 0);
        if ($couponId <= 0) couponJsonOut(['success' => false, 'message' => 'coupon_id required'], 400);

        $existing = getCoupon($couponId);
        if (!$existing) couponJsonOut(['success' => false, 'message' => 'Coupon not found'], 404);

        if (!in_array($role, ['admin', 'super_admin'])) {
            if ((int)$existing['created_by'] !== (int)$user['id']) {
                couponJsonOut(['success' => false, 'message' => 'Unauthorized'], 403);
            }
        }

        deleteCoupon($couponId);
        couponJsonOut(['success' => true, 'message' => 'Coupon deleted']);
    break;

    // ── Admin / Supplier: list ───────────────────────────────────────────────
    case 'list':
        requireLogin();
        $user  = getCurrentUser();
        $role  = $user['role'] ?? '';
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $filters = [
            'status'       => couponSanitize($_GET['status'] ?? ''),
            'type'         => couponSanitize($_GET['type'] ?? ''),
            'search'       => couponSanitize($_GET['search'] ?? ''),
        ];

        if (in_array($role, ['admin', 'super_admin'])) {
            if (!empty($_GET['creator_role'])) $filters['creator_role'] = couponSanitize($_GET['creator_role']);
        } else {
            // Supplier: only own coupons
            $result = getSupplierCoupons((int)$user['id'], $filters, $page, $perPage);
            couponJsonOut(['success' => true, 'data' => $result]);
        }

        $result = getCoupons($filters, $page, $perPage);
        couponJsonOut(['success' => true, 'data' => $result]);
    break;

    // ── Get single coupon ───────────────────────────────────────────────────
    case 'get':
        requireLogin();
        $couponId = (int)($_GET['coupon_id'] ?? $_GET['id'] ?? 0);
        if ($couponId <= 0) couponJsonOut(['success' => false, 'message' => 'coupon_id required'], 400);
        $coupon = getCoupon($couponId);
        if (!$coupon) couponJsonOut(['success' => false, 'message' => 'Coupon not found'], 404);
        couponJsonOut(['success' => true, 'data' => $coupon]);
    break;

    // ── Stats ────────────────────────────────────────────────────────────────
    case 'stats':
        requireLogin();
        $user     = getCurrentUser();
        $couponId = (int)($_GET['coupon_id'] ?? 0);
        if ($couponId <= 0) couponJsonOut(['success' => false, 'message' => 'coupon_id required'], 400);

        $existing = getCoupon($couponId);
        if (!$existing) couponJsonOut(['success' => false, 'message' => 'Coupon not found'], 404);

        $role = $user['role'] ?? '';
        if (!in_array($role, ['admin', 'super_admin'])
            && (int)$existing['created_by'] !== (int)$user['id']) {
            couponJsonOut(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $usage = getCouponUsage($couponId);
        couponJsonOut(['success' => true, 'data' => $usage]);
    break;

    default:
        couponJsonOut(['success' => false, 'message' => 'Invalid action'], 400);
}
