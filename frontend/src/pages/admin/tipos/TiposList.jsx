import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import { rutaImagen, formatoUSD } from '../../../utils'

export default function TiposList() {
  const { can } = useAuth()
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [nombre, setNombre] = useState('')
  const [page, setPage] = useState(1)
  const [mensaje, setMensaje] = useState(null)
  const [error, setError] = useState(null)

  function cargar() {
    setCargando(true)
    api
      .get('/admin/tipos-habitacion', { params: { nombre: nombre || undefined, page } })
      .then((res) => {
        setItems(res.data.data)
        setMeta(res.data.meta)
      })
      .catch(() => setError('No se pudieron cargar los tipos de habitación.'))
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

  async function eliminar(tipo) {
    if (!confirm(`¿Eliminar "${tipo.nombre}"?`)) return
    try {
      const res = await api.delete(`/admin/tipos-habitacion/${tipo.id}`)
      setMensaje(res.data.message)
      setError(null)
      cargar()
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudo eliminar.')
    }
  }

  return (
    <div>
      <div className="page-head">
        <h1 className="page-title">Tipos de habitación</h1>
        {can('tipo_habitacion_store') && (
          <Link to="/panel/tipos-habitacion/nuevo" className="btn">
            + Nuevo
          </Link>
        )}
      </div>

      {mensaje && <div className="alerta alerta--ok">{mensaje}</div>}
      {error && <div className="alerta alerta--error">{error}</div>}

      <form className="filtros" onSubmit={buscar}>
        <input
          placeholder="Buscar por nombre…"
          value={nombre}
          onChange={(e) => setNombre(e.target.value)}
        />
        <button className="btn btn--ghost">Buscar</button>
      </form>

      <div className="tabla-wrap">
        <table className="tabla">
          <thead>
            <tr>
              <th></th>
              <th>Nombre</th>
              <th>Capacidad</th>
              <th>Precio base</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {cargando && (
              <tr>
                <td colSpan="5" className="tabla__vacio">Cargando…</td>
              </tr>
            )}
            {!cargando && items.length === 0 && (
              <tr>
                <td colSpan="5" className="tabla__vacio">Sin registros.</td>
              </tr>
            )}
            {!cargando &&
              items.map((t) => (
                <tr key={t.id}>
                  <td>
                    {rutaImagen(t.imagen_principal) ? (
                      <img className="thumb" src={rutaImagen(t.imagen_principal)} alt={t.nombre} />
                    ) : (
                      <div className="thumb thumb--vacio">—</div>
                    )}
                  </td>
                  <td>{t.nombre}</td>
                  <td>{t.capacidad}</td>
                  <td>{formatoUSD(t.precio_base)}</td>
                  <td className="tabla__acciones">
                    {can('tipo_habitacion_update') && (
                      <Link to={`/panel/tipos-habitacion/${t.id}/editar`} className="btn btn--sm btn--ghost">
                        Editar
                      </Link>
                    )}
                    {can('tipo_habitacion_destroy') && (
                      <button className="btn btn--sm btn--danger" onClick={() => eliminar(t)}>
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
