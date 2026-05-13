/**
 * Service Worker for iaiaPRO PWA
 * Strategy: network-first with a minimal cache for installability
 */

const CACHE_VERSION = 'iaiapro-v1';
const MINIMAL_CACHE = [
  '/',
  '/manifest.json',
  '/assets/images/logo.png'
];

// Instalación: cachear solo recursos críticos mínimos
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION).then((cache) => {
      return cache.addAll(MINIMAL_CACHE);
    })
  );
  self.skipWaiting();
});

// Activación: limpiar cachés antiguos
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_VERSION) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Fetch: Network-first (priorizar red, solo usar caché si falla)
self.addEventListener('fetch', (event) => {
  // Solo para GET requests
  if (event.request.method !== 'GET') {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then((response) => {
        // Si la respuesta es exitosa, devolverla directamente
        return response;
      })
      .catch(() => {
        // Solo si falla la red, intentar caché
        return caches.match(event.request).then((cachedResponse) => {
          return cachedResponse || new Response('Offline', {
            status: 503,
            statusText: 'Service Unavailable'
          });
        });
      })
  );
});
