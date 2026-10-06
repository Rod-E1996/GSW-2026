import axios from 'axios'

// baseURL relativa: Vite hace proxy de /api al contenedor de Laravel (sin CORS).
export const api = axios.create({
  baseURL: '/api',
  headers: { Accept: 'application/json' },
})
