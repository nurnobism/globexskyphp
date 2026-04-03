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
    // GET ?action=list
    // -------------------------------------------------------------------------
    case 'list':
        $role       = $_GET['role']       ?? '';
        $isActive   = $_GET['is_active']  ?? '';
        $status     = $_GET['status']     ?? '';
        $kycStatus  = $_GET['kyc_status'] ?? '';
        $search     = $_GET['search']     ?? '';
        $date_from  = $_GET['date_from']  ?? '';
        $date_to    = $_GET['date_to']    ?? '';
        $page       = max(1, (int)($_GET['page'] ?? 1));

        $sql    = 'SELECT id, email, first_name, last_name, name, role,
                          is_active, is_verified, kyc_status, created_at
                   FROM users
                   WHERE 1=1';
        $params = [];

        if ($role !== '') {
            $sql      .= ' AND role = ?';
            $params[]  = $role;
        }
        // Support both ?is_active= and ?status= as filters
        if ($isActive !== '') {
            $sql      .= ' AND is_active = ?';
            $params[]  = (int)$isActive;
        } elseif ($status !== '') {
            $sql      .= ' AND status = ?';
            $params[]  = $status;
        }
        if ($kycStatus !== '') {
            $sql      .= ' AND kyc_status = ?';
            $params[]  = $kycStatus;
        }
        if ($search !== '') {
            $sql      .= ' AND (email LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR name LIKE ?)';
            $like      = '%' . $search . '%';
            $params[]  = $like;
            $params[]  = $like;
            $params[]  = $like;
            $params[]  = $like;
        }
        if ($date_from !== '') {
            $sql      .= ' AND created_at >= ?';
            $params[]  = $date_from . ' 00:00:00';
        }
        if ($date_to !== '') {
            $sql      .= ' AND created_at <= ?';
            $params[]  = $date_to . ' 23:59:59';
        }
        $sql .= ' ORDER BY created_at DESC';

        try {
            $result = paginate($db, $sql, $params, $page, 20);
            echo json_encode([
                'success' => true,
                'users'   => $result['data'],
                'total'   => $result['total'],
                'pages'   => $result['pages'],
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to fetch users']);
        }
        break;

    // -------------------------------------------------------------------------
    // GET ?action=detail&id=X
    // -------------------------------------------------------------------------
    case 'detail':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
            break;
        }

        try {
            $stmt = $db->prepare(
                'SELECT id, email, first_name, last_name, name, phone, role,
                        status, is_active, is_verified, kyc_status, kyc_verified_at,
                        company_name, bio, language, currency, timezone,
                        last_login_at, created_at, updated_at
                 FROM users WHERE id = ?'
            );
            $stmt->execute([$id]);
            $user = $stmt->fetch();

            if (!$user) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'User not found']);
                break;
            }

            // Order summary
            $stmt = $db->prepare(
                'SELECT COUNT(*) AS total,
                        SUM(status = ?) AS pending
                 FROM orders WHERE buyer_id = ?'
            );
            $stmt->execute(['pending', $id]);
            $orderSummary = $stmt->fetch();

            // Latest KYC submission
            $stmt = $db->prepare(
                'SELECT id, business_name, business_type, country, status,
                        submitted_at, reviewed_at, rejection_reason, expires_at
                 FROM kyc_submissions
                 WHERE user_id = ?
                 ORDER BY submitted_at DESC
                 LIMIT 1'
            );
            $stmt->execute([$id]);
            $kycSubmission = $stmt->fetch() ?: null;

            echo json_encode([
                'success'        => true,
                'user'           => $user,
                'order_summary'  => [
                    'total'   => (int)$orderSummary['total'],
                    'pending' => (int)($orderSummary['pending'] ?? 0),
                ],
                'kyc_submission' => $kycSubmission,
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to fetch user details']);
        }
        break;

    // -------------------------------------------------------------------------
    // POST ?action=suspend&id=X
    // -------------------------------------------------------------------------
    case 'suspend':
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

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
            break;
        }
        if ($id === $adminId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'You cannot suspend your own account']);
            break;
        }

        try {
            $stmt = $db->prepare('SELECT id, role FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $target = $stmt->fetch();

            if (!$target) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'User not found']);
                break;
            }
            if ($target['role'] === 'super_admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Cannot suspend a super_admin']);
                break;
            }

            $stmt = $db->prepare('UPDATE users SET is_active = 0 WHERE id = ?');
            $stmt->execute([$id]);

            logAdminAudit($adminId, 'user_suspend', 'user', $id, [
                'target_role' => $target['role'],
            ]);

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to suspend user']);
        }
        break;

    // -------------------------------------------------------------------------
    // POST ?action=unsuspend&id=X
    // -------------------------------------------------------------------------
    case 'unsuspend':
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

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
            break;
        }

        try {
            $stmt = $db->prepare('SELECT id, role FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $target = $stmt->fetch();

            if (!$target) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'User not found']);
                break;
            }

            $stmt = $db->prepare('UPDATE users SET is_active = 1 WHERE id = ?');
            $stmt->execute([$id]);

            logAdminAudit($adminId, 'user_unsuspend', 'user', $id, [
                'target_role' => $target['role'],
            ]);

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to unsuspend user']);
        }
        break;

    // -------------------------------------------------------------------------
    // POST ?action=change_role
    // -------------------------------------------------------------------------
    case 'change_role':
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

        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $newRole      = $_POST['new_role'] ?? '';

        if ($targetUserId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
            break;
        }

        // Roles a regular admin may assign; super_admin may also assign super_admin
        $allowedRoles     = ['buyer', 'supplier', 'carrier', 'admin'];
        $isSuperAdmin     = hasRole('super_admin');
        if ($isSuperAdmin) {
            $allowedRoles[] = 'super_admin';
        }

        if (!in_array($newRole, $allowedRoles, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid or unauthorized role']);
            break;
        }

        try {
            $stmt = $db->prepare('SELECT id, role FROM users WHERE id = ?');
            $stmt->execute([$targetUserId]);
            $target = $stmt->fetch();

            if (!$target) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'User not found']);
                break;
            }

            $stmt = $db->prepare('UPDATE users SET role = ? WHERE id = ?');
            $stmt->execute([$newRole, $targetUserId]);

            logAdminAudit($adminId, 'user_change_role', 'user', $targetUserId, [
                'old_role' => $target['role'],
                'new_role' => $newRole,
            ]);

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to change user role']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
