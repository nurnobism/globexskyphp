<?php
/**
 * pages/ai/insights.php — AI Business Insights (Phase 8)
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();
require_once __DIR__ . '/../../includes/header.php';

$role = $_SESSION['role'] ?? 'buyer';
?>
<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4">
        <i class="bi bi-lightbulb fs-2 text-warning me-3"></i>
        <div>
            <h1 class="h3 mb-0">AI Business Insights</h1>
            <p class="text-muted mb-0">Daily AI-generated intelligence for your business</p>
        </div>
        <div class="ms-auto">
            <button class="btn btn-warning text-dark btn-sm" id="refresh-insights-btn"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
        </div>
    </div>

    <!-- Daily Briefing -->
    <div class="card border-0 shadow mb-4 border-start border-warning border-4">
        <div class="card-header bg-transparent border-0 pt-3">
            <h5 class="mb-0"><i class="bi bi-newspaper me-2 text-warning"></i>Daily Business Briefing</h5>
            <small class="text-muted"><?= date('l, F j, Y') ?></small>
        </div>
        <div class="card-body" id="daily-briefing">
            <div class="text-center py-3"><div class="spinner-border text-warning"></div></div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Anomalies -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h5 class="mb-0"><i class="bi bi-radar me-2 text-danger"></i>Anomaly Detection</h5>
                </div>
                <div class="card-body" id="anomalies-container">
                    <div class="text-center py-3"><div class="spinner-border text-danger"></div></div>
                </div>
            </div>
        </div>

        <!-- Opportunities -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h5 class="mb-0"><i class="bi bi-rocket-takeoff me-2 text-success"></i>Growth Opportunities</h5>
                </div>
                <div class="card-body" id="opportunities-container">
                    <div class="text-center py-3"><div class="spinner-border text-success"></div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Actionable Recommendations -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-0 pt-3">
            <h5 class="mb-0"><i class="bi bi-check2-circle me-2 text-primary"></i>Actionable Recommendations</h5>
        </div>
        <div class="card-body" id="recommendations-container">
            <div class="text-center py-3"><div class="spinner-border text-primary"></div></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    function loadInsights() {
        // Daily briefing
        fetch('/api/ai/insights.php?action=daily_briefing')
            .then(r => r.json())
            .then(d => {
                const el = document.getElementById('daily-briefing');
                if (d.success) {
                    el.innerHTML = '<p class="mb-0">' + (d.data.briefing || 'No briefing available.').replace(/\n/g, '<br>') + '</p>';
                } else {
                    el.innerHTML = '<p class="text-muted">Briefing unavailable. Please configure AI API key.</p>';
                }
            }).catch(() => { document.getElementById('daily-briefing').innerHTML = '<p class="text-muted">Could not load briefing.</p>'; });

        // Anomalies
        fetch('/api/ai/insights.php?action=anomalies')
            .then(r => r.json())
            .then(d => {
                const el = document.getElementById('anomalies-container');
                if (d.success && d.data.length) {
                    el.innerHTML = d.data.map(a => `
                        <div class="alert alert-${a.severity === 'high' ? 'danger' : a.severity === 'medium' ? 'warning' : 'info'} py-2 mb-2">
                            <strong>${a.type || 'Anomaly'}:</strong> ${a.detail || ''}
                            ${a.recommendation ? '<br><small>' + a.recommendation + '</small>' : ''}
                        </div>`).join('');
                } else {
                    el.innerHTML = '<div class="text-center text-muted py-3"><i class="bi bi-check-circle-fill text-success fs-2 d-block mb-2"></i>No anomalies detected</div>';
                }
            }).catch(() => { document.getElementById('anomalies-container').innerHTML = '<p class="text-muted">Could not load anomalies.</p>'; });

        // Opportunities
        fetch('/api/ai/insights.php?action=opportunities')
            .then(r => r.json())
            .then(d => {
                const el = document.getElementById('opportunities-container');
                if (d.success && d.data.length) {
                    el.innerHTML = d.data.map(o => `
                        <div class="card mb-2 border-start border-success border-3">
                            <div class="card-body py-2">
                                <div class="d-flex justify-content-between">
                                    <strong>${o.title || ''}</strong>
                                    <span class="badge bg-${o.difficulty === 'easy' ? 'success' : o.difficulty === 'hard' ? 'danger' : 'warning'}">${o.difficulty || ''}</span>
                                </div>
                                <p class="small text-muted mb-1">${o.description || ''}</p>
                                <small class="text-success">${o.estimated_impact || ''}</small>
                            </div>
                        </div>`).join('');
                } else {
                    el.innerHTML = '<div class="text-center text-muted py-3">No opportunities identified yet</div>';
                }
            }).catch(() => { document.getElementById('opportunities-container').innerHTML = '<p class="text-muted">Could not load opportunities.</p>'; });

        // Recommendations (reuse insights)
        fetch('/api/ai/analytics.php?action=insights')
            .then(r => r.json())
            .then(d => {
                const el = document.getElementById('recommendations-container');
                if (d.success && d.data.insights?.length) {
                    el.innerHTML = d.data.insights.map(i => `
                        <div class="d-flex align-items-start mb-3">
                            <span class="badge bg-${i.priority === 'high' ? 'danger' : i.priority === 'medium' ? 'warning' : 'secondary'} me-3 mt-1">${i.priority || 'low'}</span>
                            <div>
                                <strong>${i.title || ''}</strong>
                                <p class="text-muted small mb-1">${i.description || ''}</p>
                                ${i.action ? '<a href="#" class="btn btn-sm btn-outline-primary">' + i.action + '</a>' : ''}
                            </div>
                        </div>`).join('<hr>');
                } else {
                    el.innerHTML = '<p class="text-muted">No recommendations available. Configure AI API key to enable insights.</p>';
                }
            }).catch(() => { document.getElementById('recommendations-container').innerHTML = '<p class="text-muted">Could not load recommendations.</p>'; });
    }

    loadInsights();
    document.getElementById('refresh-insights-btn').addEventListener('click', loadInsights);
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
