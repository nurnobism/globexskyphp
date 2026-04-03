<?php
/**
 * api/live.php — Live Streaming API
 *
 * Actions: get_live, get_stream, start, end, schedule, pin_product, unpin_product,
 *          chat_history, viewers, upcoming, vod_list, follow_streamer,
 *          unfollow_streamer, react, report
 */
require_once __DIR__ . '/../includes/middleware.php';
header('Content-Type: application/json');

$db     = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOut($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

switch ($action) {
    // ── get_live ──────────────────────────────────────────────
    case 'get_live':
        $stmt = $db->query(
            'SELECT ls.id, ls.title, ls.category, ls.thumbnail_url, ls.started_at,
                    ls.peak_viewers, ls.total_viewers,
                    u.first_name, u.last_name, u.avatar_url
             FROM live_streams ls
             LEFT JOIN users u ON u.id = ls.streamer_id
             WHERE ls.status = "live"
             ORDER BY ls.peak_viewers DESC
             LIMIT 20'
        );
        jsonOut(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    // ── get_stream ────────────────────────────────────────────
    case 'get_stream':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            jsonOut(['success' => false, 'message' => 'Stream ID required.'], 400);
        }
        $stmt = $db->prepare(
            'SELECT ls.*, u.first_name, u.last_name, u.avatar_url
             FROM live_streams ls
             LEFT JOIN users u ON u.id = ls.streamer_id
             WHERE ls.id = ?'
        );
        $stmt->execute([$id]);
        $stream = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$stream) {
            jsonOut(['success' => false, 'message' => 'Stream not found.'], 404);
        }
        // Get pinned product
        $ppStmt = $db->prepare(
            'SELECT sp.*, p.name, p.price, p.thumbnail_url
             FROM stream_products sp
             LEFT JOIN products p ON p.id = sp.product_id
             WHERE sp.stream_id = ? AND sp.is_pinned = 1
             LIMIT 1'
        );
        $ppStmt->execute([$id]);
        $stream['pinned_product'] = $ppStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        jsonOut(['success' => true, 'data' => $stream]);
        break;

    // ── start ─────────────────────────────────────────────────
    case 'start':
        requireAuth();
        if (!isSupplier()) {
            jsonOut(['success' => false, 'message' => 'Only suppliers can start streams.'], 403);
        }
        $title      = trim($_POST['title'] ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $category   = $_POST['category'] ?? 'general';
        $streamKey  = bin2hex(random_bytes(16));
        $streamerId = $_SESSION['user_id'];

        if (!$title) {
            jsonOut(['success' => false, 'message' => 'Stream title required.'], 400);
        }

        $stmt = $db->prepare(
            'INSERT INTO live_streams (streamer_id, title, description, category, stream_key, status, started_at)
             VALUES (?, ?, ?, ?, ?, "live", NOW())'
        );
        $stmt->execute([$streamerId, $title, $desc, $category, $streamKey]);
        $streamId = (int)$db->lastInsertId();

        jsonOut(['success' => true, 'stream_id' => $streamId, 'stream_key' => $streamKey]);
        break;

    // ── end ───────────────────────────────────────────────────
    case 'end':
        requireAuth();
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if (!$id) {
            jsonOut(['success' => false, 'message' => 'Stream ID required.'], 400);
        }
        $stmt = $db->prepare('SELECT * FROM live_streams WHERE id = ?');
        $stmt->execute([$id]);
        $stream = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$stream) {
            jsonOut(['success' => false, 'message' => 'Stream not found.'], 404);
        }
        if ((int)$stream['streamer_id'] !== (int)$_SESSION['user_id'] && !isAdmin()) {
            jsonOut(['success' => false, 'message' => 'Access denied.'], 403);
        }
        $startedAt = strtotime($stream['started_at'] ?? 'now');
        $duration  = time() - $startedAt;
        $db->prepare(
            'UPDATE live_streams SET status = "ended", ended_at = NOW(), duration_seconds = ? WHERE id = ?'
        )->execute([$duration, $id]);

        // Mark all active viewers as left
        $db->prepare('UPDATE stream_viewers SET is_active = 0, left_at = NOW() WHERE stream_id = ? AND is_active = 1')->execute([$id]);

        jsonOut(['success' => true, 'message' => 'Stream ended.', 'duration_seconds' => $duration]);
        break;

    // ── schedule ──────────────────────────────────────────────
    case 'schedule':
        requireAuth();
        if (!isSupplier()) {
            jsonOut(['success' => false, 'message' => 'Only suppliers can schedule streams.'], 403);
        }
        $title       = trim($_POST['title'] ?? '');
        $scheduledAt = $_POST['scheduled_at'] ?? '';
        if (!$title || !$scheduledAt) {
            jsonOut(['success' => false, 'message' => 'Title and scheduled_at required.'], 400);
        }
        $stmt = $db->prepare(
            'INSERT INTO live_streams (streamer_id, title, description, category, status, scheduled_at)
             VALUES (?, ?, ?, ?, "scheduled", ?)'
        );
        $stmt->execute([
            $_SESSION['user_id'],
            $title,
            $_POST['description'] ?? '',
            $_POST['category'] ?? 'general',
            $scheduledAt,
        ]);
        jsonOut(['success' => true, 'stream_id' => (int)$db->lastInsertId()]);
        break;

    // ── pin_product ───────────────────────────────────────────
    case 'pin_product':
        requireAuth();
        $streamId  = (int)($_POST['stream_id'] ?? 0);
        $productId = (int)($_POST['product_id'] ?? 0);
        if (!$streamId || !$productId) {
            jsonOut(['success' => false, 'message' => 'stream_id and product_id required.'], 400);
        }
        // Unpin all others first
        $db->prepare('UPDATE stream_products SET is_pinned = 0 WHERE stream_id = ?')->execute([$streamId]);
        // Upsert
        $stmt = $db->prepare('SELECT id FROM stream_products WHERE stream_id = ? AND product_id = ?');
        $stmt->execute([$streamId, $productId]);
        if ($existing = $stmt->fetchColumn()) {
            $db->prepare('UPDATE stream_products SET is_pinned = 1, pinned_at = NOW() WHERE id = ?')->execute([$existing]);
        } else {
            $db->prepare('INSERT INTO stream_products (stream_id, product_id, is_pinned, pinned_at) VALUES (?, ?, 1, NOW())')->execute([$streamId, $productId]);
        }
        jsonOut(['success' => true, 'message' => 'Product pinned.']);
        break;

    // ── unpin_product ─────────────────────────────────────────
    case 'unpin_product':
        requireAuth();
        $streamId = (int)($_POST['stream_id'] ?? 0);
        $db->prepare('UPDATE stream_products SET is_pinned = 0 WHERE stream_id = ?')->execute([$streamId]);
        jsonOut(['success' => true, 'message' => 'Product unpinned.']);
        break;

    // ── chat_history ──────────────────────────────────────────
    case 'chat_history':
        $streamId = (int)($_GET['stream_id'] ?? 0);
        if (!$streamId) {
            jsonOut(['success' => false, 'message' => 'stream_id required.'], 400);
        }
        $limit = min(200, max(1, (int)($_GET['limit'] ?? 50)));
        $stmt  = $db->prepare(
            'SELECT sc.id, sc.message, sc.type, sc.is_highlighted, sc.created_at,
                    u.first_name, u.last_name, u.avatar_url
             FROM stream_chat sc
             LEFT JOIN users u ON u.id = sc.user_id
             WHERE sc.stream_id = ? AND sc.is_deleted = 0
             ORDER BY sc.created_at ASC
             LIMIT ?'
        );
        $stmt->execute([$streamId, $limit]);
        jsonOut(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    // ── viewers ───────────────────────────────────────────────
    case 'viewers':
        $streamId = (int)($_GET['stream_id'] ?? 0);
        if (!$streamId) {
            jsonOut(['success' => false, 'message' => 'stream_id required.'], 400);
        }
        $count = $db->prepare('SELECT COUNT(*) FROM stream_viewers WHERE stream_id = ? AND is_active = 1');
        $count->execute([$streamId]);
        jsonOut(['success' => true, 'viewer_count' => (int)$count->fetchColumn()]);
        break;

    // ── upcoming ──────────────────────────────────────────────
    case 'upcoming':
        $stmt = $db->query(
            'SELECT ls.id, ls.title, ls.category, ls.thumbnail_url, ls.scheduled_at,
                    u.first_name, u.last_name
             FROM live_streams ls
             LEFT JOIN users u ON u.id = ls.streamer_id
             WHERE ls.status = "scheduled" AND ls.scheduled_at > NOW()
             ORDER BY ls.scheduled_at ASC
             LIMIT 20'
        );
        jsonOut(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    // ── vod_list ──────────────────────────────────────────────
    case 'vod_list':
        $pag = max(1, (int)($_GET['page'] ?? 1));
        $per = 12;
        $off = ($pag - 1) * $per;
        $stmt = $db->prepare(
            'SELECT ls.id, ls.title, ls.category, ls.thumbnail_url, ls.vod_url,
                    ls.duration_seconds, ls.peak_viewers, ls.ended_at,
                    u.first_name, u.last_name
             FROM live_streams ls
             LEFT JOIN users u ON u.id = ls.streamer_id
             WHERE ls.status = "ended" AND ls.is_vod_available = 1
             ORDER BY ls.ended_at DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->execute([$per, $off]);
        jsonOut(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    // ── follow_streamer ───────────────────────────────────────
    case 'follow_streamer':
        requireAuth();
        $streamerId = (int)($_POST['streamer_id'] ?? 0);
        if (!$streamerId) {
            jsonOut(['success' => false, 'message' => 'streamer_id required.'], 400);
        }
        if ($streamerId === (int)$_SESSION['user_id']) {
            jsonOut(['success' => false, 'message' => 'You cannot follow yourself.'], 400);
        }
        try {
            $db->prepare('INSERT IGNORE INTO streamer_followers (streamer_id, follower_id) VALUES (?, ?)')->execute([$streamerId, $_SESSION['user_id']]);
            jsonOut(['success' => true, 'message' => 'Following.']);
        } catch (PDOException $e) {
            jsonOut(['success' => false, 'message' => 'Could not follow.'], 500);
        }
        break;

    // ── unfollow_streamer ─────────────────────────────────────
    case 'unfollow_streamer':
        requireAuth();
        $streamerId = (int)($_POST['streamer_id'] ?? 0);
        $db->prepare('DELETE FROM streamer_followers WHERE streamer_id = ? AND follower_id = ?')->execute([$streamerId, $_SESSION['user_id']]);
        jsonOut(['success' => true, 'message' => 'Unfollowed.']);
        break;

    // ── react ─────────────────────────────────────────────────
    case 'react':
        requireAuth();
        $streamId = (int)($_POST['stream_id'] ?? 0);
        $reaction = $_POST['reaction'] ?? '❤️';
        if (!$streamId) {
            jsonOut(['success' => false, 'message' => 'stream_id required.'], 400);
        }
        $db->prepare(
            'INSERT INTO stream_chat (stream_id, user_id, message, type) VALUES (?, ?, ?, "reaction")'
        )->execute([$streamId, $_SESSION['user_id'], $reaction]);
        $db->prepare('UPDATE live_streams SET total_reactions = total_reactions + 1 WHERE id = ?')->execute([$streamId]);
        jsonOut(['success' => true]);
        break;

    // ── report ────────────────────────────────────────────────
    case 'report':
        requireAuth();
        $streamId = (int)($_POST['stream_id'] ?? 0);
        $reason   = trim($_POST['reason'] ?? 'inappropriate');
        if (!$streamId) {
            jsonOut(['success' => false, 'message' => 'stream_id required.'], 400);
        }
        // Log report as a system chat message
        $db->prepare(
            'INSERT INTO stream_chat (stream_id, user_id, message, type) VALUES (?, ?, ?, "system")'
        )->execute([$streamId, $_SESSION['user_id'], 'REPORT: ' . $reason]);
        jsonOut(['success' => true, 'message' => 'Report submitted.']);
        break;

    default:
        jsonOut(['success' => false, 'message' => 'Unknown action.'], 400);
}
