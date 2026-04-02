<?php
require_once __DIR__ . '/../includes/middleware.php';
header('Content-Type: application/json');
$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOut($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

switch ($action) {
    case 'list':
        requireAuth();
        $uid  = $_SESSION['user_id'];
        $stmt = $db->prepare(
            'SELECT DISTINCT m.*
             FROM meetings m
             LEFT JOIN meeting_participants mp ON mp.meeting_id = m.id
             WHERE mp.user_id = ? OR m.organizer_id = ?
             ORDER BY m.start_time ASC'
        );
        $stmt->execute([$uid, $uid]);
        jsonOut(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'detail':
        requireAuth();
        $uid = $_SESSION['user_id'];
        $id  = sanitize($_GET['id'] ?? $_POST['id'] ?? '');

        if (!$id) {
            jsonOut(['success' => false, 'message' => 'id is required'], 422);
        }

        $mStmt = $db->prepare(
            'SELECT * FROM meetings WHERE id = ?
             AND (organizer_id = ? OR id IN (
                 SELECT meeting_id FROM meeting_participants WHERE user_id = ?
             ))'
        );
        $mStmt->execute([$id, $uid, $uid]);
        $meeting = $mStmt->fetch();
        if (!$meeting) {
            jsonOut(['success' => false, 'message' => 'Meeting not found or access denied'], 404);
        }

        $pStmt = $db->prepare(
            'SELECT mp.*, u.name, u.email AS user_email
             FROM meeting_participants mp
             LEFT JOIN users u ON u.id = mp.user_id
             WHERE mp.meeting_id = ?'
        );
        $pStmt->execute([$id]);
        $meeting['participants'] = $pStmt->fetchAll();

        jsonOut(['success' => true, 'data' => $meeting]);
        break;

    case 'create':
        requireAuth();
        validateCsrf();
        $uid        = $_SESSION['user_id'];
        $title      = sanitize($_POST['title'] ?? '');
        $desc       = sanitize($_POST['description'] ?? '');
        $startTime  = sanitize($_POST['start_time'] ?? '');
        $endTime    = sanitize($_POST['end_time'] ?? '');
        $meetingUrl = sanitize($_POST['meeting_url'] ?? '');

        if (!$title || !$startTime || !$endTime) {
            jsonOut(['success' => false, 'message' => 'title, start_time, and end_time are required'], 422);
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'INSERT INTO meetings (organizer_id, title, description, start_time, end_time, meeting_url, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, \'scheduled\', NOW())'
            );
            $stmt->execute([$uid, $title, $desc, $startTime, $endTime, $meetingUrl]);
            $meetingId = $db->lastInsertId();

            // Add organizer as participant
            $pStmt = $db->prepare(
                "INSERT INTO meeting_participants (meeting_id, user_id, status, created_at)
                 VALUES (?, ?, 'accepted', NOW())"
            );
            $pStmt->execute([$meetingId, $uid]);

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            jsonOut(['success' => false, 'message' => 'Failed to create meeting'], 500);
        }

        jsonOut(['success' => true, 'id' => $meetingId]);
        break;

    case 'update':
        requireAuth();
        validateCsrf();
        $uid        = $_SESSION['user_id'];
        $id         = sanitize($_POST['id'] ?? '');
        $title      = sanitize($_POST['title'] ?? '');
        $desc       = sanitize($_POST['description'] ?? '');
        $startTime  = sanitize($_POST['start_time'] ?? '');
        $endTime    = sanitize($_POST['end_time'] ?? '');
        $meetingUrl = sanitize($_POST['meeting_url'] ?? '');

        if (!$id) {
            jsonOut(['success' => false, 'message' => 'id is required'], 422);
        }

        $check = $db->prepare('SELECT * FROM meetings WHERE id = ? AND organizer_id = ?');
        $check->execute([$id, $uid]);
        if (!$check->fetch()) {
            jsonOut(['success' => false, 'message' => 'Meeting not found or you are not the organizer'], 404);
        }

        $fields = [];
        $params = [];
        if ($title !== '')      { $fields[] = 'title = ?';       $params[] = $title; }
        if ($desc !== '')       { $fields[] = 'description = ?'; $params[] = $desc; }
        if ($startTime !== '')  { $fields[] = 'start_time = ?';  $params[] = $startTime; }
        if ($endTime !== '')    { $fields[] = 'end_time = ?';    $params[] = $endTime; }
        if ($meetingUrl !== '') { $fields[] = 'meeting_url = ?'; $params[] = $meetingUrl; }

        if (empty($fields)) {
            jsonOut(['success' => false, 'message' => 'No fields to update'], 422);
        }

        $params[] = $id;
        $stmt = $db->prepare('UPDATE meetings SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = ?');
        $stmt->execute($params);
        jsonOut(['success' => true, 'message' => 'Meeting updated']);
        break;

    case 'cancel':
        requireAuth();
        validateCsrf();
        $uid = $_SESSION['user_id'];
        $id  = sanitize($_POST['id'] ?? '');

        if (!$id) {
            jsonOut(['success' => false, 'message' => 'id is required'], 422);
        }

        $check = $db->prepare('SELECT * FROM meetings WHERE id = ? AND organizer_id = ?');
        $check->execute([$id, $uid]);
        $meeting = $check->fetch();
        if (!$meeting) {
            jsonOut(['success' => false, 'message' => 'Meeting not found or you are not the organizer'], 404);
        }
        if ($meeting['status'] === 'cancelled') {
            jsonOut(['success' => false, 'message' => 'Meeting is already cancelled'], 409);
        }

        $stmt = $db->prepare("UPDATE meetings SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        jsonOut(['success' => true, 'message' => 'Meeting cancelled']);
        break;

    case 'add_participant':
        requireAuth();
        validateCsrf();
        $uid       = $_SESSION['user_id'];
        $meetingId = sanitize($_POST['meeting_id'] ?? '');
        $guestId   = sanitize($_POST['user_id'] ?? '');
        $email     = sanitize($_POST['email'] ?? '');

        if (!$meetingId || (!$guestId && !$email)) {
            jsonOut(['success' => false, 'message' => 'meeting_id and at least user_id or email are required'], 422);
        }

        // Only organizer can add participants
        $check = $db->prepare('SELECT id FROM meetings WHERE id = ? AND organizer_id = ?');
        $check->execute([$meetingId, $uid]);
        if (!$check->fetch()) {
            jsonOut(['success' => false, 'message' => 'Meeting not found or you are not the organizer'], 404);
        }

        $stmt = $db->prepare(
            "INSERT INTO meeting_participants (meeting_id, user_id, email, status, created_at)
             VALUES (?, ?, ?, 'invited', NOW())"
        );
        $stmt->execute([$meetingId, $guestId ?: null, $email]);
        jsonOut(['success' => true, 'id' => $db->lastInsertId(), 'message' => 'Participant added']);
        break;

    case 'update_participant_status':
        requireAuth();
        validateCsrf();
        $uid       = $_SESSION['user_id'];
        $meetingId = sanitize($_POST['meeting_id'] ?? '');
        $status    = sanitize($_POST['status'] ?? '');

        if (!$meetingId || !$status) {
            jsonOut(['success' => false, 'message' => 'meeting_id and status are required'], 422);
        }
        $allowed = ['accepted', 'declined', 'tentative'];
        if (!in_array($status, $allowed)) {
            jsonOut(['success' => false, 'message' => 'status must be one of: ' . implode(', ', $allowed)], 422);
        }

        $check = $db->prepare(
            'SELECT id FROM meeting_participants WHERE meeting_id = ? AND user_id = ?'
        );
        $check->execute([$meetingId, $uid]);
        if (!$check->fetch()) {
            jsonOut(['success' => false, 'message' => 'You are not a participant of this meeting'], 404);
        }

        $stmt = $db->prepare(
            'UPDATE meeting_participants SET status = ?, updated_at = NOW() WHERE meeting_id = ? AND user_id = ?'
        );
        $stmt->execute([$status, $meetingId, $uid]);
        jsonOut(['success' => true, 'message' => 'Participant status updated']);
        break;

    default:
        jsonOut(['success' => false, 'message' => 'Invalid action'], 400);
}
