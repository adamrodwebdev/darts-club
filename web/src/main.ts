import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import { dismissTitleCard } from './boot/titleCard'
import { i18n, loadLocaleMessages } from './i18n'
import { createPreferenceControllers, PREFERENCES } from './preferences'
import { createAppRouter } from './router'
import { createServices, SERVICES } from './services'

// Racine de composition : chaque dépendance est créée ici une fois, puis fournie à l'application
const services = createServices()
const preferences = createPreferenceControllers(window)
const router = createAppRouter(services)

// Langue connue avant le premier rendu : aucun texte affiché dans la mauvaise langue.
// Le code de la page demandée est téléchargé en même temps que les traductions.
const locale = preferences.locale.initialFor(window.location.pathname)
const pageChunks = router
  .resolve(window.location.pathname + window.location.search)
  .matched.flatMap((record) => Object.values(record.components ?? {}))
  .filter((component): component is () => Promise<unknown> => typeof component === 'function')
  .map((load) => load().catch(() => undefined))
await Promise.all([loadLocaleMessages(locale), ...pageChunks])
i18n.global.locale.value = locale
document.documentElement.lang = locale

const app = createApp(App)

app.provide(SERVICES, services)
app.provide(PREFERENCES, preferences)
app.use(createPinia())
app.use(i18n)
app.use(router)

app.mount('#app')

// Première page affichée : l'écran titre de index.html laisse la place
void router.isReady().then(() => dismissTitleCard(document))

// Service worker (production) : page hors ligne et notifications push
if (import.meta.env.PROD && 'serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch(() => undefined)
  })
}
