import { useState } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'

function IconoUsuario() {
  return (
    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor"
         strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
      <circle cx="12" cy="7" r="4" />
    </svg>
  )
}

export default function Register() {
  const { register, user, cargando } = useAuth()
  const navigate = useNavigate()
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [confirmacion, setConfirmacion] = useState('')
  const [verPass, setVerPass] = useState(false)
  const [error, setError] = useState(null)
  const [enviando, setEnviando] = useState(false)

  // Si ya hay sesion, cada quien a su lugar.
  if (!cargando && user) return <Navigate to={user.acceso_panel ? '/panel' : '/'} replace />

  async function onSubmit(e) {
    e.preventDefault()
    setError(null)

    if (password !== confirmacion) {
      setError('Las contraseñas no coinciden.')
      return
    }

    setEnviando(true)
    try {
      await register({
        name,
        email,
        password,
        password_confirmation: confirmacion,
      })
      // El registro deja la sesion iniciada; el huesped va al portal.
      navigate('/')
    } catch (err) {
      const data = err.response?.data
      const primerError = data?.errors ? Object.values(data.errors)[0]?.[0] : null
      setError(primerError || data?.message || 'No se pudo crear la cuenta.')
    } finally {
      setEnviando(false)
    }
  }

  return (
    <div className="login">
      <form className="login__card" onSubmit={onSubmit}>
        <div className="login__mark"><IconoUsuario /></div>
        <span className="brand brand--dark">
          hotel<span className="brand__accent">link</span>
        </span>
        <h1>Crear cuenta</h1>
        <p className="login__sub">Regístrate para reservar en HotelLink</p>

        {error && <div className="login__error">{error}</div>}

        <label className="login__field">
          Nombre completo
          <input
            type="text"
            value={name}
            onChange={(e) => setName(e.target.value)}
            required
            autoFocus
            autoComplete="name"
            placeholder="Tu nombre"
          />
        </label>

        <label className="login__field">
          Correo electrónico
          <input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
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
              minLength={8}
              autoComplete="new-password"
              placeholder="Mínimo 8 caracteres"
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

        <label className="login__field">
          Confirmar contraseña
          <input
            type={verPass ? 'text' : 'password'}
            value={confirmacion}
            onChange={(e) => setConfirmacion(e.target.value)}
            required
            minLength={8}
            autoComplete="new-password"
            placeholder="Repite la contraseña"
          />
        </label>

        <button className="btn btn--block" disabled={enviando}>
          {enviando ? 'Creando…' : 'Crear cuenta'}
        </button>

        <p className="login__pie">
          ¿Ya tienes cuenta?{' '}
          <Link className="login__link" to="/login">Inicia sesión</Link>
        </p>
      </form>
    </div>
  )
}
