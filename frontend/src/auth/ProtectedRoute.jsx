import { Navigate, useLocation } from 'react-router-dom'
import { useAuth } from './AuthContext'

// Protege las rutas privadas: si no hay sesion, manda al login y luego vuelve aqui.
// `soloStaff`: solo usuarios internos (acceso_panel); los huespedes van al portal.
// `permiso`: exige ese permiso puntual para entrar.
export default function ProtectedRoute({ children, permiso, soloStaff }) {
  const { user, cargando, can } = useAuth()
  const location = useLocation()

  if (cargando) {
    return <div className="panel-cargando">Cargando…</div>
  }

  if (!user) {
    const volver = encodeURIComponent(location.pathname + location.search)
    return <Navigate to={`/login?volver=${volver}`} replace />
  }

  if (soloStaff && !user.acceso_panel) {
    return <Navigate to="/" replace />
  }

  if (permiso && !can(permiso)) {
    return <Navigate to="/panel" replace />
  }

  return children
}

// Solo acepta rutas internas, para que nadie arme un enlace que lleve a otro sitio.
export function destinoSeguro(volver) {
  return volver && volver.startsWith('/') && !volver.startsWith('//') ? volver : null
}
