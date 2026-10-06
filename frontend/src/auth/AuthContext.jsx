import { createContext, useContext, useEffect, useState } from 'react'
import { api } from '../api'
import { getToken, setToken, clearToken } from './token'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [cargando, setCargando] = useState(true)

  // Al cargar la app, si hay token guardado rehidrata la sesion con /me.
  useEffect(() => {
    const token = getToken()
    if (!token) {
      setCargando(false)
      return
    }
    api
      .get('/me')
      .then((res) => setUser(res.data.user))
      .catch(() => clearToken())
      .finally(() => setCargando(false))
  }, [])

  async function login(email, password, recordar = true) {
    const res = await api.post('/login', { email, password })
    setToken(res.data.token, recordar)
    setUser(res.data.user)
  }

  async function logout() {
    try {
      await api.post('/logout')
    } catch {
      // aunque falle en el server, cerramos localmente
    }
    clearToken()
    setUser(null)
  }

  // Comprueba si el usuario tiene un permiso (para el menu y las vistas).
  function can(permiso) {
    return user?.permissions?.includes(permiso) ?? false
  }

  return (
    <AuthContext.Provider value={{ user, cargando, login, logout, can }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  return useContext(AuthContext)
}
