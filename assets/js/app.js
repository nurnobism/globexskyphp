/**
 * GlobexSky — Main Application JS
 * Handles: UI interactions, AJAX cart/wishlist, search autocomplete,
 *          language/currency switcher, statistics counter, newsletter.
 */
(function () {
  'use strict';

  /* ── Helpers ──────────────────────────────────────────────────── */
  const $ = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

  function getCsrfToken() {
    const meta = $('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  async function apiFetch(url, options = {}) {
    const defaults = {
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': getCsrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
      },
    };
    const res = await fetch(url, Object.assign(defaults, options));
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
  }

  function showFlash(message, type = 'success') {
    const wrap = document.getElementById('gs-flash-container') || (() => {
      const div = document.createElement('div');
      div.id = 'gs-flash-container';
      div.style.cssText = 'position:fixed;top:1.25rem;right:1.25rem;z-index:1100;display:flex;flex-direction:column;gap:.5rem;';
      document.body.appendChild(div);
      return div;
    })();

    const iconMap = { success: 'fa-circle-check', danger: 'fa-circle-xmark', warning: 'fa-triangle-exclamation', info: 'fa-circle-info' };
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} d-flex align-items-center gap-2 gs-flash shadow`;
    toast.innerHTML = `<i class="fa ${iconMap[type] || iconMap.info}"></i><span>${message}</span>
                       <button type="button" class="btn-close ms-auto" aria-label="Close"></button>`;
    wrap.appendChild(toast);

    toast.querySelector('.btn-close').addEventListener('click', () => dismissToast(toast));
    setTimeout(() => dismissToast(toast), 4500);
  }

  function dismissToast(el) {
    el.classList.add('fade-out');
    setTimeout(() => el.remove(), 380);
  }

  /* ── DOMContentLoaded ─────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', () => {
    initBootstrapComponents();
    initLangCurrencySwitcher();
    initCart();
    initWishlist();
    initNewsletter();
    initFlashDismiss();
    initLazyImages();
    initSearchAutocomplete();
    initStatsCounter();
    initBackToTop();
    initProductGallery();
    initCountdown();
  });

  /* ── Bootstrap Tooltips & Popovers ───────────────────────────── */
  function initBootstrapComponents() {
    if (typeof bootstrap === 'undefined') return;
    $$('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el, { trigger: 'hover' }));
    $$('[data-bs-toggle="popover"]').forEach(el => new bootstrap.Popover(el));
  }

  /* ── Language / Currency Switcher ─────────────────────────────── */
  function initLangCurrencySwitcher() {
    $$('[data-gs-lang]').forEach(btn => {
      btn.addEventListener('click', async e => {
        e.preventDefault();
        const lang = btn.dataset.gsLang;
        try {
          await apiFetch('/api/session.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'set_lang', lang }),
          });
          location.reload();
        } catch {
          showFlash('Could not switch language.', 'warning');
        }
      });
    });

    $$('[data-gs-currency]').forEach(btn => {
      btn.addEventListener('click', async e => {
        e.preventDefault();
        const currency = btn.dataset.gsCurrency;
        try {
          await apiFetch('/api/session.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'set_currency', currency }),
          });
          location.reload();
        } catch {
          showFlash('Could not switch currency.', 'warning');
        }
      });
    });
  }

  /* ── Cart ─────────────────────────────────────────────────────── */
  function initCart() {
    document.addEventListener('click', async e => {
      const btn = e.target.closest('[data-gs-cart-add]');
      if (!btn) return;
      e.preventDefault();

      const productId = btn.dataset.gsCartAdd;
      const quantity  = parseInt(btn.dataset.qty || '1', 10);
      btn.disabled = true;
      const originalHtml = btn.innerHTML;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';

      try {
        const res = await apiFetch('/api/cart.php', {
          method: 'POST',
          body: JSON.stringify({ action: 'add', product_id: productId, quantity }),
        });
        updateCartBadge(res.cart_count ?? null);
        showFlash(res.message || 'Added to cart!', 'success');
      } catch {
        showFlash('Could not add to cart. Please try again.', 'danger');
      } finally {
        btn.innerHTML = originalHtml;
        btn.disabled = false;
      }
    });

    document.addEventListener('click', async e => {
      const btn = e.target.closest('[data-gs-cart-remove]');
      if (!btn) return;
      const itemId = btn.dataset.gsCartRemove;
      try {
        const res = await apiFetch('/api/cart.php', {
          method: 'POST',
          body: JSON.stringify({ action: 'remove', item_id: itemId }),
        });
        updateCartBadge(res.cart_count ?? null);
        const row = btn.closest('[data-cart-item]');
        if (row) row.remove();
        if (res.cart_empty) location.reload();
      } catch {
        showFlash('Could not remove item.', 'danger');
      }
    });
  }

  function updateCartBadge(count) {
    $$('.gs-cart-badge, #cartCount').forEach(el => {
      if (count === null) return;
      el.textContent = count;
      el.classList.toggle('d-none', count === 0);
    });
  }

  /* ── Wishlist ─────────────────────────────────────────────────── */
  function initWishlist() {
    document.addEventListener('click', async e => {
      const btn = e.target.closest('[data-gs-wishlist]');
      if (!btn) return;
      e.preventDefault();
      const productId = btn.dataset.gsWishlist;

      try {
        const res = await apiFetch('/api/wishlist.php', {
          method: 'POST',
          body: JSON.stringify({ action: 'toggle', product_id: productId }),
        });
        const icon = btn.querySelector('i') || btn;
        btn.classList.toggle('active', res.in_wishlist);
        icon.classList.toggle('fa-solid', res.in_wishlist);
        icon.classList.toggle('fa-regular', !res.in_wishlist);
        showFlash(res.message || (res.in_wishlist ? 'Added to wishlist' : 'Removed from wishlist'), 'info');
      } catch {
        showFlash('Please log in to use the wishlist.', 'warning');
      }
    });
  }

  /* ── Newsletter ───────────────────────────────────────────────── */
  function initNewsletter() {
    $$('form[data-gs-newsletter], #newsletterForm').forEach(form => {
      form.addEventListener('submit', async e => {
        e.preventDefault();
        const emailInput = form.querySelector('[type="email"]');
        if (!emailInput) return;
        const email = emailInput.value.trim();
        if (!email) return;

        const btn = form.querySelector('[type="submit"]');
        const origText = btn ? btn.textContent : '';
        if (btn) { btn.disabled = true; btn.textContent = 'Subscribing…'; }

        try {
          const res = await apiFetch('/api/newsletter.php', {
            method: 'POST',
            body: JSON.stringify({ email, csrf_token: getCsrfToken() }),
          });
          showFlash(res.message || 'Thank you for subscribing!', res.success ? 'success' : 'warning');
          if (res.success) emailInput.value = '';
        } catch {
          showFlash('Subscription failed. Please try again.', 'danger');
        } finally {
          if (btn) { btn.disabled = false; btn.textContent = origText; }
        }
      });
    });
  }

  /* ── Flash message auto-dismiss ───────────────────────────────── */
  function initFlashDismiss() {
    $$('.gs-flash, .alert.alert-dismissible').forEach(el => {
      setTimeout(() => {
        if (document.contains(el)) {
          el.classList.add('fade');
          setTimeout(() => el.remove(), 500);
        }
      }, 5000);
    });
  }

  /* ── Lazy Loading Images (Intersection Observer) ──────────────── */
  function initLazyImages() {
    const images = $$('img[data-src], img[loading="lazy"]');
    if (!images.length) return;

    if ('IntersectionObserver' in window) {
      const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
          if (!entry.isIntersecting) return;
          const img = entry.target;
          if (img.dataset.src) {
            img.src = img.dataset.src;
            img.removeAttribute('data-src');
          }
          img.classList.add('gs-img-loaded');
          obs.unobserve(img);
        });
      }, { rootMargin: '200px' });
      images.forEach(img => observer.observe(img));
    } else {
      images.forEach(img => { if (img.dataset.src) img.src = img.dataset.src; });
    }
  }

  /* ── Search Autocomplete ──────────────────────────────────────── */
  function initSearchAutocomplete() {
    const inputs = $$('[data-gs-search-input], #globalSearch');
    inputs.forEach(input => {
      let dropdown = input.parentElement.querySelector('.gs-autocomplete-dropdown');
      if (!dropdown) {
        dropdown = document.createElement('div');
        dropdown.className = 'gs-autocomplete-dropdown';
        input.parentElement.style.position = 'relative';
        input.parentElement.appendChild(dropdown);
      }

      let timer;
      let lastQuery = '';

      input.addEventListener('input', () => {
        const q = input.value.trim();
        clearTimeout(timer);
        if (q.length < 2) { dropdown.classList.remove('open'); dropdown.innerHTML = ''; return; }
        if (q === lastQuery) return;
        lastQuery = q;
        timer = setTimeout(() => fetchSuggestions(q, dropdown, input), 320);
      });

      input.addEventListener('keydown', e => {
        if (e.key === 'Escape') { dropdown.classList.remove('open'); input.blur(); }
        if (e.key === 'Enter') { dropdown.classList.remove('open'); }
      });

      document.addEventListener('click', e => {
        if (!input.parentElement.contains(e.target)) dropdown.classList.remove('open');
      });
    });
  }

  async function fetchSuggestions(q, dropdown, input) {
    try {
      const data = await apiFetch(`/api/products.php?action=search&q=${encodeURIComponent(q)}`);
      renderSuggestions(data.results || data, dropdown, input);
    } catch {
      dropdown.classList.remove('open');
    }
  }

  function renderSuggestions(results, dropdown, input) {
    if (!results || !results.length) { dropdown.classList.remove('open'); return; }
    dropdown.innerHTML = results.slice(0, 8).map(item => `
      <a class="gs-autocomplete-item text-decoration-none" href="/pages/product/view.php?id=${item.id}">
        <img src="${item.image || '/assets/images/placeholder.webp'}" alt="" onerror="this.src='/assets/images/placeholder.webp'">
        <div>
          <div class="name">${escHtml(item.name)}</div>
          <div class="meta">${item.category_name ? escHtml(item.category_name) + ' · ' : ''}${item.price_display || ''}</div>
        </div>
      </a>`).join('');
    dropdown.classList.add('open');
  }

  function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  /* ── Statistics Counter Animation ────────────────────────────── */
  function initStatsCounter() {
    const counters = $$('[data-gs-counter]');
    if (!counters.length) return;

    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        animateCounter(entry.target);
        observer.unobserve(entry.target);
      });
    }, { threshold: 0.5 });

    counters.forEach(el => observer.observe(el));
  }

  function animateCounter(el) {
    const target  = parseFloat(el.dataset.gsCounter.replace(/[^0-9.]/g, ''));
    const suffix  = el.dataset.suffix || '';
    const prefix  = el.dataset.prefix || '';
    const isFloat = el.dataset.gsCounter.includes('.');
    const duration = 1800;
    const start   = performance.now();

    function step(now) {
      const elapsed = now - start;
      const progress = Math.min(elapsed / duration, 1);
      const ease = 1 - Math.pow(1 - progress, 3);
      const value = target * ease;
      el.textContent = prefix + (isFloat ? value.toFixed(1) : Math.floor(value).toLocaleString()) + suffix;
      if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  /* ── Back to Top ─────────────────────────────────────────────── */
  function initBackToTop() {
    let btn = $('#gsBackTop');
    if (!btn) {
      btn = document.createElement('button');
      btn.id = 'gsBackTop';
      btn.className = 'gs-back-top';
      btn.innerHTML = '<i class="fa fa-chevron-up"></i>';
      btn.setAttribute('aria-label', 'Back to top');
      document.body.appendChild(btn);
    }

    window.addEventListener('scroll', () => {
      btn.classList.toggle('visible', window.scrollY > 400);
    }, { passive: true });

    btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
  }

  /* ── Product Image Gallery ────────────────────────────────────── */
  function initProductGallery() {
    const thumbs = $$('[data-gs-gallery-thumb]');
    thumbs.forEach(thumb => {
      thumb.addEventListener('click', () => {
        const main = document.getElementById(thumb.dataset.gsGalleryThumb);
        if (!main) return;
        const prev = $$('[data-gs-gallery-thumb].active');
        prev.forEach(t => t.classList.remove('active'));
        thumb.classList.add('active');
        main.src = thumb.dataset.full || thumb.src;
        main.classList.add('gs-gallery-transition');
        setTimeout(() => main.classList.remove('gs-gallery-transition'), 300);
      });
    });
  }

  /* ── Countdown Timer ─────────────────────────────────────────── */
  function initCountdown() {
    $$('[data-gs-countdown]').forEach(el => {
      const endTime = new Date(el.dataset.gsCountdown).getTime();
      if (isNaN(endTime)) return;

      function update() {
        const diff = endTime - Date.now();
        if (diff <= 0) {
          el.innerHTML = '<span class="gs-countdown-unit"><span class="gs-countdown-num">00</span><span class="gs-countdown-lbl">Ended</span></span>';
          return;
        }
        const d = Math.floor(diff / 86400000);
        const h = Math.floor((diff % 86400000) / 3600000);
        const m = Math.floor((diff % 3600000) / 60000);
        const s = Math.floor((diff % 60000) / 1000);
        el.innerHTML = [
          d > 0 ? `<div class="gs-countdown-unit"><span class="gs-countdown-num">${pad(d)}</span><span class="gs-countdown-lbl">Days</span></div>` : '',
          `<div class="gs-countdown-unit"><span class="gs-countdown-num">${pad(h)}</span><span class="gs-countdown-lbl">Hrs</span></div>`,
          `<div class="gs-countdown-unit"><span class="gs-countdown-num">${pad(m)}</span><span class="gs-countdown-lbl">Min</span></div>`,
          `<div class="gs-countdown-unit"><span class="gs-countdown-num">${pad(s)}</span><span class="gs-countdown-lbl">Sec</span></div>`,
        ].join('');
      }
      update();
      setInterval(update, 1000);
    });
  }

  function pad(n) { return String(n).padStart(2, '0'); }

})();
