<?php
/**
 * includes/carry.php — Carry Service Library (PR #16)
 *
 * GlobexSky's unique traveler-based delivery system.
 * Travelers post trips; buyers request package carry on those trips.
 *
 * Status flow:
 *   pending → accepted → picked_up → in_transit → delivered → completed
 *   (also: declined, cancelled, disputed)
 */

// ── Constants ─────────────────────────────────────────────────────────────────

define('CARRY_VALID_STATUSES', [
    'pending', 'accepted', 'picked_up', 'in_transit',
    'delivered', 'completed', 'declined', 'cancelled', 'disputed',
]);

define('CARRY_STATUS_TRANSITIONS', [
    'pending'    => ['accepted', 'declined', 'cancelled'],
    'accepted'   => ['picked_up', 'cancelled'],
    'picked_up'  => ['in_transit', 'disputed'],
    'in_transit' => ['delivered', 'disputed'],
    'delivered'  => ['completed'],
    'completed'  => [],
    'declined'   => [],
    'cancelled'  => [],
    'disputed'   => ['completed', 'cancelled'],
]);

// ── Trip Management ───────────────────────────────────────────────────────────

/**
 * Carrier posts a new trip.
 *
 * @param int   $carrierId  User ID of the carrier
 * @param array $data       Trip fields
 * @return array ['success'=>bool, 'trip_id'=>int|null, 'error'=>string|null]
 */
function createTrip(int $carrierId, array $data): array
{
    $db = getDB();

    $required = ['origin_city', 'origin_country', 'destination_city', 'destination_country', 'departure_date', 'arrival_date', 'max_weight_kg'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'trip_id' => null, 'error' => "Field '$field' is required"];
        }
    }

    $maxWeightKg = (float)($data['max_weight_kg'] ?? 0);
    if ($maxWeightKg <= 0) {
        return ['success' => false, 'trip_id' => null, 'error' => 'max_weight_kg must be greater than 0'];
    }

    $pricePerKg = (float)($data['price_per_kg'] ?? 0);
    $flatRate   = isset($data['flat_rate']) && $data['flat_rate'] !== '' ? (float)$data['flat_rate'] : null;

    try {
        $stmt = $db->prepare(
            'INSERT INTO carry_trips
                (carrier_id, origin_city, origin_country, destination_city, destination_country,
                 departure_date, arrival_date, max_weight_kg, max_dimensions,
                 available_space_description, price_per_kg, flat_rate,
                 carrier_notes, status, is_active, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())'
        );
        $stmt->execute([
            $carrierId,
            trim($data['origin_city']),
            trim($data['origin_country']),
            trim($data['destination_city']),
            trim($data['destination_country']),
            $data['departure_date'],
            $data['arrival_date'],
            $maxWeightKg,
            isset($data['max_dimensions']) ? trim($data['max_dimensions']) : null,
            isset($data['available_space_description']) ? trim($data['available_space_description']) : null,
            $pricePerKg,
            $flatRate,
            isset($data['carrier_notes']) ? trim($data['carrier_notes']) : null,
            'active',
            isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1,
        ]);
        return ['success' => true, 'trip_id' => (int)$db->lastInsertId(), 'error' => null];
    } catch (PDOException $e) {
        error_log('createTrip error: ' . $e->getMessage());
        return ['success' => false, 'trip_id' => null, 'error' => 'Database error'];
    }
}

/**
 * Update an existing trip (carrier only).
 *
 * @param int   $tripId    Trip ID
 * @param int   $carrierId Carrier user ID (ownership check)
 * @param array $data      Fields to update
 * @return array ['success'=>bool, 'error'=>string|null]
 */
function updateTrip(int $tripId, int $carrierId, array $data): array
{
    $db = getDB();

    try {
        $stmt = $db->prepare('SELECT id, carrier_id, status FROM carry_trips WHERE id = ? LIMIT 1');
        $stmt->execute([$tripId]);
        $trip = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error'];
    }

    if (!$trip) {
        return ['success' => false, 'error' => 'Trip not found'];
    }
    if ((int)$trip['carrier_id'] !== $carrierId) {
        return ['success' => false, 'error' => 'Access denied'];
    }
    if (in_array($trip['status'], ['cancelled', 'completed'], true)) {
        return ['success' => false, 'error' => 'Cannot update a cancelled or completed trip'];
    }

    $allowed = ['origin_city', 'origin_country', 'destination_city', 'destination_country',
                'departure_date', 'arrival_date', 'max_weight_kg', 'max_dimensions',
                'available_space_description', 'price_per_kg', 'flat_rate',
                'carrier_notes', 'is_active'];
    $sets   = [];
    $params = [];
    foreach ($allowed as $col) {
        if (array_key_exists($col, $data)) {
            $sets[]   = "`$col` = ?";
            $params[] = $data[$col] === '' ? null : $data[$col];
        }
    }
    if (empty($sets)) {
        return ['success' => false, 'error' => 'No fields to update'];
    }
    $params[] = $tripId;

    try {
        $db->prepare('UPDATE carry_trips SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
        return ['success' => true, 'error' => null];
    } catch (PDOException $e) {
        error_log('updateTrip error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Database error'];
    }
}

/**
 * Cancel / delete a trip (carrier only).
 *
 * @param int $tripId    Trip ID
 * @param int $carrierId Carrier user ID
 * @return array ['success'=>bool, 'error'=>string|null]
 */
function deleteTrip(int $tripId, int $carrierId): array
{
    $db = getDB();

    try {
        $stmt = $db->prepare('SELECT id, carrier_id, status FROM carry_trips WHERE id = ? LIMIT 1');
        $stmt->execute([$tripId]);
        $trip = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error'];
    }

    if (!$trip) {
        return ['success' => false, 'error' => 'Trip not found'];
    }
    if ((int)$trip['carrier_id'] !== $carrierId) {
        return ['success' => false, 'error' => 'Access denied'];
    }

    try {
        $db->prepare('UPDATE carry_trips SET status = ?, is_active = 0, updated_at = NOW() WHERE id = ?')
           ->execute(['cancelled', $tripId]);
        return ['success' => true, 'error' => null];
    } catch (PDOException $e) {
        error_log('deleteTrip error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Database error'];
    }
}

/**
 * Get trip details with carrier info.
 *
 * @param int $tripId Trip ID
 * @return array|null Trip row or null
 */
function getTrip(int $tripId): ?array
{
    $db = getDB();
    try {
        $stmt = $db->prepare(
            'SELECT ct.*,
                    u.first_name, u.last_name, u.email, u.avatar,
                    COALESCE(cp.verified, 0)          AS carrier_verified,
                    COALESCE(cp.trips_completed, 0)   AS trips_completed,
                    COALESCE(cp.average_rating, 0)    AS carrier_rating
             FROM carry_trips ct
             LEFT JOIN users u ON u.id = ct.carrier_id
             LEFT JOIN carrier_profiles cp ON cp.user_id = ct.carrier_id
             WHERE ct.id = ?
             LIMIT 1'
        );
        $stmt->execute([$tripId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        error_log('getTrip error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Browse available trips with optional filters.
 *
 * @param array $filters  Possible keys: origin, destination, date_from, date_to, max_weight, price_min, price_max, verified_only, min_rating
 * @param int   $page
 * @param int   $perPage
 * @return array ['trips'=>array, 'total'=>int, 'pages'=>int]
 */
function getTrips(array $filters = [], int $page = 1, int $perPage = 20): array
{
    $db    = getDB();
    $where = ['ct.is_active = 1', 'ct.status = "active"', 'ct.departure_date >= CURDATE()'];
    $params = [];

    if (!empty($filters['origin'])) {
        $where[]  = '(ct.origin_city LIKE ? OR ct.origin_country LIKE ?)';
        $like = '%' . $filters['origin'] . '%';
        $params[] = $like;
        $params[] = $like;
    }
    if (!empty($filters['destination'])) {
        $where[]  = '(ct.destination_city LIKE ? OR ct.destination_country LIKE ?)';
        $like = '%' . $filters['destination'] . '%';
        $params[] = $like;
        $params[] = $like;
    }
    if (!empty($filters['date_from'])) {
        $where[]  = 'ct.departure_date >= ?';
        $params[] = $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $where[]  = 'ct.departure_date <= ?';
        $params[] = $filters['date_to'];
    }
    if (isset($filters['max_weight']) && $filters['max_weight'] > 0) {
        $where[]  = 'ct.max_weight_kg >= ?';
        $params[] = (float)$filters['max_weight'];
    }
    if (isset($filters['price_min']) && $filters['price_min'] > 0) {
        $where[]  = 'ct.price_per_kg >= ?';
        $params[] = (float)$filters['price_min'];
    }
    if (isset($filters['price_max']) && $filters['price_max'] > 0) {
        $where[]  = 'ct.price_per_kg <= ?';
        $params[] = (float)$filters['price_max'];
    }
    if (!empty($filters['verified_only'])) {
        $where[] = 'COALESCE(cp.verified, 0) = 1';
    }
    if (isset($filters['min_rating']) && $filters['min_rating'] > 0) {
        $where[]  = 'COALESCE(cp.average_rating, 0) >= ?';
        $params[] = (float)$filters['min_rating'];
    }

    $whereStr = implode(' AND ', $where);
    $offset   = ($page - 1) * $perPage;

    $sort = 'ct.departure_date ASC';
    if (!empty($filters['sort'])) {
        $sortMap = [
            'date_asc'   => 'ct.departure_date ASC',
            'date_desc'  => 'ct.departure_date DESC',
            'price_asc'  => 'ct.price_per_kg ASC',
            'price_desc' => 'ct.price_per_kg DESC',
            'rating'     => 'COALESCE(cp.average_rating, 0) DESC',
        ];
        $sort = $sortMap[$filters['sort']] ?? $sort;
    }

    try {
        $countStmt = $db->prepare(
            "SELECT COUNT(*) FROM carry_trips ct
             LEFT JOIN carrier_profiles cp ON cp.user_id = ct.carrier_id
             WHERE $whereStr"
        );
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $dataStmt = $db->prepare(
            "SELECT ct.*,
                    u.first_name, u.last_name, u.avatar,
                    COALESCE(cp.verified, 0)       AS carrier_verified,
                    COALESCE(cp.trips_completed, 0) AS trips_completed,
                    COALESCE(cp.average_rating, 0)  AS carrier_rating
             FROM carry_trips ct
             LEFT JOIN users u ON u.id = ct.carrier_id
             LEFT JOIN carrier_profiles cp ON cp.user_id = ct.carrier_id
             WHERE $whereStr
             ORDER BY $sort
             LIMIT " . (int)$perPage . ' OFFSET ' . (int)$offset
        );
        $dataStmt->execute($params);
        $trips = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        return ['trips' => $trips, 'total' => $total, 'pages' => max(1, (int)ceil($total / $perPage))];
    } catch (PDOException $e) {
        error_log('getTrips error: ' . $e->getMessage());
        return ['trips' => [], 'total' => 0, 'pages' => 1];
    }
}

/**
 * Get a carrier's own trips.
 *
 * @param int   $carrierId Carrier user ID
 * @param array $filters   Optional: status
 * @param int   $page
 * @param int   $perPage
 * @return array ['trips'=>array, 'total'=>int, 'pages'=>int]
 */
function getCarrierTrips(int $carrierId, array $filters = [], int $page = 1, int $perPage = 20): array
{
    $db     = getDB();
    $where  = ['ct.carrier_id = ?'];
    $params = [$carrierId];

    if (!empty($filters['status'])) {
        $where[]  = 'ct.status = ?';
        $params[] = $filters['status'];
    }

    $whereStr = implode(' AND ', $where);
    $offset   = ($page - 1) * $perPage;

    try {
        $countStmt = $db->prepare("SELECT COUNT(*) FROM carry_trips ct WHERE $whereStr");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $dataStmt = $db->prepare(
            "SELECT ct.*,
                    (SELECT COUNT(*) FROM carry_requests cr WHERE cr.trip_id = ct.id) AS request_count
             FROM carry_trips ct
             WHERE $whereStr
             ORDER BY ct.departure_date ASC
             LIMIT " . (int)$perPage . ' OFFSET ' . (int)$offset
        );
        $dataStmt->execute($params);
        $trips = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        return ['trips' => $trips, 'total' => $total, 'pages' => max(1, (int)ceil($total / $perPage))];
    } catch (PDOException $e) {
        error_log('getCarrierTrips error: ' . $e->getMessage());
        return ['trips' => [], 'total' => 0, 'pages' => 1];
    }
}

/**
 * Search trips matching a shipment (origin, destination, date, weight).
 *
 * @param string $origin       City or country
 * @param string $destination  City or country
 * @param string $date         Departure date (Y-m-d)
 * @param float  $weightKg     Package weight
 * @return array Trip rows
 */
function searchTrips(string $origin, string $destination, string $date, float $weightKg): array
{
    $db = getDB();
    try {
        $stmt = $db->prepare(
            'SELECT ct.*,
                    u.first_name, u.last_name, u.avatar,
                    COALESCE(cp.verified, 0)       AS carrier_verified,
                    COALESCE(cp.average_rating, 0) AS carrier_rating
             FROM carry_trips ct
             LEFT JOIN users u ON u.id = ct.carrier_id
             LEFT JOIN carrier_profiles cp ON cp.user_id = ct.carrier_id
             WHERE ct.is_active = 1
               AND ct.status = "active"
               AND (ct.origin_city LIKE ? OR ct.origin_country LIKE ?)
               AND (ct.destination_city LIKE ? OR ct.destination_country LIKE ?)
               AND ct.departure_date >= ?
               AND ct.max_weight_kg >= ?
             ORDER BY ct.departure_date ASC
             LIMIT 50'
        );
        $originLike = '%' . $origin . '%';
        $destLike   = '%' . $destination . '%';
        $stmt->execute([$originLike, $originLike, $destLike, $destLike, $date, $weightKg]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('searchTrips error: ' . $e->getMessage());
        return [];
    }
}

// ── Carry Request (Matching) ──────────────────────────────────────────────────

/**
 * Buyer requests carry on a trip.
 *
 * @param int   $buyerId  Buyer user ID
 * @param int   $tripId   Trip ID
 * @param array $data     Request fields
 * @return array ['success'=>bool, 'request_id'=>int|null, 'error'=>string|null]
 */
function createCarryRequest(int $buyerId, int $tripId, array $data): array
{
    $db = getDB();

    // Validate trip
    $trip = getTrip($tripId);
    if (!$trip || $trip['status'] !== 'active' || !$trip['is_active']) {
        return ['success' => false, 'request_id' => null, 'error' => 'Trip not available'];
    }
    if ((int)$trip['carrier_id'] === $buyerId) {
        return ['success' => false, 'request_id' => null, 'error' => 'Cannot request carry on your own trip'];
    }

    $weightKg = (float)($data['weight_kg'] ?? 0);
    if ($weightKg <= 0) {
        return ['success' => false, 'request_id' => null, 'error' => 'weight_kg must be greater than 0'];
    }
    if ($weightKg > (float)$trip['max_weight_kg']) {
        return ['success' => false, 'request_id' => null, 'error' => 'Package weight exceeds trip capacity'];
    }
    if (empty($data['package_description'])) {
        return ['success' => false, 'request_id' => null, 'error' => 'package_description is required'];
    }

    $offeredPrice = (float)($data['offered_price'] ?? calculateCarryFee($tripId, $weightKg)['fee']);

    $pickupJson   = !empty($data['pickup_address'])   ? (is_array($data['pickup_address'])   ? json_encode($data['pickup_address'])   : $data['pickup_address'])   : null;
    $deliveryJson = !empty($data['delivery_address']) ? (is_array($data['delivery_address']) ? json_encode($data['delivery_address']) : $data['delivery_address']) : null;

    try {
        $stmt = $db->prepare(
            'INSERT INTO carry_requests
                (buyer_id, trip_id, order_id, package_description, weight_kg, dimensions,
                 pickup_address_json, delivery_address_json, offered_price,
                 special_instructions, status, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())'
        );
        $stmt->execute([
            $buyerId,
            $tripId,
            isset($data['order_id']) ? (int)$data['order_id'] : null,
            trim($data['package_description']),
            $weightKg,
            isset($data['dimensions']) ? trim($data['dimensions']) : null,
            $pickupJson,
            $deliveryJson,
            $offeredPrice,
            isset($data['special_instructions']) ? trim($data['special_instructions']) : null,
            'pending',
        ]);
        $requestId = (int)$db->lastInsertId();

        // Notify carrier
        _notifyCarryParticipant(
            (int)$trip['carrier_id'],
            'carry_request_received',
            'New Carry Request',
            "You have a new carry request for your {$trip['origin_city']} → {$trip['destination_city']} trip",
            ['request_id' => $requestId, 'trip_id' => $tripId],
            '/pages/carrier/requests/index.php'
        );

        return ['success' => true, 'request_id' => $requestId, 'error' => null];
    } catch (PDOException $e) {
        error_log('createCarryRequest error: ' . $e->getMessage());
        return ['success' => false, 'request_id' => null, 'error' => 'Database error'];
    }
}

/**
 * Get carry request details.
 *
 * @param int $requestId Request ID
 * @return array|null
 */
function getCarryRequest(int $requestId): ?array
{
    $db = getDB();
    try {
        $stmt = $db->prepare(
            'SELECT cr.*,
                    ct.origin_city, ct.origin_country, ct.destination_city, ct.destination_country,
                    ct.departure_date, ct.arrival_date, ct.carrier_id, ct.price_per_kg, ct.flat_rate,
                    bu.first_name AS buyer_first_name, bu.last_name AS buyer_last_name, bu.email AS buyer_email,
                    cu.first_name AS carrier_first_name, cu.last_name AS carrier_last_name
             FROM carry_requests cr
             JOIN carry_trips ct ON ct.id = cr.trip_id
             JOIN users bu ON bu.id = cr.buyer_id
             LEFT JOIN users cu ON cu.id = ct.carrier_id
             WHERE cr.id = ?
             LIMIT 1'
        );
        $stmt->execute([$requestId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        error_log('getCarryRequest error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get all carry requests for a trip (carrier view).
 *
 * @param int $tripId Trip ID
 * @return array
 */
function getRequestsForTrip(int $tripId): array
{
    $db = getDB();
    try {
        $stmt = $db->prepare(
            'SELECT cr.*,
                    u.first_name, u.last_name, u.email, u.avatar
             FROM carry_requests cr
             JOIN users u ON u.id = cr.buyer_id
             WHERE cr.trip_id = ?
             ORDER BY cr.created_at DESC'
        );
        $stmt->execute([$tripId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('getRequestsForTrip error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get a buyer's carry requests.
 *
 * @param int   $buyerId Buyer user ID
 * @param array $filters Optional: status
 * @param int   $page
 * @param int   $perPage
 * @return array ['requests'=>array, 'total'=>int, 'pages'=>int]
 */
function getBuyerRequests(int $buyerId, array $filters = [], int $page = 1, int $perPage = 20): array
{
    $db     = getDB();
    $where  = ['cr.buyer_id = ?'];
    $params = [$buyerId];

    if (!empty($filters['status'])) {
        $where[]  = 'cr.status = ?';
        $params[] = $filters['status'];
    }

    $whereStr = implode(' AND ', $where);
    $offset   = ($page - 1) * $perPage;

    try {
        $countStmt = $db->prepare("SELECT COUNT(*) FROM carry_requests cr WHERE $whereStr");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $dataStmt = $db->prepare(
            "SELECT cr.*,
                    ct.origin_city, ct.origin_country, ct.destination_city, ct.destination_country,
                    ct.departure_date, ct.carrier_id,
                    u.first_name AS carrier_first_name, u.last_name AS carrier_last_name, u.avatar AS carrier_avatar,
                    COALESCE(cp.verified, 0) AS carrier_verified
             FROM carry_requests cr
             JOIN carry_trips ct ON ct.id = cr.trip_id
             LEFT JOIN users u ON u.id = ct.carrier_id
             LEFT JOIN carrier_profiles cp ON cp.user_id = ct.carrier_id
             WHERE $whereStr
             ORDER BY cr.created_at DESC
             LIMIT " . (int)$perPage . ' OFFSET ' . (int)$offset
        );
        $dataStmt->execute($params);
        $requests = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        return ['requests' => $requests, 'total' => $total, 'pages' => max(1, (int)ceil($total / $perPage))];
    } catch (PDOException $e) {
        error_log('getBuyerRequests error: ' . $e->getMessage());
        return ['requests' => [], 'total' => 0, 'pages' => 1];
    }
}

/**
 * Carrier accepts a carry request.
 *
 * @param int $requestId  Request ID
 * @param int $carrierId  Carrier user ID
 * @return array ['success'=>bool, 'error'=>string|null]
 */
function acceptRequest(int $requestId, int $carrierId): array
{
    $db = getDB();
    $req = getCarryRequest($requestId);
    if (!$req) {
        return ['success' => false, 'error' => 'Request not found'];
    }
    if ((int)$req['carrier_id'] !== $carrierId) {
        return ['success' => false, 'error' => 'Access denied'];
    }
    if ($req['status'] !== 'pending') {
        return ['success' => false, 'error' => 'Request is not in pending status'];
    }

    try {
        $db->prepare('UPDATE carry_requests SET status = ?, carrier_fee = ?, updated_at = NOW() WHERE id = ?')
           ->execute(['accepted', $req['offered_price'], $requestId]);

        _logCarryStatus($requestId, 'accepted', $carrierId, 'Request accepted by carrier');

        // Notify buyer
        _notifyCarryParticipant(
            (int)$req['buyer_id'],
            'carry_request_accepted',
            'Carry Request Accepted!',
            "Your carry request has been accepted by {$req['carrier_first_name']}",
            ['request_id' => $requestId],
            '/pages/carry/my-requests.php'
        );

        return ['success' => true, 'error' => null];
    } catch (PDOException $e) {
        error_log('acceptRequest error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Database error'];
    }
}

/**
 * Carrier declines a carry request.
 *
 * @param int    $requestId Request ID
 * @param int    $carrierId Carrier user ID
 * @param string $reason    Decline reason
 * @return array ['success'=>bool, 'error'=>string|null]
 */
function declineRequest(int $requestId, int $carrierId, string $reason = ''): array
{
    $db  = getDB();
    $req = getCarryRequest($requestId);
    if (!$req) {
        return ['success' => false, 'error' => 'Request not found'];
    }
    if ((int)$req['carrier_id'] !== $carrierId) {
        return ['success' => false, 'error' => 'Access denied'];
    }
    if ($req['status'] !== 'pending') {
        return ['success' => false, 'error' => 'Request is not in pending status'];
    }

    try {
        $db->prepare('UPDATE carry_requests SET status = ?, decline_reason = ?, updated_at = NOW() WHERE id = ?')
           ->execute(['declined', trim($reason), $requestId]);

        _logCarryStatus($requestId, 'declined', $carrierId, $reason);

        // Notify buyer
        _notifyCarryParticipant(
            (int)$req['buyer_id'],
            'carry_request_declined',
            'Carry Request Declined',
            "Your carry request was declined" . ($reason ? ": $reason" : ''),
            ['request_id' => $requestId],
            '/pages/carry/my-requests.php'
        );

        return ['success' => true, 'error' => null];
    } catch (PDOException $e) {
        error_log('declineRequest error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Database error'];
    }
}

/**
 * Cancel a carry request (by buyer or carrier).
 *
 * @param int    $requestId Request ID
 * @param int    $userId    User cancelling (buyer or carrier)
 * @param string $reason    Cancel reason
 * @return array ['success'=>bool, 'error'=>string|null]
 */
function cancelRequest(int $requestId, int $userId, string $reason = ''): array
{
    $db  = getDB();
    $req = getCarryRequest($requestId);
    if (!$req) {
        return ['success' => false, 'error' => 'Request not found'];
    }

    $isBuyer   = (int)$req['buyer_id'] === $userId;
    $isCarrier = (int)$req['carrier_id'] === $userId;

    if (!$isBuyer && !$isCarrier) {
        return ['success' => false, 'error' => 'Access denied'];
    }
    if (!in_array($req['status'], ['pending', 'accepted'], true)) {
        return ['success' => false, 'error' => 'Request cannot be cancelled at this stage'];
    }

    try {
        $db->prepare('UPDATE carry_requests SET status = ?, cancel_reason = ?, updated_at = NOW() WHERE id = ?')
           ->execute(['cancelled', trim($reason), $requestId]);

        _logCarryStatus($requestId, 'cancelled', $userId, $reason);

        // Notify the other party
        $notifyUserId = $isBuyer ? (int)$req['carrier_id'] : (int)$req['buyer_id'];
        _notifyCarryParticipant(
            $notifyUserId,
            'carry_request_cancelled',
            'Carry Request Cancelled',
            'A carry request has been cancelled' . ($reason ? ": $reason" : ''),
            ['request_id' => $requestId],
            ''
        );

        return ['success' => true, 'error' => null];
    } catch (PDOException $e) {
        error_log('cancelRequest error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Database error'];
    }
}

// ── Carry Status Flow ─────────────────────────────────────────────────────────

/**
 * Update carry request status with validation.
 *
 * @param int    $requestId Request ID
 * @param string $status    New status
 * @param int    $userId    User performing the update
 * @param string $note      Optional note
 * @return array ['success'=>bool, 'error'=>string|null]
 */
function updateCarryStatus(int $requestId, string $status, int $userId, string $note = ''): array
{
    $db  = getDB();
    $req = getCarryRequest($requestId);
    if (!$req) {
        return ['success' => false, 'error' => 'Request not found'];
    }

    $isBuyer   = (int)$req['buyer_id'] === $userId;
    $isCarrier = (int)$req['carrier_id'] === $userId;

    if (!$isBuyer && !$isCarrier) {
        return ['success' => false, 'error' => 'Access denied'];
    }

    $allowedNext = CARRY_STATUS_TRANSITIONS[$req['status']] ?? [];
    if (!in_array($status, $allowedNext, true)) {
        return ['success' => false, 'error' => "Cannot transition from '{$req['status']}' to '$status'"];
    }

    $extraSets = [];
    $extraParams = [];
    if ($status === 'picked_up') {
        $extraSets[]   = 'picked_up_at = NOW()';
    }
    if ($status === 'delivered') {
        $extraSets[]   = 'delivered_at = NOW()';
    }

    $setSql = 'status = ?, updated_at = NOW()' . ($extraSets ? ', ' . implode(', ', $extraSets) : '');
    $params = array_merge([$status], $extraParams, [$requestId]);

    try {
        $db->prepare("UPDATE carry_requests SET $setSql WHERE id = ?")->execute($params);
        _logCarryStatus($requestId, $status, $userId, $note);

        // Notifications
        $route = "{$req['origin_city']} → {$req['destination_city']}";
        if ($status === 'picked_up') {
            _notifyCarryParticipant(
                (int)$req['buyer_id'],
                'carry_picked_up',
                'Package Picked Up',
                "Your package has been picked up by {$req['carrier_first_name']}",
                ['request_id' => $requestId],
                '/pages/carry/my-requests.php'
            );
        } elseif ($status === 'delivered') {
            foreach ([(int)$req['buyer_id'], (int)$req['carrier_id']] as $uid) {
                _notifyCarryParticipant(
                    $uid,
                    'carry_delivered',
                    'Package Delivered',
                    "Package delivered successfully on the $route trip",
                    ['request_id' => $requestId],
                    '/pages/carry/my-requests.php'
                );
            }
        }

        return ['success' => true, 'error' => null];
    } catch (PDOException $e) {
        error_log('updateCarryStatus error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Database error'];
    }
}

// ── Carry Payments ────────────────────────────────────────────────────────────

/**
 * Calculate carry fee for a trip and weight.
 *
 * @param int   $tripId   Trip ID
 * @param float $weightKg Package weight in kg
 * @return array ['fee'=>float, 'method'=>string, 'error'=>string|null]
 */
function calculateCarryFee(int $tripId, float $weightKg): array
{
    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT price_per_kg, flat_rate FROM carry_trips WHERE id = ? LIMIT 1');
        $stmt->execute([$tripId]);
        $trip = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return ['fee' => 0.0, 'method' => 'none', 'error' => 'Database error'];
    }

    if (!$trip) {
        return ['fee' => 0.0, 'method' => 'none', 'error' => 'Trip not found'];
    }

    if ($trip['flat_rate'] !== null && (float)$trip['flat_rate'] > 0) {
        return ['fee' => (float)$trip['flat_rate'], 'method' => 'flat_rate', 'error' => null];
    }

    $fee = round((float)$trip['price_per_kg'] * $weightKg, 2);
    return ['fee' => $fee, 'method' => 'per_kg', 'error' => null];
}

/**
 * Release carry payment to carrier after delivery is confirmed.
 *
 * @param int $requestId Request ID
 * @return array ['success'=>bool, 'error'=>string|null]
 */
function releaseCarryPayment(int $requestId): array
{
    $db  = getDB();
    $req = getCarryRequest($requestId);
    if (!$req) {
        return ['success' => false, 'error' => 'Request not found'];
    }
    if (!in_array($req['status'], ['delivered', 'completed'], true)) {
        return ['success' => false, 'error' => 'Payment can only be released after delivery'];
    }
    if ($req['payment_released']) {
        return ['success' => false, 'error' => 'Payment already released'];
    }

    try {
        $db->prepare('UPDATE carry_requests SET payment_released = 1, updated_at = NOW() WHERE id = ?')
           ->execute([$requestId]);

        // Update carrier earnings
        $amount = (float)($req['carrier_fee'] ?? $req['offered_price']);
        $db->prepare(
            'UPDATE carrier_profiles SET total_earnings = total_earnings + ?, trips_completed = trips_completed + 1, updated_at = NOW() WHERE user_id = ?'
        )->execute([$amount, (int)$req['carrier_id']]);

        return ['success' => true, 'error' => null];
    } catch (PDOException $e) {
        error_log('releaseCarryPayment error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Database error'];
    }
}

/**
 * Refund buyer if carrier fails delivery.
 *
 * @param int $requestId Request ID
 * @return array ['success'=>bool, 'error'=>string|null]
 */
function refundCarryPayment(int $requestId): array
{
    $db  = getDB();
    $req = getCarryRequest($requestId);
    if (!$req) {
        return ['success' => false, 'error' => 'Request not found'];
    }
    if (!$req['escrow_paid']) {
        return ['success' => false, 'error' => 'No escrow payment to refund'];
    }
    if ($req['payment_released']) {
        return ['success' => false, 'error' => 'Payment already released — cannot refund'];
    }

    try {
        $db->prepare('UPDATE carry_requests SET escrow_paid = 0, status = ?, updated_at = NOW() WHERE id = ?')
           ->execute(['cancelled', $requestId]);

        _notifyCarryParticipant(
            (int)$req['buyer_id'],
            'carry_refunded',
            'Refund Processed',
            'Your carry payment has been refunded',
            ['request_id' => $requestId],
            '/pages/carry/my-requests.php'
        );

        return ['success' => true, 'error' => null];
    } catch (PDOException $e) {
        error_log('refundCarryPayment error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Database error'];
    }
}

// ── Carrier Profile ───────────────────────────────────────────────────────────

/**
 * Get carrier profile with stats.
 *
 * @param int $carrierId Carrier user ID
 * @return array|null
 */
function getCarrierProfile(int $carrierId): ?array
{
    $db = getDB();
    try {
        $stmt = $db->prepare(
            'SELECT u.id, u.first_name, u.last_name, u.email, u.avatar, u.created_at AS member_since,
                    COALESCE(cp.verified, 0)        AS verified,
                    COALESCE(cp.id_document, "")    AS id_document,
                    COALESCE(cp.phone_verified, 0)  AS phone_verified,
                    COALESCE(cp.trips_completed, 0) AS trips_completed,
                    COALESCE(cp.total_earnings, 0)  AS total_earnings,
                    COALESCE(cp.average_rating, 0)  AS average_rating,
                    COALESCE(cp.bio, "")            AS bio
             FROM users u
             LEFT JOIN carrier_profiles cp ON cp.user_id = u.id
             WHERE u.id = ?
             LIMIT 1'
        );
        $stmt->execute([$carrierId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        error_log('getCarrierProfile error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Update carrier profile.
 *
 * @param int   $carrierId Carrier user ID
 * @param array $data      Fields to update (bio, etc.)
 * @return array ['success'=>bool, 'error'=>string|null]
 */
function updateCarrierProfile(int $carrierId, array $data): array
{
    $db = getDB();
    try {
        // Upsert carrier_profiles row
        $stmt = $db->prepare('SELECT id FROM carrier_profiles WHERE user_id = ? LIMIT 1');
        $stmt->execute([$carrierId]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            $allowed = ['bio', 'id_document', 'phone_verified'];
            $sets    = [];
            $params  = [];
            foreach ($allowed as $col) {
                if (array_key_exists($col, $data)) {
                    $sets[]   = "`$col` = ?";
                    $params[] = $data[$col];
                }
            }
            if ($sets) {
                $params[] = $carrierId;
                $db->prepare('UPDATE carrier_profiles SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE user_id = ?')
                   ->execute($params);
            }
        } else {
            $db->prepare(
                'INSERT INTO carrier_profiles (user_id, bio, id_document, phone_verified, created_at, updated_at) VALUES (?,?,?,?,NOW(),NOW())'
            )->execute([
                $carrierId,
                $data['bio'] ?? '',
                $data['id_document'] ?? null,
                isset($data['phone_verified']) ? (int)$data['phone_verified'] : 0,
            ]);
        }

        return ['success' => true, 'error' => null];
    } catch (PDOException $e) {
        error_log('updateCarrierProfile error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Database error'];
    }
}

/**
 * Get carrier statistics.
 *
 * @param int $carrierId Carrier user ID
 * @return array ['trips_completed'=>int, 'packages_delivered'=>int, 'rating'=>float, 'total_earnings'=>float]
 */
function getCarrierStats(int $carrierId): array
{
    $db = getDB();
    $stats = ['trips_completed' => 0, 'packages_delivered' => 0, 'rating' => 0.0, 'total_earnings' => 0.0];

    try {
        $stmt = $db->prepare('SELECT trips_completed, total_earnings, average_rating FROM carrier_profiles WHERE user_id = ? LIMIT 1');
        $stmt->execute([$carrierId]);
        $cp = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($cp) {
            $stats['trips_completed'] = (int)$cp['trips_completed'];
            $stats['total_earnings']  = (float)$cp['total_earnings'];
            $stats['rating']          = (float)$cp['average_rating'];
        }

        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM carry_requests cr
             JOIN carry_trips ct ON ct.id = cr.trip_id
             WHERE ct.carrier_id = ? AND cr.status IN ("delivered","completed")'
        );
        $stmt->execute([$carrierId]);
        $stats['packages_delivered'] = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('getCarrierStats error: ' . $e->getMessage());
    }

    return $stats;
}

/**
 * Get carrier average rating.
 *
 * @param int $carrierId Carrier user ID
 * @return float
 */
function getCarrierRating(int $carrierId): float
{
    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT COALESCE(AVG(rating), 0) FROM carry_ratings WHERE carrier_id = ?');
        $stmt->execute([$carrierId]);
        return round((float)$stmt->fetchColumn(), 2);
    } catch (PDOException $e) {
        error_log('getCarrierRating error: ' . $e->getMessage());
        return 0.0;
    }
}

/**
 * Buyer rates a carrier after delivery.
 *
 * @param int    $buyerId    Buyer user ID
 * @param int    $requestId  Carry request ID
 * @param int    $rating     1–5
 * @param string $review     Optional review text
 * @return array ['success'=>bool, 'error'=>string|null]
 */
function rateCarrier(int $buyerId, int $requestId, int $rating, string $review = ''): array
{
    $db  = getDB();
    $req = getCarryRequest($requestId);
    if (!$req) {
        return ['success' => false, 'error' => 'Request not found'];
    }
    if ((int)$req['buyer_id'] !== $buyerId) {
        return ['success' => false, 'error' => 'Access denied'];
    }
    if (!in_array($req['status'], ['delivered', 'completed'], true)) {
        return ['success' => false, 'error' => 'Can only rate after delivery'];
    }
    $rating = max(1, min(5, $rating));

    try {
        // Insert or update rating
        $stmt = $db->prepare(
            'INSERT INTO carry_ratings (request_id, buyer_id, carrier_id, rating, review, created_at)
             VALUES (?,?,?,?,?,NOW())
             ON DUPLICATE KEY UPDATE rating = VALUES(rating), review = VALUES(review)'
        );
        $stmt->execute([$requestId, $buyerId, (int)$req['carrier_id'], $rating, trim($review)]);

        // Recalculate average rating
        $avgRating = getCarrierRating((int)$req['carrier_id']);
        $db->prepare('UPDATE carrier_profiles SET average_rating = ?, updated_at = NOW() WHERE user_id = ?')
           ->execute([$avgRating, (int)$req['carrier_id']]);

        return ['success' => true, 'error' => null];
    } catch (PDOException $e) {
        error_log('rateCarrier error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Database error'];
    }
}

// ── Verification ──────────────────────────────────────────────────────────────

/**
 * Check if a carrier is verified (KYC/ID + phone).
 *
 * @param int $carrierId Carrier user ID
 * @return bool
 */
function isCarrierVerified(int $carrierId): bool
{
    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT verified FROM carrier_profiles WHERE user_id = ? LIMIT 1');
        $stmt->execute([$carrierId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row && (bool)$row['verified'];
    } catch (PDOException $e) {
        error_log('isCarrierVerified error: ' . $e->getMessage());
        return false;
    }
}

// ── Internal Helpers ──────────────────────────────────────────────────────────

/**
 * Log a carry status change.
 */
function _logCarryStatus(int $requestId, string $status, int $userId, string $note = ''): void
{
    $db = getDB();
    try {
        $db->prepare(
            'INSERT INTO carry_status_log (request_id, status, changed_by, note, created_at) VALUES (?,?,?,?,NOW())'
        )->execute([$requestId, $status, $userId, $note]);
    } catch (PDOException $e) {
        error_log('_logCarryStatus error: ' . $e->getMessage());
    }
}

/**
 * Send a notification to a carry participant.
 */
function _notifyCarryParticipant(int $userId, string $type, string $title, string $message, array $data, string $actionUrl): void
{
    if ($userId <= 0) {
        return;
    }
    $db = getDB();
    if (!function_exists('createNotification')) {
        return;
    }
    try {
        createNotification($db, $userId, $type, $title, $message, $data, 'normal', $actionUrl);
    } catch (Exception $e) {
        error_log('_notifyCarryParticipant error: ' . $e->getMessage());
    }
}
