<?php
require_once __DIR__ . '/../includes/middleware.php';
header('Content-Type: application/json');
$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOut($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

switch ($action) {
    case 'list_tickets':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $page   = (int) get('page', 1);
        $result = paginate(
            $db,
            'SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC',
            [$userId],
            $page
        );
        jsonOut(['success' => true, 'data' => $result]);
        break;

    case 'get_ticket':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $id     = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            jsonOut(['success' => false, 'message' => 'Ticket ID is required'], 400);
        }
        $stmt = $db->prepare('SELECT * FROM support_tickets WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $ticket = $stmt->fetch();
        if (!$ticket) {
            jsonOut(['success' => false, 'message' => 'Ticket not found'], 404);
        }
        $replyStmt = $db->prepare(
            'SELECT sr.*, u.name AS author_name
             FROM support_replies sr
             JOIN users u ON u.id = sr.user_id
             WHERE sr.ticket_id = ?
             ORDER BY sr.created_at ASC'
        );
        $replyStmt->execute([$id]);
        $ticket['replies'] = $replyStmt->fetchAll();
        jsonOut(['success' => true, 'data' => $ticket]);
        break;

    case 'create_ticket':
        requireAuth();
        validateCsrf();
        $userId      = $_SESSION['user_id'];
        $subject     = sanitize($_POST['subject'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $category    = sanitize($_POST['category'] ?? 'general');
        $priority    = sanitize($_POST['priority'] ?? 'normal');
        if (!$subject || !$description) {
            jsonOut(['success' => false, 'message' => 'subject and description are required'], 400);
        }
        $validPriorities = ['low', 'normal', 'high', 'urgent'];
        if (!in_array($priority, $validPriorities, true)) {
            $priority = 'normal';
        }
        $stmt = $db->prepare(
            'INSERT INTO support_tickets (user_id, subject, description, category, priority, status, created_at)
             VALUES (?, ?, ?, ?, ?, \'open\', NOW())'
        );
        $stmt->execute([$userId, $subject, $description, $category, $priority]);
        jsonOut(['success' => true, 'id' => $db->lastInsertId()]);
        break;

    case 'reply_ticket':
        requireAuth();
        validateCsrf();
        $userId   = $_SESSION['user_id'];
        $ticketId = (int) ($_POST['ticket_id'] ?? 0);
        $content  = sanitize($_POST['content'] ?? '');
        if (!$ticketId || !$content) {
            jsonOut(['success' => false, 'message' => 'ticket_id and content are required'], 400);
        }
        $ticketStmt = $db->prepare('SELECT * FROM support_tickets WHERE id = ?');
        $ticketStmt->execute([$ticketId]);
        $ticket = $ticketStmt->fetch();
        if (!$ticket) {
            jsonOut(['success' => false, 'message' => 'Ticket not found'], 404);
        }
        if ((int) $ticket['user_id'] !== (int) $userId && !isLoggedIn()) {
            jsonOut(['success' => false, 'message' => 'Access denied'], 403);
        }
        $replyStmt = $db->prepare(
            'INSERT INTO support_replies (ticket_id, user_id, content, created_at)
             VALUES (?, ?, ?, NOW())'
        );
        $replyStmt->execute([$ticketId, $userId, $content]);
        $replyId = $db->lastInsertId();
        $newStatus = $ticket['status'];
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            $newStatus = 'answered';
        } elseif ($ticket['status'] === 'answered') {
            $newStatus = 'open';
        }
        $db->prepare(
            'UPDATE support_tickets SET updated_at = NOW(), status = ? WHERE id = ?'
        )->execute([$newStatus, $ticketId]);
        jsonOut(['success' => true, 'id' => $replyId]);
        break;

    case 'close_ticket':
        requireAuth();
        validateCsrf();
        $userId = $_SESSION['user_id'];
        $id     = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            jsonOut(['success' => false, 'message' => 'Ticket ID is required'], 400);
        }
        $ticketStmt = $db->prepare('SELECT * FROM support_tickets WHERE id = ?');
        $ticketStmt->execute([$id]);
        $ticket = $ticketStmt->fetch();
        if (!$ticket) {
            jsonOut(['success' => false, 'message' => 'Ticket not found'], 404);
        }
        $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
        if ((int) $ticket['user_id'] !== (int) $userId && !$isAdmin) {
            jsonOut(['success' => false, 'message' => 'Access denied'], 403);
        }
        $db->prepare(
            'UPDATE support_tickets SET status = \'closed\', updated_at = NOW() WHERE id = ?'
        )->execute([$id]);
        jsonOut(['success' => true, 'message' => 'Ticket closed']);
        break;

    case 'list_faqs':
        $stmt = $db->query(
            'SELECT * FROM faqs WHERE published = 1 ORDER BY category ASC, sort_order ASC'
        );
        jsonOut(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'get_faq':
        $id       = (int) ($_GET['id'] ?? 0);
        $category = sanitize($_GET['category'] ?? '');
        if ($id) {
            $stmt = $db->prepare('SELECT * FROM faqs WHERE id = ? AND published = 1');
            $stmt->execute([$id]);
            $faq = $stmt->fetch();
            if (!$faq) {
                jsonOut(['success' => false, 'message' => 'FAQ not found'], 404);
            }
            jsonOut(['success' => true, 'data' => $faq]);
        } elseif ($category) {
            $stmt = $db->prepare(
                'SELECT * FROM faqs WHERE category = ? AND published = 1 ORDER BY sort_order ASC'
            );
            $stmt->execute([$category]);
            jsonOut(['success' => true, 'data' => $stmt->fetchAll()]);
        } else {
            jsonOut(['success' => false, 'message' => 'id or category is required'], 400);
        }
        break;

    case 'create_faq':
        requireRole('admin');
        validateCsrf();
        $question  = sanitize($_POST['question'] ?? '');
        $answer    = sanitize($_POST['answer'] ?? '');
        $category  = sanitize($_POST['category'] ?? 'general');
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);
        if (!$question || !$answer) {
            jsonOut(['success' => false, 'message' => 'question and answer are required'], 400);
        }
        $stmt = $db->prepare(
            'INSERT INTO faqs (question, answer, category, sort_order, published, created_at)
             VALUES (?, ?, ?, ?, 1, NOW())'
        );
        $stmt->execute([$question, $answer, $category, $sortOrder]);
        jsonOut(['success' => true, 'id' => $db->lastInsertId()]);
        break;

    default:
        jsonOut(['success' => false, 'message' => 'Invalid action'], 400);
}
