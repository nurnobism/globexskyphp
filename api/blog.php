<?php
require_once __DIR__ . '/../includes/middleware.php';
header('Content-Type: application/json');
$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOut($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

switch ($action) {
    case 'list':
        $page     = (int)($_GET['page'] ?? 1);
        $category = sanitize($_GET['category'] ?? '');
        $params   = [];
        $sql = 'SELECT id, title, excerpt, author_id, category, status, created_at, featured_image
                FROM blog_posts WHERE status = \'published\'';
        if ($category !== '') {
            $sql .= ' AND category = ?';
            $params[] = $category;
        }
        $sql .= ' ORDER BY created_at DESC';
        $result = paginate($db, $sql, $params, $page);
        jsonOut(['success' => true, 'data' => $result]);
    break;

    case 'detail':
        $id   = (int)($_GET['id'] ?? 0);
        $slug = sanitize($_GET['slug'] ?? '');
        if ($id > 0) {
            $stmt = $db->prepare('SELECT * FROM blog_posts WHERE id = ?');
            $stmt->execute([$id]);
        } elseif ($slug !== '') {
            $stmt = $db->prepare('SELECT * FROM blog_posts WHERE slug = ?');
            $stmt->execute([$slug]);
        } else {
            jsonOut(['success' => false, 'message' => 'id or slug required'], 400);
        }
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$post) jsonOut(['success' => false, 'message' => 'Post not found'], 404);
        jsonOut(['success' => true, 'data' => $post]);
    break;

    case 'create':
        requireAuth();
        validateCsrf();
        $title    = sanitize($_POST['title'] ?? '');
        $content  = sanitize($_POST['content'] ?? '');
        $excerpt  = sanitize($_POST['excerpt'] ?? '');
        $category = sanitize($_POST['category'] ?? '');
        $tags     = sanitize($_POST['tags'] ?? '');
        $status   = sanitize($_POST['status'] ?? 'draft');
        if ($title === '' || $content === '') {
            jsonOut(['success' => false, 'message' => 'title and content are required'], 400);
        }
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $title), '-'));
        $slug .= '-' . time();
        $user = getCurrentUser();
        $stmt = $db->prepare(
            'INSERT INTO blog_posts (title, slug, content, excerpt, author_id, category, tags, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$title, $slug, $content, $excerpt, $user['id'], $category, $tags, $status]);
        $newId = $db->lastInsertId();
        jsonOut(['success' => true, 'message' => 'Post created', 'id' => $newId], 201);
    break;

    case 'update':
        requireAuth();
        validateCsrf();
        $id       = (int)($_POST['id'] ?? 0);
        $title    = sanitize($_POST['title'] ?? '');
        $content  = sanitize($_POST['content'] ?? '');
        $excerpt  = sanitize($_POST['excerpt'] ?? '');
        $category = sanitize($_POST['category'] ?? '');
        $tags     = sanitize($_POST['tags'] ?? '');
        $status   = sanitize($_POST['status'] ?? '');
        if ($id <= 0) jsonOut(['success' => false, 'message' => 'Invalid id'], 400);
        $user  = getCurrentUser();
        $check = $db->prepare('SELECT id, author_id FROM blog_posts WHERE id = ?');
        $check->execute([$id]);
        $post  = $check->fetch(PDO::FETCH_ASSOC);
        if (!$post) jsonOut(['success' => false, 'message' => 'Post not found'], 404);
        if ((int)$post['author_id'] !== (int)$user['id'] && ($user['role'] ?? '') !== 'admin') {
            jsonOut(['success' => false, 'message' => 'Forbidden'], 403);
        }
        $stmt = $db->prepare(
            'UPDATE blog_posts SET title=?, content=?, excerpt=?, category=?, tags=?, status=?, updated_at=NOW() WHERE id=?'
        );
        $stmt->execute([$title, $content, $excerpt, $category, $tags, $status, $id]);
        jsonOut(['success' => true, 'message' => 'Post updated']);
    break;

    case 'delete':
        requireAuth();
        validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) jsonOut(['success' => false, 'message' => 'Invalid id'], 400);
        $user  = getCurrentUser();
        $check = $db->prepare('SELECT id, author_id FROM blog_posts WHERE id = ?');
        $check->execute([$id]);
        $post  = $check->fetch(PDO::FETCH_ASSOC);
        if (!$post) jsonOut(['success' => false, 'message' => 'Post not found'], 404);
        if ((int)$post['author_id'] !== (int)$user['id'] && ($user['role'] ?? '') !== 'admin') {
            jsonOut(['success' => false, 'message' => 'Forbidden'], 403);
        }
        $db->prepare('DELETE FROM blog_comments WHERE post_id = ?')->execute([$id]);
        $db->prepare('DELETE FROM blog_posts WHERE id = ?')->execute([$id]);
        jsonOut(['success' => true, 'message' => 'Post deleted']);
    break;

    case 'add_comment':
        requireAuth();
        validateCsrf();
        $post_id = (int)($_POST['post_id'] ?? 0);
        $content = sanitize($_POST['content'] ?? '');
        if ($post_id <= 0 || $content === '') {
            jsonOut(['success' => false, 'message' => 'post_id and content are required'], 400);
        }
        $check = $db->prepare('SELECT id FROM blog_posts WHERE id = ? AND status = \'published\'');
        $check->execute([$post_id]);
        if (!$check->fetch()) jsonOut(['success' => false, 'message' => 'Post not found'], 404);
        $user = getCurrentUser();
        $stmt = $db->prepare(
            'INSERT INTO blog_comments (post_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$post_id, $user['id'], $content]);
        $newId = $db->lastInsertId();
        jsonOut(['success' => true, 'message' => 'Comment added', 'id' => $newId], 201);
    break;

    case 'list_comments':
        $post_id = (int)($_GET['post_id'] ?? 0);
        if ($post_id <= 0) jsonOut(['success' => false, 'message' => 'post_id required'], 400);
        $page   = (int)($_GET['page'] ?? 1);
        $sql    = 'SELECT bc.id, bc.content, bc.created_at, bc.user_id
                   FROM blog_comments bc WHERE bc.post_id = ? ORDER BY bc.created_at ASC';
        $result = paginate($db, $sql, [$post_id], $page);
        jsonOut(['success' => true, 'data' => $result]);
    break;

    default:
        jsonOut(['success' => false, 'message' => 'Invalid action'], 400);
}
