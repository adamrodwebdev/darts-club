import { fileURLToPath, URL } from 'node:url'

import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  // VITE_API_PROXY peut venir de l'environnement ou d'un fichier .env.*.local
  const env = loadEnv(mode, process.cwd(), '')

  return {
    plugins: [
      vue(),
      vueDevTools(),
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
