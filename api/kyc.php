<?php
/**
 * api/kyc.php — KYC REST API
 * Actions: status, documents, submit, upload_document, resubmit
 */

require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/kyc.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {

    // -------------------------------------------------------
    case 'status':
        if ($method !== 'GET') { jsonResponse(['error' => 'Method not allowed'], 405); }
        requireAuth();

        try {
            $userId     = (int) $_SESSION['user_id'];
            $kycStatus  = getKycStatus($userId);
            $submission = getKycSubmission($userId);

            jsonResponse([
                'success'    => true,
                'kyc_status' => $kycStatus,
                'submission' => $submission,
            ]);
        } catch (PDOException $e) {
            error_log('kyc status error: ' . $e->getMessage());
            jsonResponse(['success' => false, 'error' => 'Failed to retrieve KYC status.'], 500);
        }
        break;

    // -------------------------------------------------------
    case 'documents':
        if ($method !== 'GET') { jsonResponse(['error' => 'Method not allowed'], 405); }
        requireAuth();

        try {
            $userId     = (int) $_SESSION['user_id'];
            $submission = getKycSubmission($userId);

            if (!$submission) {
                jsonResponse(['success' => true, 'documents' => []]);
            }

            $stmt = getDB()->prepare(
                'SELECT * FROM kyc_documents WHERE kyc_submission_id = ? ORDER BY created_at ASC'
            );
            $stmt->execute([$submission['id']]);
            $documents = $stmt->fetchAll();

            jsonResponse(['success' => true, 'documents' => $documents]);
        } catch (PDOException $e) {
            error_log('kyc documents error: ' . $e->getMessage());
            jsonResponse(['success' => false, 'error' => 'Failed to retrieve documents.'], 500);
        }
        break;

    // -------------------------------------------------------
    case 'submit':
        if ($method !== 'POST') { jsonResponse(['error' => 'Method not allowed'], 405); }
        requireAuth();
        if (!verifyCsrf()) { jsonResponse(['error' => 'Invalid CSRF token'], 403); }

        try {
            $userId     = (int) $_SESSION['user_id'];
            $kycStatus  = getKycStatus($userId);

            if (in_array($kycStatus, ['pending', 'under_review', 'approved'], true)) {
                $statusLabels = [
                    'pending'      => 'pending',
                    'under_review' => 'under review',
                    'approved'     => 'approved',
                ];
                $label = $statusLabels[$kycStatus] ?? $kycStatus;
                jsonResponse([
                    'success' => false,
                    'error'   => 'A KYC application is already ' . $label . '.',
                ], 409);
            }

            $data = [
                'business_name'       => trim($_POST['business_name']       ?? ''),
                'business_type'       => trim($_POST['business_type']       ?? ''),
                'registration_number' => trim($_POST['registration_number'] ?? ''),
                'tax_id'              => trim($_POST['tax_id']              ?? ''),
                'country'             => trim($_POST['country']             ?? ''),
                'address'             => trim($_POST['address']             ?? ''),
                'city'                => trim($_POST['city']                ?? ''),
                'state'               => trim($_POST['state']               ?? ''),
                'postal_code'         => trim($_POST['postal_code']         ?? ''),
            ];

            $submissionId = submitKycApplication($userId, $data);

            jsonResponse([
                'success'       => true,
                'submission_id' => $submissionId,
                'message'       => 'KYC submitted',
            ], 201);
        } catch (RuntimeException $e) {
            jsonResponse(['success' => false, 'error' => $e->getMessage()], 422);
        } catch (PDOException $e) {
            error_log('kyc submit error: ' . $e->getMessage());
            jsonResponse(['success' => false, 'error' => 'Failed to submit KYC application.'], 500);
        }
        break;

    // -------------------------------------------------------
    case 'upload_document':
        if ($method !== 'POST') { jsonResponse(['error' => 'Method not allowed'], 405); }
        requireAuth();
        if (!verifyCsrf()) { jsonResponse(['error' => 'Invalid CSRF token'], 403); }

        try {
            $userId     = (int) $_SESSION['user_id'];
            $submission = getKycSubmission($userId);

            if (!$submission) {
                jsonResponse(['success' => false, 'error' => 'No KYC submission found.'], 404);
            }

            $allowedUploadStatuses = ['pending', 'under_review', 'rejected'];
            if (!in_array($submission['status'], $allowedUploadStatuses, true)) {
                jsonResponse([
                    'success' => false,
                    'error'   => 'Documents cannot be uploaded for a submission with status: ' . $submission['status'] . '.',
                ], 409);
            }

            $docType = trim($_POST['doc_type'] ?? '');
            if ($docType === '') {
                jsonResponse(['success' => false, 'error' => 'Document type is required.'], 422);
            }

            if (empty($_FILES['file'])) {
                jsonResponse(['success' => false, 'error' => 'No file uploaded.'], 422);
            }

            $documentId = uploadKycDocument((int) $submission['id'], $_FILES['file'], $docType);

            jsonResponse(['success' => true, 'document_id' => $documentId], 201);
        } catch (RuntimeException $e) {
            jsonResponse(['success' => false, 'error' => $e->getMessage()], 422);
        } catch (PDOException $e) {
            error_log('kyc upload_document error: ' . $e->getMessage());
            jsonResponse(['success' => false, 'error' => 'Failed to upload document.'], 500);
        }
        break;

    // -------------------------------------------------------
    case 'resubmit':
        if ($method !== 'POST') { jsonResponse(['error' => 'Method not allowed'], 405); }
        requireAuth();
        if (!verifyCsrf()) { jsonResponse(['error' => 'Invalid CSRF token'], 403); }

        try {
            $userId     = (int) $_SESSION['user_id'];
            $submission = getKycSubmission($userId);

            if (!$submission || !in_array($submission['status'], ['rejected', 'expired'], true)) {
                jsonResponse([
                    'success' => false,
                    'error'   => 'Resubmission is only allowed when the current submission is rejected or expired.',
                ], 409);
            }

            $data = [
                'business_name'       => trim($_POST['business_name']       ?? ''),
                'business_type'       => trim($_POST['business_type']       ?? ''),
                'registration_number' => trim($_POST['registration_number'] ?? ''),
                'tax_id'              => trim($_POST['tax_id']              ?? ''),
                'country'             => trim($_POST['country']             ?? ''),
                'address'             => trim($_POST['address']             ?? ''),
                'city'                => trim($_POST['city']                ?? ''),
                'state'               => trim($_POST['state']               ?? ''),
                'postal_code'         => trim($_POST['postal_code']         ?? ''),
            ];

            $submissionId = submitKycApplication($userId, $data);

            jsonResponse([
                'success'       => true,
                'submission_id' => $submissionId,
                'message'       => 'KYC resubmitted',
            ], 201);
        } catch (RuntimeException $e) {
            jsonResponse(['success' => false, 'error' => $e->getMessage()], 422);
        } catch (PDOException $e) {
            error_log('kyc resubmit error: ' . $e->getMessage());
            jsonResponse(['success' => false, 'error' => 'Failed to resubmit KYC application.'], 500);
        }
        break;

    // -------------------------------------------------------
    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}
