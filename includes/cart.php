<?php
/**
 * includes/cart.php — Shopping Cart Helper Library
 *
 * Supports dual-storage: session cart for guests, DB cart for logged-in users.
 * All write operations check the `cart_checkout` feature toggle.
 *
 * DB table: cart_items (id, user_id, product_id, variant_id, quantity, added_at)
 * Session:  $_SESSION['cart'][$key] = [product_id, variant_id, quantity, ...]
 */

require_once __DIR__ . '/feature_toggles.php';

// ---------------------------------------------------------------------------
// Internal helpers
// ---------------------------------------------------------------------------

/** Session cart key for a product+variant combination */
function _cartSessionKey(int $productId, ?int $variantId): string
{
    return $productId . '_' . ($variantId ?? 0);
}

/**
 * Fetch product row (active only) — returns false if not found/inactive.
 */
function _cartGetProduct(int $productId): array|false
{
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT p.*, s.id supplier_id, s.company_name supplier_name, s.slug supplier_slug
         FROM products p
         LEFT JOIN suppliers s ON s.id = p.supplier_id
         WHERE p.id = ? AND p.status = "active"'
    );
    $stmt->execute([$productId]);
    return $stmt->fetch();
}

/**
 * Fetch variant row — returns false if not found.
 */
function _cartGetVariant(int $variantId): array|false
{
    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM product_variants WHERE id = ?');
    $stmt->execute([$variantId]);
    return $stmt->fetch();
}

// ---------------------------------------------------------------------------
// Session cart (guests)
// ---------------------------------------------------------------------------

/**
 * Add an item to the session cart (guest).
 * Returns ['success'=>true] or ['error'=>'...']
 */
function _sessionCartAdd(int $productId, ?int $variantId, int $quantity): array
{
    $product = _cartGetProduct($productId);
    if (!$product) {
        return ['error' => 'Product not found or unavailable.'];
    }

    // Determine available stock
    $stock = (int)$product['stock_qty'];
    if ($variantId) {
        $variant = _cartGetVariant($variantId);
        if ($variant) {
            $stock = (int)$variant['stock_qty'];
        }
    }

    if ($stock <= 0) {
        return ['error' => 'Product is out of stock.'];
    }

    $key     = _cartSessionKey($productId, $variantId);
    $current = (int)($_SESSION['cart'][$key]['quantity'] ?? 0);
    $newQty  = $current + $quantity;

    if ($newQty > $stock) {
        return ['error' => "Only $stock unit(s) available in stock."];
    }

    $imgArr = is_string($product['images'] ?? '') ? json_decode($product['images'], true) : ($product['images'] ?? []);
    $img    = (is_array($imgArr) && !empty($imgArr[0])) ? $imgArr[0] : '';

    $unitPrice = (float)$product['price'];
    if ($variantId) {
        $variant = _cartGetVariant($variantId);
        if ($variant && $variant['price'] !== null && (float)$variant['price'] > 0) {
            $unitPrice = (float)$variant['price'];
        }
    }

    $_SESSION['cart'][$key] = [
        'session_key' => $key,
        'product_id'  => $productId,
        'variant_id'  => $variantId,
        'quantity'    => $newQty,
        'name'        => $product['name'],
        'slug'        => $product['slug'],
        'image'       => $img,
        'unit_price'  => $unitPrice,
        'stock_qty'   => $stock,
        'supplier_id' => $product['supplier_id'] ?? null,
        'supplier_name' => $product['supplier_name'] ?? '',
    ];

    return ['success' => true];
}

/**
 * Clear session cart.
 */
function clearSessionCart(): void
{
    $_SESSION['cart'] = [];
}

// ---------------------------------------------------------------------------
// Cart CRUD — public API
// ---------------------------------------------------------------------------

/**
 * Add item to cart.
 * $userId = 0 means guest (session cart).
 * Returns ['success'=>true, 'count'=>N] or ['error'=>'...']
 */
function addToCart(int $userId, int $productId, ?int $variantId, int $quantity): array
{
    if (!isFeatureEnabled('cart_checkout')) {
        return ['error' => 'Cart & checkout is currently disabled.'];
    }

    if ($quantity <= 0) {
        return ['error' => 'Quantity must be greater than zero.'];
    }

    $product = _cartGetProduct($productId);
    if (!$product) {
        return ['error' => 'Product not found or unavailable.'];
    }

    // Guest: use session cart
    if ($userId <= 0) {
        $result = _sessionCartAdd($productId, $variantId, $quantity);
        if (isset($result['success'])) {
            $result['count'] = getCartCount(0);
        }
        return $result;
    }

    // Logged-in: DB cart
    $db    = getDB();
    $stock = (int)$product['stock_qty'];

    if ($variantId) {
        $variant = _cartGetVariant($variantId);
        if ($variant) {
            $stock = (int)$variant['stock_qty'];
        }
    }

    if ($stock <= 0) {
        return ['error' => 'Product is out of stock.'];
    }

    // Check for existing item
    if ($variantId) {
        $existStmt = $db->prepare(
            'SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ? AND variant_id = ?'
        );
        $existStmt->execute([$userId, $productId, $variantId]);
    } else {
        $existStmt = $db->prepare(
            'SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ? AND variant_id IS NULL'
        );
        $existStmt->execute([$userId, $productId]);
    }
    $existing = $existStmt->fetch();

    $newQty = $quantity;
    if ($existing) {
        $newQty = (int)$existing['quantity'] + $quantity;
    }

    if ($newQty > $stock) {
        return ['error' => "Only $stock unit(s) available in stock."];
    }

    if ($existing) {
        $db->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?')
           ->execute([$newQty, $existing['id']]);
    } else {
        $db->prepare(
            'INSERT INTO cart_items (user_id, product_id, variant_id, quantity) VALUES (?, ?, ?, ?)'
        )->execute([$userId, $productId, $variantId, $quantity]);
    }

    return ['success' => true, 'count' => getCartCount($userId)];
}

/**
 * Update cart item quantity.
 * Pass quantity=0 to remove the item.
 * Returns ['success'=>true] or ['error'=>'...']
 */
function updateCartItem(int $userId, int $cartItemId, int $quantity): array
{
    if (!isFeatureEnabled('cart_checkout')) {
        return ['error' => 'Cart & checkout is currently disabled.'];
    }

    if ($quantity < 0) {
        return ['error' => 'Quantity cannot be negative.'];
    }

    // Guest: identify by session key (cartItemId used as hash index)
    if ($userId <= 0) {
        if ($quantity === 0) {
            foreach ($_SESSION['cart'] ?? [] as $key => $item) {
                if ((int)($item['product_id'] ?? 0) === $cartItemId) {
                    unset($_SESSION['cart'][$key]);
                    break;
                }
            }
        } else {
            foreach ($_SESSION['cart'] ?? [] as $key => &$item) {
                if ((int)($item['product_id'] ?? 0) === $cartItemId) {
                    if ($quantity > (int)($item['stock_qty'] ?? PHP_INT_MAX)) {
                        return ['error' => 'Quantity exceeds available stock.'];
                    }
                    $item['quantity'] = $quantity;
                    break;
                }
            }
            unset($item);
        }
        return ['success' => true];
    }

    $db = getDB();

    if ($quantity === 0) {
        $db->prepare('DELETE FROM cart_items WHERE id = ? AND user_id = ?')
           ->execute([$cartItemId, $userId]);
        return ['success' => true];
    }

    // Validate stock before updating
    $stmt = $db->prepare(
        'SELECT ci.id, ci.product_id, ci.variant_id, p.stock_qty, pv.stock_qty variant_stock
         FROM cart_items ci
         JOIN products p ON p.id = ci.product_id
         LEFT JOIN product_variants pv ON pv.id = ci.variant_id
         WHERE ci.id = ? AND ci.user_id = ?'
    );
    $stmt->execute([$cartItemId, $userId]);
    $row = $stmt->fetch();

    if (!$row) {
        return ['error' => 'Cart item not found.'];
    }

    $stock = ($row['variant_id'] && $row['variant_stock'] !== null)
        ? (int)$row['variant_stock']
        : (int)$row['stock_qty'];

    if ($quantity > $stock) {
        return ['error' => "Only $stock unit(s) available in stock."];
    }

    $db->prepare('UPDATE cart_items SET quantity = ? WHERE id = ? AND user_id = ?')
       ->execute([$quantity, $cartItemId, $userId]);

    return ['success' => true];
}

/**
 * Remove a single cart item.
 */
function removeFromCart(int $userId, int $cartItemId): array
{
    if (!isFeatureEnabled('cart_checkout')) {
        return ['error' => 'Cart & checkout is currently disabled.'];
    }

    if ($userId <= 0) {
        // Guest: cartItemId is treated as product_id
        foreach ($_SESSION['cart'] ?? [] as $key => $item) {
            if ((int)($item['product_id'] ?? 0) === $cartItemId) {
                unset($_SESSION['cart'][$key]);
                break;
            }
        }
        return ['success' => true];
    }

    getDB()->prepare('DELETE FROM cart_items WHERE id = ? AND user_id = ?')
           ->execute([$cartItemId, $userId]);

    return ['success' => true];
}

/**
 * Remove all cart items for a user.
 */
function clearCart(int $userId): array
{
    if (!isFeatureEnabled('cart_checkout')) {
        return ['error' => 'Cart & checkout is currently disabled.'];
    }

    if ($userId <= 0) {
        clearSessionCart();
        return ['success' => true];
    }

    getDB()->prepare('DELETE FROM cart_items WHERE user_id = ?')->execute([$userId]);
    return ['success' => true];
}

/**
 * Get full cart with product/variant/supplier details.
 * Returns array of enriched cart item rows.
 */
function getCart(int $userId): array
{
    if ($userId <= 0) {
        // Guest: enrich session cart with current prices
        $items = [];
        foreach ($_SESSION['cart'] ?? [] as $key => $item) {
            $pid     = (int)($item['product_id'] ?? 0);
            $product = _cartGetProduct($pid);
            if (!$product) {
                continue;
            }
            $variantId = $item['variant_id'] ?? null;
            $unitPrice = (float)$product['price'];
            $skuInfo   = '';
            if ($variantId) {
                $variant = _cartGetVariant((int)$variantId);
                if ($variant) {
                    if ($variant['price'] !== null && (float)$variant['price'] > 0) {
                        $unitPrice = (float)$variant['price'];
                    }
                    $attrs   = is_string($variant['attributes']) ? json_decode($variant['attributes'], true) : ($variant['attributes'] ?? []);
                    $parts   = [];
                    foreach ($attrs as $k => $v) {
                        $parts[] = ucfirst($k) . ': ' . $v;
                    }
                    $skuInfo = implode(', ', $parts);
                }
            }

            $imgArr = is_string($product['images'] ?? '') ? json_decode($product['images'], true) : ($product['images'] ?? []);
            $img    = (is_array($imgArr) && !empty($imgArr[0])) ? $imgArr[0] : '';
            $qty    = (int)($item['quantity'] ?? 1);

            $items[] = [
                'id'            => 0,
                'session_key'   => $key,
                'product_id'    => $pid,
                'variant_id'    => $variantId,
                'quantity'      => $qty,
                'product_name'  => $product['name'],
                'slug'          => $product['slug'],
                'image'         => $img,
                'unit_price'    => $unitPrice,
                'subtotal'      => round($unitPrice * $qty, 2),
                'sku_info'      => $skuInfo,
                'stock_qty'     => (int)$product['stock_qty'],
                'supplier_id'   => $product['supplier_id'] ?? null,
                'supplier_name' => $product['supplier_name'] ?? '',
            ];
        }
        return $items;
    }

    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT ci.id, ci.product_id, ci.variant_id, ci.quantity,
                p.name product_name, p.slug, p.images, p.price, p.stock_qty,
                pv.price variant_price, pv.attributes variant_attributes, pv.stock_qty variant_stock,
                s.id supplier_id, s.company_name supplier_name, s.slug supplier_slug
         FROM cart_items ci
         JOIN products p ON p.id = ci.product_id
         LEFT JOIN product_variants pv ON pv.id = ci.variant_id
         LEFT JOIN suppliers s ON s.id = p.supplier_id
         WHERE ci.user_id = ?
         ORDER BY ci.added_at DESC'
    );
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    $items = [];
    foreach ($rows as $row) {
        $unitPrice = (float)$row['price'];
        if ($row['variant_id'] && $row['variant_price'] !== null && (float)$row['variant_price'] > 0) {
            $unitPrice = (float)$row['variant_price'];
        }

        $skuInfo = '';
        if ($row['variant_attributes']) {
            $attrs = is_string($row['variant_attributes']) ? json_decode($row['variant_attributes'], true) : $row['variant_attributes'];
            $parts = [];
            foreach ($attrs as $k => $v) {
                $parts[] = ucfirst($k) . ': ' . $v;
            }
            $skuInfo = implode(', ', $parts);
        }

        $imgArr = is_string($row['images'] ?? '') ? json_decode($row['images'], true) : ($row['images'] ?? []);
        $img    = (is_array($imgArr) && !empty($imgArr[0])) ? $imgArr[0] : '';

        $stock = ($row['variant_id'] && $row['variant_stock'] !== null)
            ? (int)$row['variant_stock']
            : (int)$row['stock_qty'];

        $items[] = [
            'id'            => (int)$row['id'],
            'product_id'    => (int)$row['product_id'],
            'variant_id'    => $row['variant_id'] ? (int)$row['variant_id'] : null,
            'quantity'      => (int)$row['quantity'],
            'product_name'  => $row['product_name'],
            'slug'          => $row['slug'],
            'image'         => $img,
            'unit_price'    => $unitPrice,
            'subtotal'      => round($unitPrice * (int)$row['quantity'], 2),
            'sku_info'      => $skuInfo,
            'stock_qty'     => $stock,
            'supplier_id'   => $row['supplier_id'] ? (int)$row['supplier_id'] : null,
            'supplier_name' => $row['supplier_name'] ?? '',
        ];
    }

    return $items;
}

/**
 * Get total number of items in cart (sum of quantities).
 */
function getCartCount(int $userId): int
{
    if ($userId <= 0) {
        return array_sum(array_column($_SESSION['cart'] ?? [], 'quantity'));
    }

    $db   = getDB();
    $stmt = $db->prepare('SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE user_id = ?');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Get cart subtotal (sum of unit_price * quantity).
 */
function getCartTotal(int $userId): float
{
    $items = getCart($userId);
    return array_sum(array_column($items, 'subtotal'));
}

// ---------------------------------------------------------------------------
// Multi-supplier grouping
// ---------------------------------------------------------------------------

/**
 * Group cart items by supplier.
 * Returns: [{supplier_id, supplier_name, items: [...], subtotal}, ...]
 */
function getCartGroupedBySupplier(int $userId): array
{
    $items  = getCart($userId);
    $groups = [];

    foreach ($items as $item) {
        $sid = $item['supplier_id'] ?? 0;
        if (!isset($groups[$sid])) {
            $groups[$sid] = [
                'supplier_id'   => $sid,
                'supplier_name' => $item['supplier_name'] ?: 'Unknown Supplier',
                'items'         => [],
                'subtotal'      => 0.0,
            ];
        }
        $groups[$sid]['items'][]   = $item;
        $groups[$sid]['subtotal'] += $item['subtotal'];
    }

    return array_values($groups);
}

// ---------------------------------------------------------------------------
// Stock validation
// ---------------------------------------------------------------------------

/**
 * Validate all cart items have sufficient stock.
 * Returns array of items with stock issues (empty = all clear).
 * Also auto-adjusts quantities to max available when stock has reduced.
 */
function validateCartStock(int $userId): array
{
    $issues = [];
    $items  = getCart($userId);

    foreach ($items as $item) {
        $stock = (int)$item['stock_qty'];
        $qty   = (int)$item['quantity'];

        if ($stock <= 0) {
            $issues[] = array_merge($item, ['issue' => 'out_of_stock', 'message' => 'Out of stock — please remove.']);
        } elseif ($qty > $stock) {
            // Auto-adjust to available stock
            if ($userId > 0 && $item['id'] > 0) {
                getDB()->prepare('UPDATE cart_items SET quantity = ? WHERE id = ? AND user_id = ?')
                       ->execute([$stock, $item['id'], $userId]);
            } elseif ($userId <= 0 && isset($item['session_key'])) {
                $_SESSION['cart'][$item['session_key']]['quantity'] = $stock;
            }
            $issues[] = array_merge($item, [
                'adjusted_to' => $stock,
                'issue'       => 'quantity_adjusted',
                'message'     => "Only $stock unit(s) available. Quantity adjusted.",
            ]);
        } elseif ($stock <= 5) {
            $issues[] = array_merge($item, ['issue' => 'low_stock', 'message' => "Only $stock left in stock."]);
        }
    }

    return $issues;
}

// ---------------------------------------------------------------------------
// Session → DB merge (on login)
// ---------------------------------------------------------------------------

/**
 * Merge session cart items into the DB cart for a newly-logged-in user.
 * Increments quantities for existing items; inserts new ones.
 */
function mergeSessionCartToDb(int $userId): void
{
    if ($userId <= 0) {
        return;
    }

    $db          = getDB();
    $sessionCart = $_SESSION['cart'] ?? [];

    foreach ($sessionCart as $item) {
        $productId = (int)($item['product_id'] ?? 0);
        $variantId = isset($item['variant_id']) ? (int)$item['variant_id'] : null;
        $quantity  = max(1, (int)($item['quantity'] ?? 1));

        if ($productId <= 0) {
            continue;
        }

        if ($variantId) {
            $existStmt = $db->prepare(
                'SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ? AND variant_id = ?'
            );
            $existStmt->execute([$userId, $productId, $variantId]);
        } else {
            $existStmt = $db->prepare(
                'SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ? AND variant_id IS NULL'
            );
            $existStmt->execute([$userId, $productId]);
        }

        $existing = $existStmt->fetch();

        if ($existing) {
            $db->prepare('UPDATE cart_items SET quantity = quantity + ? WHERE id = ?')
               ->execute([$quantity, $existing['id']]);
        } else {
            $db->prepare(
                'INSERT INTO cart_items (user_id, product_id, variant_id, quantity) VALUES (?, ?, ?, ?)'
            )->execute([$userId, $productId, $variantId, $quantity]);
        }
    }

    clearSessionCart();
}
