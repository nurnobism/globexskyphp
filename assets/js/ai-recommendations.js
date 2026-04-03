/**
 * GlobexSky — AI Recommendations Widget (assets/js/ai-recommendations.js)
 *
 * - AJAX loads recommendations from api/ai.php
 * - Lazy loads product cards
 * - Click tracking
 * - Load More pagination
 */
(function () {
    'use strict';

    const API = '/api/ai.php';

    /* ── Card builder ────────────────────────────────────────── */
    function buildCard(rec) {
        const price = rec.price
            ? '$' + parseFloat(rec.price).toFixed(2)
            : '';
        return '<div class="col-6 col-md-4 col-lg-3 rec-item">'
             + '<div class="card border-0 shadow-sm h-100">'
             + '<a href="/pages/product/detail.php?id=' + rec.product_id + '"'
             + '   class="rec-link" data-rec-id="' + (rec.id || '') + '">'
             + '<img src="' + escAttr(rec.image_url || '/assets/img/placeholder.png') + '"'
             + '     class="card-img-top" style="height:160px;object-fit:cover"'
             + '     loading="lazy" alt="">'
             + '</a>'
             + '<div class="card-body p-2">'
             + '<div class="small fw-semibold text-truncate mb-1">' + escHtml(rec.title || '') + '</div>'
             + (price ? '<div class="fw-bold text-primary small">' + price + '</div>' : '')
             + (rec.reason ? '<div class="badge bg-light text-muted border mt-1" style="font-size:.68rem;white-space:normal">'
                           + escHtml(rec.reason) + '</div>' : '')
             + '</div>'
             + '</div>'
             + '</div>';
    }

    /* ── Load recommendations into a container ───────────────── */
    function loadRecommendations(containerId, type, limit, append) {
        const container = document.getElementById(containerId);
        if (!container) return;

        if (!append) {
            container.innerHTML = '<div class="col-12 text-center py-3">'
                + '<div class="spinner-border spinner-border-sm text-primary" role="status"></div>'
                + '</div>';
        }

        fetch(API + '?action=recommendations&type=' + encodeURIComponent(type) + '&limit=' + limit)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success || !data.recommendations.length) {
                    if (!append) {
                        container.innerHTML = '<div class="col-12 text-muted small">No recommendations available yet.</div>';
                    }
                    return;
                }
                if (!append) container.innerHTML = '';
                data.recommendations.forEach(function (rec) {
                    container.insertAdjacentHTML('beforeend', buildCard(rec));
                });
                attachClickTracking(container);
            })
            .catch(function () {
                if (!append) container.innerHTML = '';
            });
    }

    /* ── Track recommendation clicks ────────────────────────── */
    function attachClickTracking(root) {
        root.querySelectorAll('.rec-link[data-rec-id]').forEach(function (link) {
            link.addEventListener('click', function () {
                const recId = this.dataset.recId;
                if (!recId) return;
                navigator.sendBeacon(
                    API + '?action=recommendation_click',
                    new URLSearchParams({ recommendation_id: recId })
                );
            });
        });
    }

    /* ── Refresh button ──────────────────────────────────────── */
    const refreshBtn = document.getElementById('refreshRecsBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
            ['recPersonalized', 'recTrending', 'recComplementary'].forEach(function (id) {
                const type = id.replace('rec', '').toLowerCase();
                loadRecommendations(id, type, 8, false);
            });
        });
    }

    /* ── Helpers ─────────────────────────────────────────────── */
    function escHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function escAttr(s) {
        return s.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    /* ── Init on page ready ──────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', function () {
        var sections = [
            { id: 'recPersonalized',  type: 'personalized'  },
            { id: 'recTrending',      type: 'trending'       },
            { id: 'recSimilar',       type: 'similar'        },
            { id: 'recComplementary', type: 'complementary'  },
        ];
        sections.forEach(function (s) {
            if (document.getElementById(s.id)) {
                loadRecommendations(s.id, s.type, 8, false);
            }
        });
    });

    // Expose for inline use
    window.GsRecs = { load: loadRecommendations };
}());
