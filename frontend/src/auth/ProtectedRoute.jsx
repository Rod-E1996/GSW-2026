import { Navigate } from 'react-router-dom'
import { useAuth } from './AuthContext'

// Protege las rutas del panel: si no hay sesion, manda al login.
// Si se pasa `permiso`, exige ese permiso para entrar.
export default function ProtectedRoute({ children, permiso }) {
  const { user, cargando, can } = useAuth()

  if (cargando) {
    return <div className="panel-cargando">Cargando…</div>
  }

  if (!user) {
    return <Navigate to="/login" replace />
  }

  if (permiso && !can(permiso)) {
    return <Navigate to="/panel" replace />
  }

  return children
}
