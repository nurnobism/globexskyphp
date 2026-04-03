<?php
/**
 * pages/ai/analytics.php — AI Business Analytics (Phase 8)
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();
require_once __DIR__ . '/../../includes/header.php';

$role = $_SESSION['role'] ?? 'buyer';
?>
<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4">
        <i class="bi bi-graph-up fs-2 text-success me-3"></i>
        <div>
            <h1 class="h3 mb-0">AI Business Analytics</h1>
            <p class="text-muted mb-0">AI-powered insights, predictions, and forecasts</p>
        </div>
        <div class="ms-auto d-flex gap-2">
            <select class="form-select form-select-sm" id="period-select" style="width:120px;">
                <option value="7days">Last 7 days</option>
                <option value="30days" selected>Last 30 days</option>
                <option value="90days">Last 90 days</option>
            </select>
            <button class="btn btn-outline-success btn-sm" id="refresh-analytics"><i class="bi bi-arrow-clockwise"></i></button>
        </div>
    </div>

    <!-- Sales Prediction Chart -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-0 pt-3">
            <h5 class="mb-0">Sales Trends &amp; AI Forecast</h5>
        </div>
        <div class="card-body">
            <canvas id="salesChart" height="80"></canvas>
            <div id="sales-insights" class="mt-3 p-3 bg-light rounded border-start border-success border-3 d-none">
                <h6 class="text-success"><i class="bi bi-lightbulb me-2"></i>AI Insights</h6>
                <div id="sales-insights-text" class="text-muted small"></div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Demand Forecast -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h5 class="mb-0">Demand Forecasting</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small">Select Product</label>
                        <input type="number" class="form-control form-control-sm" id="forecast-product-id" placeholder="Product ID">
                    </div>
                    <button class="btn btn-primary btn-sm" id="forecast-btn">Generate Forecast</button>
                    <div id="demand-forecast-result" class="mt-3"></div>
                </div>
            </div>
        </div>

        <!-- Business Insights -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h5 class="mb-0">AI Business Insights</h5>
                </div>
                <div class="card-body" id="business-insights-container">
                    <div class="text-center py-3"><div class="spinner-border text-success"></div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Segments (admin/supplier only) -->
    <?php if (in_array($role, ['admin', 'super_admin', 'supplier'])): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-0 pt-3">
            <h5 class="mb-0">Customer Segment Analysis</h5>
        </div>
        <div class="card-body" id="segments-container">
            <div class="text-center py-3"><div class="spinner-border text-primary"></div></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="/assets/js/ai-analytics.js"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
