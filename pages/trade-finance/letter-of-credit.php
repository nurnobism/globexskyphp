<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];

$lcs = $db->prepare("SELECT * FROM letters_of_credit WHERE applicant_id = ? ORDER BY created_at DESC");
$lcs->execute([$userId]);
$lcList = $lcs->fetchAll();

$pageTitle = 'Letter of Credit';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-file-earmark-lock text-primary me-2"></i>Letter of Credit</h3>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newLCModal">
            <i class="bi bi-plus-circle me-1"></i> Apply for LC
        </button>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 bg-primary text-white">
                <div class="card-body text-center py-4">
                    <i class="bi bi-shield-check display-5 mb-2"></i>
                    <h5>Secure Transactions</h5>
                    <p class="mb-0 opacity-75 small">LC guarantees payment when conditions are met</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 bg-success text-white">
                <div class="card-body text-center py-4">
                    <i class="bi bi-globe display-5 mb-2"></i>
                    <h5>International Trade</h5>
                    <p class="mb-0 opacity-75 small">Accepted by banks worldwide</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 bg-warning text-white">
                <div class="card-body text-center py-4">
                    <i class="bi bi-clock display-5 mb-2"></i>
                    <h5>Fast Processing</h5>
                    <p class="mb-0 opacity-75 small">2-5 business days for approval</p>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($lcList)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-file-earmark-text text-muted display-3"></i>
            <h5 class="mt-3 text-muted">No Letters of Credit</h5>
            <p class="text-muted">Apply for your first LC to secure international transactions.</p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newLCModal">Apply Now</button>
        </div>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>LC Number</th><th>Beneficiary</th><th>Amount</th><th>Expiry</th><th>Type</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($lcList as $lc): ?>
                <?php $colors = ['draft'=>'secondary','submitted'=>'warning','approved'=>'success','rejected'=>'danger','expired'=>'dark']; ?>
                <tr>
                    <td><code><?= e($lc['lc_number'] ?? '—') ?></code></td>
                    <td><?= e($lc['beneficiary_name']) ?></td>
                    <td><?= formatMoney($lc['amount']) ?> <?= e($lc['currency'] ?? 'USD') ?></td>
                    <td><?= formatDate($lc['expiry_date']) ?></td>
                    <td><span class="badge bg-light text-dark border"><?= ucfirst($lc['lc_type'] ?? 'irrevocable') ?></span></td>
                    <td><span class="badge bg-<?= $colors[$lc['status']]??'secondary' ?>"><?= ucfirst($lc['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- New LC Modal -->
<div class="modal fade" id="newLCModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Apply for Letter of Credit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/api/trade-finance.php?action=apply_lc">
                <?= csrfField() ?>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">LC Type</label>
                            <select name="lc_type" class="form-select">
                                <option value="irrevocable">Irrevocable</option>
                                <option value="revocable">Revocable</option>
                                <option value="standby">Standby LC</option>
                                <option value="transferable">Transferable</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Currency</label>
                            <select name="currency" class="form-select">
                                <option value="USD">USD — US Dollar</option>
                                <option value="EUR">EUR — Euro</option>
                                <option value="GBP">GBP — British Pound</option>
                                <option value="CNY">CNY — Chinese Yuan</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Amount *</label>
                            <input type="number" name="amount" class="form-control" required min="1000" step="100" placeholder="e.g., 50000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Expiry Date *</label>
                            <input type="date" name="expiry_date" class="form-control" required min="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Beneficiary Name *</label>
                            <input type="text" name="beneficiary_name" class="form-control" required placeholder="Company or person name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Beneficiary Bank</label>
                            <input type="text" name="beneficiary_bank" class="form-control" placeholder="Bank name and country">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Goods Description *</label>
                            <textarea name="goods_description" class="form-control" rows="3" required
                                placeholder="Describe the goods covered by this LC..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Special Terms / Conditions</label>
                            <textarea name="special_terms" class="form-control" rows="2"
                                placeholder="Any special terms..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Application</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
