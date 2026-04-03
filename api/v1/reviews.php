<?php
/**
 * api/v1/reviews.php — Reviews API Resource
 *
 * Actions: list, create, update, delete
 */
if (!defined('API_RESOURCE')) {
    require_once __DIR__ . '/gateway.php';
    exit;
}

require_once __DIR__ . '/../../includes/api-response.php';

$db     = getDB();
$action = API_ACTION ?: ($_GET['action'] ?? 'list');
$apiKey = API_KEY_ROW;

$elapsed = fn() => (int)((microtime(true) - API_START_TIME) * 1000);

switch ($action) {
    // ── GET list ──────────────────────────────────────────────
    case 'list':
        $productId = (int)($_GET['product_id'] ?? 0);
        if (!$productId) {
            apiError('product_id is required.', 400);
        }
        $pag   = getPaginationParams();
        $count = (int)$db->prepare('SELECT COUNT(*) FROM reviews WHERE product_id = ? AND status = "approved"')->execute([$productId]) ? $db->query("SELECT COUNT(*) FROM reviews WHERE product_id = $productId AND status = 'approved'")->fetchColumn() : 0;

        $countStmt = $db->prepare('SELECT COUNT(*) FROM reviews WHERE product_id = ? AND status = "approved"');
        $countStmt->execute([$productId]);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $db->prepare(
            'SELECT r.id, r.rating, r.title, r.body, r.created_at,
                    u.first_name, u.last_name
             FROM reviews r
             LEFT JOIN users u ON u.id = r.user_id
             WHERE r.product_id = ? AND r.status = "approved"
             ORDER BY r.created_at DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->execute([$productId, $pag['per_page'], $pag['offset']]);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($apiKey) {
            logApiRequest((int)$apiKey['id'], (int)$apiKey['user_id'], 'GET', 'reviews/list', 200, $elapsed());
        }
        apiPaginated($reviews, $pag['page'], $pag['per_page'], $total, $apiKey ? getRateLimit($apiKey) : null);
        break;

    // ── POST create ───────────────────────────────────────────
    case 'create':
        $userId = (int)$apiKey['user_id'];
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $errors = [];
        if (empty($body['product_id'])) {
            $errors['product_id'] = 'Product ID required.';
        }
        if (empty($body['rating']) || $body['rating'] < 1 || $body['rating'] > 5) {
            $errors['rating'] = 'Rating must be between 1 and 5.';
        }
        if (empty($body['body'])) {
            $errors['body'] = 'Review body required.';
        }
        if ($errors) {
            apiValidationError($errors);
        }
        $db->prepare(
            'INSERT INTO reviews (product_id, user_id, rating, title, body, status, created_at)
             VALUES (?, ?, ?, ?, ?, "pending", NOW())'
        )->execute([
            (int)$body['product_id'],
            $userId,
            (int)$body['rating'],
            $body['title'] ?? '',
            $body['body'],
        ]);
        logApiRequest((int)$apiKey['id'], $userId, 'POST', 'reviews/create', 201, $elapsed());
        apiSuccess(['message' => 'Review submitted and pending approval.'], null, 201, getRateLimit($apiKey));
        break;

    // ── PUT update ────────────────────────────────────────────
    case 'update':
        $userId = (int)$apiKey['user_id'];
        $id     = (int)($_GET['id'] ?? 0);
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!$id) {
            apiError('Review ID required.', 400);
        }
        $stmt = $db->prepare('SELECT * FROM reviews WHERE id = ?');
        $stmt->execute([$id]);
        $review = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$review) {
            apiNotFound('Review');
        }
        if ((int)$review['user_id'] !== $userId && !in_array($apiKey['user_role'], ['admin', 'super_admin'], true)) {
            apiForbidden('You do not own this review.');
        }
        $allowed = ['rating', 'title', 'body'];
        $sets    = [];
        $vals    = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $body)) {
                $sets[] = "$field = ?";
                $vals[] = $body[$field];
            }
        }
        if ($sets) {
            $vals[] = $id;
            $db->prepare('UPDATE reviews SET ' . implode(', ', $sets) . ', status = "pending" WHERE id = ?')->execute($vals);
        }
        logApiRequest((int)$apiKey['id'], $userId, 'PUT', 'reviews/update', 200, $elapsed());
        apiSuccess(['message' => 'Review updated.'], null, 200, getRateLimit($apiKey));
        break;

    // ── DELETE delete ─────────────────────────────────────────
    case 'delete':
        $userId = (int)$apiKey['user_id'];
        $id     = (int)($_GET['id'] ?? 0);
        if (!$id) {
            apiError('Review ID required.', 400);
        }
        $stmt = $db->prepare('SELECT user_id FROM reviews WHERE id = ?');
        $stmt->execute([$id]);
        $review = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$review) {
            apiNotFound('Review');
        }
        if ((int)$review['user_id'] !== $userId && !in_array($apiKey['user_role'], ['admin', 'super_admin'], true)) {
            apiForbidden('You do not own this review.');
        }
        $db->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
        logApiRequest((int)$apiKey['id'], $userId, 'DELETE', 'reviews/delete', 200, $elapsed());
        apiSuccess(['message' => 'Review deleted.'], null, 200, getRateLimit($apiKey));
        break;

    default:
        if ($apiKey) {
            logApiRequest((int)$apiKey['id'], (int)$apiKey['user_id'], $_SERVER['REQUEST_METHOD'], "reviews/$action", 404, $elapsed());
        }
        apiNotFound("Action '$action'");
}
