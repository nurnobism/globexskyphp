<?php
require_once __DIR__ . '/../includes/middleware.php';
header('Content-Type: application/json');
$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['compare']) || !is_array($_SESSION['compare'])) {
    $_SESSION['compare'] = [];
}

const COMPARE_MAX = 4;

function jsonOut($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

switch ($action) {
    case 'get':
        $ids = $_SESSION['compare'];
        if (empty($ids)) {
            jsonOut(['success' => true, 'data' => []]);
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare(
            "SELECT id, name, sku, price, category, brand, description, featured_image
             FROM products WHERE id IN ($placeholders)"
        );
        $stmt->execute($ids);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonOut(['success' => true, 'data' => $products, 'count' => count($products)]);

    case 'add':
        $product_id = (int)($_POST['product_id'] ?? $_GET['product_id'] ?? 0);
        if ($product_id <= 0) jsonOut(['success' => false, 'message' => 'Invalid product_id'], 400);
        $check = $db->prepare('SELECT id FROM products WHERE id = ?');
        $check->execute([$product_id]);
        if (!$check->fetch()) jsonOut(['success' => false, 'message' => 'Product not found'], 404);
        if (in_array($product_id, $_SESSION['compare'], true)) {
            jsonOut(['success' => true, 'message' => 'Product already in comparison list', 'count' => count($_SESSION['compare'])]);
        }
        if (count($_SESSION['compare']) >= COMPARE_MAX) {
            jsonOut(['success' => false, 'message' => 'Comparison list is full (max ' . COMPARE_MAX . ' products)'], 400);
        }
        $_SESSION['compare'][] = $product_id;
        jsonOut(['success' => true, 'message' => 'Product added to comparison', 'count' => count($_SESSION['compare'])]);

    case 'remove':
        $product_id = (int)($_POST['product_id'] ?? $_GET['product_id'] ?? 0);
        if ($product_id <= 0) jsonOut(['success' => false, 'message' => 'Invalid product_id'], 400);
        $key = array_search($product_id, $_SESSION['compare'], true);
        if ($key === false) {
            jsonOut(['success' => false, 'message' => 'Product not in comparison list'], 404);
        }
        array_splice($_SESSION['compare'], $key, 1);
        jsonOut(['success' => true, 'message' => 'Product removed from comparison', 'count' => count($_SESSION['compare'])]);

    case 'clear':
        $_SESSION['compare'] = [];
        jsonOut(['success' => true, 'message' => 'Comparison list cleared']);

    default:
        jsonOut(['success' => false, 'message' => 'Invalid action'], 400);
}
