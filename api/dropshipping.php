<?php
/**
 * api/dropshipping.php — Dropshipping API
 * GET  ?action=list|orders|markup_rules
 * POST ?action=import|route|markup_rules
 */
require_once __DIR__ . '/../includes/middleware.php';

header('Content-Type: application/json');
$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

switch ($action) {

    case 'list':
        $page     = max(1, (int)get('page', 1));
        $perPage  = 20;
        $offset   = ($page - 1) * $perPage;
        $category = get('category', '');
        $q        = get('q', '');

        $where  = ['p.status = "active"', 'p.dropship_eligible = 1'];
        $params = [];

        if ($category) {
            $where[]  = 'p.category_id = ?';
            $params[] = $category;
        }
        if ($q) {
            $where[]  = 'p.name LIKE ?';
            $params[] = '%' . $q . '%';
        }

        $whereClause = implode(' AND ', $where);

        $countStmt = $db->prepare("SELECT COUNT(*) FROM products p WHERE $whereClause");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT p.id, p.name, p.slug, p.images, p.unit, p.cost_price, p.price, p.short_desc,
                   s.company_name AS supplier_name,
                   c.name AS category_name, c.id AS category_id,
                   ROUND(p.cost_price * (1 + COALESCE(mr.markup_pct, 50) / 100), 2) AS suggested_retail,
                   COALESCE(mr.markup_pct, 50) AS default_markup_pct
            FROM products p
            LEFT JOIN suppliers s  ON s.id  = p.supplier_id
            LEFT JOIN categories c ON c.id  = p.category_id
            LEFT JOIN dropship_markup_rules mr ON mr.category_id = p.category_id
            WHERE $whereClause
            ORDER BY p.created_at DESC
            LIMIT $perPage OFFSET $offset
        ");
        $stmt->execute($params);

        echo json_encode([
            'success' => true,
            'data'    => $stmt->fetchAll(),
            'total'   => $total,
            'page'    => $page,
            'pages'   => (int)ceil($total / $perPage),
        ]);
        break;

    case 'import':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }
        requireAuth();
        if (!verifyCsrf()) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            break;
        }

        $productId = (int)post('product_id');
        $markupPct = (float)(post('markup_pct') ?: 50);

        if (!$productId) {
            http_response_code(400);
            echo json_encode(['error' => 'product_id required']);
            break;
        }

        // Verify product is dropship-eligible
        $product = $db->prepare('SELECT id FROM products WHERE id = ? AND dropship_eligible = 1 AND status = "active"');
        $product->execute([$productId]);
        if (!$product->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Product not found or not eligible for dropshipping']);
            break;
        }

        // Check not already imported by this user
        $exists = $db->prepare('SELECT id FROM dropship_imports WHERE user_id = ? AND product_id = ?');
        $exists->execute([$_SESSION['user_id'], $productId]);
        if ($exists->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Product already imported to your store']);
            break;
        }

        $stmt = $db->prepare('
            INSERT INTO dropship_imports (user_id, product_id, markup_pct, imported_at)
            VALUES (?, ?, ?, NOW())
        ');
        $stmt->execute([$_SESSION['user_id'], $productId, $markupPct]);

        echo json_encode([
            'success'   => true,
            'message'   => 'Product imported to your store',
            'import_id' => $db->lastInsertId(),
        ]);
        break;

    case 'orders':
        requireAuth();
        $page    = max(1, (int)get('page', 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;
        $status  = get('status', '');

        $where  = ['do.dropshipper_id = ?'];
        $params = [$_SESSION['user_id']];

        if ($status) {
            $where[]  = 'do.status = ?';
            $params[] = $status;
        }

        $whereClause = implode(' AND ', $where);

        $countStmt = $db->prepare("SELECT COUNT(*) FROM dropship_orders do WHERE $whereClause");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $params[] = $perPage;
        $params[] = $offset;

        $stmt = $db->prepare("
            SELECT do.*, p.name AS product_name, p.images AS product_images,
                   o.order_number, o.created_at AS order_date
            FROM dropship_orders do
            JOIN products p ON p.id = do.product_id
            JOIN orders o   ON o.id = do.order_id
            WHERE $whereClause
            ORDER BY do.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);

        echo json_encode([
            'success' => true,
            'data'    => $stmt->fetchAll(),
            'total'   => $total,
            'page'    => $page,
        ]);
        break;

    case 'route':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }
        requireAuth();
        if (!verifyCsrf()) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            break;
        }

        $orderId = (int)post('order_id');
        if (!$orderId) {
            http_response_code(400);
            echo json_encode(['error' => 'order_id required']);
            break;
        }

        $stmt = $db->prepare('
            UPDATE dropship_orders
            SET status = "routed_to_supplier", routed_at = NOW()
            WHERE id = ? AND dropshipper_id = ? AND status = "pending"
        ');
        $stmt->execute([$orderId, $_SESSION['user_id']]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Order not found or already routed']);
            break;
        }

        echo json_encode(['success' => true, 'message' => 'Order routed to supplier']);
        break;

    case 'markup_rules':
        if ($method === 'GET') {
            $stmt = $db->query('
                SELECT mr.*, c.name AS category_name
                FROM dropship_markup_rules mr
                LEFT JOIN categories c ON c.id = mr.category_id
                ORDER BY c.name
            ');
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        } else {
            requireAdmin();
            if (!verifyCsrf()) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid CSRF token']);
                break;
            }

            $categoryId = post('category_id') ?: null;
            $markupPct  = (float)post('markup_pct');
            $minPrice   = (float)(post('min_price') ?: 0);
            $maxPrice   = post('max_price') ? (float)post('max_price') : null;

            if ($markupPct < 0 || $markupPct > 1000) {
                http_response_code(400);
                echo json_encode(['error' => 'markup_pct must be between 0 and 1000']);
                break;
            }

            $stmt = $db->prepare('
                INSERT INTO dropship_markup_rules (category_id, markup_pct, min_price, max_price, created_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE markup_pct = VALUES(markup_pct),
                                        min_price  = VALUES(min_price),
                                        max_price  = VALUES(max_price)
            ');
            $stmt->execute([$categoryId, $markupPct, $minPrice, $maxPrice]);

            echo json_encode(['success' => true, 'message' => 'Markup rule saved']);
        }
        break;

    case 'save_prefs':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }
        requireAuth();
        if (!verifyCsrf()) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            break;
        }

        $globalMarkup    = min(1000, max(0, (float)(post('global_markup_pct') ?: post('global_markup_pct_num') ?: 50)));
        $autoSync        = post('auto_sync') ? 1 : 0;
        $syncIntervalHrs = (int)(post('sync_interval_hrs') ?: 24);
        $notifyStockOut  = post('notify_stock_out') ? 1 : 0;
        $notifyPriceChg  = post('notify_price_chg') ? 1 : 0;

        $stmt = $db->prepare('
            INSERT INTO dropship_preferences
                (user_id, global_markup_pct, auto_sync, sync_interval_hrs, notify_stock_out, notify_price_chg, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                global_markup_pct = VALUES(global_markup_pct),
                auto_sync         = VALUES(auto_sync),
                sync_interval_hrs = VALUES(sync_interval_hrs),
                notify_stock_out  = VALUES(notify_stock_out),
                notify_price_chg  = VALUES(notify_price_chg),
                updated_at        = NOW()
        ');
        $stmt->execute([$_SESSION['user_id'], $globalMarkup, $autoSync, $syncIntervalHrs, $notifyStockOut, $notifyPriceChg]);

        echo json_encode(['success' => true, 'message' => 'Preferences saved successfully']);
        break;

    case 'sync_trigger':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }
        requireAuth();
        if (!verifyCsrf()) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            break;
        }
        // Record the sync request; background worker picks it up
        $stmt = $db->prepare('
            INSERT INTO dropship_sync_log (user_id, status, requested_at)
            VALUES (?, "queued", NOW())
        ');
        $stmt->execute([$_SESSION['user_id']]);
        echo json_encode(['success' => true, 'message' => 'Sync queued — products will update shortly']);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Unknown action']);
}
