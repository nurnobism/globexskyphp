<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];
$type = trim($_GET['type'] ?? '');

$myApps = $db->prepare("SELECT * FROM trade_finance_applications WHERE applicant_id = ? ORDER BY created_at DESC");
$myApps->execute([$userId]);
$applications = $myApps->fetchAll();

$pageTitle = 'Trade Credit Application';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="row g-4">
        <!-- Application Form -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-bank me-2"></i>Apply for Trade Finance</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="/api/trade-finance.php?action=apply">
                        <?= csrfField() ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Finance Type *</label>
                                <select name="finance_type" class="form-select" required>
                                    <option value="">Select type...</option>
                                    <option value="net_30" <?= $type==='net_30'?'selected':'' ?>>Net 30 Payment Terms</option>
                                    <option value="net_60" <?= $type==='net_60'?'selected':'' ?>>Net 60 Payment Terms</option>
                                    <option value="net_90" <?= $type==='net_90'?'selected':'' ?>>Net 90 Payment Terms</option>
                                    <option value="lc" <?= $type==='lc'?'selected':'' ?>>Letter of Credit</option>
                                    <option value="trade_credit" <?= $type==='trade_credit'?'selected':'' ?>>Trade Credit Line</option>
                                    <option value="invoice_financing">Invoice Financing</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Requested Amount ($) *</label>
                                <input type="number" name="requested_amount" class="form-control" required min="1000" step="100" placeholder="e.g., 50000">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Business Name *</label>
                                <input type="text" name="business_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Business Type</label>
                                <select name="business_type" class="form-select">
                                    <option value="sole_proprietor">Sole Proprietor</option>
                                    <option value="partnership">Partnership</option>
                                    <option value="llc">LLC</option>
                                    <option value="corporation">Corporation</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Years in Business</label>
                                <input type="number" name="years_in_business" class="form-control" min="0" step="1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Annual Revenue ($)</label>
                                <input type="number" name="annual_revenue" class="form-control" min="0" step="1000">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Purpose of Finance *</label>
                                <textarea name="purpose" class="form-control" rows="3" required
                                    placeholder="Describe how you plan to use this finance..."></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Additional Information</label>
                                <textarea name="additional_info" class="form-control" rows="2"
                                    placeholder="Any additional information that supports your application..."></textarea>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-send me-1"></i> Submit Application
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- My Applications -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold">My Applications</h6>
                </div>
                <?php if (empty($applications)): ?>
                <div class="card-body text-center text-muted py-4">No applications yet.</div>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($applications as $a): ?>
                    <?php $colors = ['pending'=>'warning','under_review'=>'info','approved'=>'success','rejected'=>'danger']; ?>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-semibold small"><?= e(ucwords(str_replace('_',' ',$a['finance_type']))) ?></div>
                                <div class="text-muted small"><?= formatMoney($a['requested_amount']) ?></div>
                                <div class="text-muted small"><?= formatDate($a['created_at']) ?></div>
                            </div>
                            <span class="badge bg-<?= $colors[$a['status']]??'secondary' ?>"><?= ucfirst(str_replace('_',' ',$a['status'])) ?></span>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
