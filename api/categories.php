<?php
/**
 * api/categories.php — Category API
 *
 * Public (GET):
 *   action=tree          — Full category tree
 *   action=children      — Direct children of parent_id
 *   action=get           — Single category (params: id)
 *   action=breadcrumb    — Breadcrumb for a category (params: id)
 *   action=search        — Search categories by name (params: q)
 *
 * Admin (POST, admin + CSRF required):
 *   action=create        — Create category
 *   action=update        — Update category (params: id + fields)
 *   action=delete        — Soft-delete category (params: id)
 *   action=reorder       — Reorder categories (params: category_ids[])
 *
 * Admin (GET, admin required):
 *   action=product_count — Product count for category (params: id)
 */

require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/categories.php';

header('Content-Type: application/json');

$action = get('action', 'tree');
$method = $_SERVER['REQUEST_METHOD'];

// ── Public GET actions ──────────────────────────────────────────────────────
switch ($action) {

    case 'tree':
        echo json_encode(['success' => true, 'data' => getCategoryTree()]);
        exit;

    case 'children':
        $parentId = get('parent_id', '');
        $parentId = $parentId !== '' ? (int)$parentId : null;
        echo json_encode(['success' => true, 'data' => getChildren($parentId)]);
        exit;

    case 'get':
        $id = (int)get('id', 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'id parameter required']);
            exit;
        }
        $cat = getCategory($id);
        if (!$cat) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Category not found']);
            exit;
        }
        echo json_encode(['success' => true, 'data' => $cat]);
        exit;

    case 'breadcrumb':
        $id = (int)get('id', 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'id parameter required']);
            exit;
        }
        echo json_encode(['success' => true, 'data' => getCategoryBreadcrumb($id)]);
        exit;

    case 'search':
        $q = trim(get('q', ''));
        if ($q === '') {
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }
        echo json_encode(['success' => true, 'data' => searchCategories($q)]);
        exit;

    // ── Admin GET ───────────────────────────────────────────────────────────
    case 'product_count':
        requireAdmin();
        $id = (int)get('id', 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'id parameter required']);
            exit;
        }
        $include = get('include_children', '1') !== '0';
        echo json_encode(['success' => true, 'data' => ['count' => getProductCount($id, $include)]]);
        exit;

    // ── Admin POST ──────────────────────────────────────────────────────────
    case 'create':
        requireAdmin();
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'POST required']);
            exit;
        }
        if (!verifyCsrf()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
            exit;
        }
        try {
            $id = createCategory($_POST);
            echo json_encode([
                'success' => true,
                'message' => 'Category created successfully.',
                'data'    => ['id' => $id],
            ]);
        } catch (RuntimeException $e) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;

    case 'update':
        requireAdmin();
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'POST required']);
            exit;
        }
        if (!verifyCsrf()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
            exit;
        }
        $id = (int)post('id', 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'id parameter required']);
            exit;
        }
        try {
            updateCategory($id, $_POST);
            echo json_encode(['success' => true, 'message' => 'Category updated successfully.']);
        } catch (RuntimeException $e) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;

    case 'delete':
        requireAdmin();
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'POST required']);
            exit;
        }
        if (!verifyCsrf()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
            exit;
        }
        $id    = (int)post('id', 0);
        $force = post('force', '0') === '1';
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'id parameter required']);
            exit;
        }
        $result = deleteCategory($id, $force);
        if (!$result['success']) {
            http_response_code(422);
        }
        echo json_encode($result);
        exit;

    case 'reorder':
        requireAdmin();
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'POST required']);
            exit;
        }
        if (!verifyCsrf()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
            exit;
        }
        $ids = $_POST['category_ids'] ?? [];
        if (!is_array($ids) || empty($ids)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'category_ids[] required']);
            exit;
        }
        reorderCategories(array_map('intval', $ids));
        echo json_encode(['success' => true, 'message' => 'Categories reordered.']);
        exit;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        exit;
}
