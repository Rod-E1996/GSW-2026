import { createContext, useContext, useEffect, useState } from 'react'
import { api } from '../api'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [cargando, setCargando] = useState(true)

  // Al cargar la app, si hay token guardado rehidrata la sesion con /me.
  useEffect(() => {
    const token = localStorage.getItem('token')
    if (!token) {
      setCargando(false)
      return
    }
    api
      .get('/me')
      .then((res) => setUser(res.data.user))
      .catch(() => localStorage.removeItem('token'))
      .finally(() => setCargando(false))
  }, [])

  async function login(email, password) {
    const res = await api.post('/login', { email, password })
    localStorage.setItem('token', res.data.token)
    setUser(res.data.user)
  }

  async function logout() {
    try {
      await api.post('/logout')
    } catch {
      // aunque falle en el server, cerramos localmente
    }
    localStorage.removeItem('token')
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
