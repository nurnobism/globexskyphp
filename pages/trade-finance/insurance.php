<?php
require_once __DIR__ . '/../../includes/middleware.php';

$pageTitle = 'Trade Insurance';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <h3 class="fw-bold mb-4"><i class="bi bi-shield-check text-info me-2"></i>Trade Insurance</h3>

    <!-- Coverage Types -->
    <div class="row g-4 mb-5">
        <?php $plans = [
            ['Cargo Insurance', 'box-seam', 'primary', 'Protect your goods during transit from origin to destination.', '$50,000', '0.3% of cargo value', ['All risk coverage', 'Door to door', 'Global coverage', 'Express claims']],
            ['Credit Insurance', 'credit-card', 'success', 'Protect against non-payment by your international buyers.', '$250,000', '0.5% of invoice value', ['Buyer insolvency', 'Political risk', 'Pre-shipment cover', 'Collections support']],
            ['Political Risk', 'globe-americas', 'warning', 'Coverage for losses caused by political events in the destination country.', '$500,000', '0.8% of contract value', ['War & civil unrest', 'Trade embargo', 'Currency restriction', 'Expropriation']],
            ['Product Liability', 'exclamation-triangle', 'danger', 'Cover legal costs and damages if your product causes injury or damage.', '$1,000,000', 'Contact for pricing', ['Legal defense', 'Compensation costs', 'Recall expenses', 'Global coverage']],
        ]; ?>
        <?php foreach ($plans as [$name, $icon, $color, $desc, $maxCover, $rate, $features]): ?>
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-<?= $color ?> bg-opacity-10 text-center py-3">
                    <i class="bi bi-<?= $icon ?> text-<?= $color ?> display-5"></i>
                    <h6 class="mt-2 fw-bold mb-0 text-dark"><?= $name ?></h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3"><?= $desc ?></p>
                    <div class="mb-3">
                        <div class="small text-muted">Max Coverage</div>
                        <strong class="text-<?= $color ?>"><?= $maxCover ?></strong>
                    </div>
                    <div class="mb-3">
                        <div class="small text-muted">Starting Rate</div>
                        <strong><?= $rate ?></strong>
                    </div>
                    <ul class="list-unstyled small">
                        <?php foreach ($features as $f): ?>
                        <li class="mb-1"><i class="bi bi-check2 text-<?= $color ?> me-1"></i><?= $f ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="card-footer bg-white border-0">
                    <?php if (isLoggedIn()): ?>
                    <button class="btn btn-<?= $color ?> w-100 btn-sm" data-bs-toggle="modal" data-bs-target="#insuranceModal">
                        Get Quote
                    </button>
                    <?php else: ?>
                    <a href="/pages/auth/login.php" class="btn btn-outline-<?= $color ?> w-100 btn-sm">Login to Apply</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Why Choose Us -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-4 text-center">Why GlobexSky Trade Insurance?</h5>
            <div class="row g-4 text-center">
                <?php $features = [
                    ['lightning-charge', 'warning', 'Fast Claims', 'Claims processed within 14 business days'],
                    ['globe2', 'primary', 'Global Coverage', 'Coverage in 150+ countries worldwide'],
                    ['people-fill', 'success', 'Dedicated Support', '24/7 claims assistance team'],
                    ['patch-check', 'info', 'Rated A+', 'Backed by AA-rated insurance partners'],
                ]; ?>
                <?php foreach ($features as [$icon, $color, $title, $desc]): ?>
                <div class="col-md-3">
                    <div class="rounded-circle bg-<?= $color ?> bg-opacity-10 p-3 d-inline-flex mb-2">
                        <i class="bi bi-<?= $icon ?> text-<?= $color ?> fs-3"></i>
                    </div>
                    <h6 class="fw-bold"><?= $title ?></h6>
                    <p class="text-muted small mb-0"><?= $desc ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Get Insurance Quote Modal -->
<div class="modal fade" id="insuranceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Get Insurance Quote</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/api/trade-finance.php?action=insurance_quote">
                <?= csrfField() ?>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Insurance Type</label>
                            <select name="insurance_type" class="form-select">
                                <option value="cargo">Cargo Insurance</option>
                                <option value="credit">Credit Insurance</option>
                                <option value="political_risk">Political Risk</option>
                                <option value="product_liability">Product Liability</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Coverage Amount ($) *</label>
                            <input type="number" name="coverage_amount" class="form-control" required min="1000" step="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Origin Country</label>
                            <input type="text" name="origin_country" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Destination Country</label>
                            <input type="text" name="destination_country" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description of Goods/Transaction</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Request Quote</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
