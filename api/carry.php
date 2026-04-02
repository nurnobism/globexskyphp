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
    case 'list_trips':
        $stmt = $db->prepare("SELECT ct.*, u.full_name, u.rating FROM carry_trips ct JOIN users u ON ct.user_id = u.id WHERE ct.flight_date >= CURDATE() AND ct.status = 'active' ORDER BY ct.flight_date ASC LIMIT 50");
        $stmt->execute();
        jsonResponse(['success' => true, 'trips' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

    case 'register':
        requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'POST required'], 405);
        verifyCsrf();
        $userId = $_SESSION['user_id'];
        $fullName  = trim($_POST['full_name'] ?? '');
        $passport  = trim($_POST['passport_number'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $nationality = trim($_POST['nationality'] ?? '');
        $frequency   = trim($_POST['travel_frequency'] ?? '');
        $bio         = trim($_POST['bio'] ?? '');
        if (!$fullName || !$passport || !$phone) jsonResponse(['error' => 'Required fields missing'], 422);
        $idPath = null;
        if (!empty($_FILES['id_upload']['tmp_name'])) {
            $ext = pathinfo($_FILES['id_upload']['name'], PATHINFO_EXTENSION);
            $idPath = 'uploads/ids/' . uniqid('id_', true) . '.' . $ext;
            move_uploaded_file($_FILES['id_upload']['tmp_name'], __DIR__ . '/../' . $idPath);
        }
        $stmt = $db->prepare("INSERT INTO carriers (user_id, full_name, passport_number, id_document, phone, nationality, travel_frequency, bio, status) VALUES (?,?,?,?,?,?,?,?,'pending') ON DUPLICATE KEY UPDATE full_name=VALUES(full_name), passport_number=VALUES(passport_number), status='pending'");
        $stmt->execute([$userId, $fullName, $passport, $idPath, $phone, $nationality, $frequency, $bio]);
        jsonResponse(['success' => true, 'message' => 'Registration submitted for review']);

    case 'create_request':
        requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'POST required'], 405);
        verifyCsrf();
        $userId  = $_SESSION['user_id'];
        $from    = trim($_POST['from_city'] ?? '');
        $to      = trim($_POST['to_city'] ?? '');
        $date    = trim($_POST['flight_date'] ?? '');
        $weight  = (float)($_POST['available_weight'] ?? 0);
        $rate    = (float)($_POST['rate_per_kg'] ?? 0);
        $types   = implode(',', (array)($_POST['item_types'] ?? []));
        if (!$from || !$to || !$date || $weight <= 0) jsonResponse(['error' => 'Required fields missing'], 422);
        $ticketPath = null;
        if (!empty($_FILES['flight_ticket']['tmp_name'])) {
            $ext = pathinfo($_FILES['flight_ticket']['name'], PATHINFO_EXTENSION);
            $ticketPath = 'uploads/tickets/' . uniqid('tk_', true) . '.' . $ext;
            move_uploaded_file($_FILES['flight_ticket']['tmp_name'], __DIR__ . '/../' . $ticketPath);
        }
        $stmt = $db->prepare("INSERT INTO carry_trips (user_id, from_city, to_city, flight_date, available_weight, rate_per_kg, item_types, ticket_path, status) VALUES (?,?,?,?,?,?,?,?,'active')");
        $stmt->execute([$userId, $from, $to, $date, $weight, $rate, $types, $ticketPath]);
        jsonResponse(['success' => true, 'trip_id' => $db->lastInsertId()]);

    case 'list_requests':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $stmt = $db->prepare("SELECT * FROM carry_trips WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        jsonResponse(['success' => true, 'requests' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

    case 'match':
        requireAuth();
        $from = trim($_GET['from'] ?? '');
        $to   = trim($_GET['to'] ?? '');
        $weight = (float)($_GET['weight'] ?? 0);
        $stmt = $db->prepare("SELECT ct.*, u.full_name, u.rating FROM carry_trips ct JOIN users u ON ct.user_id = u.id WHERE ct.from_city = ? AND ct.to_city = ? AND ct.available_weight >= ? AND ct.flight_date >= CURDATE() AND ct.status = 'active' ORDER BY ct.flight_date ASC");
        $stmt->execute([$from, $to, $weight]);
        jsonResponse(['success' => true, 'matches' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

    case 'accept':
        requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'POST required'], 405);
        $carrierId = $_SESSION['user_id'];
        $requestId = (int)($_POST['request_id'] ?? 0);
        $stmt = $db->prepare("UPDATE carry_deliveries SET carrier_id = ?, status = 'accepted' WHERE id = ? AND status = 'pending'");
        $stmt->execute([$carrierId, $requestId]);
        jsonResponse(['success' => true]);

    case 'complete':
        requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'POST required'], 405);
        $userId     = $_SESSION['user_id'];
        $deliveryId = (int)($_POST['delivery_id'] ?? 0);
        $stmt = $db->prepare("UPDATE carry_deliveries SET status = 'completed', completed_at = NOW() WHERE id = ? AND carrier_id = ?");
        $stmt->execute([$deliveryId, $userId]);
        jsonResponse(['success' => true]);

    case 'earnings':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $stmt = $db->prepare("SELECT COALESCE(SUM(earning),0) AS total, COALESCE(SUM(CASE WHEN paid=0 THEN earning END),0) AS pending, COALESCE(SUM(CASE WHEN paid=1 THEN earning END),0) AS withdrawn FROM carrier_earnings WHERE user_id = ?");
        $stmt->execute([$userId]);
        jsonResponse(['success' => true, 'earnings' => $stmt->fetch(PDO::FETCH_ASSOC)]);

    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}
