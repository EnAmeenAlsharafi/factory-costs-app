/*
 * Service worker for the installable app.
 * - Pages always come from the network (they hold live, per-user data); with no connection the
 *   offline page is shown instead of the browser's error.
 * - Built assets, fonts and icons are cached so the app opens fast and looks right on poor signal.
 * - Nothing is cached for POST requests or for other sites.
 */
const VERSION = 'sadir-app-v1';
const OFFLINE_URL = '/offline';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(VERSION).then((cache) => cache.add(new Request(OFFLINE_URL, { cache: 'reload' }))));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== VERSION).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

const isStaticAsset = (url) => url.pathname.startsWith('/build/')
    || url.pathname.startsWith('/app-icon/')
    || url.pathname === '/company/logo'
    || /\.(?:woff2?|ttf|png|jpg|jpeg|webp|svg|ico)$/i.test(url.pathname);

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    if (isStaticAsset(url)) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(VERSION).then((cache) => cache.put(request, copy));
                }
                return response;
            })),
        );
    }
});
