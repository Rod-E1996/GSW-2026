import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { api } from '../api'

function IconoCandado() {
  return (
    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor"
         strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <rect x="3" y="11" width="18" height="11" rx="2" />
      <path d="M7 11V7a5 5 0 0 1 10 0v4" />
    </svg>
  )
}

export default function ResetPassword() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const token = params.get('token') || ''
  const emailQuery = params.get('email') || ''

  const [email, setEmail] = useState(emailQuery)
  const [password, setPassword] = useState('')
  const [confirmacion, setConfirmacion] = useState('')
  const [verPass, setVerPass] = useState(false)
  const [enviando, setEnviando] = useState(false)
  const [error, setError] = useState(null)
  const [ok, setOk] = useState(false)

  async function onSubmit(e) {
    e.preventDefault()
    setError(null)

    if (password !== confirmacion) {
      setError('Las contraseñas no coinciden.')
      return
    }

    setEnviando(true)
    try {
      await api.post('/reset-password', {
        token,
        email,
        password,
        password_confirmation: confirmacion,
      })
      setOk(true)
      setTimeout(() => navigate('/login'), 2000)
    } catch (err) {
      const data = err.response?.data
      const primerError = data?.errors ? Object.values(data.errors)[0]?.[0] : null
      setError(primerError || data?.message || 'No se pudo restablecer la contraseña.')
    } finally {
      setEnviando(false)
    }
  }

  // Enlace incompleto: sin token no se puede continuar.
  if (!token) {
    return (
      <div className="login">
        <div className="login__card">
          <h1>Enlace no válido</h1>
          <p className="login__sub">El enlace está incompleto o ha expirado.</p>
          <Link className="btn btn--block" to="/recuperar-contrasena">
            Solicitar uno nuevo
          </Link>
        </div>
      </div>
    )
  }

  return (
    <div className="login">
      <form className="login__card" onSubmit={onSubmit}>
        <div className="login__mark"><IconoCandado /></div>
        <span className="brand brand--dark">
          hotel<span className="brand__accent">link</span>
        </span>
        <h1>Nueva contraseña</h1>
        <p className="login__sub">Elige una contraseña para tu cuenta</p>

        {ok ? (
          <div className="login__ok">
            Tu contraseña fue restablecida. Te llevaremos al inicio de sesión…
          </div>
        ) : (
          <>
            {error && <div className="login__error">{error}</div>}

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
              Nueva contraseña
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
              {enviando ? 'Guardando…' : 'Restablecer contraseña'}
            </button>

            <Link className="login__link login__link--center" to="/login">
              ← Volver a iniciar sesión
            </Link>
          </>
        )}
      </form>
    </div>
  )
}
