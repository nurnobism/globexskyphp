<?php
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/kyc.php';
require_once __DIR__ . '/../includes/admin_permissions.php';

header('Content-Type: application/json');

requireRole(['admin', 'super_admin']);

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

switch ($action) {

    // -------------------------------------------------------------------------
    // GET ?action=list
    // -------------------------------------------------------------------------
    case 'list':
        $status    = $_GET['status']    ?? '';
        $country   = $_GET['country']   ?? '';
        $search    = $_GET['search']    ?? '';
        $date_from = $_GET['date_from'] ?? '';
        $date_to   = $_GET['date_to']   ?? '';
        $page      = max(1, (int)($_GET['page'] ?? 1));

        $sql    = 'SELECT ks.id, ks.user_id, ks.business_name, ks.business_type,
                          ks.country, ks.status, ks.submitted_at, ks.reviewed_at,
                          ks.expires_at, u.email AS user_email
                   FROM kyc_submissions ks
                   JOIN users u ON u.id = ks.user_id
                   WHERE 1=1';
        $params = [];

        if ($status !== '') {
            $sql      .= ' AND ks.status = ?';
            $params[]  = $status;
        }
        if ($country !== '') {
            $sql      .= ' AND ks.country = ?';
            $params[]  = $country;
        }
        if ($search !== '') {
            $sql      .= ' AND (u.email LIKE ? OR ks.business_name LIKE ?)';
            $params[]  = '%' . $search . '%';
            $params[]  = '%' . $search . '%';
        }
        if ($date_from !== '') {
            $sql      .= ' AND ks.submitted_at >= ?';
            $params[]  = $date_from . ' 00:00:00';
        }
        if ($date_to !== '') {
            $sql      .= ' AND ks.submitted_at <= ?';
            $params[]  = $date_to . ' 23:59:59';
        }
        $sql .= ' ORDER BY ks.submitted_at DESC';

        try {
            $result = paginate($db, $sql, $params, $page, 20);
            echo json_encode([
                'success'     => true,
                'submissions' => $result['data'],
                'total'       => $result['total'],
                'pages'       => $result['pages'],
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to fetch submissions']);
        }
        break;

    // -------------------------------------------------------------------------
    // GET ?action=detail&id=X
    // -------------------------------------------------------------------------
    case 'detail':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid submission ID']);
            break;
        }

        try {
            $stmt = $db->prepare('SELECT * FROM kyc_submissions WHERE id = ?');
            $stmt->execute([$id]);
            $submission = $stmt->fetch();

            if (!$submission) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Submission not found']);
                break;
            }

            $stmt = $db->prepare(
                'SELECT id, email, first_name, last_name, name, role,
                        kyc_status, kyc_verified_at, created_at
                 FROM users WHERE id = ?'
            );
            $stmt->execute([$submission['user_id']]);
            $user = $stmt->fetch();

            $stmt = $db->prepare(
                'SELECT * FROM kyc_documents
                 WHERE kyc_submission_id = ?
                 ORDER BY created_at ASC'
            );
            $stmt->execute([$id]);
            $documents = $stmt->fetchAll();

            echo json_encode([
                'success'    => true,
                'submission' => $submission,
                'documents'  => $documents,
                'user'       => $user,
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to fetch submission details']);
        }
        break;

    // -------------------------------------------------------------------------
    // POST ?action=approve&id=X
    // -------------------------------------------------------------------------
    case 'approve':
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
            echo json_encode(['success' => false, 'error' => 'Invalid submission ID']);
            break;
        }

        try {
            $stmt = $db->prepare('SELECT * FROM kyc_submissions WHERE id = ?');
            $stmt->execute([$id]);
            $submission = $stmt->fetch();

            if (!$submission) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Submission not found']);
                break;
            }

            $adminId = (int)$_SESSION['user_id'];

            $stmt = $db->prepare(
                'UPDATE kyc_submissions
                 SET status = ?, reviewed_at = NOW(), reviewed_by = ?,
                     expires_at = DATE_ADD(NOW(), INTERVAL 1 YEAR)
                 WHERE id = ?'
            );
            $stmt->execute(['approved', $adminId, $id]);

            $stmt = $db->prepare(
                'UPDATE users SET kyc_status = ?, kyc_verified_at = NOW() WHERE id = ?'
            );
            $stmt->execute(['approved', $submission['user_id']]);

            logKycAudit($id, 'approved', $adminId, [
                'reviewed_by' => $adminId,
                'user_id'     => $submission['user_id'],
            ]);

            logAdminAudit($adminId, 'kyc_approve', 'kyc', $id, [
                'user_id' => $submission['user_id'],
            ]);

            echo json_encode(['success' => true, 'message' => 'Submission approved']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to approve submission']);
        }
        break;

    // -------------------------------------------------------------------------
    // POST ?action=reject&id=X
    // -------------------------------------------------------------------------
    case 'reject':
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
            echo json_encode(['success' => false, 'error' => 'Invalid submission ID']);
            break;
        }

        $rejectionReason = trim($_POST['rejection_reason'] ?? '');
        if ($rejectionReason === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Rejection reason is required']);
            break;
        }

        try {
            $stmt = $db->prepare('SELECT * FROM kyc_submissions WHERE id = ?');
            $stmt->execute([$id]);
            $submission = $stmt->fetch();

            if (!$submission) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Submission not found']);
                break;
            }

            $adminId = (int)$_SESSION['user_id'];

            $stmt = $db->prepare(
                'UPDATE kyc_submissions
                 SET status = ?, reviewed_at = NOW(), reviewed_by = ?, rejection_reason = ?
                 WHERE id = ?'
            );
            $stmt->execute(['rejected', $adminId, $rejectionReason, $id]);

            $stmt = $db->prepare('UPDATE users SET kyc_status = ? WHERE id = ?');
            $stmt->execute(['rejected', $submission['user_id']]);

            logKycAudit($id, 'rejected', $adminId, [
                'reviewed_by'      => $adminId,
                'user_id'          => $submission['user_id'],
                'rejection_reason' => $rejectionReason,
            ]);

            logAdminAudit($adminId, 'kyc_reject', 'kyc', $id, [
                'user_id'          => $submission['user_id'],
                'rejection_reason' => $rejectionReason,
            ]);

            echo json_encode(['success' => true, 'message' => 'Submission rejected']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to reject submission']);
        }
        break;

    // -------------------------------------------------------------------------
    // POST ?action=verify_document
    // -------------------------------------------------------------------------
    case 'verify_document':
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

        $documentId      = (int)($_POST['document_id'] ?? 0);
        $docAction       = $_POST['action'] ?? '';
        $rejectionReason = trim($_POST['rejection_reason'] ?? '');

        if ($documentId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid document ID']);
            break;
        }
        if (!in_array($docAction, ['verified', 'rejected'], true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Action must be "verified" or "rejected"']);
            break;
        }
        if ($docAction === 'rejected' && $rejectionReason === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Rejection reason is required']);
            break;
        }

        try {
            $stmt = $db->prepare('SELECT * FROM kyc_documents WHERE id = ?');
            $stmt->execute([$documentId]);
            $document = $stmt->fetch();

            if (!$document) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Document not found']);
                break;
            }

            $adminId = (int)$_SESSION['user_id'];

            $stmt = $db->prepare(
                'UPDATE kyc_documents
                 SET status = ?, verified_at = NOW(), verified_by = ?, rejection_reason = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $docAction,
                $adminId,
                $docAction === 'rejected' ? $rejectionReason : null,
                $documentId,
            ]);

            $auditAction = $docAction === 'verified' ? 'document_verified' : 'document_rejected';
            logKycAudit($document['kyc_submission_id'], $auditAction, $adminId, [
                'document_id'      => $documentId,
                'document_type'    => $document['document_type'],
                'rejection_reason' => $rejectionReason !== '' ? $rejectionReason : null,
            ]);

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to update document status']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
