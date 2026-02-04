const CACHE_NAME = 'zensshop-v1';
const ASSETS_TO_CACHE = [
  './assets/css/main5.css',
  './assets/js/main5.js',
  './assets/js/cart12.js',
  './assets/js/lazy-load4.js',
  './assets/js/product-cards4.js'
];

// Install Service Worker
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      console.log('Caching essential assets');
      return cache.addAll(ASSETS_TO_CACHE);
    })
  );
  self.skipWaiting();
});

// Activate Service Worker
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cache) => {
          if (cache !== CACHE_NAME) {
            console.log('Clearing old cache');
            return caches.delete(cache);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Fetch Strategy: Cache Falling Back to Network for Assets, Network falling back to Cache for images
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // For static assets (CSS, JS)
  if (ASSETS_TO_CACHE.some(asset => event.request.url.includes(asset.replace('./', '')))) {
    event.respondWith(
      caches.match(event.request).then((response) => {
        return response || fetch(event.request);
      })
    );
    return;
  }

  // For Images: Network first, then cache (to ensure we get new product images but have fallback)
  if (event.request.destination === 'image') {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          const resClone = response.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, resClone);
          });
          return response;
        })
        .catch(() => caches.match(event.request))
    );
    return;
  }

  // Default: Network only or bypass
});
