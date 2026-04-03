<?php
/**
 * pages/ai/search.php — AI-Powered Search (Phase 8)
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4">
        <i class="bi bi-search fs-2 text-info me-3"></i>
        <div>
            <h1 class="h3 mb-0">AI-Powered Search</h1>
            <p class="text-muted mb-0">Search in natural language — AI understands what you mean</p>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="card border-0 shadow mb-4">
        <div class="card-body p-3">
            <div class="input-group input-group-lg">
                <input type="text" class="form-control border-end-0" id="search-input" placeholder='Try: "red wireless headphones under $100" or "organic cotton t-shirts bulk order"'>
                <span class="input-group-text bg-white">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="ai-toggle" checked>
                        <label class="form-check-label small" for="ai-toggle">AI</label>
                    </div>
                </span>
                <button class="btn btn-primary" id="search-btn"><i class="bi bi-search me-1"></i>Search</button>
            </div>
            <div class="mt-2">
                <small class="text-muted me-2">Try:</small>
                <?php
                $examples = ['best laptops for gaming', 'organic food suppliers', 'bulk electronics under $50', 'certified medical equipment'];
                foreach ($examples as $ex): ?>
                <button class="btn btn-outline-secondary btn-sm me-1 mb-1 example-query"><?= e($ex) ?></button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- AI Analysis Result -->
    <div id="ai-analysis" class="d-none mb-4">
        <div class="card border-0 shadow-sm border-start border-primary border-4">
            <div class="card-body">
                <h6 class="text-primary"><i class="bi bi-robot me-2"></i>AI Understanding</h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <small class="text-muted">Detected Intent</small>
                        <div id="ai-intent" class="fw-semibold"></div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Enhanced Query</small>
                        <div id="ai-enhanced-query"></div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">AI-Suggested Filters</small>
                        <div id="ai-filters"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AI loading animation -->
    <div id="ai-thinking" class="d-none text-center py-3 text-muted">
        <div class="spinner-border spinner-border-sm me-2"></div>
        AI is understanding your search...
    </div>

    <!-- Results -->
    <div id="search-results" class="row g-3"></div>

    <!-- Search History -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-transparent border-0 pt-3">
            <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent AI Searches</h5>
        </div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush" id="search-history-list">
                <li class="list-group-item text-muted text-center py-3">Loading history...</li>
            </ul>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('search-input');
    const searchBtn   = document.getElementById('search-btn');
    const aiToggle    = document.getElementById('ai-toggle');

    // Load search history
    fetch('/api/ai/search.php?action=history')
        .then(r => r.json())
        .then(d => {
            const list = document.getElementById('search-history-list');
            if (d.success && d.data.length) {
                list.innerHTML = d.data.map(h => `
                    <li class="list-group-item d-flex justify-content-between align-items-center" style="cursor:pointer" onclick="document.getElementById('search-input').value='${h.original_query.replace(/'/g,"\\'")}'; document.getElementById('search-btn').click();">
                        <div>
                            <i class="bi bi-search me-2 text-muted"></i>${h.original_query}
                            ${h.intent ? '<span class="badge bg-light text-dark ms-2">' + h.intent + '</span>' : ''}
                        </div>
                        <small class="text-muted">${new Date(h.created_at).toLocaleDateString()}</small>
                    </li>`).join('');
            } else {
                list.innerHTML = '<li class="list-group-item text-muted text-center py-3">No search history yet</li>';
            }
        }).catch(() => {});

    // Example queries
    document.querySelectorAll('.example-query').forEach(btn => {
        btn.addEventListener('click', () => { searchInput.value = btn.textContent; searchBtn.click(); });
    });

    searchBtn.addEventListener('click', performSearch);
    searchInput.addEventListener('keydown', e => { if (e.key === 'Enter') performSearch(); });

    async function performSearch() {
        const query = searchInput.value.trim();
        if (!query) return;
        const useAI = aiToggle.checked;
        const resultsDiv = document.getElementById('search-results');
        resultsDiv.innerHTML = '<div class="col-12 text-center py-4"><div class="spinner-border text-primary"></div></div>';

        if (useAI) {
            document.getElementById('ai-thinking').classList.remove('d-none');
            document.getElementById('ai-analysis').classList.add('d-none');
            try {
                const res = await fetch('/api/ai/search.php?action=enhance&q=' + encodeURIComponent(query));
                const data = await res.json();
                document.getElementById('ai-thinking').classList.add('d-none');
                if (data.success) {
                    const d = data.data;
                    document.getElementById('ai-intent').textContent = d.intent || 'Product search';
                    document.getElementById('ai-enhanced-query').textContent = d.enhanced_query || query;
                    const filters = d.filters || {};
                    const filterParts = [];
                    if (filters.category) filterParts.push('<span class="badge bg-primary me-1">' + filters.category + '</span>');
                    if (filters.min_price) filterParts.push('<span class="badge bg-success me-1">Min: $' + filters.min_price + '</span>');
                    if (filters.max_price) filterParts.push('<span class="badge bg-warning text-dark me-1">Max: $' + filters.max_price + '</span>');
                    document.getElementById('ai-filters').innerHTML = filterParts.join('') || '<span class="text-muted">None</span>';
                    document.getElementById('ai-analysis').classList.remove('d-none');
                }
            } catch (e) {
                document.getElementById('ai-thinking').classList.add('d-none');
            }
        }

        // Basic product search
        try {
            const searchRes = await fetch('/api/products.php?action=search&q=' + encodeURIComponent(query) + '&limit=12');
            const searchData = await searchRes.json();
            if (searchData.success && searchData.data?.length) {
                resultsDiv.innerHTML = searchData.data.map(p => `
                    <div class="col-sm-6 col-md-4 col-lg-3">
                        <div class="card border-0 shadow-sm h-100">
                            <img src="${p.image || '/assets/img/no-image.png'}" class="card-img-top" style="height:180px;object-fit:cover;" alt="">
                            <div class="card-body p-3">
                                <h6 class="card-title mb-1">${p.name}</h6>
                                <div class="text-primary fw-bold">$${parseFloat(p.price || 0).toFixed(2)}</div>
                            </div>
                        </div>
                    </div>`).join('');
            } else {
                resultsDiv.innerHTML = '<div class="col-12 text-center py-4 text-muted"><i class="bi bi-search fs-2 d-block mb-2"></i>No products found. Try different keywords.</div>';
            }
        } catch (e) {
            resultsDiv.innerHTML = '<div class="col-12 text-center py-4 text-muted">Search unavailable. Please try again.</div>';
        }
    }
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
