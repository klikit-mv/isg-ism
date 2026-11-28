/* Scout portal service worker: lets the app install on Android and iOS. Pages are never cached (they hold private data);
   only the offline page and the built CSS/JS are, and only when the server answered properly (a failed or wrong answer
   used to be kept for good, which left some devices without styles). */
const CACHE = 'scout-shell-v3';

const EXPECTED = { css: /text\/css/i, js: /javascript|ecmascript/i };

// A response is worth keeping only if it is a real 200 of the right kind for the file.
function isGood(response, url) {
    if (! response || ! response.ok || response.status !== 200 || response.type === 'opaque' || response.redirected) {
        return false;
    }

    const kind = url.pathname.endsWith('.css') ? 'css' : (url.pathname.endsWith('.js') ? 'js' : null);

    return kind === null || EXPECTED[kind].test(response.headers.get('content-type') || '');
}

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.add('/offline')).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline')));

        return;
    }

    if (url.origin === self.location.origin && url.pathname.startsWith('/build/')) {
        // Network first: a good answer refreshes the copy; the copy is only used when the network fails.
        event.respondWith(
            fetch(request).then((response) => {
                if (isGood(response, url)) {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(request, copy));
                }

                return response;
            }).catch(() => caches.match(request).then((hit) => (hit && isGood(hit, url) ? hit : Response.error()))),
        );
    }
});

self.addEventListener('push', (event) => {
    let data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { body: event.data ? event.data.text() : '' };
    }

    event.waitUntil(self.registration.showNotification(data.title || 'Scout portal', {
        body: data.body || '',
        icon: '/pwa-icon/192.png',
        badge: '/pwa-icon/192.png',
        data: { url: data.url || '/dashboard' },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL((event.notification.data && event.notification.data.url) || '/dashboard', self.location.origin).href;

    event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
        for (const client of windows) {
            if ('focus' in client) {
                client.navigate(target);

                return client.focus();
            }
        }

        return clients.openWindow(target);
    }));
});
