<?php
// config/app.php — Site Settings

define('APP_NAME', 'Globex Sky');
define('APP_URL', getenv('APP_URL') ?: 'https://globexsky.com');
define('APP_ENV', getenv('APP_ENV') ?: 'production');
define('APP_DEBUG', getenv('APP_DEBUG') === 'true');
define('APP_TIMEZONE', 'UTC');
define('APP_LOCALE', 'en');
define('APP_CURRENCY', 'USD');
define('APP_VERSION', '1.0.0');

define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('UPLOAD_PATH', __DIR__ . '/../assets/uploads/');
define('UPLOAD_URL', APP_URL . '/assets/uploads/');

define('SESSION_LIFETIME', 7200); // 2 hours
define('CSRF_TOKEN_LENGTH', 32);

define('SUPPORTED_LANGUAGES', ['en', 'bn', 'ar', 'zh', 'hi', 'fr', 'es']);
define('SUPPORTED_CURRENCIES', ['USD', 'BDT', 'EUR', 'GBP', 'CNY', 'INR', 'AED']);

date_default_timezone_set(APP_TIMEZONE);

if (APP_DEBUG) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
