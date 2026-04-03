<?php
/**
 * pages/ai/sourcing.php — AI Product Sourcing Assistant
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();
require_once __DIR__ . '/../../includes/ai-engine.php';
require_once __DIR__ . '/../../includes/ai-search.php';

$pageTitle = 'AI Product Sourcing — GlobexSky';
$currentUser = getCurrentUser();
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3 fw-bold mb-1"><i class="bi bi-search-heart text-primary me-2"></i>AI Product Sourcing</h1>
            <p class="text-muted">Describe what you're looking for in plain language — GlobexBot will find matching products and suppliers.</p>
        </div>
    </div>

    <!-- Search Form -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <form id="sourcingForm">
                <div class="mb-3">
                    <label class="form-label fw-semibold">What are you looking for?</label>
                    <textarea id="sourcingQuery" class="form-control" rows="3"
                              placeholder="e.g. Waterproof outdoor Bluetooth speaker with at least 20h battery, budget under $30 per unit, MOQ 500 pcs"
                              maxlength="500"></textarea>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-sm-4">
                        <label class="form-label small fw-semibold">Budget per unit (USD)</label>
                        <input type="number" id="maxBudget" class="form-control" placeholder="e.g. 25.00" min="0" step="0.01">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label small fw-semibold">Minimum Order Qty</label>
                        <input type="number" id="moqMin" class="form-control" placeholder="e.g. 100" min="1">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label small fw-semibold">Source Country</label>
                        <select id="sourceCountry" class="form-select">
                            <option value="">Any country</option>
                            <option value="China" selected>China</option>
                            <option value="India">India</option>
                            <option value="Vietnam">Vietnam</option>
                            <option value="Bangladesh">Bangladesh</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary px-4" id="sourcingBtn">
                    <i class="bi bi-robot me-2"></i>Find with AI
                </button>
            </form>
        </div>
    </div>

    <!-- Loading spinner -->
    <div id="sourcingLoading" class="text-center py-5 d-none">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="mt-3 text-muted">GlobexBot is analysing your request…</p>
    </div>

    <!-- AI interpretation -->
    <div id="aiInterpretation" class="alert alert-info d-none mb-3">
        <i class="bi bi-robot me-2"></i>
        <strong>AI interpreted:</strong> <span id="aiInterpretedText"></span>
    </div>

    <!-- Results -->
    <div id="sourcingResults" class="d-none">
        <h5 class="fw-semibold mb-3">Matching Products <span class="badge bg-primary ms-2" id="resultCount">0</span></h5>
        <div class="row g-3" id="productGrid"></div>
    </div>

    <!-- Empty state -->
    <div id="sourcingEmpty" class="text-center py-5 d-none">
        <i class="bi bi-search display-4 text-muted"></i>
        <p class="mt-3 text-muted">No products found matching your description. Try a different query or browse our <a href="<?= APP_URL ?>/pages/product/index.php">product catalogue</a>.</p>
    </div>
</div>

<script>
document.getElementById('sourcingForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const query   = document.getElementById('sourcingQuery').value.trim();
    const budget  = document.getElementById('maxBudget').value;
    const moq     = document.getElementById('moqMin').value;
    const country = document.getElementById('sourceCountry').value;

    if (!query) { alert('Please describe what you are looking for.'); return; }

    let fullQuery = query;
    if (budget)  fullQuery += ` budget under $${budget} per unit`;
    if (moq)     fullQuery += ` MOQ ${moq} pieces`;
    if (country) fullQuery += ` from ${country}`;

    document.getElementById('sourcingLoading').classList.remove('d-none');
    document.getElementById('sourcingResults').classList.add('d-none');
    document.getElementById('sourcingEmpty').classList.add('d-none');
    document.getElementById('aiInterpretation').classList.add('d-none');

    try {
        const resp = await fetch('/api/ai.php?action=search_enhanced', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ q: fullQuery })
        });
        const data = await resp.json();

        document.getElementById('sourcingLoading').classList.add('d-none');

        if (data.interpreted && data.ai_enhanced) {
            document.getElementById('aiInterpretedText').textContent = data.interpreted;
            document.getElementById('aiInterpretation').classList.remove('d-none');
        }

        const products = data.products || [];
        document.getElementById('resultCount').textContent = products.length;

        if (products.length === 0) {
            document.getElementById('sourcingEmpty').classList.remove('d-none');
            return;
        }

        const grid = document.getElementById('productGrid');
        grid.innerHTML = products.map(p => `
            <div class="col-sm-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm">
                    <img src="${p.image_url || '/assets/img/placeholder.png'}" class="card-img-top" style="height:180px;object-fit:cover" alt="">
                    <div class="card-body">
                        <h6 class="fw-semibold mb-1">${escHtml(p.title)}</h6>
                        <div class="fw-bold text-primary mb-2">$${parseFloat(p.price || 0).toFixed(2)}</div>
                        <a href="/pages/product/detail.php?id=${p.id}" class="btn btn-sm btn-outline-primary me-1">View</a>
                        <a href="/pages/communication/chat.php" class="btn btn-sm btn-success">Contact Supplier</a>
                    </div>
                </div>
            </div>
        `).join('');

        document.getElementById('sourcingResults').classList.remove('d-none');
    } catch (err) {
        document.getElementById('sourcingLoading').classList.add('d-none');
        alert('An error occurred. Please try again.');
    }
});

function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
