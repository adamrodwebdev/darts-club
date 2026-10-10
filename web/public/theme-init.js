/*
 * Applique le thème et le choix d'animations mémorisés avant le premier rendu :
 * aucun flash clair/sombre, aucune animation qui démarre puis s'arrête.
 * Fichier externe et non inline : la CSP n'autorise que script-src 'self'.
 * Logique miroir de src/preferences/ThemeController.ts et MotionController.ts.
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

  var motion = 'system'
  try {
    motion = window.localStorage.getItem('sd71.motion') || 'system'
  } catch {
    // localStorage indisponible : réglage de l'appareil
  }
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches
  document.documentElement.setAttribute('data-motion', motion === 'on' || motion === 'off' ? motion : reduced ? 'off' : 'on')
})()
