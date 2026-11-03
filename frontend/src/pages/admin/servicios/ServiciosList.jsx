import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import { formatoUSD, rutaImagen } from '../../../utils'

export default function ServiciosList() {
  const { can } = useAuth()
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [categorias, setCategorias] = useState([])
  const [nombre, setNombre] = useState('')
  const [categoria, setCategoria] = useState('')
  const [page, setPage] = useState(1)
  const [cargando, setCargando] = useState(true)
  const [mensaje, setMensaje] = useState(null)
  const [error, setError] = useState(null)

  function cargar() {
    setCargando(true)
    api
      .get('/admin/servicios', {
        params: { nombre: nombre || undefined, categoria_servicio_id: categoria || undefined, page },
      })
      .then((res) => {
        setItems(res.data.data)
        setMeta(res.data.meta)
      })
      .catch(() => setError('No se pudieron cargar los servicios.'))
      .finally(() => setCargando(false))
  }

  useEffect(() => {
    cargar()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page])

  useEffect(() => {
    api.get('/admin/categorias-servicio').then((res) => setCategorias(res.data.data))
  }, [])

  function buscar(e) {
    e.preventDefault()
    if (page === 1) cargar()
    else setPage(1)
  }

  async function eliminar(s) {
    if (!confirm(`¿Eliminar "${s.nombre}"?`)) return
    setMensaje(null)
    setError(null)
    try {
      const res = await api.delete(`/admin/servicios/${s.id}`)
      setMensaje(res.data.message)
      cargar()
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudo eliminar.')
    }
  }

  return (
    <div>
      <div className="page-head">
        <h1 className="page-title">Servicios</h1>
        <div className="page-head__acciones">
          <Link to="/panel/servicios/categorias" className="btn btn--ghost">
            Categorías
          </Link>
          {can('servicio_store') && (
            <Link to="/panel/servicios/nuevo" className="btn">
              + Nuevo
            </Link>
          )}
        </div>
      </div>

      {mensaje && <div className="alerta alerta--ok">{mensaje}</div>}
      {error && <div className="alerta alerta--error">{error}</div>}

      <form className="filtros" onSubmit={buscar}>
        <input placeholder="Buscar por nombre…" value={nombre} onChange={(e) => setNombre(e.target.value)} />
        <select aria-label="Filtrar por categoría" value={categoria} onChange={(e) => setCategoria(e.target.value)}>
          <option value="">Todas las categorías</option>
          {categorias.map((c) => (
            <option key={c.id} value={c.id}>{c.nombre}</option>
          ))}
        </select>
        <button className="btn btn--ghost">Buscar</button>
      </form>

      <div className="tabla-wrap">
        <table className="tabla">
          <thead>
            <tr>
              <th></th>
              <th>Nombre</th>
              <th>Categoría</th>
              <th>Precio</th>
              <th>Forma de cobro</th>
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
              items.map((s) => (
                <tr key={s.id}>
                  <td>
                    {s.imagen_url ? (
                      <img className="thumb" src={rutaImagen(s.imagen_url)} alt={s.nombre} />
                    ) : (
                      <div className="thumb thumb--vacio">—</div>
                    )}
                  </td>
                  <td>{s.nombre}</td>
                  <td>{s.categoria}</td>
                  <td>{formatoUSD(s.precio)}</td>
                  <td>{s.forma_cobro_nombre}</td>
                  <td className="tabla__acciones">
                    {can('servicio_update') && (
                      <Link to={`/panel/servicios/${s.id}/editar`} className="btn btn--sm btn--ghost">
                        Editar
                      </Link>
                    )}
                    {can('servicio_destroy') && (
                      <button className="btn btn--sm btn--danger" onClick={() => eliminar(s)}>
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
