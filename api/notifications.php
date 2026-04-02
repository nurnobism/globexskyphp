<?php
require_once __DIR__ . '/../includes/middleware.php';
header('Content-Type: application/json');
$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOut($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

$defaultPreferences = [
    'order_update'  => ['email' => true,  'push' => true,  'sms' => false],
    'new_message'   => ['email' => true,  'push' => true,  'sms' => false],
    'promotion'     => ['email' => true,  'push' => false, 'sms' => false],
    'system'        => ['email' => true,  'push' => true,  'sms' => false],
    'payment'       => ['email' => true,  'push' => true,  'sms' => true],
];

switch ($action) {
    case 'list':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $stmt = $db->prepare(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50'
        );
        $stmt->execute([$userId]);
        $notifications = $stmt->fetchAll();
        $unreadStmt = $db->prepare(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL'
        );
        $unreadStmt->execute([$userId]);
        $unreadCount = (int) $unreadStmt->fetchColumn();
        jsonOut(['success' => true, 'data' => $notifications, 'unread_count' => $unreadCount]);
        break;

    case 'mark_read':
        requireAuth();
        validateCsrf();
        $userId = $_SESSION['user_id'];
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            jsonOut(['success' => false, 'message' => 'Notification ID required'], 400);
        }
        $stmt = $db->prepare(
            'UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$id, $userId]);
        if (!$stmt->rowCount()) {
            jsonOut(['success' => false, 'message' => 'Notification not found'], 404);
        }
        jsonOut(['success' => true, 'message' => 'Notification marked as read']);
        break;

    case 'mark_all_read':
        requireAuth();
        validateCsrf();
        $userId = $_SESSION['user_id'];
        $stmt = $db->prepare(
            'UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL'
        );
        $stmt->execute([$userId]);
        jsonOut(['success' => true, 'updated' => $stmt->rowCount()]);
        break;

    case 'get_preferences':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $stmt = $db->prepare('SELECT * FROM notification_preferences WHERE user_id = ?');
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();
        if (empty($rows)) {
            jsonOut(['success' => true, 'data' => $defaultPreferences, 'is_default' => true]);
        }
        $prefs = [];
        foreach ($rows as $row) {
            $prefs[$row['type']] = [
                'email' => (bool) $row['email'],
                'push'  => (bool) $row['push'],
                'sms'   => (bool) $row['sms'],
            ];
        }
        jsonOut(['success' => true, 'data' => $prefs, 'is_default' => false]);
        break;

    case 'update_preferences':
        requireAuth();
        validateCsrf();
        $userId = $_SESSION['user_id'];
        $preferences = $_POST['preferences'] ?? [];
        if (is_string($preferences)) {
            $preferences = json_decode($preferences, true) ?? [];
        }
        $validTypes = array_keys($defaultPreferences);
        $stmt = $db->prepare(
            'INSERT INTO notification_preferences (user_id, type, email, push, sms)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE email = VALUES(email), push = VALUES(push), sms = VALUES(sms)'
        );
        foreach ($validTypes as $type) {
            if (isset($preferences[$type])) {
                $pref  = $preferences[$type];
                $email = isset($pref['email']) ? (int) (bool) $pref['email'] : 0;
                $push  = isset($pref['push'])  ? (int) (bool) $pref['push']  : 0;
                $sms   = isset($pref['sms'])   ? (int) (bool) $pref['sms']   : 0;
                $stmt->execute([$userId, $type, $email, $push, $sms]);
            }
        }
        jsonOut(['success' => true, 'message' => 'Preferences updated']);
        break;

    default:
        jsonOut(['success' => false, 'message' => 'Invalid action'], 400);
}
