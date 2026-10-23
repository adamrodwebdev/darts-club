import { readFileSync } from 'node:fs'
import { fileURLToPath, URL } from 'node:url'

import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import VueI18nPlugin from '@intlify/unplugin-vue-i18n/vite'
import vueDevTools from 'vite-plugin-vue-devtools'
import { deferredBoot } from './build/deferredBoot.ts'
import { localizedIndex } from './build/localizedIndex.ts'
import { songStructuredData } from './build/songStructuredData.ts'

const locales = fileURLToPath(new URL('./src/i18n/locales', import.meta.url))

/**
 * CSP de la production (docker/frankenphp/Caddyfile), appliquée aussi à l'aperçu : les tests
 * de bout en bout et Lighthouse tournent sous la même politique, un bloc en ligne refusé
 * casse les tests plutôt que la production. Sans upgrade-insecure-requests (aperçu en http).
 */
function productionCsp(): string {
  const caddyfile = readFileSync(fileURLToPath(new URL('../docker/frankenphp/Caddyfile', import.meta.url)), 'utf8')
  const policy = /Content-Security-Policy "([^"]+)"/.exec(caddyfile)?.[1]
  if (!policy) throw new Error('Caddyfile : Content-Security-Policy introuvable')
  return policy.replace(/;\s*upgrade-insecure-requests/, '')
}

// https://vite.dev/config/
export default defineConfig(({ mode, isPreview }) => {
  // VITE_API_PROXY peut venir de l'environnement ou d'un fichier .env.*.local
  const env = loadEnv(mode, process.cwd(), '')

  return {
    plugins: [
      vue(),
      // Traductions précompilées : pas de compilateur de messages dans le bundle (ni eval, CSP stricte)
      VueI18nPlugin({ include: [`${locales}/**`] }),
      vueDevTools(),
      // Avant localizedIndex, qui reprend index.html pour les autres langues
      deferredBoot({
        titleCardStyle: fileURLToPath(new URL('./build/inline/title-card.css', import.meta.url)),
        bootScript: fileURLToPath(new URL('./build/inline/boot.js', import.meta.url)),
      }),
      localizedIndex({
        localesDir: locales,
        source: 'fr',
        pages: { en: 'en' },
        siteUrl: env.VITE_SITE_URL,
        themeScript: fileURLToPath(new URL('./public/theme-init.js', import.meta.url)),
        structuredData: (url) => [songStructuredData(url)],
      }),
    ],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
      },
    },
    // Aperçu seulement : le build (y compris dans l'image Docker, sans le Caddyfile) n'en dépend pas
    preview: isPreview ? { headers: { 'Content-Security-Policy': productionCsp() } } : {},
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
