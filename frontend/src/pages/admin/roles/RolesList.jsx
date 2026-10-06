import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'

export default function RolesList() {
  const { can } = useAuth()
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [error, setError] = useState(null)
  const [aviso, setAviso] = useState(null)

  function cargar() {
    setCargando(true)
    api
      .get('/admin/roles', { params: { search: search || undefined, page } })
      .then((res) => {
        setItems(res.data.data)
        setMeta(res.data.meta)
      })
      .catch(() => setError('No se pudieron cargar los roles.'))
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

  async function eliminar(r) {
    if (!window.confirm(`¿Eliminar el rol "${r.name}"?`)) return
    setError(null)
    setAviso(null)
    try {
      const res = await api.delete(`/admin/roles/${r.id}`)
      setAviso(res.data.message || 'Rol eliminado.')
      cargar()
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudo eliminar el rol.')
    }
  }

  return (
    <div>
      <div className="page-head">
        <h1 className="page-title">Roles</h1>
        {can('role_create') && (
          <Link to="/panel/roles/nuevo" className="btn">+ Nuevo rol</Link>
        )}
      </div>

      {error && <div className="alerta alerta--error">{error}</div>}
      {aviso && <div className="alerta alerta--ok">{aviso}</div>}

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
              <th>Rol</th>
              <th>Permisos</th>
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
              items.map((r) => (
                <tr key={r.id}>
                  <td>{r.name}</td>
                  <td>{r.permisos_count ?? 0}</td>
                  <td className="tabla__acciones">
                    {can('role_move_permiso') && (
                      <Link to={`/panel/roles/${r.id}/permisos`} className="btn btn--sm btn--ghost">
                        Permisos
                      </Link>
                    )}
                    {can('role_update') && (
                      <Link to={`/panel/roles/${r.id}/editar`} className="btn btn--sm btn--ghost">
                        Editar
                      </Link>
                    )}
                    {can('role_destroy') && (
                      <button className="btn btn--sm btn--danger" onClick={() => eliminar(r)}>
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
