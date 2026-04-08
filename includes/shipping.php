<?php
/**
 * includes/shipping.php — Shipping Calculator Library (PR #14)
 *
 * Zone-based shipping rate calculation.
 *
 * Sections:
 *   1. Shipping Zone CRUD
 *   2. Shipping Method CRUD
 *   3. Rate Calculation
 *   4. Supplier Shipping Templates
 *   5. Free Shipping Promotions
 *
 * Feature toggle: isFeatureEnabled('shipping_calculator')
 */

// ─────────────────────────────────────────────────────────────────────────────
// 1. Shipping Zones
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Get all configured shipping zones.
 */
function getShippingZones(): array
{
    try {
        $db   = getDB();
        $stmt = $db->query(
            'SELECT * FROM shipping_zones ORDER BY sort_order ASC, id ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get a single shipping zone by ID.
 */
function getShippingZone(int $zoneId): ?array
{
    try {
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM shipping_zones WHERE id = ? LIMIT 1');
        $stmt->execute([$zoneId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Admin: create a new shipping zone.
 *
 * @param array $data  Keys: name (required), countries (array), states (array),
 *                     is_default (bool), sort_order (int), is_active (bool)
 * @return int|false   New zone ID on success, false on failure
 */
function createShippingZone(array $data): int|false
{
    $name      = trim($data['name'] ?? '');
    if ($name === '') return false;

    $countries = json_encode(array_values(array_map('strtoupper', (array)($data['countries'] ?? []))));
    $states    = json_encode(array_values(array_map('strtoupper', (array)($data['states']    ?? []))));
    $isDefault = (int)(bool)($data['is_default'] ?? false);
    $sortOrder = (int)($data['sort_order'] ?? 0);
    $isActive  = (int)(bool)($data['is_active']  ?? true);

    try {
        $db = getDB();
        if ($isDefault) {
            $db->exec('UPDATE shipping_zones SET is_default = 0');
        }
        $db->prepare(
            'INSERT INTO shipping_zones (name, countries_json, states_json, is_default, sort_order, is_active)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$name, $countries, $states, $isDefault, $sortOrder, $isActive]);
        return (int)$db->lastInsertId();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Admin: update a shipping zone.
 */
function updateShippingZone(int $zoneId, array $data): bool
{
    if ($zoneId <= 0) return false;

    $db  = getDB();
    $row = getShippingZone($zoneId);
    if (!$row) return false;

    $name      = trim($data['name']       ?? $row['name']);
    $countries = json_encode(array_values(array_map('strtoupper', (array)($data['countries'] ?? json_decode($row['countries_json'], true) ?? []))));
    $states    = json_encode(array_values(array_map('strtoupper', (array)($data['states']    ?? json_decode($row['states_json'],    true) ?? []))));
    $isDefault = isset($data['is_default']) ? (int)(bool)$data['is_default'] : (int)$row['is_default'];
    $sortOrder = isset($data['sort_order']) ? (int)$data['sort_order']       : (int)$row['sort_order'];
    $isActive  = isset($data['is_active'])  ? (int)(bool)$data['is_active']  : (int)$row['is_active'];

    try {
        if ($isDefault) {
            $db->exec('UPDATE shipping_zones SET is_default = 0');
        }
        $db->prepare(
            'UPDATE shipping_zones
             SET name=?, countries_json=?, states_json=?, is_default=?, sort_order=?, is_active=?
             WHERE id=?'
        )->execute([$name, $countries, $states, $isDefault, $sortOrder, $isActive, $zoneId]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Admin: remove a shipping zone (and its methods).
 */
function deleteShippingZone(int $zoneId): bool
{
    if ($zoneId <= 0) return false;
    try {
        $db = getDB();
        $db->prepare('DELETE FROM shipping_methods WHERE zone_id = ?')->execute([$zoneId]);
        $db->prepare('DELETE FROM shipping_zones   WHERE id = ?')->execute([$zoneId]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Determine which zone an address falls in.
 *
 * Matching priority:
 *   1. Zone with explicit state code match ("CC-ST")
 *   2. Zone with explicit country code match
 *   3. Default zone
 *   4. null — no zone found
 *
 * @param string $countryCode  ISO 3166-1 alpha-2
 * @param string $stateCode    Two-letter state/province code (optional)
 * @return array|null          Zone row, or null
 */
function getZoneForAddress(string $countryCode, string $stateCode = ''): ?array
{
    $countryCode = strtoupper(trim($countryCode));
    $stateCode   = strtoupper(trim($stateCode));
    $stateKey    = $stateCode !== '' ? "{$countryCode}-{$stateCode}" : '';

    try {
        $db    = getDB();
        $zones = $db->query(
            'SELECT * FROM shipping_zones WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $countryMatch = null;
        $defaultZone  = null;

        foreach ($zones as $zone) {
            if ($zone['is_default']) $defaultZone = $zone;

            $countries = json_decode($zone['countries_json'] ?? '[]', true) ?: [];
            $states    = json_decode($zone['states_json']    ?? '[]', true) ?: [];

            // State-level match (highest priority)
            if ($stateKey !== '' && in_array($stateKey, $states, true)) {
                return $zone;
            }

            // Country match — keep first occurrence
            if ($countryMatch === null && $countryCode !== '' && in_array($countryCode, $countries, true)) {
                $countryMatch = $zone;
            }
        }

        if ($countryMatch) return $countryMatch;
        if ($defaultZone)  return $defaultZone;

        // Fallback: "Rest of World" style zone with empty countries list
        foreach ($zones as $zone) {
            $countries = json_decode($zone['countries_json'] ?? '[]', true) ?: [];
            if (empty($countries)) return $zone;
        }
    } catch (PDOException $e) { /* fall through */ }

    return null;
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. Shipping Methods
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Get all active shipping methods for a zone.
 */
function getShippingMethods(int $zoneId): array
{
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT * FROM shipping_methods WHERE zone_id = ? AND is_active = 1
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([$zoneId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get a single shipping method.
 */
function getShippingMethod(int $methodId): ?array
{
    try {
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM shipping_methods WHERE id = ? LIMIT 1');
        $stmt->execute([$methodId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Admin: add a shipping method to a zone.
 *
 * @param int   $zoneId
 * @param array $data   Keys: name, type, base_cost, per_kg_cost, per_item_cost,
 *                      free_above_amount, estimated_days_min, estimated_days_max,
 *                      is_active, sort_order
 * @return int|false
 */
function createShippingMethod(int $zoneId, array $data): int|false
{
    if ($zoneId <= 0) return false;
    $name      = trim($data['name'] ?? 'Standard');
    $type      = $data['type']      ?? 'flat_rate';
    $validTypes = ['flat_rate', 'weight_based', 'price_based', 'free'];
    if (!in_array($type, $validTypes, true)) $type = 'flat_rate';

    try {
        $db = getDB();
        $db->prepare(
            'INSERT INTO shipping_methods
             (zone_id, name, type, base_cost, per_kg_cost, per_item_cost,
              free_above_amount, estimated_days_min, estimated_days_max, is_active, sort_order)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $zoneId,
            $name,
            $type,
            (float)($data['base_cost']          ?? 0),
            (float)($data['per_kg_cost']         ?? 0),
            (float)($data['per_item_cost']        ?? 0),
            (float)($data['free_above_amount']    ?? 0),
            max(1, (int)($data['estimated_days_min'] ?? 1)),
            max(1, (int)($data['estimated_days_max'] ?? 7)),
            (int)(bool)($data['is_active']  ?? true),
            (int)($data['sort_order']        ?? 0),
        ]);
        return (int)$db->lastInsertId();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Admin: update a shipping method.
 */
function updateShippingMethod(int $methodId, array $data): bool
{
    if ($methodId <= 0) return false;
    $row = getShippingMethod($methodId);
    if (!$row) return false;

    $name      = trim($data['name']  ?? $row['name']);
    $type      = $data['type']       ?? $row['type'];
    $validTypes = ['flat_rate', 'weight_based', 'price_based', 'free'];
    if (!in_array($type, $validTypes, true)) $type = $row['type'];

    try {
        $db = getDB();
        $db->prepare(
            'UPDATE shipping_methods SET
             name=?, type=?, base_cost=?, per_kg_cost=?, per_item_cost=?,
             free_above_amount=?, estimated_days_min=?, estimated_days_max=?,
             is_active=?, sort_order=?
             WHERE id=?'
        )->execute([
            $name, $type,
            (float)($data['base_cost']          ?? $row['base_cost']),
            (float)($data['per_kg_cost']         ?? $row['per_kg_cost']),
            (float)($data['per_item_cost']        ?? $row['per_item_cost']),
            (float)($data['free_above_amount']    ?? $row['free_above_amount']),
            max(1, (int)($data['estimated_days_min'] ?? $row['estimated_days_min'])),
            max(1, (int)($data['estimated_days_max'] ?? $row['estimated_days_max'])),
            isset($data['is_active'])  ? (int)(bool)$data['is_active']  : (int)$row['is_active'],
            isset($data['sort_order']) ? (int)$data['sort_order']        : (int)$row['sort_order'],
            $methodId,
        ]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Admin: remove a shipping method.
 */
function deleteShippingMethod(int $methodId): bool
{
    if ($methodId <= 0) return false;
    try {
        $db = getDB();
        $db->prepare('DELETE FROM shipping_methods WHERE id = ?')->execute([$methodId]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. Rate Calculation
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Calculate the cost for a single method given cart totals.
 *
 * @param array $method      Shipping method row
 * @param float $totalWeight Total weight of cart items in kg
 * @param float $subtotal    Cart subtotal (before shipping/tax)
 * @param int   $itemCount   Total number of items
 * @return float             Shipping cost (0 for free shipping)
 */
function calculateMethodCost(array $method, float $totalWeight, float $subtotal, int $itemCount = 1): float
{
    switch ($method['type']) {
        case 'free':
            $threshold = (float)$method['free_above_amount'];
            if ($threshold > 0 && $subtotal < $threshold) {
                return -1.0; // not eligible — exclude from results
            }
            return 0.0;

        case 'flat_rate':
            return (float)$method['base_cost'];

        case 'weight_based':
            $cost = (float)$method['base_cost'] + ($totalWeight * (float)$method['per_kg_cost']);
            return max(0.0, $cost);

        case 'price_based':
            // price_based uses base_cost as percentage of subtotal
            // per_item_cost as a flat per-item fee (combined if set)
            $cost = 0.0;
            if ((float)$method['base_cost'] > 0 && $subtotal > 0) {
                $cost += $subtotal * ((float)$method['base_cost'] / 100.0);
            }
            if ((float)$method['per_item_cost'] > 0) {
                $cost += $itemCount * (float)$method['per_item_cost'];
            }
            return max(0.0, $cost);
    }

    return (float)$method['base_cost'];
}

/**
 * Main shipping calculation.
 *
 * Determines the zone from the address, retrieves active methods, calculates
 * cost for each method, and optionally filters to a single requested method.
 *
 * Multi-supplier: if $cartItems include a supplier_id column this function
 * groups by supplier and calculates per-supplier shipping, then returns both
 * per-supplier and combined totals.
 *
 * @param array      $cartItems       Cart item rows (price, quantity, weight_kg, supplier_id)
 * @param array      $shippingAddress ['country' => 'US', 'state' => 'CA']
 * @param int|null   $methodId        Optionally filter to a single method ID
 * @return array  [
 *   'zone'        => zone row|null,
 *   'methods'     => [ [id, name, type, cost, estimated_days_min, estimated_days_max, delivery_estimate], … ],
 *   'suppliers'   => [ supplier_id => [...same structure...], … ],  (only if multi-supplier)
 *   'subtotal'    => float,
 *   'total_weight'=> float,
 * ]
 */
function calculateShipping(array $cartItems, array $shippingAddress, ?int $methodId = null): array
{
    $countryCode = strtoupper(trim($shippingAddress['country'] ?? $shippingAddress['country_code'] ?? ''));
    $stateCode   = strtoupper(trim($shippingAddress['state']   ?? $shippingAddress['state_code']   ?? ''));

    $zone = getZoneForAddress($countryCode, $stateCode);

    // Aggregate cart totals
    $subtotal    = 0.0;
    $totalWeight = 0.0;
    $itemCount   = 0;
    $supplierIds = [];

    foreach ($cartItems as $item) {
        $qty          = max(1, (int)($item['quantity'] ?? 1));
        $price        = (float)($item['price']     ?? $item['unit_price'] ?? 0);
        $weight       = (float)($item['weight_kg'] ?? 0);
        $subtotal    += $price * $qty;
        $totalWeight += $weight * $qty;
        $itemCount   += $qty;
        $sid = (int)($item['supplier_id'] ?? 0);
        if ($sid > 0) $supplierIds[$sid] = true;
    }

    $result = [
        'zone'         => $zone,
        'subtotal'     => round($subtotal, 2),
        'total_weight' => round($totalWeight, 3),
        'methods'      => [],
        'suppliers'    => [],
    ];

    if (!$zone) {
        return $result;
    }

    // Check global free-shipping threshold
    $globalFreeThreshold = _getShippingSettingFloat('shipping_free_threshold');

    // Build method list
    $methods = $methodId ? array_filter(getShippingMethods($zone['id']), fn($m) => (int)$m['id'] === $methodId)
                         : getShippingMethods($zone['id']);

    foreach ($methods as $method) {
        $cost = calculateMethodCost($method, $totalWeight, $subtotal, $itemCount);

        // Global free threshold overrides paid methods
        if ($globalFreeThreshold > 0 && $subtotal >= $globalFreeThreshold) {
            $cost = 0.0;
        }

        if ($cost < 0) continue; // not eligible

        $result['methods'][] = [
            'id'                 => (int)$method['id'],
            'name'               => $method['name'],
            'type'               => $method['type'],
            'cost'               => round($cost, 2),
            'estimated_days_min' => (int)$method['estimated_days_min'],
            'estimated_days_max' => (int)$method['estimated_days_max'],
            'delivery_estimate'  => getEstimatedDeliveryLabel((int)$method['estimated_days_min'], (int)$method['estimated_days_max']),
        ];
    }

    // Multi-supplier breakdown
    if (count($supplierIds) > 1) {
        foreach (array_keys($supplierIds) as $sid) {
            $supplierItems = array_filter($cartItems, fn($i) => (int)($i['supplier_id'] ?? 0) === $sid);
            $suppResult    = calculateShipping(array_values($supplierItems), $shippingAddress, $methodId);
            $result['suppliers'][$sid] = $suppResult['methods'];
        }
    }

    return $result;
}

/**
 * Get all available shipping rates for checkout display.
 *
 * @param array $cartItems   Cart item rows
 * @param int   $addressId   User address ID (looked up from DB)
 * @return array             Same structure as calculateShipping()
 */
function getShippingRates(array $cartItems, int $addressId): array
{
    $address = _getAddressById($addressId);
    if (!$address) {
        return ['zone' => null, 'methods' => [], 'suppliers' => [], 'subtotal' => 0.0, 'total_weight' => 0.0];
    }
    return calculateShipping($cartItems, [
        'country' => $address['country'] ?? $address['country_code'] ?? '',
        'state'   => $address['state']   ?? $address['state_code']   ?? '',
    ]);
}

/**
 * Get a human-readable delivery estimate string.
 *
 * @param int $zoneId    (unused — kept for API compatibility)
 */
function getEstimatedDelivery(int $methodId, int $zoneId = 0): string
{
    $method = getShippingMethod($methodId);
    if (!$method) return 'Delivery time unavailable';
    return getEstimatedDeliveryLabel((int)$method['estimated_days_min'], (int)$method['estimated_days_max']);
}

/**
 * Build a "3-7 business days" label.
 */
function getEstimatedDeliveryLabel(int $minDays, int $maxDays): string
{
    if ($minDays === $maxDays) {
        return "{$minDays} business " . ($minDays === 1 ? 'day' : 'days');
    }
    return "{$minDays}-{$maxDays} business days";
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. Supplier Shipping Templates
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Get all shipping templates for a supplier.
 */
function getSupplierShippingTemplates(int $supplierId): array
{
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT t.*,
                    COUNT(r.id) zones_count,
                    (SELECT COUNT(*) FROM product_shipping ps WHERE ps.template_id = t.id) products_count
             FROM shipping_templates t
             LEFT JOIN shipping_template_rates r ON r.template_id = t.id
             WHERE t.supplier_id = ?
             GROUP BY t.id
             ORDER BY t.is_default DESC, t.created_at ASC'
        );
        $stmt->execute([$supplierId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get a single shipping template.
 */
function getShippingTemplate(int $templateId): ?array
{
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT t.*, GROUP_CONCAT(r.id) rate_ids
             FROM shipping_templates t
             LEFT JOIN shipping_template_rates r ON r.template_id = t.id
             WHERE t.id = ? GROUP BY t.id LIMIT 1'
        );
        $stmt->execute([$templateId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Get the template limit for a supplier based on their plan.
 * Free=1, Pro=5, Enterprise=unlimited (999)
 */
function getShippingTemplatePlanLimit(int $supplierId): int
{
    if (function_exists('checkPlanLimit')) {
        $check = checkPlanLimit($supplierId, 'max_shipping_templates');
        if (isset($check['limit'])) {
            return $check['limit'] < 0 ? 999 : (int)$check['limit'];
        }
    }
    // Fallback: read plan directly
    if (function_exists('getSupplierActivePlan')) {
        $plan = getSupplierActivePlan($supplierId);
        $tier = strtolower($plan['plan_name'] ?? 'free');
        if ($tier === 'enterprise') return 999;
        if ($tier === 'pro')        return 5;
    }
    return 1; // Free plan default
}

/**
 * Supplier: create a shipping template.
 *
 * @param int   $supplierId
 * @param array $data  Keys: name, handling_time_days, is_default,
 *                     zones (array: [{zone_id, method_name, cost, free_above}])
 * @return int|false
 */
function createShippingTemplate(int $supplierId, array $data): int|false
{
    if ($supplierId <= 0) return false;
    $name        = trim($data['name'] ?? '');
    if ($name === '') return false;

    // Enforce plan limit
    $limit    = getShippingTemplatePlanLimit($supplierId);
    $existing = count(getSupplierShippingTemplates($supplierId));
    if ($existing >= $limit) return false;

    $handlingDays = max(0, (int)($data['handling_time_days'] ?? 1));
    $isDefault    = (int)(bool)($data['is_default'] ?? false);

    try {
        $db = getDB();
        if ($isDefault) {
            $db->prepare('UPDATE shipping_templates SET is_default=0 WHERE supplier_id=?')
               ->execute([$supplierId]);
        }
        $db->prepare(
            'INSERT INTO shipping_templates (supplier_id, name, handling_time_days, is_default)
             VALUES (?, ?, ?, ?)'
        )->execute([$supplierId, $name, $handlingDays, $isDefault]);
        $templateId = (int)$db->lastInsertId();

        // Insert zone rates
        foreach ((array)($data['zones'] ?? []) as $zoneRate) {
            $zoneId     = (int)($zoneRate['zone_id']     ?? 0);
            $methodName = trim($zoneRate['method_name']  ?? 'Standard');
            $cost       = (float)($zoneRate['cost']      ?? 0);
            $freeAbove  = (float)($zoneRate['free_above'] ?? 0);
            $isActive   = (int)(bool)($zoneRate['is_active'] ?? true);
            if ($zoneId > 0) {
                $db->prepare(
                    'INSERT INTO shipping_template_rates
                     (template_id, zone_id, method_name, cost, free_above, is_active)
                     VALUES (?,?,?,?,?,?)'
                )->execute([$templateId, $zoneId, $methodName, $cost, $freeAbove, $isActive]);
            }
        }

        return $templateId;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Supplier: update a shipping template.
 */
function updateShippingTemplate(int $templateId, array $data): bool
{
    if ($templateId <= 0) return false;
    $row = getShippingTemplate($templateId);
    if (!$row) return false;

    $supplierId  = (int)$row['supplier_id'];
    $name        = trim($data['name'] ?? $row['name']);
    $handlingDays = isset($data['handling_time_days']) ? max(0, (int)$data['handling_time_days']) : (int)$row['handling_time_days'];
    $isDefault   = isset($data['is_default']) ? (int)(bool)$data['is_default'] : (int)$row['is_default'];

    try {
        $db = getDB();
        if ($isDefault) {
            $db->prepare('UPDATE shipping_templates SET is_default=0 WHERE supplier_id=?')
               ->execute([$supplierId]);
        }
        $db->prepare(
            'UPDATE shipping_templates SET name=?, handling_time_days=?, is_default=? WHERE id=?'
        )->execute([$name, $handlingDays, $isDefault, $templateId]);

        // Replace zone rates if provided
        if (isset($data['zones'])) {
            $db->prepare('DELETE FROM shipping_template_rates WHERE template_id=?')->execute([$templateId]);
            foreach ((array)$data['zones'] as $zoneRate) {
                $zoneId     = (int)($zoneRate['zone_id']     ?? 0);
                $methodName = trim($zoneRate['method_name']  ?? 'Standard');
                $cost       = (float)($zoneRate['cost']      ?? 0);
                $freeAbove  = (float)($zoneRate['free_above'] ?? 0);
                $isActive   = (int)(bool)($zoneRate['is_active'] ?? true);
                if ($zoneId > 0) {
                    $db->prepare(
                        'INSERT INTO shipping_template_rates
                         (template_id, zone_id, method_name, cost, free_above, is_active)
                         VALUES (?,?,?,?,?,?)'
                    )->execute([$templateId, $zoneId, $methodName, $cost, $freeAbove, $isActive]);
                }
            }
        }
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Supplier: delete a shipping template.
 */
function deleteShippingTemplate(int $templateId): bool
{
    if ($templateId <= 0) return false;
    try {
        $db = getDB();
        $db->prepare('DELETE FROM shipping_template_rates WHERE template_id=?')->execute([$templateId]);
        $db->prepare('UPDATE product_shipping SET template_id=NULL WHERE template_id=?')->execute([$templateId]);
        $db->prepare('DELETE FROM shipping_templates WHERE id=?')->execute([$templateId]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Assign a shipping template to a product.
 */
function assignTemplateToProduct(int $productId, int $templateId): bool
{
    if ($productId <= 0 || $templateId <= 0) return false;
    try {
        $db = getDB();
        $db->prepare(
            'INSERT INTO product_shipping (product_id, template_id)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE template_id = VALUES(template_id), updated_at = NOW()'
        )->execute([$productId, $templateId]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. Free Shipping Promotions
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Check if an order qualifies for free shipping.
 *
 * Checks both the global threshold and the supplier-specific threshold.
 *
 * @param float    $cartTotal   Cart subtotal
 * @param int|null $supplierId  Optional supplier to check per-supplier threshold
 * @return bool
 */
function isFreeShippingEligible(float $cartTotal, ?int $supplierId = null): bool
{
    // Global threshold
    $globalThreshold = _getShippingSettingFloat('shipping_free_threshold');
    if ($globalThreshold > 0 && $cartTotal >= $globalThreshold) {
        return true;
    }

    // Supplier-specific threshold
    if ($supplierId !== null && $supplierId > 0) {
        $supplierThreshold = _getShippingSettingFloat("shipping_free_threshold_supplier_{$supplierId}");
        if ($supplierThreshold > 0 && $cartTotal >= $supplierThreshold) {
            return true;
        }
    }

    return false;
}

// ─────────────────────────────────────────────────────────────────────────────
// Internal helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Read a float setting from system_settings.
 */
function _getShippingSettingFloat(string $key, float $default = 0.0): float
{
    try {
        $db   = getDB();
        $stmt = $db->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        if ($val !== false && is_numeric($val)) return (float)$val;
    } catch (PDOException $e) { /* ignore */ }
    return $default;
}

/**
 * Fetch a user address row by ID.
 */
function _getAddressById(int $addressId): ?array
{
    if ($addressId <= 0) return null;
    try {
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM user_addresses WHERE id = ? LIMIT 1');
        $stmt->execute([$addressId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}
