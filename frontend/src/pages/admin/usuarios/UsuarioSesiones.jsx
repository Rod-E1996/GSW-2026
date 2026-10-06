import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'

function fecha(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('es-SV')
}

export default function UsuarioSesiones() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { can } = useAuth()

  const [data, setData] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState(null)
  const [aviso, setAviso] = useState(null)

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
    if (!window.confirm('¿Cerrar esta sesión del usuario?')) return
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
    if (!window.confirm('¿Cerrar todas las sesiones de este usuario?')) return
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

  if (cargando) return <p className="estado">Cargando…</p>
  if (!data) return <div className="alerta alerta--error">{error || 'No disponible.'}</div>

  return (
    <div>
      <div className="page-head">
        <h1 className="page-title">Sesiones de {data.usuario.name}</h1>
        <button className="btn btn--ghost" onClick={() => navigate('/panel/usuarios')}>
          ← Volver
        </button>
      </div>

      {error && <div className="alerta alerta--error">{error}</div>}
      {aviso && <div className="alerta alerta--ok">{aviso}</div>}

      <div className="page-head">
        <h2 className="seccion-title">Activas ({data.total_activas})</h2>
        {can('usuario_cerrar_todas_sessiones') && data.total_activas > 0 && (
          <button className="btn btn--sm btn--danger" onClick={cerrarTodas}>
            Cerrar todas
          </button>
        )}
      </div>

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
            {data.sesiones.length === 0 && (
              <tr><td colSpan="4" className="tabla__vacio">Sin sesiones activas.</td></tr>
            )}
            {data.sesiones.map((s) => (
              <tr key={s.id}>
                <td>{s.device_type_es} · {s.browser || 'Desconocido'}</td>
                <td>{s.ip_address}</td>
                <td>{fecha(s.ultima_actividad)}</td>
                <td className="tabla__acciones">
                  {s.es_actual ? (
                    <span className="badge badge--ok">Esta sesión</span>
                  ) : (
                    can('usuario_cerrar_session') && (
                      <button className="btn btn--sm btn--danger" onClick={() => cerrar(s)}>
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

      <h2 className="seccion-title">Historial reciente</h2>
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
            {data.historial.length === 0 && (
              <tr><td colSpan="4" className="tabla__vacio">Sin historial.</td></tr>
            )}
            {data.historial.map((h) => (
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
