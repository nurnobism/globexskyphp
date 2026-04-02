<?php
/**
 * api/users.php — Users API (account management)
 */

require_once __DIR__ . '/../includes/middleware.php';
requireLogin();

$action = $_GET['action'] ?? 'profile';
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

switch ($action) {

    case 'profile':
        $stmt = $db->prepare('SELECT id, email, first_name, last_name, phone, role, avatar, company_name, bio, is_verified, created_at FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        jsonResponse(['data' => $stmt->fetch()]);
        break;

    case 'update_profile':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);

        $firstName = trim(post('first_name', ''));
        $lastName  = trim(post('last_name', ''));
        $phone     = trim(post('phone', ''));
        $bio       = trim(post('bio', ''));
        $company   = trim(post('company_name', ''));

        if (empty($firstName)) jsonResponse(['error' => 'First name is required'], 422);

        $avatar = null;
        if (!empty($_FILES['avatar']['name'])) {
            $avatar = uploadFile($_FILES['avatar'], 'avatars');
        }

        $sql    = 'UPDATE users SET first_name=?, last_name=?, phone=?, bio=?, company_name=?';
        $params = [$firstName, $lastName, $phone, $bio, $company];
        if ($avatar) { $sql .= ', avatar=?'; $params[] = $avatar; }
        $sql .= ' WHERE id=?';
        $params[] = $_SESSION['user_id'];

        $db->prepare($sql)->execute($params);
        $_SESSION['user_name'] = trim($firstName . ' ' . $lastName);

        if (isset($_POST['_redirect'])) { flashMessage('success', 'Profile updated.'); redirect($_POST['_redirect']); }
        jsonResponse(['success' => true]);
        break;

    case 'change_password':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);

        $current  = post('current_password', '');
        $new      = post('new_password', '');
        $confirm  = post('password_confirm', '');

        if (strlen($new) < 8 || $new !== $confirm) {
            if (isset($_POST['_redirect'])) { flashMessage('danger', 'New password invalid or does not match.'); redirect($_POST['_redirect']); }
            jsonResponse(['error' => 'New password invalid or does not match.'], 422);
        }

        $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!password_verify($current, $user['password_hash'])) {
            if (isset($_POST['_redirect'])) { flashMessage('danger', 'Current password incorrect.'); redirect($_POST['_redirect']); }
            jsonResponse(['error' => 'Current password incorrect.'], 401);
        }

        $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $_SESSION['user_id']]);

        if (isset($_POST['_redirect'])) { flashMessage('success', 'Password changed successfully.'); redirect($_POST['_redirect']); }
        jsonResponse(['success' => true]);
        break;

    case 'addresses':
        $stmt = $db->prepare('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id');
        $stmt->execute([$_SESSION['user_id']]);
        jsonResponse(['data' => $stmt->fetchAll()]);
        break;

    case 'add_address':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);

        $label    = trim(post('label', 'Home'));
        $fullName = trim(post('full_name', ''));
        $phone    = trim(post('phone', ''));
        $addr1    = trim(post('address_line1', ''));
        $addr2    = trim(post('address_line2', ''));
        $city     = trim(post('city', ''));
        $state    = trim(post('state', ''));
        $postal   = trim(post('postal_code', ''));
        $country  = trim(post('country', 'US'));
        $default  = post('is_default', 0) ? 1 : 0;

        if (!$addr1 || !$city || !$country) jsonResponse(['error' => 'Address fields required'], 422);

        if ($default) $db->prepare('UPDATE addresses SET is_default=0 WHERE user_id=?')->execute([$_SESSION['user_id']]);

        $db->prepare('INSERT INTO addresses (user_id, label, full_name, phone, address_line1, address_line2, city, state, postal_code, country, is_default) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
           ->execute([$_SESSION['user_id'], $label, $fullName, $phone, $addr1, $addr2, $city, $state, $postal, $country, $default]);

        if (isset($_POST['_redirect'])) { flashMessage('success', 'Address added.'); redirect($_POST['_redirect']); }
        jsonResponse(['success' => true, 'id' => $db->lastInsertId()]);
        break;

    case 'delete_address':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!verifyCsrf())      jsonResponse(['error' => 'Invalid CSRF token'], 403);

        $id = (int)post('address_id', 0);
        $db->prepare('DELETE FROM addresses WHERE id = ? AND user_id = ?')->execute([$id, $_SESSION['user_id']]);
        if (isset($_POST['_redirect'])) { flashMessage('success', 'Address removed.'); redirect($_POST['_redirect']); }
        jsonResponse(['success' => true]);
        break;

    case 'notifications':
        $page = max(1, (int)get('page', 1));
        $sql  = 'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC';
        jsonResponse(paginate($db, $sql, [$_SESSION['user_id']], $page, 15));
        break;

    case 'mark_notification_read':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        $id = (int)post('notification_id', 0);
        if ($id) {
            $db->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?')->execute([$id, $_SESSION['user_id']]);
        } else {
            $db->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$_SESSION['user_id']]);
        }
        jsonResponse(['success' => true]);
        break;

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}
