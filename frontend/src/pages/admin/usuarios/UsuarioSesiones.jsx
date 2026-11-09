import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import Icono from '../../../components/Icono'
import { Avatar, EstadoVacio, Indicador } from '../../../components/ui'
import { Dispositivo, fechaHora, haceCuanto } from '../accesos/comun'
import '../../../styles/admin-accesos.css'

export default function UsuarioSesiones() {
  const { id } = useParams()
  const { can } = useAuth()

  const [data, setData] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState(null)
  const [aviso, setAviso] = useState(null)
  const [verTodas, setVerTodas] = useState(false)

  function cargar() {
    setCargando(true)
    api
      .get(`/admin/usuarios/${id}/sesiones`)
      .then((res) => setData(res.data))
      .catch(() => setError('No se pudieron cargar las sesiones.'))
      .finally(() => setCargando(false))
  }

  useEffect(() => {
    cargar()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id])

  async function cerrar(s) {
    if (s.es_actual) return
    if (!window.confirm('¿Cerrar esta sesión del usuario? Tendrá que iniciar sesión de nuevo en ese dispositivo.')) return
    setError(null)
    setAviso(null)
    try {
      await api.delete(`/admin/usuarios/${id}/sesiones/${s.id}`)
      setAviso('Sesión cerrada con éxito.')
      cargar()
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudo cerrar la sesión.')
    }
  }

  async function cerrarTodas() {
    if (!window.confirm('¿Cerrar todas las sesiones de este usuario? Tendrá que iniciar sesión de nuevo en todos sus dispositivos.')) return
    setError(null)
    setAviso(null)
    try {
      const res = await api.delete(`/admin/usuarios/${id}/sesiones`)
      setAviso(res.data.message)
      cargar()
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudieron cerrar las sesiones.')
    }
  }

  if (cargando && !data) {
    return <div className="tarjeta-simple"><span className="esqueleto" /><br /><span className="esqueleto" /></div>
  }
  if (!data) return <div className="alerta alerta--error">{error || 'No disponible.'}</div>

  const cerradas = data.historial.filter((h) => h.estado !== 1).length
  const ordenadas = [...data.sesiones].sort((a, b) => Number(b.es_actual) - Number(a.es_actual))
  const sesiones = verTodas ? ordenadas : ordenadas.slice(0, 8)

  return (
    <div>
      <Link to="/panel/usuarios" className="ac-volver"><Icono nombre="volver" size={15} /> Usuarios</Link>

      <div className="ac-cabecera">
        <Avatar nombre={data.usuario.name} size="lg" />
        <div className="ac-cabecera__datos">
          <h1 className="page-title">Sesiones de {data.usuario.name}</h1>
          <p>Dispositivos con la sesión abierta y los últimos inicios de sesión.</p>
        </div>
        <div className="ac-cabecera__accion">
          {can('usuario_update') && (
            <Link to={`/panel/usuarios/${id}/editar`} className="btn btn--ghost"><Icono nombre="editar" size={15} /> Editar usuario</Link>
          )}
          {can('usuario_cerrar_todas_sessiones') && data.total_activas > 0 && (
            <button className="btn btn--danger" onClick={cerrarTodas}>Cerrar todas</button>
          )}
        </div>
      </div>

      {error && <div className="alerta alerta--error">{error}</div>}
      {aviso && <div className="alerta alerta--ok">{aviso}</div>}

      <div className="indicadores">
        <Indicador icono="sesiones" etiqueta="Sesiones abiertas" valor={data.total_activas} />
        <Indicador icono="reloj" tono="azul" etiqueta="Última actividad" valor={haceCuanto(data.sesiones[0]?.ultima_actividad)} />
        <Indicador icono="entrada" tono="ok" etiqueta="Inicios recientes" valor={data.historial.length} nota={`${cerradas} ya cerrados`} />
      </div>

      <div className="ac-dos-columnas">
        <section className="tarjeta-simple">
          <h3 className="ac-titulo">Sesiones abiertas</h3>
          {data.sesiones.length === 0 ? (
            <EstadoVacio icono="sesiones" titulo="Sin sesiones abiertas" texto="El usuario no tiene ninguna sesión iniciada ahora mismo." />
          ) : (
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
                    can('usuario_cerrar_session') && (
                      <button className="btn btn--sm btn--danger" onClick={() => cerrar(s)}>Cerrar</button>
                    )
                  )}
                </li>
              ))}
            </ul>
          )}
          {ordenadas.length > 8 && (
            <button type="button" className="btn btn--ghost btn--sm ac-ver-mas" onClick={() => setVerTodas((v) => !v)}>
              <Icono nombre={verTodas ? 'volver' : 'flecha'} size={14} />
              {verTodas ? 'Ver menos' : `Ver las ${ordenadas.length} sesiones`}
            </button>
          )}
        </section>

        <section className="tarjeta-simple">
          <h3 className="ac-titulo">Historial reciente</h3>
          {data.historial.length === 0 ? (
            <EstadoVacio icono="reloj" titulo="Sin historial" texto="Aún no hay inicios de sesión registrados." />
          ) : (
            <ol className="ac-linea">
              {data.historial.map((h) => (
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
          )}
        </section>
      </div>
    </div>
  )
}
