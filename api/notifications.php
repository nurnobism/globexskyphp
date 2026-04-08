<?php
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/notifications.php';
header('Content-Type: application/json');
$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOut($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }
function validateCsrf(): void { if (!verifyCsrf()) { jsonOut(['error' => 'Invalid CSRF token'], 403); } }

$defaultPreferences = [
    'order_update'  => ['email' => true,  'push' => true,  'sms' => false, 'in_app' => true],
    'new_message'   => ['email' => true,  'push' => true,  'sms' => false, 'in_app' => true],
    'promotion'     => ['email' => true,  'push' => false, 'sms' => false, 'in_app' => true],
    'system'        => ['email' => true,  'push' => true,  'sms' => false, 'in_app' => true],
    'payment'       => ['email' => true,  'push' => true,  'sms' => true,  'in_app' => true],
    'financial'     => ['email' => true,  'push' => true,  'sms' => false, 'in_app' => true],
    'messages'      => ['email' => true,  'push' => true,  'sms' => false, 'in_app' => true],
    'orders'        => ['email' => true,  'push' => true,  'sms' => false, 'in_app' => true],
];

switch ($action) {
    case 'list':
        requireAuth();
        $userId  = (int) $_SESSION['user_id'];
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
        $tab     = in_array($_GET['tab'] ?? 'all', ['all','unread','orders','financial','messages','system'], true)
                   ? ($_GET['tab'] ?? 'all') : 'all';
        $result      = getNotifications($db, $userId, $page, $perPage, $tab);
        $unreadCount = getUnreadCount($db, $userId);
        jsonOut(['success' => true, 'data' => $result['data'], 'pagination' => [
            'total'     => $result['total'],
            'page'      => $result['page'],
            'per_page'  => $result['per_page'],
            'last_page' => $result['last_page'],
        ], 'unread_count' => $unreadCount]);
        break;

    case 'unread_count':
        requireAuth();
        $userId = (int) $_SESSION['user_id'];
        jsonOut(['success' => true, 'count' => getUnreadCount($db, $userId)]);
        break;

    case 'count':
        requireAuth();
        $userId = (int) $_SESSION['user_id'];
        jsonOut(['success' => true, 'count' => getUnreadCount($db, $userId)]);
        break;

    case 'mark_read':
        requireAuth();
        validateCsrf();
        $userId = (int) $_SESSION['user_id'];
        $id     = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            jsonOut(['success' => false, 'message' => 'Notification ID required'], 400);
        }
        $ok = markAsRead($db, $id, $userId);
        if (!$ok) {
            jsonOut(['success' => false, 'message' => 'Notification not found or already read'], 404);
        }
        jsonOut(['success' => true, 'message' => 'Notification marked as read']);
        break;

    case 'mark_all_read':
        requireAuth();
        validateCsrf();
        $userId = (int) $_SESSION['user_id'];
        markAllAsRead($db, $userId);
        jsonOut(['success' => true, 'message' => 'All notifications marked as read']);
        break;

    case 'delete':
        requireAuth();
        validateCsrf();
        $userId = (int) $_SESSION['user_id'];
        $id     = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            jsonOut(['success' => false, 'message' => 'Notification ID required'], 400);
        }
        $ok = deleteNotification($db, $id, $userId);
        if (!$ok) {
            jsonOut(['success' => false, 'message' => 'Notification not found'], 404);
        }
        jsonOut(['success' => true, 'message' => 'Notification deleted']);
        break;

    case 'clear_all':
        requireAuth();
        validateCsrf();
        $userId = (int) $_SESSION['user_id'];
        clearAll($db, $userId);
        jsonOut(['success' => true, 'message' => 'All notifications cleared']);
        break;

    case 'preferences':
    case 'get_preferences':
    case 'preferences_get':
        requireAuth();
        $userId = (int) $_SESSION['user_id'];
        $stmt   = $db->prepare('SELECT * FROM notification_preferences WHERE user_id = ?');
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            jsonOut(['success' => true, 'data' => $defaultPreferences, 'is_default' => true]);
        }
        $prefs = [];
        foreach ($rows as $row) {
            $eventKey = $row['event_type'] ?? $row['type'] ?? '';
            if ($eventKey === '') {
                continue;
            }
            $prefs[$eventKey] = [
                'in_app' => (bool) ($row['in_app'] ?? 1),
                'email'  => (bool) ($row['email']  ?? 1),
                'push'   => (bool) ($row['push']   ?? 0),
                'sms'    => (bool) ($row['sms']    ?? 0),
            ];
        }
        jsonOut(['success' => true, 'data' => $prefs, 'is_default' => false]);
        break;

    case 'update_preferences':
    case 'preferences_save':
        requireAuth();
        validateCsrf();
        $userId = (int) $_SESSION['user_id'];
        $preferences = $_POST['preferences'] ?? [];
        if (is_string($preferences)) {
            $preferences = json_decode($preferences, true) ?? [];
        }
        $validTypes = array_keys($defaultPreferences);
        $stmt = $db->prepare(
            'INSERT INTO notification_preferences (user_id, event_type, in_app, email, push, sms)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE in_app = VALUES(in_app), email = VALUES(email),
                                     push = VALUES(push), sms = VALUES(sms)'
        );
        foreach ($validTypes as $type) {
            if (isset($preferences[$type])) {
                $pref  = $preferences[$type];
                $inApp = isset($pref['in_app']) ? (int) (bool) $pref['in_app'] : 1;
                $email = isset($pref['email'])  ? (int) (bool) $pref['email']  : 1;
                $push  = isset($pref['push'])   ? (int) (bool) $pref['push']   : 0;
                $sms   = isset($pref['sms'])    ? (int) (bool) $pref['sms']    : 0;
                $stmt->execute([$userId, $type, $inApp, $email, $push, $sms]);
            }
        }
        jsonOut(['success' => true, 'message' => 'Preferences updated']);
        break;

    default:
        jsonOut(['success' => false, 'message' => 'Invalid action'], 400);
}
