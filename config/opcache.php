<?php
/**
 * OPcache Configuration Helper
 *
 * Recommended settings for Namecheap shared hosting (PHP 8.x).
 * Note: On shared hosting you cannot change opcache.* INI settings at
 * runtime — use a php.ini / .user.ini file in the document root or
 * contact your host.  This file exposes helpers for status checks and
 * conditional cache clearing from admin tooling.
 */

/**
 * Return the recommended OPcache php.ini / .user.ini settings.
 */
function getOpcacheConfig(): array
{
    return [
        'opcache.enable'                 => 1,
        'opcache.enable_cli'             => 0,
        'opcache.memory_consumption'     => 128,
        'opcache.interned_strings_buffer'=> 16,
        'opcache.max_accelerated_files'  => 10000,
        'opcache.revalidate_freq'        => 60,
        'opcache.validate_timestamps'    => 1,
        'opcache.fast_shutdown'          => 1,
        'opcache.save_comments'          => 1,
        'opcache.max_wasted_percentage'  => 10,
        'opcache.use_cwd'                => 1,
    ];
}

/**
 * Return current OPcache status statistics.
 * Returns an array with a 'disabled' key when OPcache is not loaded.
 */
function getOpcacheStatus(): array
{
    if (!function_exists('opcache_get_status')) {
        return ['disabled' => true];
    }

    $status = opcache_get_status(false);
    if ($status === false) {
        return ['disabled' => true, 'reason' => 'opcache_get_status returned false'];
    }

    return [
        'disabled'          => false,
        'enabled'           => $status['opcache_enabled'] ?? false,
        'cache_full'        => $status['cache_full'] ?? false,
        'restart_pending'   => $status['restart_pending'] ?? false,
        'memory'            => $status['memory_usage'] ?? [],
        'statistics'        => $status['opcache_statistics'] ?? [],
        'interned_strings'  => $status['interned_strings_usage'] ?? [],
    ];
}

/**
 * Clear OPcache if the application configuration has changed since the
 * last reset, or if forced.
 *
 * Uses a sentinel file in /tmp to track when the config last changed.
 *
 * @param bool $force  If true, always reset the cache.
 * @return bool        True if the cache was reset, false otherwise.
 */
function clearOpcacheIfNeeded(bool $force = false): bool
{
    if (!function_exists('opcache_reset')) {
        return false;
    }

    $sentinelFile = sys_get_temp_dir() . '/globexsky_opcache_sentinel';
    $configFiles  = [
        __DIR__ . '/app.php',
        __DIR__ . '/database.php',
        __DIR__ . '/ai.php',
    ];

    // Determine the latest modification time across config files
    $latestMtime = 0;
    foreach ($configFiles as $file) {
        if (is_file($file)) {
            $latestMtime = max($latestMtime, filemtime($file));
        }
    }

    $sentinelRaw   = is_file($sentinelFile) ? file_get_contents($sentinelFile) : false;
    $sentinelMtime = ($sentinelRaw !== false) ? (int) $sentinelRaw : 0;

    if ($force || $latestMtime > $sentinelMtime) {
        opcache_reset();
        file_put_contents($sentinelFile, (string) $latestMtime, LOCK_EX);
        return true;
    }

    return false;
}
