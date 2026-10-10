/* Service worker : affichage des notifications Web Push du club */
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
