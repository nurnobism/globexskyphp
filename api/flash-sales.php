<?php
require_once __DIR__ . '/../includes/middleware.php';
header('Content-Type: application/json');
$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOut($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

switch ($action) {
    case 'list':
        $stmt = $db->query('SELECT * FROM flash_sales ORDER BY start_date DESC');
        jsonOut(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'detail':
        $id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
        if (!$id) {
            jsonOut(['success' => false, 'message' => 'id is required'], 422);
        }

        $sale = $db->prepare('SELECT * FROM flash_sales WHERE id = ?');
        $sale->execute([$id]);
        $row = $sale->fetch();
        if (!$row) {
            jsonOut(['success' => false, 'message' => 'Flash sale not found'], 404);
        }

        $products = $db->prepare(
            'SELECT fsp.product_id, fsp.sale_price, p.name, p.description, p.price AS original_price, p.image_url
             FROM flash_sale_products fsp
             JOIN products p ON p.id = fsp.product_id
             WHERE fsp.sale_id = ?'
        );
        $products->execute([$id]);
        $row['products'] = $products->fetchAll();

        jsonOut(['success' => true, 'data' => $row]);
        break;

    case 'create':
        requireRole('admin');
        validateCsrf();
        $title    = sanitize($_POST['title'] ?? '');
        $desc     = sanitize($_POST['description'] ?? '');
        $discount = sanitize($_POST['discount_percent'] ?? '');
        $start    = sanitize($_POST['start_date'] ?? '');
        $end      = sanitize($_POST['end_date'] ?? '');

        if (!$title || !$discount || !$start || !$end) {
            jsonOut(['success' => false, 'message' => 'title, discount_percent, start_date, and end_date are required'], 422);
        }
        if (!is_numeric($discount) || $discount < 0 || $discount > 100) {
            jsonOut(['success' => false, 'message' => 'discount_percent must be between 0 and 100'], 422);
        }

        $stmt = $db->prepare(
            'INSERT INTO flash_sales (title, description, discount_percent, start_date, end_date, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$title, $desc, $discount, $start, $end]);
        jsonOut(['success' => true, 'id' => $db->lastInsertId()]);
        break;

    case 'update':
        requireRole('admin');
        validateCsrf();
        $id       = sanitize($_POST['id'] ?? '');
        $title    = sanitize($_POST['title'] ?? '');
        $desc     = sanitize($_POST['description'] ?? '');
        $discount = sanitize($_POST['discount_percent'] ?? '');
        $start    = sanitize($_POST['start_date'] ?? '');
        $end      = sanitize($_POST['end_date'] ?? '');

        if (!$id) {
            jsonOut(['success' => false, 'message' => 'id is required'], 422);
        }

        $check = $db->prepare('SELECT id FROM flash_sales WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetch()) {
            jsonOut(['success' => false, 'message' => 'Flash sale not found'], 404);
        }

        $fields = [];
        $params = [];
        if ($title !== '')    { $fields[] = 'title = ?';            $params[] = $title; }
        if ($desc !== '')     { $fields[] = 'description = ?';      $params[] = $desc; }
        if ($discount !== '') {
            if (!is_numeric($discount) || $discount < 0 || $discount > 100) {
                jsonOut(['success' => false, 'message' => 'discount_percent must be between 0 and 100'], 422);
            }
            $fields[] = 'discount_percent = ?'; $params[] = $discount;
        }
        if ($start !== '')    { $fields[] = 'start_date = ?';       $params[] = $start; }
        if ($end !== '')      { $fields[] = 'end_date = ?';         $params[] = $end; }

        if (empty($fields)) {
            jsonOut(['success' => false, 'message' => 'No fields to update'], 422);
        }

        $params[] = $id;
        $stmt = $db->prepare('UPDATE flash_sales SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = ?');
        $stmt->execute($params);
        jsonOut(['success' => true, 'message' => 'Flash sale updated']);
        break;

    case 'delete':
        requireRole('admin');
        validateCsrf();
        $id = sanitize($_POST['id'] ?? '');

        if (!$id) {
            jsonOut(['success' => false, 'message' => 'id is required'], 422);
        }

        $check = $db->prepare('SELECT id FROM flash_sales WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetch()) {
            jsonOut(['success' => false, 'message' => 'Flash sale not found'], 404);
        }

        $db->prepare('DELETE FROM flash_sale_products WHERE sale_id = ?')->execute([$id]);
        $db->prepare('DELETE FROM flash_sales WHERE id = ?')->execute([$id]);
        jsonOut(['success' => true, 'message' => 'Flash sale deleted']);
        break;

    case 'add_product':
        requireRole('admin');
        validateCsrf();
        $saleId    = sanitize($_POST['sale_id'] ?? '');
        $productId = sanitize($_POST['product_id'] ?? '');
        $salePrice = sanitize($_POST['sale_price'] ?? '');

        if (!$saleId || !$productId || !$salePrice) {
            jsonOut(['success' => false, 'message' => 'sale_id, product_id, and sale_price are required'], 422);
        }
        if (!is_numeric($salePrice) || $salePrice < 0) {
            jsonOut(['success' => false, 'message' => 'sale_price must be a non-negative number'], 422);
        }

        $checkSale = $db->prepare('SELECT id FROM flash_sales WHERE id = ?');
        $checkSale->execute([$saleId]);
        if (!$checkSale->fetch()) {
            jsonOut(['success' => false, 'message' => 'Flash sale not found'], 404);
        }

        $checkProd = $db->prepare('SELECT id FROM products WHERE id = ?');
        $checkProd->execute([$productId]);
        if (!$checkProd->fetch()) {
            jsonOut(['success' => false, 'message' => 'Product not found'], 404);
        }

        $stmt = $db->prepare(
            'INSERT INTO flash_sale_products (sale_id, product_id, sale_price) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE sale_price = VALUES(sale_price)'
        );
        $stmt->execute([$saleId, $productId, $salePrice]);
        jsonOut(['success' => true, 'id' => $db->lastInsertId()]);
        break;

    case 'remove_product':
        requireRole('admin');
        validateCsrf();
        $saleId    = sanitize($_POST['sale_id'] ?? '');
        $productId = sanitize($_POST['product_id'] ?? '');

        if (!$saleId || !$productId) {
            jsonOut(['success' => false, 'message' => 'sale_id and product_id are required'], 422);
        }

        $stmt = $db->prepare('DELETE FROM flash_sale_products WHERE sale_id = ? AND product_id = ?');
        $stmt->execute([$saleId, $productId]);
        if ($stmt->rowCount() === 0) {
            jsonOut(['success' => false, 'message' => 'Product not found in this flash sale'], 404);
        }
        jsonOut(['success' => true, 'message' => 'Product removed from flash sale']);
        break;

    default:
        jsonOut(['success' => false, 'message' => 'Invalid action'], 400);
}
