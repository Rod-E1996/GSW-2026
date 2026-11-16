import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'

// Proxy: /api, /storage e /img van a Laravel, asi el navegador solo habla con el front y no hay problemas de CORS.
// Por defecto apunta al contenedor "back" (Docker). Fuera de Docker (Herd, XAMPP) se define
// API_PROXY_TARGET en frontend/.env.local, por ejemplo: API_PROXY_TARGET=http://gsw-2026.test
export default defineConfig(({ mode }) => {
  const target = loadEnv(mode, process.cwd(), '').API_PROXY_TARGET || 'http://back'

  return {
    plugins: [react()],
    server: {
      host: true,
      port: 5173,
      // El bind-mount en Windows/Docker necesita polling para que el HMR detecte cambios
      watch: { usePolling: true },
      proxy: {
        '/api': { target, changeOrigin: true },
        '/storage': { target, changeOrigin: true },
        '/img': { target, changeOrigin: true },
      },
    },
  }
})
