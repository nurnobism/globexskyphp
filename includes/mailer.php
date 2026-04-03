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

// Load email templates
require_once __DIR__ . '/../templates/emails/base.php';
require_once __DIR__ . '/../templates/emails/welcome.php';
require_once __DIR__ . '/../templates/emails/order-confirmation.php';
require_once __DIR__ . '/../templates/emails/password-reset.php';
require_once __DIR__ . '/../templates/emails/new-message.php';

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
