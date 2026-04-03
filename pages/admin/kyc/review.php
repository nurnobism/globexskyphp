<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireRole(['admin', 'super_admin']);
require_once __DIR__ . '/../../../includes/kyc.php';

$db = getDB();

$submissionId = (int) get('id', 0);
if (!$submissionId) {
    redirect('/pages/admin/kyc/index.php');
}

// Load submission + user
try {
    $stmt = $db->prepare(
        "SELECT ks.*,
                u.id AS user_id, u.email, u.role,
                CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS user_name,
                u.company_name, u.phone, u.created_at AS user_since
         FROM kyc_submissions ks
         JOIN users u ON u.id = ks.user_id
         WHERE ks.id = ?
         LIMIT 1"
    );
    $stmt->execute([$submissionId]);
    $submission = $stmt->fetch();
} catch (PDOException $e) {
    $submission = null;
}

if (!$submission) {
    redirect('/pages/admin/kyc/index.php');
}

// Load documents
try {
    $docStmt = $db->prepare('SELECT * FROM kyc_documents WHERE kyc_submission_id = ? ORDER BY created_at ASC');
    $docStmt->execute([$submissionId]);
    $documents = $docStmt->fetchAll();
} catch (PDOException $e) {
    $documents = [];
}

// Load audit log for this submission
try {
    $auditStmt = $db->prepare(
        "SELECT kal.*, u.email AS admin_email
         FROM kyc_audit_log kal
         LEFT JOIN users u ON u.id = kal.performed_by
         WHERE kal.kyc_submission_id = ?
         ORDER BY kal.created_at DESC
         LIMIT 50"
    );
    $auditStmt->execute([$submissionId]);
    $auditLog = $auditStmt->fetchAll();
} catch (PDOException $e) {
    $auditLog = [];
}

$statusColors = [
    'pending'      => 'warning',
    'under_review' => 'info',
    'approved'     => 'success',
    'rejected'     => 'danger',
    'expired'      => 'secondary',
];
$badgeColor = $statusColors[$submission['status']] ?? 'secondary';

$pageTitle = 'Review KYC Submission';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-shield-check text-primary me-2"></i>Review KYC Submission #<?= $submissionId ?></h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="/pages/admin/index.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="/pages/admin/kyc/index.php">KYC Management</a></li>
                    <li class="breadcrumb-item active">Review #<?= $submissionId ?></li>
                </ol>
            </nav>
        </div>
        <a href="/pages/admin/kyc/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to List
        </a>
    </div>

    <!-- Status Banner -->
    <div class="alert alert-<?= $badgeColor ?> d-flex align-items-center gap-2 mb-4">
        <i class="bi bi-info-circle-fill fs-5"></i>
        <div>
            <strong>Status:</strong>
            <span class="badge bg-<?= $badgeColor ?> ms-1"><?= e(ucfirst(str_replace('_', ' ', $submission['status']))) ?></span>
            <?php if (!empty($submission['reviewed_at'])): ?>
            &mdash; Reviewed on <?= formatDateTime($submission['reviewed_at']) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">

        <!-- Left Column: Details -->
        <div class="col-lg-7">

            <!-- Business Info -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-building text-primary me-2"></i>Business Information</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <?php
                        $bizFields = [
                            'business_name'       => 'Business Name',
                            'business_type'       => 'Business Type',
                            'registration_number' => 'Registration Number',
                            'tax_id'              => 'Tax ID / VAT',
                            'country'             => 'Country',
                            'address'             => 'Address',
                            'city'                => 'City',
                            'state'               => 'State / Province',
                            'postal_code'         => 'Postal Code',
                        ];
                        foreach ($bizFields as $key => $label):
                            $val = $submission[$key] ?? '';
                            if (empty($val)) continue;
                        ?>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block"><?= $label ?></small>
                                <strong><?= e(ucfirst(str_replace('_', ' ', $val))) ?></strong>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- User Info -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-person-circle text-primary me-2"></i>User Information</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block">Name</small>
                                <strong><?= e(trim($submission['user_name']) ?: '—') ?></strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block">Email</small>
                                <strong><?= e($submission['email']) ?></strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block">Role</small>
                                <strong><?= e(ucfirst(str_replace('_', ' ', $submission['role']))) ?></strong>
                            </div>
                        </div>
                        <?php if (!empty($submission['phone'])): ?>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block">Phone</small>
                                <strong><?= e($submission['phone']) ?></strong>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($submission['company_name'])): ?>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block">Company</small>
                                <strong><?= e($submission['company_name']) ?></strong>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block">Member Since</small>
                                <strong><?= formatDate($submission['user_since']) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($submission['rejection_reason'])): ?>
            <div class="card border-0 shadow-sm mb-4 border-danger border-start border-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-danger mb-2"><i class="bi bi-x-circle me-1"></i>Rejection Reason</h6>
                    <p class="mb-0"><?= e($submission['rejection_reason']) ?></p>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /left -->

        <!-- Right Column: Documents -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-file-earmark-text text-primary me-2"></i>Documents (<?= count($documents) ?>)</h6>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($documents)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        No documents uploaded.
                    </div>
                    <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($documents as $doc):
                            $docStatus   = $doc['status'] ?? 'pending';
                            $docBadge    = ['verified'=>'success','rejected'=>'danger','pending'=>'warning'][$docStatus] ?? 'secondary';
                            $isPdf       = str_ends_with(strtolower($doc['file_path']), '.pdf');
                            $fileIcon    = $isPdf ? 'file-earmark-pdf text-danger' : 'file-earmark-image text-primary';
                            $fileUrl     = APP_URL . '/uploads/' . e($doc['file_path']);
                        ?>
                        <li class="list-group-item p-3">
                            <div class="d-flex align-items-start gap-3">
                                <i class="bi bi-<?= $fileIcon ?> fs-3 flex-shrink-0"></i>
                                <div class="flex-fill min-w-0">
                                    <div class="fw-semibold small text-truncate"><?= e($doc['file_name']) ?></div>
                                    <div class="text-muted small mb-1">
                                        <?= e(ucfirst(str_replace('_', ' ', $doc['document_type']))) ?>
                                        &bull; <?= round($doc['file_size'] / 1024) ?> KB
                                    </div>
                                    <span class="badge bg-<?= $docBadge ?> mb-2">
                                        <?= e(ucfirst($docStatus)) ?>
                                    </span>
                                    <div class="d-flex gap-1 flex-wrap">
                                        <a href="<?= $fileUrl ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-eye me-1"></i>View
                                        </a>
                                        <?php if ($docStatus !== 'verified'): ?>
                                        <form method="POST" action="/api/admin-kyc.php?action=verify_document" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="document_id" value="<?= (int) $doc['id'] ?>">
                                            <input type="hidden" name="submission_id" value="<?= $submissionId ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success">
                                                <i class="bi bi-check me-1"></i>Verify
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        <?php if ($docStatus !== 'rejected'): ?>
                                        <form method="POST" action="/api/admin-kyc.php?action=reject_document" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="document_id" value="<?= (int) $doc['id'] ?>">
                                            <input type="hidden" name="submission_id" value="<?= $submissionId ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                    onclick="return confirm('Mark this document as rejected?')">
                                                <i class="bi bi-x me-1"></i>Reject
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Submission Timeline -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-clock-history text-primary me-2"></i>Timeline</h6>
                </div>
                <div class="card-body p-4">
                    <ul class="list-unstyled mb-0" style="padding-left:1.5rem">
                        <li class="mb-3 position-relative">
                            <span class="position-absolute start-0 translate-middle rounded-circle bg-success d-flex align-items-center justify-content-center text-white" style="width:22px;height:22px;left:-0.75rem">
                                <i class="bi bi-check2 small" style="font-size:.6rem"></i>
                            </span>
                            <div class="ms-1">
                                <small class="fw-semibold">Submitted</small>
                                <div class="text-muted" style="font-size:.75rem"><?= formatDateTime($submission['submitted_at'] ?? $submission['created_at']) ?></div>
                            </div>
                        </li>
                        <?php if (in_array($submission['status'], ['under_review','approved','rejected'])): ?>
                        <li class="mb-3 position-relative">
                            <span class="position-absolute start-0 translate-middle rounded-circle bg-info d-flex align-items-center justify-content-center text-white" style="width:22px;height:22px;left:-0.75rem">
                                <i class="bi bi-search small" style="font-size:.6rem"></i>
                            </span>
                            <div class="ms-1">
                                <small class="fw-semibold">Under Review</small>
                                <div class="text-muted" style="font-size:.75rem">In progress</div>
                            </div>
                        </li>
                        <?php endif; ?>
                        <?php if ($submission['status'] === 'approved'): ?>
                        <li class="mb-0 position-relative">
                            <span class="position-absolute start-0 translate-middle rounded-circle bg-success d-flex align-items-center justify-content-center text-white" style="width:22px;height:22px;left:-0.75rem">
                                <i class="bi bi-patch-check-fill small" style="font-size:.6rem"></i>
                            </span>
                            <div class="ms-1">
                                <small class="fw-semibold">Approved</small>
                                <div class="text-muted" style="font-size:.75rem"><?= formatDateTime($submission['reviewed_at'] ?? '') ?></div>
                            </div>
                        </li>
                        <?php elseif ($submission['status'] === 'rejected'): ?>
                        <li class="mb-0 position-relative">
                            <span class="position-absolute start-0 translate-middle rounded-circle bg-danger d-flex align-items-center justify-content-center text-white" style="width:22px;height:22px;left:-0.75rem">
                                <i class="bi bi-x-lg small" style="font-size:.6rem"></i>
                            </span>
                            <div class="ms-1">
                                <small class="fw-semibold">Rejected</small>
                                <div class="text-muted" style="font-size:.75rem"><?= formatDateTime($submission['reviewed_at'] ?? '') ?></div>
                            </div>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

        </div><!-- /right -->
    </div><!-- /row -->

    <!-- Action Buttons -->
    <?php if (in_array($submission['status'], ['pending', 'under_review'])): ?>
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold mb-0"><i class="bi bi-hammer text-primary me-2"></i>Admin Actions</h6>
        </div>
        <div class="card-body p-4 d-flex gap-3 flex-wrap">
            <!-- Approve -->
            <form method="POST" action="/api/admin-kyc.php?action=approve&id=<?= $submissionId ?>">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-success px-4"
                        onclick="return confirm('Approve this KYC submission? This will grant the user full verification status.')">
                    <i class="bi bi-patch-check-fill me-1"></i> Approve Submission
                </button>
            </form>

            <!-- Reject (trigger modal) -->
            <button type="button" class="btn btn-danger px-4" data-bs-toggle="modal" data-bs-target="#rejectModal">
                <i class="bi bi-x-circle me-1"></i> Reject Submission
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Audit Log -->
    <?php if (!empty($auditLog)): ?>
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold mb-0"><i class="bi bi-journal-text text-primary me-2"></i>Audit Log</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Action</th>
                        <th>Performed By</th>
                        <th>IP Address</th>
                        <th>Date / Time</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($auditLog as $entry): ?>
                <tr>
                    <td><span class="badge bg-secondary"><?= e($entry['action']) ?></span></td>
                    <td><?= e($entry['admin_email'] ?? '—') ?></td>
                    <td><?= e($entry['ip_address'] ?? '—') ?></td>
                    <td><?= formatDateTime($entry['created_at']) ?></td>
                    <td>
                        <?php if (!empty($entry['details'])): ?>
                        <small class="text-muted"><?= e($entry['details']) ?></small>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/api/admin-kyc.php?action=reject&id=<?= $submissionId ?>">
                <?= csrfField() ?>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="rejectModalLabel">
                        <i class="bi bi-x-circle text-danger me-2"></i>Reject KYC Submission
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning small mb-3">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        This will notify the user and allow them to resubmit with corrected information.
                    </div>
                    <label class="form-label fw-semibold">Rejection Reason <span class="text-danger">*</span></label>
                    <textarea name="rejection_reason" class="form-control" rows="4"
                              placeholder="Explain clearly why this submission is being rejected..."
                              required minlength="20"></textarea>
                    <div class="form-text">Minimum 20 characters. This message will be shown to the user.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-circle me-1"></i>Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
