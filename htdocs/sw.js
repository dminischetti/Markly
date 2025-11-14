const CACHE_VERSION = 'v1.2.0';
const SCOPE_URL = new URL(self.registration.scope);
const BASE_PATH = SCOPE_URL.pathname.replace(/\/$/, '') || '';

function joinBase(path) {
  if (!path || path === '/') {
    if (!BASE_PATH || BASE_PATH === '/') {
      return '/';
    }
    return `${BASE_PATH}/`;
  }

  const normalized = path.startsWith('/') ? path : `/${path}`;
  if (!BASE_PATH || BASE_PATH === '/') {
    return normalized;
  }

  return `${BASE_PATH}${normalized}`;
}

function toUrl(path) {
  return new URL(joinBase(path), SCOPE_URL.origin).toString();
}

const APP_SHELL_ENTRIES = [
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

const APP_SHELL_URLS = APP_SHELL_ENTRIES.flatMap((entry) => {
  const full = toUrl(entry);
  return full.endsWith('/') ? [full, full.slice(0, -1)] : [full];
});
const APP_SHELL = new Set(APP_SHELL_URLS);

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION).then((cache) => cache.addAll(Array.from(APP_SHELL))).catch(() => {})
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

  const apiPrefix = joinBase('/api/');
  if (url.origin === SCOPE_URL.origin && url.pathname.startsWith(apiPrefix)) {
    event.respondWith(networkFirst(request));
    return;
  }

  if (APP_SHELL.has(url.toString())) {
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
        .catch(() =>
          caches.match(toUrl('/')).then((cached) => cached || caches.match(toUrl('/index.php')))
        );
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
