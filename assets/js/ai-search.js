/**
 * GlobexSky — AI Search (Voice, Barcode, Image, Suggestions, History)
 */
(function () {
  'use strict';

  const SEARCH_API   = '/api/products.php?action=search&q=';
  const VISION_API   = '/api/ai/visual-search.php';
  const HISTORY_KEY  = 'gs_search_history';
  const MAX_HISTORY  = 10;

  /* ── History ────────────────────────────────────────────────── */
  function getHistory() { return JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]'); }
  function saveHistory(q) {
    if (!q.trim()) return;
    let h = getHistory().filter(i => i !== q);
    h.unshift(q);
    localStorage.setItem(HISTORY_KEY, JSON.stringify(h.slice(0, MAX_HISTORY)));
  }

  /* ── Voice Search ────────────────────────────────────────────── */
  function initVoiceSearch() {
    const btns = document.querySelectorAll('[data-gs-voice-search]');
    if (!btns.length) return;

    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) {
      btns.forEach(b => { b.style.display = 'none'; });
      return;
    }

    btns.forEach(btn => {
      const targetId = btn.dataset.gsVoiceSearch;
      const input    = document.getElementById(targetId) || document.querySelector('[data-gs-search-input]');
      if (!input) return;

      const rec = new SpeechRecognition();
      rec.lang = document.documentElement.lang || 'en-US';
      rec.interimResults = false;
      rec.maxAlternatives = 1;

      btn.addEventListener('click', () => {
        btn.classList.add('listening');
        btn.title = 'Listening…';
        rec.start();
      });

      rec.onresult = e => {
        const transcript = e.results[0][0].transcript;
        input.value = transcript;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        saveHistory(transcript);
        btn.classList.remove('listening');
      };

      rec.onerror = rec.onend = () => {
        btn.classList.remove('listening');
        btn.title = 'Voice search';
      };
    });
  }

  /* ── Barcode Scanner ─────────────────────────────────────────── */
  function initBarcodeScanner() {
    const btns = document.querySelectorAll('[data-gs-barcode-scan]');
    if (!btns.length) return;

    btns.forEach(btn => {
      btn.addEventListener('click', () => {
        if (typeof Html5Qrcode === 'undefined') {
          loadScript('https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js', () => startScanner(btn));
        } else {
          startScanner(btn);
        }
      });
    });
  }

  function startScanner(triggerBtn) {
    let modal = document.getElementById('gsBarcodeModal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'gsBarcodeModal';
      modal.innerHTML = `
        <div style="position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1200;display:flex;align-items:center;justify-content:center;">
          <div style="background:#fff;border-radius:14px;padding:1.5rem;width:min(420px,95vw);">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h6 class="mb-0 fw-bold">Scan Barcode / QR Code</h6>
              <button id="gsBarcodeDismiss" class="btn-close"></button>
            </div>
            <div id="gsBarcodReader"></div>
            <p class="text-muted small mt-2 mb-0 text-center">Point your camera at a barcode or QR code</p>
          </div>
        </div>`;
      document.body.appendChild(modal);
    }

    modal.style.display = 'block';
    const scanner = new Html5Qrcode('gsBarcodReader');
    scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 280, height: 200 } },
      decodedText => {
        scanner.stop();
        modal.style.display = 'none';
        const input = document.querySelector('[data-gs-search-input], #globalSearch');
        if (input) { input.value = decodedText; input.dispatchEvent(new Event('input', { bubbles: true })); }
        saveHistory(decodedText);
      },
      () => {}
    ).catch(() => { modal.style.display = 'none'; });

    document.getElementById('gsBarcodeDismiss').onclick = () => {
      scanner.stop().catch(() => {});
      modal.style.display = 'none';
    };
  }

  /* ── Image / Visual Search ───────────────────────────────────── */
  function initImageSearch() {
    const inputs = document.querySelectorAll('[data-gs-image-search]');
    inputs.forEach(fileInput => {
      fileInput.addEventListener('change', async () => {
        const file = fileInput.files[0];
        if (!file) return;
        if (!file.type.startsWith('image/')) { alert('Please select an image file.'); return; }

        const resultsContainer = document.getElementById(fileInput.dataset.gsImageSearch) ||
          document.getElementById('gsVisualSearchResults');

        showLoadingState(resultsContainer);

        const formData = new FormData();
        formData.append('image', file);

        try {
          const res = await fetch(VISION_API, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData,
          });
          const data = await res.json();
          if (data.results?.length) {
            renderVisualResults(data.results, resultsContainer);
          } else {
            showNoResults(resultsContainer, 'No visually similar products found.');
          }
        } catch {
          showNoResults(resultsContainer, 'Visual search is currently unavailable.');
        } finally {
          fileInput.value = '';
        }
      });
    });
  }

  function showLoadingState(container) {
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2 text-muted">Analyzing image…</p></div>';
  }

  function showNoResults(container, msg) {
    if (!container) return;
    container.innerHTML = `<p class="text-center text-muted py-3">${msg}</p>`;
  }

  function renderVisualResults(results, container) {
    if (!container) return;
    container.innerHTML = `<h6 class="fw-bold mb-3">Visually Similar Products</h6>
      <div class="row g-3">${results.slice(0, 8).map(p => `
        <div class="col-6 col-md-3">
          <a href="/pages/product/view.php?id=${p.id}" class="text-decoration-none">
            <div class="gs-product-card p-2">
              <img src="${p.image || '/assets/images/placeholder.webp'}" class="w-100 rounded mb-2" style="height:120px;object-fit:cover" alt="${escHtml(p.name)}">
              <div class="small fw-bold text-dark">${escHtml(p.name)}</div>
              <div class="small text-primary fw-bold">${p.price_display || ''}</div>
            </div>
          </a>
        </div>`).join('')}
      </div>`;
  }

  /* ── AI Search Suggestions ───────────────────────────────────── */
  function initAiSearchSuggestions() {
    const inputs = document.querySelectorAll('[data-gs-ai-search]');
    inputs.forEach(input => {
      let debounce;
      const targetId = input.dataset.gsAiSearch;
      const container = targetId ? document.getElementById(targetId) : null;

      input.addEventListener('input', () => {
        clearTimeout(debounce);
        const q = input.value.trim();
        if (q.length < 2) { if (container) container.innerHTML = ''; return; }
        debounce = setTimeout(() => fetchAiSuggestions(q, container), 400);
      });

      input.addEventListener('keydown', e => {
        if (e.key === 'Enter' && input.value.trim()) {
          saveHistory(input.value.trim());
        }
      });

      input.addEventListener('focus', () => {
        const h = getHistory();
        if (h.length && !input.value && container) renderHistoryDropdown(h, input, container);
      });
    });
  }

  async function fetchAiSuggestions(q, container) {
    try {
      const res = await fetch(`${SEARCH_API}${encodeURIComponent(q)}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      const data = await res.json();
      if (container && data.results?.length) renderSuggestionDropdown(data.results, container);
    } catch { /* fail silently */ }
  }

  function renderSuggestionDropdown(results, container) {
    container.innerHTML = results.slice(0, 6).map(r => `
      <a class="gs-autocomplete-item text-decoration-none" href="/pages/product/view.php?id=${r.id}">
        <img src="${r.image || '/assets/images/placeholder.webp'}" alt="" onerror="this.src='/assets/images/placeholder.webp'">
        <div>
          <div class="name">${escHtml(r.name)}</div>
          <div class="meta">${r.category_name ? escHtml(r.category_name) + ' · ' : ''}${r.price_display || ''}</div>
        </div>
      </a>`).join('');
    container.classList.add('open');
  }

  function renderHistoryDropdown(history, input, container) {
    container.innerHTML = `<div class="px-3 py-2 text-muted small fw-bold d-flex justify-content-between align-items-center">
        Recent Searches
        <button class="btn btn-link btn-sm p-0 text-danger" id="gsClearHistory">Clear</button>
      </div>` +
      history.map(q => `
        <div class="gs-autocomplete-item" data-gs-history-item="${escHtml(q)}">
          <i class="fa fa-clock-rotate-left text-muted" style="width:36px;text-align:center"></i>
          <div><div class="name">${escHtml(q)}</div></div>
        </div>`).join('');
    container.classList.add('open');

    container.querySelector('#gsClearHistory')?.addEventListener('click', e => {
      e.stopPropagation();
      localStorage.removeItem(HISTORY_KEY);
      container.classList.remove('open');
      container.innerHTML = '';
    });

    container.querySelectorAll('[data-gs-history-item]').forEach(el => {
      el.addEventListener('click', () => {
        input.value = el.dataset.gsHistoryItem;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        container.classList.remove('open');
      });
    });
  }

  /* ── Utility ─────────────────────────────────────────────────── */
  function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function loadScript(src, cb) {
    const s = document.createElement('script');
    s.src = src; s.onload = cb;
    document.head.appendChild(s);
  }

  /* ── Init ────────────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', () => {
    initVoiceSearch();
    initBarcodeScanner();
    initImageSearch();
    initAiSearchSuggestions();
  });
})();
