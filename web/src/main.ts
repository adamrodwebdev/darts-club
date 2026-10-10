import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import { i18n, loadLocaleMessages } from './i18n'
import { localeController } from './preferences'
import router from './router'

// Langue connue avant le premier rendu : aucun texte affiché dans la mauvaise langue
const locale = localeController.initialFor(window.location.pathname)
await loadLocaleMessages(locale)
i18n.global.locale.value = locale
document.documentElement.lang = locale

const app = createApp(App)

app.use(createPinia())
app.use(i18n)
app.use(router)

app.mount('#app')
