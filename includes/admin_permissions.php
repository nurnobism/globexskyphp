<?php
/**
 * includes/admin_permissions.php — Admin Role & Permission System
 */

/**
 * Check if an admin user has a specific permission.
 * super_admin always has all permissions.
 */
function hasAdminPermission(int $adminId, string $permission): bool {
    try {
        $db   = getDB();
        $stmt = $db->prepare('SELECT role FROM users WHERE id = ?');
        $stmt->execute([$adminId]);
        $row = $stmt->fetch();

        if (!$row) {
            return false;
        }

        if ($row['role'] === 'super_admin') {
            return true;
        }

        $stmt = $db->prepare(
            'SELECT 1 FROM admin_permissions WHERE role = ? AND permission_key = ? LIMIT 1'
        );
        $stmt->execute([$row['role'], $permission]);

        return (bool) $stmt->fetch();
    } catch (PDOException $e) {
        error_log('hasAdminPermission error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get all permissions for a given role.
 */
function getAdminPermissions(string $role): array {
    try {
        $db   = getDB();
        $stmt = $db->prepare('SELECT permission_key FROM admin_permissions WHERE role = ?');
        $stmt->execute([$role]);
        return array_column($stmt->fetchAll(), 'permission_key');
    } catch (PDOException $e) {
        error_log('getAdminPermissions error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Require a specific admin permission; die with 403 if not authorized.
 */
function requireAdminPermission(string $permission): void {
    requireRole(['admin', 'super_admin']);

    $adminId = (int) ($_SESSION['user_id'] ?? 0);

    if (!hasAdminPermission($adminId, $permission)) {
        http_response_code(403);
        die(
            '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
            . '<title>403 — Forbidden</title>'
            . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">'
            . '</head><body>'
            . '<div class="container py-5 text-center">'
            . '<h2 class="text-danger">403 — Access Denied</h2>'
            . '<p>You do not have the <strong>' . htmlspecialchars($permission, ENT_QUOTES, 'UTF-8') . '</strong> permission.</p>'
            . '<a href="/" class="btn btn-primary">Go Home</a>'
            . '</div></body></html>'
        );
    }
}

/**
 * Get system setting value.
 * Returns default if not found.
 * Results are cached in a static array for the request lifetime.
 */
function getSystemSetting(string $key, string $default = ''): string {
    $cache = &getSystemSettingCache();

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $db   = getDB();
        $stmt = $db->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        $cache[$key] = $row !== false ? (string) $row['setting_value'] : $default;
    } catch (PDOException $e) {
        error_log('getSystemSetting error: ' . $e->getMessage());
        $cache[$key] = $default;
    }

    return $cache[$key];
}

/**
 * Update a system setting.
 */
function updateSystemSetting(string $key, string $value, int $updatedBy = 0): bool {
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'INSERT INTO system_settings (setting_key, setting_value, updated_by)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 setting_value = VALUES(setting_value),
                 updated_at    = NOW(),
                 updated_by    = VALUES(updated_by)'
        );
        $stmt->execute([$key, $value, $updatedBy ?: null]);

        // Invalidate cached value so subsequent reads reflect the update
        $cache = &getSystemSettingCache();
        unset($cache[$key]);

        return true;
    } catch (PDOException $e) {
        error_log('updateSystemSetting error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Returns a reference to the static cache used by getSystemSetting,
 * allowing updateSystemSetting to invalidate stale entries.
 *
 * @internal
 */
function &getSystemSettingCache(): array {
    static $cache = [];
    return $cache;
}

/**
 * requireAdmin() — backward-compatible guard requiring admin or super_admin role.
 * Only declared if not already defined (e.g. by includes/auth.php).
 */
if (!function_exists('requireAdmin')) {
    function requireAdmin(): void {
        requireRole(['admin', 'super_admin']);
    }
}
