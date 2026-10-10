/* Service worker : notifications Web Push du club et page hors ligne */

// Changer la version pour renouveler le cache de la page hors ligne
const OFFLINE_CACHE = 'offline-v1'
const OFFLINE_FILES = ['/offline.html', '/offline.css', '/logo.svg']

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(OFFLINE_CACHE).then((cache) => cache.addAll(OFFLINE_FILES)))
  self.skipWaiting()
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) => Promise.all(keys.filter((key) => key !== OFFLINE_CACHE).map((key) => caches.delete(key))))
      .then(() => self.clients.claim()),
  )
})

// Navigation seulement : le réseau d'abord (contenu toujours à jour), la page hors ligne en secours.
// L'API et les fichiers ne passent jamais par ce cache (données personnelles).
self.addEventListener('fetch', (event) => {
  if (event.request.mode !== 'navigate') return
  event.respondWith(fetch(event.request).catch(() => caches.match('/offline.html')))
})
self.addEventListener('push', (event) => {
  let data = {}
  try {
    data = event.data ? event.data.json() : {}
  } catch {
    data = {}
  }

  const url = typeof data.url === 'string' && data.url.startsWith('/') ? data.url : '/'

  event.waitUntil(
    self.registration.showNotification(data.title || 'Skull Darts 71', {
      body: data.body || '',
      tag: data.tag,
      icon: '/icon-192.png',
      badge: '/favicon-32.png',
      data: { url },
    }),
  )
})

self.addEventListener('notificationclick', (event) => {
  event.notification.close()
  // Uniquement des chemins internes au site
  const target = new URL(event.notification.data?.url || '/', self.location.origin)
  if (target.origin !== self.location.origin) return

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
      const open = windows.find((w) => w.url.startsWith(self.location.origin))
      return open ? open.navigate(target.href).then((w) => w?.focus()) : self.clients.openWindow(target.href)
    }),
  )
})
