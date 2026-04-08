<?php
/**
 * api/carry.php — Carry Service API (PR #16)
 *
 * GlobexSky traveler-based delivery system API.
 *
 * Actions:
 *   GET  trips            — Browse available trips
 *   GET  trip_detail      — Single trip with carrier info
 *   POST create_trip      — Post a new trip (carrier)
 *   POST update_trip      — Update trip (carrier)
 *   POST delete_trip      — Cancel trip (carrier)
 *   GET  my_trips         — Carrier's own trips
 *   POST request_carry    — Buyer requests carry on a trip
 *   POST accept_request   — Carrier accepts request
 *   POST decline_request  — Carrier declines request
 *   POST update_status    — Update carry status
 *   GET  my_requests      — Buyer's carry requests
 *   GET  trip_requests    — Requests for a trip (carrier)
 *   POST rate             — Buyer rates carrier after delivery
 *   GET  search           — Search trips matching shipment
 *   GET  calculate_fee    — Calculate carry fee
 */
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/carry.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';
$db     = getDB();

function jsonOk(array $data = []): never
{
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

function jsonErr(string $message, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

switch ($action) {

    // ── Browse available trips ──────────────────────────────────────────────
    case 'trips':
        $filters = [
            'origin'        => trim($_GET['origin'] ?? ''),
            'destination'   => trim($_GET['destination'] ?? ''),
            'date_from'     => trim($_GET['date_from'] ?? ''),
            'date_to'       => trim($_GET['date_to'] ?? ''),
            'max_weight'    => (float)($_GET['weight'] ?? 0),
            'price_min'     => (float)($_GET['price_min'] ?? 0),
            'price_max'     => (float)($_GET['price_max'] ?? 0),
            'verified_only' => !empty($_GET['verified_only']),
            'min_rating'    => (float)($_GET['min_rating'] ?? 0),
            'sort'          => trim($_GET['sort'] ?? 'date_asc'),
        ];
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(50, max(1, (int)($_GET['per_page'] ?? 20)));
        $result  = getTrips($filters, $page, $perPage);
        jsonOk($result);

    // ── Single trip detail ──────────────────────────────────────────────────
    case 'trip_detail':
        $tripId = (int)($_GET['trip_id'] ?? 0);
        if (!$tripId) jsonErr('trip_id is required');
        $trip = getTrip($tripId);
        if (!$trip) jsonErr('Trip not found', 404);
        jsonOk(['trip' => $trip]);

    // ── Post new trip (carrier) ─────────────────────────────────────────────
    case 'create_trip':
        requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonErr('POST required', 405);
        verifyCsrf();
        $result = createTrip((int)$_SESSION['user_id'], $_POST);
        if (!$result['success']) jsonErr($result['error'] ?? 'Failed to create trip');
        jsonOk(['trip_id' => $result['trip_id']]);

    // ── Update trip (carrier) ───────────────────────────────────────────────
    case 'update_trip':
        requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonErr('POST required', 405);
        verifyCsrf();
        $tripId = (int)($_POST['trip_id'] ?? 0);
        if (!$tripId) jsonErr('trip_id is required');
        $result = updateTrip($tripId, (int)$_SESSION['user_id'], $_POST);
        if (!$result['success']) jsonErr($result['error'] ?? 'Failed to update trip');
        jsonOk();

    // ── Cancel trip (carrier) ───────────────────────────────────────────────
    case 'delete_trip':
        requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonErr('POST required', 405);
        verifyCsrf();
        $tripId = (int)($_POST['trip_id'] ?? 0);
        if (!$tripId) jsonErr('trip_id is required');
        $result = deleteTrip($tripId, (int)$_SESSION['user_id']);
        if (!$result['success']) jsonErr($result['error'] ?? 'Failed to cancel trip');
        jsonOk();

    // ── Carrier's own trips ─────────────────────────────────────────────────
    case 'my_trips':
        requireAuth();
        $filters = ['status' => trim($_GET['status'] ?? '')];
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(50, max(1, (int)($_GET['per_page'] ?? 20)));
        $result  = getCarrierTrips((int)$_SESSION['user_id'], $filters, $page, $perPage);
        jsonOk($result);

    // ── Request carry on a trip (buyer) ────────────────────────────────────
    case 'request_carry':
        requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonErr('POST required', 405);
        verifyCsrf();
        $tripId = (int)($_POST['trip_id'] ?? 0);
        if (!$tripId) jsonErr('trip_id is required');
        $result = createCarryRequest((int)$_SESSION['user_id'], $tripId, $_POST);
        if (!$result['success']) jsonErr($result['error'] ?? 'Failed to create carry request');
        jsonOk(['request_id' => $result['request_id']]);

    // ── Accept request (carrier) ────────────────────────────────────────────
    case 'accept_request':
        requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonErr('POST required', 405);
        verifyCsrf();
        $requestId = (int)($_POST['request_id'] ?? 0);
        if (!$requestId) jsonErr('request_id is required');
        $result = acceptRequest($requestId, (int)$_SESSION['user_id']);
        if (!$result['success']) jsonErr($result['error'] ?? 'Failed to accept request');
        jsonOk();

    // ── Decline request (carrier) ───────────────────────────────────────────
    case 'decline_request':
        requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonErr('POST required', 405);
        verifyCsrf();
        $requestId = (int)($_POST['request_id'] ?? 0);
        if (!$requestId) jsonErr('request_id is required');
        $reason = trim($_POST['reason'] ?? '');
        $result = declineRequest($requestId, (int)$_SESSION['user_id'], $reason);
        if (!$result['success']) jsonErr($result['error'] ?? 'Failed to decline request');
        jsonOk();

    // ── Update carry status ─────────────────────────────────────────────────
    case 'update_status':
        requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonErr('POST required', 405);
        verifyCsrf();
        $requestId = (int)($_POST['request_id'] ?? 0);
        $status    = trim($_POST['status'] ?? '');
        $note      = trim($_POST['note'] ?? '');
        if (!$requestId) jsonErr('request_id is required');
        if (!$status) jsonErr('status is required');
        $result = updateCarryStatus($requestId, $status, (int)$_SESSION['user_id'], $note);
        if (!$result['success']) jsonErr($result['error'] ?? 'Failed to update status');
        jsonOk();

    // ── Buyer's carry requests ──────────────────────────────────────────────
    case 'my_requests':
        requireAuth();
        $filters = ['status' => trim($_GET['status'] ?? '')];
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(50, max(1, (int)($_GET['per_page'] ?? 20)));
        $result  = getBuyerRequests((int)$_SESSION['user_id'], $filters, $page, $perPage);
        jsonOk($result);

    // ── Requests for a trip (carrier) ───────────────────────────────────────
    case 'trip_requests':
        requireAuth();
        $tripId = (int)($_GET['trip_id'] ?? 0);
        if (!$tripId) jsonErr('trip_id is required');
        // Verify ownership
        $trip = getTrip($tripId);
        if (!$trip) jsonErr('Trip not found', 404);
        if ((int)$trip['carrier_id'] !== (int)$_SESSION['user_id']) jsonErr('Access denied', 403);
        jsonOk(['requests' => getRequestsForTrip($tripId)]);

    // ── Rate carrier after delivery (buyer) ─────────────────────────────────
    case 'rate':
        requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonErr('POST required', 405);
        verifyCsrf();
        $requestId = (int)($_POST['request_id'] ?? 0);
        $rating    = (int)($_POST['rating'] ?? 0);
        $review    = trim($_POST['review'] ?? '');
        if (!$requestId || !$rating) jsonErr('request_id and rating are required');
        $result = rateCarrier((int)$_SESSION['user_id'], $requestId, $rating, $review);
        if (!$result['success']) jsonErr($result['error'] ?? 'Failed to submit rating');
        jsonOk();

    // ── Search trips matching shipment ──────────────────────────────────────
    case 'search':
        $origin      = trim($_GET['origin'] ?? '');
        $destination = trim($_GET['destination'] ?? '');
        $date        = trim($_GET['date'] ?? date('Y-m-d'));
        $weightKg    = (float)($_GET['weight_kg'] ?? 0);
        if (!$origin || !$destination) jsonErr('origin and destination are required');
        $trips = searchTrips($origin, $destination, $date, $weightKg);
        jsonOk(['trips' => $trips]);

    // ── Calculate carry fee ─────────────────────────────────────────────────
    case 'calculate_fee':
        $tripId   = (int)($_GET['trip_id'] ?? 0);
        $weightKg = (float)($_GET['weight_kg'] ?? 0);
        if (!$tripId) jsonErr('trip_id is required');
        if ($weightKg <= 0) jsonErr('weight_kg must be greater than 0');
        $result = calculateCarryFee($tripId, $weightKg);
        if ($result['error']) jsonErr($result['error'], 404);
        jsonOk(['fee' => $result['fee'], 'method' => $result['method']]);

    // ── Carrier profile ─────────────────────────────────────────────────────
    case 'carrier_profile':
        $carrierId = (int)($_GET['carrier_id'] ?? 0);
        if (!$carrierId) jsonErr('carrier_id is required');
        $profile = getCarrierProfile($carrierId);
        if (!$profile) jsonErr('Carrier not found', 404);
        jsonOk(['profile' => $profile, 'stats' => getCarrierStats($carrierId)]);

    // ── Release payment (admin) ─────────────────────────────────────────────
    case 'release_payment':
        requireAuth();
        if (!isAdmin()) jsonErr('Admin access required', 403);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonErr('POST required', 405);
        $requestId = (int)($_POST['request_id'] ?? 0);
        if (!$requestId) jsonErr('request_id is required');
        $result = releaseCarryPayment($requestId);
        if (!$result['success']) jsonErr($result['error'] ?? 'Failed to release payment');
        jsonOk();

    default:
        jsonErr('Invalid action', 400);
}
