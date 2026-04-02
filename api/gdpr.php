<?php
require_once __DIR__ . '/../includes/middleware.php';
header('Content-Type: application/json');
$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOut($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

switch ($action) {
    case 'request_export':
        requireAuth();
        validateCsrf();
        $uid = $_SESSION['user_id'];

        // Prevent duplicate pending requests
        $dup = $db->prepare(
            "SELECT id FROM gdpr_requests WHERE user_id = ? AND type = 'export' AND status = 'pending'"
        );
        $dup->execute([$uid]);
        if ($dup->fetch()) {
            jsonOut(['success' => false, 'message' => 'An export request is already pending'], 409);
        }

        $stmt = $db->prepare(
            "INSERT INTO gdpr_requests (user_id, type, status, created_at) VALUES (?, 'export', 'pending', NOW())"
        );
        $stmt->execute([$uid]);
        jsonOut(['success' => true, 'id' => $db->lastInsertId(), 'message' => 'Export request submitted']);
        break;

    case 'request_delete':
        requireAuth();
        validateCsrf();
        $uid      = $_SESSION['user_id'];
        $password = $_POST['password'] ?? '';

        if (!$password) {
            jsonOut(['success' => false, 'message' => 'Password confirmation is required'], 422);
        }

        // Verify password against stored hash
        $userRow = $db->prepare('SELECT password FROM users WHERE id = ?');
        $userRow->execute([$uid]);
        $user = $userRow->fetch();
        if (!$user || !password_verify($password, $user['password'])) {
            jsonOut(['success' => false, 'message' => 'Password incorrect'], 403);
        }

        // Prevent duplicate pending delete requests
        $dup = $db->prepare(
            "SELECT id FROM gdpr_requests WHERE user_id = ? AND type = 'delete' AND status = 'pending'"
        );
        $dup->execute([$uid]);
        if ($dup->fetch()) {
            jsonOut(['success' => false, 'message' => 'A deletion request is already pending'], 409);
        }

        $stmt = $db->prepare(
            "INSERT INTO gdpr_requests (user_id, type, status, created_at) VALUES (?, 'delete', 'pending', NOW())"
        );
        $stmt->execute([$uid]);
        jsonOut(['success' => true, 'id' => $db->lastInsertId(), 'message' => 'Deletion request submitted']);
        break;

    case 'list_requests':
        requireAuth();
        $uid  = $_SESSION['user_id'];
        $stmt = $db->prepare(
            'SELECT * FROM gdpr_requests WHERE user_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$uid]);
        jsonOut(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'update_consent':
        requireAuth();
        validateCsrf();
        $uid         = $_SESSION['user_id'];
        $consentType = sanitize($_POST['consent_type'] ?? '');
        $status      = sanitize($_POST['status'] ?? '');
        $ip          = $_SERVER['REMOTE_ADDR'] ?? '';

        if (!$consentType || !$status) {
            jsonOut(['success' => false, 'message' => 'consent_type and status are required'], 422);
        }
        $allowed = ['granted', 'withdrawn'];
        if (!in_array($status, $allowed)) {
            jsonOut(['success' => false, 'message' => 'status must be one of: ' . implode(', ', $allowed)], 422);
        }

        $stmt = $db->prepare(
            'INSERT INTO consent_logs (user_id, consent_type, status, ip, created_at) VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$uid, $consentType, $status, $ip]);
        jsonOut(['success' => true, 'id' => $db->lastInsertId(), 'message' => 'Consent updated']);
        break;

    default:
        jsonOut(['success' => false, 'message' => 'Invalid action'], 400);
}
