<?php
/**
 * includes/coupons.php — Coupon & Promotion Library (PR #13)
 *
 * Coupon Types:
 *   percentage   — X% off total (with optional max_discount_amount cap)
 *   fixed        — $X off total
 *   free_shipping — waive shipping fee
 *   bxgy         — Buy X Get Y free
 *
 * Feature toggle: isFeatureEnabled('coupons_promotions')
 */

require_once __DIR__ . '/feature_toggles.php';

// ─────────────────────────────────────────────────────────────────────────────
// Coupon CRUD
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Create a new coupon.
 *
 * @param array $data  Fields: code, type, value, min_order_amount, max_discount_amount,
 *                     usage_limit, per_user_limit, valid_from, valid_to, is_active,
 *                     applicable_categories, applicable_products, applicable_suppliers,
 *                     created_by, creator_role, description, buy_x, get_y
 * @return int  New coupon ID
 * @throws RuntimeException on failure
 */
function createCoupon(array $data): int
{
    if (!isFeatureEnabled('coupons_promotions')) {
        throw new RuntimeException('Coupon system is disabled');
    }
    $db = getDB();

    $code = strtoupper(trim($data['code'] ?? ''));
    if ($code === '') {
        $prefix = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', $data['description'] ?? 'SAVE'), 0, 4));
        $code = generateCouponCode($prefix ?: 'SAVE');
    }

    // Check uniqueness
    $dup = $db->prepare('SELECT id FROM coupons WHERE code = ?');
    $dup->execute([$code]);
    if ($dup->fetch()) {
        throw new RuntimeException('Coupon code already exists: ' . $code);
    }

    $stmt = $db->prepare(
        'INSERT INTO coupons
            (code, type, value, min_order_amount, max_discount_amount,
             usage_limit, per_user_limit, valid_from, valid_to, is_active,
             applicable_categories_json, applicable_products_json, applicable_suppliers_json,
             created_by, creator_role, description, buy_x, get_y)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $code,
        $data['type'] ?? 'percentage',
        (float)($data['value'] ?? 0),
        (float)($data['min_order_amount'] ?? 0),
        isset($data['max_discount_amount']) && $data['max_discount_amount'] !== '' ? (float)$data['max_discount_amount'] : null,
        isset($data['usage_limit']) && $data['usage_limit'] !== '' ? (int)$data['usage_limit'] : null,
        (int)($data['per_user_limit'] ?? 1),
        $data['valid_from'] ?? null,
        $data['valid_to'] ?? null,
        isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1,
        !empty($data['applicable_categories']) ? json_encode($data['applicable_categories']) : null,
        !empty($data['applicable_products']) ? json_encode($data['applicable_products']) : null,
        !empty($data['applicable_suppliers']) ? json_encode($data['applicable_suppliers']) : null,
        (int)($data['created_by'] ?? 0),
        $data['creator_role'] ?? 'admin',
        $data['description'] ?? null,
        (int)($data['buy_x'] ?? 2),
        (int)($data['get_y'] ?? 1),
    ]);
    return (int)$db->lastInsertId();
}

/**
 * Update coupon details.
 *
 * @param int   $couponId
 * @param array $data  Fields to update (same keys as createCoupon)
 * @return bool
 */
function updateCoupon(int $couponId, array $data): bool
{
    if (!isFeatureEnabled('coupons_promotions')) {
        return false;
    }
    $db = getDB();

    $coupon = getCoupon($couponId);
    if (!$coupon) return false;

    if (isset($data['code'])) {
        $newCode = strtoupper(trim($data['code']));
        if ($newCode !== $coupon['code']) {
            $dup = $db->prepare('SELECT id FROM coupons WHERE code = ? AND id != ?');
            $dup->execute([$newCode, $couponId]);
            if ($dup->fetch()) throw new RuntimeException('Coupon code already in use: ' . $newCode);
            $data['code'] = $newCode;
        }
    }

    $sets = [];
    $vals = [];
    $boolFields = ['is_active'];
    $allowed = ['code','type','value','min_order_amount','max_discount_amount','usage_limit',
                'per_user_limit','valid_from','valid_to','is_active','description','buy_x','get_y'];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $data)) {
            $sets[] = "$f = ?";
            if (in_array($f, $boolFields, true)) {
                $vals[] = (int)(bool)$data[$f];
            } else {
                $vals[] = $data[$f] === '' ? null : $data[$f];
            }
        }
    }
    if (array_key_exists('applicable_categories', $data)) {
        $sets[] = 'applicable_categories_json = ?';
        $vals[] = !empty($data['applicable_categories']) ? json_encode($data['applicable_categories']) : null;
    }
    if (array_key_exists('applicable_products', $data)) {
        $sets[] = 'applicable_products_json = ?';
        $vals[] = !empty($data['applicable_products']) ? json_encode($data['applicable_products']) : null;
    }
    if (array_key_exists('applicable_suppliers', $data)) {
        $sets[] = 'applicable_suppliers_json = ?';
        $vals[] = !empty($data['applicable_suppliers']) ? json_encode($data['applicable_suppliers']) : null;
    }
    if (empty($sets)) return true;

    $vals[] = $couponId;
    $db->prepare('UPDATE coupons SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
    return true;
}

/**
 * Soft-delete a coupon.
 *
 * @param int $couponId
 * @return bool
 */
function deleteCoupon(int $couponId): bool
{
    if (!isFeatureEnabled('coupons_promotions')) return false;
    $db = getDB();
    $stmt = $db->prepare('UPDATE coupons SET deleted_at = NOW(), is_active = 0 WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$couponId]);
    return $stmt->rowCount() > 0;
}

/**
 * Get coupon by ID (excluding soft-deleted).
 *
 * @param int $couponId
 * @return array|null
 */
function getCoupon(int $couponId): ?array
{
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM coupons WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$couponId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Look up coupon by code (case-insensitive, active only).
 *
 * @param string $code
 * @return array|null
 */
function getCouponByCode(string $code): ?array
{
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM coupons WHERE code = ? AND deleted_at IS NULL');
    $stmt->execute([strtoupper(trim($code))]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * List coupons with filters.
 *
 * @param array $filters  Keys: status (active/expired/upcoming), type, creator_role, search
 * @param int   $page
 * @param int   $perPage
 * @return array  ['data'=>[], 'total'=>int, 'page'=>int, 'per_page'=>int, 'total_pages'=>int]
 */
function getCoupons(array $filters = [], int $page = 1, int $perPage = 20): array
{
    $db = getDB();
    $where = ['deleted_at IS NULL'];
    $params = [];

    if (!empty($filters['status'])) {
        switch ($filters['status']) {
            case 'active':
                $where[] = 'is_active = 1 AND (valid_from IS NULL OR valid_from <= NOW()) AND (valid_to IS NULL OR valid_to >= NOW())';
                break;
            case 'expired':
                $where[] = '(valid_to IS NOT NULL AND valid_to < NOW())';
                break;
            case 'upcoming':
                $where[] = 'valid_from IS NOT NULL AND valid_from > NOW()';
                break;
        }
    }
    if (!empty($filters['type'])) {
        $where[] = 'type = ?';
        $params[] = $filters['type'];
    }
    if (!empty($filters['creator_role'])) {
        $where[] = 'creator_role = ?';
        $params[] = $filters['creator_role'];
    }
    if (!empty($filters['created_by'])) {
        $where[] = 'created_by = ?';
        $params[] = (int)$filters['created_by'];
    }
    if (!empty($filters['search'])) {
        $where[] = '(code LIKE ? OR description LIKE ?)';
        $like = '%' . $filters['search'] . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $whereStr = implode(' AND ', $where);
    $countStmt = $db->prepare("SELECT COUNT(*) FROM coupons WHERE $whereStr");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $offset = ($page - 1) * $perPage;
    $dataStmt = $db->prepare("SELECT * FROM coupons WHERE $whereStr ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $dataStmt->execute(array_merge($params, [$perPage, $offset]));
    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'data'        => $rows,
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => (int)ceil($total / $perPage),
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// Validation & Application
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Validate a coupon against a cart (full validation).
 *
 * @param string $code
 * @param int    $userId
 * @param array  $cartItems  Each item: ['price'=>float, 'quantity'=>int, 'product_id'=>int, 'category_id'=>int, 'supplier_id'=>int]
 * @param float  $cartTotal
 * @return array  {valid:bool, discount_amount:float, message:string} or {valid:false, error:string}
 */
function validateCoupon(string $code, int $userId, array $cartItems, float $cartTotal): array
{
    if (!isFeatureEnabled('coupons_promotions')) {
        return ['valid' => false, 'error' => 'Coupon system is disabled'];
    }

    $code = strtoupper(trim($code));
    if ($code === '') {
        return ['valid' => false, 'error' => 'Coupon code is required'];
    }

    $coupon = getCouponByCode($code);
    if (!$coupon) {
        return ['valid' => false, 'error' => 'Invalid coupon code'];
    }

    // Active check
    if (!(bool)$coupon['is_active']) {
        return ['valid' => false, 'error' => 'This coupon is no longer active'];
    }

    // Date range
    $now = time();
    if (!empty($coupon['valid_from']) && strtotime($coupon['valid_from']) > $now) {
        return ['valid' => false, 'error' => 'This coupon is not yet valid'];
    }
    if (!empty($coupon['valid_to']) && strtotime($coupon['valid_to']) < $now) {
        return ['valid' => false, 'error' => 'This coupon has expired'];
    }

    // Minimum order amount
    if ($coupon['min_order_amount'] > 0 && $cartTotal < (float)$coupon['min_order_amount']) {
        return [
            'valid' => false,
            'error' => 'Minimum order of $' . number_format((float)$coupon['min_order_amount'], 2) . ' required',
        ];
    }

    $db = getDB();

    // Usage limit (total)
    if ($coupon['usage_limit'] !== null && (int)$coupon['usage_limit'] > 0) {
        $usageStmt = $db->prepare('SELECT COUNT(*) FROM coupon_usages WHERE coupon_id = ?');
        $usageStmt->execute([$coupon['id']]);
        if ((int)$usageStmt->fetchColumn() >= (int)$coupon['usage_limit']) {
            return ['valid' => false, 'error' => 'This coupon has reached its usage limit'];
        }
    }

    // Per-user limit
    if ((int)$coupon['per_user_limit'] > 0 && $userId > 0) {
        $puStmt = $db->prepare('SELECT COUNT(*) FROM coupon_usages WHERE coupon_id = ? AND user_id = ?');
        $puStmt->execute([$coupon['id'], $userId]);
        if ((int)$puStmt->fetchColumn() >= (int)$coupon['per_user_limit']) {
            return ['valid' => false, 'error' => 'You have already used this coupon the maximum number of times'];
        }
    }

    // Applicability check (category / product / supplier)
    $applicableCategories = !empty($coupon['applicable_categories_json'])
        ? (json_decode($coupon['applicable_categories_json'], true) ?: []) : [];
    $applicableProducts = !empty($coupon['applicable_products_json'])
        ? (json_decode($coupon['applicable_products_json'], true) ?: []) : [];
    $applicableSuppliers = !empty($coupon['applicable_suppliers_json'])
        ? (json_decode($coupon['applicable_suppliers_json'], true) ?: []) : [];

    if (!empty($applicableCategories) || !empty($applicableProducts) || !empty($applicableSuppliers)) {
        $matched = false;
        foreach ($cartItems as $item) {
            if (!empty($applicableProducts) && in_array((int)($item['product_id'] ?? 0), $applicableProducts)) {
                $matched = true; break;
            }
            if (!empty($applicableCategories) && in_array((int)($item['category_id'] ?? 0), $applicableCategories)) {
                $matched = true; break;
            }
            if (!empty($applicableSuppliers) && in_array((int)($item['supplier_id'] ?? 0), $applicableSuppliers)) {
                $matched = true; break;
            }
        }
        if (!$matched) {
            return ['valid' => false, 'error' => 'This coupon does not apply to items in your cart'];
        }
    }

    $discountAmount = _calculateDiscountAmount($coupon, $cartItems, $cartTotal);

    return [
        'valid'           => true,
        'discount_amount' => $discountAmount,
        'message'         => sprintf(
            '%s applied! You saved $%s',
            $coupon['code'],
            number_format($discountAmount, 2)
        ),
        'coupon'          => $coupon,
    ];
}

/**
 * Apply coupon and return full discount breakdown.
 *
 * @param string $code
 * @param int    $userId
 * @param array  $cartItems
 * @param float  $cartTotal
 * @return array  {discount_type, discount_value, discount_amount, new_total, message, coupon_id}
 */
function applyCoupon(string $code, int $userId, array $cartItems, float $cartTotal): array
{
    $validation = validateCoupon($code, $userId, $cartItems, $cartTotal);
    if (!$validation['valid']) {
        return $validation;
    }

    $coupon         = $validation['coupon'];
    $discountAmount = $validation['discount_amount'];
    $newTotal       = max(0, $cartTotal - ($coupon['type'] === 'free_shipping' ? 0 : $discountAmount));

    return [
        'valid'          => true,
        'discount_type'  => $coupon['type'],
        'discount_value' => (float)$coupon['value'],
        'discount_amount'=> $discountAmount,
        'new_total'      => $newTotal,
        'free_shipping'  => $coupon['type'] === 'free_shipping',
        'coupon_id'      => (int)$coupon['id'],
        'coupon_code'    => $coupon['code'],
        'message'        => $validation['message'],
    ];
}

/**
 * Record coupon usage after order is placed (atomic increment).
 *
 * @param int   $couponId
 * @param int   $userId
 * @param int   $orderId
 * @param float $discountAmount
 * @return bool
 */
function recordCouponUsage(int $couponId, int $userId, int $orderId, float $discountAmount): bool
{
    $db = getDB();
    try {
        $db->beginTransaction();
        $ins = $db->prepare(
            'INSERT INTO coupon_usages (coupon_id, user_id, order_id, discount_amount) VALUES (?,?,?,?)'
        );
        $ins->execute([$couponId, $userId, $orderId, $discountAmount]);
        // Atomic usage_count increment to prevent race conditions
        $db->prepare('UPDATE coupons SET usage_count = usage_count + 1 WHERE id = ?')->execute([$couponId]);
        $db->commit();
        return true;
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('recordCouponUsage error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get coupon usage statistics.
 *
 * @param int $couponId
 * @return array  {total_used, total_discount, by_user: [...], recent: [...]}
 */
function getCouponUsage(int $couponId): array
{
    $db = getDB();
    try {
        $stmt = $db->prepare(
            'SELECT COUNT(*) total_used, COALESCE(SUM(discount_amount),0) total_discount
             FROM coupon_usages WHERE coupon_id = ?'
        );
        $stmt->execute([$couponId]);
        $agg = $stmt->fetch(PDO::FETCH_ASSOC);

        $byUser = $db->prepare(
            'SELECT cu.user_id, u.email, COUNT(*) times, SUM(cu.discount_amount) total_saved
             FROM coupon_usages cu
             LEFT JOIN users u ON u.id = cu.user_id
             WHERE cu.coupon_id = ?
             GROUP BY cu.user_id
             ORDER BY times DESC LIMIT 10'
        );
        $byUser->execute([$couponId]);

        $recent = $db->prepare(
            'SELECT cu.*, u.email FROM coupon_usages cu
             LEFT JOIN users u ON u.id = cu.user_id
             WHERE cu.coupon_id = ? ORDER BY cu.used_at DESC LIMIT 20'
        );
        $recent->execute([$couponId]);

        return [
            'total_used'     => (int)$agg['total_used'],
            'total_discount' => (float)$agg['total_discount'],
            'by_user'        => $byUser->fetchAll(PDO::FETCH_ASSOC),
            'recent'         => $recent->fetchAll(PDO::FETCH_ASSOC),
        ];
    } catch (PDOException $e) {
        return ['total_used' => 0, 'total_discount' => 0, 'by_user' => [], 'recent' => []];
    }
}

/**
 * Get coupons available for a user/cart (for "Available Coupons" display).
 *
 * @param int   $userId
 * @param float $cartTotal
 * @return array
 */
function getAvailableCoupons(int $userId, float $cartTotal): array
{
    if (!isFeatureEnabled('coupons_promotions')) return [];

    $db = getDB();
    try {
        $stmt = $db->prepare(
            'SELECT c.*
             FROM coupons c
             WHERE c.deleted_at IS NULL
               AND c.is_active = 1
               AND (c.valid_from IS NULL OR c.valid_from <= NOW())
               AND (c.valid_to IS NULL OR c.valid_to >= NOW())
               AND (c.min_order_amount <= ? OR c.min_order_amount = 0)
               AND (c.usage_limit IS NULL OR c.usage_count < c.usage_limit)
             ORDER BY c.value DESC'
        );
        $stmt->execute([$cartTotal]);
        $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Filter by per-user limit
        if ($userId > 0) {
            $filtered = [];
            foreach ($all as $c) {
                if ((int)$c['per_user_limit'] > 0) {
                    $uStmt = $db->prepare('SELECT COUNT(*) FROM coupon_usages WHERE coupon_id = ? AND user_id = ?');
                    $uStmt->execute([$c['id'], $userId]);
                    if ((int)$uStmt->fetchColumn() >= (int)$c['per_user_limit']) {
                        continue;
                    }
                }
                $filtered[] = $c;
            }
            return $filtered;
        }
        return $all;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Generate a unique random coupon code.
 *
 * @param string $prefix
 * @return string  e.g. SAVE-A3F9XZ
 */
function generateCouponCode(string $prefix = 'SAVE'): string
{
    $db = getDB();
    $prefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', $prefix));
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $code   = $prefix . '-' . $suffix;
        $stmt   = $db->prepare('SELECT id FROM coupons WHERE code = ?');
        $stmt->execute([$code]);
    } while ($stmt->fetch());
    return $code;
}

// ─────────────────────────────────────────────────────────────────────────────
// Supplier-scoped helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Get coupons created by a specific supplier.
 *
 * @param int   $supplierId
 * @param array $filters
 * @param int   $page
 * @param int   $perPage
 * @return array
 */
function getSupplierCoupons(int $supplierId, array $filters = [], int $page = 1, int $perPage = 20): array
{
    $filters['created_by']    = $supplierId;
    $filters['creator_role']  = 'supplier';
    return getCoupons($filters, $page, $perPage);
}

// ─────────────────────────────────────────────────────────────────────────────
// Promotions
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Create a promotion.
 *
 * @param array $data  Fields: name, description, discount_type, discount_value,
 *                     start_date, end_date, banner_image, products, categories,
 *                     is_featured, created_by
 * @return int  New promotion ID
 */
function createPromotion(array $data): int
{
    if (!isFeatureEnabled('coupons_promotions')) {
        throw new RuntimeException('Promotion system is disabled');
    }
    $db = getDB();

    $name = trim($data['name'] ?? '');
    if ($name === '') throw new RuntimeException('Promotion name is required');

    $slug = _slugify($name);
    // Ensure unique slug
    $slugBase = $slug;
    $i = 1;
    do {
        $chk = $db->prepare('SELECT id FROM promotions WHERE slug = ?');
        $chk->execute([$slug]);
        if ($chk->fetch()) {
            $slug = $slugBase . '-' . $i++;
        } else {
            break;
        }
    } while (true);

    $stmt = $db->prepare(
        'INSERT INTO promotions
            (name, slug, description, discount_type, discount_value,
             start_date, end_date, banner_image, products_json, categories_json,
             is_featured, is_active, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,1,?)'
    );
    $stmt->execute([
        $name,
        $slug,
        $data['description'] ?? null,
        $data['discount_type'] ?? 'percentage',
        (float)($data['discount_value'] ?? 0),
        $data['start_date'],
        $data['end_date'],
        $data['banner_image'] ?? null,
        !empty($data['products']) ? json_encode($data['products']) : null,
        !empty($data['categories']) ? json_encode($data['categories']) : null,
        isset($data['is_featured']) ? (int)(bool)$data['is_featured'] : 0,
        (int)($data['created_by'] ?? 0),
    ]);
    return (int)$db->lastInsertId();
}

/**
 * Update a promotion.
 *
 * @param int   $promoId
 * @param array $data
 * @return bool
 */
function updatePromotion(int $promoId, array $data): bool
{
    if (!isFeatureEnabled('coupons_promotions')) return false;
    $db = getDB();

    $sets = [];
    $vals = [];
    $allowed = ['name','description','discount_type','discount_value','start_date','end_date',
                'banner_image','is_featured','is_active'];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $data)) {
            $sets[] = "$f = ?";
            $vals[] = $data[$f];
        }
    }
    if (array_key_exists('products', $data)) {
        $sets[] = 'products_json = ?';
        $vals[] = !empty($data['products']) ? json_encode($data['products']) : null;
    }
    if (array_key_exists('categories', $data)) {
        $sets[] = 'categories_json = ?';
        $vals[] = !empty($data['categories']) ? json_encode($data['categories']) : null;
    }
    if (empty($sets)) return true;

    $vals[] = $promoId;
    $db->prepare('UPDATE promotions SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
    return true;
}

/**
 * Delete a promotion (hard delete).
 *
 * @param int $promoId
 * @return bool
 */
function deletePromotion(int $promoId): bool
{
    if (!isFeatureEnabled('coupons_promotions')) return false;
    $db = getDB();
    $stmt = $db->prepare('DELETE FROM promotions WHERE id = ?');
    $stmt->execute([$promoId]);
    return $stmt->rowCount() > 0;
}

/**
 * Get active promotions (for homepage banner).
 *
 * @return array
 */
function getActivePromotions(): array
{
    if (!isFeatureEnabled('coupons_promotions')) return [];
    $db = getDB();
    try {
        $stmt = $db->query(
            'SELECT * FROM promotions
             WHERE is_active = 1 AND start_date <= NOW() AND end_date >= NOW()
             ORDER BY is_featured DESC, start_date ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get upcoming promotions (starting within next 7 days).
 *
 * @return array
 */
function getUpcomingPromotions(): array
{
    if (!isFeatureEnabled('coupons_promotions')) return [];
    $db = getDB();
    try {
        $stmt = $db->query(
            'SELECT * FROM promotions
             WHERE is_active = 1 AND start_date > NOW() AND start_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)
             ORDER BY start_date ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get products associated with a promotion (with discounted prices).
 *
 * @param int $promoId
 * @return array
 */
function getPromotionProducts(int $promoId): array
{
    $db = getDB();
    try {
        $promo = $db->prepare('SELECT * FROM promotions WHERE id = ? AND is_active = 1');
        $promo->execute([$promoId]);
        $promotion = $promo->fetch(PDO::FETCH_ASSOC);
        if (!$promotion) return [];

        $productIds  = json_decode($promotion['products_json'] ?? '[]', true) ?: [];
        $categoryIds = json_decode($promotion['categories_json'] ?? '[]', true) ?: [];

        if (empty($productIds) && empty($categoryIds)) return [];

        $conditions = [];
        $params = [];
        if (!empty($productIds)) {
            $pls = implode(',', array_fill(0, count($productIds), '?'));
            $conditions[] = "p.id IN ($pls)";
            $params = array_merge($params, $productIds);
        }
        if (!empty($categoryIds)) {
            $cls = implode(',', array_fill(0, count($categoryIds), '?'));
            $conditions[] = "p.category_id IN ($cls)";
            $params = array_merge($params, $categoryIds);
        }
        $whereStr = implode(' OR ', $conditions);

        $stmt = $db->prepare(
            "SELECT p.*, s.company_name supplier_name FROM products p
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             WHERE p.status = 'active' AND ($whereStr)"
        );
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add promo price
        foreach ($products as &$p) {
            $p['promo_id']    = $promoId;
            $p['promo_price'] = getPromotionPriceCalc($p['price'] ?? 0, $promotion);
        }
        unset($p);
        return $products;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Check if a product has an active promotion.
 *
 * @param int $productId
 * @return bool
 */
function isProductOnPromotion(int $productId): bool
{
    return getActivePromotionForProduct($productId) !== null;
}

/**
 * Get the promotional price for a product.
 *
 * @param int $productId
 * @return float|null  null if no active promotion
 */
function getPromotionPrice(int $productId): ?float
{
    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT price FROM products WHERE id = ?');
        $stmt->execute([$productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $promo = getActivePromotionForProduct($productId);
        if (!$promo) return null;

        return getPromotionPriceCalc((float)$row['price'], $promo);
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Get the active promotion object for a product (if any).
 *
 * @param int $productId
 * @return array|null
 */
function getActivePromotionForProduct(int $productId): ?array
{
    if (!isFeatureEnabled('coupons_promotions')) return null;
    $db = getDB();
    try {
        // Load product's category
        $pStmt = $db->prepare('SELECT category_id FROM products WHERE id = ?');
        $pStmt->execute([$productId]);
        $product = $pStmt->fetch(PDO::FETCH_ASSOC);
        $categoryId = (int)($product['category_id'] ?? 0);

        $promos = getActivePromotions();
        foreach ($promos as $promo) {
            $productIds  = json_decode($promo['products_json'] ?? '[]', true) ?: [];
            $categoryIds = json_decode($promo['categories_json'] ?? '[]', true) ?: [];
            if (in_array($productId, $productIds)) return $promo;
            if ($categoryId > 0 && in_array($categoryId, $categoryIds)) return $promo;
            if (empty($productIds) && empty($categoryIds)) return $promo; // applies to all
        }
    } catch (PDOException $e) {
        // ignore
    }
    return null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Admin analytics
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Get admin-level coupon analytics overview.
 *
 * @return array
 */
function getCouponAnalytics(): array
{
    $db = getDB();
    try {
        $overview = $db->query(
            'SELECT
                COUNT(*)                                           total_coupons,
                SUM(is_active = 1 AND deleted_at IS NULL
                    AND (valid_to IS NULL OR valid_to >= NOW()))   active_coupons,
                SUM(usage_count)                                   total_uses,
                0                                                  total_discount_given
             FROM coupons WHERE deleted_at IS NULL'
        )->fetch(PDO::FETCH_ASSOC);

        $discountRow = $db->query(
            'SELECT COALESCE(SUM(discount_amount),0) total FROM coupon_usages'
        )->fetch(PDO::FETCH_ASSOC);
        $overview['total_discount_given'] = (float)($discountRow['total'] ?? 0);

        $topCoupons = $db->query(
            'SELECT c.code, c.type, COUNT(cu.id) uses,
                    COALESCE(SUM(cu.discount_amount),0) discount_given
             FROM coupons c
             LEFT JOIN coupon_usages cu ON cu.coupon_id = c.id
             WHERE c.deleted_at IS NULL
             GROUP BY c.id
             ORDER BY uses DESC LIMIT 10'
        )->fetchAll(PDO::FETCH_ASSOC);

        return [
            'overview'    => $overview,
            'top_coupons' => $topCoupons,
        ];
    } catch (PDOException $e) {
        return ['overview' => [], 'top_coupons' => []];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Internal helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Calculate discount amount for a coupon against a cart.
 */
function _calculateDiscountAmount(array $coupon, array $cartItems, float $cartTotal): float
{
    $type  = $coupon['type'];
    $value = (float)$coupon['value'];

    switch ($type) {
        case 'percentage':
            $discount = round($cartTotal * $value / 100, 2);
            if (!empty($coupon['max_discount_amount']) && $discount > (float)$coupon['max_discount_amount']) {
                $discount = (float)$coupon['max_discount_amount'];
            }
            return $discount;

        case 'fixed':
            return min($value, $cartTotal);

        case 'free_shipping':
            return 0.0; // handled at shipping level

        case 'bxgy':
            $buyX  = max(1, (int)$coupon['buy_x']);
            $getY  = max(1, (int)$coupon['get_y']);
            $prices = [];
            foreach ($cartItems as $item) {
                $price = (float)($item['price'] ?? $item['unit_price'] ?? 0);
                $qty   = (int)($item['quantity'] ?? 1);
                for ($i = 0; $i < $qty; $i++) {
                    $prices[] = $price;
                }
            }
            sort($prices);
            $totalQty = count($prices);
            $sets     = (int)floor($totalQty / ($buyX + $getY));
            $freeItems = array_slice($prices, 0, $sets * $getY);
            return round(array_sum($freeItems), 2);

        default:
            return 0.0;
    }
}

/**
 * Calculate promotional price for a product price + promotion config.
 */
function getPromotionPriceCalc(float $originalPrice, array $promo): float
{
    if ($promo['discount_type'] === 'percentage') {
        return round($originalPrice * (1 - (float)$promo['discount_value'] / 100), 2);
    }
    return max(0, round($originalPrice - (float)$promo['discount_value'], 2));
}

/**
 * Simple slug generator.
 */
function _slugify(string $text): string
{
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}
