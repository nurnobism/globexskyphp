/**
 * assets/js/ai-recommendations.js — AI Recommendations UI (Phase 8)
 */
(function() {
    'use strict';

    function renderProductCard(p, recId) {
        const score = p.score ? Math.round(p.score * 100) : null;
        return `
            <div class="col-sm-6 col-md-4 col-lg-3">
                <div class="card border-0 shadow-sm h-100 product-card" data-rec-id="${recId || ''}">
                    <div class="position-relative">
                        <img src="${p.image || '/assets/img/no-image.png'}" class="card-img-top" style="height:180px;object-fit:cover;" alt="${escapeHtml(p.name || '')}">
                        ${score ? `<span class="position-absolute top-0 end-0 m-2 badge bg-success">${score}% match</span>` : ''}
                    </div>
                    <div class="card-body p-3">
                        <h6 class="card-title mb-1" style="font-size:.85rem;">${escapeHtml(p.name || 'Product')}</h6>
                        <div class="text-primary fw-bold mb-1">$${parseFloat(p.price || 0).toFixed(2)}</div>
                        ${p.rating ? `<div class="text-warning small">${'★'.repeat(Math.round(p.rating))} <span class="text-muted">(${p.review_count || 0})</span></div>` : ''}
                        ${p.reason ? `<small class="text-muted d-block mt-1" title="${escapeHtml(p.reason)}">ℹ ${escapeHtml(p.reason.substring(0, 60))}...</small>` : ''}
                    </div>
                    <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">
                        <a href="/pages/product/detail.php?id=${p.recommended_product_id || p.id || ''}" class="btn btn-sm btn-outline-primary w-100" onclick="trackRecClick(${recId || 0})">View Product</a>
                    </div>
                </div>
            </div>`;
    }

    function escapeHtml(text) {
        const d = document.createElement('div'); d.textContent = text; return d.innerHTML;
    }

    window.trackRecClick = function(recId) {
        if (!recId) return;
        fetch('/api/ai/recommendations.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'click', recommendation_id: recId }),
        }).catch(() => {});
    };

    function loadPersonalized() {
        const grid = document.getElementById('personalized-grid');
        if (!grid) return;
        fetch('/api/ai/recommendations.php?action=personalized')
            .then(r => r.json())
            .then(d => {
                if (d.success && d.data.length) {
                    grid.innerHTML = d.data.map(p => renderProductCard(p, p.id)).join('');
                    document.getElementById('total-recs') && (document.getElementById('total-recs').textContent = d.data.length);
                } else {
                    grid.innerHTML = '<div class="col-12 text-center py-4 text-muted"><i class="bi bi-stars fs-2 d-block mb-2"></i>Order products to get personalized recommendations</div>';
                }
            }).catch(() => {
                grid.innerHTML = '<div class="col-12 text-center py-4 text-muted">Could not load recommendations</div>';
            });
    }

    function loadTrending() {
        const grid = document.getElementById('trending-grid');
        if (!grid) return;
        fetch('/api/ai/recommendations.php?action=trending')
            .then(r => r.json())
            .then(d => {
                if (d.success && d.data.length) {
                    grid.innerHTML = d.data.map(p => renderProductCard(p, null)).join('');
                } else {
                    grid.innerHTML = '<div class="col-12 text-center py-4 text-muted">No trending products available</div>';
                }
            }).catch(() => {
                grid.innerHTML = '<div class="col-12 text-center py-4 text-muted">Could not load trending products</div>';
            });
    }

    const refreshBtn = document.getElementById('refresh-btn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => {
            refreshBtn.disabled = true;
            document.getElementById('personalized-grid').innerHTML = '<div class="col-12 text-center py-4"><div class="spinner-border text-primary"></div></div>';
            fetch('/api/ai/recommendations.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'refresh' }),
            }).then(r => r.json()).then(() => { loadPersonalized(); loadTrending(); refreshBtn.disabled = false; }).catch(() => { refreshBtn.disabled = false; });
        });
    }

    loadPersonalized();
    loadTrending();
})();
