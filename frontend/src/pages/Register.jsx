import { useState } from 'react'
import { Link, Navigate, useNavigate, useSearchParams } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import { destinoSeguro } from '../auth/ProtectedRoute'

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
  const [params] = useSearchParams()
  const volver = destinoSeguro(params.get('volver'))

  const [form, setForm] = useState({
    nombres: '',
    apellidos: '',
    email: '',
    telefono: '',
    password: '',
    password_confirmation: '',
    consentimiento: false,
  })
  const [verPass, setVerPass] = useState(false)
  const [errores, setErrores] = useState({})
  const [error, setError] = useState(null)
  const [enviando, setEnviando] = useState(false)

  // Si ya hay sesion, cada quien a su lugar.
  if (!cargando && user) return <Navigate to={volver || (user.acceso_panel ? '/panel' : '/mi-cuenta')} replace />

  function set(campo, valor) {
    setForm((f) => ({ ...f, [campo]: valor }))
  }

  async function onSubmit(e) {
    e.preventDefault()
    setError(null)
    setErrores({})

    if (form.password !== form.password_confirmation) {
      setErrores({ password_confirmation: ['Las contraseñas no coinciden.'] })
      return
    }

    setEnviando(true)
    try {
      await register(form)
      // El registro deja la sesion iniciada; el correo de verificacion ya va en camino.
      navigate(volver || '/mi-cuenta?bienvenida=1')
    } catch (err) {
      const data = err.response?.data
      if (data?.errors) {
        setErrores(data.errors)
      } else {
        setError(data?.message || 'No se pudo crear la cuenta.')
      }
    } finally {
      setEnviando(false)
    }
  }

  const campoError = (campo) => errores[campo] && <small className="login__campo-error">{errores[campo][0]}</small>

  return (
    <div className="login">
      <form className="login__card login__card--ancha" onSubmit={onSubmit} noValidate>
        <div className="login__mark"><IconoUsuario /></div>
        <span className="brand brand--dark">
          hotel<span className="brand__accent">link</span>
        </span>
        <h1>Crear cuenta</h1>
        <p className="login__sub">Regístrate para reservar en HotelLink</p>

        {error && <div className="login__error">{error}</div>}

        <div className="login__fila">
          <label className="login__field">
            Nombres
            <input value={form.nombres} onChange={(e) => set('nombres', e.target.value)} required autoFocus autoComplete="given-name" />
            {campoError('nombres')}
          </label>
          <label className="login__field">
            Apellidos
            <input value={form.apellidos} onChange={(e) => set('apellidos', e.target.value)} required autoComplete="family-name" />
            {campoError('apellidos')}
          </label>
        </div>

        <label className="login__field">
          Correo electrónico
          <input
            type="email"
            value={form.email}
            onChange={(e) => set('email', e.target.value)}
            required
            autoComplete="email"
            placeholder="nombre@ejemplo.com"
          />
          {campoError('email')}
        </label>

        <label className="login__field">
          Teléfono
          <input
            type="tel"
            value={form.telefono}
            onChange={(e) => set('telefono', e.target.value)}
            required
            autoComplete="tel"
            placeholder="7000-0000"
          />
          {campoError('telefono')}
        </label>

        <label className="login__field">
          Contraseña
          <div className="login__pass">
            <input
              type={verPass ? 'text' : 'password'}
              value={form.password}
              onChange={(e) => set('password', e.target.value)}
              required
              minLength={8}
              autoComplete="new-password"
              placeholder="Mínimo 8 caracteres"
            />
            <button type="button" className="login__toggle" onClick={() => setVerPass((v) => !v)}>
              {verPass ? 'Ocultar' : 'Mostrar'}
            </button>
          </div>
          {campoError('password')}
        </label>

        <label className="login__field">
          Confirmar contraseña
          <input
            type={verPass ? 'text' : 'password'}
            value={form.password_confirmation}
            onChange={(e) => set('password_confirmation', e.target.value)}
            required
            minLength={8}
            autoComplete="new-password"
            placeholder="Repite la contraseña"
          />
          {campoError('password_confirmation')}
        </label>

        <label className="login__check login__check--bloque">
          <input
            type="checkbox"
            checked={form.consentimiento}
            onChange={(e) => set('consentimiento', e.target.checked)}
          />
          <span>
            Leí y acepto el{' '}
            <Link className="login__link" to="/privacidad" target="_blank" rel="noopener">aviso de privacidad</Link>
            , y autorizo el uso de mis datos para gestionar mis reservas.
          </span>
        </label>
        {campoError('consentimiento')}

        <button className="btn btn--block" disabled={enviando}>
          {enviando ? 'Creando…' : 'Crear cuenta'}
        </button>

        <p className="login__pie">
          ¿Ya tienes cuenta?{' '}
          <Link className="login__link" to={volver ? `/login?volver=${encodeURIComponent(volver)}` : '/login'}>Inicia sesión</Link>
        </p>
      </form>
    </div>
  )
}
