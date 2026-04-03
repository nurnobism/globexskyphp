// sw.js — GlobexSky Service Worker (Phase 10)
const CACHE_NAME = 'globexsky-v1';
const STATIC_CACHE = 'globexsky-static-v1';
const API_CACHE = 'globexsky-api-v1';

const STATIC_ASSETS = [
  '/',
  '/assets/css/style.css',
  '/assets/js/app.js',
  '/manifest.json',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
];

// Install — cache static assets
self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => {
      return cache.addAll(STATIC_ASSETS.map(url => new Request(url, { cache: 'reload' }))).catch((err) => {
        console.warn('[SW] Pre-cache failed (non-fatal):', err);
      });
    })
  );
});

// Activate — clean old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter(k => k !== STATIC_CACHE && k !== API_CACHE).map(k => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

// Fetch strategy
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip non-GET requests and browser-extension requests
  if (request.method !== 'GET' || !url.protocol.startsWith('http')) return;

  // Network-first for API calls
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(
      fetch(request)
        .then((response) => {
          if (response.ok) {
            const clone = response.clone();
            caches.open(API_CACHE).then(c => c.put(request, clone));
          }
          return response;
        })
        .catch(() => caches.match(request).then(r => r || offlineFallback()))
    );
    return;
  }

  // Cache-first for static assets (exact hostname match to avoid substring spoofing)
  if (url.pathname.startsWith('/assets/') || url.hostname === 'cdn.jsdelivr.net' || url.hostname.endsWith('.jsdelivr.net')) {
    event.respondWith(
      caches.match(request).then(cached => cached || fetch(request).then(response => {
        if (response.ok) {
          caches.open(STATIC_CACHE).then(c => c.put(request, response.clone()));
        }
        return response;
      }))
    );
    return;
  }

  // Network-first with offline fallback for HTML pages
  event.respondWith(
    fetch(request)
      .then(response => {
        if (response.ok) {
          caches.open(STATIC_CACHE).then(c => c.put(request, response.clone()));
        }
        return response;
      })
      .catch(() => caches.match(request).then(r => r || offlineFallback()))
  );
});

function offlineFallback() {
  return new Response(
    `<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
     <meta name="viewport" content="width=device-width,initial-scale=1">
     <title>Offline — GlobexSky</title>
     <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
     </head><body>
     <div class="container d-flex flex-column align-items-center justify-content-center vh-100 text-center">
       <div class="mb-4"><svg width="80" height="80" viewBox="0 0 24 24" fill="#6c757d">
         <path d="M24 8.98A11.93 11.93 0 0 0 12 4C6.48 4 2 8.48 2 14c0 5.52 4.48 10 10 10s10-4.48 10-10c0-1.04-.16-2.04-.46-2.98L24 8.98zM12 22c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>
       </svg></div>
       <h2 class="fw-bold">You are offline</h2>
       <p class="text-muted">Please check your internet connection and try again.</p>
       <button onclick="window.location.reload()" class="btn btn-primary mt-2">Try Again</button>
     </div></body></html>`,
    { headers: { 'Content-Type': 'text/html' } }
  );
}

// Background sync
self.addEventListener('sync', (event) => {
  if (event.tag === 'background-sync') {
    event.waitUntil(processBackgroundSync());
  }
});

async function processBackgroundSync() {
  // Process any queued offline form submissions
}
