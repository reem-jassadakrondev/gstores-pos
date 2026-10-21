// ReemoPOS service worker: keeps the till app usable offline.
// - /pos (the app shell): network first, cached copy when offline
// - /build/* (hashed Vite assets): cache first
// - /api/*: never cached (the app queues offline work in IndexedDB instead)
const CACHE = 'reemo-pos-v1'

self.addEventListener('install', (event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(CACHE)
    const assets = await (await fetch('/pos/precache.json', { cache: 'no-store' })).json()
    await cache.addAll(['/pos', ...assets])
    await self.skipWaiting()
  })())
})

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    for (const key of await caches.keys()) if (key !== CACHE) await caches.delete(key)
    await self.clients.claim()
  })())
})

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url)
  if (event.request.method !== 'GET' || url.origin !== location.origin || url.pathname.startsWith('/api/')) return

  if (event.request.mode === 'navigate' && url.pathname.startsWith('/pos')) {
    event.respondWith(fetch(event.request)
      .then((response) => {
        const copy = response.clone()
        caches.open(CACHE).then((cache) => cache.put('/pos', copy))
        return response
      })
      .catch(() => caches.match('/pos')))
    return
  }

  if (url.pathname.startsWith('/build/')) {
    event.respondWith(caches.match(event.request).then((hit) => hit || fetch(event.request).then((response) => {
      const copy = response.clone()
      caches.open(CACHE).then((cache) => cache.put(event.request, copy))
      return response
    })))
  }
})
