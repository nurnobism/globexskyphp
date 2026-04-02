<?php
// config/mail.php — SMTP Email Config (PHPMailer)

define('MAIL_HOST', getenv('MAIL_HOST') ?: 'mail.globexsky.com');
define('MAIL_PORT', (int)(getenv('MAIL_PORT') ?: 587));
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: 'noreply@globexsky.com');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION') ?: 'tls');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Globex Sky');
define('MAIL_FROM_EMAIL', getenv('MAIL_FROM_EMAIL') ?: 'noreply@globexsky.com');
