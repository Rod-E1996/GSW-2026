import { useState } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'

// Icono de candado (sin dependencias de fuentes de iconos).
function IconoCandado() {
  return (
    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor"
         strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <rect x="3" y="11" width="18" height="11" rx="2" />
      <path d="M7 11V7a5 5 0 0 1 10 0v4" />
    </svg>
  )
}

export default function Login() {
  const { login, user, cargando } = useAuth()
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [verPass, setVerPass] = useState(false)
  const [recordar, setRecordar] = useState(true)
  const [error, setError] = useState(null)
  const [enviando, setEnviando] = useState(false)

  if (!cargando && user) return <Navigate to="/panel" replace />

  async function onSubmit(e) {
    e.preventDefault()
    setError(null)
    setEnviando(true)
    try {
      await login(email, password, recordar)
      navigate('/panel')
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudo iniciar sesión.')
    } finally {
      setEnviando(false)
    }
  }

  return (
    <div className="login">
      <form className="login__card" onSubmit={onSubmit}>
        <div className="login__mark"><IconoCandado /></div>
        <span className="brand brand--dark">
          hotel<span className="brand__accent">link</span>
        </span>
        <h1>Panel de administración</h1>
        <p className="login__sub">Ingresa tus credenciales para continuar</p>

        {error && <div className="login__error">{error}</div>}

        <label className="login__field">
          Correo electrónico
          <input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
            autoFocus
            autoComplete="email"
            placeholder="nombre@ejemplo.com"
          />
        </label>

        <label className="login__field">
          Contraseña
          <div className="login__pass">
            <input
              type={verPass ? 'text' : 'password'}
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              autoComplete="current-password"
              placeholder="••••••••"
            />
            <button
              type="button"
              className="login__toggle"
              onClick={() => setVerPass((v) => !v)}
            >
              {verPass ? 'Ocultar' : 'Mostrar'}
            </button>
          </div>
        </label>

        <div className="login__meta">
          <label className="login__check">
            <input
              type="checkbox"
              checked={recordar}
              onChange={(e) => setRecordar(e.target.checked)}
            />
            Recordarme
          </label>
          <Link className="login__link" to="/recuperar-contrasena">
            ¿Olvidaste tu contraseña?
          </Link>
        </div>

        <button className="btn btn--block" disabled={enviando}>
          {enviando ? 'Entrando…' : 'Iniciar sesión'}
        </button>
      </form>
    </div>
  )
}
