/* Service worker ChapTarif : cache des ressources statiques, réseau d'abord pour les pages */
const CACHE = 'chaptarif-v9';
const ASSETS = ['/assets/css/app.css?v=11', '/assets/js/app.js?v=7', '/assets/img/logo.svg', '/manifest.webmanifest'];
self.addEventListener('install', e => { e.waitUntil(caches.open(CACHE).then(c => c.addAll(ASSETS))); self.skipWaiting(); });
self.addEventListener('activate', e => { e.waitUntil(caches.keys().then(k => Promise.all(k.filter(x => x !== CACHE).map(x => caches.delete(x))))); self.clients.claim(); });
self.addEventListener('fetch', e => {
  const u = new URL(e.request.url);
  if (e.request.method !== 'GET' || u.origin !== location.origin || u.pathname.startsWith('/admin')) return;
  if (u.pathname.startsWith('/assets/')) {
    // Stale-while-revalidate : sert le cache immédiatement (rapide) mais revérifie
    // toujours le réseau en arrière-plan et met à jour le cache, pour qu'une mise à
    // jour du site se propage automatiquement sans rester bloquée en cache.
    e.respondWith(caches.open(CACHE).then(async c => {
      const cached = await c.match(e.request);
      const network = fetch(e.request).then(res => { c.put(e.request, res.clone()); return res; }).catch(() => cached);
      return cached || network;
    }));
  }
});
