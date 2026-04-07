<?php
/**
 * includes/categories.php — Category Helper Library
 *
 * Provides full CRUD helpers for the 3-level hierarchical category system
 * (DOCS/07-product-upload.md, PR #4 scope).
 *
 * Levels:
 *   Level 1 — Root categories (parent_id IS NULL)
 *   Level 2 — Sub-categories (parent_id = Level 1 id)
 *   Level 3 — Leaf categories (parent_id = Level 2 id)
 *
 * Security: write operations require admin role (caller must enforce before
 * calling these functions or use the API layer which enforces CSRF + admin).
 */

// ---------------------------------------------------------------------------
// Read helpers
// ---------------------------------------------------------------------------

/**
 * Get all categories as a flat list ordered by level, then sort_order, then name.
 *
 * @return array
 */
function getAllCategories(): array
{
    $db = getDB();
    $stmt = $db->query(
        'SELECT * FROM categories WHERE is_active = 1 ORDER BY level ASC, sort_order ASC, name ASC'
    );
    return $stmt->fetchAll();
}

/**
 * Build the full hierarchical tree:
 *   Level 1 → Level 2 → Level 3
 *
 * Each node has a 'children' key containing its direct children.
 *
 * @return array Nested array of Level-1 categories
 */
function getCategoryTree(): array
{
    $db = getDB();
    $stmt = $db->query(
        'SELECT * FROM categories WHERE is_active = 1 ORDER BY level ASC, sort_order ASC, name ASC'
    );
    $all = $stmt->fetchAll();

    $indexed  = [];
    $children = [];

    foreach ($all as $cat) {
        $cat['children'] = [];
        $indexed[$cat['id']] = $cat;
    }

    $roots = [];
    foreach ($all as $cat) {
        if ($cat['parent_id'] === null) {
            $roots[] = &$indexed[$cat['id']];
        } else {
            $indexed[$cat['parent_id']]['children'][] = &$indexed[$cat['id']];
        }
    }
    unset($cat);

    return $roots;
}

/**
 * Get a single category by ID, including parent info.
 *
 * @param  int $categoryId
 * @return array|null
 */
function getCategory(int $categoryId): ?array
{
    $db = getDB();
    $stmt = $db->prepare(
        'SELECT c.*, p.name parent_name, p.slug parent_slug
         FROM categories c
         LEFT JOIN categories p ON p.id = c.parent_id
         WHERE c.id = ?'
    );
    $stmt->execute([$categoryId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Get direct children of a category (or root categories if parentId = null).
 *
 * @param  int|null $parentId
 * @return array
 */
function getChildren(?int $parentId): array
{
    $db = getDB();
    if ($parentId === null) {
        return getRootCategories();
    }
    $stmt = $db->prepare(
        'SELECT * FROM categories WHERE parent_id = ? AND is_active = 1 ORDER BY sort_order ASC, name ASC'
    );
    $stmt->execute([$parentId]);
    return $stmt->fetchAll();
}

/**
 * Get all Level-1 (root) categories.
 *
 * @return array
 */
function getRootCategories(): array
{
    $db = getDB();
    $stmt = $db->query(
        'SELECT * FROM categories WHERE parent_id IS NULL AND is_active = 1 ORDER BY sort_order ASC, name ASC'
    );
    return $stmt->fetchAll();
}

/**
 * Build breadcrumb array for a category.
 * Example: [{id:1, name:"Electronics"}, {id:12, name:"Phones"}, {id:45, name:"Smartphones"}]
 *
 * @param  int $categoryId
 * @return array
 */
function getCategoryBreadcrumb(int $categoryId): array
{
    $db = getDB();
    $breadcrumb = [];
    $id = $categoryId;

    for ($depth = 0; $depth < 4; $depth++) {
        $stmt = $db->prepare('SELECT id, name, slug, parent_id FROM categories WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $cat = $stmt->fetch();
        if (!$cat) break;
        array_unshift($breadcrumb, ['id' => $cat['id'], 'name' => $cat['name'], 'slug' => $cat['slug']]);
        if ($cat['parent_id'] === null) break;
        $id = (int)$cat['parent_id'];
    }

    return $breadcrumb;
}

/**
 * Get full path string: "Electronics > Phones > Smartphones"
 *
 * @param  int    $categoryId
 * @param  string $separator
 * @return string
 */
function getCategoryPath(int $categoryId, string $separator = ' > '): string
{
    $breadcrumb = getCategoryBreadcrumb($categoryId);
    return implode($separator, array_column($breadcrumb, 'name'));
}

/**
 * Get categories formatted for an HTML <select> dropdown.
 * Optionally filters to direct children of $parentId.
 *
 * @param  int|null $parentId  null = root categories
 * @return array   [{id, name, slug, level}]
 */
function getCategoriesForDropdown(?int $parentId = null): array
{
    $db = getDB();
    if ($parentId === null) {
        $stmt = $db->query(
            'SELECT id, name, slug, level FROM categories WHERE parent_id IS NULL AND is_active = 1 ORDER BY sort_order ASC, name ASC'
        );
    } else {
        $stmt = $db->prepare(
            'SELECT id, name, slug, level FROM categories WHERE parent_id = ? AND is_active = 1 ORDER BY sort_order ASC, name ASC'
        );
        $stmt->execute([$parentId]);
    }
    return $stmt->fetchAll();
}

/**
 * Count products in a category, optionally including all subcategory products.
 *
 * @param  int  $categoryId
 * @param  bool $includeChildren
 * @return int
 */
function getProductCount(int $categoryId, bool $includeChildren = true): int
{
    $db = getDB();

    if (!$includeChildren) {
        $stmt = $db->prepare('SELECT COUNT(*) FROM products WHERE category_id = ? AND status = "active"');
        $stmt->execute([$categoryId]);
        return (int)$stmt->fetchColumn();
    }

    // Collect this category + all descendants
    $ids = _getAllDescendantIds($db, $categoryId);
    $ids[] = $categoryId;

    if (empty($ids)) return 0;

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id IN ($placeholders) AND status = 'active'");
    $stmt->execute($ids);
    return (int)$stmt->fetchColumn();
}

/**
 * Search categories by name (case-insensitive, partial match).
 *
 * @param  string $query
 * @return array
 */
function searchCategories(string $query): array
{
    $db = getDB();
    $stmt = $db->prepare(
        'SELECT * FROM categories WHERE name LIKE ? AND is_active = 1 ORDER BY level ASC, sort_order ASC, name ASC LIMIT 50'
    );
    $stmt->execute(['%' . $query . '%']);
    return $stmt->fetchAll();
}

/**
 * Get a category by its URL slug.
 *
 * @param  string $slug
 * @return array|null
 */
function getCategoryBySlug(string $slug): ?array
{
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM categories WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// ---------------------------------------------------------------------------
// Write helpers (admin only — callers must enforce auth + CSRF)
// ---------------------------------------------------------------------------

/**
 * Create a category.
 *
 * @param  array $data  Keys: name, parent_id, slug, icon, description, sort_order,
 *                            commission_rate, is_active
 * @return int   New category ID
 * @throws RuntimeException on validation failure
 */
function createCategory(array $data): int
{
    $db = getDB();

    $name = trim($data['name'] ?? '');
    if ($name === '') {
        throw new RuntimeException('Category name is required.');
    }

    $parentId = isset($data['parent_id']) && $data['parent_id'] !== '' ? (int)$data['parent_id'] : null;

    // Validate parent and determine level
    $level = 1;
    if ($parentId !== null) {
        $parent = getCategory($parentId);
        if (!$parent) {
            throw new RuntimeException('Parent category does not exist.');
        }
        $level = (int)$parent['level'] + 1;
        if ($level > 3) {
            throw new RuntimeException('Maximum category depth is 3 levels.');
        }
    }

    $slug = generateCategorySlug($data['slug'] ?? '', $name);

    $stmt = $db->prepare(
        'INSERT INTO categories
            (parent_id, name, slug, description, icon, sort_order, commission_rate, level, is_active, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );
    $stmt->execute([
        $parentId,
        $name,
        $slug,
        $data['description']    ?? null,
        $data['icon']           ?? null,
        isset($data['sort_order']) ? (int)$data['sort_order'] : 0,
        isset($data['commission_rate']) && $data['commission_rate'] !== '' ? (float)$data['commission_rate'] : null,
        $level,
        isset($data['is_active']) ? ((int)(bool)$data['is_active']) : 1,
    ]);

    return (int)$db->lastInsertId();
}

/**
 * Update a category.
 *
 * @param  int   $categoryId
 * @param  array $data
 * @return bool
 * @throws RuntimeException on validation failure
 */
function updateCategory(int $categoryId, array $data): bool
{
    $db = getDB();

    $existing = getCategory($categoryId);
    if (!$existing) {
        throw new RuntimeException('Category not found.');
    }

    $fields = [];
    $params = [];

    if (isset($data['name']) && trim($data['name']) !== '') {
        $fields[] = 'name = ?';
        $params[] = trim($data['name']);
    }

    if (array_key_exists('parent_id', $data)) {
        $parentId = $data['parent_id'] !== '' && $data['parent_id'] !== null ? (int)$data['parent_id'] : null;

        // Prevent moving to own subtree
        if ($parentId !== null) {
            if ($parentId === $categoryId) {
                throw new RuntimeException('A category cannot be its own parent.');
            }
            $descendants = _getAllDescendantIds($db, $categoryId);
            if (in_array($parentId, $descendants)) {
                throw new RuntimeException('Cannot move category into its own subtree.');
            }

            $parent = getCategory($parentId);
            if (!$parent) {
                throw new RuntimeException('Parent category does not exist.');
            }
            $newLevel = (int)$parent['level'] + 1;
            if ($newLevel > 3) {
                throw new RuntimeException('Maximum category depth is 3 levels.');
            }
            $fields[] = 'level = ?';
            $params[] = $newLevel;
        } else {
            $fields[] = 'level = ?';
            $params[] = 1;
        }

        $fields[] = 'parent_id = ?';
        $params[] = $parentId;
    }

    if (isset($data['slug']) && trim($data['slug']) !== '') {
        $fields[] = 'slug = ?';
        $params[] = generateCategorySlug($data['slug'], $data['name'] ?? $existing['name'], $categoryId);
    }

    foreach (['description', 'icon'] as $col) {
        if (array_key_exists($col, $data)) {
            $fields[] = "$col = ?";
            $params[] = $data[$col] !== '' ? $data[$col] : null;
        }
    }

    if (isset($data['sort_order'])) {
        $fields[] = 'sort_order = ?';
        $params[] = (int)$data['sort_order'];
    }

    if (array_key_exists('commission_rate', $data)) {
        $fields[] = 'commission_rate = ?';
        $params[] = $data['commission_rate'] !== '' ? (float)$data['commission_rate'] : null;
    }

    if (isset($data['is_active'])) {
        $fields[] = 'is_active = ?';
        $params[] = (int)(bool)$data['is_active'];
    }

    if (empty($fields)) return false;

    $params[] = $categoryId;
    $db->prepare('UPDATE categories SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = ?')
       ->execute($params);

    return true;
}

/**
 * Soft-delete a category (sets is_active = 0).
 * Refuses if products are directly assigned or if it has active children.
 *
 * @param  int  $categoryId
 * @param  bool $force  If true, deletes even if products exist (admin override)
 * @return array ['success' => bool, 'message' => string]
 */
function deleteCategory(int $categoryId, bool $force = false): array
{
    $db = getDB();

    $cat = getCategory($categoryId);
    if (!$cat) {
        return ['success' => false, 'message' => 'Category not found.'];
    }

    // Check for products
    $productCount = getProductCount($categoryId, false);
    if ($productCount > 0 && !$force) {
        return [
            'success' => false,
            'message' => "Cannot delete: {$productCount} product(s) are directly assigned to this category.",
            'product_count' => $productCount,
        ];
    }

    // Check for active children
    $stmt = $db->prepare('SELECT COUNT(*) FROM categories WHERE parent_id = ? AND is_active = 1');
    $stmt->execute([$categoryId]);
    $childCount = (int)$stmt->fetchColumn();

    if ($childCount > 0 && !$force) {
        return [
            'success' => false,
            'message' => "Cannot delete: category has {$childCount} active sub-categorie(s). Delete children first.",
            'child_count' => $childCount,
        ];
    }

    $db->prepare('UPDATE categories SET is_active = 0, updated_at = NOW() WHERE id = ?')
       ->execute([$categoryId]);

    return ['success' => true, 'message' => 'Category deleted.'];
}

/**
 * Update sort_order for a set of categories (drag-and-drop reordering).
 *
 * @param  array $categoryIds  Ordered array of category IDs
 * @return bool
 */
function reorderCategories(array $categoryIds): bool
{
    $db = getDB();
    $stmt = $db->prepare('UPDATE categories SET sort_order = ?, updated_at = NOW() WHERE id = ?');
    foreach ($categoryIds as $order => $id) {
        $stmt->execute([(int)$order + 1, (int)$id]);
    }
    return true;
}

// ---------------------------------------------------------------------------
// Slug utility
// ---------------------------------------------------------------------------

/**
 * Generate a unique URL-friendly slug from a category name (or use provided slug).
 *
 * @param  string   $inputSlug   Caller-supplied slug (may be empty)
 * @param  string   $name        Category name (fallback source)
 * @param  int|null $excludeId   Category ID to exclude from uniqueness check (update)
 * @return string
 */
function generateCategorySlug(string $inputSlug, string $name, ?int $excludeId = null): string
{
    $base = $inputSlug !== '' ? $inputSlug : $name;
    // Normalise: lowercase, replace non-alphanumeric with dash
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $base), '-'));
    if ($slug === '') $slug = 'category';

    $db  = getDB();
    $i   = 0;
    $try = $slug;
    while (true) {
        if ($excludeId !== null) {
            $s = $db->prepare('SELECT id FROM categories WHERE slug = ? AND id != ? LIMIT 1');
            $s->execute([$try, $excludeId]);
        } else {
            $s = $db->prepare('SELECT id FROM categories WHERE slug = ? LIMIT 1');
            $s->execute([$try]);
        }
        if (!$s->fetch()) break;
        $try = $slug . '-' . (++$i);
    }

    return $try;
}

// ---------------------------------------------------------------------------
// Internal helpers
// ---------------------------------------------------------------------------

/**
 * Recursively collect all descendant IDs for a category.
 *
 * @param  PDO $db
 * @param  int $parentId
 * @return int[]
 */
function _getAllDescendantIds(PDO $db, int $parentId): array
{
    $stmt = $db->prepare('SELECT id FROM categories WHERE parent_id = ?');
    $stmt->execute([$parentId]);
    $ids = [];
    foreach ($stmt->fetchAll() as $row) {
        $ids[] = (int)$row['id'];
        $ids   = array_merge($ids, _getAllDescendantIds($db, (int)$row['id']));
    }
    return $ids;
}
