import { useEffect, useState } from 'react'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'

const NOMBRE_IDIOMA = { es: 'Español', en: 'English' }
const CLAVE_VACIA = { current_password: '', password: '', password_confirmation: '' }

function fecha(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('es-SV')
}

export default function Perfil() {
  const { user, can, actualizarUsuario } = useAuth()

  const [datos, setDatos] = useState(null)
  const [form, setForm] = useState({ name: '', email: '', phone: '', idioma: 'es', current_password: '' })
  const [clave, setClave] = useState(CLAVE_VACIA)
  const [erroresDatos, setErroresDatos] = useState({})
  const [erroresClave, setErroresClave] = useState({})
  const [guardando, setGuardando] = useState(null)
  const [mensaje, setMensaje] = useState(null)
  const [error, setError] = useState(null)

  function cargar() {
    return api.get('/perfil').then((res) => setDatos(res.data))
  }

  useEffect(() => {
    cargar()
      .catch(() => setError('No se pudo cargar el perfil.'))
  }, [])

  useEffect(() => {
    if (!user) return
    setForm((f) => ({
      ...f,
      name: user.name,
      email: user.email,
      phone: user.phone || '',
      idioma: user.idioma || 'es',
    }))
  }, [user])

  function set(campo, valor) {
    setForm((f) => ({ ...f, [campo]: valor }))
  }

  function setC(campo, valor) {
    setClave((c) => ({ ...c, [campo]: valor }))
  }

  async function guardarDatos(e) {
    e.preventDefault()
    setGuardando('datos')
    setErroresDatos({})
    setMensaje(null)
    setError(null)
    try {
      const res = await api.put('/perfil', form)
      actualizarUsuario(res.data.user)
      set('current_password', '')
      setMensaje(res.data.message)
    } catch (err) {
      if (err.response?.status === 422) {
        setErroresDatos(err.response.data.errors || {})
      } else {
        setError(err.response?.data?.message || 'No se pudo guardar.')
      }
    } finally {
      setGuardando(null)
    }
  }

  async function guardarClave(e) {
    e.preventDefault()
    setGuardando('clave')
    setErroresClave({})
    setMensaje(null)
    setError(null)
    try {
      const res = await api.put('/perfil/password', clave)
      setClave(CLAVE_VACIA)
      setMensaje(
        res.data.cerradas > 0
          ? `${res.data.message} Se cerraron tus otras sesiones (${res.data.cerradas}).`
          : res.data.message,
      )
      cargar()
    } catch (err) {
      if (err.response?.status === 422) {
        setErroresClave(err.response.data.errors || {})
      } else {
        setError(err.response?.data?.message || 'No se pudo cambiar la contraseña.')
      }
    } finally {
      setGuardando(null)
    }
  }

  async function cerrarSesion(s) {
    if (!window.confirm('¿Cerrar esta sesión?')) return
    setMensaje(null)
    setError(null)
    try {
      const res = await api.delete(`/perfil/sesiones/${s.id}`)
      setMensaje(res.data.message)
      cargar()
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudo cerrar la sesión.')
    }
  }

  if (!datos) {
    return error ? <div className="alerta alerta--error">{error}</div> : <p className="estado">Cargando…</p>
  }

  const correoCambiado = form.email !== user.email

  return (
    <div className="form-page">
      <h1 className="page-title">Mi perfil</h1>

      {mensaje && <div className="alerta alerta--ok">{mensaje}</div>}
      {error && <div className="alerta alerta--error">{error}</div>}

      <form className="form form--seccion" onSubmit={guardarDatos}>
        <h2 className="seccion-title">Mis datos</h2>
        <label>
          Nombre
          <input value={form.name} disabled={!can('perfil_edit')} onChange={(e) => set('name', e.target.value)} />
          {erroresDatos.name && <small className="campo-error">{erroresDatos.name[0]}</small>}
        </label>
        <div className="form-row">
          <label>
            Correo
            <input
              type="email"
              value={form.email}
              disabled={!can('perfil_edit')}
              onChange={(e) => set('email', e.target.value)}
            />
            {erroresDatos.email && <small className="campo-error">{erroresDatos.email[0]}</small>}
          </label>
          <label>
            Teléfono
            <input value={form.phone} disabled={!can('perfil_edit')} onChange={(e) => set('phone', e.target.value)} />
            {erroresDatos.phone && <small className="campo-error">{erroresDatos.phone[0]}</small>}
          </label>
        </div>
        <label>
          Idioma
          <select value={form.idioma} disabled={!can('perfil_edit')} onChange={(e) => set('idioma', e.target.value)}>
            {datos.idiomas.map((i) => (
              <option key={i} value={i}>{NOMBRE_IDIOMA[i] || i}</option>
            ))}
          </select>
          {erroresDatos.idioma && <small className="campo-error">{erroresDatos.idioma[0]}</small>}
        </label>
        {correoCambiado && (
          <label>
            Contraseña actual (necesaria para cambiar el correo)
            <input
              type="password"
              autoComplete="current-password"
              value={form.current_password}
              onChange={(e) => set('current_password', e.target.value)}
            />
            {erroresDatos.current_password && (
              <small className="campo-error">{erroresDatos.current_password[0]}</small>
            )}
          </label>
        )}
        {can('perfil_edit') && (
          <div className="form-acciones">
            <button className="btn" disabled={guardando === 'datos'}>
              {guardando === 'datos' ? 'Guardando…' : 'Guardar datos'}
            </button>
          </div>
        )}
      </form>

      {can('perfil_edit_password') && (
        <form className="form form--seccion" onSubmit={guardarClave}>
          <h2 className="seccion-title">Cambiar contraseña</h2>
          <label>
            Contraseña actual
            <input
              type="password"
              autoComplete="current-password"
              value={clave.current_password}
              onChange={(e) => setC('current_password', e.target.value)}
            />
            {erroresClave.current_password && (
              <small className="campo-error">{erroresClave.current_password[0]}</small>
            )}
          </label>
          <div className="form-row">
            <label>
              Contraseña nueva
              <input
                type="password"
                autoComplete="new-password"
                value={clave.password}
                onChange={(e) => setC('password', e.target.value)}
              />
              {erroresClave.password && <small className="campo-error">{erroresClave.password[0]}</small>}
            </label>
            <label>
              Repite la contraseña nueva
              <input
                type="password"
                autoComplete="new-password"
                value={clave.password_confirmation}
                onChange={(e) => setC('password_confirmation', e.target.value)}
              />
            </label>
          </div>
          <div className="form-acciones">
            <button className="btn" disabled={guardando === 'clave'}>
              {guardando === 'clave' ? 'Guardando…' : 'Cambiar contraseña'}
            </button>
          </div>
        </form>
      )}

      <h2 className="seccion-title">Sesiones activas</h2>
      <div className="tabla-wrap">
        <table className="tabla">
          <thead>
            <tr>
              <th>Dispositivo</th>
              <th>IP</th>
              <th>Última actividad</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {datos.sesiones.map((s) => (
              <tr key={s.id}>
                <td>{s.device_type_es} · {s.browser || 'Desconocido'}</td>
                <td>{s.ip_address}</td>
                <td>{fecha(s.ultima_actividad)}</td>
                <td className="tabla__acciones">
                  {s.es_actual ? (
                    <span className="badge badge--ok">Esta sesión</span>
                  ) : (
                    can('perfil_cerrar_session') && (
                      <button className="btn btn--sm btn--danger" onClick={() => cerrarSesion(s)}>
                        Cerrar
                      </button>
                    )
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <h2 className="seccion-title">Inicios de sesión recientes</h2>
      <div className="tabla-wrap">
        <table className="tabla">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Dispositivo</th>
              <th>IP</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody>
            {datos.historial.length === 0 && (
              <tr><td colSpan="4" className="tabla__vacio">Sin historial.</td></tr>
            )}
            {datos.historial.map((h) => (
              <tr key={h.id}>
                <td>{fecha(h.fecha)}</td>
                <td>{h.device_type_es} · {h.browser || 'Desconocido'}</td>
                <td>{h.ip_address}</td>
                <td>
                  <span className={`badge ${h.estado === 1 ? 'badge--ok' : 'badge--danger'}`}>
                    {h.estado === 1 ? 'Activa' : 'Cerrada'}
                  </span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
