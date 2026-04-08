<?php
/**
 * api/tracking.php — Parcel Tracking API (PR #15)
 *
 * Actions:
 *   track              GET   Track a shipment by tracking number + carrier
 *   create_shipment    POST  Create shipment record for an order (supplier)
 *   update             POST  Add tracking event (supplier/admin)
 *   order_shipments    GET   Get all shipments for an order
 *   my_shipments       GET   Buyer's active shipments
 *   supplier_shipments GET   Supplier's shipment list
 *   carriers           GET   List supported carriers
 *   refresh            POST  Force refresh tracking from carrier API (supplier/admin)
 */

require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/tracking.php';
require_once __DIR__ . '/../includes/notifications.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db     = getDB();

function trackJsonOut(mixed $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function trackSanitize(string $v): string
{
    return trim(htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));
}

function trackCsrf(): void
{
    if (!verifyCsrf()) {
        trackJsonOut(['success' => false, 'message' => 'Invalid CSRF token'], 403);
    }
}

switch ($action) {

    // ── Track a shipment ─────────────────────────────────────────────────────
    case 'track':
        $trackingNumber = trackSanitize($_GET['tracking_number'] ?? $_POST['tracking_number'] ?? '');
        $carrier        = trackSanitize($_GET['carrier'] ?? $_POST['carrier'] ?? 'generic');

        if ($trackingNumber === '') {
            trackJsonOut(['success' => false, 'message' => 'tracking_number is required'], 400);
        }

        // Check internal DB first
        try {
            $stmt = $db->prepare(
                'SELECT s.*, o.order_number, o.buyer_id
                 FROM shipments s
                 LEFT JOIN orders o ON o.id = s.order_id
                 WHERE s.tracking_number = ? LIMIT 1'
            );
            $stmt->execute([$trackingNumber]);
            $shipmentRow = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $shipmentRow = null;
        }

        if ($shipmentRow) {
            $shipment = getShipment((int)$shipmentRow['id']);
            trackJsonOut(['success' => true, 'data' => $shipment]);
        }

        // Fall back to carrier API
        $result = trackShipment($carrier, $trackingNumber);
        trackJsonOut(['success' => $result['success'], 'data' => $result]);
    break;

    // ── Create shipment (supplier) ───────────────────────────────────────────
    case 'create_shipment':
        requireLogin();
        requireRole(['supplier', 'admin', 'super_admin']);
        trackCsrf();

        $orderId     = (int)($_POST['order_id'] ?? 0);
        $carrierCode = trackSanitize($_POST['carrier'] ?? 'generic');
        $trackNum    = trackSanitize($_POST['tracking_number'] ?? '');
        $trackUrl    = trackSanitize($_POST['tracking_url'] ?? '');
        $estDelivery = trackSanitize($_POST['estimated_delivery'] ?? '');
        $weightKg    = isset($_POST['weight_kg']) ? (float)$_POST['weight_kg'] : null;
        $dims        = trackSanitize($_POST['package_dimensions'] ?? '');

        if (!$orderId || $trackNum === '') {
            trackJsonOut(['success' => false, 'message' => 'order_id and tracking_number are required'], 400);
        }

        $user       = getCurrentUser();
        $supplierId = (int)$user['id'];

        // Verify the order belongs to this supplier
        if (!isAdmin()) {
            try {
                $stmt = $db->prepare(
                    'SELECT COUNT(*) FROM orders o
                     JOIN order_items oi ON oi.order_id = o.id
                     JOIN products p ON p.id = oi.product_id
                     WHERE o.id = ? AND p.supplier_id = ? LIMIT 1'
                );
                $stmt->execute([$orderId, $supplierId]);
                if ((int)$stmt->fetchColumn() === 0) {
                    trackJsonOut(['success' => false, 'message' => 'Order not found or access denied'], 403);
                }
            } catch (PDOException $e) {
                // Table may not exist; allow through
            }
        }

        $shipmentId = createShipment($orderId, $supplierId, [
            'carrier_code'        => $carrierCode,
            'tracking_number'     => $trackNum,
            'tracking_url'        => $trackUrl,
            'estimated_delivery'  => $estDelivery,
            'weight_kg'           => $weightKg,
            'package_dimensions'  => $dims,
        ]);

        if (!$shipmentId) {
            trackJsonOut(['success' => false, 'message' => 'Failed to create shipment'], 500);
        }

        trackJsonOut(['success' => true, 'shipment_id' => $shipmentId, 'message' => 'Shipment created']);
    break;

    // ── Add tracking event (supplier/admin) ──────────────────────────────────
    case 'update':
        requireLogin();
        requireRole(['supplier', 'admin', 'super_admin']);
        trackCsrf();

        $shipmentId = (int)($_POST['shipment_id'] ?? 0);
        $status     = trackSanitize($_POST['status'] ?? '');
        $location   = trackSanitize($_POST['location'] ?? '');
        $description = trackSanitize($_POST['description'] ?? '');

        if (!$shipmentId || $status === '') {
            trackJsonOut(['success' => false, 'message' => 'shipment_id and status are required'], 400);
        }

        $validStatuses = [
            'label_created', 'picked_up', 'in_transit',
            'out_for_delivery', 'delivered', 'exception', 'returned', 'unknown',
        ];
        if (!in_array($status, $validStatuses, true)) {
            trackJsonOut(['success' => false, 'message' => 'Invalid status value'], 400);
        }

        // Supplier: verify own shipment
        if (!isAdmin()) {
            $user       = getCurrentUser();
            $supplierId = (int)$user['id'];
            try {
                $stmt = $db->prepare('SELECT supplier_id FROM shipments WHERE id = ? LIMIT 1');
                $stmt->execute([$shipmentId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row || (int)$row['supplier_id'] !== $supplierId) {
                    trackJsonOut(['success' => false, 'message' => 'Access denied'], 403);
                }
            } catch (PDOException $e) { /* allow */ }
        }

        $eventId = updateShipmentStatus($shipmentId, $status, $location, $description);

        if (!$eventId) {
            trackJsonOut(['success' => false, 'message' => 'Failed to update shipment'], 500);
        }

        notifyTrackingUpdate($shipmentId, [
            'status'      => $status,
            'location'    => $location,
            'description' => $description,
        ]);

        trackJsonOut(['success' => true, 'event_id' => $eventId, 'message' => 'Tracking updated']);
    break;

    // ── Order shipments ──────────────────────────────────────────────────────
    case 'order_shipments':
        requireLogin();
        $orderId = (int)($_GET['order_id'] ?? 0);
        if (!$orderId) {
            trackJsonOut(['success' => false, 'message' => 'order_id is required'], 400);
        }

        // Buyer: verify own order
        if (!isAdmin() && !isSupplier()) {
            $userId = (int)$_SESSION['user_id'];
            try {
                $stmt = $db->prepare('SELECT COUNT(*) FROM orders WHERE id = ? AND buyer_id = ?');
                $stmt->execute([$orderId, $userId]);
                if ((int)$stmt->fetchColumn() === 0) {
                    trackJsonOut(['success' => false, 'message' => 'Order not found'], 404);
                }
            } catch (PDOException $e) { /* allow */ }
        }

        $shipments = getOrderShipments($orderId);
        trackJsonOut(['success' => true, 'data' => $shipments]);
    break;

    // ── Buyer's active shipments ─────────────────────────────────────────────
    case 'my_shipments':
        requireLogin();
        $user    = getCurrentUser();
        $buyerId = (int)$user['id'];
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(50, max(1, (int)($_GET['per_page'] ?? 20)));
        $filters = [
            'status'  => trackSanitize($_GET['status'] ?? ''),
            'carrier' => trackSanitize($_GET['carrier'] ?? ''),
        ];

        $result = getBuyerShipments($buyerId, array_filter($filters), $page, $perPage);
        trackJsonOut(['success' => true, 'data' => $result]);
    break;

    // ── Supplier's shipment list ─────────────────────────────────────────────
    case 'supplier_shipments':
        requireLogin();
        requireRole(['supplier', 'admin', 'super_admin']);
        $user       = getCurrentUser();
        $supplierId = isAdmin() ? (int)($_GET['supplier_id'] ?? $user['id']) : (int)$user['id'];
        $page       = max(1, (int)($_GET['page'] ?? 1));
        $perPage    = min(50, max(1, (int)($_GET['per_page'] ?? 20)));
        $filters    = [
            'status'    => trackSanitize($_GET['status'] ?? ''),
            'carrier'   => trackSanitize($_GET['carrier'] ?? ''),
            'date_from' => trackSanitize($_GET['date_from'] ?? ''),
            'date_to'   => trackSanitize($_GET['date_to'] ?? ''),
            'search'    => trackSanitize($_GET['search'] ?? ''),
        ];

        $result = getSupplierShipments($supplierId, array_filter($filters), $page, $perPage);
        trackJsonOut(['success' => true, 'data' => $result]);
    break;

    // ── Carriers list ────────────────────────────────────────────────────────
    case 'carriers':
        $carriers = getCarriers();
        trackJsonOut(['success' => true, 'data' => $carriers]);
    break;

    // ── Force refresh from carrier API ───────────────────────────────────────
    case 'refresh':
        requireLogin();
        requireRole(['supplier', 'admin', 'super_admin']);
        trackCsrf();

        $shipmentId = (int)($_POST['shipment_id'] ?? 0);
        if (!$shipmentId) {
            trackJsonOut(['success' => false, 'message' => 'shipment_id is required'], 400);
        }

        // Supplier: verify own shipment
        if (!isAdmin()) {
            $user       = getCurrentUser();
            $supplierId = (int)$user['id'];
            try {
                $stmt = $db->prepare('SELECT supplier_id FROM shipments WHERE id = ? LIMIT 1');
                $stmt->execute([$shipmentId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row || (int)$row['supplier_id'] !== $supplierId) {
                    trackJsonOut(['success' => false, 'message' => 'Access denied'], 403);
                }
            } catch (PDOException $e) { /* allow */ }
        }

        $ok = refreshTrackingStatus($shipmentId);
        if (!$ok) {
            trackJsonOut(['success' => false, 'message' => 'Unable to refresh tracking (no API key or carrier not supported)']);
        }

        $shipment = getShipment($shipmentId);
        trackJsonOut(['success' => true, 'message' => 'Tracking refreshed', 'data' => $shipment]);
    break;

    default:
        trackJsonOut(['success' => false, 'message' => 'Unknown action'], 400);
}
