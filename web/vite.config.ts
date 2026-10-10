import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

// https://vite.dev/config/
export default defineConfig({
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
        target: process.env.VITE_API_PROXY ?? 'https://localhost',
        secure: false,
      },
    },
  },
})
