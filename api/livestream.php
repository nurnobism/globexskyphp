<?php
/**
 * api/livestream.php — Livestream CRUD API
 * GET  ?action=list|detail|chat|products
 * POST ?action=create|update|delete|chat
 */

require_once __DIR__ . '/../includes/middleware.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

switch ($action) {

    // ── LIST ──────────────────────────────────────────────────────────────────
    case 'list':
        $status = get('status', '');   // live | upcoming | past
        $page   = max(1, (int)get('page', 1));
        $limit  = 12;
        $offset = ($page - 1) * $limit;

        $where  = ['1=1'];
        $params = [];

        if (in_array($status, ['live', 'upcoming', 'past'])) {
            $where[]  = 'ls.status = ?';
            $params[] = $status;
        }

        $sql = "SELECT ls.*, u.name seller_name, u.company_name,
                       COUNT(lc.id) AS chat_count
                FROM livestreams ls
                LEFT JOIN users u ON u.id = ls.seller_id
                LEFT JOIN livestream_chat lc ON lc.stream_id = ls.id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY ls.id
                ORDER BY FIELD(ls.status,'live','upcoming','past'), ls.scheduled_at ASC
                LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countStmt = $db->prepare("SELECT COUNT(*) FROM livestreams ls WHERE " . implode(' AND ', array_slice($where, 0)));
        $countStmt->execute(array_slice($params, 0, -2));
        $total = (int)$countStmt->fetchColumn();

        jsonResponse([
            'data'       => $streams,
            'total'      => $total,
            'page'       => $page,
            'last_page'  => (int)ceil($total / $limit),
        ]);
        break;

    // ── DETAIL ────────────────────────────────────────────────────────────────
    case 'detail':
        $id = (int)get('id', 0);
        if (!$id) jsonResponse(['error' => 'Stream ID required'], 400);

        $stmt = $db->prepare(
            "SELECT ls.*, u.name seller_name, u.company_name, u.avatar
             FROM livestreams ls
             LEFT JOIN users u ON u.id = ls.seller_id
             WHERE ls.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $stream = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$stream) jsonResponse(['error' => 'Stream not found'], 404);

        // increment viewer count for live streams
        if ($stream['status'] === 'live') {
            $db->prepare('UPDATE livestreams SET viewer_count = viewer_count + 1 WHERE id = ?')->execute([$id]);
        }

        jsonResponse($stream);
        break;

    // ── CREATE ────────────────────────────────────────────────────────────────
    case 'create':
        requireAuth();
        if ($method !== 'POST') jsonResponse(['error' => 'POST required'], 405);
        if (!verifyCsrf()) jsonResponse(['error' => 'Invalid CSRF token'], 403);

        $title        = trim($_POST['title'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $scheduledAt  = $_POST['scheduled_at'] ?? '';
        $category     = trim($_POST['category'] ?? '');
        $streamUrl    = trim($_POST['stream_url'] ?? '');

        if (!$title || !$scheduledAt) jsonResponse(['error' => 'Title and scheduled time are required'], 422);

        $stmt = $db->prepare(
            "INSERT INTO livestreams (seller_id, title, description, scheduled_at, category, stream_url, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'upcoming', NOW())"
        );
        $stmt->execute([$_SESSION['user_id'], $title, $description, $scheduledAt, $category, $streamUrl]);
        $streamId = $db->lastInsertId();

        // Attach featured products
        $productIds = array_filter(array_map('intval', explode(',', $_POST['product_ids'] ?? '')));
        if ($productIds) {
            $ins = $db->prepare('INSERT IGNORE INTO livestream_products (stream_id, product_id) VALUES (?, ?)');
            foreach ($productIds as $pid) {
                $ins->execute([$streamId, $pid]);
            }
        }

        jsonResponse(['success' => true, 'stream_id' => $streamId], 201);
        break;

    // ── UPDATE ────────────────────────────────────────────────────────────────
    case 'update':
        requireAuth();
        if ($method !== 'POST') jsonResponse(['error' => 'POST required'], 405);
        if (!verifyCsrf()) jsonResponse(['error' => 'Invalid CSRF token'], 403);

        $id = (int)($_POST['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'Stream ID required'], 400);

        $stmt = $db->prepare('SELECT seller_id FROM livestreams WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) jsonResponse(['error' => 'Stream not found'], 404);
        if ((int)$row['seller_id'] !== (int)$_SESSION['user_id'] && !isAdmin()) {
            jsonResponse(['error' => 'Forbidden'], 403);
        }

        $fields = [];
        $params = [];
        foreach (['title','description','scheduled_at','category','stream_url','status'] as $f) {
            if (isset($_POST[$f])) {
                $fields[] = "$f = ?";
                $params[] = $_POST[$f];
            }
        }
        if (!$fields) jsonResponse(['error' => 'Nothing to update'], 422);

        $params[] = $id;
        $db->prepare('UPDATE livestreams SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
        jsonResponse(['success' => true]);
        break;

    // ── DELETE ────────────────────────────────────────────────────────────────
    case 'delete':
        requireAuth();
        if ($method !== 'POST') jsonResponse(['error' => 'POST required'], 405);
        if (!verifyCsrf()) jsonResponse(['error' => 'Invalid CSRF token'], 403);

        $id = (int)($_POST['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'Stream ID required'], 400);

        $stmt = $db->prepare('SELECT seller_id FROM livestreams WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) jsonResponse(['error' => 'Stream not found'], 404);
        if ((int)$row['seller_id'] !== (int)$_SESSION['user_id'] && !isAdmin()) {
            jsonResponse(['error' => 'Forbidden'], 403);
        }

        $db->prepare('DELETE FROM livestream_chat WHERE stream_id = ?')->execute([$id]);
        $db->prepare('DELETE FROM livestream_products WHERE stream_id = ?')->execute([$id]);
        $db->prepare('DELETE FROM livestreams WHERE id = ?')->execute([$id]);
        jsonResponse(['success' => true]);
        break;

    // ── CHAT ─────────────────────────────────────────────────────────────────
    case 'chat':
        $streamId = (int)get('stream_id', (int)($_POST['stream_id'] ?? 0));
        if (!$streamId) jsonResponse(['error' => 'stream_id required'], 400);

        if ($method === 'POST') {
            // Add message
            requireAuth();
            if (!verifyCsrf()) jsonResponse(['error' => 'Invalid CSRF token'], 403);
            $message = trim($_POST['message'] ?? '');
            if (!$message) jsonResponse(['error' => 'Message cannot be empty'], 422);
            if (mb_strlen($message) > 500) jsonResponse(['error' => 'Message too long'], 422);

            $db->prepare(
                'INSERT INTO livestream_chat (stream_id, user_id, message, created_at) VALUES (?, ?, ?, NOW())'
            )->execute([$streamId, $_SESSION['user_id'], $message]);
            jsonResponse(['success' => true]);
        } else {
            // Get messages (poll after a given message ID)
            $after = (int)get('after', 0);
            $stmt  = $db->prepare(
                "SELECT lc.id, lc.message, lc.created_at, u.name AS username, u.avatar
                 FROM livestream_chat lc
                 LEFT JOIN users u ON u.id = lc.user_id
                 WHERE lc.stream_id = ? AND lc.id > ?
                 ORDER BY lc.id ASC LIMIT 50"
            );
            $stmt->execute([$streamId, $after]);
            jsonResponse(['messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }
        break;

    // ── PRODUCTS IN STREAM ────────────────────────────────────────────────────
    case 'products':
        $streamId = (int)get('stream_id', 0);
        if (!$streamId) jsonResponse(['error' => 'stream_id required'], 400);

        $stmt = $db->prepare(
            "SELECT p.id, p.name, p.slug, p.price, p.thumbnail, p.short_desc
             FROM livestream_products lp
             JOIN products p ON p.id = lp.product_id
             WHERE lp.stream_id = ?
             ORDER BY lp.sort_order ASC"
        );
        $stmt->execute([$streamId]);
        jsonResponse(['products' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}
