<?php
/**
 * includes/webmail.php — Webmail Library (PR #20)
 *
 * Internal email-style messaging (DB-based, not SMTP).
 * All platform users can message each other without exposing personal email.
 *
 * Public API:
 *   compose($senderId, $data)                              — Create and send mail
 *   reply($mailId, $userId, $body, $attachments)           — Reply to mail
 *   replyAll($mailId, $userId, $body, $attachments)        — Reply all
 *   forward($mailId, $userId, $toIds, $body)               — Forward mail
 *   saveDraft($userId, $data)                              — Save as draft
 *   updateDraft($draftId, $userId, $data)                  — Update existing draft
 *   deleteDraft($draftId, $userId)                         — Delete draft
 *   getInbox($userId, $filters, $page, $perPage)           — Inbox messages
 *   getSent($userId, $page, $perPage)                      — Sent mail
 *   getDrafts($userId, $page, $perPage)                    — Draft mail
 *   getTrash($userId, $page, $perPage)                     — Trash
 *   getStarred($userId, $page, $perPage)                   — Starred messages
 *   getByLabel($userId, $label, $page, $perPage)           — Filter by label
 *   getMail($mailId, $userId)                              — Get single mail + thread
 *   markAsRead($mailId, $userId)                           — Mark read
 *   markAsUnread($mailId, $userId)                         — Mark unread
 *   starMail($mailId, $userId)                             — Star/unstar toggle
 *   moveToTrash($mailId, $userId)                          — Move to trash
 *   restoreFromTrash($mailId, $userId)                     — Restore from trash
 *   permanentDelete($mailId, $userId)                      — Permanent delete
 *   addLabel($mailId, $userId, $label)                     — Add label
 *   removeLabel($mailId, $userId)                          — Remove label
 *   bulkAction($mailIds, $userId, $action, $options)       — Bulk operations
 *   searchMail($userId, $query, $filters, $page, $perPage) — Full-text search
 *   getUnreadCount($userId)                                — Unread count for badge
 *   getContacts($userId)                                   — Frequent contacts
 *   searchContacts($userId, $query)                        — Search users for autocomplete
 */

// ── Constants ─────────────────────────────────────────────────────────────────

define('WEBMAIL_PRIORITY_NORMAL', 'normal');
define('WEBMAIL_PRIORITY_HIGH',   'high');
define('WEBMAIL_PRIORITY_URGENT', 'urgent');

define('WEBMAIL_VALID_PRIORITIES', ['normal', 'high', 'urgent']);

define('WEBMAIL_RECIPIENT_TO',  'to');
define('WEBMAIL_RECIPIENT_CC',  'cc');
define('WEBMAIL_RECIPIENT_BCC', 'bcc');

define('WEBMAIL_MAX_BULK', 100);

// Allowed attachment MIME types
define('WEBMAIL_ALLOWED_MIME', [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/zip', 'application/x-zip-compressed',
]);

// Allowed attachment extensions
define('WEBMAIL_ALLOWED_EXT', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip']);

define('WEBMAIL_MAX_ATTACHMENT_SIZE', 10 * 1024 * 1024); // 10 MB

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Paginate a webmail query.
 *
 * @param PDO    $db
 * @param string $sql     Base SQL (without LIMIT/OFFSET)
 * @param array  $params  Bound parameters
 * @param int    $page    1-based page number
 * @param int    $perPage Items per page
 * @return array{data: array, total: int, pages: int, current: int}
 */
function wmPageinate(PDO $db, string $sql, array $params, int $page, int $perPage = 20): array
{
    $countSql  = 'SELECT COUNT(*) FROM (' . $sql . ') AS _wmc';
    $cStmt     = $db->prepare($countSql);
    $cStmt->execute($params);
    $total  = (int) $cStmt->fetchColumn();
    $pages  = max(1, (int) ceil($total / $perPage));
    $page   = max(1, min($page, $pages));
    $offset = ($page - 1) * $perPage;
    $dStmt  = $db->prepare($sql . ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset);
    $dStmt->execute($params);
    return [
        'data'    => $dStmt->fetchAll(PDO::FETCH_ASSOC),
        'total'   => $total,
        'pages'   => $pages,
        'current' => $page,
    ];
}

/**
 * Verify that a user has access to a given mail message
 * (either as sender or as a recipient).
 *
 * @param  PDO  $db
 * @param  int  $mailId
 * @param  int  $userId
 * @return bool
 */
function wmHasAccess(PDO $db, int $mailId, int $userId): bool
{
    try {
        $stmt = $db->prepare(
            'SELECT 1 FROM webmail_messages wm
             LEFT JOIN webmail_recipients wr ON wr.message_id = wm.id AND wr.user_id = :uid
             WHERE wm.id = :mid AND (wm.sender_id = :uid2 OR wr.user_id = :uid3)
             LIMIT 1'
        );
        $stmt->execute([':uid' => $userId, ':mid' => $mailId, ':uid2' => $userId, ':uid3' => $userId]);
        return (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('wmHasAccess error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Sanitize HTML body: strip dangerous tags while allowing basic formatting.
 *
 * @param  string $html
 * @return string
 */
function wmSanitizeBody(string $html): string
{
    // Strip script, style, iframe, form, and on* event attributes
    $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i', '', $html);
    $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/i', '', $html);
    $html = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $html);
    $html = preg_replace('/<form\b[^>]*>.*?<\/form>/is', '', $html);
    $html = preg_replace('/\bon\w+\s*=\s*"[^"]*"/i', '', $html);
    $html = preg_replace('/\bon\w+\s*=\s*\'[^\']*\'/i', '', $html);
    return $html;
}

/**
 * Resolve a recipient identifier (user ID integer or email string) to a user ID.
 *
 * @param  PDO          $db
 * @param  int|string   $recipient  User ID or email address
 * @return int|null                 User ID, or null if not found
 */
function wmResolveRecipient(PDO $db, $recipient): ?int
{
    $recipient = trim((string) $recipient);
    if (filter_var($recipient, FILTER_VALIDATE_INT) && (int) $recipient > 0) {
        return (int) $recipient;
    }
    if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        try {
            $stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$recipient]);
            $id = $stmt->fetchColumn();
            return $id ? (int) $id : null;
        } catch (PDOException $e) {
            return null;
        }
    }
    return null;
}

/**
 * Resolve an array of recipient identifiers to unique user IDs.
 *
 * @param  PDO   $db
 * @param  array $recipients
 * @return int[]
 */
function wmResolveRecipients(PDO $db, array $recipients): array
{
    $ids = [];
    foreach ($recipients as $r) {
        if (is_array($r)) {
            foreach ($r as $item) {
                $id = wmResolveRecipient($db, $item);
                if ($id) $ids[] = $id;
            }
        } else {
            // Handle comma-separated values
            foreach (explode(',', (string) $r) as $item) {
                $id = wmResolveRecipient($db, trim($item));
                if ($id) $ids[] = $id;
            }
        }
    }
    return array_values(array_unique($ids));
}

/**
 * Insert recipient rows and optionally fire in-app notifications.
 *
 * @param PDO    $db
 * @param int    $msgId
 * @param int[]  $toIds
 * @param int[]  $ccIds
 * @param int[]  $bccIds
 * @param int    $senderId
 * @param string $subject
 * @param string $senderName
 */
function wmInsertRecipients(
    PDO $db,
    int $msgId,
    array $toIds,
    array $ccIds,
    array $bccIds,
    int $senderId,
    string $subject,
    string $senderName
): void {
    $recpStmt = $db->prepare(
        'INSERT INTO webmail_recipients (message_id, user_id, type) VALUES (?, ?, ?)'
    );
    $notifStmt = $db->prepare(
        'INSERT INTO notifications (user_id, type, title, message, action_url, created_at)
         VALUES (?, "new_message", ?, ?, ?, NOW())'
    );

    foreach ($toIds as $rid) {
        $recpStmt->execute([$msgId, $rid, WEBMAIL_RECIPIENT_TO]);
        if ($rid !== $senderId) {
            $notifStmt->execute([
                $rid,
                'New message from ' . $senderName,
                mb_substr($subject, 0, 255),
                '/pages/webmail/inbox.php?id=' . $msgId,
            ]);
        }
    }
    foreach ($ccIds as $rid) {
        $recpStmt->execute([$msgId, $rid, WEBMAIL_RECIPIENT_CC]);
    }
    foreach ($bccIds as $rid) {
        $recpStmt->execute([$msgId, $rid, WEBMAIL_RECIPIENT_BCC]);
    }
}

// ── Mail CRUD ─────────────────────────────────────────────────────────────────

/**
 * Compose and send an internal mail message.
 *
 * $data keys:
 *   to          (int|string|array) — user IDs or email addresses
 *   cc          (array)
 *   bcc         (array)
 *   subject     (string)
 *   body        (string) — plain text
 *   body_html   (string) — HTML body (will be sanitized)
 *   priority    (string) — normal|high|urgent
 *   is_draft    (bool)
 *   attachments (array)  — optional, pre-uploaded file paths
 *
 * @param  int   $senderId
 * @param  array $data
 * @return array{success: bool, message_id?: int, message: string}
 */
function wmCompose(int $senderId, array $data): array
{
    try {
        $db = getDB();

        $subject  = trim($data['subject'] ?? '');
        $body     = trim($data['body'] ?? '');
        $bodyHtml = isset($data['body_html']) ? wmSanitizeBody(trim($data['body_html'])) : null;
        $priority = in_array($data['priority'] ?? '', WEBMAIL_VALID_PRIORITIES, true)
                    ? $data['priority']
                    : WEBMAIL_PRIORITY_NORMAL;
        $isDraft  = !empty($data['is_draft']) ? 1 : 0;

        if (!$isDraft) {
            if ($subject === '') {
                return ['success' => false, 'message' => 'Subject is required'];
            }
            if ($body === '' && (!$bodyHtml || strip_tags($bodyHtml) === '')) {
                return ['success' => false, 'message' => 'Body is required'];
            }
        }

        // Resolve recipients
        $toRaw  = $data['to']  ?? [];
        $ccRaw  = $data['cc']  ?? [];
        $bccRaw = $data['bcc'] ?? [];

        if (!is_array($toRaw)) $toRaw = [$toRaw];
        $toIds  = wmResolveRecipients($db, $toRaw);
        $ccIds  = wmResolveRecipients($db, is_array($ccRaw) ? $ccRaw : [$ccRaw]);
        $bccIds = wmResolveRecipients($db, is_array($bccRaw) ? $bccRaw : [$bccRaw]);

        if (!$isDraft && empty($toIds)) {
            return ['success' => false, 'message' => 'At least one valid recipient is required'];
        }

        $db->beginTransaction();

        $msgStmt = $db->prepare(
            'INSERT INTO webmail_messages (sender_id, subject, body, body_html, priority, is_draft, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $msgStmt->execute([$senderId, $subject, $body, $bodyHtml, $priority, $isDraft]);
        $msgId = (int) $db->lastInsertId();

        // Set thread_id to the message itself for new threads
        $db->prepare('UPDATE webmail_messages SET thread_id = ? WHERE id = ?')
           ->execute([$msgId, $msgId]);

        if (!$isDraft) {
            $senderName = wmGetSenderName($db, $senderId);
            wmInsertRecipients($db, $msgId, $toIds, $ccIds, $bccIds, $senderId, $subject, $senderName);
        }

        $db->commit();
        return [
            'success'    => true,
            'message_id' => $msgId,
            'message'    => $isDraft ? 'Draft saved' : 'Message sent',
        ];
    } catch (PDOException $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log('wmCompose error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to send message'];
    }
}

/**
 * Get the display name of a user.
 *
 * @param PDO $db
 * @param int $userId
 * @return string
 */
function wmGetSenderName(PDO $db, int $userId): string
{
    try {
        $stmt = $db->prepare('SELECT CONCAT(first_name, " ", last_name) FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        return (string) ($stmt->fetchColumn() ?: 'Unknown');
    } catch (PDOException $e) {
        return 'Unknown';
    }
}

/**
 * Reply to a mail message.
 *
 * @param  int    $mailId
 * @param  int    $userId
 * @param  string $body
 * @param  array  $attachments
 * @return array{success: bool, message_id?: int, message: string}
 */
function wmReply(int $mailId, int $userId, string $body, array $attachments = []): array
{
    try {
        $db = getDB();

        if (empty(trim($body))) {
            return ['success' => false, 'message' => 'Body is required'];
        }
        if (!wmHasAccess($db, $mailId, $userId)) {
            return ['success' => false, 'message' => 'Message not found'];
        }

        $parentStmt = $db->prepare('SELECT * FROM webmail_messages WHERE id = ?');
        $parentStmt->execute([$mailId]);
        $parent = $parentStmt->fetch(PDO::FETCH_ASSOC);
        if (!$parent) {
            return ['success' => false, 'message' => 'Parent message not found'];
        }

        $threadId = $parent['thread_id'] ?: $mailId;
        $subject  = (strncasecmp($parent['subject'], 'Re: ', 4) === 0)
                    ? $parent['subject']
                    : 'Re: ' . $parent['subject'];

        $db->beginTransaction();

        $msgStmt = $db->prepare(
            'INSERT INTO webmail_messages (sender_id, thread_id, subject, body, priority, parent_message_id, is_draft, created_at, updated_at)
             VALUES (?, ?, ?, ?, "normal", ?, 0, NOW(), NOW())'
        );
        $msgStmt->execute([$userId, $threadId, $subject, trim($body), $mailId]);
        $msgId = (int) $db->lastInsertId();

        $replyToId  = (int) $parent['sender_id'];
        $senderName = wmGetSenderName($db, $userId);

        if ($replyToId !== $userId) {
            $db->prepare(
                'INSERT INTO webmail_recipients (message_id, user_id, type) VALUES (?, ?, "to")'
            )->execute([$msgId, $replyToId]);

            $db->prepare(
                'INSERT INTO notifications (user_id, type, title, message, action_url, created_at)
                 VALUES (?, "new_message", ?, ?, ?, NOW())'
            )->execute([
                $replyToId,
                'Reply from ' . $senderName,
                mb_substr($subject, 0, 255),
                '/pages/webmail/read.php?id=' . $msgId,
            ]);
        }

        $db->commit();
        return ['success' => true, 'message_id' => $msgId, 'message' => 'Reply sent'];
    } catch (PDOException $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log('wmReply error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to send reply'];
    }
}

/**
 * Reply-All: reply to original sender AND all To/CC recipients.
 *
 * @param  int    $mailId
 * @param  int    $userId
 * @param  string $body
 * @param  array  $attachments
 * @return array{success: bool, message_id?: int, message: string}
 */
function wmReplyAll(int $mailId, int $userId, string $body, array $attachments = []): array
{
    try {
        $db = getDB();

        if (empty(trim($body))) {
            return ['success' => false, 'message' => 'Body is required'];
        }
        if (!wmHasAccess($db, $mailId, $userId)) {
            return ['success' => false, 'message' => 'Message not found'];
        }

        $parentStmt = $db->prepare('SELECT * FROM webmail_messages WHERE id = ?');
        $parentStmt->execute([$mailId]);
        $parent = $parentStmt->fetch(PDO::FETCH_ASSOC);
        if (!$parent) {
            return ['success' => false, 'message' => 'Parent message not found'];
        }

        // Collect all To/CC recipients excluding current user
        $recpStmt = $db->prepare(
            "SELECT user_id FROM webmail_recipients WHERE message_id = ? AND type IN ('to', 'cc')"
        );
        $recpStmt->execute([$mailId]);
        $recipientRows = $recpStmt->fetchAll(PDO::FETCH_COLUMN);

        $allIds = array_unique(array_merge([(int) $parent['sender_id']], array_map('intval', $recipientRows)));
        $toIds  = array_values(array_filter($allIds, fn($id) => $id !== $userId));

        $threadId = $parent['thread_id'] ?: $mailId;
        $subject  = (strncasecmp($parent['subject'], 'Re: ', 4) === 0)
                    ? $parent['subject']
                    : 'Re: ' . $parent['subject'];

        $db->beginTransaction();

        $msgStmt = $db->prepare(
            'INSERT INTO webmail_messages (sender_id, thread_id, subject, body, priority, parent_message_id, is_draft, created_at, updated_at)
             VALUES (?, ?, ?, ?, "normal", ?, 0, NOW(), NOW())'
        );
        $msgStmt->execute([$userId, $threadId, $subject, trim($body), $mailId]);
        $msgId = (int) $db->lastInsertId();

        $senderName = wmGetSenderName($db, $userId);
        wmInsertRecipients($db, $msgId, $toIds, [], [], $userId, $subject, $senderName);

        $db->commit();
        return ['success' => true, 'message_id' => $msgId, 'message' => 'Reply sent to all'];
    } catch (PDOException $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log('wmReplyAll error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to reply all'];
    }
}

/**
 * Forward a mail message to new recipients.
 *
 * @param  int          $mailId
 * @param  int          $userId
 * @param  int[]        $toIds
 * @param  string       $body    Forwarding note
 * @return array{success: bool, message_id?: int, message: string}
 */
function wmForward(int $mailId, int $userId, array $toIds, string $body = ''): array
{
    try {
        $db = getDB();

        if (empty($toIds)) {
            return ['success' => false, 'message' => 'At least one recipient is required'];
        }
        if (!wmHasAccess($db, $mailId, $userId)) {
            return ['success' => false, 'message' => 'Message not found'];
        }

        $origStmt = $db->prepare('SELECT * FROM webmail_messages WHERE id = ?');
        $origStmt->execute([$mailId]);
        $orig = $origStmt->fetch(PDO::FETCH_ASSOC);
        if (!$orig) {
            return ['success' => false, 'message' => 'Original message not found'];
        }

        $senderName = wmGetSenderName($db, $orig['sender_id']);
        $subject    = (strncasecmp($orig['subject'], 'Fwd: ', 5) === 0)
                      ? $orig['subject']
                      : 'Fwd: ' . $orig['subject'];
        $fwdBody    = trim($body) . "\n\n--- Forwarded Message ---\nFrom: " . $senderName
                      . "\nDate: " . $orig['created_at']
                      . "\nSubject: " . $orig['subject']
                      . "\n\n" . strip_tags($orig['body']);

        $db->beginTransaction();

        $msgStmt = $db->prepare(
            'INSERT INTO webmail_messages (sender_id, subject, body, priority, is_draft, created_at, updated_at)
             VALUES (?, ?, ?, "normal", 0, NOW(), NOW())'
        );
        $msgStmt->execute([$userId, $subject, $fwdBody]);
        $msgId = (int) $db->lastInsertId();

        $db->prepare('UPDATE webmail_messages SET thread_id = ? WHERE id = ?')
           ->execute([$msgId, $msgId]);

        $fwdSenderName = wmGetSenderName($db, $userId);
        wmInsertRecipients($db, $msgId, $toIds, [], [], $userId, $subject, $fwdSenderName);

        $db->commit();
        return ['success' => true, 'message_id' => $msgId, 'message' => 'Message forwarded'];
    } catch (PDOException $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log('wmForward error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to forward message'];
    }
}

/**
 * Save a new draft.
 *
 * @param  int   $userId
 * @param  array $data  (subject, body, body_html, priority, to, cc, bcc)
 * @return array{success: bool, message_id?: int, message: string}
 */
function wmSaveDraft(int $userId, array $data): array
{
    $data['is_draft'] = true;
    return wmCompose($userId, $data);
}

/**
 * Update an existing draft.
 *
 * @param  int   $draftId
 * @param  int   $userId
 * @param  array $data
 * @return array{success: bool, message: string}
 */
function wmUpdateDraft(int $draftId, int $userId, array $data): array
{
    try {
        $db = getDB();

        $check = $db->prepare(
            'SELECT id FROM webmail_messages WHERE id = ? AND sender_id = ? AND is_draft = 1'
        );
        $check->execute([$draftId, $userId]);
        if (!$check->fetch()) {
            return ['success' => false, 'message' => 'Draft not found'];
        }

        $subject  = trim($data['subject'] ?? '');
        $body     = trim($data['body'] ?? '');
        $bodyHtml = isset($data['body_html']) ? wmSanitizeBody(trim($data['body_html'])) : null;
        $priority = in_array($data['priority'] ?? '', WEBMAIL_VALID_PRIORITIES, true)
                    ? $data['priority']
                    : WEBMAIL_PRIORITY_NORMAL;

        $db->prepare(
            'UPDATE webmail_messages SET subject = ?, body = ?, body_html = ?, priority = ?, updated_at = NOW()
             WHERE id = ?'
        )->execute([$subject, $body, $bodyHtml, $priority, $draftId]);

        return ['success' => true, 'message' => 'Draft updated'];
    } catch (PDOException $e) {
        error_log('wmUpdateDraft error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update draft'];
    }
}

/**
 * Delete a draft permanently.
 *
 * @param  int $draftId
 * @param  int $userId
 * @return array{success: bool, message: string}
 */
function wmDeleteDraft(int $draftId, int $userId): array
{
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'DELETE FROM webmail_messages WHERE id = ? AND sender_id = ? AND is_draft = 1'
        );
        $stmt->execute([$draftId, $userId]);
        if (!$stmt->rowCount()) {
            return ['success' => false, 'message' => 'Draft not found'];
        }
        return ['success' => true, 'message' => 'Draft deleted'];
    } catch (PDOException $e) {
        error_log('wmDeleteDraft error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to delete draft'];
    }
}

// ── Mailbox Operations ────────────────────────────────────────────────────────

/**
 * Get paginated inbox messages for a user.
 *
 * $filters keys (all optional):
 *   is_read    (bool)
 *   priority   (string)
 *   label      (string)
 *   date_from  (string) YYYY-MM-DD
 *   date_to    (string) YYYY-MM-DD
 *
 * @param  int   $userId
 * @param  array $filters
 * @param  int   $page
 * @param  int   $perPage
 * @return array
 */
function wmGetInbox(int $userId, array $filters = [], int $page = 1, int $perPage = 20): array
{
    try {
        $db     = getDB();
        $where  = ['wr.user_id = ?', 'wr.is_trashed = 0', 'wr.is_deleted = 0',
                   "wr.type = 'to'", 'wm.is_draft = 0'];
        $params = [$userId];

        if (isset($filters['is_read'])) {
            $where[]  = 'wr.is_read = ?';
            $params[] = $filters['is_read'] ? 1 : 0;
        }
        if (!empty($filters['priority'])) {
            $where[]  = 'wm.priority = ?';
            $params[] = $filters['priority'];
        }
        if (!empty($filters['label'])) {
            $where[]  = 'wr.label = ?';
            $params[] = $filters['label'];
        }
        if (!empty($filters['date_from'])) {
            $where[]  = 'wm.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'wm.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT wm.id, wm.subject, wm.body, wm.priority, wm.created_at,
                       wr.is_read, wr.label,
                       wm.sender_id,
                       CONCAT(u.first_name, ' ', u.last_name) AS sender_name,
                       u.avatar AS sender_avatar,
                       (SELECT COUNT(*) FROM webmail_attachments wa WHERE wa.message_id = wm.id) AS attachment_count
                FROM webmail_recipients wr
                JOIN webmail_messages wm ON wm.id = wr.message_id
                JOIN users u ON u.id = wm.sender_id
                WHERE $whereClause
                ORDER BY wm.created_at DESC";

        return wmPageinate($db, $sql, $params, $page, $perPage);
    } catch (PDOException $e) {
        error_log('wmGetInbox error: ' . $e->getMessage());
        return ['data' => [], 'total' => 0, 'pages' => 1, 'current' => $page];
    }
}

/**
 * Get paginated sent mail for a user.
 *
 * @param  int $userId
 * @param  int $page
 * @param  int $perPage
 * @return array
 */
function wmGetSent(int $userId, int $page = 1, int $perPage = 20): array
{
    try {
        $db  = getDB();
        $sql = "SELECT wm.id, wm.subject, wm.body, wm.priority, wm.created_at,
                       (SELECT COUNT(*) FROM webmail_attachments wa WHERE wa.message_id = wm.id) AS attachment_count
                FROM webmail_messages wm
                WHERE wm.sender_id = ? AND wm.is_draft = 0
                ORDER BY wm.created_at DESC";
        return wmPageinate($db, $sql, [$userId], $page, $perPage);
    } catch (PDOException $e) {
        error_log('wmGetSent error: ' . $e->getMessage());
        return ['data' => [], 'total' => 0, 'pages' => 1, 'current' => $page];
    }
}

/**
 * Get paginated drafts for a user.
 *
 * @param  int $userId
 * @param  int $page
 * @param  int $perPage
 * @return array
 */
function wmGetDrafts(int $userId, int $page = 1, int $perPage = 20): array
{
    try {
        $db  = getDB();
        $sql = 'SELECT wm.id, wm.subject, wm.body, wm.priority, wm.created_at, wm.updated_at
                FROM webmail_messages wm
                WHERE wm.sender_id = ? AND wm.is_draft = 1
                ORDER BY wm.updated_at DESC';
        return wmPageinate($db, $sql, [$userId], $page, $perPage);
    } catch (PDOException $e) {
        error_log('wmGetDrafts error: ' . $e->getMessage());
        return ['data' => [], 'total' => 0, 'pages' => 1, 'current' => $page];
    }
}

/**
 * Get paginated trash for a user.
 *
 * @param  int $userId
 * @param  int $page
 * @param  int $perPage
 * @return array
 */
function wmGetTrash(int $userId, int $page = 1, int $perPage = 20): array
{
    try {
        $db  = getDB();
        $sql = "SELECT wm.id, wm.subject, wm.body, wm.priority, wm.created_at,
                       wr.is_read, wr.trashed_at,
                       CONCAT(u.first_name, ' ', u.last_name) AS sender_name,
                       u.avatar AS sender_avatar
                FROM webmail_recipients wr
                JOIN webmail_messages wm ON wm.id = wr.message_id
                JOIN users u ON u.id = wm.sender_id
                WHERE wr.user_id = ? AND wr.is_trashed = 1
                ORDER BY wr.trashed_at DESC";
        return wmPageinate($db, $sql, [$userId], $page, $perPage);
    } catch (PDOException $e) {
        error_log('wmGetTrash error: ' . $e->getMessage());
        return ['data' => [], 'total' => 0, 'pages' => 1, 'current' => $page];
    }
}

/**
 * Get paginated starred messages for a user.
 *
 * @param  int $userId
 * @param  int $page
 * @param  int $perPage
 * @return array
 */
function wmGetStarred(int $userId, int $page = 1, int $perPage = 20): array
{
    try {
        $db  = getDB();
        $sql = "SELECT wm.id, wm.subject, wm.body, wm.priority, wm.created_at,
                       wr.is_read, wr.is_starred,
                       CONCAT(u.first_name, ' ', u.last_name) AS sender_name,
                       u.avatar AS sender_avatar,
                       (SELECT COUNT(*) FROM webmail_attachments wa WHERE wa.message_id = wm.id) AS attachment_count
                FROM webmail_recipients wr
                JOIN webmail_messages wm ON wm.id = wr.message_id
                JOIN users u ON u.id = wm.sender_id
                WHERE wr.user_id = ? AND wr.is_starred = 1 AND wr.is_trashed = 0 AND wr.is_deleted = 0
                ORDER BY wm.created_at DESC";
        return wmPageinate($db, $sql, [$userId], $page, $perPage);
    } catch (PDOException $e) {
        error_log('wmGetStarred error: ' . $e->getMessage());
        return ['data' => [], 'total' => 0, 'pages' => 1, 'current' => $page];
    }
}

/**
 * Get paginated messages filtered by label.
 *
 * @param  int    $userId
 * @param  string $label
 * @param  int    $page
 * @param  int    $perPage
 * @return array
 */
function wmGetByLabel(int $userId, string $label, int $page = 1, int $perPage = 20): array
{
    try {
        $db  = getDB();
        $sql = "SELECT wm.id, wm.subject, wm.body, wm.priority, wm.created_at,
                       wr.is_read, wr.label,
                       CONCAT(u.first_name, ' ', u.last_name) AS sender_name,
                       u.avatar AS sender_avatar
                FROM webmail_recipients wr
                JOIN webmail_messages wm ON wm.id = wr.message_id
                JOIN users u ON u.id = wm.sender_id
                WHERE wr.user_id = ? AND wr.label = ? AND wr.is_trashed = 0 AND wr.is_deleted = 0
                ORDER BY wm.created_at DESC";
        return wmPageinate($db, $sql, [$userId, $label], $page, $perPage);
    } catch (PDOException $e) {
        error_log('wmGetByLabel error: ' . $e->getMessage());
        return ['data' => [], 'total' => 0, 'pages' => 1, 'current' => $page];
    }
}

/**
 * Get a single mail message with its thread, attachments, and recipients.
 * Also marks the message as read for the requesting user.
 *
 * @param  int      $mailId
 * @param  int      $userId
 * @return array|null  Message array or null if not found / no access
 */
function wmGetMail(int $mailId, int $userId): ?array
{
    try {
        $db = getDB();

        if (!wmHasAccess($db, $mailId, $userId)) {
            return null;
        }

        $stmt = $db->prepare(
            "SELECT wm.*,
                    CONCAT(u.first_name, ' ', u.last_name) AS sender_name,
                    u.avatar AS sender_avatar,
                    u.email AS sender_email
             FROM webmail_messages wm
             JOIN users u ON u.id = wm.sender_id
             WHERE wm.id = ?"
        );
        $stmt->execute([$mailId]);
        $message = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$message) {
            return null;
        }

        // Thread messages
        $threadId = $message['thread_id'] ?: $mailId;
        $threadStmt = $db->prepare(
            "SELECT wm.*,
                    CONCAT(u.first_name, ' ', u.last_name) AS sender_name,
                    u.avatar AS sender_avatar
             FROM webmail_messages wm
             JOIN users u ON u.id = wm.sender_id
             WHERE wm.thread_id = ? AND wm.id != ? AND wm.is_draft = 0
             ORDER BY wm.created_at ASC"
        );
        $threadStmt->execute([$threadId, $mailId]);
        $message['thread'] = $threadStmt->fetchAll(PDO::FETCH_ASSOC);

        // Attachments
        $attStmt = $db->prepare('SELECT * FROM webmail_attachments WHERE message_id = ?');
        $attStmt->execute([$mailId]);
        $message['attachments'] = $attStmt->fetchAll(PDO::FETCH_ASSOC);

        // Recipients
        $recpStmt = $db->prepare(
            "SELECT wr.type, wr.is_read, wr.read_at, wr.is_starred,
                    u.id AS user_id,
                    CONCAT(u.first_name, ' ', u.last_name) AS name,
                    u.email, u.avatar
             FROM webmail_recipients wr
             JOIN users u ON u.id = wr.user_id
             WHERE wr.message_id = ?"
        );
        $recpStmt->execute([$mailId]);
        $message['recipients'] = $recpStmt->fetchAll(PDO::FETCH_ASSOC);

        // Mark as read for this user
        $db->prepare(
            'UPDATE webmail_recipients SET is_read = 1, read_at = NOW()
             WHERE message_id = ? AND user_id = ? AND is_read = 0'
        )->execute([$mailId, $userId]);

        return $message;
    } catch (PDOException $e) {
        error_log('wmGetMail error: ' . $e->getMessage());
        return null;
    }
}

// ── Mail Actions ──────────────────────────────────────────────────────────────

/**
 * Mark a message as read for the given user.
 *
 * @param  int $mailId
 * @param  int $userId
 * @return bool
 */
function wmMarkAsRead(int $mailId, int $userId): bool
{
    try {
        $db = getDB();
        $db->prepare(
            'UPDATE webmail_recipients SET is_read = 1, read_at = NOW()
             WHERE message_id = ? AND user_id = ?'
        )->execute([$mailId, $userId]);
        return true;
    } catch (PDOException $e) {
        error_log('wmMarkAsRead error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Mark a message as unread for the given user.
 *
 * @param  int $mailId
 * @param  int $userId
 * @return bool
 */
function wmMarkAsUnread(int $mailId, int $userId): bool
{
    try {
        $db = getDB();
        $db->prepare(
            'UPDATE webmail_recipients SET is_read = 0, read_at = NULL
             WHERE message_id = ? AND user_id = ?'
        )->execute([$mailId, $userId]);
        return true;
    } catch (PDOException $e) {
        error_log('wmMarkAsUnread error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Toggle star on a message for the given user.
 * Returns true if the message is now starred, false if unstarred.
 *
 * @param  int $mailId
 * @param  int $userId
 * @return array{success: bool, starred: bool}
 */
function wmStarMail(int $mailId, int $userId): array
{
    try {
        $db = getDB();

        $stmt = $db->prepare(
            'SELECT is_starred FROM webmail_recipients WHERE message_id = ? AND user_id = ?'
        );
        $stmt->execute([$mailId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return ['success' => false, 'starred' => false];
        }

        $newState = $row['is_starred'] ? 0 : 1;
        $db->prepare(
            'UPDATE webmail_recipients SET is_starred = ? WHERE message_id = ? AND user_id = ?'
        )->execute([$newState, $mailId, $userId]);

        return ['success' => true, 'starred' => (bool) $newState];
    } catch (PDOException $e) {
        error_log('wmStarMail error: ' . $e->getMessage());
        return ['success' => false, 'starred' => false];
    }
}

/**
 * Move a message to trash for the given user.
 *
 * @param  int $mailId
 * @param  int $userId
 * @return bool
 */
function wmMoveToTrash(int $mailId, int $userId): bool
{
    try {
        $db = getDB();
        $stmt = $db->prepare(
            'UPDATE webmail_recipients SET is_trashed = 1, trashed_at = NOW()
             WHERE message_id = ? AND user_id = ? AND is_trashed = 0'
        );
        $stmt->execute([$mailId, $userId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('wmMoveToTrash error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Restore a message from trash for the given user.
 *
 * @param  int $mailId
 * @param  int $userId
 * @return bool
 */
function wmRestoreFromTrash(int $mailId, int $userId): bool
{
    try {
        $db = getDB();
        $stmt = $db->prepare(
            'UPDATE webmail_recipients SET is_trashed = 0, trashed_at = NULL
             WHERE message_id = ? AND user_id = ? AND is_trashed = 1'
        );
        $stmt->execute([$mailId, $userId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('wmRestoreFromTrash error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Permanently delete a message for the given user (removes recipient row).
 *
 * @param  int $mailId
 * @param  int $userId
 * @return bool
 */
function wmPermanentDelete(int $mailId, int $userId): bool
{
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'DELETE FROM webmail_recipients WHERE message_id = ? AND user_id = ?'
        );
        $stmt->execute([$mailId, $userId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('wmPermanentDelete error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Add a label to a message for the given user.
 *
 * @param  int    $mailId
 * @param  int    $userId
 * @param  string $label
 * @return bool
 */
function wmAddLabel(int $mailId, int $userId, string $label): bool
{
    try {
        $db = getDB();
        $stmt = $db->prepare(
            'UPDATE webmail_recipients SET label = ?
             WHERE message_id = ? AND user_id = ?'
        );
        $stmt->execute([mb_substr(trim($label), 0, 50), $mailId, $userId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('wmAddLabel error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Remove the label from a message for the given user.
 *
 * @param  int $mailId
 * @param  int $userId
 * @return bool
 */
function wmRemoveLabel(int $mailId, int $userId): bool
{
    try {
        $db = getDB();
        $stmt = $db->prepare(
            'UPDATE webmail_recipients SET label = NULL
             WHERE message_id = ? AND user_id = ?'
        );
        $stmt->execute([$mailId, $userId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('wmRemoveLabel error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Perform a bulk action on multiple messages for a user.
 *
 * $action values:
 *   mark_read    — mark all as read
 *   mark_unread  — mark all as unread
 *   delete       — move to trash
 *   restore      — restore from trash
 *   permanent_delete — permanently delete
 *   label        — apply label (requires $options['label'])
 *
 * @param  int[]  $mailIds
 * @param  int    $userId
 * @param  string $action
 * @param  array  $options  e.g. ['label' => 'Important']
 * @return array{success: bool, updated: int, message: string}
 */
function wmBulkAction(array $mailIds, int $userId, string $action, array $options = []): array
{
    if (empty($mailIds)) {
        return ['success' => false, 'updated' => 0, 'message' => 'No message IDs provided'];
    }

    $mailIds = array_values(array_unique(array_filter(array_map('intval', $mailIds))));

    if (count($mailIds) > WEBMAIL_MAX_BULK) {
        return ['success' => false, 'updated' => 0, 'message' => 'Maximum ' . WEBMAIL_MAX_BULK . ' messages per bulk operation'];
    }

    try {
        $db           = getDB();
        $placeholders = implode(',', array_fill(0, count($mailIds), '?'));

        switch ($action) {
            case 'mark_read':
                $stmt = $db->prepare(
                    "UPDATE webmail_recipients SET is_read = 1, read_at = NOW()
                     WHERE user_id = ? AND message_id IN ($placeholders)"
                );
                $stmt->execute(array_merge([$userId], $mailIds));
                break;

            case 'mark_unread':
                $stmt = $db->prepare(
                    "UPDATE webmail_recipients SET is_read = 0, read_at = NULL
                     WHERE user_id = ? AND message_id IN ($placeholders)"
                );
                $stmt->execute(array_merge([$userId], $mailIds));
                break;

            case 'delete':
                $stmt = $db->prepare(
                    "UPDATE webmail_recipients SET is_trashed = 1, trashed_at = NOW()
                     WHERE user_id = ? AND message_id IN ($placeholders) AND is_trashed = 0"
                );
                $stmt->execute(array_merge([$userId], $mailIds));
                break;

            case 'restore':
                $stmt = $db->prepare(
                    "UPDATE webmail_recipients SET is_trashed = 0, trashed_at = NULL
                     WHERE user_id = ? AND message_id IN ($placeholders) AND is_trashed = 1"
                );
                $stmt->execute(array_merge([$userId], $mailIds));
                break;

            case 'permanent_delete':
                $stmt = $db->prepare(
                    "DELETE FROM webmail_recipients WHERE user_id = ? AND message_id IN ($placeholders)"
                );
                $stmt->execute(array_merge([$userId], $mailIds));
                break;

            case 'label':
                $label = mb_substr(trim($options['label'] ?? ''), 0, 50) ?: null;
                $stmt  = $db->prepare(
                    "UPDATE webmail_recipients SET label = ?
                     WHERE user_id = ? AND message_id IN ($placeholders)"
                );
                $stmt->execute(array_merge([$label, $userId], $mailIds));
                break;

            default:
                return ['success' => false, 'updated' => 0, 'message' => 'Invalid bulk action'];
        }

        return ['success' => true, 'updated' => $stmt->rowCount(), 'message' => 'Bulk action applied'];
    } catch (PDOException $e) {
        error_log('wmBulkAction error: ' . $e->getMessage());
        return ['success' => false, 'updated' => 0, 'message' => 'Bulk action failed'];
    }
}

// ── Search & Filters ──────────────────────────────────────────────────────────

/**
 * Search mail for a user with optional filters.
 *
 * $filters keys (all optional):
 *   from           (string) — sender name or email fragment
 *   to             (string) — recipient name or email fragment
 *   subject        (string) — subject keyword
 *   has_attachment (bool)
 *   date_from      (string) YYYY-MM-DD
 *   date_to        (string) YYYY-MM-DD
 *   label          (string)
 *   is_read        (bool)
 *
 * @param  int    $userId
 * @param  string $query
 * @param  array  $filters
 * @param  int    $page
 * @param  int    $perPage
 * @return array
 */
function wmSearchMail(int $userId, string $query, array $filters = [], int $page = 1, int $perPage = 20): array
{
    try {
        $db     = getDB();
        // wr.user_id is bound via the JOIN clause; extra WHERE conditions go into $where/$params
        $where  = ['wr.is_trashed = 0', 'wr.is_deleted = 0', 'wm.is_draft = 0'];
        $params = [$userId]; // first param is for the JOIN condition

        $query = trim($query);
        if (strlen($query) >= 2) {
            $like     = '%' . $query . '%';
            $where[]  = "(wm.subject LIKE ? OR wm.body LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($filters['label'])) {
            $where[]  = 'wr.label = ?';
            $params[] = $filters['label'];
        }
        if (isset($filters['is_read'])) {
            $where[]  = 'wr.is_read = ?';
            $params[] = $filters['is_read'] ? 1 : 0;
        }
        if (!empty($filters['date_from'])) {
            $where[]  = 'wm.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'wm.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['has_attachment'])) {
            $where[] = 'EXISTS (SELECT 1 FROM webmail_attachments wa WHERE wa.message_id = wm.id)';
        }

        $whereClause = empty($where) ? '1=1' : implode(' AND ', $where);
        $sql = "SELECT wm.id, wm.subject, wm.body, wm.priority, wm.created_at,
                       wr.is_read, wr.label,
                       CONCAT(u.first_name, ' ', u.last_name) AS sender_name,
                       u.avatar AS sender_avatar,
                       (SELECT COUNT(*) FROM webmail_attachments wa WHERE wa.message_id = wm.id) AS attachment_count
                FROM webmail_messages wm
                JOIN webmail_recipients wr ON wr.message_id = wm.id AND wr.user_id = ?
                JOIN users u ON u.id = wm.sender_id
                WHERE $whereClause
                ORDER BY wm.created_at DESC";

        return wmPageinate($db, $sql, $params, $page, $perPage);
    } catch (PDOException $e) {
        error_log('wmSearchMail error: ' . $e->getMessage());
        return ['data' => [], 'total' => 0, 'pages' => 1, 'current' => $page];
    }
}

/**
 * Get total unread message count for a user (for badge display).
 *
 * @param  int $userId
 * @return int
 */
function wmGetUnreadCount(int $userId): int
{
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM webmail_recipients wr
             JOIN webmail_messages wm ON wm.id = wr.message_id
             WHERE wr.user_id = ? AND wr.is_read = 0
               AND wr.is_trashed = 0 AND wr.is_deleted = 0
               AND wr.type = 'to' AND wm.is_draft = 0"
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('wmGetUnreadCount error: ' . $e->getMessage());
        return 0;
    }
}

// ── Contacts ─────────────────────────────────────────────────────────────────

/**
 * Get frequent contacts for a user (people they have messaged or received messages from).
 *
 * @param  int $userId
 * @param  int $limit
 * @return array
 */
function wmGetContacts(int $userId, int $limit = 20): array
{
    try {
        $db  = getDB();
        // People the user has sent mail to
        $sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.avatar,
                       COUNT(*) AS message_count
                FROM webmail_messages wm
                JOIN webmail_recipients wr ON wr.message_id = wm.id
                JOIN users u ON u.id = wr.user_id
                WHERE wm.sender_id = ? AND wr.user_id != ? AND wm.is_draft = 0
                GROUP BY u.id
                ORDER BY message_count DESC
                LIMIT " . (int) $limit;
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('wmGetContacts error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Search users by name or email for compose autocomplete.
 *
 * @param  int    $userId  Current user (exclude from results)
 * @param  string $query
 * @param  int    $limit
 * @return array
 */
function wmSearchContacts(int $userId, string $query, int $limit = 10): array
{
    $query = trim($query);
    if (strlen($query) < 2) {
        return [];
    }
    try {
        $db   = getDB();
        $like = '%' . $query . '%';
        $stmt = $db->prepare(
            "SELECT id, first_name, last_name, email, avatar
             FROM users
             WHERE id != ?
               AND (CONCAT(first_name, ' ', last_name) LIKE ? OR email LIKE ?)
               AND is_active = 1
             ORDER BY first_name ASC, last_name ASC
             LIMIT " . (int) $limit
        );
        $stmt->execute([$userId, $like, $like]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('wmSearchContacts error: ' . $e->getMessage());
        return [];
    }
}
