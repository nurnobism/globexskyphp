<?php
/**
 * Database Configuration
 * Update these values with your Namecheap cPanel MySQL credentials
 */

/**
 * Require an environment variable; die with an error if it is not set.
 */
function requireEnvVar(string $name): string {
    $value = getenv($name);
    if ($value === false) {
        error_log('FATAL: ' . $name . ' environment variable not set');
        die(json_encode(['error' => 'Server configuration error']));
    }
    return $value;
}

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', requireEnvVar('DB_NAME'));
define('DB_USER', requireEnvVar('DB_USER'));
define('DB_PASS', requireEnvVar('DB_PASS'));
define('DB_CHARSET', 'utf8mb4');

/**
 * Get PDO database connection (singleton)
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed']));
        }
    }
    return $pdo;
}
