<?php
/**
 * includes/kyc.php — KYC Helper Functions
 */

/**
 * Get KYC status for a user.
 * Returns 'none' if no submission exists.
 */
function getKycStatus(int $userId): string {
    try {
        $db   = getDB();
        $stmt = $db->prepare('SELECT kyc_status FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row['kyc_status'] ?? 'none';
    } catch (PDOException $e) {
        error_log('getKycStatus error: ' . $e->getMessage());
        return 'none';
    }
}

/**
 * Get the most recent KYC submission for a user.
 * Returns submission row array or null.
 */
function getKycSubmission(int $userId): ?array {
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT * FROM kyc_submissions WHERE user_id = ? ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (PDOException $e) {
        error_log('getKycSubmission error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Submit a new KYC application.
 * $data keys: business_name, business_type, registration_number, tax_id,
 *             country, address, city, state, postal_code
 * Returns submission ID on success, throws RuntimeException on failure.
 */
function submitKycApplication(int $userId, array $data): int {
    $required = ['business_name', 'business_type', 'country', 'address', 'city'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new RuntimeException("Missing required field: {$field}");
        }
    }

    try {
        $db = getDB();
        $db->beginTransaction();

        $stmt = $db->prepare(
            'INSERT INTO kyc_submissions
                (user_id, business_name, business_type, registration_number, tax_id,
                 country, address, city, state, postal_code, status, submitted_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\', NOW())'
        );
        $stmt->execute([
            $userId,
            $data['business_name'],
            $data['business_type'],
            $data['registration_number'] ?? null,
            $data['tax_id']              ?? null,
            $data['country'],
            $data['address'],
            $data['city'],
            $data['state']       ?? '',
            $data['postal_code'] ?? '',
        ]);

        $submissionId = (int) $db->lastInsertId();

        $db->prepare('UPDATE users SET kyc_status = \'pending\' WHERE id = ?')
           ->execute([$userId]);

        $db->commit();

        logKycAudit($submissionId, 'submitted', $userId);

        return $submissionId;
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('submitKycApplication error: ' . $e->getMessage());
        throw new RuntimeException('Failed to submit KYC application.');
    }
}

/**
 * Upload a KYC document for a submission.
 * $file is $_FILES['file'] element.
 * $type is the document_type string.
 * Validates: allowed extensions (jpg,jpeg,png,pdf), max 5MB, MIME type.
 * Stores to uploads/kyc/{submissionId}/ with random filename.
 * Returns document ID.
 */
function uploadKycDocument(int $submissionId, array $file, string $type): int {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File upload error code: ' . $file['error']);
    }

    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        throw new RuntimeException('File exceeds maximum allowed size of 5 MB.');
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
    $allowedMimes      = ['image/jpeg', 'image/png', 'application/pdf'];

    $originalName = $file['name'];
    $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExtensions, true)) {
        throw new RuntimeException('File type not allowed. Permitted types: jpg, jpeg, png, pdf.');
    }

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, $allowedMimes, true)) {
        throw new RuntimeException('Invalid MIME type detected: ' . $mimeType);
    }

    $uploadDir = UPLOAD_DIR . 'kyc/' . $submissionId . '/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new RuntimeException('Failed to create upload directory.');
    }

    $filename    = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Failed to move uploaded file.');
    }

    $relativePath = 'kyc/' . $submissionId . '/' . $filename;

    try {
        $db = getDB();
        $db->beginTransaction();

        $stmt = $db->prepare(
            'INSERT INTO kyc_documents
                (kyc_submission_id, document_type, file_path, file_name, file_size, mime_type)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $submissionId,
            $type,
            $relativePath,
            $originalName,
            $file['size'],
            $mimeType,
        ]);
        $documentId = (int) $db->lastInsertId();

        // Advance submission status from pending → under_review
        $db->prepare(
            "UPDATE kyc_submissions SET status = 'under_review'
             WHERE id = ? AND status = 'pending'"
        )->execute([$submissionId]);

        $db->commit();

        logKycAudit($submissionId, 'document_uploaded', $_SESSION['user_id'] ?? 0, [
            'document_id'   => $documentId,
            'document_type' => $type,
            'file_name'     => $originalName,
        ]);

        return $documentId;
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        // Remove the uploaded file if the DB insert failed
        if (file_exists($destination)) {
            unlink($destination);
        }
        error_log('uploadKycDocument error: ' . $e->getMessage());
        throw new RuntimeException('Failed to record document upload.');
    }
}

/**
 * Guard function: require KYC to be approved for the current user.
 * Redirects to /pages/account/kyc.php if not approved.
 */
function requireKycApproved(): void {
    if (!isLoggedIn()) {
        redirect('/pages/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }

    $status = getKycStatus((int) $_SESSION['user_id']);
    if ($status !== 'approved') {
        redirect('/pages/account/kyc.php?notice=kyc_required');
    }
}

/**
 * Guard function: require KYC for sellers (supplier/carrier roles).
 * Only enforces if kyc_required_for_sellers setting is enabled.
 * Redirects to KYC page with message if needed.
 */
function requireKycForSellers(): void {
    if (!isLoggedIn()) {
        return;
    }

    $sellerRoles = ['supplier', 'carrier'];
    if (!in_array($_SESSION['user_role'] ?? '', $sellerRoles, true)) {
        return;
    }

    // Lazy-load getSystemSetting if admin_permissions.php is not included
    $kycRequired = '0';
    if (function_exists('getSystemSetting')) {
        $kycRequired = getSystemSetting('kyc_required_for_sellers', '0');
    } else {
        try {
            $db   = getDB();
            $stmt = $db->prepare(
                "SELECT setting_value FROM system_settings WHERE setting_key = 'kyc_required_for_sellers'"
            );
            $stmt->execute();
            $row         = $stmt->fetch();
            $kycRequired = $row['setting_value'] ?? '0';
        } catch (PDOException $e) {
            error_log('requireKycForSellers setting lookup error: ' . $e->getMessage());
        }
    }

    if ($kycRequired !== '1') {
        return;
    }

    $status = getKycStatus((int) $_SESSION['user_id']);
    if ($status !== 'approved') {
        redirect('/pages/account/kyc.php?notice=kyc_required_for_sellers');
    }
}

/**
 * Log a KYC audit event.
 */
function logKycAudit(int $submissionId, string $action, int $performedBy, array $details = []): void {
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'INSERT INTO kyc_audit_log
                (kyc_submission_id, action, performed_by, ip_address, user_agent, details)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $submissionId > 0 ? $submissionId : null,
            $action,
            $performedBy > 0 ? $performedBy : null,
            $_SERVER['REMOTE_ADDR']     ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $details ? json_encode($details) : null,
        ]);
    } catch (PDOException $e) {
        error_log('logKycAudit error: ' . $e->getMessage());
    }
}

/**
 * Log an admin audit event.
 */
function logAdminAudit(
    int    $adminId,
    string $action,
    string $targetType = '',
    int    $targetId   = 0,
    array  $details    = []
): void {
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'INSERT INTO admin_audit_log
                (admin_id, action, target_type, target_id, details, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $adminId,
            $action,
            $targetType ?: null,
            $targetId > 0 ? $targetId : null,
            $details ? json_encode($details) : null,
            $_SERVER['REMOTE_ADDR']     ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    } catch (PDOException $e) {
        error_log('logAdminAudit error: ' . $e->getMessage());
    }
}
