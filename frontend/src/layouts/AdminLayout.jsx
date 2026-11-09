import { useEffect, useState } from 'react'
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import Icono from '../components/Icono'
import { MENU, SECCIONES_EXTRA } from './menu'

// Iniciales para el avatar: "Mariela Fuentes" -> "MF"
function iniciales(nombre = '') {
  return nombre
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0].toUpperCase())
    .join('')
}

export default function AdminLayout() {
  const { user, logout, can } = useAuth()
  const navigate = useNavigate()
  const { pathname } = useLocation()
  const [abierto, setAbierto] = useState(false)

  // Al navegar en movil el menu se cierra solo
  useEffect(() => setAbierto(false), [pathname])

  async function onLogout() {
    await logout()
    navigate('/login')
  }

  const grupos = MENU.map((g) => ({ ...g, items: g.items.filter((item) => can(item.permiso)) }))
    .filter((g) => g.items.length > 0)

  // Nombre de la seccion actual para la barra superior: la ruta mas larga que coincida
  const seccion = [...grupos.flatMap((g) => g.items), ...SECCIONES_EXTRA]
    .filter((item) => pathname === item.path || (!item.end && pathname.startsWith(item.path + '/')))
    .sort((a, b) => b.path.length - a.path.length)[0]?.label

  return (
    <div className={`admin ${abierto ? 'admin--menu-abierto' : ''}`}>
      <aside className="admin__sidebar">
        <div className="admin__brand">
          <Link to="/panel" className="admin__brand-link">
            <span className="admin__logo"><Icono nombre="ola" size={20} /></span>
            <span>hotel<span className="brand__accent">link</span></span>
          </Link>
          <button type="button" className="admin__cerrar" onClick={() => setAbierto(false)} aria-label="Cerrar menú">
            <Icono nombre="cerrar" />
          </button>
        </div>

        <nav className="admin__nav">
          {grupos.map((g) => (
            <div key={g.grupo} className="admin__grupo">
              <span className="admin__grupo-titulo">{g.grupo}</span>
              {g.items.map((item) => (
                <NavLink
                  key={item.path}
                  to={item.path}
                  end={item.end}
                  className={({ isActive }) => `admin__link ${isActive ? 'is-active' : ''}`}
                >
                  <Icono nombre={item.icon} className="admin__icon" />
                  {item.label}
                </NavLink>
              ))}
            </div>
          ))}
        </nav>

        <div className="admin__pie">
          <Link to="/" className="admin__link admin__link--sutil" target="_blank" rel="noreferrer">
            <Icono nombre="externo" className="admin__icon" />
            Ver portal de reservas
          </Link>
        </div>
      </aside>

      <div className="admin__overlay" onClick={() => setAbierto(false)} aria-hidden="true" />

      <div className="admin__main">
        <header className="admin__topbar">
          <button className="admin__burger" onClick={() => setAbierto(true)} aria-label="Abrir menú">
            <Icono nombre="menu" size={22} />
          </button>
          <span className="admin__seccion">{seccion || 'Panel'}</span>

          <div className="admin__cuenta">
            {can('perfil_show') ? (
              <Link to="/panel/perfil" className="admin__user" title="Mi perfil">
                <span className="avatar">{iniciales(user?.name)}</span>
                <span className="admin__user-datos">
                  <span>{user?.name}</span>
                  <small>{user?.roles?.join(', ')}</small>
                </span>
              </Link>
            ) : (
              <span className="admin__user">
                <span className="avatar">{iniciales(user?.name)}</span>
                <span className="admin__user-datos">
                  <span>{user?.name}</span>
                  <small>{user?.roles?.join(', ')}</small>
                </span>
              </span>
            )}
            <button className="btn btn--ghost btn--icono" onClick={onLogout} title="Cerrar sesión" aria-label="Cerrar sesión">
              <Icono nombre="salir" />
            </button>
          </div>
        </header>

        <main className="admin__content">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
