import { useEffect, useState } from 'react'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import Icono from '../../../components/Icono'
import { Avatar, EstadoVacio, FormSeccion } from '../../../components/ui'
import { Dispositivo, fechaHora, haceCuanto } from '../accesos/comun'
import '../../../styles/admin-accesos.css'

const NOMBRE_IDIOMA = { es: 'Español', en: 'English' }
const CLAVE_VACIA = { current_password: '', password: '', password_confirmation: '' }

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
  const [verHistorial, setVerHistorial] = useState(false)
  const [verSesiones, setVerSesiones] = useState(false)

  function cargar() {
    return api.get('/perfil').then((res) => setDatos(res.data))
  }

  useEffect(() => {
    cargar().catch(() => setError('No se pudo cargar el perfil.'))
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
    if (!window.confirm('¿Cerrar esta sesión? Tendrás que volver a entrar en ese dispositivo.')) return
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
    return error
      ? <div className="alerta alerta--error">{error}</div>
      : <div className="form-page"><div className="tarjeta-simple"><span className="esqueleto" /><br /><span className="esqueleto" /></div></div>
  }

  const correoCambiado = form.email !== user.email
  const puedeEditar = can('perfil_edit')
  const otras = datos.sesiones.filter((s) => !s.es_actual)
  const historial = verHistorial ? datos.historial : datos.historial.slice(0, 5)
  // La sesion actual siempre primero; las demas se pliegan si son muchas
  const ordenadas = [...datos.sesiones].sort((a, b) => Number(b.es_actual) - Number(a.es_actual))
  const sesiones = verSesiones ? ordenadas : ordenadas.slice(0, 6)

  return (
    <div className="form-page">
      <div className="ac-cabecera ac-cabecera--perfil">
        <Avatar nombre={user.name} size="lg" />
        <div className="ac-cabecera__datos">
          <h1 className="page-title">{user.name}</h1>
          <p>
            {user.email}
            {(user.roles || []).map((r) => <span key={r} className="ac-chip">{r}</span>)}
          </p>
        </div>
      </div>

      {mensaje && <div className="alerta alerta--ok">{mensaje}</div>}
      {error && <div className="alerta alerta--error">{error}</div>}

      <form className="form form--seccion" onSubmit={guardarDatos}>
        <FormSeccion titulo="Datos personales" descripcion="Tu nombre y teléfono son visibles para el resto del personal. El idioma cambia los correos que recibes.">
          <label>
            Nombre
            <input value={form.name} disabled={!puedeEditar} onChange={(e) => set('name', e.target.value)} />
            {erroresDatos.name && <small className="campo-error">{erroresDatos.name[0]}</small>}
          </label>
          <div className="form-row">
            <label>
              Correo
              <input type="email" value={form.email} disabled={!puedeEditar} onChange={(e) => set('email', e.target.value)} />
              {erroresDatos.email && <small className="campo-error">{erroresDatos.email[0]}</small>}
            </label>
            <label>
              Teléfono
              <input value={form.phone} disabled={!puedeEditar} onChange={(e) => set('phone', e.target.value)} placeholder="Opcional" />
              {erroresDatos.phone && <small className="campo-error">{erroresDatos.phone[0]}</small>}
            </label>
          </div>
          <label>
            Idioma
            <select value={form.idioma} disabled={!puedeEditar} onChange={(e) => set('idioma', e.target.value)}>
              {datos.idiomas.map((i) => (
                <option key={i} value={i}>{NOMBRE_IDIOMA[i] || i}</option>
              ))}
            </select>
            {erroresDatos.idioma && <small className="campo-error">{erroresDatos.idioma[0]}</small>}
          </label>
          {correoCambiado && (
            <label>
              Contraseña actual
              <input
                type="password"
                autoComplete="current-password"
                value={form.current_password}
                onChange={(e) => set('current_password', e.target.value)}
              />
              <small className="form-nota">Para cambiar el correo necesitamos confirmar que eres tú.</small>
              {erroresDatos.current_password && <small className="campo-error">{erroresDatos.current_password[0]}</small>}
            </label>
          )}
        </FormSeccion>
        {puedeEditar && (
          <div className="form-acciones">
            <button className="btn" disabled={guardando === 'datos'}>
              {guardando === 'datos' ? 'Guardando…' : 'Guardar datos'}
            </button>
          </div>
        )}
      </form>

      {can('perfil_edit_password') && (
        <form className="form form--seccion" onSubmit={guardarClave}>
          <FormSeccion titulo="Contraseña" descripcion="Al cambiarla se cierran tus sesiones en los demás dispositivos.">
            <label>
              Contraseña actual
              <input
                type="password"
                autoComplete="current-password"
                value={clave.current_password}
                onChange={(e) => setC('current_password', e.target.value)}
              />
              {erroresClave.current_password && <small className="campo-error">{erroresClave.current_password[0]}</small>}
            </label>
            <div className="form-row">
              <label>
                Contraseña nueva
                <input type="password" autoComplete="new-password" value={clave.password} onChange={(e) => setC('password', e.target.value)} />
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
          </FormSeccion>
          <div className="form-acciones">
            <button className="btn" disabled={guardando === 'clave' || !clave.current_password || !clave.password}>
              {guardando === 'clave' ? 'Guardando…' : 'Cambiar contraseña'}
            </button>
          </div>
        </form>
      )}

      <section className="form form--seccion">
        <FormSeccion
          titulo="Dónde tienes la sesión abierta"
          descripcion={otras.length ? 'Si no reconoces un dispositivo, cierra su sesión y cambia tu contraseña.' : 'Solo tienes abierta la sesión de este dispositivo.'}
        >
          <ul className="ac-lista">
            {sesiones.map((s) => (
              <li key={s.id}>
                <Dispositivo s={s} />
                <div className="ac-lista__meta">
                  <span>{s.ip_address}</span>
                  <small title={fechaHora(s.ultima_actividad)}>Activa {haceCuanto(s.ultima_actividad)}</small>
                </div>
                {s.es_actual ? (
                  <span className="badge badge--info">Esta sesión</span>
                ) : (
                  can('perfil_cerrar_session') && (
                    <button className="btn btn--sm btn--danger" onClick={() => cerrarSesion(s)}>Cerrar</button>
                  )
                )}
              </li>
            ))}
          </ul>
          {ordenadas.length > 6 && (
            <button type="button" className="btn btn--ghost btn--sm ac-ver-mas" onClick={() => setVerSesiones((v) => !v)}>
              <Icono nombre={verSesiones ? 'volver' : 'flecha'} size={14} />
              {verSesiones ? 'Ver menos' : `Ver las ${ordenadas.length} sesiones`}
            </button>
          )}
        </FormSeccion>

        <FormSeccion titulo="Inicios de sesión recientes" descripcion="Cada vez que entras queda registrado el dispositivo y la dirección IP.">
          {datos.historial.length === 0 ? (
            <EstadoVacio icono="reloj" titulo="Sin historial" />
          ) : (
            <>
              <ol className="ac-linea">
                {historial.map((h) => (
                  <li key={h.id} className={h.estado === 1 ? 'is-activa' : ''}>
                    <div>
                      <strong>{h.browser || 'Navegador desconocido'}</strong>
                      <small>{[h.device_type_es, h.platform].filter((x) => x && x !== 'Desconocido').join(' · ')} · {h.ip_address}</small>
                    </div>
                    <div className="ac-linea__fecha">
                      <span>{fechaHora(h.fecha)}</span>
                      <span className={`badge ${h.estado === 1 ? 'badge--ok' : ''}`}>{h.estado === 1 ? 'Abierta' : 'Cerrada'}</span>
                    </div>
                  </li>
                ))}
              </ol>
              {datos.historial.length > 5 && (
                <button type="button" className="btn btn--ghost btn--sm ac-ver-mas" onClick={() => setVerHistorial((v) => !v)}>
                  <Icono nombre={verHistorial ? 'volver' : 'flecha'} size={14} />
                  {verHistorial ? 'Ver menos' : `Ver los ${datos.historial.length}`}
                </button>
              )}
            </>
          )}
        </FormSeccion>
      </section>
    </div>
  )
}
