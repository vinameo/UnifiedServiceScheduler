import react from '@vitejs/plugin-react'
import { defineConfig } from 'vitest/config'

// The dev server sends API calls to nginx (docker compose), so cookies stay same-site.
const backend = process.env.VITE_BACKEND_URL ?? 'http://localhost:8088'

export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    proxy: {
      '/api': backend,
      '/sanctum': backend,
      '/health': backend,
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: './src/test/setup.ts',
    css: false,
  },
})
