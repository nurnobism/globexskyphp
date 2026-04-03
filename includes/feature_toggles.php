<?php
/**
 * includes/feature_toggles.php — Feature Toggle System (Phase 9)
 */

/** Check if a platform feature is enabled */
function isFeatureEnabled(string $featureName): bool {
    static $cache = [];
    if (isset($cache[$featureName])) {
        return $cache[$featureName];
    }
    try {
        $db   = getDB();
        $stmt = $db->prepare('SELECT is_enabled FROM platform_features WHERE feature_name = ?');
        $stmt->execute([$featureName]);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        $result = $row ? (bool)$row['is_enabled'] : true; // default enabled if not configured
        $cache[$featureName] = $result;
        return $result;
    } catch (PDOException $e) {
        return true; // fail open — feature enabled by default
    }
}

/** Toggle a feature (admin only) */
function setFeatureEnabled(string $featureName, bool $enabled, int $adminId): bool {
    try {
        $db = getDB();
        $db->prepare(
            'INSERT INTO platform_features (feature_name, is_enabled, updated_by, updated_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE is_enabled=?, updated_by=?, updated_at=NOW()'
        )->execute([$featureName, (int)$enabled, $adminId, (int)$enabled, $adminId]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/** Get all feature toggles */
function getAllFeatureToggles(): array {
    try {
        $db   = getDB();
        $stmt = $db->query('SELECT * FROM platform_features ORDER BY feature_name ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}
