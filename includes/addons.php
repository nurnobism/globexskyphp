<?php
/**
 * includes/addons.php — Add-On Management Library (PR #10)
 *
 * Add-on types and pricing:
 *   extra_product_slot  — $0.50/slot  (permanent, adds to plan limit)
 *   extra_image_slot    — $0.10/slot  (permanent, more images per product)
 *   product_boost       — $5/product  (7-day search ranking boost)
 *   featured_listing    — $25/week    (homepage featured section)
 *   livestream_session  — $10/session (one livestream event)
 *   api_calls_pack      — $1/1000     (monthly API quota top-up)
 *   translation_credit  — $2/product/lang (AI translation credit)
 */

/**
 * Get all active add-ons from the catalog.
 */
function getAddons(): array
{
    $db = getDB();
    try {
        $stmt = $db->query('SELECT * FROM addons WHERE is_active = 1 ORDER BY sort_order ASC');
        return $stmt->fetchAll() ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get a single add-on by ID or slug.
 */
function getAddon(int|string $addonId): array|false
{
    $db = getDB();
    try {
        if (is_int($addonId) || ctype_digit((string)$addonId)) {
            $stmt = $db->prepare('SELECT * FROM addons WHERE id = ? AND is_active = 1');
            $stmt->execute([(int)$addonId]);
        } else {
            $stmt = $db->prepare('SELECT * FROM addons WHERE slug = ? AND is_active = 1');
            $stmt->execute([$addonId]);
        }
        return $stmt->fetch() ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Purchase an add-on for a supplier.
 *
 * @param int    $supplierId  Supplier user ID
 * @param int    $addonId     Add-on ID from the addons table
 * @param int    $quantity    Number of units to purchase
 * @param array  $options     ['target_product_id' => int, 'stripe_payment_id' => string]
 * @return array ['success'=>bool, 'purchase_id'=>int, 'invoice_id'=>int, 'error'=>string]
 */
function purchaseAddon(int $supplierId, int $addonId, int $quantity = 1, array $options = []): array
{
    if ($quantity < 1) return ['success' => false, 'error' => 'Quantity must be at least 1'];

    $addon = getAddon($addonId);
    if (!$addon) return ['success' => false, 'error' => 'Add-on not found'];

    $unitPrice  = (float)$addon['price'];
    $totalPrice = round($unitPrice * $quantity, 2);

    $targetProductId = isset($options['target_product_id']) ? (int)$options['target_product_id'] : null;
    $stripePaymentId = $options['stripe_payment_id'] ?? null;

    $db = getDB();
    try {
        $db->beginTransaction();

        // Determine expiry for time-limited add-ons
        $expiresAt = null;
        if (!empty($addon['duration_days'])) {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . (int)$addon['duration_days'] . ' days'));
        }

        // Create purchase record
        $stmt = $db->prepare('INSERT INTO addon_purchases
            (supplier_id, addon_id, quantity, target_product_id, unit_price, total_price,
             status, stripe_payment_id, activated_at, expires_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, "active", ?, NOW(), ?, NOW())');
        $stmt->execute([
            $supplierId, $addonId, $quantity, $targetProductId,
            $unitPrice, $totalPrice, $stripePaymentId, $expiresAt,
        ]);
        $purchaseId = (int)$db->lastInsertId();

        // Apply add-on effect
        _applyAddonEffect($db, $supplierId, $addon, $quantity, $targetProductId);

        // Generate invoice
        $items = [[
            'description' => $addon['name'],
            'quantity'    => $quantity,
            'unit_price'  => $unitPrice,
            'total'       => $totalPrice,
        ]];
        $invoiceId = _createInvoice($db, $supplierId, 'addon_purchase', $items, $totalPrice, $stripePaymentId);

        $db->commit();

        return [
            'success'     => true,
            'purchase_id' => $purchaseId,
            'invoice_id'  => $invoiceId,
            'total'       => $totalPrice,
        ];
    } catch (PDOException $e) {
        $db->rollBack();
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Apply the effect of an add-on after purchase.
 */
function _applyAddonEffect(PDO $db, int $supplierId, array $addon, int $quantity, ?int $targetProductId): void
{
    $type = $addon['type'];

    if (in_array($type, ['api_calls_pack', 'translation_credit', 'livestream_session'], true)) {
        // Credit-based add-ons — add to addon_credits
        $credits = $quantity;
        if ($type === 'api_calls_pack') $credits = $quantity * 1000;
        _addCredits($db, $supplierId, $type, $credits);
    }
    // extra_product_slot, extra_image_slot, product_boost, featured_listing
    // are represented as active addon_purchases rows — getEffectiveLimit() reads them
}

/**
 * Add credits to a supplier's credit pool.
 */
function _addCredits(PDO $db, int $supplierId, string $addonType, int $credits): void
{
    $stmt = $db->prepare('INSERT INTO addon_credits (supplier_id, addon_type, credits_total, credits_used)
        VALUES (?, ?, ?, 0)
        ON DUPLICATE KEY UPDATE credits_total = credits_total + VALUES(credits_total), updated_at = NOW()');
    $stmt->execute([$supplierId, $addonType, $credits]);
}

/**
 * Create an invoice record and return its ID.
 */
function _createInvoice(PDO $db, int $supplierId, string $type, array $items, float $total, ?string $paymentRef): int
{
    require_once __DIR__ . '/invoices.php';
    return generateInvoice([
        'supplier_id'    => $supplierId,
        'type'           => $type,
        'items'          => $items,
        'subtotal'       => $total,
        'tax_amount'     => 0,
        'total'          => $total,
        'currency'       => 'USD',
        'status'         => $paymentRef ? 'paid' : 'pending',
        'payment_method' => $paymentRef ? 'stripe' : null,
        'payment_ref'    => $paymentRef,
    ], $db);
}

/**
 * Get all active add-ons for a supplier.
 */
function getSupplierAddons(int $supplierId): array
{
    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT ap.*, a.name, a.slug, a.type, a.icon, a.description
            FROM addon_purchases ap
            JOIN addons a ON a.id = ap.addon_id
            WHERE ap.supplier_id = ?
              AND ap.status = "active"
              AND (ap.expires_at IS NULL OR ap.expires_at > NOW())
            ORDER BY ap.created_at DESC');
        $stmt->execute([$supplierId]);
        return $stmt->fetchAll() ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get paginated purchase history for a supplier.
 */
function getAddonPurchaseHistory(int $supplierId, int $page = 1, int $perPage = 20): array
{
    $db     = getDB();
    $offset = ($page - 1) * $perPage;
    try {
        $countStmt = $db->prepare('SELECT COUNT(*) FROM addon_purchases WHERE supplier_id = ?');
        $countStmt->execute([$supplierId]);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $db->prepare('SELECT ap.*, a.name, a.slug, a.type, a.icon
            FROM addon_purchases ap
            JOIN addons a ON a.id = ap.addon_id
            WHERE ap.supplier_id = ?
            ORDER BY ap.created_at DESC
            LIMIT ? OFFSET ?');
        $stmt->execute([$supplierId, $perPage, $offset]);
        $rows = $stmt->fetchAll() ?: [];

        return ['total' => $total, 'page' => $page, 'per_page' => $perPage, 'rows' => $rows];
    } catch (PDOException $e) {
        return ['total' => 0, 'page' => $page, 'per_page' => $perPage, 'rows' => []];
    }
}

/**
 * Check if a specific add-on is currently active for a supplier/target.
 *
 * @param int    $supplierId
 * @param string $addonType    e.g. 'product_boost'
 * @param int    $targetId     e.g. product ID (0 = any)
 */
function isAddonActive(int $supplierId, string $addonType, int $targetId = 0): bool
{
    $db = getDB();
    try {
        if ($targetId > 0) {
            $stmt = $db->prepare('SELECT COUNT(*) FROM addon_purchases ap
                JOIN addons a ON a.id = ap.addon_id
                WHERE ap.supplier_id = ? AND a.type = ? AND ap.target_product_id = ?
                  AND ap.status = "active"
                  AND (ap.expires_at IS NULL OR ap.expires_at > NOW())');
            $stmt->execute([$supplierId, $addonType, $targetId]);
        } else {
            $stmt = $db->prepare('SELECT COUNT(*) FROM addon_purchases ap
                JOIN addons a ON a.id = ap.addon_id
                WHERE ap.supplier_id = ? AND a.type = ?
                  AND ap.status = "active"
                  AND (ap.expires_at IS NULL OR ap.expires_at > NOW())');
            $stmt->execute([$supplierId, $addonType]);
        }
        return (int)$stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Cron: expire time-limited add-ons that have passed their expiry date.
 * Returns number of rows expired.
 */
function expireAddons(): int
{
    $db = getDB();
    try {
        $stmt = $db->prepare('UPDATE addon_purchases SET status = "expired"
            WHERE status = "active" AND expires_at IS NOT NULL AND expires_at < NOW()');
        $stmt->execute();
        return $stmt->rowCount();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Get currently boosted products for a supplier.
 */
function getActiveBoosts(int $supplierId): array
{
    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT ap.*, p.name AS product_name
            FROM addon_purchases ap
            JOIN addons a ON a.id = ap.addon_id
            LEFT JOIN products p ON p.id = ap.target_product_id
            WHERE ap.supplier_id = ? AND a.type = "product_boost"
              AND ap.status = "active" AND ap.expires_at > NOW()
            ORDER BY ap.expires_at ASC');
        $stmt->execute([$supplierId]);
        return $stmt->fetchAll() ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get currently featured products for a supplier.
 */
function getActiveFeatured(int $supplierId): array
{
    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT ap.*, p.name AS product_name
            FROM addon_purchases ap
            JOIN addons a ON a.id = ap.addon_id
            LEFT JOIN products p ON p.id = ap.target_product_id
            WHERE ap.supplier_id = ? AND a.type = "featured_listing"
              AND ap.status = "active" AND ap.expires_at > NOW()
            ORDER BY ap.expires_at ASC');
        $stmt->execute([$supplierId]);
        return $stmt->fetchAll() ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get effective limit for a supplier key, factoring in purchased add-on slots.
 *
 * @param int    $supplierId
 * @param string $limitKey   e.g. 'products', 'images_per_product'
 * @return int  -1 = unlimited
 */
function getEffectiveLimit(int $supplierId, string $limitKey): int
{
    // Get base plan limit
    if (function_exists('getSupplierPlan')) {
        $plan      = getSupplierPlan($supplierId);
        $baseLimit = (int)($plan['limits_decoded'][$limitKey] ?? 0);
    } else {
        $defaults  = ['products' => 10, 'images_per_product' => 3];
        $baseLimit = (int)($defaults[$limitKey] ?? 0);
    }

    if ($baseLimit < 0) return -1; // already unlimited

    // Map limit key to add-on type
    $addonTypeMap = [
        'products'           => 'extra_product_slot',
        'images_per_product' => 'extra_image_slot',
    ];

    $addonType = $addonTypeMap[$limitKey] ?? null;
    if (!$addonType) return $baseLimit;

    // Sum purchased add-on slots
    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT COALESCE(SUM(ap.quantity), 0)
            FROM addon_purchases ap
            JOIN addons a ON a.id = ap.addon_id
            WHERE ap.supplier_id = ? AND a.type = ?
              AND ap.status = "active"
              AND (ap.expires_at IS NULL OR ap.expires_at > NOW())');
        $stmt->execute([$supplierId, $addonType]);
        $extra = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        $extra = 0;
    }

    return $baseLimit + $extra;
}

/**
 * Consume credits for a supplier (API calls, translations, etc.).
 *
 * @param int    $supplierId
 * @param string $addonType  e.g. 'api_calls_pack'
 * @param int    $amount     Credits to consume
 * @return bool  false if insufficient credits
 */
function consumeAddonCredit(int $supplierId, string $addonType, int $amount = 1): bool
{
    if ($amount <= 0) return true;

    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT id, credits_total, credits_used FROM addon_credits
            WHERE supplier_id = ? AND addon_type = ?');
        $stmt->execute([$supplierId, $addonType]);
        $row = $stmt->fetch();
        if (!$row) return false;

        $remaining = (int)$row['credits_total'] - (int)$row['credits_used'];
        if ($remaining < $amount) return false;

        $upd = $db->prepare('UPDATE addon_credits SET credits_used = credits_used + ?, updated_at = NOW()
            WHERE id = ?');
        $upd->execute([$amount, $row['id']]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get remaining credits for a supplier/type.
 */
function getRemainingCredits(int $supplierId, string $addonType): int
{
    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT credits_total - credits_used FROM addon_credits
            WHERE supplier_id = ? AND addon_type = ?');
        $stmt->execute([$supplierId, $addonType]);
        return max(0, (int)$stmt->fetchColumn());
    } catch (PDOException $e) {
        return 0;
    }
}
