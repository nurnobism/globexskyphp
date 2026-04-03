<?php
/**
 * includes/kyc.php — KYC Verification Engine (Phase 9)
 */

/** KYC level labels */
function kycLevelLabel(int $level): string {
    return match($level) {
        0 => 'L0 — Unverified',
        1 => 'L1 — Basic Verified',
        2 => 'L2 — Business Verified',
        3 => 'L3 — Premium Verified',
        4 => 'L4 — Gold Verified',
        default => 'Unknown'
    };
}

/** KYC level badge color */
function kycLevelBadge(int $level): string {
    return match($level) {
        0 => 'secondary',
        1 => 'info',
        2 => 'primary',
        3 => 'success',
        4 => 'warning',
        default => 'secondary'
    };
}

/** Get user's current KYC level (0 if none) */
function getKYCLevel(int $userId): int {
    try {
        $db   = getDB();
        $stmt = $db->prepare('SELECT current_level FROM kyc_levels WHERE user_id = ?');
        $stmt->execute([$userId]);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['current_level'] : 0;
    } catch (PDOException $e) {
        return 0;
    }
}

/** Get all KYC submissions for a user */
function getKYCSubmissions(int $userId, ?string $status = null): array {
    try {
        $db     = getDB();
        $sql    = 'SELECT * FROM kyc_submissions WHERE user_id = ?';
        $params = [$userId];
        if ($status !== null) {
            $sql    .= ' AND status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY submitted_at DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/** Get all pending KYC submissions (admin) */
function getPendingKYCSubmissions(int $limit = 50): array {
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT s.*, u.email, u.first_name, u.last_name
             FROM kyc_submissions s
             JOIN users u ON u.id = s.user_id
             WHERE s.status = "pending"
             ORDER BY s.submitted_at ASC
             LIMIT ?'
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/** Submit a KYC document */
function submitKYCDocument(int $userId, int $level, string $docType, string $filePath): int {
    $db   = getDB();
    $stmt = $db->prepare(
        'INSERT INTO kyc_submissions (user_id, level, document_type, file_path, status)
         VALUES (?, ?, ?, ?, "pending")'
    );
    $stmt->execute([$userId, $level, $docType, $filePath]);
    return (int)$db->lastInsertId();
}

/** Admin: approve or reject a KYC submission */
function reviewKYCSubmission(int $submissionId, string $decision, int $reviewerId, string $notes = ''): bool {
    if (!in_array($decision, ['approved', 'rejected'], true)) {
        return false;
    }
    $db   = getDB();

    // Update submission
    $db->prepare(
        'UPDATE kyc_submissions SET status=?, reviewer_id=?, review_notes=?, reviewed_at=NOW() WHERE id=?'
    )->execute([$decision, $reviewerId, $notes, $submissionId]);

    if ($decision !== 'approved') {
        return true;
    }

    // Check if all level documents are approved → upgrade kyc_level
    $sub  = $db->prepare('SELECT user_id, level FROM kyc_submissions WHERE id=?');
    $sub->execute([$submissionId]);
    $row  = $sub->fetch(PDO::FETCH_ASSOC);
    if (!$row) return true;

    $userId = (int)$row['user_id'];
    $level  = (int)$row['level'];

    upgradeKYCLevelIfEligible($userId, $level);
    return true;
}

/** Check if user has all docs approved for a level and upgrade */
function upgradeKYCLevelIfEligible(int $userId, int $level): void {
    $db = getDB();

    $required = kycRequiredDocs($level);
    if (empty($required)) return;

    // Count how many approved docs exist for this level
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM kyc_submissions
         WHERE user_id=? AND level=? AND status="approved" AND document_type IN (' .
         implode(',', array_fill(0, count($required), '?')) . ')'
    );
    $stmt->execute(array_merge([$userId, $level], $required));
    $approvedCount = (int)$stmt->fetchColumn();

    if ($approvedCount >= count($required)) {
        // Use separate parameterized queries per level to avoid column name interpolation
        $colMap = [
            1 => 'INSERT INTO kyc_levels (user_id, current_level, l1_verified_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE current_level = GREATEST(current_level, ?), l1_verified_at = COALESCE(l1_verified_at, NOW())',
            2 => 'INSERT INTO kyc_levels (user_id, current_level, l2_verified_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE current_level = GREATEST(current_level, ?), l2_verified_at = COALESCE(l2_verified_at, NOW())',
            3 => 'INSERT INTO kyc_levels (user_id, current_level, l3_verified_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE current_level = GREATEST(current_level, ?), l3_verified_at = COALESCE(l3_verified_at, NOW())',
            4 => 'INSERT INTO kyc_levels (user_id, current_level, l4_verified_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE current_level = GREATEST(current_level, ?), l4_verified_at = COALESCE(l4_verified_at, NOW())',
        ];
        if (!isset($colMap[$level])) return;
        $db->prepare($colMap[$level])->execute([$userId, $level, $level]);
    }
}

/** Required document types per KYC level */
function kycRequiredDocs(int $level): array {
    return match($level) {
        1 => ['government_id'],
        2 => ['business_license', 'proof_of_address'],
        3 => ['factory_photos', 'video_verification'],
        default => []
    };
}

/** Get KYC record for a user */
function getKYCRecord(int $userId): array {
    try {
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM kyc_levels WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['user_id' => $userId, 'current_level' => 0];
    } catch (PDOException $e) {
        return ['user_id' => $userId, 'current_level' => 0];
    }
}
