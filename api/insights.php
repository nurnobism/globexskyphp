<?php
require_once __DIR__ . '/../includes/middleware.php';
header('Content-Type: application/json');
$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOut($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

switch ($action) {
    case 'market_trends':
        $stmt = $db->query(
            'SELECT category,
                    AVG(price)  AS avg_price,
                    COUNT(*)    AS count
             FROM products
             GROUP BY category
             ORDER BY count DESC
             LIMIT 10'
        );
        jsonOut(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'supplier_performance':
        $stmt = $db->query(
            'SELECT s.id,
                    s.company_name,
                    AVG(r.rating)  AS avg_rating,
                    COUNT(o.id)    AS order_count
             FROM suppliers s
             LEFT JOIN orders  o ON o.supplier_id = s.id
             LEFT JOIN reviews r ON r.supplier_id = s.id
             GROUP BY s.id
             ORDER BY avg_rating DESC
             LIMIT 20'
        );
        jsonOut(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'business_metrics':
        requireAuth();
        $uid = $_SESSION['user_id'];

        $orders = $db->prepare(
            'SELECT COUNT(*)         AS total_orders,
                    COALESCE(SUM(total), 0) AS total_revenue,
                    COALESCE(AVG(total), 0) AS avg_order_value
             FROM orders
             WHERE buyer_id = ?'
        );
        $orders->execute([$uid]);
        $metrics = $orders->fetch();

        $products = $db->prepare('SELECT COUNT(*) AS total_products FROM products WHERE seller_id = ?');
        $products->execute([$uid]);
        $prodRow = $products->fetch();

        jsonOut([
            'success' => true,
            'data'    => [
                'total_orders'     => (int)  $metrics['total_orders'],
                'total_revenue'    => (float) $metrics['total_revenue'],
                'avg_order_value'  => (float) $metrics['avg_order_value'],
                'total_products'   => (int)  $prodRow['total_products'],
            ],
        ]);
        break;

    case 'revenue_data':
        requireAuth();
        $uid  = $_SESSION['user_id'];
        $stmt = $db->prepare(
            'SELECT DATE(placed_at) AS date,
                    SUM(total)      AS revenue
             FROM orders
             WHERE buyer_id = ?
             GROUP BY DATE(placed_at)
             ORDER BY date DESC
             LIMIT 30'
        );
        $stmt->execute([$uid]);
        jsonOut(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    default:
        jsonOut(['success' => false, 'message' => 'Invalid action'], 400);
}
