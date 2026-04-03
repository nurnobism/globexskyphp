<?php
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/admin_permissions.php';

header('Content-Type: application/json');

requireRole(['admin', 'super_admin']);

$action  = $_GET['action'] ?? '';
$method  = $_SERVER['REQUEST_METHOD'];
$db      = getDB();
$adminId = (int)$_SESSION['user_id'];

switch ($action) {

    // -------------------------------------------------------------------------
    // GET ?action=get
    // -------------------------------------------------------------------------
    case 'get':
        try {
            $stmt = $db->prepare(
                'SELECT setting_key, setting_value, setting_group, description, updated_at
                 FROM system_settings
                 ORDER BY setting_group, setting_key'
            );
            $stmt->execute();
            $rows = $stmt->fetchAll();

            // Group settings by setting_group
            $grouped = [];
            foreach ($rows as $row) {
                $grouped[$row['setting_group']][$row['setting_key']] = [
                    'value'       => $row['setting_value'],
                    'description' => $row['description'],
                    'updated_at'  => $row['updated_at'],
                ];
            }

            echo json_encode(['success' => true, 'settings' => $grouped]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to fetch settings']);
        }
        break;

    // -------------------------------------------------------------------------
    // POST ?action=update
    // -------------------------------------------------------------------------
    case 'update':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
        }
        if (!verifyCsrf()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            break;
        }

        // Accept settings from POST body or JSON body
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $input    = json_decode(file_get_contents('php://input'), true) ?? [];
            $settings = $input['settings'] ?? [];
        } else {
            $settings = $_POST['settings'] ?? [];
        }

        if (!is_array($settings) || count($settings) === 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No settings provided']);
            break;
        }

        try {
            $updated = [];
            $failed  = [];

            foreach ($settings as $key => $value) {
                $key = trim((string)$key);
                if ($key === '') {
                    continue;
                }
                $success = updateSystemSetting($key, (string)$value, $adminId);
                if ($success) {
                    $updated[] = $key;
                } else {
                    $failed[] = $key;
                }
            }

            if (!empty($updated)) {
                logAdminAudit($adminId, 'settings_update', 'setting', 0, [
                    'updated_keys' => $updated,
                ]);
            }

            if (!empty($failed)) {
                echo json_encode([
                    'success' => false,
                    'error'   => 'Some settings could not be saved',
                    'failed'  => $failed,
                    'updated' => $updated,
                ]);
                break;
            }

            echo json_encode(['success' => true, 'message' => 'Settings updated']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to update settings']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
