<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();
require_once __DIR__ . '/../../includes/kyc.php';

$user       = getCurrentUser();
$userId     = (int) $_SESSION['user_id'];
$kycStatus  = getKycStatus($userId);
$submission = getKycSubmission($userId);

$step = (int) get('step', 1);
if ($step < 1 || $step > 3) $step = 1;

// If we already have a submission ID in session (after step 1 POST), carry it
$submissionId = (int) ($_SESSION['kyc_submission_id'] ?? ($submission['id'] ?? 0));

$notice = get('notice', '');

$pageTitle = 'KYC Verification';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="row g-4">
        <!-- Sidebar -->
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center p-4">
                    <img src="<?= $user['avatar'] ? e(APP_URL . '/' . $user['avatar']) : 'https://ui-avatars.com/api/?name=' . urlencode(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) . '&size=100&background=0d6efd&color=fff' ?>"
                         class="rounded-circle mb-3" width="80" height="80" alt="Avatar">
                    <h6 class="fw-bold mb-0"><?= e(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></h6>
                    <small class="text-muted"><?= e(ucfirst($user['role'] ?? '')) ?></small>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item"><a href="/pages/account/profile.php" class="text-decoration-none d-flex align-items-center gap-2"><i class="bi bi-person"></i> Profile</a></li>
                    <li class="list-group-item"><a href="/pages/account/addresses.php" class="text-decoration-none d-flex align-items-center gap-2"><i class="bi bi-geo-alt"></i> Addresses</a></li>
                    <li class="list-group-item"><a href="/pages/order/index.php" class="text-decoration-none d-flex align-items-center gap-2"><i class="bi bi-bag"></i> My Orders</a></li>
                    <li class="list-group-item active"><a href="/pages/account/kyc.php" class="text-decoration-none d-flex align-items-center gap-2 text-white"><i class="bi bi-shield-check"></i> KYC Verification</a></li>
                    <li class="list-group-item"><a href="/pages/account/settings.php" class="text-decoration-none d-flex align-items-center gap-2"><i class="bi bi-gear"></i> Settings</a></li>
                    <li class="list-group-item"><a href="/api/auth.php?action=logout" class="text-decoration-none text-danger d-flex align-items-center gap-2"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-lg-9">

            <?php if ($notice === 'kyc_required'): ?>
            <div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <span>KYC verification is required to access that feature. Please complete your verification below.</span>
            </div>
            <?php elseif ($notice === 'kyc_required_for_sellers'): ?>
            <div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <span>KYC verification is required for sellers. Please complete your verification below.</span>
            </div>
            <?php endif; ?>

            <!-- Status Banner -->
            <?php
            $alertMap = [
                'none'         => ['info',    'bi-info-circle-fill',         'Not Started',   'Complete your KYC verification to unlock all platform features.'],
                'pending'      => ['warning', 'bi-hourglass-split',          'Pending Review','Your KYC application has been submitted and is awaiting review.'],
                'under_review' => ['warning', 'bi-search',                   'Under Review',  'Your documents are currently being reviewed by our compliance team.'],
                'approved'     => ['success', 'bi-patch-check-fill',         'Approved',      'Your KYC verification is approved. You have full access to all features.'],
                'rejected'     => ['danger',  'bi-x-circle-fill',            'Rejected',      'Your KYC application was rejected. Please review the reason below and resubmit.'],
                'expired'      => ['secondary','bi-clock-history',           'Expired',       'Your KYC verification has expired. Please resubmit your documents.'],
            ];
            [$alertType, $alertIcon, $alertLabel, $alertMsg] = $alertMap[$kycStatus] ?? $alertMap['none'];
            ?>
            <div class="alert alert-<?= $alertType ?> d-flex align-items-center gap-2 mb-4">
                <i class="bi <?= $alertIcon ?> fs-5"></i>
                <div><strong><?= $alertLabel ?>:</strong> <?= $alertMsg ?></div>
            </div>

            <!-- ====================================================
                 APPROVED STATE
            ===================================================== -->
            <?php if ($kycStatus === 'approved' && $submission): ?>
            <div class="card border-0 shadow-sm border-start border-success border-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-circle bg-success bg-opacity-10 p-3">
                            <i class="bi bi-patch-check-fill text-success fs-3"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 text-success">KYC Approved</h5>
                            <small class="text-muted">Your business identity has been verified</small>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block">Business Name</small>
                                <strong><?= e($submission['business_name']) ?></strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block">Business Type</small>
                                <strong><?= e(ucfirst(str_replace('_', ' ', $submission['business_type']))) ?></strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block">Country</small>
                                <strong><?= e($submission['country']) ?></strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block">Approved On</small>
                                <strong><?= formatDate($submission['reviewed_at'] ?? $submission['updated_at'] ?? '') ?></strong>
                            </div>
                        </div>
                        <?php if (!empty($submission['expires_at'])): ?>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block">Expires On</small>
                                <strong><?= formatDate($submission['expires_at']) ?></strong>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ====================================================
                 PENDING / UNDER_REVIEW STATE — Status Timeline
            ===================================================== -->
            <?php elseif (in_array($kycStatus, ['pending', 'under_review'])): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history text-warning me-2"></i>Verification in Progress</h5>
                </div>
                <div class="card-body p-4">
                    <?php if ($submission): ?>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <small class="text-muted d-block">Business Name</small>
                            <strong><?= e($submission['business_name']) ?></strong>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Submitted On</small>
                            <strong><?= formatDate($submission['submitted_at'] ?? $submission['created_at']) ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Timeline -->
                    <ul class="list-unstyled position-relative" style="padding-left:2rem">
                        <li class="mb-4 position-relative">
                            <span class="position-absolute start-0 translate-middle rounded-circle d-flex align-items-center justify-content-center bg-success text-white" style="width:28px;height:28px;left:-1rem">
                                <i class="bi bi-check2 small"></i>
                            </span>
                            <div class="ms-2">
                                <strong>Submitted</strong>
                                <div class="text-muted small"><?= $submission ? formatDateTime($submission['submitted_at'] ?? $submission['created_at']) : '—' ?></div>
                            </div>
                        </li>
                        <li class="mb-4 position-relative">
                            <span class="position-absolute start-0 translate-middle rounded-circle d-flex align-items-center justify-content-center <?= $kycStatus === 'under_review' ? 'bg-warning text-dark' : 'bg-secondary text-white' ?>" style="width:28px;height:28px;left:-1rem">
                                <i class="bi bi-<?= $kycStatus === 'under_review' ? 'search' : 'three-dots' ?> small"></i>
                            </span>
                            <div class="ms-2">
                                <strong>Under Review</strong>
                                <div class="text-muted small"><?= $kycStatus === 'under_review' ? 'In progress' : 'Waiting' ?></div>
                            </div>
                        </li>
                        <li class="position-relative">
                            <span class="position-absolute start-0 translate-middle rounded-circle d-flex align-items-center justify-content-center bg-secondary text-white" style="width:28px;height:28px;left:-1rem">
                                <i class="bi bi-patch-check small"></i>
                            </span>
                            <div class="ms-2">
                                <strong>Approved</strong>
                                <div class="text-muted small">Pending</div>
                            </div>
                        </li>
                    </ul>
                    <p class="text-muted mt-3 mb-0 small"><i class="bi bi-info-circle me-1"></i>Verification typically takes 1–3 business days. You will be notified by email once the review is complete.</p>
                </div>
            </div>

            <!-- ====================================================
                 NONE / REJECTED / EXPIRED — Multi-step form
            ===================================================== -->
            <?php else: ?>

            <?php if ($kycStatus === 'rejected' && $submission && !empty($submission['rejection_reason'])): ?>
            <div class="alert alert-danger mb-4">
                <h6 class="fw-bold"><i class="bi bi-x-circle me-1"></i>Rejection Reason</h6>
                <p class="mb-0"><?= e($submission['rejection_reason']) ?></p>
            </div>
            <?php endif; ?>

            <!-- Step Indicator -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <?php foreach ([1 => ['Business Info', 'building'], 2 => ['Documents', 'file-earmark-arrow-up'], 3 => ['Review', 'check-circle']] as $n => [$label, $icon]): ?>
                        <div class="d-flex align-items-center flex-fill <?= $n < 3 ? 'me-2' : '' ?>">
                            <div class="d-flex flex-column align-items-center text-center flex-fill">
                                <div class="rounded-circle d-flex align-items-center justify-content-center mb-1 <?= $step >= $n ? 'bg-primary text-white' : 'bg-light text-muted' ?>" style="width:36px;height:36px">
                                    <i class="bi bi-<?= $step > $n ? 'check-lg' : $icon ?>"></i>
                                </div>
                                <small class="fw-semibold <?= $step === $n ? 'text-primary' : 'text-muted' ?>"><?= $label ?></small>
                            </div>
                            <?php if ($n < 3): ?>
                            <div class="flex-fill border-top border-2 <?= $step > $n ? 'border-primary' : 'border-secondary border-opacity-25' ?> mb-3"></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Step 1: Business Info -->
            <?php if ($step === 1): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-building text-primary me-2"></i>Step 1: Business Information</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="/api/kyc.php?action=submit" id="step1Form">
                        <?= csrfField() ?>
                        <input type="hidden" name="_redirect" value="/pages/account/kyc.php?step=2">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Business Name <span class="text-danger">*</span></label>
                                <input type="text" name="business_name" class="form-control" value="<?= e($submission['business_name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Registration Number</label>
                                <input type="text" name="registration_number" class="form-control" value="<?= e($submission['registration_number'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Business Type <span class="text-danger">*</span></label>
                                <div class="row g-2 mt-1">
                                    <?php $types = ['sole_proprietorship' => 'Sole Proprietorship', 'partnership' => 'Partnership', 'llc' => 'LLC', 'corporation' => 'Corporation', 'nonprofit' => 'Non-Profit', 'other' => 'Other']; ?>
                                    <?php foreach ($types as $val => $lbl): ?>
                                    <div class="col-md-4 col-6">
                                        <div class="form-check form-check-card p-0">
                                            <label class="form-check-label d-block border rounded p-2 cursor-pointer <?= ($submission['business_type'] ?? '') === $val ? 'border-primary bg-primary bg-opacity-10' : '' ?>" style="cursor:pointer">
                                                <input type="radio" name="business_type" value="<?= $val ?>" class="form-check-input me-2" <?= ($submission['business_type'] ?? '') === $val ? 'checked' : '' ?> required>
                                                <?= $lbl ?>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Tax ID / VAT Number</label>
                                <input type="text" name="tax_id" class="form-control" value="<?= e($submission['tax_id'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Country <span class="text-danger">*</span></label>
                                <select name="country" class="form-select" required>
                                    <option value="">Select Country...</option>
                                    <?php
                                    $countries = ['US'=>'United States','CN'=>'China','GB'=>'United Kingdom','DE'=>'Germany','FR'=>'France','JP'=>'Japan','IN'=>'India','CA'=>'Canada','AU'=>'Australia','BR'=>'Brazil','MX'=>'Mexico','SG'=>'Singapore','AE'=>'United Arab Emirates','SA'=>'Saudi Arabia','ZA'=>'South Africa','NG'=>'Nigeria','KE'=>'Kenya','GH'=>'Ghana','PK'=>'Pakistan','BD'=>'Bangladesh','ID'=>'Indonesia','MY'=>'Malaysia','TH'=>'Thailand','VN'=>'Vietnam','PH'=>'Philippines','TR'=>'Turkey','IT'=>'Italy','ES'=>'Spain','NL'=>'Netherlands','CH'=>'Switzerland','SE'=>'Sweden','NO'=>'Norway','DK'=>'Denmark','PL'=>'Poland','RU'=>'Russia','UA'=>'Ukraine','OTHER'=>'Other'];
                                    $sel = $submission['country'] ?? '';
                                    foreach ($countries as $code => $name): ?>
                                    <option value="<?= e($code) ?>" <?= $sel === $code ? 'selected' : '' ?>><?= e($name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Address <span class="text-danger">*</span></label>
                                <input type="text" name="address" class="form-control" value="<?= e($submission['address'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">City <span class="text-danger">*</span></label>
                                <input type="text" name="city" class="form-control" value="<?= e($submission['city'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">State / Province</label>
                                <input type="text" name="state" class="form-control" value="<?= e($submission['state'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Postal Code</label>
                                <input type="text" name="postal_code" class="form-control" value="<?= e($submission['postal_code'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="mt-4 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary px-4">
                                Next: Upload Documents <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Step 2: Document Upload -->
            <?php elseif ($step === 2): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-file-earmark-arrow-up text-primary me-2"></i>Step 2: Document Upload</h5>
                </div>
                <div class="card-body p-4">
                    <?php if (!$submissionId): ?>
                    <div class="alert alert-warning">Please complete Step 1 first. <a href="/pages/account/kyc.php?step=1">Go to Step 1</a></div>
                    <?php else: ?>
                    <p class="text-muted mb-4">Upload supporting documents for your KYC verification. Accepted formats: JPG, PNG, PDF (max 5 MB each).</p>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Document Type</label>
                        <select id="docType" class="form-select" style="max-width:320px">
                            <option value="">Select document type...</option>
                            <option value="business_registration">Business Registration Certificate</option>
                            <option value="tax_certificate">Tax Certificate</option>
                            <option value="identity_document">Identity Document (Passport/ID)</option>
                            <option value="proof_of_address">Proof of Address</option>
                            <option value="bank_statement">Bank Statement</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <!-- Drop Zone -->
                    <div id="dropZone" class="border border-2 border-dashed rounded p-5 text-center mb-4" style="cursor:pointer; transition:background .2s" ondragover="event.preventDefault();this.classList.add('bg-light')" ondragleave="this.classList.remove('bg-light')" ondrop="handleDrop(event)">
                        <i class="bi bi-cloud-arrow-up fs-1 text-muted mb-2 d-block"></i>
                        <p class="mb-1 fw-semibold">Drag &amp; drop files here</p>
                        <p class="text-muted small mb-3">or</p>
                        <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('fileInput').click()">
                            <i class="bi bi-folder2-open me-1"></i> Browse Files
                        </button>
                        <input type="file" id="fileInput" class="d-none" accept=".jpg,.jpeg,.png,.pdf" multiple>
                        <p class="text-muted small mt-3 mb-0">Accepted: JPG, PNG, PDF &bull; Max 5 MB per file</p>
                    </div>

                    <!-- Upload Progress / Results -->
                    <div id="uploadList" class="mb-4"></div>

                    <!-- Uploaded Documents -->
                    <?php
                    try {
                        $db = getDB();
                        $docs = $db->prepare('SELECT * FROM kyc_documents WHERE kyc_submission_id = ? ORDER BY created_at DESC');
                        $docs->execute([$submissionId]);
                        $uploadedDocs = $docs->fetchAll();
                    } catch (PDOException $e) {
                        $uploadedDocs = [];
                    }
                    ?>
                    <?php if (!empty($uploadedDocs)): ?>
                    <h6 class="fw-semibold mb-3">Uploaded Documents</h6>
                    <div class="list-group mb-4">
                        <?php foreach ($uploadedDocs as $doc): ?>
                        <div class="list-group-item d-flex align-items-center gap-3">
                            <i class="bi bi-<?= str_ends_with($doc['file_path'], '.pdf') ? 'file-earmark-pdf text-danger' : 'file-earmark-image text-primary' ?> fs-4"></i>
                            <div class="flex-fill">
                                <div class="fw-semibold small"><?= e($doc['file_name']) ?></div>
                                <small class="text-muted"><?= e(ucfirst(str_replace('_', ' ', $doc['document_type']))) ?> &bull; <?= round($doc['file_size'] / 1024) ?> KB</small>
                            </div>
                            <span class="badge bg-<?= ($doc['status'] ?? 'pending') === 'verified' ? 'success' : (($doc['status'] ?? 'pending') === 'rejected' ? 'danger' : 'warning') ?>">
                                <?= e(ucfirst($doc['status'] ?? 'pending')) ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between">
                        <a href="/pages/account/kyc.php?step=1" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </a>
                        <a href="/pages/account/kyc.php?step=3" class="btn btn-primary px-4">
                            Next: Review &amp; Submit <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Step 3: Review & Submit -->
            <?php elseif ($step === 3): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-check-circle text-primary me-2"></i>Step 3: Review &amp; Submit</h5>
                </div>
                <div class="card-body p-4">
                    <?php if (!$submission): ?>
                    <div class="alert alert-warning">Please complete Steps 1 and 2 first. <a href="/pages/account/kyc.php?step=1">Go to Step 1</a></div>
                    <?php else: ?>
                    <p class="text-muted mb-4">Please review your submission details before finalising. Once submitted, your application will be reviewed by our compliance team.</p>

                    <h6 class="fw-bold mb-3">Business Information</h6>
                    <div class="row g-3 mb-4">
                        <?php $fields = ['business_name'=>'Business Name','business_type'=>'Business Type','registration_number'=>'Registration No.','tax_id'=>'Tax ID','country'=>'Country','address'=>'Address','city'=>'City','state'=>'State','postal_code'=>'Postal Code']; ?>
                        <?php foreach ($fields as $key => $label): ?>
                        <?php if (!empty($submission[$key])): ?>
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block"><?= $label ?></small>
                                <strong><?= e(ucfirst(str_replace('_', ' ', $submission[$key]))) ?></strong>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <?php
                    try {
                        $db = getDB();
                        $docsStmt = $db->prepare('SELECT * FROM kyc_documents WHERE kyc_submission_id = ?');
                        $docsStmt->execute([$submission['id']]);
                        $reviewDocs = $docsStmt->fetchAll();
                    } catch (PDOException $e) {
                        $reviewDocs = [];
                    }
                    ?>
                    <?php if (!empty($reviewDocs)): ?>
                    <h6 class="fw-bold mb-3">Documents (<?= count($reviewDocs) ?>)</h6>
                    <div class="list-group mb-4">
                        <?php foreach ($reviewDocs as $doc): ?>
                        <div class="list-group-item d-flex align-items-center gap-3">
                            <i class="bi bi-file-earmark-check text-success fs-4"></i>
                            <div>
                                <div class="fw-semibold small"><?= e($doc['file_name']) ?></div>
                                <small class="text-muted"><?= e(ucfirst(str_replace('_', ' ', $doc['document_type']))) ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning mb-4">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        No documents uploaded. <a href="/pages/account/kyc.php?step=2">Please upload at least one document.</a>
                    </div>
                    <?php endif; ?>

                    <div class="alert alert-info small mb-4">
                        <i class="bi bi-info-circle me-1"></i>
                        By submitting, you confirm that all information provided is accurate and complete. Submission of false information may result in account suspension.
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="/pages/account/kyc.php?step=2" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </a>
                        <?php if (!empty($reviewDocs)): ?>
                        <form method="POST" action="/api/kyc.php?action=finalize">
                            <?= csrfField() ?>
                            <input type="hidden" name="submission_id" value="<?= (int) $submission['id'] ?>">
                            <button type="submit" class="btn btn-success px-4">
                                <i class="bi bi-send-check me-1"></i> Submit for Review
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; // end step switch ?>

            <?php endif; // end status switch ?>

        </div><!-- /col -->
    </div><!-- /row -->
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const fileInput = document.getElementById('fileInput');
    const submissionId = <?= (int) $submissionId ?>;

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            uploadFiles(this.files);
        });
    }

    function handleDrop(e) {
        e.preventDefault();
        document.getElementById('dropZone').classList.remove('bg-light');
        uploadFiles(e.dataTransfer.files);
    }
    window.handleDrop = handleDrop;

    function uploadFiles(files) {
        const docType = document.getElementById('docType');
        if (!docType || !docType.value) {
            alert('Please select a document type before uploading.');
            return;
        }
        if (!submissionId) {
            alert('Please complete Step 1 first.');
            return;
        }
        Array.from(files).forEach(file => {
            const allowed = ['image/jpeg', 'image/png', 'application/pdf'];
            if (!allowed.includes(file.type)) {
                appendUploadResult(file.name, 'error', 'Invalid file type. Allowed: JPG, PNG, PDF.');
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                appendUploadResult(file.name, 'error', 'File exceeds 5 MB limit.');
                return;
            }
            doUpload(file, docType.value);
        });
    }

    function doUpload(file, docType) {
        const itemId = 'upload-' + Date.now() + '-' + Math.random().toString(36).slice(2);
        appendUploadResult(file.name, 'uploading', 'Uploading...', itemId);

        const formData = new FormData();
        formData.append('file', file);
        formData.append('document_type', docType);
        formData.append('submission_id', submissionId);
        formData.append('_csrf_token', '<?= e(csrfToken()) ?>');

        fetch('/api/kyc.php?action=upload_document', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                updateUploadResult(itemId, 'success', 'Uploaded successfully');
                setTimeout(() => location.reload(), 1200);
            } else {
                updateUploadResult(itemId, 'error', data.message || 'Upload failed.');
            }
        })
        .catch(() => updateUploadResult(itemId, 'error', 'Network error. Please try again.'));
    }

    function appendUploadResult(name, status, message, id) {
        const list = document.getElementById('uploadList');
        if (!list) return;
        const icons = {uploading: 'hourglass-split text-warning', success: 'check-circle-fill text-success', error: 'x-circle-fill text-danger'};
        const div = document.createElement('div');
        div.className = 'alert alert-light d-flex align-items-center gap-2 py-2 mb-2';
        if (id) div.id = id;
        div.innerHTML = `<i class="bi bi-${icons[status] || 'file-earmark'}"></i><span class="flex-fill small">${escHtml(name)}</span><small class="text-muted">${escHtml(message)}</small>`;
        list.appendChild(div);
    }

    function updateUploadResult(id, status, message) {
        const el = document.getElementById(id);
        if (!el) return;
        const icons = {uploading: 'hourglass-split text-warning', success: 'check-circle-fill text-success', error: 'x-circle-fill text-danger'};
        el.querySelector('i').className = 'bi bi-' + (icons[status] || 'file-earmark');
        el.querySelector('small').textContent = message;
    }

    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
});
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
