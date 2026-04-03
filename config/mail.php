<?php
/**
 * Mail Configuration
 * Uses PHPMailer if available, otherwise PHP mail()
 */

/**
 * Get an environment variable, logging a warning and returning an empty string if not set.
 */
function getEnvWithWarning(string $name): string {
    $value = getenv($name);
    if ($value === false) {
        error_log('WARNING: ' . $name . ' environment variable not set');
        return '';
    }
    return $value;
}

define('MAIL_HOST',       getEnvWithWarning('MAIL_HOST'));
define('MAIL_PORT',       getenv('MAIL_PORT')       ?: '587');
define('MAIL_USERNAME',   getEnvWithWarning('MAIL_USERNAME'));
define('MAIL_PASSWORD',   getEnvWithWarning('MAIL_PASSWORD'));
define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION') ?: 'tls');
define('MAIL_FROM_NAME',  getenv('MAIL_FROM_NAME')  ?: APP_NAME);
define('MAIL_FROM_EMAIL', getEnvWithWarning('MAIL_FROM_EMAIL'));

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
