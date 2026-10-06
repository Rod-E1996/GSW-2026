import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// El dev server corre dentro de un contenedor Node.
// Proxy interno: /api y /storage van al contenedor "app" (Laravel) por la red de Docker,
// asi el navegador solo habla con el front y no hay problemas de CORS.
export default defineConfig({
  plugins: [react()],
  server: {
    host: true,
    port: 5173,
    // El bind-mount en Windows/Docker necesita polling para que el HMR detecte cambios
    watch: { usePolling: true },
    proxy: {
      '/api': { target: 'http://app', changeOrigin: true },
      '/storage': { target: 'http://app', changeOrigin: true },
    },
  },
})
