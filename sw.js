const CACHE_NAME = 'denr-land-inventory-v1';
const ASSETS_TO_CACHE = [
    './assets/css/style.css',
    './assets/img/logo.png',
    './manifest.json'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(ASSETS_TO_CACHE);
        })
    );
});

self.addEventListener('fetch', (event) => {
    event.respondWith(
        caches.match(event.request).then((cachedResponse) => {
            // Return cached response if found, else fetch from network
            return cachedResponse || fetch(event.request);
        })
    );
});
