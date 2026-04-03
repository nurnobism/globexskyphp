<?php
/**
 * pages/supplier/kyc.php — Supplier KYC Verification
 */
require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/kyc.php';
requireRole(['supplier', 'admin', 'super_admin']);

$userId = (int)$_SESSION['user_id'];
$kycRecord = getKYCRecord($userId);
$currentLevel = (int)($kycRecord['current_level'] ?? 0);
$submissions = getKYCSubmissions($userId);

// Group submissions by level + doc type (latest per type)
$submissionMap = [];
foreach ($submissions as $s) {
    $key = $s['level'] . '_' . $s['document_type'];
    if (!isset($submissionMap[$key]) || $s['submitted_at'] > $submissionMap[$key]['submitted_at']) {
        $submissionMap[$key] = $s;
    }
}

$pageTitle = 'KYC Verification';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-shield-check text-primary me-2"></i>KYC Verification</h3>
        <a href="/pages/supplier/dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Dashboard
        </a>
    </div>

    <!-- Current Level Banner -->
    <div class="alert alert-<?= kycLevelBadge($currentLevel) ?> d-flex align-items-center mb-4">
        <i class="bi bi-award-fill fs-4 me-3"></i>
        <div>
            <strong>Current Level: <?= e(kycLevelLabel($currentLevel)) ?></strong>
            <?php if ($currentLevel < 4): ?>
                <div class="small">Complete the requirements below to advance to Level <?= $currentLevel + 1 ?>.</div>
            <?php else: ?>
                <div class="small">You have achieved the highest KYC level!</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Level Progress -->
    <div class="row g-3 mb-4">
        <?php foreach ([1 => 'Basic', 2 => 'Business', 3 => 'Premium', 4 => 'Gold'] as $lvl => $name): ?>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 <?= $currentLevel >= $lvl ? 'border-success' : '' ?>">
                <div class="card-body text-center">
                    <div class="rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center"
                         style="width:48px;height:48px;background:<?= $currentLevel >= $lvl ? '#198754' : '#dee2e6' ?>">
                        <?php if ($currentLevel >= $lvl): ?>
                            <i class="bi bi-check-lg text-white fs-5"></i>
                        <?php else: ?>
                            <span class="text-secondary fw-bold"><?= $lvl ?></span>
                        <?php endif; ?>
                    </div>
                    <h6 class="fw-bold mb-1">L<?= $lvl ?> — <?= $name ?></h6>
                    <span class="badge bg-<?= $currentLevel >= $lvl ? 'success' : 'secondary' ?>">
                        <?= $currentLevel >= $lvl ? 'Verified' : 'Pending' ?>
                    </span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Document Upload Sections -->
    <?php
    $levelDocs = [
        1 => ['government_id' => 'Government ID (Passport / National ID)'],
        2 => ['business_license' => 'Business License', 'proof_of_address' => 'Proof of Address'],
        3 => ['factory_photos' => 'Factory Photos (ZIP)', 'video_verification' => 'Video Verification (MP4/MOV)'],
    ];
    foreach ($levelDocs as $lvl => $docs): ?>
    <div class="card border-0 shadow-sm mb-4 <?= $currentLevel >= $lvl ? 'opacity-75' : '' ?>">
        <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
            <h6 class="mb-0 fw-bold">
                <i class="bi bi-<?= $currentLevel >= $lvl ? 'check-circle-fill text-success' : 'circle text-secondary' ?> me-2"></i>
                Level <?= $lvl ?> — <?= ['Basic Verification','Business Verification','Premium Verification'][$lvl-1] ?>
            </h6>
            <?php if ($lvl > 1): ?>
                <small class="text-muted">Requires Level <?= $lvl-1 ?> to be completed first</small>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="row g-3">
            <?php foreach ($docs as $docType => $docLabel): ?>
                <?php $key = $lvl . '_' . $docType; $sub = $submissionMap[$key] ?? null; ?>
                <div class="col-md-6">
                    <div class="border rounded p-3">
                        <h6 class="fw-semibold mb-2"><?= e($docLabel) ?></h6>
                        <?php if ($sub): ?>
                            <span class="badge bg-<?= ['pending'=>'warning','approved'=>'success','rejected'=>'danger'][$sub['status']] ?>">
                                <?= ucfirst($sub['status']) ?>
                            </span>
                            <?php if ($sub['review_notes']): ?>
                                <small class="text-muted d-block mt-1"><?= e($sub['review_notes']) ?></small>
                            <?php endif; ?>
                            <small class="text-muted d-block mt-1">Submitted: <?= e(date('M j, Y', strtotime($sub['submitted_at']))) ?></small>
                        <?php else: ?>
                            <span class="badge bg-secondary">Not Submitted</span>
                        <?php endif; ?>

                        <?php if ($currentLevel >= ($lvl - 1) && ($sub === null || $sub['status'] === 'rejected')): ?>
                        <form method="POST" action="/api/kyc.php?action=submit" enctype="multipart/form-data" class="mt-2">
                            <?= csrfField() ?>
                            <input type="hidden" name="level" value="<?= $lvl ?>">
                            <input type="hidden" name="document_type" value="<?= e($docType) ?>">
                            <input type="file" name="document" class="form-control form-control-sm mb-2" required
                                   accept=".jpg,.jpeg,.png,.pdf,.mp4,.mov,.zip">
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <i class="bi bi-upload me-1"></i>Upload
                            </button>
                        </form>
                        <?php elseif ($currentLevel < ($lvl - 1)): ?>
                            <small class="text-muted d-block mt-2">Complete Level <?= $lvl-1 ?> first</small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
