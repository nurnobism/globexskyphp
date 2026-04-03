/**
 * assets/js/ai-fraud.js — Fraud Detection UI (Phase 8)
 */
(function() {
    'use strict';

    let currentPage = 1;
    let currentFilters = {};
    let selectedAlertId = null;

    function escapeHtml(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

    function riskBadge(level) {
        const colors = { critical: 'danger', high: 'warning', medium: 'info', low: 'success' };
        return `<span class="badge bg-${colors[level] || 'secondary'}">${level}</span>`;
    }

    function actionBadge(action) {
        const colors = { none: 'secondary', flag: 'warning', hold: 'info', block: 'danger', notify_admin: 'dark' };
        return `<span class="badge bg-${colors[action] || 'secondary'}">${action}</span>`;
    }

    function loadStats() {
        fetch('/api/ai/fraud-detection.php?action=stats')
            .then(r => r.json())
            .then(d => {
                if (!d.success) return;
                const today = d.data.today || {};
                document.getElementById('count-critical') && (document.getElementById('count-critical').textContent = today.critical || 0);
                document.getElementById('count-high') && (document.getElementById('count-high').textContent = today.high || 0);
                document.getElementById('count-medium') && (document.getElementById('count-medium').textContent = today.medium || 0);
                document.getElementById('false-positive-rate') && (document.getElementById('false-positive-rate').textContent = (d.data.false_positive_rate || 0) + '%');
            }).catch(() => {});
    }

    function loadAlerts(page, filters) {
        const tbody = document.getElementById('alerts-table-body');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3"><div class="spinner-border text-danger"></div></td></tr>';

        const params = new URLSearchParams({ action: 'alerts', page: page, ...filters });
        fetch('/api/ai/fraud-detection.php?' + params.toString())
            .then(r => r.json())
            .then(d => {
                if (!d.success) { tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Error loading alerts</td></tr>'; return; }
                const alerts = d.data.data || [];
                document.getElementById('alerts-count') && (document.getElementById('alerts-count').textContent = d.data.total || 0);
                if (!alerts.length) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><i class="bi bi-shield-check fs-2 d-block mb-2"></i>No fraud alerts found</td></tr>';
                    return;
                }
                tbody.innerHTML = alerts.map(a => `
                    <tr>
                        <td><span class="badge bg-light text-dark">${escapeHtml(a.entity_type)}</span> #${escapeHtml(String(a.entity_id))}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height:8px;min-width:60px;">
                                    <div class="progress-bar bg-${a.risk_score >= 75 ? 'danger' : a.risk_score >= 50 ? 'warning' : 'success'}"
                                         style="width:${a.risk_score}%"></div>
                                </div>
                                <small>${parseFloat(a.risk_score).toFixed(0)}</small>
                            </div>
                        </td>
                        <td>${riskBadge(a.risk_level)}</td>
                        <td><small class="text-muted">${a.factors ? Object.values(JSON.parse(a.factors || '[]')).slice(0,2).join(', ') : '—'}</small></td>
                        <td>${actionBadge(a.action_taken)}</td>
                        <td><small>${new Date(a.created_at).toLocaleDateString()}</small></td>
                        <td><button class="btn btn-sm btn-outline-primary review-btn" data-alert-id="${a.id}" data-bs-toggle="modal" data-bs-target="#alertDetailModal">Review</button></td>
                    </tr>`).join('');

                document.querySelectorAll('.review-btn').forEach(btn => {
                    btn.addEventListener('click', () => openAlertDetail(btn.dataset.alertId, alerts));
                });
            }).catch(() => { tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Could not load alerts</td></tr>'; });
    }

    function openAlertDetail(alertId, alerts) {
        selectedAlertId = alertId;
        const alert = alerts.find(a => String(a.id) === String(alertId));
        if (!alert) return;
        const body = document.getElementById('modal-body-content');
        if (!body) return;
        let factors = [];
        try { factors = JSON.parse(alert.factors || '[]'); } catch (e) {}
        body.innerHTML = `
            <div class="row g-3 mb-3">
                <div class="col-6"><strong>Entity:</strong> ${escapeHtml(alert.entity_type)} #${escapeHtml(String(alert.entity_id))}</div>
                <div class="col-6"><strong>Risk Score:</strong> ${parseFloat(alert.risk_score).toFixed(0)}/100</div>
                <div class="col-6"><strong>Level:</strong> ${riskBadge(alert.risk_level)}</div>
                <div class="col-6"><strong>Action:</strong> ${actionBadge(alert.action_taken)}</div>
            </div>
            <div class="mb-3">
                <strong>AI Reasoning:</strong>
                <p class="mt-1 p-3 bg-light rounded">${escapeHtml(alert.ai_reasoning || 'No reasoning provided.')}</p>
            </div>
            ${factors.length ? `<div><strong>Risk Factors:</strong><ul class="mt-1">${factors.map(f => `<li>${escapeHtml(f)}</li>`).join('')}</ul></div>` : ''}
            <small class="text-muted">Detected: ${new Date(alert.created_at).toLocaleString()}</small>`;
    }

    // Polling for real-time updates
    setInterval(() => { loadStats(); loadAlerts(currentPage, currentFilters); }, 30000);

    // Apply filters
    const applyBtn = document.getElementById('apply-filters-btn');
    if (applyBtn) {
        applyBtn.addEventListener('click', () => {
            currentFilters = {
                entity_type: document.getElementById('filter-entity')?.value || '',
                risk_level:  document.getElementById('filter-risk')?.value || '',
                action_taken:document.getElementById('filter-action')?.value || '',
                date_from:   document.getElementById('filter-date-from')?.value || '',
                date_to:     document.getElementById('filter-date-to')?.value || '',
            };
            currentPage = 1;
            loadAlerts(currentPage, currentFilters);
        });
    }

    // Modal action buttons
    ['modal-false-positive-btn', 'modal-flag-btn', 'modal-block-btn'].forEach(id => {
        const btn = document.getElementById(id);
        if (!btn) return;
        btn.addEventListener('click', () => {
            if (!selectedAlertId) return;
            const actionMap = { 'modal-false-positive-btn': 'false_positive', 'modal-flag-btn': 'review', 'modal-block-btn': 'review' };
            const actionTakenMap = { 'modal-flag-btn': 'flag', 'modal-block-btn': 'block' };
            const endpoint = id === 'modal-false-positive-btn' ? 'false_positive' : 'review';
            const payload = { action: endpoint, fraud_log_id: parseInt(selectedAlertId, 10) };
            if (actionTakenMap[id]) payload.action_taken = actionTakenMap[id];
            fetch('/api/ai/fraud-detection.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            }).then(r => r.json()).then(d => {
                if (d.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('alertDetailModal'));
                    if (modal) modal.hide();
                    loadAlerts(currentPage, currentFilters);
                    loadStats();
                }
            }).catch(() => {});
        });
    });

    loadStats();
    loadAlerts(1, {});
})();
