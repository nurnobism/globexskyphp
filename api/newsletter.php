<?php
require_once __DIR__ . '/../includes/middleware.php';
header('Content-Type: application/json');
$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOut($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

switch ($action) {
    case 'subscribe':
        $email = sanitize($_POST['email'] ?? '');
        $name  = sanitize($_POST['name'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonOut(['success' => false, 'message' => 'Invalid email address'], 400);
        }
        $stmt = $db->prepare(
            'INSERT INTO newsletter_subscribers (email, name, status) VALUES (?, ?, \'active\')
             ON DUPLICATE KEY UPDATE status = \'active\', name = VALUES(name)'
        );
        $stmt->execute([$email, $name]);
        jsonOut(['success' => true, 'message' => 'Subscribed successfully']);
        break;

    case 'unsubscribe':
        $email = sanitize($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonOut(['success' => false, 'message' => 'Invalid email address'], 400);
        }
        $stmt = $db->prepare('UPDATE newsletter_subscribers SET status = \'unsubscribed\' WHERE email = ?');
        $stmt->execute([$email]);
        jsonOut(['success' => true, 'message' => 'Unsubscribed successfully']);
        break;

    case 'list':
        requireRole('admin');
        $newsletters = $db->query('SELECT * FROM newsletters ORDER BY created_at DESC')->fetchAll();
        $countStmt = $db->query('SELECT COUNT(*) FROM newsletter_subscribers WHERE status = \'active\'');
        $subscriberCount = (int) $countStmt->fetchColumn();
        jsonOut(['success' => true, 'data' => $newsletters, 'subscriber_count' => $subscriberCount]);
        break;

    case 'create':
        requireRole('admin');
        validateCsrf();
        $title        = sanitize($_POST['title'] ?? '');
        $subject      = sanitize($_POST['subject'] ?? '');
        $content      = $_POST['content'] ?? '';
        $scheduledAt  = sanitize($_POST['scheduled_at'] ?? '');
        if (!$title || !$subject || !$content) {
            jsonOut(['success' => false, 'message' => 'title, subject, and content are required'], 400);
        }
        $stmt = $db->prepare(
            'INSERT INTO newsletters (title, subject, content, scheduled_at, status, created_at)
             VALUES (?, ?, ?, ?, \'draft\', NOW())'
        );
        $stmt->execute([$title, $subject, $content, $scheduledAt ?: null]);
        jsonOut(['success' => true, 'id' => $db->lastInsertId()]);
        break;

    case 'send':
        requireRole('admin');
        validateCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            jsonOut(['success' => false, 'message' => 'Newsletter ID required'], 400);
        }
        $stmt = $db->prepare('UPDATE newsletters SET status = \'sent\', sent_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->rowCount()) {
            jsonOut(['success' => false, 'message' => 'Newsletter not found'], 404);
        }
        jsonOut(['success' => true, 'message' => 'Newsletter marked as sent']);
        break;

    case 'archive':
        requireRole('admin');
        $stmt = $db->query('SELECT * FROM newsletters WHERE status = \'sent\' ORDER BY sent_at DESC');
        jsonOut(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    default:
        jsonOut(['success' => false, 'message' => 'Invalid action'], 400);
}
