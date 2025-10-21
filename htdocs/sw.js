const CACHE_VERSION = 'v1.2.0';
const APP_SHELL = [
  '/',
  '/index.php',
  '/assets/css/app.css',
  '/assets/js/app.js',
  '/assets/js/api.js',
  '/assets/js/db.js',
  '/assets/js/editor.js',
  '/assets/js/graph.js',
  '/public/md-editor.css',
  '/public/md-editor.js',
  '/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION).then((cache) => cache.addAll(APP_SHELL)).catch(() => {})
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((key) => key !== CACHE_VERSION).map((key) => caches.delete(key)))
    )
  );
  self.clients.claim();
  console.info(`[Markly] Service worker ${CACHE_VERSION} active`);
});

self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  if (request.method !== 'GET') {
    return;
  }

  if (url.pathname.startsWith('/api/')) {
    event.respondWith(networkFirst(request));
    return;
  }

  if (APP_SHELL.includes(url.pathname)) {
    event.respondWith(cacheFirst(request));
    return;
  }

  event.respondWith(
    caches.match(request).then((cached) => {
      if (cached) {
        return cached;
      }
      return fetch(request)
        .then((response) => {
          const clone = response.clone();
          caches.open(CACHE_VERSION).then((cache) => cache.put(request, clone)).catch(() => {});
          return response;
        })
        .catch(() => caches.match('/index.php'));
    })
  );
});

function cacheFirst(request) {
  return caches.match(request).then((cached) => {
    if (cached) {
      return cached;
    }
    return fetch(request).then((response) => {
      const clone = response.clone();
      caches.open(CACHE_VERSION).then((cache) => cache.put(request, clone)).catch(() => {});
      return response;
    });
  });
}

function networkFirst(request) {
  return fetch(request)
    .then((response) => {
      const clone = response.clone();
      caches.open(CACHE_VERSION).then((cache) => cache.put(request, clone)).catch(() => {});
      return response;
    })
    .catch(() => caches.match(request));
}
