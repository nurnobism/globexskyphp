<?php
/**
 * GlobexSky Mailer
 *
 * Wraps PHPMailer for SMTP delivery. Degrades gracefully to PHP mail() when
 * PHPMailer is not installed (e.g. vendor/autoload.php is absent).
 *
 * Config is sourced from config/mail.php constants:
 *  MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD,
 *  MAIL_ENCRYPTION, MAIL_FROM_NAME, MAIL_FROM_EMAIL
 */

// Load PHPMailer via Composer autoloader if available
$_phpmailerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($_phpmailerAutoload)) {
    require_once $_phpmailerAutoload;
}

// Load mail config constants if not already defined (e.g. in standalone usage)
if (!defined('MAIL_HOST')) {
    $mailConfigPath = __DIR__ . '/../config/mail.php';
    if (file_exists($mailConfigPath)) {
        require_once $mailConfigPath;
    }
}

// Load email templates
require_once __DIR__ . '/../templates/emails/base.php';
require_once __DIR__ . '/../templates/emails/welcome.php';
require_once __DIR__ . '/../templates/emails/order-confirmation.php';
require_once __DIR__ . '/../templates/emails/order-placed.php';
require_once __DIR__ . '/../templates/emails/order-confirmed.php';
require_once __DIR__ . '/../templates/emails/order-shipped.php';
require_once __DIR__ . '/../templates/emails/order-delivered.php';
require_once __DIR__ . '/../templates/emails/order-cancelled.php';
require_once __DIR__ . '/../templates/emails/new-order.php';
require_once __DIR__ . '/../templates/emails/password-reset.php';
require_once __DIR__ . '/../templates/emails/password-changed.php';
require_once __DIR__ . '/../templates/emails/email-verification.php';
require_once __DIR__ . '/../templates/emails/new-message.php';
require_once __DIR__ . '/../templates/emails/payout-processed.php';
require_once __DIR__ . '/../templates/emails/payout-rejected.php';
require_once __DIR__ . '/../templates/emails/plan-expires-soon.php';
require_once __DIR__ . '/../templates/emails/plan-expired.php';
require_once __DIR__ . '/../templates/emails/kyc-approved.php';
require_once __DIR__ . '/../templates/emails/kyc-rejected.php';
require_once __DIR__ . '/../templates/emails/dispute-opened.php';
require_once __DIR__ . '/../templates/emails/invoice.php';

/**
 * Send an HTML email via SMTP (PHPMailer) or fall back to PHP mail().
 *
 * @param string|array $to      Single address string or ['email@x.com' => 'Name']
 * @param string       $subject Email subject
 * @param string       $body    HTML body
 * @param string       $altBody Plain-text fallback
 * @return bool
 */
function sendEmail(string|array $to, string $subject, string $body, string $altBody = ''): bool
{
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return _sendEmailViaPHPMailer($to, $subject, $body, $altBody);
    }

    // Fallback: PHP mail()
    error_log('mailer: PHPMailer not available, falling back to mail()');
    return _sendEmailFallback($to, $subject, $body, $altBody);
}

/**
 * Send via PHPMailer with SMTP.
 */
function _sendEmailViaPHPMailer(string|array $to, string $subject, string $body, string $altBody): bool
{
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->Port       = (int) MAIL_PORT;

        $mail->SMTPSecure = match (strtolower(MAIL_ENCRYPTION)) {
            'ssl'   => PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS,
            default => PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS,
        };

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addReplyTo(MAIL_FROM_EMAIL, MAIL_FROM_NAME);

        if (is_array($to)) {
            foreach ($to as $address => $name) {
                $mail->addAddress(is_string($address) ? $address : $name, is_string($address) ? $name : '');
            }
        } else {
            $mail->addAddress($to);
        }

        $mail->isHTML(true);
        $mail->CharSet  = 'UTF-8';
        $mail->Subject  = $subject;
        $mail->Body     = $body;
        $mail->AltBody  = $altBody ?: strip_tags($body);

        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('PHPMailer error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Minimal PHP mail() fallback.
 */
function _sendEmailFallback(string|array $to, string $subject, string $body, string $altBody): bool
{
    $toAddress = is_array($to) ? implode(', ', array_keys($to)) : $to;

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_EMAIL . ">\r\n";
    $headers .= 'Reply-To: ' . MAIL_FROM_EMAIL . "\r\n";
    $headers .= 'X-Mailer: PHP/' . phpversion();

    return mail($toAddress, $subject, $body, $headers);
}

/**
 * Send a welcome email to a newly registered user.
 */
function sendWelcomeEmail(PDO $db, int $userId): bool
{
    try {
        $stmt = $db->prepare('SELECT name, email FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('sendWelcomeEmail DB error: ' . $e->getMessage());
        return false;
    }

    if (!$user) {
        error_log("sendWelcomeEmail: user {$userId} not found");
        return false;
    }

    $loginUrl = defined('APP_URL') ? APP_URL . '/pages/login.php' : '/pages/login.php';
    $html     = emailWelcome($user['name'], $loginUrl);

    return sendEmail(
        $user['email'],
        'Welcome to ' . (defined('APP_NAME') ? APP_NAME : 'GlobexSky') . '!',
        $html
    );
}

/**
 * Send an order confirmation to the buyer.
 */
function sendOrderConfirmation(PDO $db, int $orderId): bool
{
    try {
        $stmt = $db->prepare(
            'SELECT o.*, u.name AS buyer_name, u.email AS buyer_email
             FROM orders o
             JOIN users u ON u.id = o.buyer_id
             WHERE o.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('sendOrderConfirmation DB error: ' . $e->getMessage());
        return false;
    }

    if (!$order) {
        error_log("sendOrderConfirmation: order {$orderId} not found");
        return false;
    }

    // Load order items
    try {
        $iStmt = $db->prepare(
            'SELECT oi.*, p.name AS product_name, p.sku
             FROM order_items oi
             JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = :order_id'
        );
        $iStmt->execute([':order_id' => $orderId]);
        $order['items'] = $iStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $order['items'] = [];
    }

    $html = emailOrderConfirmation($order);

    return sendEmail(
        $order['buyer_email'],
        'Order Confirmation #' . $orderId,
        $html
    );
}

/**
 * Send a password-reset email with a signed token link.
 */
function sendPasswordReset(PDO $db, int $userId, string $token): bool
{
    try {
        $stmt = $db->prepare('SELECT name, email FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('sendPasswordReset DB error: ' . $e->getMessage());
        return false;
    }

    if (!$user) {
        error_log("sendPasswordReset: user {$userId} not found");
        return false;
    }

    $baseUrl  = defined('APP_URL') ? APP_URL : '';
    $resetUrl = $baseUrl . '/pages/reset-password.php?token=' . urlencode($token);
    $html     = emailPasswordReset($user['name'], $resetUrl);

    return sendEmail(
        $user['email'],
        'Reset Your Password — ' . (defined('APP_NAME') ? APP_NAME : 'GlobexSky'),
        $html
    );
}

/**
 * Notify a user by email that they have received a new chat message.
 */
function sendNewMessageNotification(PDO $db, int $userId, string $senderName, string $preview): bool
{
    try {
        $stmt = $db->prepare('SELECT name, email FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('sendNewMessageNotification DB error: ' . $e->getMessage());
        return false;
    }

    if (!$user) {
        error_log("sendNewMessageNotification: user {$userId} not found");
        return false;
    }

    $baseUrl    = defined('APP_URL') ? APP_URL : '';
    $messageUrl = $baseUrl . '/pages/messages.php';
    $html       = emailNewMessage($user['name'], $senderName, $preview, $messageUrl);

    return sendEmail(
        $user['email'],
        "New message from {$senderName} — " . (defined('APP_NAME') ? APP_NAME : 'GlobexSky'),
        $html
    );
}

// ── PR #22 additions ───────────────────────────────────────────────────────

/**
 * Send a raw HTML email without using a named template.
 *
 * @param string|array $to       Recipient address or ['email' => 'Name'] map
 * @param string       $subject  Email subject
 * @param string       $htmlBody Full HTML body
 * @param string       $textBody Plain-text alternative (auto-stripped if empty)
 * @return bool
 */
function sendRawEmail(string|array $to, string $subject, string $htmlBody, string $textBody = ''): bool
{
    return sendEmail($to, $subject, $htmlBody, $textBody);
}

/**
 * Send the same email to multiple recipients in a loop.
 *
 * Each recipient may override $data by supplying an array entry that is itself
 * an array with keys 'email', 'name', and optionally 'data'.
 *
 * @param array  $recipients  [['email'=>'a@b.com','name'=>'Alice','data'=>[…]], …]
 * @param string $subject     Email subject
 * @param string $htmlBody    Rendered HTML body (use placeholder-replaced content)
 * @param string $textBody    Plain-text alternative
 * @return int  Number of successful deliveries
 */
function sendBulkEmail(array $recipients, string $subject, string $htmlBody, string $textBody = ''): int
{
    $sent = 0;
    foreach ($recipients as $r) {
        $email = is_array($r) ? ($r['email'] ?? '') : (string)$r;
        if (!$email) {
            continue;
        }
        if (sendEmail($email, $subject, $htmlBody, $textBody)) {
            $sent++;
        }
    }
    return $sent;
}

/**
 * Add an email to the outbound queue (processed by cron).
 *
 * @param PDO    $db       Database connection
 * @param string $toEmail  Recipient email address
 * @param string $toName   Recipient display name
 * @param string $subject  Email subject
 * @param string $template Template identifier (filename without extension)
 * @param array  $data     Template variables
 * @return bool
 */
function queueEmail(PDO $db, string $toEmail, string $toName, string $subject, string $template, array $data = []): bool
{
    try {
        $stmt = $db->prepare(
            'INSERT INTO email_queue (to_email, to_name, subject, template, data_json)
             VALUES (:to_email, :to_name, :subject, :template, :data_json)'
        );
        return $stmt->execute([
            ':to_email'  => $toEmail,
            ':to_name'   => $toName,
            ':subject'   => $subject,
            ':template'  => $template,
            ':data_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
        ]);
    } catch (PDOException $e) {
        error_log('queueEmail DB error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Process pending emails from the queue (called by a cron job).
 *
 * @param PDO $db    Database connection
 * @param int $limit Maximum number of emails to process per run
 * @return array ['sent' => int, 'failed' => int]
 */
function processEmailQueue(PDO $db, int $limit = 50): array
{
    $sent   = 0;
    $failed = 0;

    try {
        $stmt = $db->prepare(
            "SELECT * FROM email_queue
             WHERE status = 'pending' AND attempts < 3
             ORDER BY created_at ASC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('processEmailQueue fetch error: ' . $e->getMessage());
        return ['sent' => 0, 'failed' => 0];
    }

    foreach ($rows as $row) {
        // Mark as processing
        try {
            $db->prepare("UPDATE email_queue SET status='processing', attempts=attempts+1 WHERE id=:id")
               ->execute([':id' => $row['id']]);
        } catch (PDOException $e) {
            continue;
        }

        $htmlBody = (string)($row['html_body'] ?? '');
        $success  = false;

        if ($htmlBody !== '') {
            $success = sendEmail($row['to_email'], $row['subject'], $htmlBody);
        } else {
            error_log("processEmailQueue: no html_body for queue id={$row['id']}");
        }

        $newStatus = $success ? 'sent' : 'failed';
        $sentAt    = $success ? date('Y-m-d H:i:s') : null;

        try {
            $upd = $db->prepare(
                "UPDATE email_queue
                 SET status=:status, sent_at=:sent_at
                 WHERE id=:id"
            );
            $upd->execute([
                ':status'  => $newStatus,
                ':sent_at' => $sentAt,
                ':id'      => $row['id'],
            ]);
        } catch (PDOException $e) {
            error_log('processEmailQueue update error: ' . $e->getMessage());
        }

        // Log delivery
        _logEmailDelivery(
            $db,
            $row['to_email'],
            $row['to_name'] ?? '',
            $row['subject'],
            $row['template'] ?? '',
            $success ? 'sent' : 'failed',
            $success ? 'OK' : 'send failed'
        );

        $success ? $sent++ : $failed++;
    }

    return ['sent' => $sent, 'failed' => $failed];
}

/**
 * Retrieve the email delivery log with optional filters.
 *
 * @param PDO    $db      Database connection
 * @param array  $filters ['status'=>'sent|failed', 'template'=>'...', 'to_email'=>'...']
 * @param int    $page    1-based page number
 * @param int    $perPage Results per page
 * @return array ['data' => rows, 'total' => int, 'pages' => int]
 */
function getEmailLog(PDO $db, array $filters = [], int $page = 1, int $perPage = 25): array
{
    $where  = [];
    $params = [];

    if (!empty($filters['status'])) {
        $where[]            = 'status = :status';
        $params[':status']  = $filters['status'];
    }
    if (!empty($filters['template'])) {
        $where[]             = 'template = :template';
        $params[':template'] = $filters['template'];
    }
    if (!empty($filters['to_email'])) {
        $where[]              = 'to_email LIKE :to_email';
        $params[':to_email']  = '%' . $filters['to_email'] . '%';
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $offset      = max(0, ($page - 1) * $perPage);

    try {
        $countStmt = $db->prepare("SELECT COUNT(*) FROM email_logs {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $dataStmt = $db->prepare(
            "SELECT * FROM email_logs {$whereClause}
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            $dataStmt->bindValue($k, $v);
        }
        $dataStmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $dataStmt->execute();
        $data = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('getEmailLog DB error: ' . $e->getMessage());
        return ['data' => [], 'total' => 0, 'pages' => 0];
    }

    return [
        'data'  => $data,
        'total' => $total,
        'pages' => max(1, (int)ceil($total / $perPage)),
    ];
}

/**
 * Test SMTP connectivity by sending a test email.
 *
 * @param string $testAddress  Address to send the test to (defaults to MAIL_FROM_EMAIL)
 * @return array ['success' => bool, 'message' => string]
 */
function testSmtp(string $testAddress = ''): array
{
    if (!$testAddress) {
        $testAddress = defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : '';
    }

    if (!$testAddress) {
        return ['success' => false, 'message' => 'No test address provided and MAIL_FROM_EMAIL is not configured.'];
    }

    $appName = defined('APP_NAME') ? APP_NAME : 'GlobexSky';
    $subject = "[{$appName}] SMTP Test — " . date('Y-m-d H:i:s');
    $body    = "<p>This is an automated SMTP test from <strong>{$appName}</strong>. "
             . "If you received this email, your SMTP configuration is working correctly.</p>";

    $ok = sendEmail($testAddress, $subject, $body, strip_tags($body));

    return [
        'success' => $ok,
        'message' => $ok
            ? "Test email sent successfully to {$testAddress}."
            : "Failed to send test email. Check PHP error log for details.",
    ];
}

/**
 * Internal helper: log an email delivery to the email_logs table.
 *
 * @param PDO    $db
 * @param string $toEmail
 * @param string $toName
 * @param string $subject
 * @param string $template
 * @param string $status   'sent' or 'failed'
 * @param string $smtpResponse
 * @return void
 */
function _logEmailDelivery(
    PDO    $db,
    string $toEmail,
    string $toName,
    string $subject,
    string $template,
    string $status,
    string $smtpResponse = ''
): void {
    try {
        $stmt = $db->prepare(
            'INSERT INTO email_logs (to_email, to_name, subject, template, status, smtp_response)
             VALUES (:to_email, :to_name, :subject, :template, :status, :smtp_response)'
        );
        $stmt->execute([
            ':to_email'      => $toEmail,
            ':to_name'       => $toName,
            ':subject'       => substr($subject, 0, 500),
            ':template'      => substr($template, 0, 100),
            ':status'        => $status,
            ':smtp_response' => substr($smtpResponse, 0, 1000),
        ]);
    } catch (PDOException $e) {
        error_log('_logEmailDelivery DB error: ' . $e->getMessage());
    }
}
