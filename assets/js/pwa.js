// assets/js/pwa.js — PWA Registration (Phase 10)
(function () {
  'use strict';

  // Register Service Worker
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker
        .register('/sw.js', { scope: '/' })
        .then(function (reg) {
          console.log('[PWA] Service Worker registered:', reg.scope);
          reg.addEventListener('updatefound', function () {
            var newWorker = reg.installing;
            if (newWorker) {
              newWorker.addEventListener('statechange', function () {
                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                  showUpdateBanner();
                }
              });
            }
          });
        })
        .catch(function (err) {
          console.warn('[PWA] Service Worker registration failed:', err);
        });
    });
  }

  // Install prompt (A2HS)
  var deferredPrompt = null;
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredPrompt = e;
    showInstallButton();
  });

  window.addEventListener('appinstalled', function () {
    deferredPrompt = null;
    hideInstallButton();
  });

  function showInstallButton() {
    var btn = document.getElementById('pwa-install-btn');
    if (btn) btn.style.display = 'inline-flex';
  }

  function hideInstallButton() {
    var btn = document.getElementById('pwa-install-btn');
    if (btn) btn.style.display = 'none';
  }

  // Expose install function globally
  window.pwaInstall = function () {
    if (!deferredPrompt) return;
    deferredPrompt.prompt();
    deferredPrompt.userChoice.then(function (result) {
      deferredPrompt = null;
      hideInstallButton();
    });
  };

  function showUpdateBanner() {
    var banner = document.createElement('div');
    banner.style.cssText = 'position:fixed;bottom:1rem;right:1rem;z-index:9999;';
    banner.innerHTML = '<div class="alert alert-info shadow d-flex align-items-center gap-3 mb-0">'
      + '<span>A new version is available.</span>'
      + '<button class="btn btn-sm btn-primary" onclick="window.location.reload()">Update</button>'
      + '<button class="btn-close" onclick="this.closest(\'.alert\').remove()"></button>'
      + '</div>';
    document.body.appendChild(banner);
  }
}());
