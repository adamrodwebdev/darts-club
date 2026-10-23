// Chargement du site une fois le logo de l'écran titre affiché et signalé par le navigateur
// (plus grand élément de la page) : le code et ses styles ne retardent ni n'alourdissent ce
// premier affichage. Les fichiers à charger viennent du bloc #boot-manifest (écrit au build).
// Intégré à la page : la CSP l'autorise par son empreinte.
;(() => {
  const manifest = JSON.parse(document.getElementById('boot-manifest').textContent)
  // Seuls les fichiers versionnés du site sont chargés, quel que soit le contenu du manifeste
  const ASSET = /^\/assets\/[\w.-]+\.(?:js|css)$/
  let started = false

  const start = () => {
    if (started) return
    started = true
    for (const href of manifest.modules) {
      if (!ASSET.test(href)) continue
      const link = document.createElement('link')
      link.rel = 'modulepreload'
      link.crossOrigin = ''
      link.href = href
      document.head.append(link)
    }
    const styles = []
    for (const href of manifest.styles) {
      if (!ASSET.test(href)) continue
      styles.push(
        new Promise((resolve) => {
          const link = document.createElement('link')
          link.rel = 'stylesheet'
          link.href = href
          link.onload = link.onerror = resolve
          document.head.append(link)
        }),
      )
    }
    const entry = manifest.entry
    if (ASSET.test(entry)) Promise.all(styles).then(() => import(entry))
  }

  const logo = document.querySelector('.title-card__logo')
  const measured = (window.PerformanceObserver && PerformanceObserver.supportedEntryTypes) || []
  // Onglet en arrière-plan ou navigateur sans mesure des affichages : chargement immédiat
  if (document.visibilityState !== 'visible' || logo === null || !measured.includes('paint')) {
    setTimeout(start)
    return
  }
  if (measured.includes('largest-contentful-paint')) {
    // Logo signalé comme plus grand élément peint : il est compté avant tout le code
    new PerformanceObserver((list, observer) => {
      if (list.getEntries().some((entry) => entry.element === logo)) {
        observer.disconnect()
        start()
      }
    }).observe({ type: 'largest-contentful-paint', buffered: true })
  } else {
    // Sans cette mesure : premier affichage signalé et logo prêt
    new PerformanceObserver((list, observer) => {
      if (list.getEntriesByName('first-contentful-paint').length > 0) {
        observer.disconnect()
        logo.decode().then(start, start)
      }
    }).observe({ type: 'paint', buffered: true })
  }
  // Filet : un signal qui ne vient jamais ne bloque pas le site
  setTimeout(start, 1000)
})()
