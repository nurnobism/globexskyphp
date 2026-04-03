<?php
require_once __DIR__ . '/../includes/middleware.php';
header('Content-Type: application/json');
$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOut($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }
function validateCsrf(): void { if (!verifyCsrf()) { jsonOut(['error' => 'Invalid CSRF token'], 403); } }

/** Paginated query helper: returns ['data','total','pages','current'] */
function wmPaginate(PDO $db, string $sql, array $params, int $page, int $perPage = 20): array {
    $countSql = 'SELECT COUNT(*) FROM (' . $sql . ') AS _c';
    $cStmt = $db->prepare($countSql);
    $cStmt->execute($params);
    $total  = (int) $cStmt->fetchColumn();
    $pages  = max(1, (int) ceil($total / $perPage));
    $offset = ($page - 1) * $perPage;
    $dStmt  = $db->prepare($sql . ' LIMIT ' . $perPage . ' OFFSET ' . $offset);
    $dStmt->execute($params);
    return ['data' => $dStmt->fetchAll(), 'total' => $total, 'pages' => $pages, 'current' => $page];
}

/** Verify a webmail message belongs to the given user (as recipient or sender) */
function wmVerifyAccess(PDO $db, int $messageId, int $userId): bool {
    $stmt = $db->prepare(
        'SELECT 1 FROM webmail_messages wm
         LEFT JOIN webmail_recipients wr ON wr.message_id = wm.id AND wr.user_id = ?
         WHERE wm.id = ? AND (wm.sender_id = ? OR wr.user_id = ?)'
    );
    $stmt->execute([$userId, $messageId, $userId, $userId]);
    return (bool) $stmt->fetchColumn();
}

switch ($action) {

    // ── INBOX ─────────────────────────────────────────────────────────────────
    case 'inbox':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $sql = 'SELECT wm.id, wm.subject, wm.body, wm.priority, wm.created_at,
                       wr.is_read, wr.label,
                       wm.sender_id,
                       CONCAT(u.first_name, " ", u.last_name) AS sender_name,
                       u.avatar AS sender_avatar,
                       (SELECT COUNT(*) FROM webmail_attachments wa WHERE wa.message_id = wm.id) AS attachment_count
                FROM webmail_recipients wr
                JOIN webmail_messages wm ON wm.id = wr.message_id
                JOIN users u ON u.id = wm.sender_id
                WHERE wr.user_id = ? AND wr.is_trashed = 0 AND wr.is_deleted = 0
                  AND wr.type = "to" AND wm.is_draft = 0
                ORDER BY wm.created_at DESC';
        jsonOut(['success' => true] + wmPaginate($db, $sql, [$userId], $page));
        break;

    // ── SENT ─────────────────────────────────────────────────────────────────
    case 'sent':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $sql = 'SELECT wm.*,
                       (SELECT COUNT(*) FROM webmail_attachments wa WHERE wa.message_id = wm.id) AS attachment_count
                FROM webmail_messages wm
                WHERE wm.sender_id = ? AND wm.is_draft = 0
                ORDER BY wm.created_at DESC';
        jsonOut(['success' => true] + wmPaginate($db, $sql, [$userId], $page));
        break;

    // ── DRAFTS ───────────────────────────────────────────────────────────────
    case 'drafts':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $sql = 'SELECT wm.*
                FROM webmail_messages wm
                WHERE wm.sender_id = ? AND wm.is_draft = 1
                ORDER BY wm.updated_at DESC';
        jsonOut(['success' => true] + wmPaginate($db, $sql, [$userId], $page));
        break;

    // ── TRASH ────────────────────────────────────────────────────────────────
    case 'trash':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $sql = 'SELECT wm.id, wm.subject, wm.body, wm.priority, wm.created_at,
                       wr.is_read, wr.trashed_at,
                       CONCAT(u.first_name, " ", u.last_name) AS sender_name,
                       u.avatar AS sender_avatar
                FROM webmail_recipients wr
                JOIN webmail_messages wm ON wm.id = wr.message_id
                JOIN users u ON u.id = wm.sender_id
                WHERE wr.user_id = ? AND wr.is_trashed = 1
                ORDER BY wr.trashed_at DESC';
        jsonOut(['success' => true] + wmPaginate($db, $sql, [$userId], $page));
        break;

    // ── READ MESSAGE ──────────────────────────────────────────────────────────
    case 'read':
        requireAuth();
        $userId    = $_SESSION['user_id'];
        $messageId = (int) ($_GET['id'] ?? 0);

        if (!$messageId) {
            jsonOut(['success' => false, 'message' => 'Message ID required'], 400);
        }
        if (!wmVerifyAccess($db, $messageId, $userId)) {
            jsonOut(['success' => false, 'message' => 'Message not found'], 404);
        }

        // Fetch main message
        $stmt = $db->prepare(
            'SELECT wm.*,
                    CONCAT(u.first_name, " ", u.last_name) AS sender_name,
                    u.avatar AS sender_avatar,
                    u.email AS sender_email
             FROM webmail_messages wm
             JOIN users u ON u.id = wm.sender_id
             WHERE wm.id = ?'
        );
        $stmt->execute([$messageId]);
        $message = $stmt->fetch();

        if (!$message) {
            jsonOut(['success' => false, 'message' => 'Message not found'], 404);
        }

        // Fetch thread (other messages in same thread)
        $threadId = $message['thread_id'] ?: $messageId;
        $threadStmt = $db->prepare(
            'SELECT wm.*,
                    CONCAT(u.first_name, " ", u.last_name) AS sender_name,
                    u.avatar AS sender_avatar
             FROM webmail_messages wm
             JOIN users u ON u.id = wm.sender_id
             WHERE wm.thread_id = ? AND wm.id != ? AND wm.is_draft = 0
             ORDER BY wm.created_at ASC'
        );
        $threadStmt->execute([$threadId, $messageId]);
        $message['thread'] = $threadStmt->fetchAll();

        // Fetch attachments
        $attStmt = $db->prepare(
            'SELECT * FROM webmail_attachments WHERE message_id = ?'
        );
        $attStmt->execute([$messageId]);
        $message['attachments'] = $attStmt->fetchAll();

        // Fetch recipients
        $recpStmt = $db->prepare(
            'SELECT wr.type, wr.is_read, wr.read_at,
                    u.id AS user_id,
                    CONCAT(u.first_name, " ", u.last_name) AS name,
                    u.email, u.avatar
             FROM webmail_recipients wr
             JOIN users u ON u.id = wr.user_id
             WHERE wr.message_id = ?'
        );
        $recpStmt->execute([$messageId]);
        $message['recipients'] = $recpStmt->fetchAll();

        // Mark as read for current user
        $db->prepare(
            'UPDATE webmail_recipients SET is_read = 1, read_at = NOW()
             WHERE message_id = ? AND user_id = ? AND is_read = 0'
        )->execute([$messageId, $userId]);

        jsonOut(['success' => true, 'data' => $message]);
        break;

    // ── COMPOSE ───────────────────────────────────────────────────────────────
    case 'compose':
        requireAuth();
        validateCsrf();
        $userId   = $_SESSION['user_id'];
        $to       = $_POST['to'] ?? '';
        $subject  = trim($_POST['subject'] ?? '');
        $body     = trim($_POST['body'] ?? '');
        $bodyHtml = trim($_POST['body_html'] ?? '');
        $priority = $_POST['priority'] ?? 'normal';
        $isDraft  = isset($_POST['is_draft']) ? (int) (bool) $_POST['is_draft'] : 0;
        $cc       = $_POST['cc'] ?? [];
        $bcc      = $_POST['bcc'] ?? [];

        if (!$isDraft && empty($subject)) {
            jsonOut(['success' => false, 'message' => 'Subject is required'], 400);
        }
        if (!$isDraft && empty($body)) {
            jsonOut(['success' => false, 'message' => 'Body is required'], 400);
        }
        if (!in_array($priority, ['normal', 'high', 'urgent'])) {
            $priority = 'normal';
        }

        // Resolve recipient user IDs
        $toIds = [];
        if (!empty($to)) {
            $toList = is_array($to) ? $to : [$to];
            foreach ($toList as $recipient) {
                $recipient = trim($recipient);
                if (filter_var($recipient, FILTER_VALIDATE_INT)) {
                    $toIds[] = (int) $recipient;
                } elseif (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    $uStmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                    $uStmt->execute([$recipient]);
                    $uid = $uStmt->fetchColumn();
                    if ($uid) $toIds[] = (int) $uid;
                }
            }
        }

        if (!$isDraft && empty($toIds)) {
            jsonOut(['success' => false, 'message' => 'At least one valid recipient required'], 400);
        }

        $db->beginTransaction();
        try {
            $msgStmt = $db->prepare(
                'INSERT INTO webmail_messages (sender_id, subject, body, body_html, priority, is_draft)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $msgStmt->execute([$userId, $subject, $body, $bodyHtml ?: null, $priority, $isDraft]);
            $msgId = (int) $db->lastInsertId();

            // Set thread_id to the message itself for new threads
            $db->prepare('UPDATE webmail_messages SET thread_id = ? WHERE id = ?')
               ->execute([$msgId, $msgId]);

            if (!$isDraft) {
                $recpStmt = $db->prepare(
                    'INSERT INTO webmail_recipients (message_id, user_id, type) VALUES (?, ?, ?)'
                );
                foreach ($toIds as $rid) {
                    $recpStmt->execute([$msgId, $rid, 'to']);
                }
                // CC
                if (!empty($cc)) {
                    $ccList = is_array($cc) ? $cc : explode(',', $cc);
                    foreach ($ccList as $ccId) {
                        $ccId = (int) trim($ccId);
                        if ($ccId) $recpStmt->execute([$msgId, $ccId, 'cc']);
                    }
                }
                // BCC
                if (!empty($bcc)) {
                    $bccList = is_array($bcc) ? $bcc : explode(',', $bcc);
                    foreach ($bccList as $bccId) {
                        $bccId = (int) trim($bccId);
                        if ($bccId) $recpStmt->execute([$msgId, $bccId, 'bcc']);
                    }
                }

                // Create in-app notifications for recipients
                $notifStmt = $db->prepare(
                    'INSERT INTO notifications (user_id, type, title, message, action_url)
                     VALUES (?, "new_message", ?, ?, ?)'
                );
                $senderName = $_SESSION['user_name'] ?? 'Someone';
                foreach ($toIds as $rid) {
                    $notifStmt->execute([
                        $rid,
                        'New message from ' . $senderName,
                        $subject,
                        '/pages/webmail/inbox.php?id=' . $msgId,
                    ]);
                }
            }

            $db->commit();
            jsonOut(['success' => true, 'message_id' => $msgId, 'message' => $isDraft ? 'Draft saved' : 'Message sent']);
        } catch (Exception $e) {
            $db->rollBack();
            jsonOut(['success' => false, 'message' => 'Failed to send message'], 500);
        }
        break;

    // ── REPLY ─────────────────────────────────────────────────────────────────
    case 'reply':
        requireAuth();
        validateCsrf();
        $userId          = $_SESSION['user_id'];
        $parentMessageId = (int) ($_POST['parent_message_id'] ?? 0);
        $body            = trim($_POST['body'] ?? '');
        $bodyHtml        = trim($_POST['body_html'] ?? '');
        $priority        = $_POST['priority'] ?? 'normal';

        if (!$parentMessageId) {
            jsonOut(['success' => false, 'message' => 'parent_message_id required'], 400);
        }
        if (empty($body)) {
            jsonOut(['success' => false, 'message' => 'Body is required'], 400);
        }
        if (!wmVerifyAccess($db, $parentMessageId, $userId)) {
            jsonOut(['success' => false, 'message' => 'Parent message not found'], 404);
        }

        $parentStmt = $db->prepare('SELECT * FROM webmail_messages WHERE id = ?');
        $parentStmt->execute([$parentMessageId]);
        $parent = $parentStmt->fetch();

        if (!$parent) {
            jsonOut(['success' => false, 'message' => 'Parent message not found'], 404);
        }

        $threadId = $parent['thread_id'] ?: $parentMessageId;
        $subject  = (strncasecmp($parent['subject'], 'Re: ', 4) === 0)
                    ? $parent['subject']
                    : 'Re: ' . $parent['subject'];

        $db->beginTransaction();
        try {
            $msgStmt = $db->prepare(
                'INSERT INTO webmail_messages (sender_id, thread_id, subject, body, body_html, priority, parent_message_id, is_draft)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 0)'
            );
            $msgStmt->execute([$userId, $threadId, $subject, $body, $bodyHtml ?: null, $priority, $parentMessageId]);
            $msgId = (int) $db->lastInsertId();

            // Send reply to the original sender
            $replyToId = (int) $parent['sender_id'];
            if ($replyToId !== $userId) {
                $db->prepare(
                    'INSERT INTO webmail_recipients (message_id, user_id, type) VALUES (?, ?, "to")'
                )->execute([$msgId, $replyToId]);

                $senderName = $_SESSION['user_name'] ?? 'Someone';
                $db->prepare(
                    'INSERT INTO notifications (user_id, type, title, message, action_url)
                     VALUES (?, "new_message", ?, ?, ?)'
                )->execute([
                    $replyToId,
                    'Reply from ' . $senderName,
                    $subject,
                    '/pages/webmail/inbox.php?id=' . $msgId,
                ]);
            }

            $db->commit();
            jsonOut(['success' => true, 'message_id' => $msgId, 'message' => 'Reply sent']);
        } catch (Exception $e) {
            $db->rollBack();
            jsonOut(['success' => false, 'message' => 'Failed to send reply'], 500);
        }
        break;

    // ── SAVE DRAFT ───────────────────────────────────────────────────────────
    case 'save_draft':
        requireAuth();
        validateCsrf();
        $userId    = $_SESSION['user_id'];
        $draftId   = (int) ($_POST['id'] ?? 0);
        $subject   = trim($_POST['subject'] ?? '');
        $body      = trim($_POST['body'] ?? '');
        $bodyHtml  = trim($_POST['body_html'] ?? '');
        $priority  = $_POST['priority'] ?? 'normal';

        if (!in_array($priority, ['normal', 'high', 'urgent'])) {
            $priority = 'normal';
        }

        if ($draftId) {
            // Verify ownership
            $check = $db->prepare(
                'SELECT id FROM webmail_messages WHERE id = ? AND sender_id = ? AND is_draft = 1'
            );
            $check->execute([$draftId, $userId]);
            if (!$check->fetch()) {
                jsonOut(['success' => false, 'message' => 'Draft not found'], 404);
            }
            $db->prepare(
                'UPDATE webmail_messages SET subject = ?, body = ?, body_html = ?, priority = ?
                 WHERE id = ?'
            )->execute([$subject, $body, $bodyHtml ?: null, $priority, $draftId]);
            jsonOut(['success' => true, 'message_id' => $draftId, 'message' => 'Draft updated']);
        } else {
            $msgStmt = $db->prepare(
                'INSERT INTO webmail_messages (sender_id, subject, body, body_html, priority, is_draft)
                 VALUES (?, ?, ?, ?, ?, 1)'
            );
            $msgStmt->execute([$userId, $subject, $body, $bodyHtml ?: null, $priority]);
            $draftId = (int) $db->lastInsertId();
            $db->prepare('UPDATE webmail_messages SET thread_id = ? WHERE id = ?')
               ->execute([$draftId, $draftId]);
            jsonOut(['success' => true, 'message_id' => $draftId, 'message' => 'Draft saved']);
        }
        break;

    // ── DELETE (MOVE TO TRASH) ────────────────────────────────────────────────
    case 'delete':
        requireAuth();
        validateCsrf();
        $userId    = $_SESSION['user_id'];
        $messageId = (int) ($_POST['id'] ?? 0);

        if (!$messageId) {
            jsonOut(['success' => false, 'message' => 'Message ID required'], 400);
        }

        // Move to trash for recipient
        $affected = $db->prepare(
            'UPDATE webmail_recipients SET is_trashed = 1, trashed_at = NOW()
             WHERE message_id = ? AND user_id = ? AND is_trashed = 0'
        );
        $affected->execute([$messageId, $userId]);

        // Also soft-delete drafts owned by user
        if (!$affected->rowCount()) {
            $db->prepare(
                'UPDATE webmail_messages SET is_draft = 0
                 WHERE id = ? AND sender_id = ? AND is_draft = 1'
            )->execute([$messageId, $userId]);
        }

        jsonOut(['success' => true, 'message' => 'Moved to trash']);
        break;

    // ── PERMANENT DELETE ─────────────────────────────────────────────────────
    case 'permanent_delete':
        requireAuth();
        validateCsrf();
        $userId    = $_SESSION['user_id'];
        $messageId = (int) ($_POST['id'] ?? 0);

        if (!$messageId) {
            jsonOut(['success' => false, 'message' => 'Message ID required'], 400);
        }

        // Permanently remove recipient record (message row stays for others)
        $stmt = $db->prepare(
            'DELETE FROM webmail_recipients WHERE message_id = ? AND user_id = ? AND is_trashed = 1'
        );
        $stmt->execute([$messageId, $userId]);
        if (!$stmt->rowCount()) {
            jsonOut(['success' => false, 'message' => 'Message not found in trash'], 404);
        }
        jsonOut(['success' => true, 'message' => 'Message permanently deleted']);
        break;

    // ── RESTORE FROM TRASH ────────────────────────────────────────────────────
    case 'restore':
        requireAuth();
        validateCsrf();
        $userId    = $_SESSION['user_id'];
        $messageId = (int) ($_POST['id'] ?? 0);

        if (!$messageId) {
            jsonOut(['success' => false, 'message' => 'Message ID required'], 400);
        }

        $stmt = $db->prepare(
            'UPDATE webmail_recipients SET is_trashed = 0, trashed_at = NULL
             WHERE message_id = ? AND user_id = ? AND is_trashed = 1'
        );
        $stmt->execute([$messageId, $userId]);
        if (!$stmt->rowCount()) {
            jsonOut(['success' => false, 'message' => 'Message not found in trash'], 404);
        }
        jsonOut(['success' => true, 'message' => 'Message restored']);
        break;

    // ── MARK READ ─────────────────────────────────────────────────────────────
    case 'mark_read':
        requireAuth();
        validateCsrf();
        $userId    = $_SESSION['user_id'];
        $messageId = (int) ($_POST['id'] ?? 0);

        if (!$messageId) {
            jsonOut(['success' => false, 'message' => 'Message ID required'], 400);
        }
        $db->prepare(
            'UPDATE webmail_recipients SET is_read = 1, read_at = NOW()
             WHERE message_id = ? AND user_id = ?'
        )->execute([$messageId, $userId]);
        jsonOut(['success' => true, 'message' => 'Marked as read']);
        break;

    // ── MARK UNREAD ───────────────────────────────────────────────────────────
    case 'mark_unread':
        requireAuth();
        validateCsrf();
        $userId    = $_SESSION['user_id'];
        $messageId = (int) ($_POST['id'] ?? 0);

        if (!$messageId) {
            jsonOut(['success' => false, 'message' => 'Message ID required'], 400);
        }
        $db->prepare(
            'UPDATE webmail_recipients SET is_read = 0, read_at = NULL
             WHERE message_id = ? AND user_id = ?'
        )->execute([$messageId, $userId]);
        jsonOut(['success' => true, 'message' => 'Marked as unread']);
        break;

    // ── SEARCH ───────────────────────────────────────────────────────────────
    case 'search':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $query  = trim($_GET['q'] ?? '');
        $page   = max(1, (int) ($_GET['page'] ?? 1));

        if (strlen($query) < 2) {
            jsonOut(['success' => false, 'message' => 'Search query too short'], 400);
        }

        $like = '%' . $query . '%';
        $sql  = 'SELECT wm.id, wm.subject, wm.body, wm.priority, wm.created_at,
                        wr.is_read,
                        CONCAT(u.first_name, " ", u.last_name) AS sender_name,
                        u.avatar AS sender_avatar
                 FROM webmail_messages wm
                 JOIN webmail_recipients wr ON wr.message_id = wm.id AND wr.user_id = ?
                 JOIN users u ON u.id = wm.sender_id
                 WHERE wr.is_trashed = 0 AND wr.is_deleted = 0
                   AND (wm.subject LIKE ? OR wm.body LIKE ?
                        OR CONCAT(u.first_name, " ", u.last_name) LIKE ?)
                 ORDER BY wm.created_at DESC';
        jsonOut(['success' => true] + wmPaginate($db, $sql, [$userId, $like, $like, $like], $page));
        break;

    // ── LABELS ───────────────────────────────────────────────────────────────
    case 'labels':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET') {
            $stmt = $db->prepare(
                'SELECT * FROM webmail_labels WHERE user_id = ? ORDER BY sort_order ASC, name ASC'
            );
            $stmt->execute([$userId]);
            jsonOut(['success' => true, 'data' => $stmt->fetchAll()]);
        }

        // POST: create or update label
        validateCsrf();
        $labelId = (int) ($_POST['id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');
        $color   = trim($_POST['color'] ?? '#6c757d');

        if (empty($name)) {
            jsonOut(['success' => false, 'message' => 'Label name required'], 400);
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#6c757d';
        }

        if ($labelId) {
            $stmt = $db->prepare(
                'UPDATE webmail_labels SET name = ?, color = ? WHERE id = ? AND user_id = ?'
            );
            $stmt->execute([$name, $color, $labelId, $userId]);
            if (!$stmt->rowCount()) {
                jsonOut(['success' => false, 'message' => 'Label not found'], 404);
            }
            jsonOut(['success' => true, 'message' => 'Label updated']);
        } else {
            try {
                $stmt = $db->prepare(
                    'INSERT INTO webmail_labels (user_id, name, color) VALUES (?, ?, ?)'
                );
                $stmt->execute([$userId, $name, $color]);
                jsonOut(['success' => true, 'label_id' => (int) $db->lastInsertId(), 'message' => 'Label created']);
            } catch (PDOException $e) {
                jsonOut(['success' => false, 'message' => 'Label name already exists'], 409);
            }
        }
        break;

    // ── BULK ACTIONS ─────────────────────────────────────────────────────────
    case 'bulk':
        requireAuth();
        validateCsrf();
        $userId     = $_SESSION['user_id'];
        $bulkAction = $_POST['bulk_action'] ?? '';
        $ids        = $_POST['ids'] ?? [];

        if (is_string($ids)) {
            $ids = json_decode($ids, true) ?? [];
        }
        $ids = array_filter(array_map('intval', $ids));

        if (empty($ids)) {
            jsonOut(['success' => false, 'message' => 'No message IDs provided'], 400);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        switch ($bulkAction) {
            case 'mark_read':
                $stmt = $db->prepare(
                    'UPDATE webmail_recipients SET is_read = 1, read_at = NOW()
                     WHERE user_id = ? AND message_id IN (' . $placeholders . ')'
                );
                $stmt->execute(array_merge([$userId], $ids));
                jsonOut(['success' => true, 'updated' => $stmt->rowCount()]);
                break;

            case 'mark_unread':
                $stmt = $db->prepare(
                    'UPDATE webmail_recipients SET is_read = 0, read_at = NULL
                     WHERE user_id = ? AND message_id IN (' . $placeholders . ')'
                );
                $stmt->execute(array_merge([$userId], $ids));
                jsonOut(['success' => true, 'updated' => $stmt->rowCount()]);
                break;

            case 'delete':
                $stmt = $db->prepare(
                    'UPDATE webmail_recipients SET is_trashed = 1, trashed_at = NOW()
                     WHERE user_id = ? AND message_id IN (' . $placeholders . ') AND is_trashed = 0'
                );
                $stmt->execute(array_merge([$userId], $ids));
                jsonOut(['success' => true, 'updated' => $stmt->rowCount()]);
                break;

            case 'move':
                $label = trim($_POST['label'] ?? '');
                $stmt  = $db->prepare(
                    'UPDATE webmail_recipients SET label = ?
                     WHERE user_id = ? AND message_id IN (' . $placeholders . ')'
                );
                $stmt->execute(array_merge([$label ?: null, $userId], $ids));
                jsonOut(['success' => true, 'updated' => $stmt->rowCount()]);
                break;

            default:
                jsonOut(['success' => false, 'message' => 'Invalid bulk action'], 400);
        }
        break;

    // ── UNREAD COUNT ─────────────────────────────────────────────────────────
    case 'unread_count':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM webmail_recipients wr
             JOIN webmail_messages wm ON wm.id = wr.message_id
             WHERE wr.user_id = ? AND wr.is_read = 0
               AND wr.is_trashed = 0 AND wr.is_deleted = 0
               AND wr.type = "to" AND wm.is_draft = 0'
        );
        $stmt->execute([$userId]);
        jsonOut(['success' => true, 'count' => (int) $stmt->fetchColumn()]);
        break;

    default:
        jsonOut(['success' => false, 'message' => 'Invalid action'], 400);
}
