const CACHE_NAME = 'gymtracker-v1';
const STATIC_ASSETS = [
  'icon.png',
  'manifest.json',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js'
];

// Install Event
self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS);
    })
  );
  self.skipWaiting();
});

// Activate Event
self.addEventListener('activate', (e) => {
  self.clients.claim();
});

// Fetch Event (Network First for dynamic PHP, Cache First for static styles/assets)
self.addEventListener('fetch', (e) => {
  // Hanya intercept HTTP/HTTPS requests (hindari chrome-extension:// dsb.)
  if (!e.request.url.startsWith('http')) return;

  const isStatic = STATIC_ASSETS.includes(e.request.url) || 
                   e.request.destination === 'image' || 
                   e.request.destination === 'font';

  if (isStatic) {
    // Cache First Strategy untuk asset statis
    e.respondWith(
      caches.match(e.request).then((cachedResponse) => {
        return cachedResponse || fetch(e.request).then((networkResponse) => {
          return caches.open(CACHE_NAME).then((cache) => {
            // Hanya simpan respon sukses (200 OK) ke dalam cache
            if (networkResponse.status === 200) {
              cache.put(e.request, networkResponse.clone());
            }
            return networkResponse;
          });
        });
      })
    );
  } else {
    // Network First Strategy untuk PHP Pages agar database log selalu up-to-date
    e.respondWith(
      fetch(e.request)
        .then((networkResponse) => {
          return caches.open(CACHE_NAME).then((cache) => {
            // Hanya simpan respon sukses (200 OK) ke dalam cache
            if (networkResponse.status === 200) {
              cache.put(e.request, networkResponse.clone());
            }
            return networkResponse;
          });
        })
        .catch(() => {
          return caches.match(e.request);
        })
    );
  }
});
