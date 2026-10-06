import { useEffect, useState } from 'react'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'

function fecha(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('es-SV')
}

export default function SesionesList() {
  const { can } = useAuth()
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [stats, setStats] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [error, setError] = useState(null)
  const [aviso, setAviso] = useState(null)

  function cargar() {
    setCargando(true)
    api
      .get('/admin/sesiones', { params: { search: search || undefined, page } })
      .then((res) => {
        setItems(res.data.data)
        setMeta(res.data.meta)
        setStats(res.data.stats)
      })
      .catch(() => setError('No se pudieron cargar las sesiones.'))
      .finally(() => setCargando(false))
  }

  useEffect(() => {
    cargar()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page])

  function buscar(e) {
    e.preventDefault()
    setPage(1)
    cargar()
  }

  async function cerrar(s) {
    if (s.es_actual) return
    if (!window.confirm('¿Cerrar esta sesión? El usuario deberá iniciar sesión de nuevo.')) return
    setError(null)
    setAviso(null)
    try {
      await api.delete(`/admin/sesiones/${s.id}`)
      setAviso('Sesión cerrada con éxito.')
      cargar()
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudo cerrar la sesión.')
    }
  }

  return (
    <div>
      <h1 className="page-title">Sesiones activas</h1>

      {error && <div className="alerta alerta--error">{error}</div>}
      {aviso && <div className="alerta alerta--ok">{aviso}</div>}

      {stats && (
        <div className="cards-info">
          <div className="info-card">
            <span className="info-card__label">Sesiones activas</span>
            <span className="info-card__value">{stats.total_activas}</span>
          </div>
          <div className="info-card">
            <span className="info-card__label">Usuarios conectados</span>
            <span className="info-card__value">{stats.usuarios_conectados}</span>
          </div>
        </div>
      )}

      <form className="filtros" onSubmit={buscar}>
        <input
          placeholder="Buscar por usuario o email…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
        <button className="btn btn--ghost">Buscar</button>
      </form>

      <div className="tabla-wrap">
        <table className="tabla">
          <thead>
            <tr>
              <th>Usuario</th>
              <th>Dispositivo</th>
              <th>IP</th>
              <th>Última actividad</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {cargando && (
              <tr><td colSpan="5" className="tabla__vacio">Cargando…</td></tr>
            )}
            {!cargando && items.length === 0 && (
              <tr><td colSpan="5" className="tabla__vacio">Sin sesiones activas.</td></tr>
            )}
            {!cargando &&
              items.map((s) => (
                <tr key={s.id}>
                  <td>{s.usuario || '—'}</td>
                  <td>{s.device_type_es} · {s.browser || 'Desconocido'}</td>
                  <td>{s.ip_address}</td>
                  <td>{fecha(s.ultima_actividad)}</td>
                  <td className="tabla__acciones">
                    {s.es_actual ? (
                      <span className="badge badge--ok">Esta sesión</span>
                    ) : (
                      can('session_cerrar') && (
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

      {meta && meta.last_page > 1 && (
        <div className="paginacion">
          <button className="btn btn--ghost" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
            ← Anterior
          </button>
          <span>Página {meta.current_page} de {meta.last_page}</span>
          <button className="btn btn--ghost" disabled={page >= meta.last_page} onClick={() => setPage((p) => p + 1)}>
            Siguiente →
          </button>
        </div>
      )}
    </div>
  )
}
