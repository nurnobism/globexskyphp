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
    case 'create':
        requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'POST required'], 405);
        verifyCsrf();
        $userId   = $_SESSION['user_id'];
        $senderAddr   = (int)($_POST['sender_address_id'] ?? 0);
        $receiverAddr = (int)($_POST['receiver_address_id'] ?? 0);
        $weight   = (float)($_POST['weight'] ?? 0);
        $length   = (float)($_POST['length'] ?? 0);
        $width    = (float)($_POST['width'] ?? 0);
        $height   = (float)($_POST['height'] ?? 0);
        $contents = trim($_POST['contents'] ?? '');
        $speed    = trim($_POST['speed'] ?? 'standard');
        $insured  = isset($_POST['insurance']) ? 1 : 0;
        if (!$senderAddr || !$receiverAddr || $weight <= 0) jsonResponse(['error' => 'Required fields missing'], 422);
        $tracking = strtoupper('GS' . date('ymd') . substr(uniqid(), -6));
        $stmt = $db->prepare("INSERT INTO parcels (user_id, sender_address_id, receiver_address_id, weight, length, width, height, contents, speed, insured, tracking_number, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,'pending')");
        $stmt->execute([$userId, $senderAddr, $receiverAddr, $weight, $length, $width, $height, $contents, $speed, $insured, $tracking]);
        jsonResponse(['success' => true, 'tracking_number' => $tracking, 'parcel_id' => $db->lastInsertId()]);

    case 'calculate':
        $weight     = (float)($_REQUEST['weight'] ?? 0);
        $length     = (float)($_REQUEST['length'] ?? 0);
        $width      = (float)($_REQUEST['width'] ?? 0);
        $height     = (float)($_REQUEST['height'] ?? 0);
        $fromCountry = trim($_REQUEST['from_country'] ?? '');
        $toCountry   = trim($_REQUEST['to_country'] ?? '');
        $speed       = trim($_REQUEST['speed'] ?? 'standard');
        $volumetric  = ($length * $width * $height) / 5000;
        $billable    = max($weight, $volumetric);
        $baseRate    = 8.50;
        $multiplier  = match($speed) { 'express' => 1.6, 'priority' => 2.2, default => 1.0 };
        $price       = round($billable * $baseRate * $multiplier, 2);
        jsonResponse(['success' => true, 'billable_weight' => round($billable, 2), 'volumetric_weight' => round($volumetric, 2), 'base_price' => round($billable * $baseRate, 2), 'multiplier' => $multiplier, 'total' => $price, 'currency' => 'USD']);

    case 'track':
        $tracking = trim($_REQUEST['tracking'] ?? '');
        if (!$tracking) jsonResponse(['error' => 'Tracking number required'], 422);
        $stmt = $db->prepare("SELECT p.*, sa.city AS from_city, sa.country AS from_country, ra.city AS to_city, ra.country AS to_country FROM parcels p LEFT JOIN addresses sa ON p.sender_address_id = sa.id LEFT JOIN addresses ra ON p.receiver_address_id = ra.id WHERE p.tracking_number = ?");
        $stmt->execute([$tracking]);
        $parcel = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$parcel) jsonResponse(['error' => 'Parcel not found'], 404);
        $stmt2 = $db->prepare("SELECT * FROM parcel_events WHERE parcel_id = ? ORDER BY created_at ASC");
        $stmt2->execute([$parcel['id']]);
        $events = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse(['success' => true, 'parcel' => $parcel, 'timeline' => $events]);

    case 'list':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $stmt = $db->prepare("SELECT p.*, sa.city AS from_city, ra.city AS to_city FROM parcels p LEFT JOIN addresses sa ON p.sender_address_id = sa.id LEFT JOIN addresses ra ON p.receiver_address_id = ra.id WHERE p.user_id = ? ORDER BY p.created_at DESC");
        $stmt->execute([$userId]);
        jsonResponse(['success' => true, 'parcels' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

    case 'addresses':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'GET') {
            $stmt = $db->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, label ASC");
            $stmt->execute([$userId]);
            jsonResponse(['success' => true, 'addresses' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } elseif ($method === 'POST') {
            verifyCsrf();
            $sub = $_POST['sub_action'] ?? 'add';
            if ($sub === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $db->prepare("DELETE FROM addresses WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $userId]);
                jsonResponse(['success' => true]);
            }
            $fields = ['label','full_name','address_line1','address_line2','city','state','postal_code','country','phone'];
            $vals = array_map(fn($f) => trim($_POST[$f] ?? ''), $fields);
            $isDefault = isset($_POST['is_default']) ? 1 : 0;
            if ($sub === 'edit') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $db->prepare("UPDATE addresses SET label=?,full_name=?,address_line1=?,address_line2=?,city=?,state=?,postal_code=?,country=?,phone=?,is_default=? WHERE id=? AND user_id=?");
                $stmt->execute([...$vals, $isDefault, $id, $userId]);
            } else {
                $stmt = $db->prepare("INSERT INTO addresses (user_id,label,full_name,address_line1,address_line2,city,state,postal_code,country,phone,is_default) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$userId, ...$vals, $isDefault]);
            }
            jsonResponse(['success' => true]);
        }
        break;

    case 'history':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $stmt = $db->prepare("SELECT p.tracking_number, p.weight, p.speed, p.status, p.created_at, sa.city AS from_city, ra.city AS to_city, ra.country AS to_country FROM parcels p LEFT JOIN addresses sa ON p.sender_address_id = sa.id LEFT JOIN addresses ra ON p.receiver_address_id = ra.id WHERE p.user_id = ? ORDER BY p.created_at DESC");
        $stmt->execute([$userId]);
        jsonResponse(['success' => true, 'history' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

    case 'cancel':
        requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'POST required'], 405);
        verifyCsrf();
        $userId     = $_SESSION['user_id'];
        $shipmentId = (int)($_POST['shipment_id'] ?? 0);
        if (!$shipmentId) jsonResponse(['error' => 'shipment_id required'], 422);
        try {
            $stmt = $db->prepare("UPDATE parcel_shipments SET status='cancelled' WHERE id=? AND user_id=? AND status IN ('pending','processing')");
            $stmt->execute([$shipmentId, $userId]);
            if ($stmt->rowCount() === 0) jsonResponse(['error' => 'Shipment not found or cannot be cancelled'], 404);
            jsonResponse(['success' => true]);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Database error'], 500);
        }

    case 'update_status':
        requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'POST required'], 405);
        verifyCsrf();
        $shipmentId  = (int)($_POST['shipment_id'] ?? 0);
        $newStatus   = trim($_POST['new_status'] ?? '');
        $location    = trim($_POST['location'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if (!$shipmentId || !$newStatus) jsonResponse(['error' => 'shipment_id and new_status required'], 422);
        try {
            $stmt = $db->prepare("UPDATE parcel_shipments SET status=? WHERE id=?");
            $stmt->execute([$newStatus, $shipmentId]);
            $stmt2 = $db->prepare("INSERT INTO parcel_tracking_events (shipment_id, status, location, description, created_at) VALUES (?,?,?,?,NOW())");
            $stmt2->execute([$shipmentId, $newStatus, $location, $description]);
            jsonResponse(['success' => true]);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Database error'], 500);
        }

    case 'add_tracking_event':
        requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'POST required'], 405);
        verifyCsrf();
        $userId      = $_SESSION['user_id'];
        $shipmentId  = (int)($_POST['shipment_id'] ?? 0);
        $status      = trim($_POST['status'] ?? '');
        $location    = trim($_POST['location'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if (!$shipmentId || !$status) jsonResponse(['error' => 'shipment_id and status required'], 422);
        try {
            $adminCheck = $db->prepare("SELECT role FROM users WHERE id=?");
            $adminCheck->execute([$userId]);
            $userRow = $adminCheck->fetch(PDO::FETCH_ASSOC);
            if (!$userRow || !in_array($userRow['role'] ?? '', ['admin', 'carrier'])) {
                jsonResponse(['error' => 'Admin or carrier access required'], 403);
            }
            $stmt = $db->prepare("INSERT INTO parcel_tracking_events (shipment_id, status, location, description, created_at) VALUES (?,?,?,?,NOW())");
            $stmt->execute([$shipmentId, $status, $location, $description]);
            jsonResponse(['success' => true, 'event_id' => $db->lastInsertId()]);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Database error'], 500);
        }

    case 'get_rates':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonResponse(['error' => 'GET required'], 405);
        $fromCountry = trim($_GET['from_country'] ?? '');
        $toCountry   = trim($_GET['to_country'] ?? '');
        if (!$fromCountry || !$toCountry) jsonResponse(['error' => 'from_country and to_country required'], 422);
        try {
            $stmt = $db->prepare("SELECT * FROM shipping_rates WHERE origin_country=? AND destination_country=?");
            $stmt->execute([$fromCountry, $toCountry]);
            $rate = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$rate) jsonResponse(['error' => 'No rate found for this route'], 404);
            jsonResponse(['success' => true, 'rate' => $rate]);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Database error'], 500);
        }

    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}
