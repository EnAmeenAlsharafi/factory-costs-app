/*
 * Service worker for the installable app.
 * - Pages always come from the network (they hold live, per-user data); with no connection the
 *   offline page is shown instead of the browser's error.
 * - Built assets, fonts and icons are cached so the app opens fast and looks right on poor signal.
 * - Nothing is cached for POST requests or for other sites.
 */
const VERSION = 'sadir-app-v2';
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

/*
 * Push notifications.
 *
 * The push carries the notification itself, encrypted end to end for this browser, so it can be
 * shown while the app is closed and no one is signed in on this tab. An empty push (from an older
 * sender, or a browser whose keys could not be used) falls back to asking the application, and if
 * even that fails a plain notice is still shown: a push the phone receives is never silent.
 */
const PUSH_ENDPOINT = '/push/latest';
const FALLBACK_MESSAGE = { count: 1, title: 'لديك إشعارات جديدة', body: 'افتح النظام لعرضها.', url: '/notifications' };

const readPushMessage = (event) => {
    if (event.data) {
        try {
            return Promise.resolve(event.data.json());
        } catch (error) {
            // Not JSON: fall through to asking the application.
        }
    }

    return fetch(PUSH_ENDPOINT, { credentials: 'include', headers: { Accept: 'application/json' } })
        .then((response) => (response.ok ? response.json() : FALLBACK_MESSAGE))
        .catch(() => FALLBACK_MESSAGE);
};

self.addEventListener('push', (event) => {
    event.waitUntil(
        readPushMessage(event)
            .then((payload) => {
                if (!payload || !payload.count) {
                    return undefined;
                }

                return self.registration.showNotification(payload.title || FALLBACK_MESSAGE.title, {
                    body: payload.body || '',
                    dir: 'rtl',
                    lang: 'ar',
                    tag: payload.tag || 'sadir-notifications',
                    renotify: true,
                    icon: '/app-icon/192/any',
                    vibrate: [120, 60, 120],
                    data: { url: payload.url || '/' },
                });
            }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            for (const client of clients) {
                if ('focus' in client) {
                    client.navigate(target);
                    return client.focus();
                }
            }
            return self.clients.openWindow(target);
        }),
    );
});
