<?php
/**
 * api/cms.php — CMS/Blog & Newsletter API
 */

require_once __DIR__ . '/../includes/middleware.php';

$action = $_GET['action'] ?? 'posts';
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

switch ($action) {

    case 'posts':
        $page = max(1, (int)get('page', 1));
        $sql  = 'SELECT bp.*, u.first_name, u.last_name FROM blog_posts bp
                 JOIN users u ON u.id=bp.author_id
                 WHERE bp.status="published" ORDER BY bp.published_at DESC';
        jsonResponse(paginate($db, $sql, [], $page, 10));
        break;

    case 'post':
        $slug = get('slug', '');
        $id   = (int)get('id', 0);
        if (!$slug && !$id) jsonResponse(['error' => 'Slug or ID required'], 400);

        $stmt = $db->prepare('SELECT bp.*, u.first_name, u.last_name FROM blog_posts bp
            JOIN users u ON u.id=bp.author_id
            WHERE ' . ($id ? 'bp.id=?' : 'bp.slug=?') . ' AND bp.status="published"');
        $stmt->execute([$id ?: $slug]);
        $post = $stmt->fetch();
        if (!$post) jsonResponse(['error' => 'Post not found'], 404);
        $db->prepare('UPDATE blog_posts SET view_count=view_count+1 WHERE id=?')->execute([$post['id']]);
        jsonResponse(['data' => $post]);
        break;

    case 'newsletter_subscribe':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);

        $email = trim(post('email', ''));
        if (!$email || !isValidEmail($email)) {
            if (isset($_POST['_redirect'])) { flashMessage('danger', 'Valid email required.'); redirect($_POST['_redirect'] ?? '/'); }
            jsonResponse(['error' => 'Valid email required'], 422);
        }
        $stmt = $db->prepare('INSERT IGNORE INTO newsletter_subscribers (email) VALUES (?)');
        $stmt->execute([$email]);
        if (isset($_POST['_redirect'])) { flashMessage('success', 'Subscribed successfully!'); redirect($_POST['_redirect'] ?? '/'); }
        jsonResponse(['success' => true]);
        break;

    case 'contact':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);

        $name    = trim(post('name', ''));
        $email   = trim(post('email', ''));
        $subject = trim(post('subject', ''));
        $message = trim(post('message', ''));
        $phone   = trim(post('phone', ''));

        if (!$name || !$email || !$message) {
            if (isset($_POST['_redirect'])) { flashMessage('danger', 'Name, email, and message are required.'); redirect($_POST['_redirect']); }
            jsonResponse(['error' => 'Name, email, and message are required.'], 422);
        }
        if (!isValidEmail($email)) {
            if (isset($_POST['_redirect'])) { flashMessage('danger', 'Valid email required.'); redirect($_POST['_redirect']); }
            jsonResponse(['error' => 'Valid email required'], 422);
        }

        $db->prepare('INSERT INTO contact_inquiries (name, email, phone, subject, message) VALUES (?,?,?,?,?)')
           ->execute([$name, $email, $phone, $subject, $message]);

        // Send notification email to admin
        $html = "<p>New contact inquiry from <strong>$name</strong> ($email).</p><p><strong>Subject:</strong> $subject</p><p><strong>Message:</strong><br>" . nl2br(e($message)) . "</p>";
        sendMail(MAIL_FROM_EMAIL, 'New Contact Inquiry: ' . $subject, $html);

        if (isset($_POST['_redirect'])) { flashMessage('success', 'Message sent! We will get back to you soon.'); redirect($_POST['_redirect']); }
        jsonResponse(['success' => true]);
        break;

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}
