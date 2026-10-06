import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'

export default function UsuariosList() {
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
      .get('/admin/usuarios', { params: { search: search || undefined, page } })
      .then((res) => {
        setItems(res.data.data)
        setMeta(res.data.meta)
      })
      .catch(() => setError('No se pudieron cargar los usuarios.'))
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

  async function cambiarEstado(u) {
    const accion = u.estado === 1 ? 'desactivar' : 'activar'
    if (!window.confirm(`¿Seguro que deseas ${accion} a "${u.name}"?`)) return
    setError(null)
    setAviso(null)
    try {
      await api.put(`/admin/usuarios/${u.id}/estado`)
      setAviso(`Usuario ${accion === 'activar' ? 'activado' : 'desactivado'} con éxito.`)
      cargar()
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudo cambiar el estado.')
    }
  }

  return (
    <div>
      <div className="page-head">
        <h1 className="page-title">Usuarios</h1>
        {can('usuario_create') && (
          <Link to="/panel/usuarios/nuevo" className="btn">+ Nuevo usuario</Link>
        )}
      </div>

      {error && <div className="alerta alerta--error">{error}</div>}
      {aviso && <div className="alerta alerta--ok">{aviso}</div>}

      <form className="filtros" onSubmit={buscar}>
        <input
          placeholder="Buscar por nombre o email…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
        <button className="btn btn--ghost">Buscar</button>
      </form>

      <div className="tabla-wrap">
        <table className="tabla">
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Email</th>
              <th>Roles</th>
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
              items.map((u) => (
                <tr key={u.id}>
                  <td>{u.name}</td>
                  <td>{u.email}</td>
                  <td>{u.roles.length ? u.roles.join(', ') : '—'}</td>
                  <td>
                    <span className={`badge ${u.estado === 1 ? 'badge--ok' : 'badge--danger'}`}>
                      {u.estado === 1 ? 'Activo' : 'Inactivo'}
                    </span>
                  </td>
                  <td className="tabla__acciones">
                    {can('usuario_update') && (
                      <Link to={`/panel/usuarios/${u.id}/editar`} className="btn btn--sm btn--ghost">
                        Editar
                      </Link>
                    )}
                    {can('usuario_estado') && (
                      <button
                        className={`btn btn--sm ${u.estado === 1 ? 'btn--danger' : ''}`}
                        onClick={() => cambiarEstado(u)}
                      >
                        {u.estado === 1 ? 'Desactivar' : 'Activar'}
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
