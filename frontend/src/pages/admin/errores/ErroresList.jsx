import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'

function fecha(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleString('es-SV')
}

export default function ErroresList() {
  const { can } = useAuth()
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [error, setError] = useState(null)
  const [aviso, setAviso] = useState(null)
  const [generando, setGenerando] = useState(false)

  function cargar() {
    setCargando(true)
    api
      .get('/admin/error-logs', { params: { search: search || undefined, page } })
      .then((res) => {
        setItems(res.data.data)
        setMeta(res.data.meta)
      })
      .catch(() => setError('No se pudo cargar el error log.'))
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

  async function resolver(id) {
    if (!window.confirm('¿Marcar este error como resuelto?')) return
    setError(null)
    try {
      await api.put(`/admin/error-logs/${id}/estado`)
      setAviso('Error marcado como resuelto.')
      cargar()
    } catch {
      setError('No se pudo actualizar el estado.')
    }
  }

  async function generarPrueba() {
    setError(null)
    setGenerando(true)
    try {
      await api.post('/admin/error-logs/prueba')
      setAviso('Registro de prueba generado.')
      setPage(1)
      cargar()
    } catch {
      setError('No se pudo generar el registro de prueba.')
    } finally {
      setGenerando(false)
    }
  }

  return (
    <div>
      <div className="page-head">
        <h1 className="page-title">Error logs</h1>
        {can('error_log_create') && (
          <button className="btn" onClick={generarPrueba} disabled={generando}>
            {generando ? 'Generando…' : '+ Registro de prueba'}
          </button>
        )}
      </div>

      {error && <div className="alerta alerta--error">{error}</div>}
      {aviso && <div className="alerta alerta--ok">{aviso}</div>}

      <form className="filtros" onSubmit={buscar}>
        <input
          placeholder="Buscar por usuario, controller, mensaje…"
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
              <th>Controller</th>
              <th>Mensaje</th>
              <th>Fecha</th>
              <th>Estado</th>
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
              items.map((e) => (
                <tr key={e.id}>
                  <td>{e.usuario || '(Sin usuario)'}</td>
                  <td className="celda-corta">{e.controller || '—'}</td>
                  <td className="celda-corta">{e.mensaje || '—'}</td>
                  <td>{fecha(e.fecha)}</td>
                  <td>
                    <span className={`badge ${e.estado === 1 ? 'badge--ok' : 'badge--danger'}`}>
                      {e.estado === 1 ? 'Resuelto' : 'Sin resolver'}
                    </span>
                  </td>
                  <td className="tabla__acciones">
                    <Link to={`/panel/error-logs/${e.id}`} className="btn btn--sm btn--ghost">
                      Ver
                    </Link>
                    {e.estado === 0 && can('error_log_estado') && (
                      <button className="btn btn--sm" onClick={() => resolver(e.id)}>
                        Resolver
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
          <span>Página {meta.current_page} de {meta.last_page}</span>
          <button className="btn btn--ghost" disabled={page >= meta.last_page} onClick={() => setPage((p) => p + 1)}>
            Siguiente →
          </button>
        </div>
      )}
    </div>
  )
}
