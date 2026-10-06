import { Navigate } from 'react-router-dom'
import { useAuth } from './AuthContext'

// Protege las rutas del panel: si no hay sesion, manda al login.
// `soloStaff`: solo usuarios internos (acceso_panel); los huespedes van al portal.
// `permiso`: exige ese permiso puntual para entrar.
export default function ProtectedRoute({ children, permiso, soloStaff }) {
  const { user, cargando, can } = useAuth()

  if (cargando) {
    return <div className="panel-cargando">Cargando…</div>
  }

  if (!user) {
    return <Navigate to="/login" replace />
  }

  if (soloStaff && !user.acceso_panel) {
    return <Navigate to="/" replace />
  }

  if (permiso && !can(permiso)) {
    return <Navigate to="/panel" replace />
  }

  return children
}
