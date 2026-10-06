import { useEffect, useState } from 'react'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'

export default function PermisosList() {
  const { can } = useAuth()
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [error, setError] = useState(null)
  const [aviso, setAviso] = useState(null)

  const [nuevo, setNuevo] = useState('')
  const [creando, setCreando] = useState(false)

  const puedeGestionar = can('permiso_move')

  function cargar() {
    setCargando(true)
    api
      .get('/admin/permisos', { params: { search: search || undefined, page } })
      .then((res) => {
        setItems(res.data.data)
        setMeta(res.data.meta)
      })
      .catch(() => setError('No se pudieron cargar los permisos.'))
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

  async function crear(e) {
    e.preventDefault()
    if (!nuevo.trim()) return
    setError(null)
    setAviso(null)
    setCreando(true)
    try {
      await api.post('/admin/permisos', { name: nuevo.trim() })
      setAviso('Permiso creado con éxito.')
      setNuevo('')
      setPage(1)
      cargar()
    } catch (err) {
      const data = err.response?.data
      setError(data?.errors?.name?.[0] || data?.message || 'No se pudo crear el permiso.')
    } finally {
      setCreando(false)
    }
  }

  async function eliminar(p) {
    if (!window.confirm(`¿Eliminar el permiso "${p.name}"?`)) return
    setError(null)
    setAviso(null)
    try {
      const res = await api.delete(`/admin/permisos/${p.id}`)
      setAviso(res.data.message || 'Permiso eliminado.')
      cargar()
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudo eliminar el permiso.')
    }
  }

  return (
    <div>
      <h1 className="page-title">Permisos</h1>

      {error && <div className="alerta alerta--error">{error}</div>}
      {aviso && <div className="alerta alerta--ok">{aviso}</div>}

      {puedeGestionar && (
        <form className="filtros" onSubmit={crear}>
          <input
            placeholder="Nombre del nuevo permiso…"
            value={nuevo}
            onChange={(e) => setNuevo(e.target.value)}
          />
          <button className="btn" disabled={creando}>
            {creando ? 'Creando…' : '+ Crear permiso'}
          </button>
        </form>
      )}

      <form className="filtros" onSubmit={buscar}>
        <input
          placeholder="Buscar por nombre…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
        <button className="btn btn--ghost">Buscar</button>
      </form>

      <div className="tabla-wrap">
        <table className="tabla">
          <thead>
            <tr>
              <th>Permiso</th>
              <th>Roles que lo usan</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {cargando && (
              <tr><td colSpan="3" className="tabla__vacio">Cargando…</td></tr>
            )}
            {!cargando && items.length === 0 && (
              <tr><td colSpan="3" className="tabla__vacio">Sin registros.</td></tr>
            )}
            {!cargando &&
              items.map((p) => (
                <tr key={p.id}>
                  <td>{p.name}</td>
                  <td>{p.roles_count ?? 0}</td>
                  <td className="tabla__acciones">
                    {puedeGestionar && (
                      <button
                        className="btn btn--sm btn--danger"
                        onClick={() => eliminar(p)}
                        disabled={(p.roles_count ?? 0) > 0}
                        title={(p.roles_count ?? 0) > 0 ? 'Está asignado a uno o más roles' : 'Eliminar'}
                      >
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
          <span>Página {meta.current_page} de {meta.last_page}</span>
          <button className="btn btn--ghost" disabled={page >= meta.last_page} onClick={() => setPage((p) => p + 1)}>
            Siguiente →
          </button>
        </div>
      )}
    </div>
  )
}
