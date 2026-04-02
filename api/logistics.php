<?php
require_once __DIR__ . '/../includes/middleware.php';
header('Content-Type: application/json');
$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOut($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

switch ($action) {
    case 'list_routes':
        $stmt = $db->query('SELECT * FROM logistics_routes ORDER BY id DESC');
        jsonOut(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'add_route':
        requireRole('admin');
        validateCsrf();
        $origin    = sanitize($_POST['origin'] ?? '');
        $dest      = sanitize($_POST['destination'] ?? '');
        $carrier   = sanitize($_POST['carrier'] ?? '');
        $days      = sanitize($_POST['estimated_days'] ?? '');
        $cost      = sanitize($_POST['cost'] ?? '');
        $status    = sanitize($_POST['status'] ?? 'active');

        if (!$origin || !$dest || !$carrier || $days === '' || $cost === '') {
            jsonOut(['success' => false, 'message' => 'origin, destination, carrier, estimated_days, and cost are required'], 422);
        }
        if (!is_numeric($days) || (int)$days < 0) {
            jsonOut(['success' => false, 'message' => 'estimated_days must be a non-negative integer'], 422);
        }
        if (!is_numeric($cost) || $cost < 0) {
            jsonOut(['success' => false, 'message' => 'cost must be a non-negative number'], 422);
        }

        $stmt = $db->prepare(
            'INSERT INTO logistics_routes (origin, destination, carrier, estimated_days, cost, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$origin, $dest, $carrier, (int)$days, $cost, $status]);
        jsonOut(['success' => true, 'id' => $db->lastInsertId()]);
        break;

    case 'list_warehouses':
        $stmt = $db->query('SELECT * FROM warehouses ORDER BY name ASC');
        jsonOut(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'add_warehouse':
        requireRole('admin');
        validateCsrf();
        $name     = sanitize($_POST['name'] ?? '');
        $location = sanitize($_POST['location'] ?? '');
        $capacity = sanitize($_POST['capacity'] ?? '');
        $manager  = sanitize($_POST['manager'] ?? '');
        $status   = sanitize($_POST['status'] ?? 'active');

        if (!$name || !$location || $capacity === '') {
            jsonOut(['success' => false, 'message' => 'name, location, and capacity are required'], 422);
        }
        if (!is_numeric($capacity) || (int)$capacity < 0) {
            jsonOut(['success' => false, 'message' => 'capacity must be a non-negative integer'], 422);
        }

        $stmt = $db->prepare(
            'INSERT INTO warehouses (name, location, capacity, manager, status, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$name, $location, (int)$capacity, $manager, $status]);
        jsonOut(['success' => true, 'id' => $db->lastInsertId()]);
        break;

    case 'update_warehouse':
        requireRole('admin');
        validateCsrf();
        $id       = sanitize($_POST['id'] ?? '');
        $name     = sanitize($_POST['name'] ?? '');
        $location = sanitize($_POST['location'] ?? '');
        $capacity = sanitize($_POST['capacity'] ?? '');
        $manager  = sanitize($_POST['manager'] ?? '');
        $status   = sanitize($_POST['status'] ?? '');

        if (!$id) {
            jsonOut(['success' => false, 'message' => 'id is required'], 422);
        }

        $check = $db->prepare('SELECT id FROM warehouses WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetch()) {
            jsonOut(['success' => false, 'message' => 'Warehouse not found'], 404);
        }

        $fields = [];
        $params = [];
        if ($name !== '')     { $fields[] = 'name = ?';     $params[] = $name; }
        if ($location !== '') { $fields[] = 'location = ?'; $params[] = $location; }
        if ($capacity !== '') {
            if (!is_numeric($capacity) || (int)$capacity < 0) {
                jsonOut(['success' => false, 'message' => 'capacity must be a non-negative integer'], 422);
            }
            $fields[] = 'capacity = ?'; $params[] = (int)$capacity;
        }
        if ($manager !== '') { $fields[] = 'manager = ?'; $params[] = $manager; }
        if ($status !== '')  { $fields[] = 'status = ?';  $params[] = $status; }

        if (empty($fields)) {
            jsonOut(['success' => false, 'message' => 'No fields to update'], 422);
        }

        $params[] = $id;
        $stmt = $db->prepare('UPDATE warehouses SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = ?');
        $stmt->execute($params);
        jsonOut(['success' => true, 'message' => 'Warehouse updated']);
        break;

    default:
        jsonOut(['success' => false, 'message' => 'Invalid action'], 400);
}
