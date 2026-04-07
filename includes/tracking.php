<?php
/**
 * includes/tracking.php — Parcel Tracking Library (PR #15)
 *
 * Carrier Integration (cURL, no Composer):
 *   DHL, FedEx, UPS, USPS, Royal Mail, China Post, Bangladesh Post, Aramex, Generic
 *
 * Public API:
 *   getCarriers()                                         — list supported carriers
 *   getCarrierTrackingUrl($carrier, $trackingNumber)      — generate direct tracking URL
 *   trackShipment($carrier, $trackingNumber)              — poll carrier API for status
 *   createShipment($orderId, $supplierId, $data)          — create shipment record
 *   updateShipmentStatus($shipmentId, $status, $loc, $d)  — add tracking event
 *   getShipment($shipmentId)                              — get shipment + events
 *   getOrderShipments($orderId)                           — all shipments for an order
 *   getSupplierShipments($supplierId, $filters, $page, $perPage)
 *   getBuyerShipments($buyerId, $filters, $page, $perPage)
 *   getStatusLabel($status)                               — human-readable label
 *   getStatusColor($status)                               — Bootstrap badge color
 *   refreshTrackingStatus($shipmentId)                    — poll carrier + save update
 *   refreshAllActiveShipments()                           — cron: refresh all in-transit
 *   notifyTrackingUpdate($shipmentId, $event)             — notify buyer of update
 */

// ── Status constants ──────────────────────────────────────────────────────────

define('TRACKING_STATUS_LABEL_CREATED',     'label_created');
define('TRACKING_STATUS_PICKED_UP',         'picked_up');
define('TRACKING_STATUS_IN_TRANSIT',        'in_transit');
define('TRACKING_STATUS_OUT_FOR_DELIVERY',  'out_for_delivery');
define('TRACKING_STATUS_DELIVERED',         'delivered');
define('TRACKING_STATUS_EXCEPTION',         'exception');
define('TRACKING_STATUS_RETURNED',          'returned');
define('TRACKING_STATUS_UNKNOWN',           'unknown');

// ── Carrier Helpers ───────────────────────────────────────────────────────────

/**
 * Return all supported carriers from the DB (with in-memory fallback).
 *
 * @return array[]
 */
function getCarriers(): array
{
    try {
        $db   = getDB();
        $stmt = $db->query(
            'SELECT code, name, logo_url, tracking_url_template, api_endpoint, api_key_setting, is_active, sort_order
             FROM carriers WHERE is_active = 1 ORDER BY sort_order ASC'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            return $rows;
        }
    } catch (PDOException $e) {
        // fall through to static list
    }

    // Static fallback (table not yet seeded)
    return [
        ['code' => 'dhl',            'name' => 'DHL',             'logo_url' => '/assets/carriers/dhl.png',           'tracking_url_template' => 'https://www.dhl.com/en/express/tracking.html?AWB={tracking_number}'],
        ['code' => 'fedex',          'name' => 'FedEx',           'logo_url' => '/assets/carriers/fedex.png',         'tracking_url_template' => 'https://www.fedex.com/fedextrack/?trknbr={tracking_number}'],
        ['code' => 'ups',            'name' => 'UPS',             'logo_url' => '/assets/carriers/ups.png',           'tracking_url_template' => 'https://www.ups.com/track?tracknum={tracking_number}'],
        ['code' => 'usps',           'name' => 'USPS',            'logo_url' => '/assets/carriers/usps.png',          'tracking_url_template' => 'https://tools.usps.com/go/TrackConfirmAction?tLabels={tracking_number}'],
        ['code' => 'royal_mail',     'name' => 'Royal Mail',      'logo_url' => '/assets/carriers/royal_mail.png',    'tracking_url_template' => 'https://www3.royalmail.com/track-your-item#/tracking-results/{tracking_number}'],
        ['code' => 'china_post',     'name' => 'China Post',      'logo_url' => '/assets/carriers/china_post.png',    'tracking_url_template' => 'https://yjcx.ems.com.cn/qps/english/yjcx?mailNo={tracking_number}'],
        ['code' => 'bangladesh_post','name' => 'Bangladesh Post', 'logo_url' => '/assets/carriers/bd_post.png',       'tracking_url_template' => 'https://www.bangladeshpost.gov.bd/posts/tracking.asp?TrackNumber={tracking_number}'],
        ['code' => 'aramex',         'name' => 'Aramex',          'logo_url' => '/assets/carriers/aramex.png',        'tracking_url_template' => 'https://www.aramex.com/us/en/track/results?mode=0&ShipmentNumber={tracking_number}'],
        ['code' => 'generic',        'name' => 'Other Carrier',   'logo_url' => '/assets/carriers/generic.png',       'tracking_url_template' => '{tracking_url}'],
    ];
}

/**
 * Generate a direct tracking URL for a carrier and tracking number.
 *
 * @param  string $carrier        Carrier code (e.g. 'dhl')
 * @param  string $trackingNumber Shipment tracking number
 * @param  string $customUrl      For generic carrier: custom URL override
 * @return string
 */
function getCarrierTrackingUrl(string $carrier, string $trackingNumber, string $customUrl = ''): string
{
    $carriers = getCarriers();
    $template = '';
    foreach ($carriers as $c) {
        if ($c['code'] === $carrier) {
            $template = $c['tracking_url_template'] ?? '';
            break;
        }
    }

    if ($template === '') {
        return '';
    }

    $trackingNumber = rawurlencode($trackingNumber);
    $url = str_replace('{tracking_number}', $trackingNumber, $template);
    $url = str_replace('{tracking_url}', $customUrl ?: '', $url);
    return $url;
}

// ── Carrier API Polling ───────────────────────────────────────────────────────

/**
 * Poll a carrier's API for tracking status.
 *
 * Returns a normalized result:
 *   ['success' => bool, 'status' => string, 'location' => string, 'timestamp' => string,
 *    'events'  => [['date', 'status', 'location', 'description'], ...]]
 *
 * If no API key is configured or the carrier doesn't support API tracking,
 * returns a graceful "no data" response.
 *
 * @param  string $carrier        Carrier code
 * @param  string $trackingNumber Tracking number
 * @return array
 */
function trackShipment(string $carrier, string $trackingNumber): array
{
    $trackingNumber = trim($trackingNumber);
    if ($trackingNumber === '') {
        return _trackingError('Tracking number is required');
    }

    switch ($carrier) {
        case 'dhl':
            return _trackDhl($trackingNumber);
        case 'ups':
            return _trackUps($trackingNumber);
        case 'fedex':
            return _trackFedex($trackingNumber);
        default:
            // For carriers without a direct API integration, return a placeholder
            return _trackGenericFallback($carrier, $trackingNumber);
    }
}

/** @internal */
function _trackingError(string $message): array
{
    return ['success' => false, 'status' => 'unknown', 'location' => '', 'timestamp' => '', 'events' => [], 'message' => $message];
}

/** @internal */
function _trackingSuccess(string $status, string $location, string $timestamp, array $events): array
{
    return ['success' => true, 'status' => $status, 'location' => $location, 'timestamp' => $timestamp, 'events' => $events];
}

/** @internal */
function _carrierApiKey(string $settingKey): string
{
    try {
        $db   = getDB();
        $stmt = $db->prepare('SELECT value FROM settings WHERE `key` = ? LIMIT 1');
        $stmt->execute([$settingKey]);
        return (string)($stmt->fetchColumn() ?: '');
    } catch (PDOException $e) {
        return '';
    }
}

/** @internal cURL GET/POST helper */
function _trackingCurl(string $url, array $headers = [], string $postBody = '', string $method = 'GET'): array
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postBody);
    }
    $body  = curl_exec($ch);
    $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    return ['body' => (string)$body, 'code' => $code, 'error' => $error];
}

/** @internal DHL Unified Tracking API v2 */
function _trackDhl(string $trackingNumber): array
{
    $apiKey = _carrierApiKey('carrier_api_dhl');
    if ($apiKey === '') {
        return _trackGenericFallback('dhl', $trackingNumber);
    }

    $url      = 'https://api-eu.dhl.com/track/shipments?trackingNumber=' . rawurlencode($trackingNumber);
    $response = _trackingCurl($url, ['DHL-API-Key: ' . $apiKey]);

    if ($response['code'] !== 200 || $response['body'] === '') {
        return _trackingError('DHL API unavailable');
    }

    $data = json_decode($response['body'], true);
    if (!isset($data['shipments'][0])) {
        return _trackingError('No DHL shipment found');
    }

    $s      = $data['shipments'][0];
    $status = _normaliseDhlStatus($s['status']['statusCode'] ?? 'unknown');
    $loc    = $s['status']['location']['address']['addressLocality'] ?? '';
    $ts     = $s['status']['timestamp'] ?? '';
    $events = [];

    foreach ($s['events'] ?? [] as $ev) {
        $events[] = [
            'date'        => $ev['timestamp'] ?? '',
            'status'      => _normaliseDhlStatus($ev['statusCode'] ?? 'unknown'),
            'location'    => $ev['location']['address']['addressLocality'] ?? '',
            'description' => $ev['description'] ?? '',
        ];
    }

    return _trackingSuccess($status, $loc, $ts, $events);
}

/** @internal */
function _normaliseDhlStatus(string $code): string
{
    return match (strtolower($code)) {
        'pre-transit'    => TRACKING_STATUS_LABEL_CREATED,
        'transit'        => TRACKING_STATUS_IN_TRANSIT,
        'delivered'      => TRACKING_STATUS_DELIVERED,
        'failure'        => TRACKING_STATUS_EXCEPTION,
        'unknown'        => TRACKING_STATUS_UNKNOWN,
        default          => TRACKING_STATUS_IN_TRANSIT,
    };
}

/** @internal UPS Tracking API */
function _trackUps(string $trackingNumber): array
{
    $apiKey = _carrierApiKey('carrier_api_ups');
    if ($apiKey === '') {
        return _trackGenericFallback('ups', $trackingNumber);
    }

    $url      = 'https://onlinetools.ups.com/api/track/v1/details/' . rawurlencode($trackingNumber);
    $response = _trackingCurl($url, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ]);

    if ($response['code'] !== 200 || $response['body'] === '') {
        return _trackingError('UPS API unavailable');
    }

    $data = json_decode($response['body'], true);
    $pkg  = $data['trackResponse']['shipment'][0]['package'][0] ?? null;
    if (!$pkg) {
        return _trackingError('No UPS shipment found');
    }

    $actArr  = $pkg['activity'] ?? [];
    $latest  = $actArr[0] ?? [];
    $status  = _normaliseUpsStatus($latest['status']['type'] ?? 'X');
    $loc     = ($latest['location']['address']['city'] ?? '') . ', ' . ($latest['location']['address']['country'] ?? '');
    $ts      = ($latest['date'] ?? '') . ' ' . ($latest['time'] ?? '');
    $events  = [];

    foreach ($actArr as $act) {
        $events[] = [
            'date'        => ($act['date'] ?? '') . ' ' . ($act['time'] ?? ''),
            'status'      => _normaliseUpsStatus($act['status']['type'] ?? 'X'),
            'location'    => ($act['location']['address']['city'] ?? '') . ', ' . ($act['location']['address']['country'] ?? ''),
            'description' => $act['status']['description'] ?? '',
        ];
    }

    return _trackingSuccess($status, $loc, $ts, $events);
}

/** @internal */
function _normaliseUpsStatus(string $type): string
{
    return match (strtoupper($type)) {
        'P'  => TRACKING_STATUS_PICKED_UP,
        'I'  => TRACKING_STATUS_IN_TRANSIT,
        'O'  => TRACKING_STATUS_OUT_FOR_DELIVERY,
        'D'  => TRACKING_STATUS_DELIVERED,
        'X'  => TRACKING_STATUS_EXCEPTION,
        default => TRACKING_STATUS_IN_TRANSIT,
    };
}

/** @internal FedEx Track API v1 */
function _trackFedex(string $trackingNumber): array
{
    $apiKey = _carrierApiKey('carrier_api_fedex');
    if ($apiKey === '') {
        return _trackGenericFallback('fedex', $trackingNumber);
    }

    $payload  = json_encode([
        'trackingInfo'       => [['trackingNumberInfo' => ['trackingNumber' => $trackingNumber]]],
        'includeDetailedScans' => true,
    ]);
    $response = _trackingCurl(
        'https://apis.fedex.com/track/v1/trackingnumbers',
        ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json', 'X-locale: en_US'],
        $payload,
        'POST'
    );

    if ($response['code'] !== 200 || $response['body'] === '') {
        return _trackingError('FedEx API unavailable');
    }

    $data   = json_decode($response['body'], true);
    $result = $data['output']['completeTrackResults'][0]['trackResults'][0] ?? null;
    if (!$result) {
        return _trackingError('No FedEx shipment found');
    }

    $latestStatus = $result['latestStatusDetail'] ?? [];
    $status  = _normaliseFedexStatus($latestStatus['code'] ?? 'UN');
    $loc     = ($latestStatus['scanLocation']['city'] ?? '') . ', ' . ($latestStatus['scanLocation']['countryCode'] ?? '');
    $ts      = $result['dateAndTimes'][0]['dateTime'] ?? '';
    $events  = [];

    foreach ($result['scanEvents'] ?? [] as $ev) {
        $events[] = [
            'date'        => $ev['date'] ?? '',
            'status'      => _normaliseFedexStatus($ev['eventType'] ?? 'OC'),
            'location'    => ($ev['scanLocation']['city'] ?? '') . ', ' . ($ev['scanLocation']['countryCode'] ?? ''),
            'description' => $ev['eventDescription'] ?? '',
        ];
    }

    return _trackingSuccess($status, $loc, $ts, $events);
}

/** @internal */
function _normaliseFedexStatus(string $code): string
{
    return match (strtoupper($code)) {
        'PU'  => TRACKING_STATUS_PICKED_UP,
        'IT'  => TRACKING_STATUS_IN_TRANSIT,
        'OD'  => TRACKING_STATUS_OUT_FOR_DELIVERY,
        'DL'  => TRACKING_STATUS_DELIVERED,
        'DE'  => TRACKING_STATUS_EXCEPTION,
        default => TRACKING_STATUS_IN_TRANSIT,
    };
}

/** @internal Carriers without API integration — return tracking URL only */
function _trackGenericFallback(string $carrier, string $trackingNumber): array
{
    return [
        'success'      => true,
        'status'       => TRACKING_STATUS_UNKNOWN,
        'location'     => '',
        'timestamp'    => '',
        'events'       => [],
        'tracking_url' => getCarrierTrackingUrl($carrier, $trackingNumber),
        'message'      => 'Live API tracking not available for this carrier. Please use the carrier website.',
    ];
}

// ── Internal Tracking CRUD ────────────────────────────────────────────────────

/**
 * Create a new shipment record for an order.
 *
 * @param  int   $orderId
 * @param  int   $supplierId
 * @param  array $data  Keys: carrier_code, tracking_number, tracking_url, shipped_date,
 *                             estimated_delivery, weight_kg, package_dimensions
 * @return int   New shipment ID, or 0 on failure
 */
function createShipment(int $orderId, int $supplierId, array $data): int
{
    $db = getDB();

    $carrierCode  = trim($data['carrier_code']  ?? 'generic');
    $carrierName  = _resolveCarrierName($carrierCode);
    $tracking     = trim($data['tracking_number'] ?? '');
    $trackingUrl  = trim($data['tracking_url']  ?? '');
    $shippedDate  = !empty($data['shipped_date'])        ? $data['shipped_date']        : date('Y-m-d H:i:s');
    $estDelivery  = !empty($data['estimated_delivery'])   ? $data['estimated_delivery']  : null;
    $weightKg     = isset($data['weight_kg'])             ? (float)$data['weight_kg']    : null;
    $dims         = trim($data['package_dimensions'] ?? '');

    // Auto-generate tracking URL if not provided
    if ($trackingUrl === '' && $tracking !== '') {
        $trackingUrl = getCarrierTrackingUrl($carrierCode, $tracking);
    }

    try {
        $stmt = $db->prepare(
            'INSERT INTO shipments
                (order_id, supplier_id, carrier_code, carrier_name, tracking_number, tracking_url,
                 status, shipped_date, estimated_delivery, weight_kg, package_dimensions)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $orderId, $supplierId, $carrierCode, $carrierName, $tracking, $trackingUrl,
            TRACKING_STATUS_LABEL_CREATED, $shippedDate, $estDelivery, $weightKg, $dims,
        ]);
        $shipmentId = (int)$db->lastInsertId();
    } catch (PDOException $e) {
        error_log('createShipment error: ' . $e->getMessage());
        return 0;
    }

    // Initial event
    if ($shipmentId) {
        updateShipmentStatus($shipmentId, TRACKING_STATUS_LABEL_CREATED, '', 'Shipping label created');

        // Update order status to 'shipped'
        try {
            $db->prepare('UPDATE orders SET status = "shipped", updated_at = NOW() WHERE id = ?')
               ->execute([$orderId]);
        } catch (PDOException $e) {
            // orders table may use different column — non-fatal
        }

        // Notify buyer
        _notifyBuyerShipped($orderId, $shipmentId, $tracking, $carrierName);
    }

    return $shipmentId;
}

/** @internal */
function _resolveCarrierName(string $code): string
{
    $carriers = getCarriers();
    foreach ($carriers as $c) {
        if ($c['code'] === $code) {
            return $c['name'];
        }
    }
    return ucwords(str_replace('_', ' ', $code));
}

/**
 * Add a tracking event and update the shipment status.
 *
 * @param  int    $shipmentId
 * @param  string $status    One of the TRACKING_STATUS_* constants
 * @param  string $location  e.g. "Dhaka, Bangladesh"
 * @param  string $details   Human-readable description
 * @param  string $eventDate ISO datetime (defaults to now)
 * @param  string $rawJson   Optional raw API response JSON
 * @return int    Event ID or 0 on failure
 */
function updateShipmentStatus(
    int    $shipmentId,
    string $status,
    string $location = '',
    string $details  = '',
    string $eventDate = '',
    string $rawJson  = ''
): int {
    $db   = getDB();
    $date = $eventDate ?: date('Y-m-d H:i:s');

    try {
        // Insert event
        $stmt = $db->prepare(
            'INSERT INTO shipment_events (shipment_id, status, location, description, event_date, raw_data_json)
             VALUES (?,?,?,?,?,?)'
        );
        $stmt->execute([$shipmentId, $status, $location, $details, $date, $rawJson ?: null]);
        $eventId = (int)$db->lastInsertId();

        // Update shipment status (and actual_delivery if delivered)
        $actualDelivery = ($status === TRACKING_STATUS_DELIVERED) ? $date : null;
        if ($actualDelivery) {
            $db->prepare('UPDATE shipments SET status=?, updated_at=NOW(), actual_delivery=? WHERE id=?')
               ->execute([$status, $actualDelivery, $shipmentId]);
        } else {
            $db->prepare('UPDATE shipments SET status=?, updated_at=NOW() WHERE id=?')
               ->execute([$status, $shipmentId]);
        }

        return $eventId;
    } catch (PDOException $e) {
        error_log('updateShipmentStatus error: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Get a shipment with all its events.
 *
 * @param  int $shipmentId
 * @return array|null
 */
function getShipment(int $shipmentId): ?array
{
    $db = getDB();
    try {
        $stmt = $db->prepare(
            'SELECT s.*, o.order_number, o.buyer_id,
                    u.first_name AS buyer_first, u.last_name AS buyer_last
             FROM shipments s
             LEFT JOIN orders  o ON o.id = s.order_id
             LEFT JOIN users   u ON u.id = o.buyer_id
             WHERE s.id = ? LIMIT 1'
        );
        $stmt->execute([$shipmentId]);
        $shipment = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }

    if (!$shipment) {
        return null;
    }

    try {
        $evStmt = $db->prepare(
            'SELECT * FROM shipment_events WHERE shipment_id = ? ORDER BY event_date DESC'
        );
        $evStmt->execute([$shipmentId]);
        $shipment['events'] = $evStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $shipment['events'] = [];
    }

    return $shipment;
}

/**
 * Get all shipments for a given order (multi-package support).
 *
 * @param  int $orderId
 * @return array[]
 */
function getOrderShipments(int $orderId): array
{
    $db = getDB();
    try {
        $stmt = $db->prepare(
            'SELECT s.* FROM shipments s WHERE s.order_id = ? ORDER BY s.created_at ASC'
        );
        $stmt->execute([$orderId]);
        $shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }

    foreach ($shipments as &$s) {
        try {
            $evStmt = $db->prepare(
                'SELECT * FROM shipment_events WHERE shipment_id = ? ORDER BY event_date DESC'
            );
            $evStmt->execute([$s['id']]);
            $s['events'] = $evStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $s['events'] = [];
        }
    }

    return $shipments;
}

/**
 * Get paginated shipments for a supplier with optional filters.
 *
 * @param  int   $supplierId
 * @param  array $filters   Keys: status, carrier, date_from, date_to, search
 * @param  int   $page
 * @param  int   $perPage
 * @return array ['shipments' => [], 'total' => int, 'pages' => int]
 */
function getSupplierShipments(int $supplierId, array $filters = [], int $page = 1, int $perPage = 20): array
{
    return _getShipmentsFiltered(['supplier_id' => $supplierId] + $filters, $page, $perPage);
}

/**
 * Get paginated active shipments for a buyer.
 *
 * @param  int   $buyerId
 * @param  array $filters   Keys: status, carrier
 * @param  int   $page
 * @param  int   $perPage
 * @return array ['shipments' => [], 'total' => int, 'pages' => int]
 */
function getBuyerShipments(int $buyerId, array $filters = [], int $page = 1, int $perPage = 20): array
{
    return _getShipmentsFiltered(['buyer_id' => $buyerId] + $filters, $page, $perPage);
}

/** @internal */
function _getShipmentsFiltered(array $filters, int $page, int $perPage): array
{
    $db     = getDB();
    $where  = [];
    $params = [];

    if (!empty($filters['supplier_id'])) {
        $where[]  = 's.supplier_id = ?';
        $params[] = (int)$filters['supplier_id'];
    }
    if (!empty($filters['buyer_id'])) {
        $where[]  = 'o.buyer_id = ?';
        $params[] = (int)$filters['buyer_id'];
    }
    if (!empty($filters['status'])) {
        $where[]  = 's.status = ?';
        $params[] = $filters['status'];
    }
    if (!empty($filters['carrier'])) {
        $where[]  = 's.carrier_code = ?';
        $params[] = $filters['carrier'];
    }
    if (!empty($filters['date_from'])) {
        $where[]  = 'DATE(s.shipped_date) >= ?';
        $params[] = $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $where[]  = 'DATE(s.shipped_date) <= ?';
        $params[] = $filters['date_to'];
    }
    if (!empty($filters['search'])) {
        $where[]  = '(s.tracking_number LIKE ? OR o.order_number LIKE ?)';
        $like     = '%' . $filters['search'] . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $whereStr = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $baseQuery = "FROM shipments s
                  LEFT JOIN orders o ON o.id = s.order_id
                  LEFT JOIN users  b ON b.id = o.buyer_id
                  LEFT JOIN users  sup ON sup.id = s.supplier_id
                  $whereStr";

    $total = 0;
    $rows  = [];

    try {
        $countStmt = $db->prepare("SELECT COUNT(*) $baseQuery");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset   = ($page - 1) * $perPage;
        $dataStmt = $db->prepare(
            "SELECT s.*,
                    o.order_number,
                    b.first_name AS buyer_first, b.last_name AS buyer_last,
                    sup.first_name AS supplier_first, sup.last_name AS supplier_last
             $baseQuery
             ORDER BY s.created_at DESC
             LIMIT " . (int)$perPage . " OFFSET " . (int)$offset
        );
        $dataStmt->execute($params);
        $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('_getShipmentsFiltered error: ' . $e->getMessage());
    }

    return [
        'shipments' => $rows,
        'total'     => $total,
        'pages'     => $perPage > 0 ? (int)ceil($total / $perPage) : 1,
    ];
}

// ── Status Helpers ────────────────────────────────────────────────────────────

/**
 * Human-readable label for a tracking status.
 */
function getStatusLabel(string $status): string
{
    return match ($status) {
        TRACKING_STATUS_LABEL_CREATED    => 'Label Created',
        TRACKING_STATUS_PICKED_UP        => 'Picked Up',
        TRACKING_STATUS_IN_TRANSIT       => 'In Transit',
        TRACKING_STATUS_OUT_FOR_DELIVERY => 'Out for Delivery',
        TRACKING_STATUS_DELIVERED        => 'Delivered',
        TRACKING_STATUS_EXCEPTION        => 'Delivery Exception',
        TRACKING_STATUS_RETURNED         => 'Returned',
        default                          => 'Unknown',
    };
}

/**
 * Bootstrap badge color for a tracking status.
 */
function getStatusColor(string $status): string
{
    return match ($status) {
        TRACKING_STATUS_LABEL_CREATED    => 'secondary',
        TRACKING_STATUS_PICKED_UP        => 'info',
        TRACKING_STATUS_IN_TRANSIT       => 'primary',
        TRACKING_STATUS_OUT_FOR_DELIVERY => 'warning',
        TRACKING_STATUS_DELIVERED        => 'success',
        TRACKING_STATUS_EXCEPTION        => 'danger',
        TRACKING_STATUS_RETURNED         => 'dark',
        default                          => 'secondary',
    };
}

// ── Auto-Tracking ─────────────────────────────────────────────────────────────

/**
 * Poll a carrier API for the latest status and save new events.
 *
 * @param  int $shipmentId
 * @return bool True if the tracking data was successfully refreshed
 */
function refreshTrackingStatus(int $shipmentId): bool
{
    $shipment = getShipment($shipmentId);
    if (!$shipment) {
        return false;
    }

    $carrier = $shipment['carrier_code'] ?? 'generic';
    $number  = $shipment['tracking_number'] ?? '';
    if ($number === '') {
        return false;
    }

    $result = trackShipment($carrier, $number);
    if (!$result['success'] || empty($result['events'])) {
        return false;
    }

    $db = getDB();

    foreach (array_reverse($result['events']) as $ev) {
        // Avoid duplicating events already stored
        $evDate = $ev['date'] ?? '';
        $evStat = $ev['status'] ?? '';
        try {
            $chk = $db->prepare(
                'SELECT COUNT(*) FROM shipment_events WHERE shipment_id = ? AND status = ? AND event_date = ?'
            );
            $chk->execute([$shipmentId, $evStat, $evDate]);
            if ((int)$chk->fetchColumn() > 0) {
                continue;
            }
        } catch (PDOException $e) { /* continue */ }

        updateShipmentStatus(
            $shipmentId,
            $evStat,
            $ev['location']    ?? '',
            $ev['description'] ?? '',
            $evDate,
            json_encode($ev)
        );
    }

    // Fire notifications for the latest status if it changed
    $newStatus = $result['status'];
    if ($newStatus !== $shipment['status']) {
        $latestEvent = $result['events'][0] ?? [];
        notifyTrackingUpdate($shipmentId, $latestEvent);

        // Auto-detect delivery → update order
        if ($newStatus === TRACKING_STATUS_DELIVERED && !empty($shipment['order_id'])) {
            try {
                $db->prepare('UPDATE orders SET status="delivered", updated_at=NOW() WHERE id=?')
                   ->execute([$shipment['order_id']]);
            } catch (PDOException $e) { /* non-fatal */ }
            _notifySupplierDelivered($shipment['order_id'], $shipment['supplier_id'], $shipmentId);
        }
    }

    return true;
}

/**
 * Cron job: refresh all shipments that are not yet delivered.
 *
 * @return array ['refreshed' => int, 'errors' => int]
 */
function refreshAllActiveShipments(): array
{
    $db = getDB();
    $activeStatuses = [
        TRACKING_STATUS_LABEL_CREATED,
        TRACKING_STATUS_PICKED_UP,
        TRACKING_STATUS_IN_TRANSIT,
        TRACKING_STATUS_OUT_FOR_DELIVERY,
    ];
    $placeholders = implode(',', array_fill(0, count($activeStatuses), '?'));

    try {
        $stmt = $db->prepare(
            "SELECT id FROM shipments WHERE status IN ($placeholders) AND tracking_number != '' ORDER BY updated_at ASC LIMIT 200"
        );
        $stmt->execute($activeStatuses);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return ['refreshed' => 0, 'errors' => 0];
    }

    $refreshed = 0;
    $errors    = 0;

    foreach ($ids as $id) {
        if (refreshTrackingStatus((int)$id)) {
            $refreshed++;
        } else {
            $errors++;
        }
        // Avoid hammering carrier APIs
        usleep(250000); // 0.25 s
    }

    return ['refreshed' => $refreshed, 'errors' => $errors];
}

// ── Notifications ─────────────────────────────────────────────────────────────

/**
 * Notify the buyer of a tracking update.
 *
 * @param  int   $shipmentId
 * @param  array $event  Tracking event ['status', 'location', 'description']
 */
function notifyTrackingUpdate(int $shipmentId, array $event): void
{
    $shipment = getShipment($shipmentId);
    if (!$shipment || empty($shipment['buyer_id'])) {
        return;
    }

    $status     = $event['status'] ?? $shipment['status'] ?? '';
    $orderId    = (int)($shipment['order_id'] ?? 0);
    $orderNum   = $shipment['order_number'] ?? '#' . $orderId;
    $carrier    = $shipment['carrier_name'] ?? '';
    $trackNum   = $shipment['tracking_number'] ?? '';
    $buyerId    = (int)$shipment['buyer_id'];
    $actionUrl  = '/pages/account/orders/tracking.php?shipment_id=' . $shipmentId;

    [$title, $message] = match ($status) {
        TRACKING_STATUS_PICKED_UP        => [
            "Package Picked Up",
            "Your package for order $orderNum has been picked up by $carrier.",
        ],
        TRACKING_STATUS_IN_TRANSIT       => [
            "Package In Transit",
            "Your package for order $orderNum is on the way! Tracking: $trackNum",
        ],
        TRACKING_STATUS_OUT_FOR_DELIVERY => [
            "Out for Delivery Today!",
            "Your package for order $orderNum is out for delivery today.",
        ],
        TRACKING_STATUS_DELIVERED        => [
            "Package Delivered",
            "Your package for order $orderNum has been delivered.",
        ],
        TRACKING_STATUS_EXCEPTION        => [
            "Delivery Issue",
            "There is a delivery issue with your shipment for order $orderNum. Please check tracking.",
        ],
        TRACKING_STATUS_RETURNED         => [
            "Package Returned",
            "Your package for order $orderNum has been returned to the sender.",
        ],
        default => [
            "Shipment Update",
            "Your shipment for order $orderNum has been updated.",
        ],
    };

    if (!function_exists('createNotification')) {
        return;
    }

    try {
        $db = getDB();
        createNotification($db, $buyerId, 'order_shipped', $title, $message, [
            'shipment_id'     => $shipmentId,
            'order_id'        => $orderId,
            'tracking_number' => $trackNum,
        ], 'normal', $actionUrl);

        // Exception: also notify supplier
        if ($status === TRACKING_STATUS_EXCEPTION && !empty($shipment['supplier_id'])) {
            createNotification($db, (int)$shipment['supplier_id'], 'order_shipped',
                'Delivery Exception on Order ' . $orderNum,
                "There is a delivery issue with shipment tracking $trackNum for order $orderNum.",
                ['shipment_id' => $shipmentId, 'order_id' => $orderId],
                'high',
                '/pages/supplier/orders/ship.php?order_id=' . $orderId
            );
        }
    } catch (PDOException $e) {
        error_log('notifyTrackingUpdate error: ' . $e->getMessage());
    }
}

/** @internal Notify buyer when order is shipped */
function _notifyBuyerShipped(int $orderId, int $shipmentId, string $trackingNumber, string $carrier): void
{
    if (!function_exists('createNotification')) {
        return;
    }
    try {
        $db   = getDB();
        $stmt = $db->prepare('SELECT buyer_id, order_number FROM orders WHERE id = ? LIMIT 1');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            return;
        }
        $buyerId  = (int)$order['buyer_id'];
        $orderNum = $order['order_number'] ?? '#' . $orderId;
        createNotification(
            $db,
            $buyerId,
            'order_shipped',
            'Your Order Has Shipped!',
            "Your order $orderNum has shipped via $carrier. Tracking: $trackingNumber",
            ['shipment_id' => $shipmentId, 'order_id' => $orderId, 'tracking_number' => $trackingNumber],
            'normal',
            '/pages/account/orders/tracking.php?shipment_id=' . $shipmentId
        );
    } catch (PDOException $e) {
        error_log('_notifyBuyerShipped error: ' . $e->getMessage());
    }
}

/** @internal Notify supplier when order is delivered */
function _notifySupplierDelivered(int $orderId, int $supplierId, int $shipmentId): void
{
    if (!function_exists('createNotification')) {
        return;
    }
    try {
        $db   = getDB();
        $stmt = $db->prepare('SELECT order_number FROM orders WHERE id = ? LIMIT 1');
        $stmt->execute([$orderId]);
        $orderNum = (string)($stmt->fetchColumn() ?: '#' . $orderId);
        createNotification(
            $db,
            $supplierId,
            'order_delivered',
            'Order Delivered',
            "Order $orderNum has been delivered to the buyer.",
            ['shipment_id' => $shipmentId, 'order_id' => $orderId],
            'normal',
            '/pages/supplier/orders/ship.php?order_id=' . $orderId
        );
    } catch (PDOException $e) {
        error_log('_notifySupplierDelivered error: ' . $e->getMessage());
    }
}
