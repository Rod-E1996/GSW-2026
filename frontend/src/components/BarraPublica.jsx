import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'

// Barra superior de las paginas publicas y de Mi cuenta.
export default function BarraPublica() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  async function salir() {
    await logout()
    navigate('/')
  }

  return (
    <header className="barra-publica">
      <Link to="/" className="brand">
        hotel<span className="brand__accent">link</span>
      </Link>
      <nav className="barra-publica__nav">
        {user?.acceso_panel && <Link to="/panel" className="barra-publica__login">Panel</Link>}
        <Link to="/mi-cuenta" className="barra-publica__login">Mi cuenta</Link>
        {user && (
          <button type="button" className="barra-publica__salir" onClick={salir}>
            Salir
          </button>
        )}
      </nav>
    </header>
  )
}
