import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../../api'

function fecha(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleString('es-SV')
}

export default function AuditoriaList() {
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [error, setError] = useState(null)

  function cargar() {
    setCargando(true)
    api
      .get('/admin/auditoria', { params: { search: search || undefined, page } })
      .then((res) => {
        setItems(res.data.data)
        setMeta(res.data.meta)
      })
      .catch(() => setError('No se pudo cargar la auditoría.'))
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

  return (
    <div>
      <h1 className="page-title">Auditoría</h1>
      {error && <div className="alerta alerta--error">{error}</div>}

      <form className="filtros" onSubmit={buscar}>
        <input
          placeholder="Buscar por usuario, entidad o fecha…"
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
              <th>Acción</th>
              <th>Entidad</th>
              <th>Nombre modificado</th>
              <th>Fecha</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {cargando && (
              <tr><td colSpan="6" className="tabla__vacio">Cargando…</td></tr>
            )}
            {!cargando && items.length === 0 && (
              <tr><td colSpan="6" className="tabla__vacio">Sin registros.</td></tr>
            )}
            {!cargando &&
              items.map((a) => (
                <tr key={a.id}>
                  <td>{a.usuario || '—'}</td>
                  <td>{a.accion || '—'}</td>
                  <td>{a.entidad || '—'}</td>
                  <td>{a.nombre_modificado || '—'}</td>
                  <td>{fecha(a.fecha)}</td>
                  <td className="tabla__acciones">
                    <Link to={`/panel/auditoria/${a.id}`} className="btn btn--sm btn--ghost">
                      Ver
                    </Link>
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
