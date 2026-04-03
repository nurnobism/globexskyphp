<?php
/**
 * pages/ai/fraud-detection.php — Fraud Detection Dashboard (Phase 8)
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();
requireRole(['admin', 'super_admin', 'support']);

require_once __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4">
        <i class="bi bi-shield-exclamation fs-2 text-danger me-3"></i>
        <div>
            <h1 class="h3 mb-0">AI Fraud Detection</h1>
            <p class="text-muted mb-0">Real-time risk analysis powered by DeepSeek</p>
        </div>
    </div>

    <!-- Risk Overview Cards -->
    <div class="row g-3 mb-4" id="risk-overview">
        <div class="col-6 col-md-3">
            <div class="card border-0 border-start border-danger border-4 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="text-muted small">Critical</div>
                            <div class="h3 mb-0 text-danger" id="count-critical">—</div>
                        </div>
                        <i class="bi bi-exclamation-octagon fs-2 text-danger opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 border-start border-warning border-4 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="text-muted small">High</div>
                            <div class="h3 mb-0 text-warning" id="count-high">—</div>
                        </div>
                        <i class="bi bi-exclamation-triangle fs-2 text-warning opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 border-start border-info border-4 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="text-muted small">Medium</div>
                            <div class="h3 mb-0 text-info" id="count-medium">—</div>
                        </div>
                        <i class="bi bi-exclamation-circle fs-2 text-info opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 border-start border-success border-4 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="text-muted small">False Positive Rate</div>
                            <div class="h3 mb-0 text-success" id="false-positive-rate">—</div>
                        </div>
                        <i class="bi bi-check-circle fs-2 text-success opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small">Entity Type</label>
                    <select class="form-select form-select-sm" id="filter-entity">
                        <option value="">All Types</option>
                        <option value="order">Order</option>
                        <option value="user">User</option>
                        <option value="review">Review</option>
                        <option value="transaction">Transaction</option>
                        <option value="listing">Listing</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Risk Level</label>
                    <select class="form-select form-select-sm" id="filter-risk">
                        <option value="">All Levels</option>
                        <option value="critical">Critical</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Action</label>
                    <select class="form-select form-select-sm" id="filter-action">
                        <option value="">All Actions</option>
                        <option value="none">None</option>
                        <option value="flag">Flagged</option>
                        <option value="hold">Hold</option>
                        <option value="block">Blocked</option>
                        <option value="notify_admin">Notified Admin</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Date From</label>
                    <input type="date" class="form-control form-control-sm" id="filter-date-from">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Date To</label>
                    <input type="date" class="form-control form-control-sm" id="filter-date-to">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary btn-sm w-100" id="apply-filters-btn">Apply Filters</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts Table -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-0 pt-3 d-flex align-items-center">
            <h5 class="mb-0">Fraud Alerts</h5>
            <span class="badge bg-danger ms-2" id="alerts-count">0</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Entity</th>
                            <th>Risk Score</th>
                            <th>Level</th>
                            <th>Factors</th>
                            <th>Action</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="alerts-table-body">
                        <tr><td colspan="7" class="text-center py-4"><div class="spinner-border text-danger"></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="alertDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Fraud Alert Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modal-body-content">Loading...</div>
            <div class="modal-footer">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-success btn-sm" id="modal-false-positive-btn">Mark False Positive</button>
                <button class="btn btn-warning btn-sm" id="modal-flag-btn">Flag</button>
                <button class="btn btn-danger btn-sm" id="modal-block-btn">Block</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="/assets/js/ai-fraud.js"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
