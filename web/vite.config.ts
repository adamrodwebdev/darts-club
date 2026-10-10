import { fileURLToPath, URL } from 'node:url'

import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import VueI18nPlugin from '@intlify/unplugin-vue-i18n/vite'
import vueDevTools from 'vite-plugin-vue-devtools'
import { localizedIndex } from './build/localizedIndex.ts'

const locales = fileURLToPath(new URL('./src/i18n/locales', import.meta.url))

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  // VITE_API_PROXY peut venir de l'environnement ou d'un fichier .env.*.local
  const env = loadEnv(mode, process.cwd(), '')

  return {
    plugins: [
      vue(),
      // Traductions précompilées : pas de compilateur de messages dans le bundle (ni eval, CSP stricte)
      VueI18nPlugin({ include: [`${locales}/**`] }),
      vueDevTools(),
      localizedIndex({ localesDir: locales, source: 'fr', pages: { en: 'en' }, siteUrl: env.VITE_SITE_URL }),
    ],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
      },
    },
    server: {
      // Même origine que le front : cookies de session SameSite, pas de CORS
      proxy: {
        '/api': {
          target: env.VITE_API_PROXY ?? 'https://localhost',
          secure: false,
        },
      },
    },
  }
})
