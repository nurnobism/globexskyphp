<?php
/**
 * Mail Configuration
 * Uses PHPMailer if available, otherwise PHP mail()
 */

define('MAIL_HOST',       getenv('MAIL_HOST')       ?: 'smtp.yourdomain.com');
define('MAIL_PORT',       getenv('MAIL_PORT')        ?: '587');
define('MAIL_USERNAME',   getenv('MAIL_USERNAME')    ?: 'noreply@yourdomain.com');
define('MAIL_PASSWORD',   getenv('MAIL_PASSWORD')    ?: 'your_email_password');
define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION')  ?: 'tls');
define('MAIL_FROM_NAME',  getenv('MAIL_FROM_NAME')   ?: APP_NAME);
define('MAIL_FROM_EMAIL', getenv('MAIL_FROM_EMAIL')  ?: 'noreply@yourdomain.com');

/**
 * Send an email using PHP mail() with basic headers
 */
function sendMail(string $to, string $subject, string $htmlBody, string $plainBody = ''): bool {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . MAIL_FROM_EMAIL . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    return mail($to, $subject, $htmlBody, $headers);
}
