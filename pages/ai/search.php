<?php
require_once __DIR__ . '/../../includes/middleware.php';
$pageTitle = 'AI Smart Search';
include __DIR__ . '/../../includes/header.php';
?>

<style>
    :root { --brand-orange: #FF6B35; --brand-dark: #1B2A4A; }
    .search-hero { background: linear-gradient(135deg, #1B2A4A 0%, #243660 100%); }
    .search-box  { border-radius: 50px; border: 3px solid transparent; background: #fff;
                   transition: border-color .25s, box-shadow .25s; }
    .search-box:focus-within { border-color: var(--brand-orange); box-shadow: 0 0 0 4px rgba(255,107,53,.15); }
    .search-box input { border: none; background: transparent; padding: .75rem 1.25rem; font-size: 1.05rem; }
    .search-box input:focus { box-shadow: none; outline: none; }
    .search-mode-btn { border-radius: 50%; width: 44px; height: 44px; padding: 0;
                       display:inline-flex; align-items:center; justify-content:center;
                       border: 2px solid transparent; transition: all .2s; }
    .search-mode-btn.active, .search-mode-btn:hover { border-color: var(--brand-orange); color: var(--brand-orange); }
    .search-mode-btn.recording { background: #dc3545; color: #fff; border-color: #dc3545; animation: pulse 1s infinite; }
    @keyframes pulse { 0%,100%{box-shadow:0 0 0 0 rgba(220,53,69,.4)} 50%{box-shadow:0 0 0 8px rgba(220,53,69,0)} }
    .filter-chip { border-radius: 50px; padding: .3rem .9rem; font-size: .82rem; border: 1.5px solid #dee2e6;
                   cursor: pointer; transition: all .2s; }
    .filter-chip:hover, .filter-chip.active { background: var(--brand-orange); color: #fff; border-color: var(--brand-orange); }
    .result-card { border-radius: 14px; border: 1.5px solid #f0f0f0; transition: transform .2s, box-shadow .2s; }
    .result-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,.1); }
    .relevance-bar { height: 4px; border-radius: 2px; background: #f0f0f0; }
    .relevance-fill { height: 100%; border-radius: 2px; background: linear-gradient(90deg, var(--brand-orange), #ffbb45); }
    .interpretation-badge { background: rgba(255,107,53,.1); border: 1px solid rgba(255,107,53,.3);
                            border-radius: 8px; padding: .4rem .8rem; font-size: .85rem; }
    #qrReader { border-radius: 12px; overflow: hidden; }
</style>

<!-- Search Hero -->
<div class="search-hero text-white py-5">
    <div class="container py-2">
        <h2 class="fw-bold text-center mb-1"><i class="bi bi-cpu-fill text-warning me-2"></i>AI Smart Search</h2>
        <p class="text-center text-white-75 mb-4">Search in plain English · Speak · Scan a barcode · Upload an image</p>

        <!-- Main Search Form -->
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="search-box d-flex align-items-center pe-2 py-1">
                    <input type="text" id="searchInput" class="form-control flex-grow-1 rounded-pill"
                           placeholder='Try "wholesale electronics under $500" or scan a barcode…'
                           autocomplete="off">
                    <!-- Voice -->
                    <button class="search-mode-btn btn btn-light ms-1" id="voiceBtn" title="Voice Search">
                        <i class="bi bi-mic-fill"></i>
                    </button>
                    <!-- Image -->
                    <label class="search-mode-btn btn btn-light ms-1 mb-0" title="Image Search" id="imageBtn">
                        <i class="bi bi-camera-fill"></i>
                        <input type="file" id="imageInput" accept="image/*" class="d-none">
                    </label>
                    <!-- Barcode -->
                    <button class="search-mode-btn btn btn-light ms-1" id="barcodeBtn" title="Barcode / QR Scanner">
                        <i class="bi bi-upc-scan"></i>
                    </button>
                    <!-- Search -->
                    <button class="btn text-white px-4 ms-2 rounded-pill" id="searchBtn"
                            style="background:var(--brand-orange); min-width:90px;">
                        <i class="bi bi-search"></i> Search
                    </button>
                </div>

                <!-- Filter chips -->
                <div class="d-flex flex-wrap gap-2 mt-3 justify-content-center">
                    <span class="filter-chip" data-q="electronics">Electronics</span>
                    <span class="filter-chip" data-q="clothing wholesale">Clothing</span>
                    <span class="filter-chip" data-q="industrial machinery">Machinery</span>
                    <span class="filter-chip" data-q="food packaging">Food & Packaging</span>
                    <span class="filter-chip" data-q="under $100">Under $100</span>
                    <span class="filter-chip" data-q="certified ISO products">ISO Certified</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container py-4">

    <!-- Barcode scanner panel (hidden by default) -->
    <div id="scannerPanel" class="d-none mb-4">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center rounded-top-4">
                <span><i class="bi bi-upc-scan me-2"></i>Barcode / QR Scanner</span>
                <button class="btn btn-sm btn-outline-light" id="closeScanner">
                    <i class="bi bi-x-lg"></i> Close
                </button>
            </div>
            <div class="card-body p-3">
                <div id="qrReader" style="max-width:500px;margin:0 auto;"></div>
            </div>
        </div>
    </div>

    <!-- Status / interpretation -->
    <div id="searchStatus" class="d-none mb-3">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="interpretation-badge">
                <i class="bi bi-magic text-warning me-1"></i>
                AI interpreted: <strong id="interpretationText"></strong>
            </div>
            <small class="text-muted" id="resultCount"></small>
        </div>
    </div>

    <!-- Loading -->
    <div id="loadingSpinner" class="d-none text-center py-5">
        <div class="spinner-border text-warning" style="width:3rem;height:3rem;" role="status"></div>
        <p class="text-muted mt-3">Searching with AI…</p>
    </div>

    <!-- Results Grid -->
    <div id="resultsGrid" class="row g-3"></div>

    <!-- Pagination -->
    <div id="pagination" class="d-flex justify-content-center gap-2 mt-4"></div>

    <!-- Empty state -->
    <div id="emptyState" class="d-none text-center py-5">
        <i class="bi bi-search text-muted" style="font-size:4rem;"></i>
        <h5 class="text-muted mt-3">No products found</h5>
        <p class="text-muted small">Try a different query or remove some filters.</p>
    </div>

    <!-- Initial prompt -->
    <div id="initialPrompt" class="text-center py-5">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <i class="bi bi-search-heart text-warning" style="font-size:3.5rem;"></i>
                <h4 class="mt-3 fw-bold">Start your AI-powered search</h4>
                <p class="text-muted">
                    Type naturally, like <em>"stainless steel bolts, minimum order 1000, under $0.50 each"</em>
                    — our AI will extract the right filters automatically.
                </p>
            </div>
        </div>
        <!-- Example queries -->
        <div class="row justify-content-center g-3 mt-2">
            <?php
            $examples = [
                ['Organic cotton t-shirts bulk order', 'bi-shop'],
                ['Industrial LED lighting 50W+', 'bi-lightbulb'],
                ['Bluetooth headphones under $30', 'bi-headphones'],
                ['Food grade packaging boxes', 'bi-box-seam'],
            ];
            foreach ($examples as [$text, $icon]):
            ?>
            <div class="col-sm-6 col-lg-3">
                <div class="card border rounded-3 p-3 example-query" role="button" data-q="<?= e($text) ?>">
                    <i class="bi <?= $icon ?> text-warning fs-4 mb-2"></i>
                    <small class="text-muted"><?= e($text) ?></small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<!-- html5-qrcode CDN -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
const SEARCH_URL = '<?= APP_URL ?>/api/ai/search.php';
let currentPage = 1, html5QrCode = null, isRecording = false;
let recognition = null;

// ---- Utility ----
function starHtml(rating) {
    let html = '';
    for (let i = 1; i <= 5; i++) {
        html += `<i class="bi bi-star${i <= Math.round(rating) ? '-fill' : ''} text-warning" style="font-size:.7rem;"></i>`;
    }
    return html;
}

function productCard(p) {
    const rel = Math.min(100, Math.round(p.relevance_score));
    const img = p.image && p.image !== '/assets/img/no-image.png'
        ? p.image : '<?= APP_URL ?>/assets/img/no-image.png';
    return `
    <div class="col-sm-6 col-md-4 col-lg-3">
        <div class="card result-card h-100">
            <a href="<?= APP_URL ?>/pages/product/detail.php?id=${p.id}" class="text-decoration-none">
                <img src="${img}" class="card-img-top" alt="${p.name}"
                     style="height:180px;object-fit:cover;border-radius:13px 13px 0 0;"
                     onerror="this.src='<?= APP_URL ?>/assets/img/no-image.png'">
            </a>
            <div class="card-body p-3 d-flex flex-column">
                <small class="text-muted">${p.category || ''}</small>
                <a href="<?= APP_URL ?>/pages/product/detail.php?id=${p.id}"
                   class="text-decoration-none text-dark fw-semibold small lh-sm mt-1">
                    ${p.name}
                </a>
                <div class="mt-1 mb-2">${starHtml(p.rating)} <small class="text-muted">(${p.review_count})</small></div>
                <div class="fw-bold text-primary mt-auto" style="color:var(--brand-orange)!important;">
                    $${parseFloat(p.price).toFixed(2)}
                </div>
                ${p.min_order_qty > 1 ? `<small class="text-muted">MOQ: ${p.min_order_qty}</small>` : ''}
                <div class="mt-2">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>Relevance</span><span>${rel}%</span>
                    </div>
                    <div class="relevance-bar">
                        <div class="relevance-fill" style="width:${rel}%"></div>
                    </div>
                </div>
                <a href="<?= APP_URL ?>/pages/product/detail.php?id=${p.id}"
                   class="btn btn-sm btn-outline-primary mt-3 rounded-pill">View Product</a>
            </div>
        </div>
    </div>`;
}

// ---- Search ----
async function doSearch(q, type = 'text', page = 1) {
    if (!q.trim()) return;
    currentPage = page;
    document.getElementById('initialPrompt').classList.add('d-none');
    document.getElementById('emptyState').classList.add('d-none');
    document.getElementById('searchStatus').classList.add('d-none');
    document.getElementById('loadingSpinner').classList.remove('d-none');
    document.getElementById('resultsGrid').innerHTML = '';
    document.getElementById('pagination').innerHTML = '';

    try {
        const res  = await fetch(`${SEARCH_URL}?q=${encodeURIComponent(q)}&type=${type}&page=${page}`);
        const data = await res.json();
        document.getElementById('loadingSpinner').classList.add('d-none');

        if (!data.success) throw new Error(data.message || 'Search failed');

        if (data.results.length === 0) {
            document.getElementById('emptyState').classList.remove('d-none');
            return;
        }

        document.getElementById('searchStatus').classList.remove('d-none');
        document.getElementById('interpretationText').textContent = data.query_interpretation || q;
        document.getElementById('resultCount').textContent = `${data.total.toLocaleString()} result(s) found`;

        document.getElementById('resultsGrid').innerHTML = data.results.map(productCard).join('');

        // Pagination
        if (data.pages > 1) {
            let pHtml = '';
            for (let i = 1; i <= Math.min(data.pages, 10); i++) {
                pHtml += `<button class="btn btn-sm ${i === page ? 'btn-warning' : 'btn-outline-secondary'} rounded-pill"
                          onclick="doSearch(document.getElementById('searchInput').value,'${type}',${i})">${i}</button>`;
            }
            document.getElementById('pagination').innerHTML = pHtml;
        }
    } catch (err) {
        document.getElementById('loadingSpinner').classList.add('d-none');
        document.getElementById('resultsGrid').innerHTML =
            `<div class="col-12 text-center text-danger py-4"><i class="bi bi-exclamation-triangle me-2"></i>${err.message}</div>`;
    }
}

// ---- Event listeners ----
document.getElementById('searchBtn').addEventListener('click', () => {
    doSearch(document.getElementById('searchInput').value);
});
document.getElementById('searchInput').addEventListener('keydown', e => {
    if (e.key === 'Enter') doSearch(e.target.value);
});

document.querySelectorAll('.filter-chip').forEach(el => {
    el.addEventListener('click', () => {
        document.getElementById('searchInput').value = el.dataset.q;
        doSearch(el.dataset.q);
    });
});
document.querySelectorAll('.example-query').forEach(el => {
    el.addEventListener('click', () => {
        document.getElementById('searchInput').value = el.dataset.q;
        doSearch(el.dataset.q);
    });
});

// ---- Voice Search ----
document.getElementById('voiceBtn').addEventListener('click', () => {
    if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
        alert('Voice search is not supported in this browser. Please use Chrome or Edge.');
        return;
    }
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (isRecording) {
        recognition && recognition.stop();
        return;
    }
    recognition = new SpeechRecognition();
    recognition.lang       = 'en-US';
    recognition.continuous = false;
    recognition.onstart  = () => {
        isRecording = true;
        document.getElementById('voiceBtn').classList.add('recording');
    };
    recognition.onresult = e => {
        const transcript = e.results[0][0].transcript;
        document.getElementById('searchInput').value = transcript;
        doSearch(transcript, 'voice');
    };
    recognition.onend = () => {
        isRecording = false;
        document.getElementById('voiceBtn').classList.remove('recording');
    };
    recognition.onerror = () => {
        isRecording = false;
        document.getElementById('voiceBtn').classList.remove('recording');
    };
    recognition.start();
});

// ---- Image Search ----
document.getElementById('imageInput').addEventListener('change', async function () {
    const file = this.files[0];
    if (!file) return;
    // Use filename as search hint
    const name = file.name.replace(/\.[^.]+$/, '').replace(/[-_]/g, ' ');
    document.getElementById('searchInput').value = name;
    doSearch(name, 'image');
    this.value = '';
});

// ---- Barcode Scanner ----
document.getElementById('barcodeBtn').addEventListener('click', () => {
    const panel = document.getElementById('scannerPanel');
    panel.classList.toggle('d-none');
    if (!panel.classList.contains('d-none') && !html5QrCode) {
        html5QrCode = new Html5Qrcode('qrReader');
        html5QrCode.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 280, height: 180 } },
            decodedText => {
                document.getElementById('searchInput').value = decodedText;
                doSearch(decodedText, 'barcode');
                html5QrCode.stop();
                panel.classList.add('d-none');
                html5QrCode = null;
            }
        ).catch(() => {});
    } else if (panel.classList.contains('d-none') && html5QrCode) {
        html5QrCode.stop().catch(() => {});
        html5QrCode = null;
    }
});

document.getElementById('closeScanner').addEventListener('click', () => {
    document.getElementById('scannerPanel').classList.add('d-none');
    if (html5QrCode) { html5QrCode.stop().catch(() => {}); html5QrCode = null; }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
