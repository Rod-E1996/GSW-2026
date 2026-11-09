import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api } from '../../../api'
import Icono from '../../../components/Icono'
import { Avatar, FilaVacia, FilasCargando } from '../../../components/ui'
import { COLOR_ACCION, Paginacion, fechaHora, haceCuanto, nombreEntidad } from '../accesos/comun'
import '../../../styles/admin-accesos.css'

export default function AuditoriaList() {
  const navigate = useNavigate()
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
      <div className="page-head">
        <div>
          <h1 className="page-title">Auditoría</h1>
          <p className="page-sub">Registro de quién creó, modificó o eliminó cada dato del sistema. Abre un registro para ver qué cambió.</p>
        </div>
      </div>

      {error && <div className="alerta alerta--error">{error}</div>}

      <form className="filtros" onSubmit={buscar}>
        <input
          className="filtros__busqueda"
          placeholder="Buscar por usuario, tabla (p. ej. reservas) o fecha (AAAA-MM-DD)…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          aria-label="Buscar en la auditoría"
        />
        <button className="btn btn--ghost">Buscar</button>
      </form>

      <div className="tabla-wrap">
        <table className="tabla ac-tabla-clic">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Usuario</th>
              <th>Acción</th>
              <th>Entidad</th>
              <th>Registro</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {cargando && <FilasCargando columnas={6} />}
            {!cargando && items.length === 0 && (
              <FilaVacia
                columnas={6}
                icono="auditoria"
                titulo={search ? 'Ningún registro coincide' : 'Aún no hay registros'}
                texto={search ? 'Prueba con otro usuario, otra tabla u otra fecha.' : 'Cada cambio en los datos quedará registrado aquí.'}
              />
            )}
            {!cargando &&
              items.map((a) => (
                <tr key={a.id} onClick={() => navigate(`/panel/auditoria/${a.id}`)}>
                  <td>
                    <div className="ac-fecha">
                      <strong>{haceCuanto(a.fecha)}</strong>
                      <small>{fechaHora(a.fecha)}</small>
                    </div>
                  </td>
                  <td>
                    {a.usuario ? (
                      <div className="celda-entidad"><Avatar nombre={a.usuario} size="sm" /><div><strong>{a.usuario}</strong></div></div>
                    ) : (
                      <span className="form-nota">Sistema</span>
                    )}
                  </td>
                  <td>
                    <span className={`badge ${COLOR_ACCION[a.accion] || ''}`}>
                      {a.accion ? a.accion.charAt(0).toUpperCase() + a.accion.slice(1) : '—'}
                    </span>
                  </td>
                  <td>
                    <div className="ac-fecha">
                      <strong>{nombreEntidad(a.entidad)}</strong>
                      <small className="ac-mono">{a.entidad}</small>
                    </div>
                  </td>
                  <td className="celda-corta">{a.nombre_modificado || <span className="form-nota">—</span>}</td>
                  <td className="tabla__acciones">
                    <Link to={`/panel/auditoria/${a.id}`} className="ac-ver" aria-label="Ver detalle" onClick={(e) => e.stopPropagation()}><Icono nombre="flecha" size={16} /></Link>
                  </td>
                </tr>
              ))}
          </tbody>
        </table>
      </div>

      <Paginacion meta={meta} page={page} setPage={setPage} nombre="registros" />
    </div>
  )
}
