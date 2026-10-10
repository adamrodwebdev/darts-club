import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import { i18n, loadLocaleMessages } from './i18n'
import { localeController } from './preferences'
import router from './router'

// Langue connue avant le premier rendu : aucun texte affiché dans la mauvaise langue.
// Le code de la page demandée est téléchargé en même temps que les traductions.
const locale = localeController.initialFor(window.location.pathname)
const pageChunks = router
  .resolve(window.location.pathname + window.location.search)
  .matched.flatMap((record) => Object.values(record.components ?? {}))
  .filter((component): component is () => Promise<unknown> => typeof component === 'function')
  .map((load) => load().catch(() => undefined))
await Promise.all([loadLocaleMessages(locale), ...pageChunks])
i18n.global.locale.value = locale
document.documentElement.lang = locale

const app = createApp(App)

app.use(createPinia())
app.use(i18n)
app.use(router)

app.mount('#app')

// Service worker (production) : page hors ligne et notifications push
if (import.meta.env.PROD && 'serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch(() => undefined)
  })
}
