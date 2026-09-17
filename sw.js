/**
 * Service Worker for Admidio Progressive Web App (PWA)
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

const CACHE_NAME = 'admidio-pwa-v2';

const PRECACHE_ASSETS = [
    './system/logo/admidio_logo_192.png',
    './system/logo/admidio_logo_512.png'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(PRECACHE_ASSETS).catch((err) => {
                console.warn('PWA: Pre-caching assets failed:', err);
            });
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
            );
        }).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    // Only handle GET requests and http/https schemes
    if (event.request.method !== 'GET' || !event.request.url.startsWith('http')) {
        return;
    }

    // Network-first strategy
    event.respondWith(
        fetch(event.request)
            .then((networkResponse) => {
                // Cache static assets (CSS, JS, images, fonts)
                if (networkResponse && networkResponse.status === 200) {
                    const url = new URL(event.request.url);
                    if (url.pathname.match(/\.(css|js|png|jpg|jpeg|svg|ico|woff2?)$/i)) {
                        const responseClone = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(event.request, responseClone);
                        });
                    }
                }
                return networkResponse;
            })
            .catch(async () => {
                // Try cache
                const cachedResponse = await caches.match(event.request);
                if (cachedResponse) {
                    return cachedResponse;
                }

                // If navigation request and offline, provide friendly offline HTML
                if (event.request.mode === 'navigate') {
                    return new Response(
                        '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline - Admidio</title><style>body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;text-align:center;padding:50px 20px;background:#f8f9fa;color:#333}h1{color:#349aaa;margin-bottom:1rem}p{color:#6c757d;font-size:1.1rem;margin-bottom:2rem}.btn{display:inline-block;padding:10px 20px;background:#349aaa;color:#fff;border-radius:4px;text-decoration:none;font-weight:bold}</style></head><body><h1>Offline</h1><p>Keine Internetverbindung / No internet connection</p><a href="javascript:window.location.reload()" class="btn">Erneut versuchen / Retry</a></body></html>',
                        {
                            status: 200,
                            headers: { 'Content-Type': 'text/html; charset=utf-8' }
                        }
                    );
                }

                return new Response('Offline', {
                    status: 503,
                    statusText: 'Service Unavailable',
                    headers: { 'Content-Type': 'text/plain; charset=utf-8' }
                });
            })
    );
});
