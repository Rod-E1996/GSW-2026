import axios from 'axios'
import { getToken, clearToken } from './auth/token'

// baseURL relativa: Vite hace proxy de /api al contenedor del back (sin CORS).
export const api = axios.create({
  baseURL: '/api',
  headers: { Accept: 'application/json' },
})

// Adjunta el token de Sanctum (si existe) en cada peticion.
api.interceptors.request.use((config) => {
  const token = getToken()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Si el token caduca o es invalido (401), limpia la sesion local.
api.interceptors.response.use(
  (res) => res,
  (error) => {
    if (error.response?.status === 401) {
      clearToken()
    }
    return Promise.reject(error)
  },
)
