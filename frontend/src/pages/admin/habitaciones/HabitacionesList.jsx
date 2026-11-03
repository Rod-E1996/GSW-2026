import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'

const COLOR_ESTADO = { disponible: 'badge--ok', limpieza: 'badge--aviso', mantenimiento: 'badge--danger' }

export default function HabitacionesList() {
  const { can } = useAuth()
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [catalogos, setCatalogos] = useState({ tipos: [], estados: [] })
  const [filtros, setFiltros] = useState({ numero: '', tipo_habitacion_id: '', estado_habitacion: '' })
  const [page, setPage] = useState(1)
  const [cargando, setCargando] = useState(true)
  const [mensaje, setMensaje] = useState(null)
  const [error, setError] = useState(null)

  function cargar() {
    setCargando(true)
    api
      .get('/admin/habitaciones', {
        params: {
          numero: filtros.numero || undefined,
          tipo_habitacion_id: filtros.tipo_habitacion_id || undefined,
          estado_habitacion: filtros.estado_habitacion || undefined,
          page,
        },
      })
      .then((res) => {
        setItems(res.data.data)
        setMeta(res.data.meta)
      })
      .catch(() => setError('No se pudieron cargar las habitaciones.'))
      .finally(() => setCargando(false))
  }

  useEffect(() => {
    cargar()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page])

  useEffect(() => {
    api.get('/admin/habitaciones/catalogos').then((res) => setCatalogos(res.data))
  }, [])

  function buscar(e) {
    e.preventDefault()
    if (page === 1) cargar()
    else setPage(1)
  }

  function setFiltro(campo, valor) {
    setFiltros((f) => ({ ...f, [campo]: valor }))
  }

  async function cambiarEstado(h, estado) {
    setMensaje(null)
    setError(null)
    try {
      await api.put(`/admin/habitaciones/${h.id}/estado`, { estado_habitacion: estado })
      setMensaje(`La habitación ${h.numero} quedó en ${estado}.`)
      cargar()
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudo cambiar el estado.')
    }
  }

  async function eliminar(h) {
    if (!confirm(`¿Eliminar la habitación ${h.numero}?`)) return
    setMensaje(null)
    setError(null)
    try {
      const res = await api.delete(`/admin/habitaciones/${h.id}`)
      setMensaje(res.data.message)
      cargar()
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudo eliminar.')
    }
  }

  return (
    <div>
      <div className="page-head">
        <h1 className="page-title">Habitaciones</h1>
        <div className="page-head__acciones">
          {can('bloqueo_index') && (
            <Link to="/panel/bloqueos" className="btn btn--ghost">
              Bloqueos
            </Link>
          )}
          {can('habitacion_store') && (
            <Link to="/panel/habitaciones/nueva" className="btn">
              + Nueva
            </Link>
          )}
        </div>
      </div>

      {mensaje && <div className="alerta alerta--ok">{mensaje}</div>}
      {error && <div className="alerta alerta--error">{error}</div>}

      <form className="filtros" onSubmit={buscar}>
        <input
          placeholder="Buscar por número…"
          value={filtros.numero}
          onChange={(e) => setFiltro('numero', e.target.value)}
        />
        <select value={filtros.tipo_habitacion_id} onChange={(e) => setFiltro('tipo_habitacion_id', e.target.value)}>
          <option value="">Todos los tipos</option>
          {catalogos.tipos.map((t) => (
            <option key={t.id} value={t.id}>{t.nombre}</option>
          ))}
        </select>
        <select value={filtros.estado_habitacion} onChange={(e) => setFiltro('estado_habitacion', e.target.value)}>
          <option value="">Todos los estados</option>
          {catalogos.estados.map((s) => (
            <option key={s.valor} value={s.valor}>{s.nombre}</option>
          ))}
        </select>
        <button className="btn btn--ghost">Buscar</button>
      </form>

      <div className="tabla-wrap">
        <table className="tabla">
          <thead>
            <tr>
              <th>Número</th>
              <th>Piso</th>
              <th>Tipo</th>
              <th>Estado</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {cargando && (
              <tr><td colSpan="5" className="tabla__vacio">Cargando…</td></tr>
            )}
            {!cargando && items.length === 0 && (
              <tr><td colSpan="5" className="tabla__vacio">Sin registros.</td></tr>
            )}
            {!cargando &&
              items.map((h) => (
                <tr key={h.id}>
                  <td>{h.numero}</td>
                  <td>{h.piso}</td>
                  <td>{h.tipo}</td>
                  <td>
                    {can('habitacion_estado') ? (
                      <select
                        className="tabla__select"
                        aria-label={`Estado de la habitación ${h.numero}`}
                        value={h.estado_habitacion}
                        onChange={(e) => cambiarEstado(h, e.target.value)}
                      >
                        {catalogos.estados.map((s) => (
                          <option key={s.valor} value={s.valor}>{s.nombre}</option>
                        ))}
                      </select>
                    ) : (
                      <span className={`badge ${COLOR_ESTADO[h.estado_habitacion] || ''}`}>{h.estado_nombre}</span>
                    )}
                  </td>
                  <td className="tabla__acciones">
                    {can('bloqueo_store') && (
                      <Link to={`/panel/bloqueos?habitacion=${h.id}`} className="btn btn--sm btn--ghost">
                        Bloquear
                      </Link>
                    )}
                    {can('habitacion_update') && (
                      <Link to={`/panel/habitaciones/${h.id}/editar`} className="btn btn--sm btn--ghost">
                        Editar
                      </Link>
                    )}
                    {can('habitacion_destroy') && (
                      <button className="btn btn--sm btn--danger" onClick={() => eliminar(h)}>
                        Eliminar
                      </button>
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
          <span>
            Página {meta.current_page} de {meta.last_page}
          </span>
          <button
            className="btn btn--ghost"
            disabled={page >= meta.last_page}
            onClick={() => setPage((p) => p + 1)}
          >
            Siguiente →
          </button>
        </div>
      )}
    </div>
  )
}
