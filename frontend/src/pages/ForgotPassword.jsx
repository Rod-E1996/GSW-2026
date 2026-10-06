import { useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api'

function IconoSobre() {
  return (
    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor"
         strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <rect x="3" y="5" width="18" height="14" rx="2" />
      <path d="m3 7 9 6 9-6" />
    </svg>
  )
}

export default function ForgotPassword() {
  const [email, setEmail] = useState('')
  const [enviando, setEnviando] = useState(false)
  const [ok, setOk] = useState(false)
  const [error, setError] = useState(null)

  async function onSubmit(e) {
    e.preventDefault()
    setError(null)
    setEnviando(true)
    try {
      await api.post('/forgot-password', { email })
      setOk(true)
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudo enviar el enlace. Inténtalo de nuevo.')
    } finally {
      setEnviando(false)
    }
  }

  return (
    <div className="login">
      <form className="login__card" onSubmit={onSubmit}>
        <div className="login__mark"><IconoSobre /></div>
        <span className="brand brand--dark">
          hotel<span className="brand__accent">link</span>
        </span>
        <h1>Recuperar contraseña</h1>
        <p className="login__sub">Te enviaremos un enlace para restablecerla</p>

        {ok ? (
          <>
            <div className="login__ok">
              Si el correo está registrado, recibirás un enlace para restablecer tu
              contraseña. Revisa tu bandeja de entrada (y la carpeta de spam).
            </div>
            <Link className="btn btn--block btn--ghost" to="/login">
              Volver a iniciar sesión
            </Link>
          </>
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
                autoFocus
                autoComplete="email"
                placeholder="nombre@ejemplo.com"
              />
            </label>

            <button className="btn btn--block" disabled={enviando}>
              {enviando ? 'Enviando…' : 'Enviar enlace'}
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
