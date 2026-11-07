import { NavLink, Outlet, useSearchParams } from 'react-router-dom'
import { useAuth } from '../../auth/AuthContext'
import AvisoVerificacion from '../../components/AvisoVerificacion'
import BarraPublica from '../../components/BarraPublica'

// Area privada del huesped: Mis reservas y Mi perfil.
export default function MiCuenta() {
  const { user } = useAuth()
  const [params] = useSearchParams()

  return (
    <div className="app">
      <BarraPublica />
      <main className="cuenta">
        <h1 className="page-title">Hola, {user?.name}</h1>
        {params.get('bienvenida') && (
          <div className="alerta alerta--ok">Tu cuenta quedó creada. Te enviamos un correo para verificar tu dirección.</div>
        )}
        <AvisoVerificacion />
        <nav className="cuenta__pestanas">
          <NavLink to="/mi-cuenta" end>Mis reservas</NavLink>
          <NavLink to="/mi-cuenta/perfil">Mi perfil</NavLink>
        </nav>
        <Outlet />
      </main>
    </div>
  )
}
