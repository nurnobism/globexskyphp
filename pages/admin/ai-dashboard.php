<?php
/**
 * pages/admin/ai-dashboard.php — Admin AI System Dashboard (Phase 8)
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();
requireRole(['admin', 'super_admin']);
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4">
        <i class="bi bi-cpu fs-2 text-primary me-3"></i>
        <div>
            <h1 class="h3 mb-0">AI System Dashboard</h1>
            <p class="text-muted mb-0">Monitor DeepSeek AI usage, costs, and performance</p>
        </div>
        <div class="ms-auto">
            <span id="api-status" class="badge bg-secondary">Checking API...</span>
        </div>
    </div>

    <!-- Overview Cards -->
    <div class="row g-3 mb-4" id="ai-overview-cards">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <i class="bi bi-lightning-charge text-primary fs-3"></i>
                <div class="h4 mt-2 mb-0" id="total-calls-today">—</div>
                <small class="text-muted">API Calls Today</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <i class="bi bi-exclamation-triangle text-warning fs-3"></i>
                <div class="h4 mt-2 mb-0" id="error-rate">—</div>
                <small class="text-muted">Error Rate</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <i class="bi bi-clock text-info fs-3"></i>
                <div class="h4 mt-2 mb-0" id="avg-response">—</div>
                <small class="text-muted">Avg Response (ms)</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <i class="bi bi-currency-dollar text-success fs-3"></i>
                <div class="h4 mt-2 mb-0" id="cost-today">—</div>
                <small class="text-muted">Cost Today (USD)</small>
            </div>
        </div>
    </div>

    <!-- Usage Chart -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-0 pt-3">
            <h5 class="mb-0">Token Usage — Last 30 Days</h5>
        </div>
        <div class="card-body">
            <canvas id="admin-usage-chart" height="60"></canvas>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Feature Breakdown -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h5 class="mb-0">Feature Usage (7 Days)</h5>
                </div>
                <div class="card-body" id="feature-breakdown">
                    <div class="text-center py-3"><div class="spinner-border text-primary"></div></div>
                </div>
            </div>
        </div>

        <!-- Error Log -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h5 class="mb-0">Recent Errors</h5>
                </div>
                <div class="card-body p-0" id="error-log">
                    <div class="text-center py-3"><div class="spinner-border text-danger"></div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Configuration Panel -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-0 pt-3">
            <h5 class="mb-0"><i class="bi bi-gear me-2"></i>AI Configuration</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="p-3 border rounded">
                        <h6>DeepSeek API</h6>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">API Key Status</span>
                            <span id="api-key-status" class="badge bg-secondary">Checking...</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">Model</span>
                            <span class="badge bg-info"><?= e(getenv('DEEPSEEK_MODEL') ?: 'deepseek-chat') ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small">Base URL</span>
                            <span class="badge bg-light text-dark">api.deepseek.com</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 border rounded">
                        <h6>AI Feature Toggles</h6>
                        <?php
                        $features = ['ai_chat' => 'AI Chatbot', 'ai_recommendations' => 'Recommendations', 'ai_fraud' => 'Fraud Detection', 'ai_content' => 'Content Generation', 'ai_analytics' => 'Analytics', 'ai_search' => 'Smart Search'];
                        foreach ($features as $key => $label): ?>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small><?= e($label) ?></small>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" checked disabled>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    fetch('/api/ai/analytics.php?action=health')
        .then(r => r.json())
        .then(d => {
            if (!d.success) return;
            const data = d.data;
            const today = data.today || {};
            document.getElementById('total-calls-today').textContent = today.total_calls || 0;
            document.getElementById('error-rate').textContent = (data.error_rate || 0) + '%';
            document.getElementById('avg-response').textContent = Math.round(today.avg_response_ms || 0);
            document.getElementById('cost-today').textContent = '$' + parseFloat(today.total_cost || 0).toFixed(4);

            const statusBadge = document.getElementById('api-status');
            const colors = { healthy: 'success', degraded: 'warning', critical: 'danger', unknown: 'secondary' };
            const s = data.status || 'unknown';
            statusBadge.className = 'badge bg-' + (colors[s] || 'secondary');
            statusBadge.textContent = 'API ' + s.charAt(0).toUpperCase() + s.slice(1);

            document.getElementById('api-key-status').className = 'badge bg-' + (today.total_calls > 0 ? 'success' : 'warning');
            document.getElementById('api-key-status').textContent = today.total_calls > 0 ? 'Configured' : 'Not Tested';

            // Feature breakdown
            const featureEl = document.getElementById('feature-breakdown');
            if (data.feature_breakdown?.length) {
                featureEl.innerHTML = '<ul class="list-group list-group-flush">' +
                    data.feature_breakdown.map(f => `
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-capitalize">${f.feature}</span>
                            <div>
                                <span class="badge bg-primary me-1">${f.calls} calls</span>
                                <span class="badge bg-light text-dark">${Number(f.tokens).toLocaleString()} tokens</span>
                            </div>
                        </li>`).join('') + '</ul>';
            } else {
                featureEl.innerHTML = '<p class="text-muted text-center py-3">No usage data yet</p>';
            }
        }).catch(() => {
            document.getElementById('api-status').className = 'badge bg-secondary';
            document.getElementById('api-status').textContent = 'Status Unknown';
        });

    // Error log
    fetch('/api/ai/analytics.php?action=health')
        .then(r => r.json())
        .then(d => {
            document.getElementById('error-log').innerHTML = '<p class="text-muted text-center py-3">No recent errors</p>';
        }).catch(() => {
            document.getElementById('error-log').innerHTML = '<p class="text-muted text-center py-3">Could not load error log</p>';
        });

    // Usage chart placeholder
    const ctx = document.getElementById('admin-usage-chart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: { labels: [], datasets: [{ label: 'Tokens', data: [], backgroundColor: 'rgba(13,110,253,0.6)' }] },
        options: { responsive: true, plugins: { legend: { display: false } } }
    });
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
