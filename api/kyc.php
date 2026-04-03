<?php
/**
 * api/kyc.php — KYC API Endpoints (Phase 9)
 */
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/kyc.php';
requireLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$userId = (int)$_SESSION['user_id'];

try {
    switch ($action) {

        case 'submit':
            // POST — submit KYC document
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            if (!verifyCsrf()) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid CSRF token']);
                exit;
            }

            $level   = (int)($_POST['level'] ?? 0);
            $docType = trim($_POST['document_type'] ?? '');
            $allowedTypes = ['government_id','business_license','proof_of_address','factory_photos','video_verification'];

            if ($level < 1 || $level > 3 || !in_array($docType, $allowedTypes, true)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid level or document type']);
                exit;
            }

            if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(['error' => 'File upload failed']);
                exit;
            }

            $file = $_FILES['document'];
            $maxSize = 10 * 1024 * 1024; // 10 MB
            if ($file['size'] > $maxSize) {
                http_response_code(400);
                echo json_encode(['error' => 'File too large (max 10 MB)']);
                exit;
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','pdf','mp4','mov','zip'];
            if (!in_array($ext, $allowed, true)) {
                http_response_code(400);
                echo json_encode(['error' => 'File type not allowed']);
                exit;
            }

            $uploadDir = rtrim(getenv('KYC_UPLOAD_DIR') ?: 'uploads/kyc/', '/') . '/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filename  = 'kyc_' . $userId . '_' . $level . '_' . $docType . '_' . time() . '.' . $ext;
            $filePath  = $uploadDir . $filename;

            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save file']);
                exit;
            }

            $id = submitKYCDocument($userId, $level, $docType, $filePath);
            header('Location: /pages/supplier/kyc.php?submitted=1');
            exit;

        case 'status':
            // GET — KYC status for current user
            $level = getKYCLevel($userId);
            $subs  = getKYCSubmissions($userId);
            echo json_encode(['level' => $level, 'submissions' => $subs]);
            break;

        case 'verify':
            // POST — admin: approve/reject submission
            requireRole(['admin', 'super_admin']);
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            if (!verifyCsrf()) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid CSRF token']);
                exit;
            }

            $submissionId = (int)($_POST['submission_id'] ?? 0);
            $decision     = $_POST['decision'] ?? '';
            $notes        = trim($_POST['review_notes'] ?? '');

            if (!$submissionId || !in_array($decision, ['approved','rejected'], true)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid parameters']);
                exit;
            }

            $ok = reviewKYCSubmission($submissionId, $decision, $userId, $notes);
            header('Location: /pages/admin/kyc-management.php?reviewed=1');
            exit;

        case 'pending':
            // GET — admin: list pending submissions
            requireRole(['admin', 'super_admin']);
            $list = getPendingKYCSubmissions(100);
            echo json_encode($list);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
