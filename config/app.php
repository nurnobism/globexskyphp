<?php
/**
 * Application Configuration
 */

define('APP_NAME',    getenv('APP_NAME')    ?: 'GlobexSky');
define('APP_URL',     getenv('APP_URL')     ?: 'https://yourdomain.com');
define('APP_VERSION', '1.0.0');
define('APP_ENV',     getenv('APP_ENV')     ?: 'production');
define('APP_DEBUG',   getenv('APP_DEBUG')   === 'true');

// Session settings
define('SESSION_NAME',     'globexsky_session');
define('SESSION_LIFETIME', 86400); // 24 hours

// Pagination
define('ITEMS_PER_PAGE', 20);

// Upload settings
define('UPLOAD_MAX_SIZE',  10 * 1024 * 1024); // 10 MB
define('UPLOAD_DIR',       __DIR__ . '/../assets/uploads/');
define('UPLOAD_URL',       APP_URL . '/assets/uploads/');
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Timezone
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'UTC');

// Error display
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}
