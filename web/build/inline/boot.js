// Chargement du site une fois le premier affichage (écran titre) signalé par le navigateur :
// le code et ses styles ne retardent ni n'alourdissent ce premier affichage. Les fichiers à
// charger viennent du bloc #boot-manifest (écrit au build). Intégré à la page : la CSP
// l'autorise par son empreinte.
;(() => {
  const manifest = JSON.parse(document.getElementById('boot-manifest').textContent)
  let started = false

  const start = () => {
    if (started) return
    started = true
    for (const href of manifest.modules) {
      const link = document.createElement('link')
      link.rel = 'modulepreload'
      link.crossOrigin = ''
      link.href = href
      document.head.append(link)
    }
    const styles = manifest.styles.map(
      (href) =>
        new Promise((resolve) => {
          const link = document.createElement('link')
          link.rel = 'stylesheet'
          link.href = href
          link.onload = link.onerror = resolve
          document.head.append(link)
        }),
    )
    Promise.all(styles).then(() => import(manifest.entry))
  }

  // Onglet en arrière-plan ou navigateur sans mesure des affichages : chargement immédiat
  const paint = window.PerformanceObserver && (PerformanceObserver.supportedEntryTypes || []).includes('paint')
  if (document.visibilityState !== 'visible' || !paint) {
    setTimeout(start)
    return
  }
  new PerformanceObserver((list, observer) => {
    if (list.getEntriesByName('first-contentful-paint').length > 0) {
      observer.disconnect()
      start()
    }
  }).observe({ type: 'paint', buffered: true })
  // Filet : un signal qui ne vient jamais ne bloque pas le site
  setTimeout(start, 1000)
})()
