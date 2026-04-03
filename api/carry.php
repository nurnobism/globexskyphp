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

    case 'add_trip':
        requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'POST required'], 405);
        verifyCsrf();
        $userId          = $_SESSION['user_id'];
        $departureCity   = trim($_POST['departure_city'] ?? '');
        $departureCountry = trim($_POST['departure_country'] ?? '');
        $arrivalCity     = trim($_POST['arrival_city'] ?? '');
        $arrivalCountry  = trim($_POST['arrival_country'] ?? '');
        $travelDate      = trim($_POST['travel_date'] ?? '');
        $capacityKg      = (float)($_POST['available_capacity_kg'] ?? 0);
        $pricePerKg      = (float)($_POST['price_per_kg'] ?? 0);
        $transportMode   = trim($_POST['transport_mode'] ?? 'flight');
        $notes           = trim($_POST['notes'] ?? '');
        if (!$departureCity || !$arrivalCity || !$travelDate || $capacityKg <= 0) {
            jsonResponse(['error' => 'Required fields missing'], 422);
        }
        try {
            $carrierCheck = $db->prepare("SELECT id FROM carriers WHERE user_id=? AND status='active'");
            $carrierCheck->execute([$userId]);
            $carrierRow = $carrierCheck->fetch(PDO::FETCH_ASSOC);
            if (!$carrierRow) jsonResponse(['error' => 'Active carrier profile required'], 403);
            $carrierId = $carrierRow['id'];
            $stmt = $db->prepare("INSERT INTO carrier_trips (carrier_id, departure_city, departure_country, arrival_city, arrival_country, travel_date, available_capacity_kg, price_per_kg, transport_mode, notes, status, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,'active',NOW())");
            $stmt->execute([$carrierId, $departureCity, $departureCountry, $arrivalCity, $arrivalCountry, $travelDate, $capacityKg, $pricePerKg, $transportMode, $notes]);
            jsonResponse(['success' => true, 'trip_id' => $db->lastInsertId()]);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Database error'], 500);
        }

    case 'post_request':
        requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'POST required'], 405);
        verifyCsrf();
        $userId          = $_SESSION['user_id'];
        $title           = trim($_POST['title'] ?? '');
        $description     = trim($_POST['description'] ?? '');
        $category        = trim($_POST['category'] ?? '');
        $weightKg        = (float)($_POST['weight_kg'] ?? 0);
        $fromCity        = trim($_POST['from_city'] ?? '');
        $fromCountry     = trim($_POST['from_country'] ?? '');
        $toCity          = trim($_POST['to_city'] ?? '');
        $toCountry       = trim($_POST['to_country'] ?? '');
        $preferredFrom   = trim($_POST['preferred_date_from'] ?? '');
        $preferredTo     = trim($_POST['preferred_date_to'] ?? '');
        $budget          = (float)($_POST['budget'] ?? 0);
        $specialHandling = trim($_POST['special_handling'] ?? '');
        if (!$title || !$fromCity || !$toCity) jsonResponse(['error' => 'Required fields missing'], 422);
        try {
            $stmt = $db->prepare("INSERT INTO carry_requests (sender_id, title, description, category, weight_kg, from_city, from_country, to_city, to_country, preferred_date_from, preferred_date_to, budget, special_handling, status, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'open',NOW())");
            $stmt->execute([$userId, $title, $description, $category, $weightKg, $fromCity, $fromCountry, $toCity, $toCountry, $preferredFrom ?: null, $preferredTo ?: null, $budget ?: null, $specialHandling]);
            jsonResponse(['success' => true, 'request_id' => $db->lastInsertId()]);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Database error'], 500);
        }

    case 'browse_requests':
        try {
            $stmt = $db->prepare("SELECT cr.*, u.full_name AS sender_name FROM carry_requests cr JOIN users u ON cr.sender_id = u.id WHERE cr.status='open' ORDER BY cr.created_at DESC LIMIT 50");
            $stmt->execute();
            jsonResponse(['success' => true, 'requests' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Database error'], 500);
        }

    case 'accept_request':
        requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'POST required'], 405);
        verifyCsrf();
        $userId    = $_SESSION['user_id'];
        $requestId = (int)($_POST['request_id'] ?? 0);
        if (!$requestId) jsonResponse(['error' => 'request_id required'], 422);
        try {
            $carrierStmt = $db->prepare("SELECT id FROM carriers WHERE user_id=? AND status='active'");
            $carrierStmt->execute([$userId]);
            $carrier = $carrierStmt->fetch(PDO::FETCH_ASSOC);
            if (!$carrier) jsonResponse(['error' => 'Active carrier profile required'], 403);
            $carrierId = $carrier['id'];
            $tripStmt = $db->prepare("SELECT id FROM carrier_trips WHERE carrier_id=? AND status='active' AND travel_date >= CURDATE() LIMIT 1");
            $tripStmt->execute([$carrierId]);
            $trip = $tripStmt->fetch(PDO::FETCH_ASSOC);
            if (!$trip) jsonResponse(['error' => 'No active trip found'], 404);
            $reqStmt = $db->prepare("SELECT id, sender_id FROM carry_requests WHERE id=? AND status='open'");
            $reqStmt->execute([$requestId]);
            $req = $reqStmt->fetch(PDO::FETCH_ASSOC);
            if (!$req) jsonResponse(['error' => 'Request not found or already matched'], 404);
            $agreedPrice = (float)($req['budget'] ?? 0);
            // Read commission rate from carry_service_settings (fallback to 15%)
            $commPct = 15.0;
            try {
                $cStmt = $db->prepare("SELECT setting_value FROM carry_service_settings WHERE setting_key='platform_commission_percent' LIMIT 1");
                $cStmt->execute();
                $cRow = $cStmt->fetch(PDO::FETCH_ASSOC);
                if ($cRow) $commPct = (float)$cRow['setting_value'];
            } catch (Exception $ignored) {}
            $commission  = round($agreedPrice * ($commPct / 100), 2);
            $carrierEarn = round($agreedPrice - $commission, 2);
            $matchStmt = $db->prepare("INSERT INTO carry_matches (request_id, trip_id, carrier_id, sender_id, agreed_price, platform_commission, carrier_earning, status, created_at) VALUES (?,?,?,?,?,?,?,'pending',NOW())");
            $matchStmt->execute([$requestId, $trip['id'], $carrierId, $req['sender_id'], $agreedPrice, $commission, $carrierEarn]);
            $matchId = $db->lastInsertId();
            $updStmt = $db->prepare("UPDATE carry_requests SET status='matched' WHERE id=?");
            $updStmt->execute([$requestId]);
            jsonResponse(['success' => true, 'match_id' => $matchId]);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Database error'], 500);
        }

    case 'update_match_status':
        requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'POST required'], 405);
        verifyCsrf();
        $userId    = $_SESSION['user_id'];
        $matchId   = (int)($_POST['match_id'] ?? 0);
        $newStatus = trim($_POST['new_status'] ?? '');
        if (!$matchId || !$newStatus) jsonResponse(['error' => 'match_id and new_status required'], 422);
        try {
            $carrierStmt = $db->prepare("SELECT id FROM carriers WHERE user_id=? AND status='active'");
            $carrierStmt->execute([$userId]);
            $carrier = $carrierStmt->fetch(PDO::FETCH_ASSOC);
            $carrierId = $carrier ? $carrier['id'] : null;
            $checkStmt = $db->prepare("SELECT id FROM carry_matches WHERE id=? AND (carrier_id=? OR sender_id=?)");
            $checkStmt->execute([$matchId, $carrierId ?? 0, $userId]);
            if (!$checkStmt->fetch()) jsonResponse(['error' => 'Match not found or access denied'], 403);
            $stmt = $db->prepare("UPDATE carry_matches SET status=? WHERE id=?");
            $stmt->execute([$newStatus, $matchId]);
            jsonResponse(['success' => true]);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Database error'], 500);
        }

    case 'dashboard_stats':
        requireAuth();
        $userId = $_SESSION['user_id'];
        try {
            $carrierStmt = $db->prepare("SELECT id FROM carriers WHERE user_id=? AND status='active'");
            $carrierStmt->execute([$userId]);
            $carrier   = $carrierStmt->fetch(PDO::FETCH_ASSOC);
            $carrierId = $carrier ? $carrier['id'] : null;
            $tripCount = 0;
            if ($carrierId) {
                $tripStmt = $db->prepare("SELECT COUNT(*) FROM carrier_trips WHERE carrier_id=? AND status='active'");
                $tripStmt->execute([$carrierId]);
                $tripCount = (int)$tripStmt->fetchColumn();
            }
            $reqStmt = $db->prepare("SELECT COUNT(*) FROM carry_requests WHERE sender_id=? AND status='open'");
            $reqStmt->execute([$userId]);
            $activeRequests = (int)$reqStmt->fetchColumn();
            $earnStmt = $db->prepare("SELECT COALESCE(SUM(CASE WHEN type='delivery_earning' THEN amount ELSE 0 END),0) AS total_earnings, COALESCE(SUM(CASE WHEN type='payout' THEN ABS(amount) ELSE 0 END),0) AS total_withdrawn FROM carrier_earning_log WHERE carrier_id=?");
            $earnStmt->execute([$carrierId ?? 0]);
            $earnings = $earnStmt->fetch(PDO::FETCH_ASSOC);
            $totalEarnings  = (float)($earnings['total_earnings'] ?? 0);
            $totalWithdrawn = (float)($earnings['total_withdrawn'] ?? 0);
            jsonResponse([
                'success'         => true,
                'trip_count'      => $tripCount,
                'active_requests' => $activeRequests,
                'total_earnings'  => $totalEarnings,
                'pending_payout'  => max(0.0, $totalEarnings - $totalWithdrawn),
            ]);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Database error'], 500);
        }

    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}
