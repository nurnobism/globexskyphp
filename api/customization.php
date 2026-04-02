<?php
require_once __DIR__ . '/../includes/middleware.php';
header('Content-Type: application/json');
$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOut($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

switch ($action) {
    case 'save':
        requireAuth();
        validateCsrf();
        $user       = getCurrentUser();
        $product_id = (int)($_POST['product_id'] ?? 0);
        $name       = sanitize($_POST['name'] ?? '');
        $options    = $_POST['options_json'] ?? '';
        $id         = (int)($_POST['id'] ?? 0);
        if ($product_id <= 0 || $name === '' || $options === '') {
            jsonOut(['success' => false, 'message' => 'product_id, name and options_json are required'], 400);
        }
        $decoded = json_decode($options, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            jsonOut(['success' => false, 'message' => 'options_json must be valid JSON'], 400);
        }
        $options_clean = json_encode($decoded);
        $productCheck = $db->prepare('SELECT id FROM products WHERE id = ?');
        $productCheck->execute([$product_id]);
        if (!$productCheck->fetch()) jsonOut(['success' => false, 'message' => 'Product not found'], 404);
        if ($id > 0) {
            $existing = $db->prepare('SELECT id, user_id FROM product_customizations WHERE id = ?');
            $existing->execute([$id]);
            $row = $existing->fetch(PDO::FETCH_ASSOC);
            if (!$row) jsonOut(['success' => false, 'message' => 'Customization not found'], 404);
            if ((int)$row['user_id'] !== (int)$user['id']) {
                jsonOut(['success' => false, 'message' => 'Forbidden'], 403);
            }
            $stmt = $db->prepare(
                'UPDATE product_customizations SET product_id=?, name=?, options_json=?, updated_at=NOW() WHERE id=? AND user_id=?'
            );
            $stmt->execute([$product_id, $name, $options_clean, $id, $user['id']]);
            jsonOut(['success' => true, 'message' => 'Customization updated', 'id' => $id]);
        } else {
            $stmt = $db->prepare(
                'INSERT INTO product_customizations (user_id, product_id, name, options_json, created_at)
                 VALUES (?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$user['id'], $product_id, $name, $options_clean]);
            $newId = $db->lastInsertId();
            jsonOut(['success' => true, 'message' => 'Customization saved', 'id' => $newId], 201);
        }

    case 'load':
        requireAuth();
        $user = getCurrentUser();
        $id   = (int)($_GET['id'] ?? 0);
        if ($id <= 0) jsonOut(['success' => false, 'message' => 'id required'], 400);
        $stmt = $db->prepare('SELECT * FROM product_customizations WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) jsonOut(['success' => false, 'message' => 'Customization not found'], 404);
        if ($row['options_json']) {
            $row['options'] = json_decode($row['options_json'], true);
        }
        jsonOut(['success' => true, 'data' => $row]);
    break;

    case 'list':
        requireAuth();
        $user = getCurrentUser();
        $page = (int)($_GET['page'] ?? 1);
        $sql  = 'SELECT pc.id, pc.product_id, pc.name, pc.created_at, pc.updated_at, p.name AS product_name
                 FROM product_customizations pc
                 LEFT JOIN products p ON p.id = pc.product_id
                 WHERE pc.user_id = ?
                 ORDER BY pc.created_at DESC';
        $result = paginate($db, $sql, [$user['id']], $page);
        jsonOut(['success' => true, 'data' => $result]);
    break;

    case 'delete':
        requireAuth();
        validateCsrf();
        $user = getCurrentUser();
        $id   = (int)($_POST['id'] ?? 0);
        if ($id <= 0) jsonOut(['success' => false, 'message' => 'id required'], 400);
        $check = $db->prepare('SELECT id, user_id FROM product_customizations WHERE id = ?');
        $check->execute([$id]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        if (!$row) jsonOut(['success' => false, 'message' => 'Customization not found'], 404);
        if ((int)$row['user_id'] !== (int)$user['id']) {
            jsonOut(['success' => false, 'message' => 'Forbidden'], 403);
        }
        $db->prepare('DELETE FROM product_customizations WHERE id = ? AND user_id = ?')->execute([$id, $user['id']]);
        jsonOut(['success' => true, 'message' => 'Customization deleted']);
    break;

    default:
        jsonOut(['success' => false, 'message' => 'Invalid action'], 400);
}
