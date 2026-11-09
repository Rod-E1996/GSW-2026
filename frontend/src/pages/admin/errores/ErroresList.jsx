import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import Icono from '../../../components/Icono'
import { FilaVacia, FilasCargando } from '../../../components/ui'
import { Paginacion, controllerCorto, fechaHora, haceCuanto } from '../accesos/comun'
import '../../../styles/admin-accesos.css'

export default function ErroresList() {
  const { can } = useAuth()
  const navigate = useNavigate()
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
    setAviso(null)
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
    setAviso(null)
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

  const pendientes = items.filter((e) => e.estado === 0).length

  return (
    <div>
      <div className="page-head">
        <div>
          <h1 className="page-title">Error logs</h1>
          <p className="page-sub">Errores capturados por el sistema. Los que siguen sin resolver aparecen primero.</p>
        </div>
        {can('error_log_create') && (
          <button className="btn btn--ghost" onClick={generarPrueba} disabled={generando}>
            <Icono nombre="mas" size={15} /> {generando ? 'Generando…' : 'Registro de prueba'}
          </button>
        )}
      </div>

      {error && <div className="alerta alerta--error">{error}</div>}
      {aviso && <div className="alerta alerta--ok">{aviso}</div>}
      {!cargando && page === 1 && !search && items.length > 0 && pendientes === 0 && (
        <div className="alerta alerta--ok">No hay errores pendientes: todos los registros están resueltos.</div>
      )}

      <form className="filtros" onSubmit={buscar}>
        <input
          className="filtros__busqueda"
          placeholder="Buscar por usuario, controlador, mensaje o parámetros…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          aria-label="Buscar error"
        />
        <button className="btn btn--ghost">Buscar</button>
      </form>

      <div className="tabla-wrap">
        <table className="tabla ac-tabla-clic">
          <thead>
            <tr>
              <th>Estado</th>
              <th>Error</th>
              <th>Usuario</th>
              <th>Fecha</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {cargando && <FilasCargando columnas={5} />}
            {!cargando && items.length === 0 && (
              <FilaVacia
                columnas={5}
                icono="check"
                titulo={search ? 'Ningún error coincide' : 'Sin errores registrados'}
                texto={search ? 'Prueba con otro texto.' : 'Cuando el sistema capture un error aparecerá aquí.'}
              />
            )}
            {!cargando &&
              items.map((e) => (
                <tr key={e.id} className={e.estado === 0 ? 'ac-fila-pendiente' : ''} onClick={() => navigate(`/panel/error-logs/${e.id}`)}>
                  <td>
                    <span className={`badge ${e.estado === 1 ? 'badge--ok' : 'badge--danger'}`}>
                      {e.estado === 1 ? 'Resuelto' : 'Sin resolver'}
                    </span>
                  </td>
                  <td className="ac-celda-error">
                    <strong>{e.mensaje || 'Sin mensaje'}</strong>
                    <small className="ac-mono">{controllerCorto(e.controller)}</small>
                  </td>
                  <td>{e.usuario || <span className="form-nota">Sin usuario</span>}</td>
                  <td>
                    <div className="ac-fecha">
                      <strong>{haceCuanto(e.fecha)}</strong>
                      <small>{fechaHora(e.fecha)}</small>
                    </div>
                  </td>
                  <td className="tabla__acciones" onClick={(ev) => ev.stopPropagation()}>
                    {e.estado === 0 && can('error_log_estado') && (
                      <button className="btn btn--sm btn--ghost" onClick={() => resolver(e.id)}>
                        <Icono nombre="check" size={14} /> Resolver
                      </button>
                    )}
                    <Link to={`/panel/error-logs/${e.id}`} className="ac-ver" aria-label="Ver detalle"><Icono nombre="flecha" size={16} /></Link>
                  </td>
                </tr>
              ))}
          </tbody>
        </table>
      </div>

      <Paginacion meta={meta} page={page} setPage={setPage} nombre="errores" />
    </div>
  )
}
