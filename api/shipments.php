<?php
/**
 * api/shipments.php — Shipment Tracking API
 */

require_once __DIR__ . '/../includes/middleware.php';

$action = $_GET['action'] ?? 'track';
$db     = getDB();

switch ($action) {

    case 'track':
        $tracking = trim(get('tracking_number', '') ?: post('tracking_number', ''));
        if (!$tracking) jsonResponse(['error' => 'Tracking number required'], 400);

        $stmt = $db->prepare('SELECT s.*, o.order_number FROM shipments s
            LEFT JOIN orders o ON o.id=s.order_id
            WHERE s.tracking_number = ?');
        $stmt->execute([$tracking]);
        $shipment = $stmt->fetch();
        if (!$shipment) jsonResponse(['error' => 'Shipment not found'], 404);
        jsonResponse(['data' => $shipment]);
        break;

    case 'list':
        requireLogin();
        $stmt = $db->prepare('SELECT s.*, o.order_number FROM shipments s
            JOIN orders o ON o.id=s.order_id WHERE o.buyer_id=? ORDER BY s.created_at DESC');
        $stmt->execute([$_SESSION['user_id']]);
        jsonResponse(['data' => $stmt->fetchAll()]);
        break;

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}
