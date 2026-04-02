<?php
/**
 * api/reviews.php — Reviews API
 */

require_once __DIR__ . '/../includes/middleware.php';

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

switch ($action) {

    case 'list':
        $productId = (int)get('product_id', 0);
        if (!$productId) jsonResponse(['error' => 'Product ID required'], 400);
        $page = max(1, (int)get('page', 1));
        $sql  = 'SELECT r.*, u.first_name, u.last_name, u.avatar FROM reviews r
                 JOIN users u ON u.id=r.user_id
                 WHERE r.product_id=? AND r.status="approved" ORDER BY r.created_at DESC';
        $result = paginate($db, $sql, [$productId], $page, 10);

        // Add stats
        $stats = $db->prepare('SELECT AVG(rating) avg_rating, COUNT(*) total FROM reviews WHERE product_id=? AND status="approved"');
        $stats->execute([$productId]);
        $result['stats'] = $stats->fetch();
        jsonResponse($result);
        break;

    case 'create':
        if (!isLoggedIn())      jsonResponse(['error' => 'Login required'], 401);
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);

        $productId = (int)post('product_id', 0);
        $rating    = min(5, max(1, (int)post('rating', 3)));
        $title     = trim(post('title', ''));
        $body      = trim(post('body', ''));

        if (!$productId) jsonResponse(['error' => 'Product ID required'], 400);

        // Check if already reviewed
        $existing = $db->prepare('SELECT id FROM reviews WHERE user_id=? AND product_id=?');
        $existing->execute([$_SESSION['user_id'], $productId]);
        if ($existing->fetch()) {
            if (isset($_POST['_redirect'])) { flashMessage('warning', 'You have already reviewed this product.'); redirect($_POST['_redirect']); }
            jsonResponse(['error' => 'Already reviewed'], 409);
        }

        $db->prepare('INSERT INTO reviews (product_id, user_id, rating, title, body) VALUES (?,?,?,?,?)')
           ->execute([$productId, $_SESSION['user_id'], $rating, $title, $body]);

        // Update product rating
        $db->prepare('UPDATE products SET rating=(SELECT AVG(rating) FROM reviews WHERE product_id=? AND status="approved"), review_count=(SELECT COUNT(*) FROM reviews WHERE product_id=? AND status="approved") WHERE id=?')
           ->execute([$productId, $productId, $productId]);

        if (isset($_POST['_redirect'])) { flashMessage('success', 'Review submitted for moderation.'); redirect($_POST['_redirect']); }
        jsonResponse(['success' => true]);
        break;

    case 'helpful':
        if (!isLoggedIn())      jsonResponse(['error' => 'Login required'], 401);
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        $id = (int)post('review_id', 0);
        $db->prepare('UPDATE reviews SET helpful = helpful + 1 WHERE id = ?')->execute([$id]);
        jsonResponse(['success' => true]);
        break;

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}
