<?php
require_once __DIR__ . '/../includes/middleware.php';

header('Content-Type: application/json');
$action = $_REQUEST['action'] ?? '';
$db = getDB();

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

switch ($action) {
    case 'list_rules':
        requireAdmin();
        $category = trim($_GET['category'] ?? '');
        if ($category) {
            $stmt = $db->prepare("SELECT * FROM pricing_rules WHERE category = ? ORDER BY sort_order ASC, created_at DESC");
            $stmt->execute([$category]);
        } else {
            $stmt = $db->query("SELECT * FROM pricing_rules ORDER BY category, sort_order ASC");
        }
        jsonResponse(['success' => true, 'rules' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

    case 'create_rule':
        requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'POST required'], 405);
        verifyCsrf();
        $name        = trim($_POST['name'] ?? '');
        $category    = trim($_POST['category'] ?? '');
        $valueType   = trim($_POST['value_type'] ?? 'percentage');
        $value       = (float)($_POST['value'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $metadata    = trim($_POST['metadata'] ?? '');
        $minOrder    = !empty($_POST['min_order_value']) ? (float)$_POST['min_order_value'] : null;
        $maxOrder    = !empty($_POST['max_order_value']) ? (float)$_POST['max_order_value'] : null;
        if (!$name || !$category) jsonResponse(['error' => 'Name and category are required'], 422);
        if (!in_array($valueType, ['percentage', 'fixed'])) jsonResponse(['error' => 'Invalid value type'], 422);
        // Validate JSON metadata if provided
        if ($metadata && !json_decode($metadata)) jsonResponse(['error' => 'Invalid JSON metadata'], 422);
        $stmt = $db->prepare("INSERT INTO pricing_rules (name, category, value_type, value, description, metadata, min_order_value, max_order_value, is_active, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)");
        $stmt->execute([$name, $category, $valueType, $value, $description, $metadata ?: null, $minOrder, $maxOrder, $_SESSION['user_id']]);
        $ruleId = $db->lastInsertId();
        // Log change
        $db->prepare("INSERT INTO pricing_history (rule_id, rule_name, category, old_value, new_value, changed_by, changed_at)
            VALUES (?, ?, ?, NULL, ?, ?, NOW())")->execute([$ruleId, $name, $category, $value, $_SESSION['user_id']]);
        header('Location: /pages/admin/pricing/' . $category . '.php');
        exit;

    case 'toggle_rule':
        requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'POST required'], 405);
        verifyCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM pricing_rules WHERE id = ?");
        $stmt->execute([$id]);
        $rule = $stmt->fetch();
        if (!$rule) jsonResponse(['error' => 'Rule not found'], 404);
        $newStatus = $rule['is_active'] ? 0 : 1;
        $db->prepare("UPDATE pricing_rules SET is_active = ? WHERE id = ?")->execute([$newStatus, $id]);
        $db->prepare("INSERT INTO pricing_history (rule_id, rule_name, category, old_value, new_value, changed_by, changed_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())")->execute([$id, $rule['name'], $rule['category'], $rule['is_active'] ? 'active' : 'inactive', $newStatus ? 'active' : 'inactive', $_SESSION['user_id']]);
        $catMap = ['commission'=>'commissions','supplier_plan'=>'supplier-plans','inspection'=>'inspection-pricing','dropship_markup'=>'dropship-markup','carry'=>'carry-pricing','api_platform'=>'api-pricing','flash_sale'=>'flash-sale-fees','advertising'=>'ad-pricing'];
        $redirect = $catMap[$rule['category']] ?? 'index';
        header('Location: /pages/admin/pricing/' . $redirect . '.php');
        exit;

    case 'delete_rule':
        requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'POST required'], 405);
        verifyCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM pricing_rules WHERE id = ?");
        $stmt->execute([$id]);
        $rule = $stmt->fetch();
        if (!$rule) jsonResponse(['error' => 'Rule not found'], 404);
        $db->prepare("DELETE FROM pricing_rules WHERE id = ?")->execute([$id]);
        $catMap = ['commission'=>'commissions','supplier_plan'=>'supplier-plans','inspection'=>'inspection-pricing','dropship_markup'=>'dropship-markup','carry'=>'carry-pricing','api_platform'=>'api-pricing','flash_sale'=>'flash-sale-fees','advertising'=>'ad-pricing'];
        $redirect = $catMap[$rule['category']] ?? 'index';
        header('Location: /pages/admin/pricing/' . $redirect . '.php');
        exit;

    case 'get_rule':
        requireAdmin();
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM pricing_rules WHERE id = ?");
        $stmt->execute([$id]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$rule) jsonResponse(['error' => 'Not found'], 404);
        jsonResponse(['success' => true, 'rule' => $rule]);

    case 'history':
        requireAdmin();
        $stmt = $db->query("SELECT ph.*, u.first_name, u.last_name FROM pricing_history ph LEFT JOIN users u ON ph.changed_by = u.id ORDER BY ph.changed_at DESC LIMIT 100");
        jsonResponse(['success' => true, 'history' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}
