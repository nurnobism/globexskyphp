<?php
/**
 * includes/variations.php — Product Variation & SKU Matrix Helper Library
 *
 * Implements Taobao/Alibaba-style product variation management:
 *   - Up to 3 variation dimensions (e.g. Color × Size × Material)
 *   - Auto-generated SKU matrix from dimension combinations
 *   - Per-SKU price override, stock, weight, image
 *
 * Database tables used (schema.sql + schema_v12_variations.sql):
 *   product_variations       — variation types (e.g. "Color", "Size")
 *   product_variation_options — variation values (e.g. "Red", "S", "M")
 *   product_skus             — generated SKU rows
 *   product_sku_options      — maps SKU → variation option combos
 *
 * Security: all write functions verify product ownership via supplier_id.
 * Use supplier_id = 0 to skip ownership check (admin context).
 */

require_once __DIR__ . '/feature_toggles.php';

// ---------------------------------------------------------------------------
// Variation Type CRUD
// ---------------------------------------------------------------------------

/**
 * Get all variation types (with their option values) for a product.
 *
 * @param  int   $productId
 * @return array Array of variation type rows, each with 'options' sub-array
 */
function getVariationTypes(int $productId): array
{
    $db = getDB();
    $stmt = $db->prepare(
        'SELECT * FROM product_variations WHERE product_id = ? ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute([$productId]);
    $types = $stmt->fetchAll();

    foreach ($types as &$type) {
        $oStmt = $db->prepare(
            'SELECT * FROM product_variation_options WHERE variation_id = ? ORDER BY sort_order ASC, id ASC'
        );
        $oStmt->execute([$type['id']]);
        $type['options'] = $oStmt->fetchAll();
    }
    unset($type);

    return $types;
}

/**
 * Add a variation type with its values to a product.
 *
 * Enforces: max 3 variation types per product.
 *
 * @param  int    $productId
 * @param  int    $supplierId  Ownership check; 0 = admin bypass
 * @param  string $typeName    e.g. "Color"
 * @param  array  $values      e.g. ["Red", "Blue", "Black"]
 * @return int    New variation type ID
 * @throws RuntimeException on validation/permission failure
 */
function addVariationType(int $productId, int $supplierId, string $typeName, array $values): int
{
    if (!isFeatureEnabled('product_listing')) {
        throw new RuntimeException('Product listing is currently disabled.');
    }

    $db = getDB();

    _verifyProductOwnership($db, $productId, $supplierId);

    $typeName = trim($typeName);
    if ($typeName === '') {
        throw new RuntimeException('Variation type name is required.');
    }

    // Max 3 variation types per product
    $countStmt = $db->prepare('SELECT COUNT(*) FROM product_variations WHERE product_id = ?');
    $countStmt->execute([$productId]);
    if ((int)$countStmt->fetchColumn() >= 3) {
        throw new RuntimeException('Maximum of 3 variation types allowed per product.');
    }

    // Validate values
    $values = _filterValues($values);
    if (empty($values)) {
        throw new RuntimeException('At least one variation value is required.');
    }

    // Get next sort order
    $sortStmt = $db->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_variations WHERE product_id = ?');
    $sortStmt->execute([$productId]);
    $sortOrder = (int)$sortStmt->fetchColumn();

    $db->prepare('INSERT INTO product_variations (product_id, name, sort_order) VALUES (?, ?, ?)')
       ->execute([$productId, $typeName, $sortOrder]);
    $typeId = (int)$db->lastInsertId();

    _insertVariationOptions($db, $typeId, $values);

    return $typeId;
}

/**
 * Update an existing variation type's name and/or values.
 * Replaces all existing option values with the new list.
 *
 * @param  int    $typeId
 * @param  int    $supplierId  0 = admin bypass
 * @param  string $typeName
 * @param  array  $values
 * @return bool
 * @throws RuntimeException
 */
function updateVariationType(int $typeId, int $supplierId, string $typeName, array $values): bool
{
    $db = getDB();

    $type = _getVariationTypeRow($db, $typeId);
    if (!$type) {
        throw new RuntimeException('Variation type not found.');
    }
    _verifyProductOwnership($db, (int)$type['product_id'], $supplierId);

    $typeName = trim($typeName);
    if ($typeName === '') {
        throw new RuntimeException('Variation type name is required.');
    }

    $values = _filterValues($values);
    if (empty($values)) {
        throw new RuntimeException('At least one variation value is required.');
    }

    $db->prepare('UPDATE product_variations SET name = ? WHERE id = ?')
       ->execute([$typeName, $typeId]);

    // Replace options
    $db->prepare('DELETE FROM product_variation_options WHERE variation_id = ?')
       ->execute([$typeId]);
    _insertVariationOptions($db, $typeId, $values);

    return true;
}

/**
 * Delete a variation type (cascades to options and SKU option mappings).
 * Also deletes all generated SKUs for the product (they are now invalid).
 *
 * @param  int $typeId
 * @param  int $supplierId  0 = admin bypass
 * @return bool
 * @throws RuntimeException
 */
function deleteVariationType(int $typeId, int $supplierId): bool
{
    $db = getDB();

    $type = _getVariationTypeRow($db, $typeId);
    if (!$type) {
        throw new RuntimeException('Variation type not found.');
    }
    $productId = (int)$type['product_id'];
    _verifyProductOwnership($db, $productId, $supplierId);

    // Remove all SKUs for this product (variations changed → matrix invalid)
    _deleteAllSkus($db, $productId);

    // Delete the variation type (CASCADE removes options + sku_options)
    $db->prepare('DELETE FROM product_variations WHERE id = ?')->execute([$typeId]);

    return true;
}

// ---------------------------------------------------------------------------
// SKU Matrix Generation
// ---------------------------------------------------------------------------

/**
 * Auto-generate all combinations of variation values into SKU rows.
 *
 * Supports 1, 2, or 3 variation dimensions.
 * SKU code format: PRD-{productId}-{VAL1}[-{VAL2}[-{VAL3}]]
 * Value abbreviation: first 3 uppercase chars of each value.
 *
 * Example: Color [Red, Blue] × Size [S, M, L] = 6 SKUs:
 *   PRD-5-RED-S, PRD-5-RED-M, PRD-5-RED-L, PRD-5-BLU-S, ...
 *
 * Existing SKUs for the product are deleted before regeneration.
 * Base price from the product row is used as the default SKU price.
 *
 * @param  int $productId
 * @param  int $supplierId  0 = admin bypass
 * @return array Generated SKU rows (from getSkuMatrix)
 * @throws RuntimeException
 */
function generateSkuMatrix(int $productId, int $supplierId): array
{
    $db = getDB();

    _verifyProductOwnership($db, $productId, $supplierId);

    $types = getVariationTypes($productId);
    if (empty($types)) {
        throw new RuntimeException('No variation types defined for this product. Add variation types first.');
    }

    // Get base price
    $priceStmt = $db->prepare('SELECT price FROM products WHERE id = ?');
    $priceStmt->execute([$productId]);
    $basePrice = (float)($priceStmt->fetchColumn() ?: 0);

    // Delete existing SKUs
    _deleteAllSkus($db, $productId);

    // Build combos: array of [optionId1, optionId2?, optionId3?]
    $optionSets = [];
    foreach ($types as $type) {
        $set = [];
        foreach ($type['options'] as $opt) {
            $set[] = ['typeId' => (int)$type['id'], 'optId' => (int)$opt['id'], 'value' => $opt['value']];
        }
        $optionSets[] = $set;
    }

    $combos = _cartesianProduct($optionSets);

    $insertSku = $db->prepare(
        'INSERT INTO product_skus (product_id, sku_code, price, stock, is_active, created_at, updated_at)
         VALUES (?, ?, ?, 0, 1, NOW(), NOW())'
    );
    $insertSkuOpt = $db->prepare(
        'INSERT INTO product_sku_options (sku_id, variation_id, option_id) VALUES (?, ?, ?)'
    );

    foreach ($combos as $combo) {
        $parts = [];
        foreach ($combo as $item) {
            $parts[] = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $item['value']), 0, 3));
        }
        $skuCode = 'PRD-' . $productId . '-' . implode('-', $parts);

        $insertSku->execute([$productId, $skuCode, $basePrice]);
        $skuId = (int)$db->lastInsertId();

        foreach ($combo as $item) {
            $insertSkuOpt->execute([$skuId, $item['typeId'], $item['optId']]);
        }
    }

    return getSkuMatrix($productId);
}

// ---------------------------------------------------------------------------
// SKU Retrieval
// ---------------------------------------------------------------------------

/**
 * Get all generated SKUs for a product, with their variation option details.
 *
 * Each SKU row includes a 'variation_options' key: an array of
 * {type_name, option_value} objects.
 *
 * @param  int  $productId
 * @return array
 */
function getSkuMatrix(int $productId): array
{
    $db = getDB();

    $stmt = $db->prepare(
        'SELECT s.*
         FROM   product_skus s
         WHERE  s.product_id = ?
         ORDER  BY s.id ASC'
    );
    $stmt->execute([$productId]);
    $skus = $stmt->fetchAll();

    foreach ($skus as &$sku) {
        $oStmt = $db->prepare(
            'SELECT pv.name type_name, pvo.value option_value, pvo.id option_id, pv.id type_id
             FROM   product_sku_options pso
             JOIN   product_variation_options pvo ON pvo.id = pso.option_id
             JOIN   product_variations        pv  ON pv.id  = pso.variation_id
             WHERE  pso.sku_id = ?
             ORDER  BY pv.sort_order ASC, pv.id ASC'
        );
        $oStmt->execute([$sku['id']]);
        $sku['variation_options'] = $oStmt->fetchAll();
    }
    unset($sku);

    return $skus;
}

/**
 * Look up a specific SKU by a set of selected option IDs.
 * Used by cart/checkout to find the exact SKU for an order.
 *
 * @param  int   $productId
 * @param  array $optionIds  Array of option IDs (one per variation type)
 * @return array|null  SKU row or null if not found
 */
function getSkuByVariation(int $productId, array $optionIds): ?array
{
    if (empty($optionIds)) return null;

    $db    = getDB();
    $count = count($optionIds);

    // Find SKUs for this product that have exactly these option IDs
    $placeholders = implode(',', array_fill(0, $count, '?'));
    $sql = "SELECT s.*
            FROM   product_skus s
            WHERE  s.product_id = ?
              AND  s.is_active  = 1
              AND  (SELECT COUNT(*) FROM product_sku_options pso
                    WHERE pso.sku_id = s.id AND pso.option_id IN ($placeholders)) = ?";

    $params = array_merge([$productId], array_map('intval', $optionIds), [$count]);
    $stmt   = $db->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Get variation options with availability/stock info — for the buyer product page.
 *
 * Returns each variation type with its options, and for each option:
 *   - is_available: bool (at least one active SKU with this option has stock > 0)
 *   - stock_total: total stock across all active SKUs using this option
 *
 * @param  int   $productId
 * @return array
 */
function getAvailableVariations(int $productId): array
{
    $db    = getDB();
    $types = getVariationTypes($productId);

    foreach ($types as &$type) {
        foreach ($type['options'] as &$opt) {
            $stmt = $db->prepare(
                'SELECT COALESCE(SUM(s.stock), 0) total_stock
                 FROM   product_sku_options pso
                 JOIN   product_skus s ON s.id = pso.sku_id
                 WHERE  pso.option_id = ? AND s.product_id = ? AND s.is_active = 1'
            );
            $stmt->execute([$opt['id'], $productId]);
            $totalStock = (int)$stmt->fetchColumn();

            $opt['is_available'] = $totalStock > 0;
            $opt['stock_total']  = $totalStock;
        }
        unset($opt);
    }
    unset($type);

    return $types;
}

// ---------------------------------------------------------------------------
// SKU Updates
// ---------------------------------------------------------------------------

/**
 * Update a single SKU's editable fields.
 *
 * Updatable fields: sku_code, price, stock, weight_override, image_url, is_active
 *
 * @param  int   $skuId
 * @param  int   $supplierId  0 = admin bypass
 * @param  array $data
 * @return bool
 * @throws RuntimeException
 */
function updateSku(int $skuId, int $supplierId, array $data): bool
{
    $db = getDB();

    $sku = _getSkuRow($db, $skuId);
    if (!$sku) {
        throw new RuntimeException('SKU not found.');
    }
    _verifyProductOwnership($db, (int)$sku['product_id'], $supplierId);

    $sets   = [];
    $params = [];

    if (array_key_exists('sku_code', $data)) {
        $sets[]   = 'sku_code = ?';
        $params[] = trim($data['sku_code']) ?: null;
    }
    if (array_key_exists('price', $data)) {
        $price = $data['price'] === '' || $data['price'] === null ? null : (float)$data['price'];
        $sets[]   = 'price = ?';
        $params[] = $price !== null ? max(0, $price) : 0;
    }
    if (array_key_exists('stock', $data)) {
        $sets[]   = 'stock = ?';
        $params[] = max(0, (int)$data['stock']);
    }
    if (array_key_exists('weight_override', $data)) {
        $weight = ($data['weight_override'] === '' || $data['weight_override'] === null) ? null : (float)$data['weight_override'];
        $sets[]   = 'weight_override = ?';
        $params[] = $weight !== null ? max(0, $weight) : null;
    }
    if (array_key_exists('image_url', $data)) {
        $sets[]   = 'image_url = ?';
        $params[] = trim($data['image_url']) ?: null;
    }
    if (array_key_exists('is_active', $data)) {
        $sets[]   = 'is_active = ?';
        $params[] = $data['is_active'] ? 1 : 0;
    }

    if (empty($sets)) return true;

    $sets[]   = 'updated_at = NOW()';
    $params[] = $skuId;

    $db->prepare('UPDATE product_skus SET ' . implode(', ', $sets) . ' WHERE id = ?')
       ->execute($params);

    return true;
}

/**
 * Bulk-update all SKUs for a product at once.
 *
 * @param  int   $productId
 * @param  int   $supplierId  0 = admin bypass
 * @param  array $skusData    Array of SKU data arrays, each must include 'id'
 * @return int   Number of SKUs updated
 * @throws RuntimeException
 */
function bulkUpdateSkus(int $productId, int $supplierId, array $skusData): int
{
    $db = getDB();
    _verifyProductOwnership($db, $productId, $supplierId);

    $updated = 0;
    foreach ($skusData as $skuData) {
        $skuId = isset($skuData['id']) ? (int)$skuData['id'] : 0;
        if (!$skuId) continue;

        // Verify this SKU belongs to the product
        $check = $db->prepare('SELECT id FROM product_skus WHERE id = ? AND product_id = ?');
        $check->execute([$skuId, $productId]);
        if (!$check->fetch()) continue;

        updateSku($skuId, 0, $skuData); // use 0 = admin bypass since we already verified ownership
        $updated++;
    }
    return $updated;
}

/**
 * Soft-delete (deactivate) a single SKU.
 *
 * @param  int $skuId
 * @param  int $supplierId  0 = admin bypass
 * @return bool
 * @throws RuntimeException
 */
function deleteSku(int $skuId, int $supplierId): bool
{
    $db  = getDB();
    $sku = _getSkuRow($db, $skuId);
    if (!$sku) {
        throw new RuntimeException('SKU not found.');
    }
    _verifyProductOwnership($db, (int)$sku['product_id'], $supplierId);

    $db->prepare('UPDATE product_skus SET is_active = 0, updated_at = NOW() WHERE id = ?')
       ->execute([$skuId]);

    return true;
}

// ---------------------------------------------------------------------------
// Validation
// ---------------------------------------------------------------------------

/**
 * Validate variation input data before write operations.
 *
 * @param  array $data  Keys: type_name (string), values (array)
 * @return array  Array of error strings (empty = valid)
 */
function validateVariationData(array $data): array
{
    $errors = [];

    $typeName = trim($data['type_name'] ?? '');
    if ($typeName === '') {
        $errors[] = 'Variation type name is required.';
    } elseif (mb_strlen($typeName) > 100) {
        $errors[] = 'Variation type name must not exceed 100 characters.';
    }

    $values = $data['values'] ?? [];
    if (!is_array($values)) {
        $errors[] = 'Values must be an array.';
    } else {
        $values = _filterValues($values);
        if (empty($values)) {
            $errors[] = 'At least one non-empty value is required.';
        }
        foreach ($values as $v) {
            if (mb_strlen($v) > 200) {
                $errors[] = "Value \"{$v}\" exceeds 200 characters.";
            }
        }
    }

    return $errors;
}

// ---------------------------------------------------------------------------
// Internal helpers
// ---------------------------------------------------------------------------

/** Verify that a product belongs to the given supplier (or skip if supplierId=0). */
function _verifyProductOwnership(PDO $db, int $productId, int $supplierId): void
{
    if ($supplierId === 0) return;
    $stmt = $db->prepare('SELECT id FROM products WHERE id = ? AND supplier_id = ?');
    $stmt->execute([$productId, $supplierId]);
    if (!$stmt->fetch()) {
        throw new RuntimeException('Product not found or access denied.');
    }
}

/** Fetch a single variation type row. */
function _getVariationTypeRow(PDO $db, int $typeId): array|false
{
    $stmt = $db->prepare('SELECT * FROM product_variations WHERE id = ?');
    $stmt->execute([$typeId]);
    return $stmt->fetch();
}

/** Fetch a single SKU row. */
function _getSkuRow(PDO $db, int $skuId): array|false
{
    $stmt = $db->prepare('SELECT * FROM product_skus WHERE id = ?');
    $stmt->execute([$skuId]);
    return $stmt->fetch();
}

/** Insert variation option values for a type. */
function _insertVariationOptions(PDO $db, int $typeId, array $values): void
{
    $stmt = $db->prepare(
        'INSERT INTO product_variation_options (variation_id, value, sort_order) VALUES (?, ?, ?)'
    );
    foreach (array_values($values) as $i => $value) {
        $stmt->execute([$typeId, $value, $i]);
    }
}

/** Filter values: trim, remove empties, deduplicate, max 50 values. */
function _filterValues(array $values): array
{
    $filtered = [];
    $seen     = [];
    foreach ($values as $v) {
        $v = trim((string)$v);
        if ($v === '' || isset($seen[$v])) continue;
        $seen[$v]   = true;
        $filtered[] = $v;
        if (count($filtered) >= 50) break;
    }
    return $filtered;
}

/** Delete all SKUs (and their option mappings via CASCADE) for a product. */
function _deleteAllSkus(PDO $db, int $productId): void
{
    $db->prepare('DELETE FROM product_skus WHERE product_id = ?')->execute([$productId]);
}

/**
 * Compute Cartesian product of multiple option sets.
 * Each element in $sets is an array of option objects.
 * Returns an array of combos, each combo is an ordered array of option objects.
 */
function _cartesianProduct(array $sets): array
{
    $result = [[]];
    foreach ($sets as $set) {
        $newResult = [];
        foreach ($result as $combo) {
            foreach ($set as $item) {
                $newResult[] = array_merge($combo, [$item]);
            }
        }
        $result = $newResult;
    }
    return $result;
}
