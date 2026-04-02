<?php
require_once __DIR__ . '/../includes/middleware.php';
header('Content-Type: application/json');
$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOut($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

switch ($action) {
    case 'get_team':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $stmt = $db->prepare(
            'SELECT t.*
             FROM teams t
             JOIN team_members tm ON tm.team_id = t.id
             WHERE tm.user_id = ? AND tm.status = \'active\'
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $team = $stmt->fetch();
        if (!$team) {
            jsonOut(['success' => false, 'message' => 'No team found'], 404);
        }
        jsonOut(['success' => true, 'data' => $team]);
        break;

    case 'create_team':
        requireAuth();
        validateCsrf();
        $userId      = $_SESSION['user_id'];
        $name        = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        if (!$name) {
            jsonOut(['success' => false, 'message' => 'Team name is required'], 400);
        }
        $teamStmt = $db->prepare(
            'INSERT INTO teams (name, description, created_by, created_at) VALUES (?, ?, ?, NOW())'
        );
        $teamStmt->execute([$name, $description, $userId]);
        $teamId = $db->lastInsertId();
        $memberStmt = $db->prepare(
            'INSERT INTO team_members (team_id, user_id, role, status, joined_at)
             VALUES (?, ?, \'owner\', \'active\', NOW())'
        );
        $memberStmt->execute([$teamId, $userId]);
        jsonOut(['success' => true, 'id' => $teamId]);
        break;

    case 'invite_member':
        requireAuth();
        validateCsrf();
        $userId = $_SESSION['user_id'];
        $email  = sanitize($_POST['email'] ?? '');
        $role   = sanitize($_POST['role'] ?? 'member');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonOut(['success' => false, 'message' => 'Valid email is required'], 400);
        }
        $validRoles = ['member', 'admin', 'owner'];
        if (!in_array($role, $validRoles, true)) {
            jsonOut(['success' => false, 'message' => 'Invalid role'], 400);
        }
        $memberStmt = $db->prepare(
            'SELECT tm.role, tm.team_id
             FROM team_members tm
             WHERE tm.user_id = ? AND tm.status = \'active\'
             LIMIT 1'
        );
        $memberStmt->execute([$userId]);
        $currentMember = $memberStmt->fetch();
        if (!$currentMember || !in_array($currentMember['role'], ['admin', 'owner'], true)) {
            jsonOut(['success' => false, 'message' => 'You must be an admin or owner to invite members'], 403);
        }
        $teamId = $currentMember['team_id'];
        $userStmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $userStmt->execute([$email]);
        $invitedUser = $userStmt->fetch();
        $inviteToken = bin2hex(random_bytes(32));
        if ($invitedUser) {
            $checkStmt = $db->prepare(
                'SELECT id FROM team_members WHERE team_id = ? AND user_id = ?'
            );
            $checkStmt->execute([$teamId, $invitedUser['id']]);
            if ($checkStmt->fetch()) {
                jsonOut(['success' => false, 'message' => 'User is already a team member'], 400);
            }
            $insertStmt = $db->prepare(
                'INSERT INTO team_members (team_id, user_id, role, status, invite_token, created_at)
                 VALUES (?, ?, ?, \'invited\', ?, NOW())'
            );
            $insertStmt->execute([$teamId, $invitedUser['id'], $role, $inviteToken]);
        } else {
            $insertStmt = $db->prepare(
                'INSERT INTO team_members (team_id, user_id, role, status, invite_email, invite_token, created_at)
                 VALUES (?, NULL, ?, \'invited\', ?, ?, NOW())'
            );
            $insertStmt->execute([$teamId, $role, $email, $inviteToken]);
        }
        jsonOut(['success' => true, 'invite_token' => $inviteToken]);
        break;

    case 'remove_member':
        requireAuth();
        validateCsrf();
        $userId       = $_SESSION['user_id'];
        $removeUserId = (int) ($_POST['user_id'] ?? 0);
        if (!$removeUserId) {
            jsonOut(['success' => false, 'message' => 'user_id to remove is required'], 400);
        }
        $memberStmt = $db->prepare(
            'SELECT tm.role, tm.team_id
             FROM team_members tm
             WHERE tm.user_id = ? AND tm.status = \'active\'
             LIMIT 1'
        );
        $memberStmt->execute([$userId]);
        $currentMember = $memberStmt->fetch();
        if (!$currentMember || !in_array($currentMember['role'], ['admin', 'owner'], true)) {
            jsonOut(['success' => false, 'message' => 'You must be an admin or owner to remove members'], 403);
        }
        $teamId = $currentMember['team_id'];
        $targetStmt = $db->prepare(
            'SELECT role FROM team_members WHERE user_id = ? AND team_id = ?'
        );
        $targetStmt->execute([$removeUserId, $teamId]);
        $targetMember = $targetStmt->fetch();
        if (!$targetMember) {
            jsonOut(['success' => false, 'message' => 'Member not found in this team'], 404);
        }
        if ($targetMember['role'] === 'owner') {
            jsonOut(['success' => false, 'message' => 'Cannot remove the team owner'], 400);
        }
        $deleteStmt = $db->prepare(
            'DELETE FROM team_members WHERE user_id = ? AND team_id = ?'
        );
        $deleteStmt->execute([$removeUserId, $teamId]);
        jsonOut(['success' => true, 'message' => 'Member removed']);
        break;

    case 'update_role':
        requireAuth();
        validateCsrf();
        $userId       = $_SESSION['user_id'];
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        $newRole      = sanitize($_POST['role'] ?? '');
        $validRoles   = ['member', 'admin'];
        if (!$targetUserId || !$newRole) {
            jsonOut(['success' => false, 'message' => 'user_id and role are required'], 400);
        }
        if (!in_array($newRole, $validRoles, true)) {
            jsonOut(['success' => false, 'message' => 'Invalid role; allowed: member, admin'], 400);
        }
        $ownerStmt = $db->prepare(
            'SELECT team_id FROM team_members WHERE user_id = ? AND role = \'owner\' AND status = \'active\' LIMIT 1'
        );
        $ownerStmt->execute([$userId]);
        $ownerRow = $ownerStmt->fetch();
        if (!$ownerRow) {
            jsonOut(['success' => false, 'message' => 'Only the team owner can update roles'], 403);
        }
        $teamId = $ownerRow['team_id'];
        $stmt = $db->prepare(
            'UPDATE team_members SET role = ? WHERE user_id = ? AND team_id = ?'
        );
        $stmt->execute([$newRole, $targetUserId, $teamId]);
        if (!$stmt->rowCount()) {
            jsonOut(['success' => false, 'message' => 'Member not found in this team'], 404);
        }
        jsonOut(['success' => true, 'message' => 'Role updated']);
        break;

    case 'list_members':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $memberStmt = $db->prepare(
            'SELECT team_id FROM team_members WHERE user_id = ? AND status = \'active\' LIMIT 1'
        );
        $memberStmt->execute([$userId]);
        $row = $memberStmt->fetch();
        if (!$row) {
            jsonOut(['success' => false, 'message' => 'You are not part of any team'], 403);
        }
        $teamId = (int) $row['team_id'];
        $stmt = $db->prepare(
            'SELECT tm.*, u.name, u.email
             FROM team_members tm
             JOIN users u ON u.id = tm.user_id
             WHERE tm.team_id = ?
             ORDER BY tm.role ASC, u.name ASC'
        );
        $stmt->execute([$teamId]);
        jsonOut(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'accept_invite':
        requireAuth();
        $userId      = $_SESSION['user_id'];
        $inviteToken = sanitize($_POST['invite_token'] ?? '');
        if (!$inviteToken) {
            jsonOut(['success' => false, 'message' => 'invite_token is required'], 400);
        }
        $tokenStmt = $db->prepare(
            'SELECT * FROM team_members WHERE invite_token = ? AND user_id = ? AND status = \'invited\''
        );
        $tokenStmt->execute([$inviteToken, $userId]);
        $invite = $tokenStmt->fetch();
        if (!$invite) {
            jsonOut(['success' => false, 'message' => 'Invalid or expired invite token'], 404);
        }
        $stmt = $db->prepare(
            'UPDATE team_members
             SET status = \'active\', accepted_at = NOW(), invite_token = NULL
             WHERE invite_token = ? AND user_id = ?'
        );
        $stmt->execute([$inviteToken, $userId]);
        jsonOut(['success' => true, 'message' => 'Invitation accepted', 'team_id' => $invite['team_id']]);
        break;

    default:
        jsonOut(['success' => false, 'message' => 'Invalid action'], 400);
}
