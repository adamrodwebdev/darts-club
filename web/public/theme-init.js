/*
 * Applique le thème mémorisé avant le premier rendu : aucun flash clair/sombre.
 * Fichier externe et non inline : la CSP n'autorise que script-src 'self'.
 * Logique miroir de src/preferences/ThemeController.ts.
 */
;(function () {
  var preference = 'system'
  try {
    preference = window.localStorage.getItem('sd71.theme') || 'system'
  } catch {
    // localStorage indisponible : thème du système
  }
  var theme =
    preference === 'light' || preference === 'dark'
      ? preference
      : window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches
        ? 'light'
        : 'dark'
  document.documentElement.setAttribute('data-theme', theme)
  var meta = document.querySelector('meta[name="theme-color"]')
  if (meta) meta.setAttribute('content', theme === 'light' ? '#f6f6f8' : '#0b0b0d')
})()
