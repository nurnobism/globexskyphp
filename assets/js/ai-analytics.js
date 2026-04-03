/**
 * assets/js/ai-analytics.js — AI Analytics UI (Phase 8)
 */
(function() {
    'use strict';

    let salesChart = null;

    function loadSalesTrends() {
        const period = document.getElementById('period-select')?.value || '30days';
        fetch('/api/ai/analytics.php?action=sales_trends&period=' + period)
            .then(r => r.json())
            .then(d => {
                if (!d.success) return;
                const data = d.data;
                const labels = data.historical?.map(h => h.date) || [];
                const revenues = data.historical?.map(h => parseFloat(h.revenue || 0)) || [];
                const predLabels = data.prediction?.map(p => p.date) || [];
                const predRevenues = data.prediction?.map(p => parseFloat(p.revenue || 0)) || [];

                const ctx = document.getElementById('salesChart');
                if (!ctx) return;
                if (salesChart) salesChart.destroy();
                salesChart = new Chart(ctx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: [...labels, ...predLabels],
                        datasets: [
                            {
                                label: 'Actual Revenue',
                                data: [...revenues, ...Array(predLabels.length).fill(null)],
                                borderColor: '#0d6efd',
                                backgroundColor: 'rgba(13,110,253,0.1)',
                                fill: true,
                                tension: 0.4,
                            },
                            {
                                label: 'AI Forecast',
                                data: [...Array(labels.length).fill(null), revenues[revenues.length - 1], ...predRevenues],
                                borderColor: '#198754',
                                backgroundColor: 'rgba(25,135,84,0.1)',
                                borderDash: [5, 5],
                                fill: false,
                                tension: 0.4,
                            }
                        ]
                    },
                    options: { responsive: true, plugins: { legend: { position: 'top' } } }
                });

                const insightsEl = document.getElementById('sales-insights');
                const insightsText = document.getElementById('sales-insights-text');
                if (insightsEl && insightsText && data.ai_insights) {
                    insightsText.textContent = data.ai_insights;
                    insightsEl.classList.remove('d-none');
                }
            }).catch(() => {});
    }

    function loadBusinessInsights() {
        const container = document.getElementById('business-insights-container');
        if (!container) return;
        fetch('/api/ai/analytics.php?action=insights')
            .then(r => r.json())
            .then(d => {
                if (d.success && d.data.insights?.length) {
                    container.innerHTML = d.data.insights.map(ins => `
                        <div class="d-flex align-items-start mb-3">
                            <span class="badge bg-${ins.priority === 'high' ? 'danger' : ins.priority === 'medium' ? 'warning' : 'secondary'} me-2 mt-1">${ins.priority || 'low'}</span>
                            <div>
                                <strong class="d-block">${ins.title || ''}</strong>
                                <small class="text-muted">${ins.description || ''}</small>
                            </div>
                        </div>`).join('<hr class="my-2">');
                } else {
                    container.innerHTML = '<p class="text-muted">No insights available. Configure AI to generate insights.</p>';
                }
            }).catch(() => { container.innerHTML = '<p class="text-muted">Could not load insights.</p>'; });
    }

    function loadSegments() {
        const container = document.getElementById('segments-container');
        if (!container) return;
        fetch('/api/ai/analytics.php?action=customer_segments')
            .then(r => r.json())
            .then(d => {
                if (d.success && d.data.segments?.length) {
                    container.innerHTML = d.data.segments.map(seg => `
                        <div class="card border-0 bg-light mb-2">
                            <div class="card-body py-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong>${seg.name || 'Segment'}</strong>
                                    <span class="badge bg-primary">${seg.size || 0} users</span>
                                </div>
                                <small class="text-muted">${(seg.characteristics || []).join(', ')}</small>
                            </div>
                        </div>`).join('');
                } else {
                    container.innerHTML = '<p class="text-muted">No segment data available.</p>';
                }
            }).catch(() => { container.innerHTML = '<p class="text-muted">Could not load segments.</p>'; });
    }

    // Demand forecast
    const forecastBtn = document.getElementById('forecast-btn');
    if (forecastBtn) {
        forecastBtn.addEventListener('click', () => {
            const productId = document.getElementById('forecast-product-id')?.value;
            if (!productId) { alert('Please enter a Product ID'); return; }
            const result = document.getElementById('demand-forecast-result');
            result.innerHTML = '<div class="spinner-border spinner-border-sm"></div>';
            fetch(`/api/ai/analytics.php?action=demand_forecast&product_id=${productId}`)
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        const data = d.data;
                        result.innerHTML = `
                            <div class="p-3 bg-light rounded mt-2">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Predicted Units:</span>
                                    <strong>${data.predicted_units || 0}</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Trend:</span>
                                    <span class="badge bg-info">${data.trend || 'stable'}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Confidence:</span>
                                    <span class="badge bg-${data.confidence === 'high' ? 'success' : data.confidence === 'low' ? 'danger' : 'warning'}">${data.confidence || 'medium'}</span>
                                </div>
                                ${data.factors?.length ? '<div class="mt-2"><small class="text-muted">' + data.factors.join(', ') + '</small></div>' : ''}
                            </div>`;
                    } else {
                        result.innerHTML = '<p class="text-danger small">Forecast unavailable</p>';
                    }
                }).catch(() => { result.innerHTML = '<p class="text-danger small">Error loading forecast</p>'; });
        });
    }

    const periodSelect = document.getElementById('period-select');
    if (periodSelect) periodSelect.addEventListener('change', loadSalesTrends);

    const refreshBtn = document.getElementById('refresh-analytics');
    if (refreshBtn) refreshBtn.addEventListener('click', () => { loadSalesTrends(); loadBusinessInsights(); loadSegments(); });

    loadSalesTrends();
    loadBusinessInsights();
    loadSegments();
})();
