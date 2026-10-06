import { useState } from 'react'
import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import { MENU } from './menu'

export default function AdminLayout() {
  const { user, logout, can } = useAuth()
  const navigate = useNavigate()
  const [abierto, setAbierto] = useState(false)

  async function onLogout() {
    await logout()
    navigate('/login')
  }

  const items = MENU.filter((item) => can(item.permiso))

  return (
    <div className={`admin ${abierto ? 'admin--menu-abierto' : ''}`}>
      <aside className="admin__sidebar">
        <div className="admin__brand">
          hotel<span className="brand__accent">link</span>
        </div>
        <nav className="admin__nav">
          {items.map((item) => (
            <NavLink
              key={item.path}
              to={item.path}
              end={item.end}
              className={({ isActive }) => `admin__link ${isActive ? 'is-active' : ''}`}
              onClick={() => setAbierto(false)}
            >
              <span className="admin__icon">{item.icon}</span>
              {item.label}
            </NavLink>
          ))}
        </nav>
      </aside>

      <div className="admin__main">
        <header className="admin__topbar">
          <button className="admin__burger" onClick={() => setAbierto((v) => !v)}>
            ☰
          </button>
          <div className="admin__user">
            <span>{user?.name}</span>
            <small>{user?.roles?.join(', ')}</small>
          </div>
          <button className="btn btn--ghost" onClick={onLogout}>
            Salir
          </button>
        </header>

        <main className="admin__content">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
