<?php
/**
 * includes/products.php — Product Helper Library
 *
 * Provides CRUD helpers, image management, and plan-limit enforcement
 * for the product system (DOCS/07-product-upload.md, DOCS/05-free-vs-premium.md).
 *
 * All write operations check the `product_listing` feature toggle
 * (DOCS/12-feature-toggle.md) before proceeding.
 */

require_once __DIR__ . '/plan_limits.php';
require_once __DIR__ . '/feature_toggles.php';

// ---------------------------------------------------------------------------
// Product CRUD
// ---------------------------------------------------------------------------

/**
 * Create a new product owned by the given supplier.
 *
 * Returns the new product ID on success or throws on error.
 * Enforces:
 *   • product_listing feature toggle
 *   • plan product-count limit (Free=10, Pro=500, Enterprise=unlimited)
 *
 * @param  int   $supplierId
 * @param  array $data  Associative array of product fields.
 * @return int   New product ID
 * @throws RuntimeException on validation / limit failure
 */
function createProduct(int $supplierId, array $data): int
{
    if (!isFeatureEnabled('product_listing')) {
        throw new RuntimeException('Product listing is currently disabled by platform administrators.');
    }

    if (!canAddProduct($supplierId)) {
        $plan  = getSupplierPlan($supplierId);
        $limit = (int)($plan['limits_decoded']['products'] ?? 10);
        throw new RuntimeException("Product limit reached ({$limit} products on {$plan['name']} plan). Please upgrade your plan.");
    }

    $db   = getDB();
    $name = trim($data['name'] ?? '');
    if ($name === '') {
        throw new RuntimeException('Product name is required.');
    }
    $price = (float)($data['price'] ?? 0);
    if ($price < 0) {
        throw new RuntimeException('Price must be non-negative.');
    }

    $slug = slugify($name);
    $i    = 1;
    $base = $slug;
    while (true) {
        $s = $db->prepare('SELECT id FROM products WHERE slug = ? LIMIT 1');
        $s->execute([$slug]);
        if (!$s->fetch()) break;
        $slug = $base . '-' . $i++;
    }

    $tags = _normaliseTags($data['tags'] ?? null);

    $stmt = $db->prepare(
        'INSERT INTO products
            (supplier_id, category_id, name, slug, short_desc, description,
             sku, price, compare_price, min_order_qty, stock_qty,
             weight, tags, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );
    $stmt->execute([
        $supplierId,
        isset($data['category_id']) && $data['category_id'] ? (int)$data['category_id'] : null,
        $name,
        $slug,
        $data['short_desc']   ?? null,
        $data['description']  ?? null,
        $data['sku']          ?? null,
        $price,
        isset($data['compare_price']) && $data['compare_price'] !== '' ? (float)$data['compare_price'] : null,
        max(1, (int)($data['min_order_qty'] ?? 1)),
        max(0, (int)($data['stock_qty']     ?? 0)),
        isset($data['weight']) && $data['weight'] !== '' ? (float)$data['weight'] : null,
        $tags,
        in_array($data['status'] ?? '', ['active', 'draft', 'inactive']) ? $data['status'] : 'draft',
    ]);

    return (int)$db->lastInsertId();
}

/**
 * Update an existing product.  Only the owning supplier (or an admin) may edit.
 *
 * @param  int   $productId
 * @param  int   $supplierId  Pass 0 when called from admin context.
 * @param  array $data
 * @return bool  true on success
 * @throws RuntimeException on validation / permission failure
 */
function updateProduct(int $productId, int $supplierId, array $data): bool
{
    if (!isFeatureEnabled('product_listing')) {
        throw new RuntimeException('Product listing is currently disabled by platform administrators.');
    }

    $db = getDB();

    // Ownership verification (skip for admin: supplierId === 0)
    if ($supplierId > 0) {
        $check = $db->prepare('SELECT id FROM products WHERE id = ? AND supplier_id = ?');
        $check->execute([$productId, $supplierId]);
        if (!$check->fetch()) {
            throw new RuntimeException('Product not found or access denied.');
        }
    }

    $name  = trim($data['name'] ?? '');
    $price = isset($data['price']) ? (float)$data['price'] : null;

    if ($name === '') {
        throw new RuntimeException('Product name is required.');
    }
    if ($price !== null && $price < 0) {
        throw new RuntimeException('Price must be non-negative.');
    }

    $tags = _normaliseTags($data['tags'] ?? null);

    $db->prepare(
        'UPDATE products SET
            category_id     = ?,
            name            = ?,
            short_desc      = ?,
            description     = ?,
            sku             = ?,
            price           = COALESCE(?, price),
            compare_price   = ?,
            min_order_qty   = ?,
            stock_qty       = ?,
            weight          = ?,
            tags            = ?,
            status          = ?,
            updated_at      = NOW()
         WHERE id = ?'
    )->execute([
        isset($data['category_id']) && $data['category_id'] ? (int)$data['category_id'] : null,
        $name,
        $data['short_desc']   ?? null,
        $data['description']  ?? null,
        $data['sku']          ?? null,
        $price,
        isset($data['compare_price']) && $data['compare_price'] !== '' ? (float)$data['compare_price'] : null,
        max(1, (int)($data['min_order_qty'] ?? 1)),
        max(0, (int)($data['stock_qty']     ?? 0)),
        isset($data['weight']) && $data['weight'] !== '' ? (float)$data['weight'] : null,
        $tags,
        in_array($data['status'] ?? '', ['active', 'draft', 'inactive']) ? $data['status'] : 'draft',
        $productId,
    ]);

    return true;
}

/**
 * Soft-delete a product (sets status = 'archived').
 * Only the owning supplier (or admin, supplierId === 0) may delete.
 *
 * @param  int $productId
 * @param  int $supplierId  Pass 0 for admin context.
 * @return bool
 * @throws RuntimeException on permission failure
 */
function deleteProduct(int $productId, int $supplierId): bool
{
    $db = getDB();

    if ($supplierId > 0) {
        $check = $db->prepare('SELECT id FROM products WHERE id = ? AND supplier_id = ?');
        $check->execute([$productId, $supplierId]);
        if (!$check->fetch()) {
            throw new RuntimeException('Product not found or access denied.');
        }
    }

    $db->prepare('UPDATE products SET status = "archived", updated_at = NOW() WHERE id = ?')
       ->execute([$productId]);

    return true;
}

/**
 * Get a single product by ID, joining supplier and category info.
 *
 * @param  int  $productId
 * @return array|null  Product row or null if not found
 */
function getProduct(int $productId): ?array
{
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT p.*,
                s.company_name  supplier_name,
                s.slug          supplier_slug,
                c.name          category_name
         FROM   products p
         LEFT JOIN suppliers  s ON s.id = p.supplier_id
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE  p.id = ?'
    );
    $stmt->execute([$productId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * List products with optional filtering.
 *
 * @param  array $filters  Keys: category_id, supplier_id, q (search), status, min_price, max_price
 * @param  int   $page
 * @param  int   $perPage
 * @return array  {data, pagination}
 */
function getProducts(array $filters = [], int $page = 1, int $perPage = 20): array
{
    $db     = getDB();
    $where  = [];
    $params = [];

    $status = $filters['status'] ?? 'active';
    $where[]  = 'p.status = ?';
    $params[] = $status;

    if (!empty($filters['category_id'])) {
        $where[]  = 'p.category_id = ?';
        $params[] = (int)$filters['category_id'];
    }
    if (!empty($filters['supplier_id'])) {
        $where[]  = 'p.supplier_id = ?';
        $params[] = (int)$filters['supplier_id'];
    }
    if (!empty($filters['q'])) {
        $where[]  = '(p.name LIKE ? OR p.short_desc LIKE ?)';
        $params[] = '%' . $filters['q'] . '%';
        $params[] = '%' . $filters['q'] . '%';
    }
    if (isset($filters['min_price']) && $filters['min_price'] !== '') {
        $where[]  = 'p.price >= ?';
        $params[] = (float)$filters['min_price'];
    }
    if (isset($filters['max_price']) && $filters['max_price'] !== '') {
        $where[]  = 'p.price <= ?';
        $params[] = (float)$filters['max_price'];
    }

    $allowedSorts = ['price', 'created_at', 'rating', 'view_count', 'name'];
    $sort = in_array($filters['sort'] ?? '', $allowedSorts) ? $filters['sort'] : 'created_at';
    $dir  = strtoupper($filters['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT p.*,
                   s.company_name supplier_name,
                   c.name         category_name
            FROM   products p
            LEFT JOIN suppliers  s ON s.id = p.supplier_id
            LEFT JOIN categories c ON c.id = p.category_id
            $whereClause
            ORDER BY p.$sort $dir";

    return paginate($db, $sql, $params, $page, $perPage);
}

/**
 * Get products belonging to a specific supplier (for supplier dashboard).
 *
 * @param  int    $supplierId
 * @param  string $status     Empty string = all non-archived statuses
 * @param  int    $page
 * @param  int    $perPage
 * @return array  {data, pagination}
 */
function getSupplierProducts(int $supplierId, string $status = '', int $page = 1, int $perPage = 20): array
{
    $db     = getDB();
    $where  = ['p.supplier_id = ?', 'p.status != "archived"'];
    $params = [$supplierId];

    if ($status !== '') {
        $where[]  = 'p.status = ?';
        $params[] = $status;
    }

    $sql = 'SELECT p.*, c.name category_name
            FROM   products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE  ' . implode(' AND ', $where) . '
            ORDER BY p.created_at DESC';

    return paginate($db, $sql, $params, $page, $perPage);
}

/**
 * Count active (non-archived) products for a supplier.
 * Used by plan-limit checks.
 */
function countSupplierProducts(int $supplierId): int
{
    $db   = getDB();
    $stmt = $db->prepare('SELECT COUNT(*) FROM products WHERE supplier_id = ? AND status != "archived"');
    $stmt->execute([$supplierId]);
    return (int)$stmt->fetchColumn();
}

// ---------------------------------------------------------------------------
// Product Images
// ---------------------------------------------------------------------------

/**
 * Upload a product image and store the record in product_images.
 *
 * Enforces plan limit on images per product.
 * $type = 'main' | 'gallery'
 * Main image: min 800×800 px, gallery: min 600×600 px.
 * Both: max 5 MB, JPG/PNG only.
 *
 * @param  int    $productId
 * @param  int    $supplierId   Owner check; pass 0 to skip.
 * @param  array  $file         $_FILES element
 * @param  string $type         'main' or 'gallery'
 * @return array  {url, path, id}
 * @throws RuntimeException on validation failure
 */
function uploadProductImage(int $productId, int $supplierId, array $file, string $type = 'gallery'): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File upload error (code ' . $file['error'] . ').');
    }

    // Size limit: 5 MB
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Image must not exceed 5 MB.');
    }

    // MIME type
    $allowed = ['image/jpeg', 'image/jpg', 'image/png'];
    $mime    = _detectMime($file['tmp_name']);
    if (!in_array($mime, $allowed, true)) {
        throw new RuntimeException('Only JPG and PNG images are accepted.');
    }

    // Dimension check
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        throw new RuntimeException('Could not read image dimensions.');
    }
    [$imgW, $imgH] = $info;
    if ($type === 'main') {
        if ($imgW < 800 || $imgH < 800) {
            throw new RuntimeException("Main image must be at least 800×800 px (got {$imgW}×{$imgH}).");
        }
    } else {
        if ($imgW < 600 || $imgH < 600) {
            throw new RuntimeException("Gallery image must be at least 600×600 px (got {$imgW}×{$imgH}).");
        }
    }

    // Plan limit: count existing images for this product, then compare to supplier limit
    if ($supplierId > 0) {
        $db       = getDB();
        $existing = _countProductImages($productId);
        $plan     = getSupplierPlan($supplierId);
        $limit    = (int)($plan['limits_decoded']['images_per_product'] ?? 3);
        if ($limit > 0 && $existing >= $limit) {
            throw new RuntimeException("Image limit reached ({$limit} images per product on {$plan['name']} plan).");
        }

        // Ownership check
        $ownerCheck = $db->prepare('SELECT id FROM products WHERE id = ? AND supplier_id = ?');
        $ownerCheck->execute([$productId, $supplierId]);
        if (!$ownerCheck->fetch()) {
            throw new RuntimeException('Product not found or access denied.');
        }
    }

    // Save file
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $ext      = in_array($ext, ['jpg', 'jpeg', 'png']) ? $ext : ($mime === 'image/png' ? 'png' : 'jpg');
    $dir      = rtrim(UPLOAD_DIR, '/') . '/products/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest     = $dir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Failed to save uploaded file.');
    }
    $path = 'assets/uploads/products/' . $filename;

    $db        = getDB();
    $isPrimary = ($type === 'main') ? 1 : 0;

    // If setting a new primary, unset the old one
    if ($isPrimary) {
        $db->prepare('UPDATE product_images SET is_primary = 0 WHERE product_id = ?')
           ->execute([$productId]);
    }

    $db->prepare('INSERT INTO product_images (product_id, image_url, is_primary, sort_order, created_at) VALUES (?, ?, ?, ?, NOW())')
       ->execute([$productId, $path, $isPrimary, $isPrimary ? 0 : 99]);

    $imageId = (int)$db->lastInsertId();

    return ['id' => $imageId, 'url' => $path, 'path' => $path];
}

/**
 * Delete a product image.
 * Only the owning supplier (or admin, supplierId === 0) may delete.
 *
 * @param  int $imageId
 * @param  int $supplierId  0 = admin context
 * @return bool
 * @throws RuntimeException on permission failure
 */
function deleteProductImage(int $imageId, int $supplierId): bool
{
    $db = getDB();

    if ($supplierId > 0) {
        $stmt = $db->prepare(
            'SELECT pi.id, pi.image_url
             FROM   product_images pi
             JOIN   products p ON p.id = pi.product_id
             WHERE  pi.id = ? AND p.supplier_id = ?'
        );
        $stmt->execute([$imageId, $supplierId]);
    } else {
        $stmt = $db->prepare('SELECT id, image_url FROM product_images WHERE id = ?');
        $stmt->execute([$imageId]);
    }

    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Image not found or access denied.');
    }

    // Remove physical file if it exists under UPLOAD_DIR
    $filePath = rtrim(UPLOAD_DIR, '/') . '/' . ltrim(str_replace('assets/uploads/', '', $row['image_url']), '/');
    if (is_file($filePath)) {
        @unlink($filePath);
    }

    $db->prepare('DELETE FROM product_images WHERE id = ?')->execute([$imageId]);
    return true;
}

/**
 * Get all images for a product, ordered by sort_order then id.
 *
 * @param  int   $productId
 * @return array Array of image rows
 */
function getProductImages(int $productId): array
{
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC'
    );
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

// ---------------------------------------------------------------------------
// Internal helpers
// ---------------------------------------------------------------------------

/** Encode tags array (up to 10) to JSON, or return null. */
function _normaliseTags(mixed $tags): ?string
{
    if ($tags === null) return null;
    if (is_string($tags)) {
        $decoded = json_decode($tags, true);
        if (is_array($decoded)) {
            $tags = $decoded;
        } else {
            // comma-separated
            $tags = array_filter(array_map('trim', explode(',', $tags)));
        }
    }
    if (!is_array($tags) || empty($tags)) return null;
    return json_encode(array_values(array_slice($tags, 0, 10)));
}

/** Count images already uploaded for a product. */
function _countProductImages(int $productId): int
{
    $db   = getDB();
    $stmt = $db->prepare('SELECT COUNT(*) FROM product_images WHERE product_id = ?');
    $stmt->execute([$productId]);
    return (int)$stmt->fetchColumn();
}

/** Detect MIME type using fileinfo or getimagesize fallback. */
function _detectMime(string $path): string
{
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $mime = (string)finfo_file($fi, $path);
        finfo_close($fi);
        return $mime;
    }
    $info = @getimagesize($path);
    return $info['mime'] ?? 'application/octet-stream';
}
